<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Casts\Price;

class PayoutAdjustment extends Model
{
    use HasFactory, SoftDeletes, BroadcastsBandChanges;

    protected $fillable = [
        'payout_id',
        'created_by',
        'amount',
        'description',
        'notes',
    ];

    protected $casts = [
        'amount' => Price::class,
    ];

    protected $with = ['creator'];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function broadcastBandId(): ?int
    {
        $bandId = $this->payout?->band_id;

        return $bandId ? (int) $bandId : null;
    }

    protected function broadcastParent(): ?array
    {
        return $this->payout && $this->payout->payable_type === \App\Models\Bookings::class
            ? ['model' => 'bookings', 'id' => (int) $this->payout->payable_id]
            : null;
    }
}
