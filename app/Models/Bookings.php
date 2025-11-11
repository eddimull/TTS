<?php

namespace App\Models;

use App\Casts\Price;
use App\Models\Contracts;
use Laravel\Scout\Searchable;
use App\Casts\BookingDateTime;
use App\Formatters\CalendarEventFormatter;
use App\Http\Traits\BookingTraits;
use App\Models\Interfaces\Contractable;
use App\Models\Interfaces\GoogleCalenderable;
use App\Models\Traits\GoogleCalendarWritable;
use App\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Bookings extends Model implements Contractable, GoogleCalenderable
{
    use HasFactory;
    use BookingTraits;
    use Searchable;
    use GoogleCalendarWritable;
    use LogsActivity;

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

    protected $appends = [
        'amount_paid',
        'amount_due',
        'is_paid',
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
        $totalPayments = $this->payments()->where('status', 'paid')->sum('amount');
        // Convert cents to dollars for comparison (price is already cast to dollars)
        return ($totalPayments / 100) >= $this->price;
    }

    public function eventType()
    {
        return $this->belongsTo(EventTypes::class, 'event_type_id');
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
        $total = $this->payments()->where('status', 'paid')->sum('amount') / 100;
        return number_format($total, 2, '.', '');
    }

    public function getAmountDueAttribute()
    {
        $price = is_string($this->price) ? floatval($this->price) : $this->price;
        $amountPaid = is_string($this->amount_paid) ? floatval($this->amount_paid) : $this->amount_paid;
        return number_format($price - $amountPaid, 2, '.', '');
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

    public function googleEvent(): MorphOne
    {
        return $this->morphOne(GoogleEvents::class, 'google_eventable');
    }

    public function getGoogleEvent(BandCalendars $bandCalendar = null): GoogleEvents|null
    {
        if (!$bandCalendar) {
            return $this->googleEvent;
        }
        return $this->googleEvent()->where('band_calendar_id', $bandCalendar->id)->first();
    }

    public function getGoogleCalendar(): BandCalendars|null
    {
        return $this->band->bookingCalendar;
    }

    public function getGoogleCalendarSummary(): ?string
    {
        return $this->name . ' (' . ucfirst($this->status) . ')';
    }

    public function getGoogleCalendarDescription(): ?string
    {
        return CalendarEventFormatter::formatBookingDescription($this);
    }

    public function getGoogleCalendarColor(): string|null
    {
        switch ($this->status) {
            case 'confirmed':
                return '10'; // Green
            case 'pending':
                return '5'; // Yellow
            case 'cancelled':
                return '11'; // Red
            case 'draft':
                return '9'; // Blueberry
            default:
                return null;
        }
    }

    public function getGoogleCalendarLocation(): string|null
    {
       if($this->venue_name && $this->venue_name !== 'TBD') {
           return $this->venue_name . ($this->venue_address ? ', ' . $this->venue_address : '');
       }
       return null;
    }

    public function getGoogleCalendarStartTime(): \Google\Service\Calendar\EventDateTime
    {
        return new \Google\Service\Calendar\EventDateTime(['dateTime' => $this->startDateTime, 'timeZone' => config('app.timezone')]);
    }

    public function getGoogleCalendarEndTime(): \Google\Service\Calendar\EventDateTime
    {
        return new \Google\Service\Calendar\EventDateTime(['dateTime' => $this->endDateTime, 'timeZone' => config('app.timezone')]);
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
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
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('bookings')
            ->setDescriptionForEvent(fn(string $eventName) => "Booking has been {$eventName}");
    }

}
