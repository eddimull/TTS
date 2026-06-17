<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\AccountDeletionConfirmation;
use App\Models\User;
use App\Models\State;
use App\Models\Country;
use App\Services\AccountDeletionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $states = State::all();
        $countries = Country::all();
        return Inertia::render('Account/Index',[
            'user' => $user,
            'states' => $states,
            'countries' => $countries
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $request->validate([
            'name'=>'required'
        ]);
        
        $user = User::find(Auth::user()->id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->Zip = $request->zip;
        $user->City = $request->city;
        $user->StateID = $request->state;
        $user->CountryID = $request->country;
        $user->Address1 = $request->address1;
        $user->Address2 = $request->address2;
        $user->emailNotifications = $request->emailNotifications;

        if($request->password !== '' && !is_null($request->password) && $request->password !== null)
        {
            // dd('password updated',$request->password);
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->back()->with('successMessage', 'User was successfully updated');   
    }

    /**
     * Request account deletion from the web app.
     *
     * Rather than deleting immediately, this emails the user a signed, expiring
     * confirmation link. The account is only removed when that link is opened and
     * the confirmation form is submitted (confirmDeletion / performDeletion). A
     * GET prefetch of the link can never trigger the delete (POST-only).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function requestDeletion(Request $request)
    {
        $user = Auth::user();

        Mail::to($user->email)->send(
            new AccountDeletionConfirmation($user, $this->deletionConfirmationUrl($user))
        );

        return redirect()->back()->with(
            'successMessage',
            'Check your email to confirm account deletion. The link expires in 60 minutes.'
        );
    }

    /**
     * Build the signed, expiring URL for the account-deletion confirmation page.
     * Shared by the web (requestDeletion) and mobile (Api\Mobile\AuthController)
     * request flows so both email the same neutral confirmation link.
     */
    public static function deletionConfirmationUrl(User $user): string
    {
        // Points at the GET confirmation PAGE (not the deleting action). The page
        // POSTs back to the same signed URL to actually delete — so a link
        // prefetch/scanner (which only issues GETs) can never trigger the
        // irreversible delete.
        return URL::temporarySignedRoute(
            'account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $user->id],
        );
    }

    /**
     * GET /account/confirm-deletion/{user} — signed link target. Shared by web
     * and mobile request flows.
     *
     * Renders a confirmation page with a button that POSTs back to the same
     * signed URL. It does NOT delete: GET is safe to prefetch (email security
     * scanners and "safe link" crawlers issue GETs), so the destructive action
     * lives on POST only.
     *
     * The route param is a raw int (not route-model-bound) so the signature is
     * validated BEFORE any DB lookup — no DB hit on forged links, and no
     * account-existence leak via 403-vs-404 (invalid signature always 403s).
     */
    public function confirmDeletion(Request $request, int $user): \Illuminate\Http\Response
    {
        abort_unless($request->hasValidSignature(), 403, 'This deletion link is invalid or has expired.');

        // Re-present the exact signed query string so the form can POST to the
        // same URL and pass signature validation again.
        return response()->view('account.confirm-deletion', [
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * POST /account/confirm-deletion/{user} — performs the deletion. Same signed
     * URL as the GET page; the signature is the credential. Only a deliberate
     * form submit (POST) reaches here, never a passive GET prefetch.
     */
    public function performDeletion(Request $request, int $user): \Illuminate\Http\Response
    {
        abort_unless($request->hasValidSignature(), 403, 'This deletion link is invalid or has expired.');

        $account = User::findOrFail($user);

        app(AccountDeletionService::class)->deleteAccount($account);

        return response()->view('account.deletion-confirmed');
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
