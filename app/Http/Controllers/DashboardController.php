<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Services\MileageService;
use App\Services\UserEventsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $events = (new UserEventsService())->getEvents();
        $upcomingCharts = (new UserEventsService())->getUpcomingCharts();


        // $stats = (new MileageService())->handle($events);
        // dd($stats);
        return Inertia::render('Dashboard',
        [
            'events'=>$events,
            'upcomingCharts'=>$upcomingCharts,
            'stats'=>[]
            ]);
        }
    
    /**
     * Load older events for infinite scroll
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadOlderEvents(Request $request)
    {
        $beforeDate = $request->input('before_date');
        
        if (!$beforeDate) {
            return response()->json(['events' => []]);
        }
        
        $beforeDate = Carbon::parse($beforeDate);
        
        // Load events before the given date, going back 30 days at a time
        $afterDate = $beforeDate->copy()->subDays(30);
        
        $events = (new UserEventsService())->getEvents($afterDate, $beforeDate);
        
        return response()->json(['events' => $events]);
    }
}
