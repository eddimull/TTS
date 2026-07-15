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

    public function test_owner_can_create_blank_questionnaire(): void
    {
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaires", [
                'name' => 'New Intake',
                'description' => 'For weddings',
            ])
            ->assertStatus(201)
            ->assertJsonPath('questionnaire.name', 'New Intake')
            ->assertJsonPath('questionnaire.fields', []);

        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'name' => 'New Intake',
        ]);
    }

    public function test_create_with_preset_clones_fields(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaires", [
                'name' => 'Wedding',
                'preset_key' => 'wedding',
            ])
            ->assertStatus(201);

        $this->assertNotEmpty($response->json('questionnaire.fields'));
    }

    public function test_member_cannot_create(): void
    {
        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaires", ['name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_update_upserts_fields_and_rewrites_visibility(): void
    {
        $q = $this->makeQuestionnaire();

        $response = $this->withHeaders($this->asOwner())
            ->putJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}", [
                'name' => 'Renamed',
                'description' => null,
                'fields' => [
                    [
                        'id' => null, 'client_id' => 'tmp-1', 'type' => 'yes_no',
                        'label' => 'Onsite ceremony?', 'help_text' => null,
                        'required' => true, 'position' => 10,
                        'settings' => null, 'visibility_rule' => null, 'mapping_target' => 'wedding.onsite',
                    ],
                    [
                        'id' => null, 'client_id' => 'tmp-2', 'type' => 'short_text',
                        'label' => 'Ceremony details', 'help_text' => null,
                        'required' => false, 'position' => 20,
                        'settings' => null,
                        'visibility_rule' => ['depends_on' => 'tmp-1', 'operator' => 'equals', 'value' => 'yes'],
                        'mapping_target' => null,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('questionnaire.name', 'Renamed')
            ->assertJsonCount(2, 'questionnaire.fields');

        $fields = $response->json('questionnaire.fields');
        // depends_on must be rewritten from client_id to the persisted DB id.
        $this->assertSame($fields[0]['id'], $fields[1]['visibility_rule']['depends_on']);
    }

    public function test_update_deletes_missing_fields(): void
    {
        $q = $this->makeQuestionnaire();
        $keep = $q->fields()->create(['type' => 'short_text', 'label' => 'Keep', 'position' => 10, 'required' => false]);
        $drop = $q->fields()->create(['type' => 'short_text', 'label' => 'Drop', 'position' => 20, 'required' => false]);

        $this->withHeaders($this->asOwner())
            ->putJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}", [
                'name' => $q->name,
                'description' => null,
                'fields' => [[
                    'id' => $keep->id, 'client_id' => "id-{$keep->id}", 'type' => 'short_text',
                    'label' => 'Keep', 'help_text' => null, 'required' => false, 'position' => 10,
                    'settings' => null, 'visibility_rule' => null, 'mapping_target' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'questionnaire.fields');

        $this->assertDatabaseMissing('questionnaire_fields', ['id' => $drop->id]);
    }

    public function test_update_rejects_dropdown_without_options(): void
    {
        $q = $this->makeQuestionnaire();

        $this->withHeaders($this->asOwner())
            ->putJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}", [
                'name' => $q->name,
                'description' => null,
                'fields' => [[
                    'id' => null, 'client_id' => 'tmp-1', 'type' => 'dropdown',
                    'label' => 'Pick one', 'help_text' => null, 'required' => false, 'position' => 10,
                    'settings' => null, 'visibility_rule' => null, 'mapping_target' => null,
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_archive_and_restore(): void
    {
        $q = $this->makeQuestionnaire();

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}/archive")
            ->assertOk();
        $this->assertNotNull($q->fresh()->archived_at);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}/restore")
            ->assertOk()
            ->assertJsonPath('questionnaire.archived_at', null);
        $this->assertNull($q->fresh()->archived_at);
    }

    public function test_destroy_soft_deletes(): void
    {
        $q = $this->makeQuestionnaire();

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}")
            ->assertOk();

        $this->assertSoftDeleted('questionnaires', ['id' => $q->id]);
    }

    public function test_destroy_with_instances_is_409(): void
    {
        $q = $this->makeQuestionnaire();
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $this->band->id]);
        $contact = \App\Models\Contacts::factory()->create();

        \App\Models\QuestionnaireInstances::create([
            'questionnaire_id' => $q->id,
            'booking_id' => $booking->id,
            'recipient_contact_id' => $contact->id,
            'sent_by_user_id' => $this->owner->id,
            'name' => $q->name,
            'description' => '',
            'status' => \App\Models\QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$q->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('questionnaires', ['id' => $q->id, 'deleted_at' => null]);
    }
}
