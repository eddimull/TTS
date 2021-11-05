<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalPayments extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'payments';
}
