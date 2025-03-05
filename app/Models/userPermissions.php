<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated use Laravel "can" and check for specific permissions instead
 */
class userPermissions extends Model
{
    protected $guarded = [];
    use HasFactory;
}
