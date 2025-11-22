<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Casts\Price;

class PayoutAdjustment extends Model
{
    use HasFactory, SoftDeletes;

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
}
