<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Proposals;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BookingContacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class migrateProposalsToBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'etl:proposals-to-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of proposals to bookings...');

        DB::beginTransaction();

        try
        {
            Proposals::chunk(100, function ($proposals)
            {
                foreach ($proposals as $proposal)
                {
                    $this->processProposal($proposal);
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

    private function processProposal(Proposals $proposal)
    {
        // Try to find a matching booking
        $matchingBooking = $this->findMatchingBooking($proposal);

        if ($matchingBooking)
        {
            $booking = $this->updateExistingBooking($matchingBooking, $proposal);
            $this->info("Updated existing booking ID {$booking->id} with proposal ID {$proposal->id}");
        }
        else
        {
            $booking = $this->createNewBooking($proposal);
            $this->info("Created new booking ID {$booking->id} from proposal ID {$proposal->id}");
        }

        // Process contacts
        $this->processContacts($proposal, $booking);

        //process contracts
        $this->handleContracts($proposal, $booking);

        // Process payments
        $this->processPayments($proposal, $booking);
    }

    private function findMatchingBooking(Proposals $proposal)
    {
        $ymd = Carbon::parse($proposal->date)->format('Y-m-d');
        return Bookings::where('name', $proposal->name)
            ->where('date', $ymd)
            ->first();
    }

    private function updateExistingBooking(Bookings $booking, Proposals $proposal)
    {
        $startTime = Carbon::parse($proposal->date);
        $endTime = $startTime->copy()->addHours($proposal->hours);

        $booking->update([
            'band_id' => $proposal->band_id,
            'event_type_id' => $proposal->event_type_id,
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $endTime->format('H:i:s'),
            'price' => $proposal->price ?? $booking->price * 100,
            'status' => $this->mapProposalPhaseToBookingStatus($proposal->phase_id),
            'notes' => $proposal->notes . "\n\nClient Notes: " . $proposal->client_notes . "\n\nMerged from proposal ID: " . $proposal->id,
            'author_id' => $proposal->author_id,
            'contract_option' => 'default',
        ]);


        return $booking;
    }

    private function createNewBooking(Proposals $proposal)
    {
        $startTime = Carbon::parse($proposal->date);
        $endTime = $startTime->copy()->addHours($proposal->hours);

        return Bookings::create([
            'band_id' => $proposal->band_id,
            'name' => $proposal->name,
            'event_type_id' => $proposal->event_type_id,
            'date' => $proposal->date,
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $endTime->format('H:i:s'),
            'venue_name' => $proposal->location ?? 'TBD',
            'venue_address' => $proposal->location,
            'price' => $proposal->price,
            'author_id' => $proposal->author_id,
            'status' => $this->mapProposalPhaseToBookingStatus($proposal->phase_id),
            'notes' => $proposal->notes . "\n\nClient Notes: " . $proposal->client_notes . "\n\nCreated from proposal ID: " . $proposal->id,
            'contract_option' => 'default',
        ]);
    }

    private function processContacts(Proposals $proposal, Bookings $booking)
    {
        foreach ($proposal->proposal_contacts as $index => $proposalContact)
        {
            $contact = Contacts::firstOrCreate(
                ['email' => $proposalContact->email, 'band_id' => $proposal->band_id],
                [
                    'name' => $proposalContact->name,
                    'phone' => $proposalContact->phonenumber,
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
                        'migrated_from_proposal_id' => $proposal->id,
                    ])
                ]
            );
        }
    }

    private function handleContracts(Proposals $proposal, Bookings $booking)
    {
        if ($proposal->contract)
        {
            $booking->contract()->create([
                'envelope_id' => $proposal->contract->envelope_id,
                'status' => $proposal->contract->status,
                'asset_url' => $proposal->contract->image_url,
                'author_id' => $proposal->author_id,
            ]);
        }
    }

    private function processPayments(Proposals $proposal, Bookings $booking)
    {


        foreach ($proposal->payments as $payment)
        {
            $booking->payments()->create([
                'name' => $payment->name,
                'band_id' => $proposal->band_id,
                'amount' => $payment->amount / 100,
                'date' => $payment->paymentDate,
                'user_id' => 3 //this might look static, but fortunately only 1 user has added payments
            ]);
        }
    }

    private function mapProposalPhaseToBookingStatus($phaseName)
    {
        switch ($phaseName)
        {
            case 1:
            case 2:
                return 'draft';
            case 3:
            case 4:
            case 5:
                return 'pending';
            case 6:
                return 'confirmed';
            default:
                return 'pending';
        }
    }

    private function parseTime($timeString)
    {
        return $timeString ? Carbon::parse($timeString)->format('H:i:s') : null;
    }
}
