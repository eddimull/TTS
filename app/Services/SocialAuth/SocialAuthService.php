<?php

namespace App\Services\SocialAuth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PendingInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAuthService
{
    public function __construct(
        private readonly PendingInvitationService $pendingInvitations,
    ) {}

    /**
     * Resolve a verified provider profile to a local user:
     *  1. already linked (provider, provider_id) -> that user
     *  2. email matches an existing user        -> auto-link + return it
     *  3. otherwise                              -> create user + link,
     *     honoring pending invitations exactly like email registration.
     *
     * Providers verify email ownership, so both auto-link and creation mark
     * the email verified (the web dashboard sits behind `verified`).
     */
    public function resolveUser(SocialProfile $profile): User
    {
        return DB::transaction(function () use ($profile) {
            $existing = SocialAccount::where('provider', $profile->provider)
                ->where('provider_id', $profile->providerId)
                ->first();

            if ($existing) {
                if ($profile->avatarUrl && $existing->avatar_url !== $profile->avatarUrl) {
                    $existing->update(['avatar_url' => $profile->avatarUrl]);
                }

                return $existing->user;
            }

            $user = User::where('email', $profile->email)->first();

            if (!$user) {
                $user = User::create([
                    'name'     => $profile->name ?: Str::before($profile->email, '@'),
                    'email'    => $profile->email,
                    'password' => null,
                ]);
                $this->pendingInvitations->applyFor($user);
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            SocialAccount::create([
                'user_id'     => $user->id,
                'provider'    => $profile->provider,
                'provider_id' => $profile->providerId,
                'avatar_url'  => $profile->avatarUrl,
            ]);

            return $user;
        });
    }
}
