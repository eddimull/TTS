<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_for_band_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        Lodging::factory()->create(['band_id' => $band->id, 'name' => 'Visible Hotel']);

        $this->actingAs($user)
            ->get(route('lodgings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodging/Index'));
    }

    public function test_store_creates_and_redirects(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bands.lodgings.store', $band), [
                'name'         => 'Web Hotel',
                'check_in_at'  => now()->addDays(3)->format('Y-m-d H:i:s'),
                'check_out_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'rooms'        => [['label' => 'King']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lodgings', ['name' => 'Web Hotel', 'band_id' => $band->id]);
        $this->assertDatabaseHas('lodging_rooms', ['label' => 'King']);
    }

    public function test_show_403s_for_stranger(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $lodging  = Lodging::factory()->create();

        $this->actingAs($stranger)
            ->get(route('lodgings.show', $lodging))
            ->assertStatus(403);
    }
}
