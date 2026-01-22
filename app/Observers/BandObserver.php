<?php

namespace App\Observers;

use App\Models\Bands;
use App\Models\BandRole;

class BandObserver
{
    /**
     * Handle the Bands "created" event.
     */
    public function created(Bands $band): void
    {
        // Seed default roles for new bands
        $defaultRoles = [
            'Vocals',
            'Guitar',
            'Bass',
            'Drums',
            'Keys',
            'Saxophone',
            'Trumpet',
            'Trombone',
        ];

        foreach ($defaultRoles as $index => $roleName) {
            BandRole::create([
                'band_id' => $band->id,
                'name' => $roleName,
                'display_order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
