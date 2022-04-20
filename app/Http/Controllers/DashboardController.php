<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Services\MileageService;
use App\Services\UserEventsService;

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
        
        
        // $stats = (new MileageService())->handle($events);
        // dd($stats);
        return Inertia::render('Dashboard',
        [
            'events'=>$events,
            'stats'=>[]
            ]);
        }
}
