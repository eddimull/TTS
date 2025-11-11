<?php

namespace App\Models;

use App\Notifications\ContactPasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contacts extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use Searchable;
    use LogsActivity;

    protected $fillable = [
        'band_id',
        'name',
        'email',
        'phone',
        'password',
        'can_login',
        'email_verified_at',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'can_login' => 'boolean',
    ];
    
    // protected $appends = ['booking_history'];

    public function bookingContacts()
    {
        return $this->hasMany(BookingContacts::class, 'contact_id');
    }

    public function bookings()
    {
        return $this->belongsToMany(Bookings::class, 'booking_contacts', 'contact_id', 'booking_id')
            ->withPivot(['role', 'is_primary', 'notes', 'additional_info'])
            ->withTimestamps();
    }

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    protected function bookingHistory(): Attribute
    {
        return Attribute::make(
            get: function ()
            {
                return $this->bookingContacts
                    ->map(function ($bookingContact)
                    {
                        return [
                            'booking_name' => $bookingContact->booking->name,
                            'date' => $bookingContact->booking->date->format('Y-m-d'),
                            'booking_id' => $bookingContact->booking,
                        ];
                    });
            }
        );
    }

    public function toSearchableArray()
    {
        $searchableArray = $this->toArray();
        
        // Include bookings data in searchable results
        $searchableArray['bookings'] = $this->bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date?->format('Y-m-d'),
                'role' => $booking->pivot->role,
                'is_primary' => $booking->pivot->is_primary,
                'notes' => $booking->pivot->notes,
                'additional_info' => $booking->pivot->additional_info,
            ];
        })->toArray();
        
        return $searchableArray;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ContactPasswordReset($token));
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
                'email',
                'phone',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('contacts')
            ->setDescriptionForEvent(fn(string $eventName) => "Contact has been {$eventName}");
    }
}
