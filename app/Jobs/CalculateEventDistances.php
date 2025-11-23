<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\EventDistanceForMembers;
use App\Models\State;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CalculateEventDistances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function handle()
    {
        try {
            // Get the band from the event's eventable relationship
            $eventable = $this->event->eventable;
            if (!$eventable || !isset($eventable->band_id)) {
                Log::info('Event has no associated band, skipping distance calculation for event ID: ' . $this->event->id);
                return;
            }

            $band = $eventable->band;
            if (!$band) {
                Log::info('Band not found for event ID: ' . $this->event->id);
                return;
            }

            // Get event address information
            $eventAddress = $this->getEventAddress();
            if (!$eventAddress) {
                Log::info('Event has no valid address, skipping distance calculation for event ID: ' . $this->event->id);
                return;
            }

            // Get all band members and owners - these are BandMembers/BandOwners models
            $bandMembers = $band->members()->with('user')->get();
            $bandOwners = $band->owners()->with('user')->get();
            
            // Extract the actual User models
            $memberUsers = $bandMembers->map(fn($bm) => $bm->user)->filter();
            $ownerUsers = $bandOwners->map(fn($bo) => $bo->user)->filter();
            $allUsers = $memberUsers->merge($ownerUsers)->unique('id');

            foreach ($allUsers as $user) {
                // Check if user has a complete address
                if (!$this->hasCompleteAddress($user)) {
                    Log::info('User ID ' . $user->id . ' has incomplete address, skipping distance calculation');
                    continue;
                }

                $this->calculateDistanceForMember($user, $eventAddress);
            }

            Log::info('Distance calculation completed for event ID: ' . $this->event->id);
        } catch (\Exception $e) {
            Log::error('Failed to calculate event distances for event ID ' . $this->event->id . ': ' . $e->getMessage());
        }
    }

    protected function getEventAddress(): ?array
    {
        $eventable = $this->event->eventable;
        
        // Try to get address from eventable (Booking or BandEvent)
        if (isset($eventable->venue_address) && isset($eventable->venue_name)) {
            return [
                'address' => $eventable->venue_address,
                'name' => $eventable->venue_name,
            ];
        }

        // For legacy BandEvents with separate fields
        if (isset($eventable->address_street) && isset($eventable->city) && isset($eventable->state_id) && isset($eventable->zip)) {
            $state = State::where('state_id', $eventable->state_id)->first();
            if ($state) {
                return [
                    'address' => $eventable->address_street . ' ' . $eventable->city . ', ' . $state->state_name . ' ' . $eventable->zip,
                    'name' => $eventable->venue_name ?? 'Event Venue',
                ];
            }
        }

        return null;
    }

    protected function hasCompleteAddress($user): bool
    {
        return !empty($user->Address1) 
            && !empty($user->City) 
            && !empty($user->StateID) 
            && !empty($user->Zip);
    }

    protected function calculateDistanceForMember($member, array $eventAddress)
    {
        try {
            // Get or create distance record
            $mileage = EventDistanceForMembers::where('event_id', $this->event->id)
                ->where('user_id', $member->id)
                ->first();

            // Only recalculate if doesn't exist or event was updated after last calculation
            if ($mileage && $mileage->miles && $mileage->created_at >= $this->event->updated_at) {
                Log::info('Distance already calculated for user ' . $member->id . ' and event ' . $this->event->id);
                return;
            }

            if (!$mileage) {
                $mileage = new EventDistanceForMembers();
                $mileage->event_id = $this->event->id;
                $mileage->user_id = $member->id;
            }

            // Get user's state for proper address formatting
            $userState = State::where('state_id', $member->StateID)->first();
            if (!$userState) {
                Log::info('User state not found for user ID ' . $member->id);
                return;
            }

            $userAddress = $member->Address1 . ' ' . $member->City . ', ' . $userState->state_name . ' ' . $member->Zip;

            // Call Google Maps Distance Matrix API
            $response = \GoogleMaps::load('distancematrix')
                ->setParamByKey('origins', $userAddress)
                ->setParamByKey('destinations', $eventAddress['address'])
                ->setParamByKey('units', 'imperial')
                ->get();

            $response = json_decode($response);

            if (!isset($response->rows[0]->elements[0])) {
                Log::warning('No distance matrix response for user ' . $member->id . ' and event ' . $this->event->id);
                return;
            }

            $element = $response->rows[0]->elements[0];

            // If ZERO_RESULTS, try with just venue name
            if ($element->status === 'ZERO_RESULTS') {
                Log::info('Zero results, trying with venue name for event ' . $this->event->id);
                
                $eventable = $this->event->eventable;
                $venueSearch = $eventAddress['name'];
                if (isset($eventable->zip)) {
                    $venueSearch .= ' ' . $eventable->zip;
                }

                $response = \GoogleMaps::load('distancematrix')
                    ->setParamByKey('origins', $userAddress)
                    ->setParamByKey('destinations', $venueSearch)
                    ->setParamByKey('units', 'imperial')
                    ->get();

                $response = json_decode($response);
                
                if (!isset($response->rows[0]->elements[0])) {
                    Log::warning('No distance found even with venue name for user ' . $member->id);
                    return;
                }

                $element = $response->rows[0]->elements[0];
            }

            if ($element->status !== 'OK') {
                Log::warning('Distance calculation failed with status: ' . $element->status);
                return;
            }

            // Extract miles from distance text (e.g., "123 mi" -> 123)
            $mileage->miles = (int) preg_replace('/\D/', '', $element->distance->text);

            // Parse duration - handle "X hours Y mins", "X hour Y mins", or "Y mins"
            $durationText = $element->duration->text;
            $minutes = 0;

            // Check if contains hours
            if (preg_match('/(\d+)\s*(?:hours?|hrs?)/', $durationText, $hourMatches)) {
                $hours = (int) $hourMatches[1];
                $minutes = $hours * 60;
            }

            // Check if contains additional minutes
            if (preg_match('/(\d+)\s*(?:mins?|minutes?)/', $durationText, $minMatches)) {
                $minutes += (int) $minMatches[1];
            }

            $mileage->minutes = $minutes;
            $mileage->save();

            Log::info('Distance calculated for user ' . $member->id . ' and event ' . $this->event->id . ': ' . $mileage->miles . ' miles, ' . $mileage->minutes . ' minutes');
        } catch (\Exception $e) {
            Log::error('Failed to calculate distance for user ' . $member->id . ' and event ' . $this->event->id . ': ' . $e->getMessage());
        }
    }
}
