<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Contacts extends Model
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'band_id',
        'name',
        'email',
        'phone'
    ];

    // protected $appends = ['booking_history'];

    public function bookingContacts()
    {
        return $this->hasMany(BookingContacts::class, 'contact_id');
    }

    public function bookings()
    {
        return $this->belongsToMany(Bookings::class, 'booking_contact', 'contact_id', 'booking_id')
            ->withPivot(['role', 'is_primary', 'notes', 'additional_info'])
            ->withTimestamps();
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
}
