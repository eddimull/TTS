<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Casts\Price;

class Payout extends Model
{
    use HasFactory, SoftDeletes;

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
        $calculationResult = $this->calculation_result;
        return round(\collect($calculationResult['member_payouts'])->firstWhere('user_id', $user->id)['amount'] ?? 0.0, 2);
    }
}
