<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\AttireChip;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the mobile attire-chips endpoints:
 *
 *   GET    /api/mobile/bands/{band}/attire-chips
 *   POST   /api/mobile/bands/{band}/attire-chips
 *   DELETE /api/mobile/bands/{band}/attire-chips/{chip}
 *
 * Notably, the first POST against an empty band seeds six default chips
 * before inserting the new one.
 */
class AttireChipTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_CHIPS = [
        'All black',
        'All white',
        'Black tie',
        'Cocktail',
        'Smart casual',
        'Casual',
    ];

    private function makeOwnedBand(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'token');
    }

    private function headers(string $token, Bands $band): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * `band_id` is guarded on the model (not in $fillable) so we bypass it
     * with forceFill — same pattern the controller uses internally.
     */
    private function makeChip(Bands $band, string $label, int $position): AttireChip
    {
        $chip = (new AttireChip())->forceFill([
            'band_id'  => $band->id,
            'label'    => $label,
            'position' => $position,
        ]);
        $chip->save();
        return $chip;
    }

    // ── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_empty_array_when_band_has_no_chips(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $response = $this->withHeaders($this->headers($token, $band))
            ->getJson("/api/mobile/bands/{$band->id}/attire-chips");

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_index_returns_chips_ordered_by_position_then_label(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->makeChip($band, 'Zeta', 5);
        $this->makeChip($band, 'Alpha', 1);
        $this->makeChip($band, 'Bravo', 1);

        $response = $this->withHeaders($this->headers($token, $band))
            ->getJson("/api/mobile/bands/{$band->id}/attire-chips");

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();
        // position 1 (Alpha then Bravo by label), then position 5 (Zeta)
        $this->assertSame(['Alpha', 'Bravo', 'Zeta'], $labels);
    }

    public function test_index_does_not_leak_other_bands_chips(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $otherBand = Bands::factory()->create();
        $this->makeChip($otherBand, 'Other', 0);
        $this->makeChip($band, 'Mine', 0);

        $response = $this->withHeaders($this->headers($token, $band))
            ->getJson("/api/mobile/bands/{$band->id}/attire-chips");

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();
        $this->assertSame(['Mine'], $labels);
    }

    public function test_index_returns_403_for_non_member(): void
    {
        $user      = User::factory()->create();
        $otherBand = Bands::factory()->create();
        $token     = $user->createToken('test-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $otherBand))
            ->getJson("/api/mobile/bands/{$otherBand->id}/attire-chips")
            ->assertStatus(403);
    }

    // ── store: seeding ──────────────────────────────────────────────────────

    public function test_first_save_seeds_six_defaults_plus_new_chip(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'Disco'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Disco');

        $chips = AttireChip::where('band_id', $band->id)
            ->orderBy('position')->get(['label', 'position']);

        // 6 defaults + 1 new = 7 rows total
        $this->assertCount(7, $chips);

        // Defaults preserved in declared order, position 0..5
        foreach (self::DEFAULT_CHIPS as $i => $label) {
            $this->assertSame($label,                $chips[$i]->label);
            $this->assertSame($i,                    $chips[$i]->position);
        }

        // New chip lands at position 6
        $this->assertSame('Disco', $chips[6]->label);
        $this->assertSame(6,       $chips[6]->position);
    }

    public function test_first_save_with_label_matching_default_yields_only_six_rows(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        // Case-insensitive match against "Black tie" — the user typed
        // "BLACK TIE" but we should not insert it twice.
        $response = $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'BLACK TIE'])
            ->assertOk();

        $this->assertSame(6, AttireChip::where('band_id', $band->id)->count());

        // Response should be the seeded "Black tie" row, not the user's casing.
        $response->assertJsonPath('data.label', 'Black tie');
    }

    public function test_subsequent_save_does_not_reseed_defaults(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        // Seed via the first POST.
        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'First'])
            ->assertOk();

        $this->assertSame(7, AttireChip::where('band_id', $band->id)->count());

        // Add a second new chip.
        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'Second'])
            ->assertOk();

        // 7 (defaults + First) + 1 (Second) = 8
        $this->assertSame(8, AttireChip::where('band_id', $band->id)->count());
    }

    // ── store: validation & idempotency ─────────────────────────────────────

    public function test_store_rejects_empty_label(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('label');

        $this->assertSame(0, AttireChip::where('band_id', $band->id)->count());
    }

    public function test_store_rejects_missing_label(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('label');
    }

    public function test_store_rejects_label_over_64_chars(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => str_repeat('a', 65)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('label');
    }

    public function test_store_duplicate_is_idempotent_returns_existing(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        // First save (triggers seed of defaults + "Tropical").
        $first = $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'Tropical'])
            ->assertOk()
            ->json('data');

        // Re-post with same label (different casing + whitespace) — should
        // return the same id, no new row.
        $second = $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => '  tropical  '])
            ->assertOk()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('Tropical',   $second['label']);
        $this->assertSame(
            1,
            AttireChip::where('band_id', $band->id)
                ->whereRaw('LOWER(label) = ?', ['tropical'])
                ->count(),
        );
    }

    public function test_store_trims_and_collapses_whitespace_in_label(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => "  Fancy   pants  "])
            ->assertOk()
            ->assertJsonPath('data.label', 'Fancy pants');
    }

    public function test_store_returns_403_for_non_member(): void
    {
        $user      = User::factory()->create();
        $otherBand = Bands::factory()->create();
        $token     = $user->createToken('test-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $otherBand))
            ->postJson("/api/mobile/bands/{$otherBand->id}/attire-chips", ['label' => 'Sneaky'])
            ->assertStatus(403);

        $this->assertSame(0, AttireChip::where('band_id', $otherBand->id)->count());
    }

    // ── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_removes_the_chip(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $chip = $this->makeChip($band, 'Throwaway', 0);

        $this->withHeaders($this->headers($token, $band))
            ->deleteJson("/api/mobile/bands/{$band->id}/attire-chips/{$chip->id}")
            ->assertOk();

        $this->assertNull(AttireChip::find($chip->id));
    }

    public function test_destroy_returns_404_when_chip_belongs_to_another_band(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $otherBand = Bands::factory()->create();
        $foreign = $this->makeChip($otherBand, 'Foreign', 0);

        $this->withHeaders($this->headers($token, $band))
            ->deleteJson("/api/mobile/bands/{$band->id}/attire-chips/{$foreign->id}")
            ->assertStatus(404);

        $this->assertNotNull(AttireChip::find($foreign->id));
    }

    public function test_destroy_returns_403_for_non_member(): void
    {
        $user      = User::factory()->create();
        $otherBand = Bands::factory()->create();
        $token     = $user->createToken('test-device')->plainTextToken;

        $chip = $this->makeChip($otherBand, 'Locked', 0);

        $this->withHeaders($this->headers($token, $otherBand))
            ->deleteJson("/api/mobile/bands/{$otherBand->id}/attire-chips/{$chip->id}")
            ->assertStatus(403);

        $this->assertNotNull(AttireChip::find($chip->id));
    }
}
