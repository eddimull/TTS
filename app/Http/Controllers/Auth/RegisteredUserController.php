<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Models\Invitations;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        
        $invitationEmail = '';

        if(request('key')){
            $invitation = Invitations::where('key',request('key'))
            ->where('pending',true)
            ->firstOrFail();
            
            $invitationEmail = $invitation->email;
            
        }
        return Inertia::render('Auth/Register',compact('invitationEmail'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|confirmed|min:8',
        ]);
            
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        

        $invitations = Invitations::where('email',$user->email)->where('pending',true)->get();

        foreach($invitations as $invitation)
        {
            if($invitation->invite_type_id == 1)
            {
                BandOwners::create([
                    'user_id'=>$user->id,
                    'band_id'=>$invitation->band_id
                ]);
            }
            if($invitation->invite_type_id == 2)
            {
                BandMembers::create([
                    'user_id'=>$user->id,
                    'band_id'=>$invitation->band_id
                ]);
            }            

            $invitation->pending = false;
            $invitation->save();
        }
        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
