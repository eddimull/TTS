<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BookingContacts;


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
}
