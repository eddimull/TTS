<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetlistPromptTemplate extends Model
{
    protected $fillable = ['band_id', 'name', 'prompt'];
}
