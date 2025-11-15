<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = $request->get('q');

        try {
            $results = $this->searchService->search($query);
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

            $results['bookings'] = $allBookings;
            $results['contacts'] = $allContacts;

            $results = $this->filterResultsForUsersBand($results);

            // Log search query with results metadata
            $this->logSearch($query, $results);

            // If no results found, return an empty array
            if (empty($results)) {
                return response()->json([]);
            }

        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage(), [
                'query' => $query,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'error' => 'Search temporarily unavailable'
            ], 500);
        }

        return response()->json($results);
    }

    private function extractBookingsFromContacts($contacts)
    {
        return $contacts->flatMap(function ($contact) {
            return $contact->bookings->map(function ($booking) {
                return $booking;
            });
        });
    }

    private function extractContactsFromBookings($bookings)
    {
        return $bookings->flatMap(function ($booking) {
            return $booking->contacts->map(function ($contact) {
                return $contact;
            });
        });
    }

    private function filterResultsForUsersBand($results)
    {
        $user = Auth::user();
        $usersBands = $user->bands()->pluck('id')->toArray();

        foreach ($results as $type => $items) {
            $results[$type] = $items->filter(function ($item) use ($usersBands) {
                return in_array($item->band_id, $usersBands);
            });
        }

        return $results;
    }

    private function logSearch(string $query, array $results): void
    {
        $resultCounts = [];
        $totalResults = 0;

        foreach ($results as $type => $items) {
            $count = $items->count();
            $resultCounts[$type] = $count;
            $totalResults += $count;
        }

        activity('search')
            ->causedBy(Auth::user())
            ->withProperties([
                'query' => $query,
                'total_results' => $totalResults,
                'results_by_type' => $resultCounts,
                'has_results' => $totalResults > 0,
            ])
            ->log('User performed search');
    }
}
