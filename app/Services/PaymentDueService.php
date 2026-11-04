<?php

namespace App\Services;

use App\Models\Bands;
use App\Models\Bookings;
use Carbon\Carbon;

/**
 * The single source of "what is due, and when" for a band's bookings.
 *
 * A deposit is due 3 weeks after the contract is signed. A balance is due from
 * 7 days before the first event and only overdue once that event's date has
 * passed: the contract says 10 days, but clients may by agreement pay on or
 * before the performance. Payments apply to the deposit first.
 */
class PaymentDueService
{
    public const DEPOSIT_DUE_WEEKS = 3;
    public const BALANCE_DUE_DAYS_BEFORE_EVENT = 7;
    public const NEXT_WINDOW_DAYS = 30;
    public const OLDER_AFTER_DAYS = 90;

    public const GROUPS = ['overdue', 'next_30', 'later', 'older'];

    /**
     * Every payment still owed on the band's confirmed, contracted bookings.
     *
     * @return array{summary: array, groups: array}
     */
    public function forBand(Bands $band): array
    {
        $today = Carbon::today();

        $bookings = Bookings::query()
            ->where('band_id', $band->id)
            ->where('status', 'confirmed')
            ->whereHas('contract', fn ($q) => $q->where('status', 'completed')->whereNotNull('signed_at'))
            ->with(['contract', 'payments', 'events'])
            ->get();

        $groups = array_fill_keys(self::GROUPS, []);
        foreach ($bookings as $booking) {
            foreach ($this->rowsFor($booking, $today) as $row) {
                $groups[$row['group']][] = $row;
            }
        }

        usort($groups['overdue'], fn ($a, $b) => $b['days_late'] <=> $a['days_late']);
        usort($groups['next_30'], fn ($a, $b) => [$a['status'] !== 'due_now', $a['due_date']] <=> [$b['status'] !== 'due_now', $b['due_date']]);
        usort($groups['later'], fn ($a, $b) => $a['due_date'] <=> $b['due_date']);
        usort($groups['older'], fn ($a, $b) => $b['event_date'] <=> $a['event_date']);

        $summary = [];
        foreach ($groups as $name => $rows) {
            $cents = array_sum(array_map(fn ($r) => $this->toCents($r['amount']), $rows));
            $summary[$name] = ['count' => count($rows), 'total' => $this->toDollars($cents)];
        }

        return ['summary' => $summary, 'groups' => $groups];
    }

    /**
     * The deposit and balance state of one booking, for its finance screens.
     * Null when the booking has no signed contract, no event or isn't confirmed:
     * nothing has a due date yet.
     */
    public function stateFor(Bookings $booking, ?Carbon $today = null): ?array
    {
        $today ??= Carbon::today();

        $signedAt = $booking->contract_signed_date;
        $eventDate = $booking->start_date;
        if ($booking->status !== 'confirmed' || !$signedAt || !$eventDate) {
            return null;
        }

        $eventDate = $eventDate->copy()->startOfDay();
        $balanceDue = $eventDate->copy()->subDays(self::BALANCE_DUE_DAYS_BEFORE_EVENT);
        $depositDue = $signedAt->copy()->startOfDay()->addWeeks(self::DEPOSIT_DUE_WEEKS);

        $priceCents = $this->toCents($booking->price);
        $paidCents = $this->toCents($booking->amount_paid);
        $owedCents = max(0, $priceCents - $paidCents);
        $depositOwedCents = min($owedCents, max(0, $this->toCents($booking->expected_deposit_amount) - $paidCents));

        $balance = $this->balanceState($owedCents - $depositOwedCents, $balanceDue, $eventDate, $today);

        // Signed so close to the show that the deposit lands on or after the
        // balance's date: there's only one deadline, the balance's.
        $merged = $depositDue->gte($balanceDue);
        $deposit = $merged
            ? $this->balanceState($depositOwedCents, $balanceDue, $eventDate, $today)
            : $this->depositState($depositOwedCents, $depositDue, $today);

        return [
            'event_date' => $eventDate->toDateString(),
            'merged' => $merged,
            'deposit' => $deposit,
            'balance' => $balance,
        ];
    }

    /** @return array<int, array> */
    private function rowsFor(Bookings $booking, Carbon $today): array
    {
        $state = $this->stateFor($booking, $today);
        if (!$state) {
            return [];
        }

        $eventDate = Carbon::parse($state['event_date']);
        $isOlder = $eventDate->lt($today->copy()->subDays(self::OLDER_AFTER_DAYS));

        $rows = [];
        if ($state['merged']) {
            $cents = $this->toCents($state['deposit']['owed']) + $this->toCents($state['balance']['owed']);
            $rows[] = ['balance', [...$state['balance'], 'owed' => $this->toDollars($cents)]];
        } else {
            $rows[] = ['deposit', $state['deposit']];
            $rows[] = ['balance', $state['balance']];
        }

        $out = [];
        foreach ($rows as [$type, $payment]) {
            if ($this->toCents($payment['owed']) <= 0) {
                continue;
            }
            $out[] = [
                'booking_id' => $booking->id,
                'band_id' => $booking->band_id,
                'booking_name' => $booking->name,
                'type' => $type,
                'status' => $payment['status'],
                'group' => $this->groupFor($payment, $isOlder, $today),
                'amount' => $payment['owed'],
                'due_date' => $payment['due_date'],
                'event_date' => $state['event_date'],
                'days_late' => $payment['days_late'],
                'overdue_since' => $payment['overdue_since'],
                'deposit_type' => $booking->deposit_type,
                'deposit_value' => (string) $booking->deposit_value,
                'deposit_amount' => (string) $booking->expected_deposit_amount,
                'amount_paid' => (string) $booking->amount_paid,
            ];
        }

        return $out;
    }

    private function groupFor(array $payment, bool $isOlder, Carbon $today): string
    {
        if ($isOlder) {
            return 'older';
        }
        if ($payment['status'] === 'overdue') {
            return 'overdue';
        }

        return Carbon::parse($payment['due_date'])->lte($today->copy()->addDays(self::NEXT_WINDOW_DAYS))
            ? 'next_30'
            : 'later';
    }

    private function depositState(int $owedCents, Carbon $due, Carbon $today): array
    {
        $status = $due->lt($today) ? 'overdue' : ($due->eq($today) ? 'due_now' : 'upcoming');

        return [
            'owed' => $this->toDollars($owedCents),
            'due_date' => $due->toDateString(),
            'status' => $status,
            'days_late' => $status === 'overdue' ? (int) $due->diffInDays($today, true) : null,
            'overdue_since' => $status === 'overdue' ? $due->toDateString() : null,
        ];
    }

    private function balanceState(int $owedCents, Carbon $due, Carbon $eventDate, Carbon $today): array
    {
        $status = $eventDate->lt($today) ? 'overdue' : ($due->lte($today) ? 'due_now' : 'upcoming');

        return [
            'owed' => $this->toDollars($owedCents),
            'due_date' => $due->toDateString(),
            'status' => $status,
            'days_late' => $status === 'overdue' ? (int) $eventDate->diffInDays($today, true) : null,
            'overdue_since' => $status === 'overdue' ? $eventDate->toDateString() : null,
        ];
    }

    private function toCents(mixed $dollars): int
    {
        return (int) round((float) $dollars * 100);
    }

    private function toDollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
