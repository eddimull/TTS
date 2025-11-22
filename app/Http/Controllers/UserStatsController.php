<?php

namespace App\Http\Controllers;

use App\Services\UserStatsService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserStatsController extends Controller
{
    /**
     * Display the user's personal statistics
     */
    public function index()
    {
        $user = Auth::user();
        $statsService = new UserStatsService($user);
        $stats = $statsService->getUserStats();

        return Inertia::render('UserStats/Index', [
            'stats' => $stats,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'googleMapsApiKey' => config('googlemaps.key'),
        ]);
    }
}
