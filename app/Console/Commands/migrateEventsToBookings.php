<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BookingContacts;
use App\Models\Events;
use Carbon\Carbon;

class migrateEventsToBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'etl:migrate-events-to-bookings
                            {--run-proposals : Run etl:migrate-proposals-to-bookings after completion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Step 1 in the ETL process';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of events to bookings...');

        DB::beginTransaction();

        try
        {
            BandEvents::chunk(100, function ($events)
            {
                foreach ($events as $event)
                {
                    $this->processEvent($event);
                }
            });

            DB::commit();
            $this->info('Migration completed successfully!');

            $runProposals = $this->option('run-proposals');

            if ($runProposals)
            {
                $this->runProposalsMigration();
            }
            elseif ($this->confirm('Do you want to run etl:migrate-proposals-to-bookings now?'))
            {
                $this->runProposalsMigration();
            }
        }
        catch (\Exception $e)
        {
            DB::rollBack();
            $this->error('An error occurred during migration: ' . $e->getMessage());
        }
    }

    private function runProposalsMigration()
    {
        $this->info('Running etl:proposals-to-bookings...');
        $this->call('etl:proposals-to-bookings');
    }

    private function processEvent(BandEvents $event)
    {
        $eventDate = Carbon::parse($event->event_time)->format('Y-m-d');
        $eventTime = Carbon::parse($event->event_time)->format('H:i:00');
        // Create or update booking
        $booking = Bookings::create(
            [
                'band_id' => $event->band_id,
                'name' => $event->event_name,
                'event_type_id' => $event->event_type_id,
                'date' => $eventDate,
                'start_time' => $eventTime,
                'end_time' => $event->end_time,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->address_street . ', ' . $event->city . ', ' . $event->state->state_name . ' ' . $event->zip,
                'price' => 0,
                'status' => 'confirmed',
                'notes' => '',
                'author_id' => 1,
                'contract_option' => 'none',
            ]
        );

        // Process contacts
        $this->processContacts($event, $booking);

        // Process events
        $this->processEvents($event, $booking);

        $this->info("Processed event ID {$event->id} - Created/Updated booking ID {$booking->id}");
    }

    private function processContacts(BandEvents $event, Bookings $booking)
    {
        // Assuming event_contacts relationship still exists on BandEvents
        foreach ($event->event_contacts as $index => $eventContact)
        {
            $contact = Contacts::firstOrCreate(
                ['email' => $eventContact->email, 'band_id' => $event->band_id],
                [
                    'name' => $eventContact->name,
                    'phone' => $eventContact->phonenumber,
                ]
            );

            BookingContacts::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'contact_id' => $contact->id,
                ],
                [
                    'role' => '',
                    'is_primary' => $index === 0,
                    'notes' => '',
                    'additional_info' => json_encode([
                        'migrated_from_event_id' => $event->id,
                        'original_event_contact_id' => $eventContact->id,
                    ]),
                ]
            );
        }
    }

    private function processEvents(BandEvents $event, Bookings $booking)
    {
        $eventDate = Carbon::parse($event->event_time)->format('Y-m-d');
        $eventTime = Carbon::parse($event->event_time)->format('H:i:00');
        Events::create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $event->event_type_id,
            'date' => $eventDate,
            'time' => $eventTime,
            'notes' => $event->notes,
            'title' => $event->event_name,
            'key' => $event->event_key,
            'additional_data' => [
                'migrated_from_event_id' => $event->id,
                'public' => $event->public,
                'outside' => $event->outside,
                'lodging' => $event->lodging,
                'production_needed' => $event->production_needed,
                'backline_provided' => $event->backline_provided,
                'attire' => $event->colorway_text,
                'times' => array_filter([
                    ['title' => 'Band Load-In', 'time' => $event->band_loadin_time],
                    ['title' => 'Rhythm Load-In', 'time' => $event->rhythm_loadin_time],
                    ['title' => 'Production Load-In', 'time' => $event->production_loadin_time],
                    ['title' => 'End Time', 'time' => $event->end_time],
                    ['title' => 'Quiet Time', 'time' => $event->quiet_time],
                    $event->event_type_id === 1 ? ['title' => 'Ceremony', 'time' => $event->ceremony_time] : null,
                ]),
                $event->event_type_id === 1 ? 'wedding' : null => $event->event_type_id === 1 ? [
                    'onsite' => $event->onsite,
                    'dances' => [
                        ['title' => 'First Dance', 'data' => $event->first_dance],
                        ['title' => 'Father Daughter', 'data' => $event->father_daughter],
                        ['title' => 'Mother Son', 'data' => $event->mother_groom],
                        ['title' => 'Money Dance', 'data' => $event->money_dance],
                        ['title' => 'Bouquet/Garter', 'data' => $event->bouquet_garter],
                    ],
                ] : null,
            ],
        ]);
    }
}
