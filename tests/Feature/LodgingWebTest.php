<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LodgingWebTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression for the create-page 500: create() queried
     * `$band->bookings()->orderByDesc('date')->get(['id', 'name', 'date'])`,
     * but `date` was moved off `bookings` onto `events` by the
     * 2026_05_03_140000 migration — the query threw
     * SQLSTATE[42S22] Column not found. An existing booking (and event) must
     * be present in the band so the bookings()/bandEventOptions() queries
     * actually execute against real rows, not just an empty result set.
     */
    public function test_create_page_renders_for_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($user)
            ->get(route('bands.lodgings.create', $band))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodging/Form'));
    }

    /**
     * Same defect as test_create_page_renders_for_owner, but via edit()
     * (line ~106), which had the identical bad `bookings.date` query.
     */
    public function test_edit_page_renders_for_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)
            ->get(route('lodgings.edit', $lodging))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodging/Form'));
    }

    public function test_index_renders_for_band_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        Lodging::factory()->create(['band_id' => $band->id, 'name' => 'Visible Hotel']);

        $this->actingAs($user)
            ->get(route('lodgings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodging/Index'));
    }

    public function test_store_creates_and_redirects(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bands.lodgings.store', $band), [
                'name'         => 'Web Hotel',
                'check_in_at'  => now()->addDays(3)->format('Y-m-d H:i:s'),
                'check_out_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'rooms'        => [['label' => 'King']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lodgings', ['name' => 'Web Hotel', 'band_id' => $band->id]);
        $this->assertDatabaseHas('lodging_rooms', ['label' => 'King']);
    }

    /**
     * PATCH semantics validate check_in_at/check_out_at independently
     * (`sometimes`), so supplying only check_out_at bypasses the
     * `after:check_in_at` rule used on store(). Without comparing against
     * the stay's currently-stored check_in_at, a caller could PATCH
     * check_out_at alone to a moment before the existing check-in and end up
     * with an inverted date range.
     */
    public function test_update_rejects_check_out_before_stored_check_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create([
            'band_id'      => $band->id,
            'check_in_at'  => now()->addDays(10)->format('Y-m-d H:i:s'),
            'check_out_at' => now()->addDays(12)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($user)
            ->from(route('lodgings.edit', $lodging))
            ->patch(route('lodgings.update', $lodging), [
                'check_out_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('check_out_at');
    }

    public function test_update_allows_moving_both_dates_to_a_valid_earlier_window(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create([
            'band_id'      => $band->id,
            'check_in_at'  => now()->addDays(10)->format('Y-m-d H:i:s'),
            'check_out_at' => now()->addDays(12)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($user)
            ->patch(route('lodgings.update', $lodging), [
                'check_in_at'  => now()->addDays(2)->format('Y-m-d H:i:s'),
                'check_out_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();
    }

    public function test_show_403s_for_stranger(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $lodging  = Lodging::factory()->create();

        $this->actingAs($stranger)
            ->get(route('lodgings.show', $lodging))
            ->assertStatus(403);
    }

    public function test_event_show_receives_lodgings_prop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        $event = \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        Lodging::factory()->create(['band_id' => $band->id, 'event_id' => $event->id, 'name' => 'Prop Hotel']);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Prop Hotel');
    }

    public function test_booking_show_receives_lodgings_prop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'booking_id' => $booking->id, 'name' => 'Booking Prop Hotel',
        ]);

        $this->actingAs($user)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertOk()
            ->assertSee('Booking Prop Hotel');
    }

    /**
     * events.show used to be guarded only by ['auth', 'verified'], so an
     * unaffiliated user reached the page with a 200 and the lodgings prop was
     * withheld at the prop level. The page itself is now gated
     * (EventsController::viewerCanAccessEvent — see EventShowAccessTest), so a
     * stranger never renders it at all and the stay stays hidden a layer
     * earlier. Kept as a lodging-specific regression: if the page gate is ever
     * relaxed, this must fail rather than silently leak hotel details.
     */
    public function test_event_show_hides_lodgings_from_non_member(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        $event = \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'event_id' => $event->id, 'name' => 'Secret Hotel',
        ]);

        $this->actingAs($stranger)
            ->get(route('events.show', $event))
            ->assertStatus(403)
            ->assertDontSee('Secret Hotel');
    }

    /**
     * The booking page is behind `booking.access`, which admits only owners and
     * members — a sub cannot reach it at all, so no prop-level gating is needed
     * there. Locks in the 403 that makes that reasoning safe.
     */
    public function test_booking_show_403s_for_non_member(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);

        $this->actingAs($stranger)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertStatus(403);
    }

    /**
     * C1/M4 regression: showAttachment() previously gated only on
     * authorizeRead($attachment->lodging->band_id), i.e. canRead('lodging'),
     * which is band-wide for a stranger too (403s) but was the same
     * band-wide-only check that let a sub cross gigs on the mobile serve
     * route. Locks in that a stranger with no band relationship is 403'd
     * before ever reaching the sub per-stay check.
     */
    public function test_show_attachment_403s_for_stranger(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);
        $attachment = $lodging->attachments()->create([
            'filename' => 'x.jpg', 'stored_filename' => 'x/x.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);

        $this->actingAs($stranger)
            ->get(route('lodgings.attachments.show', $attachment))
            ->assertStatus(403);
    }

    /**
     * I1 regression: showAttachment() dereferenced $attachment->lodging->band_id
     * directly; a soft-deleted parent Lodging makes that relation null
     * (SoftDeletingScope). Decision: 404, the stay is gone.
     */
    public function test_show_attachment_404s_for_soft_deleted_lodging(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);
        $attachment = $lodging->attachments()->create([
            'filename' => 'x.jpg', 'stored_filename' => 'x/x.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);

        $lodging->delete();

        $this->actingAs($user)
            ->get(route('lodgings.attachments.show', $attachment))
            ->assertStatus(404);
    }

    /**
     * A filename containing a double quote and CRLF is user-controlled
     * (uploader's original filename) — naively concatenating it into
     * `filename="..."` would let it break out of the quoted-string and
     * inject arbitrary header bytes. HeaderUtils::makeDisposition() must
     * produce a single well-formed header value with no raw quote/newline.
     */
    /**
     * Regression: UploadedFile::storeAs() returns false (not an exception)
     * on a storage-driver failure (e.g. S3/MinIO unreachable). Previously
     * that `false` was persisted directly as `stored_filename`, creating a
     * phantom attachment row that serves Content-Length: 0 forever. Mock
     * the disk (Storage::fake() would make storeAs() succeed, defeating the
     * point) so putFileAs() returns false, and assert the endpoint 500s
     * with no row created.
     */
    public function test_upload_attachment_aborts_when_storage_write_fails(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);

        $failingDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $failingDisk->shouldReceive('putFileAs')->andReturn(false);
        Storage::shouldReceive('disk')->andReturn($failingDisk);

        $this->actingAs($user)
            ->post(route('lodgings.attachments.upload', $lodging), [
                'files' => [UploadedFile::fake()->image('confirmation.jpg')],
            ])
            ->assertStatus(500);

        $this->assertDatabaseCount('lodging_attachments', 0);
    }

    public function test_show_attachment_sanitizes_malicious_filename_in_content_disposition(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);
        $maliciousName = "evil\".jpg\r\nX-Injected: 1";
        $attachment = $lodging->attachments()->create([
            'filename' => $maliciousName, 'stored_filename' => 'x/evil.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $response = $this->actingAs($user)
            ->get(route('lodgings.attachments.show', $attachment))
            ->assertOk();

        $header = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $header);
        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        $this->assertMatchesRegularExpression('/filename="(?:[^"\\\\]|\\\\.)*"/', $header);
    }
}
