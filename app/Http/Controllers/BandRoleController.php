<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\BandRole;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BandRoleController extends Controller
{
    /**
     * Display the roles management page.
     */
    public function page(Bands $band)
    {
        $this->authorize('view', $band);

        $roles = $band->bandRoles()
            ->withCount(['rosterMembers', 'eventMembers', 'substituteCallLists'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Band/Roles/Index', [
            'band' => $band,
            'roles' => $roles,
        ]);
    }

    /**
     * Display a listing of the band's roles (API).
     */
    public function index(Bands $band)
    {
        $this->authorize('view', $band);

        $roles = $band->bandRoles()
            ->withCount(['rosterMembers', 'eventMembers', 'substituteCallLists'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request, Bands $band)
    {
        $this->authorize('update', $band);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:band_roles,name,NULL,id,band_id,' . $band->id,
            'display_order' => 'sometimes|integer|min:0',
        ]);

        // Get the max display_order and add 1 if not provided
        if (!isset($validated['display_order'])) {
            $maxOrder = $band->bandRoles()->max('display_order') ?? -1;
            $validated['display_order'] = $maxOrder + 1;
        }

        $role = $band->bandRoles()->create($validated);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Bands $band, BandRole $role)
    {
        $this->authorize('update', $band);

        // Ensure the role belongs to this band
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
            'role' => $role->fresh()
        ]);
    }

    /**
     * Soft delete the specified role (set is_active to false).
     */
    public function destroy(Bands $band, BandRole $role)
    {
        $this->authorize('update', $band);

        // Ensure the role belongs to this band
        if ($role->band_id !== $band->id) {
            abort(404);
        }

        // Soft delete by setting is_active to false
        $role->update(['is_active' => false]);

        return response()->json([
            'message' => 'Role deactivated successfully'
        ]);
    }

    /**
     * Reorder roles by updating display_order.
     */
    public function reorder(Request $request, Bands $band)
    {
        $this->authorize('update', $band);

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
            'message' => 'Roles reordered successfully'
        ]);
    }
}
