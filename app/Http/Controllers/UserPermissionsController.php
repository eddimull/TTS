<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\User;
use App\Models\userPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserPermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Bands $band, User $user)
    {

        $permissions = userPermissions::firstOrCreate(['band_id' => $band->id, 'user_id' => $user->id]);
        return Inertia::render('Band/ShowPermissions', ['band' => $band, 'user' => $user, 'permissions' => $permissions]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Bands $band, User $user, Request $request)
    {

        $permissions = userPermissions::firstOrCreate(['band_id' => $band->id, 'user_id' => $user->id]);
        $permissions->read_events = !empty($request->permissions['read_events']) ? $request->permissions['read_events'] : false;
        $permissions->write_events = !empty($request->permissions['write_events']) ? $request->permissions['write_events'] : false;
        $permissions->read_proposals = !empty($request->permissions['read_proposals']) ? $request->permissions['read_proposals'] : false;
        $permissions->write_proposals = !empty($request->permissions['write_proposals']) ? $request->permissions['write_proposals'] : false;
        $permissions->read_invoices = !empty($request->permissions['read_invoices']) ? $request->permissions['read_invoices'] : false;
        $permissions->write_invoices = !empty($request->permissions['write_invoices']) ? $request->permissions['write_invoices'] : false;
        $permissions->read_colors = !empty($request->permissions['read_colors']) ? $request->permissions['read_colors'] : false;
        $permissions->write_colors = !empty($request->permissions['write_colors']) ? $request->permissions['write_colors'] : false;
        $permissions->read_charts = !empty($request->permissions['read_charts']) ? $request->permissions['read_charts'] : false;
        $permissions->write_charts = !empty($request->permissions['write_charts']) ? $request->permissions['write_charts'] : false;
        $permissions->read_bookings = !empty($request->permissions['read_bookings']) ? $request->permissions['read_bookings'] : false;
        $permissions->write_bookings = !empty($request->permissions['write_bookings']) ? $request->permissions['write_bookings'] : false;
        $permissions->save();
        return redirect()->back()->with('successMessage', 'Permissions Updated!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function show(userPermissions $userPermissions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function edit(userPermissions $userPermissions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, userPermissions $userPermissions)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function destroy(userPermissions $userPermissions)
    {
        //
    }
}
