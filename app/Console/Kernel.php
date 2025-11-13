<?php

namespace App\Console;

use App\Mail\EventReminder;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\ProposalContracts;
use App\Notifications\DepositPaymentReminder;
use App\Notifications\FinalPaymentReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\AdvanceReminderService;
use App\Services\ProposalServices;
use Symfony\Component\ErrorHandler\Debug;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Console\Commands\DevHelpers;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function ()
        {

            $contracts = ProposalContracts::where('status', '!=', 'completed')->where('created_at', '>', Carbon::now()->subMonths(2))->get();

            foreach ($contracts as $contract)
            {
                $response = Http::withHeaders([
                    'Authorization' => 'API-Key ' . env('PANDADOC_KEY')
                ])
                    ->acceptJson()
                    ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);

                if (!$response->ok()) //don't try to parse failed requests
                {
                    continue;
                }

                if ($response['status'] == "document.completed")
                {
                    $proposal = $contract->proposal;
                    $proposal->phase_id = 6;
                    $proposal->save();
                    $contract->status = 'completed';



                    $opts = array(
                        'http' => array(
                            'method' => "GET",
                            'header' => "Authorization: API-Key " . env('PANDADOC_KEY')
                        )
                    );
                    $context = stream_context_create($opts);

                    $imagePath = $proposal->band->site_name . '/' . $proposal->name . '_signed_contract_' . time() . '.pdf';

                    Storage::disk('s3')->put(
                        $imagePath,
                        file_get_contents('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download', false, $context),
                        ['visibility' => 'public']
                    );
                    $contract->image_url = Storage::disk('s3')->url($imagePath);

                    foreach ($proposal->band->owners as $owner)
                    {
                        $user = User::find($owner->user_id);
                        $user->notify(new TTSNotification([
                            'text' => 'Contract for ' . $proposal->name . ' signed and completed!',
                            'route' => 'proposals',
                            'routeParams' => '',
                            'url' => '/proposals/'
                        ]));
                    }

                    $contract->save();

                    $proposalService = new ProposalServices($proposal);
                    $proposalService->writeToCalendar();
                }
            }
        })->everyMinute()->name('check-signed-contracts')->withoutOverlapping();


        $schedule->call(function ()
        {
            $BandEvents = BandEvents::whereDate('event_time', Carbon::today())->get();

            foreach ($BandEvents as $event)
            {

                $band = $event->band;
                $owners = $band->owners;
                $members = $band->members;
                foreach ($owners as $person)
                {
                    $member = $person->user;
                    Mail::to($member->email)->send(new EventReminder($event));
                }
                foreach ($members as $person)
                {
                    $member = $person->user;
                    Mail::to($member->email)->send(new EventReminder($event));
                }
            }
        })->dailyAt('9:00');

        $schedule->call(function ()
        {
            $bands = Bands::all();

            foreach ($bands as $band)
            {
                $reminder = new AdvanceReminderService($band);
                $reminder->searchAndSend();
            }
        })->weeklyOn(2, '23:00');

        // Send deposit payment reminders (3 weeks after contract signed)
        $schedule->call(function ()
        {
            $bookings = Bookings::with(['contract', 'contacts', 'band'])
                ->whereHas('contract', function ($query) {
                    $query->where('status', 'completed');
                })
                ->where('date', '>', Carbon::now()) // Only future events
                ->get();

            foreach ($bookings as $booking) {
                if ($booking->needs_deposit_reminder) {
                    $contacts = $booking->contacts;

                    if ($contacts->isEmpty()) {
                        Log::warning('No contacts found for booking needing deposit reminder', [
                            'booking_id' => $booking->id,
                            'booking_name' => $booking->name
                        ]);
                        continue;
                    }

                    foreach ($contacts as $contact) {
                        try {
                            $contact->notify(new DepositPaymentReminder($booking));

                            Log::info('Deposit reminder sent', [
                                'booking_id' => $booking->id,
                                'booking_name' => $booking->name,
                                'contact_id' => $contact->id,
                                'contact_email' => $contact->email,
                                'deposit_due' => $booking->deposit_due,
                            ]);

                            // Log activity on the booking
                            activity()
                                ->performedOn($booking)
                                ->withProperties([
                                    'contact_id' => $contact->id,
                                    'contact_email' => $contact->email,
                                    'deposit_due' => $booking->deposit_due,
                                    'reminder_type' => 'deposit',
                                ])
                                ->log('Deposit payment reminder sent to contact');
                        } catch (\Exception $e) {
                            Log::error('Failed to send deposit reminder', [
                                'booking_id' => $booking->id,
                                'contact_id' => $contact->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        })->dailyAt('10:00')->name('send-deposit-reminders');

        // Send final payment reminders (1 week before event)
        $schedule->call(function ()
        {
            $bookings = Bookings::with(['contacts', 'band'])
                ->where('date', '>', Carbon::now())
                ->where('date', '<=', Carbon::now()->addDays(8)) // Within next 8 days
                ->get();

            foreach ($bookings as $booking) {
                if ($booking->needs_final_payment_reminder) {
                    $contacts = $booking->contacts;

                    if ($contacts->isEmpty()) {
                        Log::warning('No contacts found for booking needing final payment reminder', [
                            'booking_id' => $booking->id,
                            'booking_name' => $booking->name
                        ]);
                        continue;
                    }

                    foreach ($contacts as $contact) {
                        try {
                            $contact->notify(new FinalPaymentReminder($booking));

                            Log::info('Final payment reminder sent', [
                                'booking_id' => $booking->id,
                                'booking_name' => $booking->name,
                                'contact_id' => $contact->id,
                                'contact_email' => $contact->email,
                                'amount_due' => $booking->amount_due,
                                'days_until_event' => now()->diffInDays($booking->date, false),
                            ]);

                            // Log activity on the booking
                            activity()
                                ->performedOn($booking)
                                ->withProperties([
                                    'contact_id' => $contact->id,
                                    'contact_email' => $contact->email,
                                    'amount_due' => $booking->amount_due,
                                    'days_until_event' => now()->diffInDays($booking->date, false),
                                    'reminder_type' => 'final_payment',
                                ])
                                ->log('Final payment reminder sent to contact');
                        } catch (\Exception $e) {
                            Log::error('Failed to send final payment reminder', [
                                'booking_id' => $booking->id,
                                'contact_id' => $contact->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        })->dailyAt('10:00')->name('send-final-payment-reminders');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
