<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $user = Auth::user();

        try {
            $results = $this->searchService->search($query);
            $bookingsFromContacts = $this->extractBookingsFromContacts($results['contacts'] ?? collect());
            $contactsFromBookings = $this->extractContactsFromBookings($results['bookings'] ?? collect());
            
            // Merge and deduplicate bookings
            $allBookings = collect($results['bookings'] ?? [])
                ->merge($bookingsFromContacts)
                ->unique('id');
            
            // Merge and deduplicate contacts
            $allContacts = collect($results['contacts'] ?? [])
                ->merge($contactsFromBookings)
                ->unique('id');
            
            $results['bookings'] = $allBookings;
            $results['contacts'] = $allContacts;
            
            // If no results found, return an empty array
            if (empty($results)) {
                return response()->json([]);
            }

        } catch (\Exception $e) {
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
}
