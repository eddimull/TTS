<?php

namespace App\Console\Commands;

use App\Models\Bookings;
use App\Notifications\FinalPaymentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendFinalPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:send-final-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send final payment reminders (1 week before event)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending final payment reminders...');

        $bookings = Bookings::with(['contacts', 'band'])
            ->where('date', '>', Carbon::now())
            ->where('date', '<=', Carbon::now()->addDays(8)) // Within next 8 days
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($bookings as $booking) {
            if ($booking->needs_final_payment_reminder) {
                $contacts = $booking->contacts;

                if ($contacts->isEmpty()) {
                    Log::warning('No contacts found for booking needing final payment reminder', [
                        'booking_id' => $booking->id,
                        'booking_name' => $booking->name
                    ]);
                    $skippedCount++;
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

                        $sentCount++;
                    } catch (\Exception $e) {
                        Log::error('Failed to send final payment reminder', [
                            'booking_id' => $booking->id,
                            'contact_id' => $contact->id,
                            'error' => $e->getMessage()
                        ]);
                        $skippedCount++;
                    }
                }

                $this->line("Sent final payment reminder for: {$booking->name}");
            }
        }

        $this->info("Sent {$sentCount} final payment reminders.");

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} reminders. Check logs for details.");
        }

        return 0;
    }
}
