<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\BandRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of {@see \App\Http\Controllers\BandRoleController}.
 *
 * Band ownership is enforced by the `owner` middleware on the route group, so
 * no in-controller authorize() call is needed — we only confirm that the bound
 * {role} actually belongs to the {band} (returning 404 if not).
 */
class BandRolesController extends Controller
{
    public function index(Bands $band): JsonResponse
    {
        $roles = $band->bandRoles()
            ->withCount(['rosterMembers', 'eventMembers', 'substituteCallLists'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:band_roles,name,NULL,id,band_id,' . $band->id,
            'display_order' => 'sometimes|integer|min:0',
        ]);

        if (!isset($validated['display_order'])) {
            $maxOrder = $band->bandRoles()->max('display_order') ?? -1;
            $validated['display_order'] = $maxOrder + 1;
        }

        $role = $band->bandRoles()->create($validated);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role,
        ], 201);
    }

    public function update(Request $request, Bands $band, BandRole $role): JsonResponse
    {
        if ($role->band_id !== $band->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100|unique:band_roles,name,' . $role->id . ',id,band_id,' . $band->id,
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $role->update($validated);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->fresh(),
        ]);
    }

    public function destroy(Bands $band, BandRole $role): JsonResponse
    {
        if ($role->band_id !== $band->id) {
            abort(404);
        }

        // Soft delete by deactivating (mirrors web controller behavior).
        $role->update(['is_active' => false]);

        return response()->json([
            'message' => 'Role deactivated successfully',
        ]);
    }

    public function reorder(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*.id' => 'required|exists:band_roles,id',
            'roles.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['roles'] as $roleData) {
            BandRole::where('id', $roleData['id'])
                ->where('band_id', $band->id)
                ->update(['display_order' => $roleData['display_order']]);
        }

        return response()->json([
            'message' => 'Roles reordered successfully',
        ]);
    }
}
