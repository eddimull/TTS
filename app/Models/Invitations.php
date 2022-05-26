<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitations extends Model
{
    use HasFactory;

    protected $fillable = ['email','band_id','invite_type_id','pending','key'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($invitation) {
            $invitation->key = Str::random(36);
        });
    }
}
