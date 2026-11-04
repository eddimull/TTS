<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Models\PaymentOverdueNotice;
use App\Models\User;
use App\Notifications\PaymentOverdue;
use App\Services\PaymentDueService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyOverduePayments extends Command
{
    /**
     * Only payments that went overdue this recently notify, which keeps old
     * debts silent on release and when a booking is confirmed late, while
     * still surviving a few missed scheduler runs.
     */
    private const RECENT_DAYS = 3;

    protected $signature = 'payments:notify-overdue';

    protected $description = 'Tell band owners when a deposit or balance has just gone overdue';

    public function handle(PaymentDueService $payments): int
    {
        $cutoff = Carbon::today()->subDays(self::RECENT_DAYS)->toDateString();
        $sent = 0;

        Bands::query()->whereHas('owners')->each(function (Bands $band) use ($payments, $cutoff, &$sent) {
            $owners = null;

            foreach ($payments->forBand($band)['groups']['overdue'] as $row) {
                if ($row['overdue_since'] < $cutoff) {
                    continue;
                }

                $notice = PaymentOverdueNotice::firstOrCreate([
                    'booking_id' => $row['booking_id'],
                    'type' => $row['type'],
                ]);
                if (!$notice->wasRecentlyCreated) {
                    continue;
                }

                $owners ??= User::whereIn('id', $band->owners()->pluck('user_id'))->get();
                Notification::send($owners, new PaymentOverdue($row));
                $sent++;
            }
        });

        $this->info("Notified owners of {$sent} overdue payment(s).");

        return self::SUCCESS;
    }
}
