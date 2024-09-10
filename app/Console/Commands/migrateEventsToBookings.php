<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BookingContacts;
use App\Models\Events;

class migrateEventsToBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'etl:migrate-events-to-bookings';

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
        }
        catch (\Exception $e)
        {
            DB::rollBack();
            $this->error('An error occurred during migration: ' . $e->getMessage());
        }
    }

    private function processEvent(BandEvents $event)
    {
        // Create or update booking
        $booking = Bookings::updateOrCreate(
            ['name' => $event->name],
            [
                'band_id' => $event->band_id,
                'name' => $event->event_name,
                'event_type_id' => $event->event_type_id,
                'event_date' => $event->event_time,
                'start_time' => $event->band_loadin_time,
                'end_time' => $event->end_time,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->address_street . ', ' . $event->city . ', ' . $event->state->state_name . ' ' . $event->zip,
                'price' => 0,
                'status' => 'confirmed',
                'notes' => $event->notes,
                'contract_option' => 'none',
            ]
        );

        // Update event with booking_id if not already set
        if (!$event->booking_id)
        {
            $event->update(['booking_id' => $booking->id]);
        }

        // Process contacts
        $this->processContacts($event, $booking);

        // Process events
        $this->processEvents($event, $booking);

        $this->info("Processed event ID {$event->id} - Created/Updated booking ID {$booking->id}");
    }

    private function processContacts(BandEvents $event, Bookings $booking)
    {
        // Assuming event_contacts relationship still exists on BandEvents
        foreach ($event->event_contacts as $eventContact)
        {
            $contact = Contacts::firstOrCreate(
                ['email' => $eventContact->email],
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
                    'role' => 'primary', // You might want to adjust this based on your needs
                    'is_primary' => true,
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
        Events::create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $event->event_type_id,
            'notes' => $event->notes,
            'color' => $event->colorway_text,
            'additional_data' => json_encode([
                'migrated_from_event_id' => $event->id,
                'lodging' => $event->lodging,
                'first_dance' => $event->first_dance,
                'father_daughter' => $event->father_daughter,
                'mother_groom' => $event->mother_groom,
                'bouquet_garter' => $event->bouquet_garter,
                'production_needed' => $event->production_needed,
                'backline_provided' => $event->backline_provided,
                'money_dance' => $event->money_dance,
                'onsite' => $event->onsite,
                'times' => [
                    'event_time' => $event->event_time,
                    'band_loadin_time' => $event->band_loadin_time,
                    'end_time' => $event->end_time,
                    'rhythm_loadin_time' => $event->rhythm_loadin_time,
                    'production_loadin_time' => $event->production_loadin_time,
                    'ceremony_time' => $event->ceremony_time,
                    'quiet_time' => $event->quiet_time,
                ],
            ]),

        ]);
    }
}
