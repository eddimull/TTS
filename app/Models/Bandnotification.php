<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Bandnotification extends DatabaseNotification
{
    
    public function markAsSeen()
    {
        if (is_null($this->seen_at)) {
            $this->forceFill(['seen_at' => $this->freshTimestamp()])->save();
        }
    }
}