<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use Illuminate\Http\Request;
use App\Models\EventTypes;
use App\Models\Proposals;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\InvoiceServices;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;
                   
        $eventTypes = EventTypes::all();
        $proposals = Proposals::where('band_id','=',$bands[0]->id)->where('phase_id','=',6)->with('invoices')->get();
        // dd($proposals);
        return Inertia::render('Invoices/Index',[
            'proposals'=>$proposals,
            'eventTypes'=>$eventTypes
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Proposals $proposal, Request $request)
    {
        
        (new InvoiceServices())->createInvoice($proposal,$request);

        return back()->with('successMessage','Invoice sent in for ' . $proposal->name);
    }

}
