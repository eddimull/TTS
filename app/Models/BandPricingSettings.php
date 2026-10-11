<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-band configuration for the booking price estimator.
 *
 * `settings` holds only the values the band has overridden; the defaults in
 * PriceEstimatorService::DEFAULT_SETTINGS are merged underneath on read.
 */
class BandPricingSettings extends Model
{
    protected $table = 'band_pricing_settings';

    protected $fillable = [
        'band_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }
}
