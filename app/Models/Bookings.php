<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'price' => 'decimal:2',
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

    public function payments()
    {
        return $this->morphMany(Payments::class, 'payable');
    }
}
