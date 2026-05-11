<?php

namespace App\Models;

use App\Casts\Price;
use App\Models\Contracts;
use App\Models\Events;
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
        'price',
        'status',
        'contract_option',
        'author_id',
        'notes',
        'enable_portal_media_access',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
        'price' => Price::class,
        'enable_portal_media_access' => 'boolean',
    ];

    protected $attributes = [
        'enable_portal_media_access' => true,
    ];

    protected $appends = [
        'amount_paid',
        'amount_due',
        'is_paid',
        'start_date',
        'end_date',
        'event_count',
        'venue_summary',
        'is_multi_event',
        'total_duration',
    ];

    protected $hidden = [
        'payment_total_cents', // Internal field used for SQL aggregation, not for API responses
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

    public function events(): MorphMany
    {
        return $this->morphMany(Events::class, 'eventable');
    }

    /**
     * The booking's primary event (chronologically-first by (date, id)).
     * Memoized; uses the loaded `events` collection when available, else queries.
     */
    private ?Events $cachedPrimaryEvent = null;
    private bool $primaryEventCached = false;

    protected function primaryEvent(): ?Events
    {
        if ($this->primaryEventCached) {
            return $this->cachedPrimaryEvent;
        }

        if ($this->relationLoaded('events')) {
            $primary = $this->events
                ->sortBy([['date', 'asc'], ['id', 'asc']])
                ->first();
        } else {
            $primary = $this->events()->orderBy('date')->orderBy('id')->first();
        }

        $this->cachedPrimaryEvent = $primary;
        $this->primaryEventCached = true;
        return $primary;
    }

    /**
     * The booking's last event (chronologically-last by (date, id)).
     * Memoized; uses the loaded `events` collection when available, else queries.
     */
    private ?Events $cachedLastEvent = null;
    private bool $lastEventCached = false;

    protected function lastEvent(): ?Events
    {
        if ($this->lastEventCached) {
            return $this->cachedLastEvent;
        }

        if ($this->relationLoaded('events')) {
            $last = $this->events
                ->sortBy([['date', 'asc'], ['id', 'asc']])
                ->last();
        } else {
            $last = $this->events()->orderBy('date', 'desc')->orderBy('id', 'desc')->first();
        }

        $this->cachedLastEvent = $last;
        $this->lastEventCached = true;
        return $last;
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payments::class, 'payable');
    }

    public function payout(): MorphOne
    {
        return $this->morphOne(Payout::class, 'payable');
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
        // Use the amount_paid accessor which handles both SQL aggregation and lazy loading
        $amountPaid = is_string($this->amount_paid) ? floatval($this->amount_paid) : $this->amount_paid;
        $price = is_string($this->price) ? floatval($this->price) : $this->price;
        return $amountPaid >= $price;
    }

    public function eventType()
    {
        return $this->belongsTo(EventTypes::class, 'event_type_id');
    }

    public function getStartDateAttribute(): ?\Carbon\Carbon
    {
        return $this->primaryEvent()?->date;
    }

    public function getEndDateAttribute(): ?\Carbon\Carbon
    {
        return $this->lastEvent()?->date;
    }

    public function getEventCountAttribute(): int
    {
        return $this->relationLoaded('events')
            ? $this->events->count()
            : $this->events()->count();
    }

    public function getIsMultiEventAttribute(): bool
    {
        return $this->event_count > 1;
    }

    public function getVenueSummaryAttribute(): ?string
    {
        $names = $this->events->pluck('venue_name')
            ->filter(fn($n) => !empty($n))
            ->unique()
            ->values();
        if ($names->isEmpty()) {
            return null;
        }
        if ($names->count() === 1) {
            return $names->first();
        }
        return 'Multiple venues';
    }

    public function getTotalDurationAttribute(): float
    {
        $events = $this->events;
        $hours = 0.0;
        foreach ($events as $event) {
            if (!$event->start_time || !$event->end_time) {
                continue;
            }
            $start = $event->start_time;
            $end = $event->end_time;
            if ($end < $start) {
                $end = $end->copy()->addDay();
            }
            $hours += round($start->diffInHours($end, true), 2);
        }
        return $hours;
    }

    public function getAmountPaidAttribute($value = null)
    {
        // If payment_total_cents was pre-calculated (e.g., from search aggregation),
        // use it to avoid N+1 queries
        if (array_key_exists('payment_total_cents', $this->attributes)) {
            $rawValue = $this->attributes['payment_total_cents'];
            return number_format($rawValue / 100, 2, '.', '');
        }

        // If amount_paid was selected in the query (e.g., from scopeUnpaid or scopePaid),
        // the raw value is in cents and needs to be formatted like the Price cast does
        if (array_key_exists('amount_paid', $this->attributes)) {
            $rawValue = $this->attributes['amount_paid'];
            // The Price cast divides by 100 and formats
            return number_format($rawValue / 100, 2, '.', '');
        }

        // Otherwise, calculate it from the payments relationship
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

    /**
     * Calculate the total payout amount including adjustments
     * Returns amount in dollars as a float
     */
    public function getAdjustedPayoutTotalAttribute(): float
    {
        $basePrice = is_string($this->price) ? floatval($this->price) : $this->price;
        
        // If payout exists with adjustments, use adjusted_amount
        if ($this->relationLoaded('payout') && $this->payout) {
            return $this->payout->adjusted_amount_float;
        }
        
        // If payout exists in DB but not loaded
        $payout = $this->payout()->first();
        if ($payout) {
            return is_string($payout->adjusted_amount) 
                ? floatval($payout->adjusted_amount) 
                : $payout->adjusted_amount;
        }
        
        // No payout record, return base price
        return $basePrice;
    }

    /**
     * Get the total value from all events in this booking
     * Returns amount in dollars as a float
     * Falls back to booking price if no events have values
     */
    public function getTotalEventValueAttribute(): float
    {
        $events = $this->events()->get();

        if ($events->isEmpty()) {
            return is_string($this->price) ? floatval($this->price) : $this->price;
        }

        // Sum all event values (convert from cents to dollars)
        $total = $events->sum(function ($event) {
            if ($event->value === null) {
                return 0;
            }
            return is_string($event->value) ? floatval($event->value) : $event->value;
        });

        // If no events have values, fall back to booking price
        if ($total == 0) {
            return is_string($this->price) ? floatval($this->price) : $this->price;
        }

        return $total;
    }

    /**
     * Get the expected deposit amount (50% of booking price)
     * Returns amount in dollars as a formatted string
     */
    public function getExpectedDepositAmountAttribute(): string
    {
        $depositPercent = 0.50; // 50% deposit requirement
        $price = is_string($this->price) ? floatval($this->price) : $this->price;
        return number_format($price * $depositPercent, 2, '.', '');
    }

    /**
     * Check if the deposit has been paid
     */
    public function getIsDepositPaidAttribute(): bool
    {
        $expectedDeposit = floatval($this->expected_deposit_amount);
        $amountPaid = floatval($this->amount_paid);
        return $amountPaid >= $expectedDeposit;
    }

    /**
     * Get the amount of deposit still owed
     * Returns amount in dollars as a formatted string
     */
    public function getDepositDueAttribute(): string
    {
        $expectedDeposit = floatval($this->expected_deposit_amount);
        $amountPaid = floatval($this->amount_paid);
        $amountDue = max(0, $expectedDeposit - $amountPaid);
        return number_format($amountDue, 2, '.', '');
    }

    /**
     * Get the date when the contract was signed (completed)
     */
    public function getContractSignedDateAttribute(): ?Carbon
    {
        if (!$this->contract || $this->contract->status !== 'completed') {
            return null;
        }
        return $this->contract->updated_at;
    }

    /**
     * Get the deposit due date (3 weeks after contract signed)
     */
    public function getDepositDueDateAttribute(): ?Carbon
    {
        if (!$this->contract_signed_date) {
            return null;
        }
        return $this->contract_signed_date->copy()->addWeeks(3);
    }

    /**
     * Check if booking needs a deposit payment reminder
     * Should remind if:
     * - Contract was signed exactly 3 weeks ago (±1 day window)
     * - Deposit has not been paid
     * - Event date is in the future
     */
    public function getNeedsDepositReminderAttribute(): bool
    {
        if (!$this->deposit_due_date || $this->is_deposit_paid) {
            return false;
        }
        $startDate = $this->start_date;
        if ($startDate && $startDate < now()) {
            return false;
        }

        // Check if due date is today (±1 day for safety)
        $dueDate = $this->deposit_due_date;
        return $dueDate->isToday() ||
               $dueDate->isYesterday() ||
               $dueDate->isTomorrow();
    }

    /**
     * Check if booking needs a final payment reminder
     * Should remind if:
     * - Event is 7 days away
     * - Full payment has not been made
     */
    public function getNeedsFinalPaymentReminderAttribute(): bool
    {
        if ($this->is_paid) {
            return false;
        }
        $startDate = $this->start_date;
        if (!$startDate || $startDate < now()) {
            return false;
        }

        $daysUntilEvent = now()->diffInDays($startDate, false);
        return $daysUntilEvent >= 6 && $daysUntilEvent <= 8; // 7 days ±1 day
    }

    public function scopeUnpaid($query)
    {
        return $query->leftJoinSub(
            function ($query)
            {
                $query->from('payments')
                    ->selectRaw('payable_id, SUM(amount) as total_paid')
                    ->where('payable_type', Bookings::class)
                    ->where('status', 'paid')
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
                    ->where('status', 'paid')

            ])
            ->whereRaw('COALESCE(payments_sum.total_paid, 0) < bookings.price')
            ->with('payout')
            ->get();
    }

    public function scopePaid($query)
    {
        return $query->select('bookings.*')
            ->selectRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = bookings.id AND status = "paid") as amount_paid', [Bookings::class])
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = bookings.id AND status = "paid") >= bookings.price', [Bookings::class])
            ->with('payout')
            ->get();
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
        $primary = $this->primaryEvent();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'venue_name' => $primary?->venue_name,
            'venue_address' => $primary?->venue_address,
            'status' => $this->status,
            'date' => $primary?->date?->format('Y-m-d'),
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

    public function questionnaireInstances()
    {
        return $this->hasMany(QuestionnaireInstances::class, 'booking_id');
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
        $primary = $this->primaryEvent();
        if (!$primary) {
            return null;
        }
        if ($primary->venue_name && $primary->venue_name !== 'TBD') {
            return $primary->venue_name . ($primary->venue_address ? ', ' . $primary->venue_address : '');
        }
        return null;
    }

    public function getGoogleCalendarStartTime(): \Google\Service\Calendar\EventDateTime
    {
        $primary = $this->primaryEvent();
        return new \Google\Service\Calendar\EventDateTime([
            'dateTime' => $primary?->startDateTime,
            'timeZone' => config('app.timezone'),
        ]);
    }

    public function getGoogleCalendarEndTime(): \Google\Service\Calendar\EventDateTime
    {
        $primary = $this->primaryEvent();
        return new \Google\Service\Calendar\EventDateTime([
            'dateTime' => $primary?->endDateTime,
            'timeZone' => config('app.timezone'),
        ]);
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
