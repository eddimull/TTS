<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cache;

class EventTypes extends Model
{
    use HasFactory;
    protected $table = 'event_types';

    public static function all($columns = ['*'])
    {
        return Cache::rememberForever('all_event_types', function () use ($columns) {
            return parent::all($columns);
        });
    }
}
