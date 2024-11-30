<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingContacts extends Model
{
    use HasFactory;

    protected $table = 'booking_contacts';

    protected $fillable = [
        'booking_id',
        'contact_id',
        'role',
        'is_primary',
        'notes',
        'additional_info',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'additional_info' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contacts::class, 'contact_id');
    }
}
