<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\MediaFile;
use App\Models\BandOwners;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression test for TTS-BAND-156.
 *
 * The mobile-auth commit added a second route block reusing the identical
 * `media/{media}/serve` and `media/{media}/thumbnail` URIs with `.token`
 * names, which overwrote the original `media.serve` / `media.thumbnail`
 * names in Laravel's route collection. BookingsController::media() calls
 * route('media.serve', $media), which then threw RouteNotFoundException —
 * but only once a media file existed in the event folder.
 */
class MediaServeRouteNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_media_serve_and_thumbnail_route_names_are_registered(): void
    {
        $this->assertTrue(
            Route::has('media.serve'),
            "Route name 'media.serve' must be registered (overwritten by media.serve.token)."
        );
        $this->assertTrue(
            Route::has('media.thumbnail'),
            "Route name 'media.thumbnail' must be registered (overwritten by media.thumbnail.token)."
        );
    }

    public function test_booking_media_page_renders_when_event_folder_has_media(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create(['site_name' => 'route-test-band']);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'media_folder_path' => 'events/route-test',
        ]);

        MediaFile::factory()->create([
            'band_id' => $band->id,
            'folder_path' => 'events/route-test',
            'media_type' => 'image',
        ]);

        $response = $this->actingAs($user)
            ->get(route('Booking Media', ['band' => $band->id, 'booking' => $booking->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Bookings/Media')->has('mediaFiles', 1)
        );
    }
}
