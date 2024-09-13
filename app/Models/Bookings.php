<?php

namespace App\Models;

use App\Casts\Price;
use App\Models\Contracts;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bookings extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'name',
        'event_type_id',
        'date',
        'start_time',
        'end_time',
        'venue_name',
        'venue_address',
        'price',
        'status',
        'contract_option',
        'author_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
        'date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'price' => Price::class,
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function contacts()
    {
        return $this->belongsToMany(Contacts::class, 'booking_contacts', 'booking_id', 'contact_id')
            ->withPivot(['role', 'is_primary', 'notes', 'additional_info'])
            ->withTimestamps();
    }

    public function primaryContact()
    {
        return $this->contacts()->wherePivot('is_primary', true);
    }

    public function getDurationAttribute()
    {
        // Calculate the duration of the booking
        $diff = $this->start_time->diff($this->end_time);
        return $diff->h;
    }

    public function events(): MorphMany
    {
        return $this->morphMany(Events::class, 'eventable');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payments::class, 'payable');
    }

    public function contract(): MorphOne
    {
        return $this->morphOne(Contracts::class, 'contractable');
    }

    public function getIsPaidAttribute()
    {
        $totalPayments = $this->payments()->sum('amount');
        return $totalPayments >= $this->price;
    }

    public function scopeUnpaid($query)
    {
        return $query->leftJoinSub(
            function ($query)
            {
                $query->from('payments')
                    ->selectRaw('payable_id, SUM(amount) as total_paid')
                    ->where('payable_type', Bookings::class)
                    ->groupBy('payable_id');
            },
            'payments_sum',
            'bookings.id',
            '=',
            'payments_sum.payable_id'
        )
            ->addSelect([
                'amount_paid' => Payments::selectRaw('COALESCE(SUM(amount),0)')
                    ->whereColumn('payable_id', 'bookings.id')
                    ->where('payable_type', Bookings::class)

            ])
            ->whereRaw('COALESCE(payments_sum.total_paid, 0) < bookings.price')
            ->withCasts(['amount_paid' => Price::class, 'price' => Price::class])->get();
    }

    public function scopePaid($query)
    {
        return $query->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = bookings.id) >= bookings.price', [Bookings::class])->get();
    }
}
