<?php

namespace App\Console\Commands;

use App\Models\Bookings;
use App\Notifications\DepositPaymentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendDepositReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:send-deposit-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send deposit payment reminders (3 weeks after contract signed)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending deposit payment reminders...');

        $bookings = Bookings::with(['contract', 'contacts', 'band'])
            ->whereHas('contract', function ($query) {
                $query->where('status', 'completed');
            })
            ->where('date', '>', Carbon::now()) // Only future events
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($bookings as $booking) {
            if ($booking->needs_deposit_reminder) {
                $contacts = $booking->contacts;

                if ($contacts->isEmpty()) {
                    Log::warning('No contacts found for booking needing deposit reminder', [
                        'booking_id' => $booking->id,
                        'booking_name' => $booking->name
                    ]);
                    $skippedCount++;
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

                        $sentCount++;
                    } catch (\Exception $e) {
                        Log::error('Failed to send deposit reminder', [
                            'booking_id' => $booking->id,
                            'contact_id' => $contact->id,
                            'error' => $e->getMessage()
                        ]);
                        $skippedCount++;
                    }
                }

                $this->line("Sent deposit reminder for: {$booking->name}");
            }
        }

        $this->info("Sent {$sentCount} deposit reminders.");

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} reminders. Check logs for details.");
        }

        return 0;
    }
}
