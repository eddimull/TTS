<?php

namespace App\Http\Controllers;

use App\Enums\BandResource;
use App\Models\Bands;
use App\Models\User;
use App\Models\userPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserPermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Bands $band, User $user)
    {
        setPermissionsTeamId($band->id);

        $permissions = collect(BandResource::cases())
            ->flatMap(fn($r) => [
                $r->readPermission()  => $user->hasPermissionTo($r->readPermission()),
                $r->writePermission() => $user->hasPermissionTo($r->writePermission()),
            ])
            ->all();

        setPermissionsTeamId(0);

        return Inertia::render('Band/ShowPermissions', ['band' => $band, 'user' => $user, 'permissions' => $permissions]);
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
    public function store(Bands $band, User $user, Request $request)
    {
        $incoming = $request->permissions ?? [];

        $grant = [];
        $revoke = [];

        foreach (BandResource::cases() as $resource) {
            $read  = $resource->readPermission();
            $write = $resource->writePermission();

            if (!empty($incoming[$read])) {
                $grant[] = $read;
            } else {
                $revoke[] = $read;
            }

            if (!empty($incoming[$write])) {
                $grant[] = $write;
            } else {
                $revoke[] = $write;
            }
        }

        setPermissionsTeamId($band->id);
        $user->givePermissionTo($grant);
        $user->revokePermissionTo($revoke);
        setPermissionsTeamId(0);

        return redirect()->back()->with('successMessage', 'Permissions Updated!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function show(userPermissions $userPermissions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function edit(userPermissions $userPermissions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, userPermissions $userPermissions)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\userPermissions  $userPermissions
     * @return \Illuminate\Http\Response
     */
    public function destroy(userPermissions $userPermissions)
    {
        //
    }
}
