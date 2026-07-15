<?php

namespace Tests\Unit\Services\Mobile;

use App\Services\Mobile\DashboardFormatter;
use PHPUnit\Framework\TestCase;

class DashboardFormatterTest extends TestCase
{
    private DashboardFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new DashboardFormatter();
    }

    /**
     * A real rehearsal must expose the Rehearsal model's primary key
     * (events.eventable_id) as `id` — NOT events.id. The mobile app navigates
     * to /rehearsals/{id} which resolves App\Models\Rehearsal::findOrFail($id),
     * so emitting events.id here 404s ("No query results for model
     * [App\Models\Rehearsal]").
     */
    public function test_rehearsal_id_is_the_rehearsal_pk_not_the_event_id(): void
    {
        $rehearsalEvent = [
            'id'             => 741,            // events.id — must NOT leak as the item id
            'eventable_id'   => 42,            // rehearsals.id — the real navigable id
            'eventable_type' => 'App\\Models\\Rehearsal',
            'key'            => 'rehearsal-abc',
            'title'          => 'Weekly Rehearsal',
            'date'           => '2026-06-27',
            'event_source'   => 'rehearsal',
            'band_id'        => null,
        ];

        $out = $this->formatter->formatEvents([$rehearsalEvent]);

        $this->assertCount(1, $out);
        $this->assertSame('rehearsal', $out[0]['event_source']);
        $this->assertSame(42, $out[0]['id'], 'Rehearsal id should be the rehearsals.id (eventable_id), not events.id');
        $this->assertSame('rehearsal-abc', $out[0]['key']);
        $this->assertSame(0, $out[0]['unread_comment_count']);
    }

    /**
     * Virtual rehearsals (recurring schedule projections) have no real Rehearsal
     * row yet — their id stays null and the mobile app navigates by key.
     */
    public function test_virtual_rehearsal_id_stays_null(): void
    {
        $virtual = [
            'id'                  => null,
            'key'                 => 'virtual-rehearsal-9-2026-06-27',
            'title'               => 'Weekly Rehearsal',
            'date'                => '2026-06-27',
            'event_source'        => 'rehearsal_schedule',
            'rehearsal_schedule_id' => 9,
            'band_id'             => null,
        ];

        $out = $this->formatter->formatEvents([$virtual]);

        $this->assertCount(1, $out);
        $this->assertSame('rehearsal_schedule', $out[0]['event_source']);
        $this->assertNull($out[0]['id']);
        $this->assertSame('virtual-rehearsal-9-2026-06-27', $out[0]['key']);
        $this->assertSame(0, $out[0]['unread_comment_count']);
    }

    /**
     * Bookings and band events are unaffected — their `id` passes through as-is
     * (the mobile app navigates them by key regardless, but the contract should
     * not change for non-rehearsal sources).
     */
    public function test_booking_id_passes_through_unchanged(): void
    {
        $booking = [
            'id'             => 555,
            'eventable_id'   => 7,
            'eventable_type' => 'App\\Models\\Bookings',
            'key'            => 'booking-xyz',
            'title'          => 'Bar Gig',
            'date'           => '2026-07-01',
            'event_source'   => 'booking',
            'band_id'        => null,
        ];

        $out = $this->formatter->formatEvents([$booking]);

        $this->assertSame('booking', $out[0]['event_source']);
        $this->assertSame(555, $out[0]['id']);
        $this->assertSame(0, $out[0]['unread_comment_count']);
    }
}
