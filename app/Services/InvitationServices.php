<?php

namespace App\Services;

use App\Models\BandMembers;
use App\Models\Invitations;
use App\Mail\Invitation;
use App\Models\BandOwners;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Bands;
use App\Notifications\TTSNotification;

class InvitationServices{
    public function inviteUser(string $email, int $bandid, $owner = false)
    {
        $verbage = [
            'type' => 'owner',
            'language' => 'an owner'
        ];

        if(!$owner)
        {
            $verbage = [
                'type' => 'member',
                'language' => 'a member'
            ];
        }
        $checkInvite = Invitations::where('email','=',$email)->and('band_id','=',$bandid)->and('invite_type_id','=',$owner ? 1 : 2)->first();
        if(!is_null($checkInvite))
        {
            return back()->withErrors(['user'=>'Invitation already sent!']);
        }
        $invite = Invitations::create([
            'email'=>$email,
            'band_id'=>$bandid,
            'invite_type_id'=>$owner ? 1 : 2
        ]);

        $author = Auth::user();
        $user = User::where('email', $invite->email)->first();
        $band = Bands::find($invite->band_id);
        
        if($user !== null)
        {

            if($user->ownsBand($band->id))
            {
                return back()->withErrors('User is already ' . $verbage['language'] .'.');
            }

            if($owner)
            {
                BandOwners::firstOrCreate([
                    'user_id' => $user->id,
                    'band_id' => $invite->band_id
                ]);
            }
            else
            {
                BandMembers::firstOrCreate([
                    'user_id' => $user->id,
                    'band_id' => $invite->band_id
                ]);
            }
           
            $details = [
                'title' => 'Invitation to become a band ' . $verbage['type'] . ' of ' . $band->name,
                'body' => 'You were invited to be ' . $verbage['language'] . ' of ' . $band->name . ' at TTS.'
            ];
            foreach($band->owners as $owner)
            {
               $ownerUser = User::find($owner->user_id);
               $ownerUser->notify(new TTSNotification([
                'text'=>$author->name . ' made ' . $user->name . ' ' . $verbage['language'] . ' of ' . $band->name,
                'route'=>'bands.edit',
                'routeParams'=>$band->id,
                'url'=>'/bands/' . $band->id . '/edit'
                ]));
            }
            //uncomment when out of sandbox
            Mail::to($user->email)->send(new Invitation($band,$owner));
        }
        else
        {
            $details = [
                'title' => 'Invitation to become a band ' . $verbage['type'],
                'body' => 'You were invited to be ' . $verbage['language'] . ' of ' . $band->name . ' at TTS. Create an account at http://tts.band/register'
            ];

            foreach($band->owners as $owner)
            {
               $ownerUser = User::find($owner->user_id);
               $ownerUser->notify(new TTSNotification([
                'text'=>$author->name . ' invited ' . $invite->email . ' ' . $verbage['language'] .' of ' . $band->name . ' (invitation pending)',
                'route'=>'bands.edit',
                'routeParams'=>$band->id,
                'url'=>'/bands/' . $band->id . '/edit'
                ]));
            }
            Mail::to($invite->email)->send(new Invitation($band,$owner));
        }
       


    }
}