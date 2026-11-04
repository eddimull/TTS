<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\PaymentOverdueNotice;
use App\Models\Payments;
use App\Models\User;
use App\Notifications\PaymentOverdue;
use App\Services\Notifications\NotificationPresenter;
use App\Services\PaymentDueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentsDueTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 12:00:00');
        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
    }

    /**
     * A booking priced at $1,000 with a 50% deposit unless overridden.
     */
    private function booking(
        string $eventDate,
        ?string $signedAt = '2026-01-01',
        int $paid = 0,
        array $attrs = [],
    ): Bookings {
        $booking = Bookings::factory()->for($this->band, 'band')->create([
            'name' => 'Sample Booking',
            'price' => 1000,
            'status' => 'confirmed',
            'deposit_type' => 'percent',
            'deposit_value' => '50.00',
            ...$attrs,
        ]);
        Events::factory()->forBand($this->band)->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => $eventDate,
        ]);
        if ($signedAt) {
            Contracts::factory()->create([
                'contractable_type' => Bookings::class,
                'contractable_id' => $booking->id,
                'status' => 'completed',
                'signed_at' => $signedAt,
            ]);
        }
        if ($paid > 0) {
            Payments::factory()->create([
                'band_id' => $this->band->id,
                'payable_type' => Bookings::class,
                'payable_id' => $booking->id,
                'amount' => $paid,
                'status' => 'paid',
                'date' => '2026-01-02',
            ]);
        }

        return $booking->refresh();
    }

    private function due(): array
    {
        return app(PaymentDueService::class)->forBand($this->band);
    }

    private function row(array $due, string $group, string $type): array
    {
        $rows = array_values(array_filter($due['groups'][$group], fn ($r) => $r['type'] === $type));
        $this->assertCount(1, $rows, "expected one {$type} row in {$group}");

        return $rows[0];
    }

    public function test_deposit_is_due_three_weeks_after_signing_and_overdue_after_that(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01');

        $row = $this->row($this->due(), 'overdue', 'deposit');

        $this->assertSame('2026-10-22', $row['due_date']);
        $this->assertSame('500.00', $row['amount']);
        $this->assertSame('overdue', $row['status']);
        $this->assertSame(13, $row['days_late']);
    }

    public function test_deposit_is_not_overdue_on_its_due_date(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-14');

        $row = $this->row($this->due(), 'next_30', 'deposit');

        $this->assertSame('2026-11-04', $row['due_date']);
        $this->assertSame('due_now', $row['status']);
    }

    public function test_balance_upcoming_outside_seven_days_of_the_event(): void
    {
        $this->booking('2026-11-20', paid: 500);

        $row = $this->row($this->due(), 'next_30', 'balance');

        $this->assertSame('2026-11-13', $row['due_date']);
        $this->assertSame('upcoming', $row['status']);
    }

    public function test_balance_is_due_now_seven_days_out_and_through_the_show_date(): void
    {
        $this->booking('2026-11-11', paid: 500);
        $this->booking('2026-11-04', paid: 500);

        $rows = $this->due()['groups']['next_30'];

        $this->assertCount(2, $rows);
        $this->assertSame(['due_now', 'due_now'], array_column($rows, 'status'));
    }

    public function test_balance_is_overdue_the_day_after_the_show_not_seven_days_before(): void
    {
        $this->booking('2026-11-03', paid: 500);

        $row = $this->row($this->due(), 'overdue', 'balance');

        $this->assertSame(1, $row['days_late']);
        $this->assertSame('2026-11-03', $row['overdue_since']);
    }

    public function test_payments_apply_to_the_deposit_first(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01', paid: 200);

        $due = $this->due();

        $this->assertSame('300.00', $this->row($due, 'overdue', 'deposit')['amount']);
        $this->assertSame('500.00', $this->row($due, 'later', 'balance')['amount']);
    }

    public function test_paid_deposit_leaves_only_the_balance(): void
    {
        $this->booking('2027-06-01', paid: 500);

        $due = $this->due();

        $this->assertSame([], $due['groups']['overdue']);
        $this->assertSame('500.00', $this->row($due, 'later', 'balance')['amount']);
    }

    public function test_a_flat_deposit_uses_its_dollar_amount(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01', attrs: [
            'deposit_type' => 'amount',
            'deposit_value' => '300.00',
        ]);

        $due = $this->due();

        $this->assertSame('300.00', $this->row($due, 'overdue', 'deposit')['amount']);
        $this->assertSame('700.00', $this->row($due, 'later', 'balance')['amount']);
    }

    public function test_signed_close_to_the_show_collapses_to_one_balance_row(): void
    {
        $this->booking('2026-11-20', signedAt: '2026-11-01');

        $due = $this->due();
        $rows = $due['groups']['next_30'];

        $this->assertCount(1, $rows);
        $this->assertSame('balance', $rows[0]['type']);
        $this->assertSame('1000.00', $rows[0]['amount']);
        $this->assertSame('2026-11-13', $rows[0]['due_date']);
    }

    public function test_merged_state_gives_the_balance_everything_owed_even_with_a_full_deposit(): void
    {
        $booking = $this->booking('2026-11-10', signedAt: '2026-11-01', attrs: [
            'deposit_type' => 'percent',
            'deposit_value' => '100.00',
        ]);

        $state = app(PaymentDueService::class)->stateFor($booking);

        $this->assertTrue($state['merged']);
        $this->assertSame('1000.00', $state['deposit']['owed']);
        $this->assertSame('1000.00', $state['balance']['owed']);
        $this->assertSame('due_now', $state['balance']['status']);
    }

    public function test_paid_off_bookings_are_not_loaded(): void
    {
        $this->booking('2027-06-01', paid: 1000);
        $open = $this->booking('2027-06-01', paid: 400);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $due = $this->due();
        $paymentsQuery = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->pluck('query')
            ->first(fn ($q) => str_starts_with($q, 'select * from `payments`'));
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Eager loading only asked for the open booking's payments.
        $this->assertStringContainsString("in ({$open->id})", $paymentsQuery);
        $this->assertSame('100.00', $this->row($due, 'overdue', 'deposit')['amount']);
    }

    public function test_shows_over_ninety_days_ago_go_to_older(): void
    {
        $this->booking('2026-07-01');

        $due = $this->due();

        $this->assertSame([], $due['groups']['overdue']);
        $this->assertCount(2, $due['groups']['older']);
        $this->assertSame(0, $due['summary']['overdue']['count']);
        $this->assertSame(2, $due['summary']['older']['count']);
    }

    public function test_only_confirmed_contracted_unpaid_bookings_appear(): void
    {
        $this->booking('2027-06-01', attrs: ['status' => 'cancelled']);
        $this->booking('2027-06-01', attrs: ['status' => 'draft']);
        $this->booking('2027-06-01', attrs: ['status' => 'pending']);
        $this->booking('2027-06-01', signedAt: null);
        $this->booking('2027-06-01', paid: 1000);

        $due = $this->due();

        foreach (PaymentDueService::GROUPS as $group) {
            $this->assertSame([], $due['groups'][$group], $group);
        }
    }

    public function test_summary_totals_each_group(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01');
        $this->booking('2027-07-01', signedAt: '2026-10-02');

        $due = $this->due();

        $this->assertSame(['count' => 2, 'total' => '1000.00'], $due['summary']['overdue']);
        $this->assertSame(['count' => 2, 'total' => '1000.00'], $due['summary']['later']);
    }

    public function test_mobile_due_endpoint_is_owner_only(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01');
        $member = User::factory()->create();
        $this->band->members()->create(['user_id' => $member->id]);
        $url = "/api/mobile/bands/{$this->band->id}/finances/due";

        $this->withToken($this->owner->createToken('t')->plainTextToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('summary.overdue.count', 1)
            ->assertJsonPath('groups.overdue.0.type', 'deposit');

        $this->app['auth']->forgetGuards();

        $this->withToken($member->createToken('t')->plainTextToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson($url)
            ->assertForbidden();
    }

    public function test_mobile_booking_show_exposes_payment_state(): void
    {
        $booking = $this->booking('2026-11-03', paid: 500);

        $this->withToken($this->owner->createToken('t')->plainTextToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.payment_due.deposit.owed', '0.00')
            ->assertJsonPath('booking.payment_due.balance.status', 'overdue')
            ->assertJsonPath('booking.payment_due.balance.overdue_since', '2026-11-03');
    }

    public function test_web_page_lists_only_owned_bands(): void
    {
        $this->booking('2027-06-01', signedAt: '2026-10-01');
        Bands::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('Payments Due'))
            ->assertInertia(fn ($page) => $page
                ->component('Finances/Due')
                ->has('bands', 1)
                ->where('bands.0.id', $this->band->id)
                ->where('bands.0.summary.overdue.count', 1));
    }

    public function test_web_booking_finances_includes_payment_state(): void
    {
        $booking = $this->booking('2027-06-01', signedAt: '2026-10-01');

        $this->actingAs($this->owner)
            ->get(route('Booking Finances', [$this->band, $booking]))
            ->assertInertia(fn ($page) => $page
                ->where('booking.payment_due.deposit.status', 'overdue')
                ->where('booking.payment_due.balance.status', 'upcoming'));
    }

    public function test_notify_tells_owners_once_when_a_payment_just_went_overdue(): void
    {
        Notification::fake();
        $this->booking('2026-11-03', paid: 500);

        $this->artisan('payments:notify-overdue')->assertSuccessful();
        $this->artisan('payments:notify-overdue')->assertSuccessful();

        Notification::assertSentToTimes($this->owner, PaymentOverdue::class, 1);
        $this->assertSame(1, PaymentOverdueNotice::count());
    }

    public function test_a_failed_dispatch_is_retried_on_the_next_run(): void
    {
        $this->booking('2026-11-03', paid: 500);

        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('queue down'));
        $this->artisan('payments:notify-overdue')->assertSuccessful();
        $this->assertSame(0, PaymentOverdueNotice::count());

        Notification::shouldReceive('send')->once();
        $this->artisan('payments:notify-overdue')->assertSuccessful();
        $this->assertSame(1, PaymentOverdueNotice::count());
    }

    public function test_notify_stays_silent_about_debts_that_went_overdue_long_ago(): void
    {
        Notification::fake();
        $this->booking('2026-10-20', paid: 500);
        $this->booking('2026-07-01');

        $this->artisan('payments:notify-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_notification_text_and_deeplink(): void
    {
        $booking = $this->booking('2026-11-03', paid: 500, attrs: ['name' => 'Cypress Club']);
        $row = $this->row($this->due(), 'overdue', 'balance');

        $this->owner->notify(new PaymentOverdue($row));
        $presented = app(NotificationPresenter::class)->present(
            \App\Models\Bandnotification::where('notifiable_id', $this->owner->id)->latest()->firstOrFail(),
            $this->owner,
        );

        $this->assertSame('Balance overdue: Cypress Club, $500.00 still unpaid after the Nov 3 show', $presented['text']);
        $this->assertSame("/bookings/{$this->band->id}/{$booking->id}/payments", $presented['deeplink']);
        $this->assertSame("/bands/{$this->band->id}/booking/{$booking->id}/finances", $presented['web_url']);
    }

    public function test_signed_at_is_set_on_completion_and_survives_later_edits(): void
    {
        $booking = Bookings::factory()->for($this->band, 'band')->create();
        $contract = Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id' => $booking->id,
        ]);
        $this->assertNull($contract->signed_at);

        $contract->update(['status' => 'completed']);
        $this->assertSame('2026-11-04', $contract->fresh()->signed_at->toDateString());

        $this->travelTo('2026-12-01');
        $contract->fresh()->update(['asset_url' => 'https://example.com/edited.pdf']);

        $this->assertSame('2026-11-04', $contract->fresh()->signed_at->toDateString());
        $this->assertSame('2026-11-25', $booking->fresh()->deposit_due_date->toDateString());
    }

    public function test_leaving_completed_clears_signed_at(): void
    {
        $booking = $this->booking('2027-06-01');
        $contract = $booking->contract;

        $contract->update(['status' => 'sent']);

        $this->assertNull($contract->fresh()->signed_at);
    }

    public function test_storing_a_generated_pdf_does_not_mark_the_contract_signed(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $booking = Bookings::factory()->for($this->band, 'band')->create();
        $contract = Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id' => $booking->id,
        ]);
        $this->actingAs($this->owner);

        $booking->storeContractPdf('%PDF generated');

        $this->assertSame('pending', $contract->fresh()->status);
        $this->assertNull($contract->fresh()->signed_at);

        $booking->storeContractPdf('%PDF signed', signed: true);

        $this->assertSame('completed', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->signed_at);
    }
}
