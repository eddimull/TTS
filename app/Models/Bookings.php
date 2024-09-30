<?php

namespace App\Models;

use App\Casts\Price;
use App\Http\Traits\BookingTraits;
use App\Models\Contracts;
use App\Models\Interfaces\Contractable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bookings extends Model implements Contractable
{
    use HasFactory;
    use BookingTraits;

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
        'amountDue' => Price::class,
        'amountLeft' => Price::class,
        'amountPaid' => Price::class,
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function contacts()
    {
        return $this->belongsToMany(Contacts::class, 'booking_contacts', 'booking_id', 'contact_id')
            ->withPivot(['id', 'role', 'is_primary', 'notes', 'additional_info'])
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

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getIsPaidAttribute()
    {
        $totalPayments = $this->payments()->sum('amount');
        return $totalPayments >= $this->price;
    }

    public function getAmountPaidAttribute()
    {
        return $this->payments()->sum('amount') / 100;
    }

    public function getAmountDueAttribute()
    {
        return $this->price - $this->amount_paid;
    }

    public function getAmountLeftAttribute()
    {
        return $this->getAmountDueAttribute();
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
        return $query->select('bookings.*')
            ->selectRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = bookings.id) as amount_paid', [Bookings::class])
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = bookings.id) >= bookings.price', [Bookings::class])
            ->withCasts(['amount_paid' => Price::class, 'price' => Price::class])->get();
    }

    public function getContractRecipients(): array
    {
        return $this->contacts->map(function ($contact)
        {
            return [
                'email' => $contact->email,
                'first_name' => explode(' ', $contact->name)[0],
                'last_name' => explode(' ', $contact->name)[1] ?? '',
                'role' => 'user',
            ];
        })->toArray();
    }

    public function getContractName(): string
    {
        return "Contract for {$this->name} - {$this->band->name}";
    }
}
