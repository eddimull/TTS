<?php

namespace App\Services\Mobile;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    private const RESOURCES = ['bookings', 'events', 'media', 'rehearsals', 'charts'];

    public function buildAbilities(User $user): array
    {
        $abilities = ['mobile'];

        // Delegate to User::canRead()/canWrite() rather than re-checking Spatie
        // permissions directly. Those methods already encode the owner shortcut
        // AND the sub exception (a sub-of-band can read events even without the
        // `read:events` permission). Re-implementing the check here is what let
        // the token abilities drift from the controller's canRead() gate, so a
        // sub passed canRead() inside the controller but their token lacked
        // `read:events` and the mobile.band:read:events middleware 403'd them.
        foreach ($user->allBands() as $band) {
            foreach (self::RESOURCES as $resource) {
                if ($user->canRead($resource, $band->id)) {
                    $abilities[] = "read:{$resource}";
                }
                if ($user->canWrite($resource, $band->id)) {
                    $abilities[] = "write:{$resource}";
                }
            }
        }

        return array_values(array_unique($abilities));
    }

    /**
     * Re-mint the calling device's token from the user's CURRENT abilities and
     * delete the old one. Returns the new plain-text token.
     *
     * Used by the refresh endpoint and goSolo so a token can't stay stale after
     * the user's bands/roles change. $current is the token being replaced (the
     * caller's currentAccessToken), or null when none is resolvable — in which
     * case we fall back to a generic device name.
     */
    public function reissueForCurrentDevice(User $user, ?PersonalAccessToken $current): string
    {
        $deviceName = $current?->name ?: 'mobile';
        $abilities  = $this->buildAbilities($user);

        $new = $user->createToken($deviceName, $abilities)->plainTextToken;

        $current?->delete();

        return $new;
    }

    public function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar_url' => null,
        ];
    }

    public function formatBands(User $user): array
    {
        return $user->allBands()->map(fn ($b) => [
            'id'          => $b->id,
            'name'        => $b->name,
            'is_owner'    => $user->ownsBand($b->id),
            'is_personal' => (bool) $b->is_personal,
            'logo_url'    => self::resolveLogoUrl($b->logo),
        ])->values()->all();
    }

    /**
     * Resolve a stored bands.logo value to a public URL.
     *
     * Convention:
     *  - Empty/null  -> null
     *  - Starts with '/'  -> public-root path (legacy/default), e.g. '/images/default.png'
     *  - Otherwise   -> storage-relative path (uploaded file), e.g. 'logos/real.png'
     */
    public static function resolveLogoUrl(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }

        if (str_starts_with($logo, '/')) {
            return asset(ltrim($logo, '/'));
        }

        return asset('storage/' . $logo);
    }
}
