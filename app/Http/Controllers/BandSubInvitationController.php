<?php

namespace App\Http\Controllers;

use App\Models\BandSubInvitation;
use App\Services\SubInvitationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BandSubInvitationController extends Controller
{
    protected $subInvitationService;

    public function __construct(SubInvitationService $subInvitationService)
    {
        $this->subInvitationService = $subInvitationService;
    }

    /**
     * Show the band invitation page
     */
    public function show(string $key)
    {
        $invitation = BandSubInvitation::where('invitation_key', $key)->firstOrFail();

        $invitation->load(['band', 'bandRole']);

        // If user is already authenticated
        if (Auth::check()) {
            // If invitation is still pending, accept it
            if ($invitation->pending) {
                $this->subInvitationService->acceptBandInvitation($key, Auth::user());
            }

            return redirect()->route('dashboard')
                ->with('success', 'Invitation accepted! You are now a sub for this band.');
        }

        // Show invitation page for non-authenticated users
        return Inertia::render('SubInvitation/BandShow', [
            'invitation' => $invitation,
            'band' => $invitation->band,
            'invitationKey' => $key,
            'roleName' => $invitation->role_name,
        ]);
    }

    /**
     * Accept the invitation (for authenticated users)
     */
    public function accept(string $key)
    {
        $this->subInvitationService->acceptBandInvitation($key, Auth::user());

        return redirect()
            ->route('dashboard')
            ->with('success', 'Invitation accepted! You are now a sub for this band.');
    }
}
