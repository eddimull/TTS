<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contacts extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone'
    ];

    public function bookingContacts()
    {
        return $this->hasMany(BookingContacts::class);
    }

    public function bookings()
    {
        return $this->belongsToMany(Bookings::class, 'booking_contact', 'contact_id', 'booking_id')
            ->withPivot(['role', 'is_primary', 'notes', 'additional_info'])
            ->withTimestamps();
    }
}
