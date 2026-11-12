<?php

namespace Tests\Feature\Api\Mobile;

use App\Events\BandDataChanged;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\Invoices;
use App\Models\Payments;
use App\Models\User;
use App\Notifications\BalanceSettled;
use App\Notifications\DepositPaymentReminder;
use App\Notifications\FinalPaymentReminder;
use App\Services\PaymentDueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BookingWriteOffTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Bands $band;
    private Contacts $contact;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-12 12:00:00');

        $this->owner = User::factory()->create(['name' => 'Olive Owner']);
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->token = $this->owner->createToken('test-device')->plainTextToken;

        $this->contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'email' => 'client@example.com',
            'can_login' => true,
        ]);
    }

    /**
     * The reported case: an $11,250 show, $11,200 paid, played more than 300
     * days ago, contract signed, with one client contact.
     */
    private function shortBooking(
        string $eventDate = '2026-01-10',
        int $price = 11250,
        int $paid = 11200,
    ): Bookings {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Smith Wedding',
            'price' => $price,
            'status' => 'confirmed',
            'deposit_type' => 'percent',
            'deposit_value' => '50.00',
        ]);
        Events::factory()->forBand($this->band)->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => $eventDate,
        ]);
        Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id' => $booking->id,
            'status' => 'completed',
            'signed_at' => '2025-06-01',
        ]);
        if ($paid > 0) {
            Payments::factory()->create([
                'band_id' => $this->band->id,
                'payable_type' => Bookings::class,
                'payable_id' => $booking->id,
                'amount' => $paid,
                'status' => 'paid',
                'date' => '2025-12-01',
            ]);
        }
        $booking->contacts()->attach($this->contact->id, ['role' => 'Client', 'is_primary' => true]);

        return $booking->refresh();
    }

    private function api(?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    private function writeOffUrl(Bookings $booking, ?Bands $band = null): string
    {
        $band ??= $this->band;

        return "/api/mobile/bands/{$band->id}/bookings/{$booking->id}/write-off";
    }

    // ── Write off ─────────────────────────────────────────────────────

    public function test_write_off_settles_the_balance_without_changing_amount_paid(): void
    {
        Notification::fake();
        $booking = $this->shortBooking();

        $response = $this->api()->postJson($this->writeOffUrl($booking), ['note' => 'Client short $50, let it go']);

        $response->assertOk()
            ->assertExactJson([
                'amount_paid' => '11200.00',
                'amount_due' => '0.00',
                'is_paid' => true,
                'write_off' => [
                    'amount' => '50.00',
                    'at' => now()->toIso8601String(),
                    'by' => 'Olive Owner',
                    'note' => 'Client short $50, let it go',
                ],
            ]);

        $booking->refresh();
        $this->assertSame(5000, $booking->written_off_amount);
        $this->assertSame($this->owner->id, $booking->written_off_by);
        $this->assertSame('11200.00', $booking->amount_paid);
        $this->assertSame('0.00', $booking->amount_due);
        $this->assertTrue($booking->is_paid);
        $this->assertTrue($booking->is_deposit_paid);
        $this->assertSame('0.00', $booking->deposit_due);
    }

    public function test_booking_detail_and_finance_rows_include_the_write_off(): void
    {
        $booking = $this->shortBooking();

        $this->api()->getJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.write_off', null);

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $this->api()->getJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.amount_due', '0.00')
            ->assertJsonPath('booking.is_paid', true)
            ->assertJsonPath('booking.write_off.amount', '50.00')
            ->assertJsonPath('booking.write_off.by', 'Olive Owner')
            ->assertJsonPath('booking.write_off.note', null)
            ->assertJsonPath('booking.payment_due.write_off.amount', '50.00')
            ->assertJsonPath('booking.payment_due.balance.owed', '0.00')
            ->assertJsonPath('booking.payment_due.deposit.owed', '0.00');

        $paid = $this->api()->getJson("/api/mobile/bands/{$this->band->id}/finances/paid")->assertOk();
        $this->assertSame([$booking->id], array_column($paid->json('bookings'), 'id'));
        $paid->assertJsonPath('bookings.0.write_off.amount', '50.00')
            ->assertJsonPath('bookings.0.amount_paid', '11200.00');
    }

    public function test_written_off_booking_drops_out_of_payments_due(): void
    {
        $booking = $this->shortBooking();

        $before = $this->api()->getJson("/api/mobile/bands/{$this->band->id}/finances/due")->assertOk();
        $this->assertSame([$booking->id], array_column($before->json('groups.older'), 'booking_id'));
        $before->assertJsonPath('groups.older.0.write_off', null);

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $after = $this->api()->getJson("/api/mobile/bands/{$this->band->id}/finances/due")->assertOk();
        foreach (PaymentDueService::GROUPS as $group) {
            $this->assertSame([], $after->json("groups.{$group}"), "{$group} should be empty");
            $after->assertJsonPath("summary.{$group}.count", 0);
        }
    }

    public function test_written_off_upcoming_booking_drops_out_of_deposit_and_balance_groups(): void
    {
        // Upcoming show with nothing paid: both a deposit and a balance row.
        $booking = $this->shortBooking(eventDate: '2027-03-01', price: 1000, paid: 0);
        $due = app(PaymentDueService::class)->forBand($this->band);
        $this->assertCount(2, array_merge(...array_values($due['groups'])));

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk()
            ->assertJsonPath('write_off.amount', '1000.00')
            ->assertJsonPath('amount_paid', '0.00');

        $due = app(PaymentDueService::class)->forBand($this->band);
        $this->assertSame([], array_merge(...array_values($due['groups'])));
    }

    public function test_paid_and_unpaid_scopes_count_the_write_off(): void
    {
        $booking = $this->shortBooking();

        $this->assertSame([$booking->id], $this->band->getUnpaidBookings()->pluck('id')->all());
        $this->assertSame([], $this->band->getPaidBookings()->pluck('id')->all());

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $this->assertSame([], $this->band->getUnpaidBookings()->pluck('id')->all());
        $paid = $this->band->getPaidBookings();
        $this->assertSame([$booking->id], $paid->pluck('id')->all());
        // Real money only: the scope's SQL amount_paid excludes the write-off.
        $this->assertSame('11200.00', $paid->first()->amount_paid);
        $this->assertSame('0.00', $paid->first()->amount_due);
    }

    public function test_revenue_and_trends_exclude_the_write_off(): void
    {
        $booking = $this->shortBooking();
        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $revenue = $this->api()->getJson("/api/mobile/bands/{$this->band->id}/finances/revenue")->assertOk();
        $this->assertSame(1120000, array_sum(array_column($revenue->json('revenue'), 'total')));

        $january = $this->api()->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026")
            ->assertOk()
            ->json('months.0');
        $this->assertSame(1120000, $january['paid']);
        $this->assertSame(0, $january['unpaid']);
        $this->assertSame(1125000, $january['forecast']);
    }

    public function test_write_off_broadcasts_a_band_change(): void
    {
        $booking = $this->shortBooking();
        Event::fake([BandDataChanged::class]);

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $this->band->id && $e->id === $booking->id && $e->action === 'updated',
        );

        Event::fake([BandDataChanged::class]);
        $this->api()->deleteJson($this->writeOffUrl($booking))->assertOk();
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->id === $booking->id && $e->action === 'updated',
        );
    }

    // ── Client notification ───────────────────────────────────────────

    public function test_write_off_does_not_notify_anyone_by_default(): void
    {
        Notification::fake();
        $booking = $this->shortBooking();

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();
        $this->api()->postJson($this->writeOffUrl($this->shortBooking()), ['notify_client' => false])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_write_off_notifies_contacts_when_asked_without_amount_or_wording(): void
    {
        Notification::fake();
        $booking = $this->shortBooking();
        $second = Contacts::factory()->create(['band_id' => $this->band->id, 'email' => 'planner@example.com']);
        $booking->contacts()->attach($second->id, ['role' => 'Planner', 'is_primary' => false]);

        $this->api()->postJson($this->writeOffUrl($booking), ['notify_client' => true])->assertOk();

        Notification::assertSentTo($this->contact, BalanceSettled::class);
        Notification::assertSentTo($second, BalanceSettled::class);
        Notification::assertCount(2);

        $mail = (new BalanceSettled($booking->fresh()))->toMail($this->contact);
        $html = (string) $mail->render();
        $this->assertStringContainsString('Smith Wedding', $html);
        $this->assertStringContainsString('nothing further is owed', $html);
        $this->assertStringNotContainsStringIgnoringCase('write', $html);
        $this->assertStringNotContainsString('50.00', $html);
        $this->assertStringNotContainsString('$', $html);
        $this->assertStringNotContainsStringIgnoringCase('write', $mail->subject);
    }

    public function test_balance_settled_notification_is_queued_mail(): void
    {
        $notification = new BalanceSettled($this->shortBooking());

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via($this->contact));
    }

    public function test_written_off_booking_gets_no_payment_reminders(): void
    {
        Notification::fake();
        // A week out with a balance owed: the final-payment reminder target.
        $booking = $this->shortBooking(eventDate: now()->addDays(7)->toDateString(), price: 1000, paid: 500);
        $this->assertTrue($booking->needs_final_payment_reminder);

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $booking->refresh();
        $this->assertFalse($booking->needs_final_payment_reminder);
        $this->assertFalse($booking->needs_deposit_reminder);

        $this->artisan('payments:send-final-reminders')->assertSuccessful();
        Notification::assertNotSentTo($this->contact, FinalPaymentReminder::class);
        Notification::assertNotSentTo($this->contact, DepositPaymentReminder::class);
    }

    // ── Undo ──────────────────────────────────────────────────────────

    public function test_undo_restores_the_balance_and_never_notifies(): void
    {
        Notification::fake();
        $booking = $this->shortBooking();
        $this->api()->postJson($this->writeOffUrl($booking), ['note' => 'oops'])->assertOk();

        $this->api()->deleteJson($this->writeOffUrl($booking))
            ->assertOk()
            ->assertExactJson([
                'amount_paid' => '11200.00',
                'amount_due' => '50.00',
                'is_paid' => false,
                'write_off' => null,
            ]);

        $booking->refresh();
        $this->assertSame(0, $booking->written_off_amount);
        $this->assertNull($booking->written_off_at);
        $this->assertNull($booking->written_off_by);
        $this->assertNull($booking->write_off_note);
        $this->assertSame([$booking->id], array_column(
            app(PaymentDueService::class)->forBand($this->band)['groups']['older'],
            'booking_id',
        ));
        Notification::assertNothingSent();
    }

    public function test_undo_returns_422_when_there_is_no_write_off(): void
    {
        $booking = $this->shortBooking();

        $this->api()->deleteJson($this->writeOffUrl($booking))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This booking has no write-off to undo.');
    }

    public function test_write_off_and_undo_are_logged(): void
    {
        $booking = $this->shortBooking();

        $this->api()->postJson($this->writeOffUrl($booking), ['notify_client' => true, 'note' => 'n'])->assertOk();
        $this->api()->deleteJson($this->writeOffUrl($booking))->assertOk();

        $logged = Activity::where('subject_type', Bookings::class)
            ->where('subject_id', $booking->id)
            ->whereIn('description', ['balance_written_off', 'balance_write_off_undone'])
            ->orderBy('id')
            ->get();

        $this->assertSame(['balance_written_off', 'balance_write_off_undone'], $logged->pluck('description')->all());
        $this->assertSame($this->owner->id, $logged[0]->causer_id);
        $this->assertSame('50.00', $logged[0]->properties['amount']);
        $this->assertTrue($logged[0]->properties['client_notified']);
        $this->assertSame('50.00', $logged[1]->properties['amount']);
        $this->assertFalse($logged[1]->properties['client_notified']);
    }

    // ── Refusals ──────────────────────────────────────────────────────

    public function test_write_off_returns_422_when_nothing_is_owed(): void
    {
        $booking = $this->shortBooking(price: 1000, paid: 1000);

        $this->api()->postJson($this->writeOffUrl($booking))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nothing is owed on this booking.')
            ->assertJsonValidationErrors('amount_due');

        $this->assertSame(0, $booking->fresh()->written_off_amount);
    }

    public function test_overpaid_booking_without_write_off_keeps_a_negative_amount_due(): void
    {
        $booking = $this->shortBooking(price: 1000, paid: 1200);

        $this->assertSame('-200.00', $booking->amount_due);
        $this->assertTrue($booking->is_paid);
        $this->assertNull($booking->writeOffSummary());

        $this->api()->getJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.amount_due', '-200.00');

        $this->api()->postJson($this->writeOffUrl($booking))->assertStatus(422);
    }

    public function test_write_off_returns_422_when_already_written_off(): void
    {
        $booking = $this->shortBooking();
        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $this->api()->postJson($this->writeOffUrl($booking))
            ->assertStatus(422)
            ->assertJsonPath('message', "This booking's balance has already been written off.");
    }

    public function test_write_off_validates_input(): void
    {
        $booking = $this->shortBooking();

        $this->api()->postJson($this->writeOffUrl($booking), [
            'notify_client' => 'loudly',
            'note' => str_repeat('a', 256),
        ])->assertStatus(422)->assertJsonValidationErrors(['notify_client', 'note']);
    }

    public function test_write_off_requires_authentication(): void
    {
        $booking = $this->shortBooking();

        $this->withHeaders(['X-Band-ID' => $this->band->id])
            ->postJson($this->writeOffUrl($booking))
            ->assertStatus(401);
    }

    public function test_member_without_booking_write_permission_cannot_write_off(): void
    {
        $booking = $this->shortBooking();
        $member = User::factory()->create();
        $this->band->members()->create(['user_id' => $member->id]);
        $token = $member->createToken('test-device')->plainTextToken;

        $this->api($token)->postJson($this->writeOffUrl($booking))->assertStatus(403);
        $this->api($token)->deleteJson($this->writeOffUrl($booking))->assertStatus(403);
        $this->assertSame(0, $booking->fresh()->written_off_amount);
    }

    public function test_non_member_cannot_write_off(): void
    {
        $booking = $this->shortBooking();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('test-device')->plainTextToken;

        $this->api($token)->postJson($this->writeOffUrl($booking))->assertStatus(403);
        $this->assertSame(0, $booking->fresh()->written_off_amount);
    }

    public function test_cannot_write_off_another_bands_booking_through_own_band(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = Bookings::factory()->create(['band_id' => $otherBand->id, 'price' => 1000]);

        $this->api()->postJson($this->writeOffUrl($foreign))->assertStatus(404);
        $this->assertSame(0, $foreign->fresh()->written_off_amount);
    }

    // ── Contact portal ────────────────────────────────────────────────

    public function test_portal_shows_nothing_due_and_no_write_off_in_history(): void
    {
        // Recent enough for the portal's six-month window.
        $booking = $this->shortBooking(eventDate: '2026-10-01');
        $this->api()->postJson($this->writeOffUrl($booking))->assertOk();

        $this->actingAs($this->contact, 'contact')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contact/Dashboard')
                ->where('bookings.0.id', $booking->id)
                ->where('bookings.0.amount_due', '0.00')
                ->where('bookings.0.amount_paid', '11200.00')
                ->where('bookings.0.is_paid', true)
                ->where('bookings.0.has_balance', false)
                ->has('bookings.0.payments', 1)
                ->where('bookings.0.payments.0.amount', 11200)
                ->missing('bookings.0.write_off')
                ->missing('bookings.0.written_off_amount')
            );

        $this->actingAs($this->contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking->id), ['amount' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    // ── Review follow-ups (#647) ──────────────────────────────────────

    public function test_write_off_succeeds_and_hides_a_stale_open_invoice_in_the_portal(): void
    {
        // Like booking 394: a few cents owed, with an old Stripe invoice still 'open'.
        $booking = $this->shortBooking(eventDate: '2026-10-01');
        $invoice = Invoices::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 5000,
            'status' => 'open',
            'stripe_url' => 'https://invoice.stripe.com/i/stale',
        ]);

        $this->api()->postJson($this->writeOffUrl($booking))->assertOk()
            ->assertJsonPath('amount_due', '0.00');

        $this->actingAs($this->contact, 'contact')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contact/Dashboard')
                ->has('outstandingInvoices', 0)
                ->where('bookings.0.has_balance', false)
            );

        $this->actingAs($this->contact, 'contact')
            ->get(route('portal.invoices'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoices.0.id', $invoice->id)
                ->where('invoices.0.stripe_url', null)
            );
    }

    public function test_unpaid_booking_still_shows_its_open_invoice_in_the_portal(): void
    {
        $booking = $this->shortBooking(eventDate: '2026-10-01');
        $invoice = Invoices::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 5000,
            'status' => 'open',
            'stripe_url' => 'https://invoice.stripe.com/i/live',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('outstandingInvoices', 1)
                ->where('outstandingInvoices.0.id', $invoice->id)
                ->where('outstandingInvoices.0.stripe_url', 'https://invoice.stripe.com/i/live')
            );

        $this->actingAs($this->contact, 'contact')
            ->get(route('portal.invoices'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoices.0.stripe_url', 'https://invoice.stripe.com/i/live')
            );
    }

    public function test_balance_settled_is_not_sent_once_the_write_off_is_undone(): void
    {
        Notification::fake();
        $booking = $this->shortBooking();

        $this->api()->postJson($this->writeOffUrl($booking), ['notify_client' => true])->assertOk();
        $queued = new BalanceSettled($booking->fresh());
        $this->assertTrue($queued->shouldSend($this->contact, 'mail'));

        // Undo lands before the queue worker delivers the mail.
        $this->api()->deleteJson($this->writeOffUrl($booking))->assertOk();
        $this->assertFalse($queued->shouldSend($this->contact, 'mail'));
    }

    public function test_list_and_finance_endpoints_do_not_query_per_write_off(): void
    {
        $writeOffs = function (int $count): void {
            for ($i = 0; $i < $count; $i++) {
                $booking = $this->shortBooking();
                $this->api()->postJson($this->writeOffUrl($booking))->assertOk();
            }
        };
        $endpoints = [
            "/api/mobile/bands/{$this->band->id}/bookings",
            '/api/mobile/me/bookings',
            "/api/mobile/bands/{$this->band->id}/finances",
            "/api/mobile/bands/{$this->band->id}/finances/paid",
        ];
        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->api()->getJson($url)->assertOk();
            DB::disableQueryLog();

            return collect(DB::getQueryLog())
                ->filter(fn ($q) => str_contains($q['query'], 'from `users`'))
                ->count();
        };

        $writeOffs(1);
        $baseline = array_map($count, $endpoints);
        $writeOffs(3);
        $after = array_map($count, $endpoints);

        $this->assertSame($baseline, $after, 'user lookups must not grow with the number of write-offs');
    }
}
