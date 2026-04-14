<?php

namespace App\Services\Mobile;

use App\Models\User;

class TokenService
{
    private const RESOURCES = ['bookings', 'events', 'media', 'rehearsals', 'charts'];

    public function buildAbilities(User $user): array
    {
        $abilities = ['mobile'];

        foreach ($user->allBands() as $band) {
            if ($user->ownsBand($band->id)) {
                foreach (self::RESOURCES as $resource) {
                    $abilities[] = "read:{$resource}";
                    $abilities[] = "write:{$resource}";
                }
            } else {
                setPermissionsTeamId($band->id);
                foreach (self::RESOURCES as $resource) {
                    if ($user->hasPermissionTo("read:{$resource}")) {
                        $abilities[] = "read:{$resource}";
                    }
                    if ($user->hasPermissionTo("write:{$resource}")) {
                        $abilities[] = "write:{$resource}";
                    }
                }
                setPermissionsTeamId(0);
            }
        }

        return array_values(array_unique($abilities));
    }

    public function formatUser(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }

    public function formatBands(User $user): array
    {
        return $user->allBands()->map(fn ($b) => [
            'id'       => $b->id,
            'name'     => $b->name,
            'is_owner' => $user->ownsBand($b->id),
        ])->values()->all();
    }
}
