<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LodgingRoom extends Model
{
    use HasFactory;

    protected $fillable = ['lodging_id', 'label', 'confirmation_number', 'notes', 'sort_order'];

    public function lodging()
    {
        return $this->belongsTo(Lodging::class);
    }
}
