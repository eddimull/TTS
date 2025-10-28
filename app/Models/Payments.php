<?php

namespace App\Models;

use App\Casts\Price;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payments extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'payments';

    protected $fillable = ['name', 'amount', 'date', 'band_id', 'user_id', 'status', 'invoices_id', 'payable_type', 'payable_id'];

    protected $casts = [
        'amount' => Price::class,
        'date' => 'datetime',
    ];

    public function getformattedPaymentDateAttribute()
    {
        return Carbon::parse($this->date)->format('Y-m-d');
    }

    public function getformattedPaymentAmountAttribute()
    {
        return number_format($this->amount / 100, 2);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoices::class, 'invoices_id');
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'amount',
                'date',
                'band_id',
                'user_id',
                'status',
                'invoices_id',
                'payable_type',
                'payable_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('payments')
            ->setDescriptionForEvent(fn(string $eventName) => "Payment has been {$eventName}");
    }
}
