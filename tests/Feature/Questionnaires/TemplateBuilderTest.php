<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private User $member;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
    }

    public function test_band_owner_can_view_index(): void
    {
        Questionnaires::factory()->count(2)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.index', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(fn ($a) => $a
            ->component('Questionnaires/Index')
            ->has('questionnaires', 2));
    }

    public function test_outsider_cannot_view_index(): void
    {
        $response = $this->actingAs($this->outsider)
            ->get(route('questionnaires.index', $this->band));

        $response->assertStatus(403);
    }

    public function test_band_owner_can_create_questionnaire(): void
    {
        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day', 'description' => 'Wedding details']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'name' => 'Wedding Day',
            'slug' => 'wedding-day',
        ]);
    }

    public function test_member_without_write_permission_cannot_create(): void
    {
        $response = $this->actingAs($this->member)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(403);
    }

    public function test_slug_uniqueness_scoped_to_band(): void
    {
        $otherBand = Bands::factory()->create();
        Questionnaires::factory()->create(['band_id' => $otherBand->id, 'name' => 'Wedding Day']);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'slug' => 'wedding-day',
        ]);
    }

    public function test_slug_uniqueness_within_same_band_appends_suffix(): void
    {
        Questionnaires::factory()->create(['band_id' => $this->band->id, 'name' => 'Wedding Day']);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'slug' => 'wedding-day-2',
        ]);
    }

    public function test_band_owner_can_bulk_save_template_with_fields(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => 'Wedding Day Questionnaire',
            'description' => 'Updated',
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp-1',
                    'type' => 'header',
                    'label' => 'Bride and Groom',
                    'help_text' => null,
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'tmp-2',
                    'type' => 'short_text',
                    'label' => "Bride's Name",
                    'help_text' => 'Full name with spelling',
                    'required' => true,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(302);
        $this->assertSame(2, $template->fields()->count());
        $this->assertDatabaseHas('questionnaire_fields', [
            'questionnaire_id' => $template->id,
            'label' => "Bride's Name",
            'required' => true,
        ]);
    }

    public function test_bulk_save_resolves_visibility_rule_client_ids_to_field_ids(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'description' => $template->description,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'parent',
                    'type' => 'yes_no',
                    'label' => 'Wedding party?',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'child',
                    'type' => 'short_text',
                    'label' => 'How many?',
                    'required' => false,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => [
                        'depends_on' => 'parent', // client id reference
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(302);

        $parent = $template->fields()->where('label', 'Wedding party?')->first();
        $child = $template->fields()->where('label', 'How many?')->first();

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->visibility_rule['depends_on']);
    }

    public function test_bulk_save_rejects_forward_visibility_reference(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'description' => null,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'first',
                    'type' => 'short_text',
                    'label' => 'Refers ahead',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => [
                        'depends_on' => 'second', // forward reference
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'second',
                    'type' => 'yes_no',
                    'label' => 'After',
                    'required' => false,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(
                route('questionnaires.update', [$this->band, $template]),
                $payload
            );

        $response->assertStatus(422);
    }

    public function test_bulk_save_rejects_dropdown_with_no_options(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp',
                    'type' => 'dropdown',
                    'label' => 'Pick one',
                    'required' => false,
                    'position' => 10,
                    'settings' => null, // missing options
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(
                route('questionnaires.update', [$this->band, $template]),
                $payload
            );

        $response->assertStatus(422);
    }

    public function test_bulk_save_rejects_incompatible_mapping_target(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp',
                    'type' => 'short_text', // wedding.onsite needs yes_no
                    'label' => 'Onsite?',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => 'wedding.onsite',
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(
                route('questionnaires.update', [$this->band, $template]),
                $payload
            );

        $response->assertStatus(422);
    }

    public function test_archive_marks_archived_at(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.archive', [$this->band, $template])
        );

        $response->assertStatus(302);
        $template->refresh();
        $this->assertNotNull($template->archived_at);
    }

    public function test_restore_clears_archived_at(): void
    {
        $template = Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'archived_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.restore', [$this->band, $template])
        );

        $response->assertStatus(302);
        $template->refresh();
        $this->assertNull($template->archived_at);
    }

    public function test_destroy_blocked_when_template_has_been_sent(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        \App\Models\QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $template->id,
        ]);

        $response = $this->actingAs($this->owner)->delete(
            route('questionnaires.destroy', [$this->band, $template])
        );

        $response->assertStatus(409);
        $this->assertDatabaseHas('questionnaires', ['id' => $template->id, 'deleted_at' => null]);
    }

    public function test_destroy_succeeds_for_unsent_template(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->delete(
            route('questionnaires.destroy', [$this->band, $template])
        );

        $response->assertStatus(302);
        $this->assertSoftDeleted('questionnaires', ['id' => $template->id]);
    }
}
