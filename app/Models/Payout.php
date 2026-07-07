<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Casts\Price;

class Payout extends Model
{
    use HasFactory, SoftDeletes, BroadcastsBandChanges;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'band_id',
        'payout_config_id',
        'base_amount',
        'adjusted_amount',
        'calculation_result',
    ];

    protected $casts = [
        'base_amount' => Price::class,
        'adjusted_amount' => Price::class,
        'calculation_result' => 'array',
    ];

    protected $with = ['adjustments'];

    /**
     * calculation_result is a derived cache rewritten by READ paths (the
     * Payout page GET recomputes and stores it on every render). Broadcasting
     * it creates an infinite loop: signal -> client partial reload ->
     * controller recomputes + saves -> signal.
     *
     * payout_config_id is deliberately NOT ignored: switching a booking's
     * payout configuration is a user mutation other clients must see. The
     * payout-page GET rewrites it every render, but once converged it
     * reassigns the SAME id (no dirty diff, no signal) — loop safety rests
     * on that idempotence, not on the write being one-time. A render only
     * re-broadcasts when the resolved config genuinely changes (first
     * adoption, or the stored config vanishing and the fallback adopting
     * the active one) — a single settling signal, never a cycle.
     */
    protected function broadcastIgnoreDirty(): array
    {
        return ['calculation_result'];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    public function payoutConfig(): BelongsTo
    {
        return $this->belongsTo(BandPayoutConfig::class, 'payout_config_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayoutAdjustment::class);
    }

    protected function broadcastParent(): ?array
    {
        return $this->payable_type === \App\Models\Bookings::class
            ? ['model' => 'bookings', 'id' => (int) $this->payable_id]
            : null;
    }

    /**
     * Recalculate the adjusted amount based on base amount and adjustments
     */
    public function recalculateAdjustedAmount(): void
    {
        // Get base amount in CENTS (raw DB value before cast)
        $baseAmountCents = $this->getAttributes()['base_amount'];
        
        // Sum adjustments in CENTS (raw DB values before cast)
        $adjustmentTotalCents = $this->adjustments()->sum('amount');
        
        // Store adjusted amount in CENTS
        $this->attributes['adjusted_amount'] = $baseAmountCents + $adjustmentTotalCents;
        $this->save();
    }

    /**
     * Get the adjusted amount as a float (in dollars)
     */
    public function getAdjustedAmountFloatAttribute(): float
    {
        // The adjusted_amount cast already converts from cents to dollars
        $amount = $this->adjusted_amount;
        return is_string($amount) ? floatval($amount) : $amount;
    }

    /**
     * Get the payout amount for one user
     */
    public function getPayoutAmountForUser(User $user): float
    {
        return static::amountForUserInResult($this->calculation_result, $user);
    }

    /**
     * Same matching rules against an arbitrary (e.g. freshly computed)
     * calculation result, so read surfaces don't depend on the stored cache.
     */
    public static function amountForUserInResult(?array $result, User $user): float
    {
        $payouts = collect($result['member_payouts'] ?? []);

        $match = $payouts->firstWhere('user_id', $user->id)
            ?? $payouts->first(fn($p) => $p['user_id'] === null && ($p['name'] ?? '') === $user->name);

        return round($match['amount'] ?? 0.0, 2);
    }
}
