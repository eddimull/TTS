<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMemberRequest;
use App\Models\Invitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\Invitation;
use App\Mail\Notification;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Error;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Notifications\TTSNotification;
use App\Services\InvitationServices;

class InvitationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
    public function createOwner(CreateMemberRequest $request, $id)
    {
        (new InvitationServices())->inviteUser($request->email,$id,true);
        return back()->with('successMessage','User invited to be an owner');
    }

    public function createMember(CreateMemberRequest $request, $id)
    {
        (new InvitationServices())->inviteUser($request->email,$id,false);
        return back()->with('successMessage',$request->email . ' has been invited to be a member');

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
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Invitations  $invitations
     * @return \Illuminate\Http\Response
     */
    public function show(Invitations $invitations)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invitations  $invitations
     * @return \Illuminate\Http\Response
     */
    public function edit(Invitations $invitations)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invitations  $invitations
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invitations $invitations)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invitations  $invitations
     * @return \Illuminate\Http\Response
     */
    public function destroy(Bands $band, $invitation)
    {
        $invite = Invitations::where('band_id','=',$band->id)->where('id','=',$invitation)->first();

        $user = Auth::user();
        if($user->ownsBand($band->id))
        {
            $invite->delete();
            return back()->with('successMessage','Invitation removed');
        }
        else
        {
            return back()->withErrors(['You are not authorized to remove this invitation.']);
        }
    }
}
