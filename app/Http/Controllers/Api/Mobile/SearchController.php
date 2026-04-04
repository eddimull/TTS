<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = $request->get('q');
        $user = $request->user();

        // Sanctum token auth does not set the session guard. Bind the user
        // so that Auth::user() works inside SearchService / activity logging.
        Auth::setUser($user);

        try {
            $results = $this->searchService->search($query);

            // Cross-reference contacts ↔ bookings (same logic as web SearchController)
            $bookingsFromContacts = $this->extractBookingsFromContacts($results['contacts'] ?? collect());
            $contactsFromBookings = $this->extractContactsFromBookings($results['bookings'] ?? collect());

            $allBookings = collect($results['bookings'] ?? [])
                ->merge($bookingsFromContacts)
                ->unique('id')
                ->values();

            $allContacts = collect($results['contacts'] ?? [])
                ->merge($contactsFromBookings)
                ->unique('id')
                ->values();

            $allSongs  = collect($results['song'] ?? []);
            $allCharts = collect($results['charts'] ?? []);

            // Filter to the authenticated user's bands only
            $userBandIds = $user->bands()->pluck('id')->toArray();

            $allBookings = $allBookings->filter(fn ($b) => in_array($b->band_id, $userBandIds));
            $allContacts = $allContacts->filter(fn ($c) => in_array($c->band_id, $userBandIds));
            $allSongs    = $allSongs->filter(fn ($s) => in_array($s->band_id, $userBandIds));
            $allCharts   = $allCharts->filter(fn ($ch) => in_array($ch->band_id, $userBandIds));

            return response()->json([
                'bookings' => $allBookings->map(fn ($b) => $this->formatBooking($b))->values(),
                'contacts' => $allContacts->map(fn ($c) => $this->formatContact($c))->values(),
                'songs'    => $allSongs->map(fn ($s) => $this->formatSong($s))->values(),
                'charts'   => $allCharts->map(fn ($ch) => $this->formatChart($ch))->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile search error: ' . $e->getMessage(), [
                'query'   => $query,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Search temporarily unavailable',
            ], 500);
        }
    }

    // ------------------------------------------------------------------
    // Cross-referencing helpers (mirrors web SearchController)
    // ------------------------------------------------------------------

    private function extractBookingsFromContacts($contacts)
    {
        return $contacts->flatMap(fn ($contact) => $contact->bookings);
    }

    private function extractContactsFromBookings($bookings)
    {
        return $bookings->flatMap(fn ($booking) => $booking->contacts);
    }

    // ------------------------------------------------------------------
    // Response formatters — intentionally lean for mobile consumption
    // ------------------------------------------------------------------

    private function formatBooking(\App\Models\Bookings $booking): array
    {
        return [
            'id'         => $booking->id,
            'band_id'    => $booking->band_id,
            'name'       => $booking->name ?? '',
            'venue_name' => $booking->venue_name ?? '',
            'date'       => $booking->date?->format('Y-m-d') ?? '',
            'status'     => $booking->status ?? '',
        ];
    }

    private function formatContact(\App\Models\Contacts $contact): array
    {
        return [
            'id'      => $contact->id,
            'band_id' => $contact->band_id,
            'name'    => $contact->name ?? '',
            'email'   => $contact->email ?? '',
            'phone'   => $contact->phone ?? '',
        ];
    }

    private function formatSong(\App\Models\Song $song): array
    {
        return [
            'id'       => $song->id,
            'band_id'  => $song->band_id,
            'title'    => $song->title ?? '',
            'artist'   => $song->artist ?? '',
            'song_key' => $song->song_key ?? '',
            'genre'    => $song->genre ?? '',
            'bpm'      => $song->bpm ?? 0,
        ];
    }

    private function formatChart(\App\Models\Charts $chart): array
    {
        return [
            'id'       => $chart->id,
            'band_id'  => $chart->band_id,
            'title'    => $chart->title ?? '',
            'composer' => $chart->composer ?? '',
        ];
    }
}
