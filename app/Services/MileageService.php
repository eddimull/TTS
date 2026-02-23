<?php

namespace App\Services;
use App\Models\State;
use App\Models\Bands;
use App\Models\Bookings;
use Illuminate\Support\Facades\Auth;
use App\Models\EventDistanceForMembers;

class MileageService{
    public function handle($events, ?Bands $band = null)
    {
        $user = Auth::user();
        $stats = ['miles'=>0];

        $origin = $this->resolveOrigin($user, $band);
        if ($origin === null) {
            return $stats;
        }

        foreach($events as $event)
        {
            $destination = $this->resolveDestination($event);
            if ($destination === null) {
                continue;
            }

            $mileage = EventDistanceForMembers::firstOrCreate([
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]);

            if(is_null($mileage->miles) || $mileage->created_at < $event->updated_at)
            {
                $mileage->event_id = $event->id;
                $mileage->user_id = $user->id;

                $response = \GoogleMaps::load('distancematrix')
                    ->setParamByKey('origins', $origin)
                    ->setParamByKey('destinations', $destination)
                    ->setParamByKey('units', 'imperial')->get();
                $response = json_decode($response);

                $element = $response->rows[0]->elements[0] ?? null;
                if ($element && $element->status === "ZERO_RESULTS")
                {
                    // Fallback: try venue name + zip if available
                    $fallback = $this->resolveFallbackDestination($event);
                    if ($fallback) {
                        $response = \GoogleMaps::load('distancematrix')
                            ->setParamByKey('origins', $origin)
                            ->setParamByKey('destinations', $fallback)
                            ->setParamByKey('units', 'imperial')->get();
                        $response = json_decode($response);
                    }
                }

                if (isset($response->rows[0]->elements[0]->distance->text)) {
                    $mileage->miles = preg_replace('/\D/', '', $response->rows[0]->elements[0]->distance->text);

                    $durationText = $response->rows[0]->elements[0]->duration->text;
                    if(str_contains($durationText,'hours'))
                    {
                        $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" hours",".",$durationText)) * 60;
                    }
                    elseif(str_contains($durationText,'hour'))
                    {
                        $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" hour",".",$durationText)) * 60;
                    }
                    elseif(str_contains($durationText,'mins'))
                    {
                        $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" mins","",$durationText));
                    }
                    $mileage->save();
                }
            }

            $event->miles = $mileage->miles;
            $stats['miles'] = $stats['miles'] + $event->miles;
        }

        return $stats;
    }

    /**
     * Build the origin address string from user address, falling back to band address.
     */
    protected function resolveOrigin($user, ?Bands $band): ?string
    {
        if ($user->Address1 && $user->City && $user->StateID && $user->Zip) {
            $state = State::where('state_id', $user->StateID)->first();
            if ($state) {
                return $user->Address1 . ' ' . $user->City . ' ' . $state->state_name . ' ' . $user->Zip;
            }
        }

        if ($band && $band->address && $band->city && $band->state && $band->zip) {
            return $band->address . ' ' . $band->city . ' ' . $band->state . ' ' . $band->zip;
        }

        return null;
    }

    /**
     * Build the destination address for an event, handling both Bookings and BandEvents.
     * The $event may be an Events model (with eventable loaded) or a Bookings/BandEvents directly.
     */
    protected function resolveDestination($event): ?string
    {
        // If this is an Events model with eventable loaded
        $eventable = isset($event->eventable) ? $event->eventable : $event;

        if ($eventable instanceof Bookings) {
            if ($eventable->venue_address) {
                return $eventable->venue_address;
            }
            return null;
        }

        // BandEvents or raw event with split address fields
        $street = $eventable->address_street ?? null;
        $city = $eventable->city ?? null;
        $zip = $eventable->zip ?? null;
        $stateId = $eventable->state_id ?? null;

        if ($street && $city && $stateId) {
            $state = State::where('state_id', $stateId)->first();
            return $street . ' ' . $city . ', ' . ($state ? $state->state_name : '') . ' ' . $zip;
        }

        return null;
    }

    /**
     * Build a fallback destination using venue name + zip.
     */
    protected function resolveFallbackDestination($event): ?string
    {
        $eventable = isset($event->eventable) ? $event->eventable : $event;
        $venueName = $eventable->venue_name ?? null;
        $zip = $eventable->zip ?? $eventable->venue_address ?? null;

        if ($venueName && $zip) {
            return $venueName . ' ' . $zip;
        }

        return null;
    }
}
