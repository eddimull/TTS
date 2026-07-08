<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InertiaSharedBandIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_props_include_all_band_ids_owner_member_and_sub(): void
    {
        $user = User::factory()->create();
        $owned = Bands::factory()->create();
        $owned->owners()->create(['user_id' => $user->id]);
        $memberOf = Bands::factory()->create();
        $memberOf->members()->create(['user_id' => $user->id]);
        // allBands() is what folds subs into the shared band_ids prop, so the
        // sub case must be exercised or a regression dropping subs slips through.
        $subOf = Bands::factory()->create();
        $user->bandSub()->attach($subOf->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.band_ids', fn ($ids) => collect($ids)
                    ->sort()->values()->all() === collect([$owned->id, $memberOf->id, $subOf->id])
                    ->sort()->values()->all()));
    }
}
