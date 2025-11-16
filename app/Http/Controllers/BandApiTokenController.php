<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\BandApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class BandApiTokenController extends Controller
{
    /**
     * Display the API tokens management page
     */
    public function index(Bands $band)
    {
        $this->authorize('view', $band);

        $tokens = $band->apiTokens()->with('permissions')->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'is_active' => $token->is_active,
                'created_at' => $token->created_at->format('M d, Y'),
                'permissions' => $token->permissions->pluck('name')->toArray(),
            ];
        });

        // Get all available API permissions
        $availablePermissions = Permission::where('guard_name', 'api_token')
            ->get()
            ->map(function ($permission) {
                return [
                    'name' => $permission->name,
                    'label' => ucwords(str_replace(['api:', '-'], ['', ' '], $permission->name)),
                ];
            });

        // Get new token from session if it exists
        $newToken = session('new_api_token');

        return Inertia::render('Band/ApiTokens', [
            'band' => $band,
            'tokens' => $tokens,
            'availablePermissions' => $availablePermissions,
            'newToken' => $newToken,
        ]);
    }

    /**
     * Create a new API token
     */
    public function store(Request $request, Bands $band)
    {
        $this->authorize('update', $band);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Generate plain text token
        $plainTextToken = Str::random(60);
        $hashedToken = hash('sha256', $plainTextToken);

        $token = BandApiToken::create([
            'band_id' => $band->id,
            'token' => $hashedToken,
            'name' => $request->name ?? 'API Token',
            'is_active' => true,
        ]);

        // Assign permissions to the token
        if ($request->has('permissions') && is_array($request->permissions)) {
            $token->givePermissionTo($request->permissions);
        }

        // Store the plain text token in session until user acknowledges they've saved it
        session(['new_api_token' => [
            'token' => $plainTextToken,
            'name' => $token->name,
            'created_at' => now()->toDateTimeString(),
        ]]);

        return back();
    }

    /**
     * Dismiss the new token modal (clear from session)
     */
    public function dismissNewToken(Bands $band)
    {
        session()->forget('new_api_token');
        return back();
    }

    /**
     * Toggle token active status
     */
    public function toggle(Bands $band, BandApiToken $token)
    {
        $this->authorize('update', $band);

        if ($token->band_id !== $band->id) {
            abort(403, 'Unauthorized');
        }

        $token->update([
            'is_active' => !$token->is_active,
        ]);

        return back()->with('message', 'Token status updated');
    }

    /**
     * Delete an API token
     */
    public function destroy(Bands $band, BandApiToken $token)
    {
        $this->authorize('update', $band);

        if ($token->band_id !== $band->id) {
            abort(403, 'Unauthorized');
        }

        $token->delete();

        return back()->with('message', 'Token deleted successfully');
    }
}
