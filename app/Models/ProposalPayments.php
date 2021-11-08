<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProposalPayments extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'payments';

    public function getpaymentDateAttribute()
    {
        return Carbon::parse($this->created_at)->format('Y-m-d');
    }
}
