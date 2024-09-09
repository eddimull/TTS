<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\State;
use App\Models\BandEvents;
use App\Models\Bands;
use Illuminate\Support\Facades\Config;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use App\Models\EventContacts;
use App\Models\Contracts;
use PDF;


class ProposalServices
{
    protected $proposal;
    public function __construct($proposal)
    {
        $this->proposal = $proposal;
    }


    private function addContactsToEvent($eventId)
    {
        $contacts = $this->proposal->proposal_contacts;
        foreach ($contacts as $contact)
        {
            EventContacts::create([
                'event_id' => $eventId,
                'name' => $contact->name,
                'phonenumber' => $contact->phonenumber,
                'email' => $contact->email
            ]);
        }
    }

    public function writeToCalendar()
    {
        $sessionToken = Str::random();
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json", [
            'input' => $this->proposal->location,
            'key' => Config::get('googlemaps.key'),
            'sessiontoken' => $sessionToken
        ]);
        $parsedResponse = json_decode($googleResponse->body());
        $usableAddress = [
            'venue' => 'Unnamed Venue',
            'street_number' => '',
            'route' => '',
            'locality' => '',
            'state' => 'Louisiana',
            'postal_code' => ''
        ];
        if ($parsedResponse->status !== 'INVALID_REQUEST' && $parsedResponse->status !== 'ZERO_RESULTS' && !app()->environment('testing'))
        {
            $usableAddress['venue'] = $parsedResponse->predictions[0]->structured_formatting->main_text;
            $place_id = $parsedResponse->predictions[0]->place_id;
            $detailedResponse = Http::get("https://maps.googleapis.com/maps/api/place/details/json", [
                'place_id' => $place_id,
                'key' => Config::get('googlemaps.key'),
                'sessiontoken' => $sessionToken
            ]);
            $parsedDetails = json_decode($detailedResponse->body());

            if ($parsedDetails->status !== 'INVALID_REQUEST')
            {
                $addressComponents = $parsedDetails->result->address_components;
                foreach ($addressComponents as $component)
                {

                    if (array_search('street_number', $component->types) !== false)
                    {
                        $usableAddress['street_number'] = $component->long_name;
                    }
                    if (array_search('route', $component->types) !== false)
                    {
                        $usableAddress['route'] = $component->long_name;
                    }
                    if (array_search('locality', $component->types) !== false)
                    {
                        $usableAddress['locality'] = $component->long_name;
                    }
                    if (array_search('administrative_area_level_1', $component->types) !== false)
                    {
                        $usableAddress['state'] = $component->long_name;
                    }
                    if (array_search('postal_code', $component->types) !== false)
                    {
                        $usableAddress['postal_code'] = $component->long_name;
                    }
                }
            }
        }

        $state = State::where('state_name', $usableAddress['state'])->first();


        $event = BandEvents::create([
            'band_id' => $this->proposal->band->id,
            'event_name' => $this->proposal->name,
            'venue_name' => $usableAddress['venue'],
            'first_dance' => 'TBD',
            'father_daughter' => 'TBD',
            'mother_groom' => 'TBD',
            'money_dance' => 'TBD',
            'bouquet_garter' => 'TBD',
            'address_street' => $usableAddress['street_number'] . ' ' . $usableAddress['route'],
            'production_needed' => true,
            'backline_provided' => false,
            'zip' => $usableAddress['postal_code'],
            'notes' => $this->proposal->notes,
            'event_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'band_loadin_time' =>  date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'rhythm_loadin_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'production_loadin_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'pay' => $this->proposal->price,
            'depositReceived' => true,
            'event_key' => Str::uuid(),
            'public' => false,
            'event_type_id' => $this->proposal->event_type_id,
            'lodging' => false,
            'state_id' => $state->state_id,
            'city' => $usableAddress['locality'],
            'colorway_id' => 0,
            'quiet_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'end_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date . '+ ' . $this->proposal->hours . ' hours')),
            'ceremony_time' => date('Y-m-d H:i:s', strtotime($this->proposal->date)),
            'outside' => false,
            'second_line' => false,
            'onsite' => false,
        ]);

        $this->proposal->event_id = $event->id;
        $this->proposal->save();

        $band = Bands::find($event->band_id);
        if ($band->calendar_id !== '' && $band->calendar_id !== null)
        {

            Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id', $band->calendar_id);

            // dd(Carbon::parse($event->event_time));

            if ($event->google_calendar_event_id !== null)
            {
                $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            }
            else
            {
                $calendarEvent = new CalendarEvent;
            }
            $calendarEvent->name = $event->event_name;

            $startTime = Carbon::parse($event->event_time);
            $endDateTimeFixed = date('Y-m-d', strtotime($event->event_time)) . ' ' . date('H:i:s', strtotime($event->end_time));
            if ($endDateTimeFixed < $startTime) //when events end after midnight
            {
                $endDateTimeFixed = date('Y-m-d', strtotime($event->event_time . ' +1 day')) . ' ' . date('H:i:s', strtotime($event->end_time));
            }
            $endTime = Carbon::parse($endDateTimeFixed);
            $calendarEvent->startDateTime = $startTime;
            $calendarEvent->endDateTime = $endTime;
            $calendarEvent->description = 'https://tts.band/events/' . $event->event_key . '/advance';
            $google_id = $calendarEvent->save();
            $event->google_calendar_event_id = $google_id->id;
            $event->save();
        }

        $this->addContactsToEvent($event->id);

        return $event;
    }


    public function make_pandadoc_contract()
    {
        $pdf = PDF::loadView('contract', ['proposal' => $this->proposal]);
        $base64PDF = base64_encode($pdf->output());
        $imagePath = $this->proposal->band->site_name . '/' . $this->proposal->name . '_contract_' . time() . '.pdf';

        $path = Storage::disk('s3')->put(
            $imagePath,
            base64_decode($base64PDF),
            ['visibility' => 'public']
        );

        $body =  [
            "name" => "Contract for " . $this->proposal->band->name,
            "url" => Storage::disk('s3')->url($imagePath),
            "tags" => [
                "tag_1"
            ],
            "recipients" => [
                [
                    "email" => $this->proposal->proposal_contacts[0]->email,
                    "first_name" => $this->proposal->proposal_contacts[0]->name,
                    "last_name" => ".",
                    "role" => "user"
                ]
            ],
            "fields" => [
                "name" => [
                    "value" => $this->proposal->proposal_contacts[0]->name,
                    "role" => "user"
                ]
            ],
            "parse_form_fields" => false
        ];

        if (app()->environment('testing'))
        {
            // In testing environment, we'll skip the actual API call
            $uploadedDocumentId = 'test_document_id';
            $sent = true;
        }
        else
        {
            $uploadedDocumentId = $this->sendToPandaDoc($body);
            sleep(5);
            $sent = $this->sendToPandaDoc($body, $uploadedDocumentId);
        }

        Contracts::create([
            'proposal_id' => $this->proposal->id,
            'envelope_id' => $uploadedDocumentId,
            'status' => 'sent',
            'image_url' => Storage::disk('s3')->url($imagePath)
        ]);

        return $sent;
    }

    private function sendToPandaDoc($body, $uploadedDocumentId = null)
    {
        if ($uploadedDocumentId === null)
        {
            $response = Http::withHeaders([
                'Authorization' => 'API-Key ' . env('PANDADOC_KEY')
            ])
                ->acceptJson()
                ->post('https://api.pandadoc.com/public/v1/documents', $body);

            return $response['id'];
        }
        else
        {
            return Http::withHeaders([
                'Authorization' => 'API-Key '  . env('PANDADOC_KEY')
            ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send', [
                "message" => 'Please sign this contract so we can make this official!',
                "subject" => 'Contract for ' . $body['name']
            ]);
        }
    }
}
