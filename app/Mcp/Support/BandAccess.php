<?php

namespace App\Mcp\Support;

use App\Models\Bands;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides which band-scoped MCP tools a user may run in which band.
 *
 * A call is allowed only when ALL of these hold, checked in this order so an
 * error never reveals anything about a band the user can't see:
 *  1. the OAuth token carries the tool's scope;
 *  2. the user is an owner or member of the band (subs are excluded: the
 *     developer-API data these tools return is band-wide, while subs may only
 *     see the gigs they're assigned to);
 *  3. the user holds the tool's band permission (owners hold all of them), or
 *     is an owner for owner-only tools.
 */
class BandAccess
{
    /**
     * Tool name => [token scope, band permission ("read:bookings") or null, owner only].
     *
     * @var array<string, array{0: string, 1: string|null, 2: bool}>
     */
    public const TOOLS = [
        'check_availability' => [McpScopes::BOOKINGS_READ, 'read:bookings', false],
        'list_bookings' => [McpScopes::BOOKINGS_READ, 'read:bookings', false],
        'get_booking' => [McpScopes::BOOKINGS_READ, 'read:bookings', false],
        'create_booking' => [McpScopes::BOOKINGS_WRITE, 'write:bookings', false],
        'update_booking' => [McpScopes::BOOKINGS_WRITE, 'write:bookings', false],
        'delete_booking' => [McpScopes::BOOKINGS_WRITE, 'write:bookings', false],
        'add_booking_contact' => [McpScopes::BOOKINGS_WRITE, 'write:bookings', false],
        'list_events' => [McpScopes::EVENTS_READ, 'read:events', false],
        'estimate_price' => [McpScopes::PRICING_READ, null, true],
    ];

    /**
     * Bands the user owns or is a member of, by name.
     *
     * @return Collection<int, Bands>
     */
    public function bands(User $user): Collection
    {
        return $user->bandOwner
            ->merge($user->bandMember)
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function role(User $user, Bands $band): string
    {
        return $user->isOwner($band->id) ? 'owner' : 'member';
    }

    /**
     * The tools this user can run in this band with the current token.
     *
     * @return list<string>
     */
    public function toolsFor(User $user, Bands $band): array
    {
        return array_values(array_filter(
            array_keys(self::TOOLS),
            fn (string $tool) => $this->denial($user, $band, $tool) === null,
        ));
    }

    /**
     * Resolve the band for a tool call, or return the error message to show.
     */
    public function resolve(User $user, mixed $bandId, string $tool): Bands|string
    {
        if (! isset(self::TOOLS[$tool])) {
            return $this->unknownTool($tool);
        }

        [$scope] = self::TOOLS[$tool];

        if (! McpScopes::granted($user, $scope)) {
            return $this->missingScope($scope);
        }

        $band = $this->bands($user)->first(fn (Bands $band) => (string) $band->id === (string) $bandId);

        if (! $band) {
            return "Band {$bandId} is not one of your bands. Call list_bands to see the bands you can access.";
        }

        return $this->denial($user, $band, $tool) ?? $band;
    }

    /**
     * Why the user can't run this tool in this band (null when they can).
     */
    public function denial(User $user, Bands $band, string $tool): ?string
    {
        if (! isset(self::TOOLS[$tool])) {
            return $this->unknownTool($tool);
        }

        [$scope, $permission, $ownerOnly] = self::TOOLS[$tool];

        if (! McpScopes::granted($user, $scope)) {
            return $this->missingScope($scope);
        }

        if ($ownerOnly && ! $user->isOwner($band->id)) {
            return "Only band owners can use {$tool}; you are a member of {$band->name}.";
        }

        if ($permission !== null) {
            [$action, $resource] = explode(':', $permission, 2);

            // Permissions are team(band)-scoped and spatie caches the loaded
            // relations on the model; list_bands checks several bands with the
            // same User instance, so drop them before each band's check.
            $user->unsetRelation('permissions')->unsetRelation('roles');

            $allowed = $action === 'write'
                ? $user->canWrite($resource, $band->id)
                : $user->canRead($resource, $band->id);

            if (! $allowed) {
                return "You don't have permission to {$action} {$resource} in {$band->name}. A band owner can grant it in the band's member permissions.";
            }
        }

        return null;
    }

    /**
     * Fail closed when a band tool has no entry in TOOLS (e.g. a new tool or a
     * rename that didn't update the map) instead of erroring on the lookup.
     */
    private function unknownTool(string $tool): string
    {
        report(new \LogicException("MCP tool '{$tool}' has no BandAccess::TOOLS entry."));

        return "{$tool} is not available right now.";
    }

    private function missingScope(string $scope): string
    {
        return "This connection was not granted the '{$scope}' scope. Disconnect and reconnect the TTS Bandmate connector, then approve that access.";
    }
}
