<?php

namespace App\Http\Controllers;

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
    public function createOwner(Request $request, $id)
    {
        $request->validate([
            'email'=>'required|email:rfc,dns',
        ]);
        if(Invitations::where('email',$request->email)->where('band_id',$id)->exists())
        {
            return back()->withErrors('User already invited to be an owner.');
        }

        $invite = Invitations::create([
            'email'=>$request->email,
            'band_id'=>$id,
            'invite_type_id'=>1
        ]);


        $user = User::where('email', $invite->email)->first();
        $band = Bands::find($invite->band_id);
        
        if($user !== null)
        {
            $created = BandOwners::firstOrCreate([
                'user_id' => $user->id,
                'band_id' => $invite->band_id
            ]);
            $details = [
                'title' => 'Invitation to become a band owner',
                'body' => 'You were invited to be an owner of ' . $band->name . ' at TTS.'
            ];
            //uncomment when out of sandbox
            // Mail::to($user->email)->send(new Notification($details));
        }
        else
        {
            $details = [
                'title' => 'Invitation to become a band owner',
                'body' => 'You were invited to be an owner of ' . $band->name . ' at TTS. Create an account at http://tts.band/register'
            ];
           
            Mail::to($invite->email)->send(new Invitation($band));
        }
        return back()->with('successMessage','User invited to be an owner');
    }

    public function createMember(Request $request, $id)
    {
        $request->validate([
            'email'=>'required|email:rfc,dns',
        ]);

        Invitations::create([
            'email'=>$request->email,
            'band_id'=>$id,
            'invite_type_id'=>2
        ]);
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
            dd('cant delete');
        }
    }
}
