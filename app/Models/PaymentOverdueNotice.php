<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that owners were told a booking's deposit or balance went overdue,
 * so each payment notifies once.
 */
class PaymentOverdueNotice extends Model
{
    protected $fillable = ['booking_id', 'type'];
}
