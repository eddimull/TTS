<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;

class Contacts extends Model
{
    use HasFactory;
    use Notifiable;
    use Searchable;

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

    protected function makeAllSearchableUsing(Builder $query)
    {
        return $query->with(['bookings']);
    }

    public function makeSearchableUsing($query)
    {
        return $query->with(['bookings']);
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
    
}
