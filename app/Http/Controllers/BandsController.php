<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\BandOwners;
use Illuminate\Support\Facades\Auth;

class BandsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $bands = Bands::select('bands.*')->join('band_owners','bands.id','=','band_owners.band_id')->where('user_id',Auth::id())->get();
        $user = Auth::user();
        // $bandOwner = $user->ban
        // ddd(Auth::id());
        return Inertia::render('Band/Index',[
            'bands'=>$user->bandOwner
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return Inertia::render('Band/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name'=>'required',
            'site_name'=>'required|unique:bands,site_name'
        ]);
        
        // dd($request);
        $createdBand = Bands::create([
            'name'=>$request->name,
            'site_name'=>$request->site_name
        ]);

        BandOwners::create([
            'band_id'=>$createdBand->id,
            'user_id'=>Auth::id()
        ]);

        return redirect()->route('bands')->with('successMessage','Band was successfully added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $band = Bands::where('id',$id)->first();
        return Inertia::render('Band/Edit',[
            'band' => $band
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'=>'required',
            'site_name'=>'required|unique:bands,site_name'
        ]);
        
        $band = Bands::find($id);

        $band->name = $request->name;
        $band->site_name = $request->site_name;
        
        $band->save();

        return redirect()->route('bands')->with('successMessage', $band->name . ' was successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
