<?php

namespace App\Services\Push;

use App\Models\Events;
use Closure;
use GoogleMaps\GoogleMaps;
use Illuminate\Support\Facades\Log;

class VenueTimezoneResolver
{
    /** @var Closure(string): ?string  Address → IANA tz (null on failure). */
    private Closure $lookup;

    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? fn (string $address) => $this->lookupViaGoogle($address);
    }

    public function forEvent(Events $event): string
    {
        if (!empty($event->venue_timezone)) {
            return $event->venue_timezone;
        }

        $address = $event->resolved_venue_address;
        if (empty($address)) {
            return config('app.timezone');
        }

        $tz = ($this->lookup)($address);
        if ($tz === null) {
            Log::warning('VenueTimezoneResolver: lookup failed, using app tz', [
                'event_id' => $event->id,
            ]);
            return config('app.timezone'); // do not cache the fallback
        }

        $event->venue_timezone = $tz;
        $event->save();

        return $tz;
    }

    private function lookupViaGoogle(string $address): ?string
    {
        try {
            $maps = app(GoogleMaps::class);

            $geo = json_decode(
                $maps->load('geocoding')->setParamByKey('address', $address)->get(),
                true,
            );
            $loc = $geo['results'][0]['geometry']['location'] ?? null;
            if (!$loc) {
                return null;
            }

            $tzResp = json_decode(
                $maps->load('timezone')->setParam([
                    'location'  => "{$loc['lat']},{$loc['lng']}",
                    'timestamp' => now()->timestamp,
                ])->get(),
                true,
            );

            return $tzResp['timeZoneId'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('VenueTimezoneResolver: google lookup threw', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
