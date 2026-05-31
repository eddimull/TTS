<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\SetlistPromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the mobile setlist prompt-template endpoints:
 *
 *   GET    /api/mobile/bands/{band}/setlist-prompt-templates
 *   POST   /api/mobile/bands/{band}/setlist-prompt-templates
 *   PATCH  /api/mobile/bands/{band}/setlist-prompt-templates/{template}
 *   DELETE /api/mobile/bands/{band}/setlist-prompt-templates/{template}
 */
class MobileSetlistPromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnedBand(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

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

    public function test_index_returns_band_templates(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        SetlistPromptTemplate::create([
            'band_id' => $band->id,
            'name'    => 'Wedding default',
            'prompt'  => 'High energy throughout',
        ]);

        $response = $this->withHeaders($this->headers($token, $band))
            ->getJson("/api/mobile/bands/{$band->id}/setlist-prompt-templates");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([['id', 'name', 'prompt']]);
    }

    public function test_store_creates_template_for_writer(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $response = $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/setlist-prompt-templates", [
                'name'   => 'Corporate',
                'prompt' => 'Keep it tasteful',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Corporate']);

        $this->assertDatabaseHas('setlist_prompt_templates', [
            'band_id' => $band->id,
            'name'    => 'Corporate',
        ]);
    }

    public function test_store_rejects_non_writer(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeaders($this->headers($token, $band))
            ->postJson("/api/mobile/bands/{$band->id}/setlist-prompt-templates", [
                'name'   => 'Corporate',
                'prompt' => 'Keep it tasteful',
            ]);

        $response->assertStatus(403);
    }

    public function test_update_scoped_to_band(): void
    {
        ['band' => $bandA, 'token' => $token] = $this->makeOwnedBand();

        $bandB = Bands::factory()->create();
        $templateB = SetlistPromptTemplate::create([
            'band_id' => $bandB->id,
            'name'    => 'Other band template',
            'prompt'  => 'Belongs to band B',
        ]);

        $response = $this->withHeaders($this->headers($token, $bandA))
            ->patchJson("/api/mobile/bands/{$bandA->id}/setlist-prompt-templates/{$templateB->id}", [
                'name' => 'Hijacked',
            ]);

        $response->assertStatus(404);
    }

    public function test_destroy_removes_template(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeOwnedBand();

        $template = SetlistPromptTemplate::create([
            'band_id' => $band->id,
            'name'    => 'Throwaway',
            'prompt'  => 'Delete me',
        ]);

        $response = $this->withHeaders($this->headers($token, $band))
            ->deleteJson("/api/mobile/bands/{$band->id}/setlist-prompt-templates/{$template->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('setlist_prompt_templates', [
            'id' => $template->id,
        ]);
    }
}
