<?php

namespace App\Models;

use App\Casts\Price;
use App\Models\Contracts;
use Laravel\Scout\Searchable;
use App\Casts\BookingDateTime;
use App\Http\Traits\BookingTraits;
use App\Models\Interfaces\Contractable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bookings extends Model implements Contractable
{
    use HasFactory;
    use BookingTraits;
    use Searchable;

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
        $start = $this->start_time;
        $end = $this->end_time;

        // If end time is earlier than start time, add a day to end time
        if ($end < $start)
        {
            $end = $end->addDay();
        }

        return round($start->diffInHours($end, true), 2);
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

    public function getStartDateTimeAttribute(): ?Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->start_time->format('H:i'));
    }

    public function getEndDateTimeAttribute(): ?Carbon
    {
        $endTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->end_time->format('H:i'))->copy();

        $startTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->start_time->format('H:i'))->copy();

        if ($endTime->lt($startTime)) {
            $endTime->addDay();
        }
        return $endTime;
    }

    public function getAmountPaidAttribute()
    {
        return $this->payments()->where('status', 'paid')->sum('amount') / 100;
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

    public function attachPayments()
    {
        $this->amountLeft = $this->amountLeft;
        $this->amountPaid = $this->amountPaid;

        foreach ($this->payments as $payment)
        {
            $payment->formattedPaymentDate = $payment->formattedPaymentDate;
        }
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'status' => $this->status,
            'date' => $this->date->format('Y-m-d'),
            'price' => $this->price,
            'band_id' => $this->band_id,
            'event_type_id' => $this->event_type_id,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'band_name' => $this->band?->name,
            'author_name' => $this->author?->name,
            'notes' => $this->notes,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'bookings';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index bookings that are not cancelled or deleted
        return !in_array($this->status, ['cancelled', 'deleted']);
    }

}
