<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private Bands $band;
    private string $ownerToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->band = Bands::factory()->create();
        BandOwners::create(['user_id' => $this->owner->id, 'band_id' => $this->band->id]);
        BandMembers::create(['user_id' => $this->member->id, 'band_id' => $this->band->id]);

        // Member needs the Spatie permission the middleware re-checks per band.
        setPermissionsTeamId($this->band->id);
        $this->member->assignRole('band-member');
        // Fallback if the role isn't migrated in tests:
        // $this->member->givePermissionTo('read:questionnaires');

        $this->ownerToken = $this->owner->createToken(
            'test-device', ['mobile', 'read:questionnaires', 'write:questionnaires']
        )->plainTextToken;
        $this->memberToken = $this->member->createToken(
            'test-device', ['mobile', 'read:questionnaires']
        )->plainTextToken;
    }

    private function asOwner(): array
    {
        return [
            'Authorization' => "Bearer {$this->ownerToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function asMember(): array
    {
        return [
            'Authorization' => "Bearer {$this->memberToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function makeQuestionnaire(array $attrs = []): Questionnaires
    {
        $q = new Questionnaires();
        $q->band_id = $this->band->id;
        $q->name = $attrs['name'] ?? 'Wedding Intake';
        $q->description = $attrs['description'] ?? null;
        $q->archived_at = $attrs['archived_at'] ?? null;
        $q->save();

        return $q;
    }

    public function test_owner_can_list_questionnaires(): void
    {
        $this->makeQuestionnaire(['name' => 'Wedding Intake']);

        $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires")
            ->assertOk()
            ->assertJsonCount(1, 'questionnaires')
            ->assertJsonPath('questionnaires.0.name', 'Wedding Intake')
            ->assertJsonPath('questionnaires.0.instances_count', 0);
    }

    public function test_member_can_list_questionnaires(): void
    {
        $this->makeQuestionnaire();

        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires")
            ->assertOk()
            ->assertJsonCount(1, 'questionnaires');
    }

    public function test_catalog_returns_registries(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/catalog")
            ->assertOk()
            ->assertJsonCount(13, 'field_types')
            ->assertJsonCount(7, 'mapping_targets');

        $presetKeys = array_column($response->json('presets'), 'key');
        $this->assertContains('wedding', $presetKeys);
    }

    public function test_show_returns_fields_in_position_order(): void
    {
        $q = $this->makeQuestionnaire();
        $q->fields()->create(['type' => 'short_text', 'label' => 'Second', 'position' => 20, 'required' => false]);
        $q->fields()->create(['type' => 'short_text', 'label' => 'First', 'position' => 10, 'required' => true]);

        $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}")
            ->assertOk()
            ->assertJsonPath('questionnaire.fields.0.label', 'First')
            ->assertJsonPath('questionnaire.fields.0.required', true)
            ->assertJsonPath('questionnaire.fields.1.label', 'Second');
    }

    public function test_show_cross_band_questionnaire_is_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = new Questionnaires();
        $foreign->band_id = $otherBand->id;
        $foreign->name = 'Foreign';
        $foreign->save();

        $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_token_without_ability_is_403(): void
    {
        $bareToken = $this->owner->createToken('bare-device', ['mobile'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$bareToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ])->getJson("/api/mobile/bands/{$this->band->id}/questionnaires")
            ->assertStatus(403);
    }
}
