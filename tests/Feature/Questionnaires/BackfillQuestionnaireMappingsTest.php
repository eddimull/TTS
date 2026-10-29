<?php

namespace Tests\Feature\Questionnaires;

use App\Models\QuestionnaireFields;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillQuestionnaireMappingsTest extends TestCase
{
    use RefreshDatabase;

    private function templateField(array $attrs): QuestionnaireFields
    {
        return QuestionnaireFields::factory()->create(array_merge([
            'questionnaire_id' => Questionnaires::factory(),
            'type' => 'long_text',
            'mapping_target' => null,
        ], $attrs));
    }

    private function instanceField(QuestionnaireInstances $instance, array $attrs): QuestionnaireInstanceFields
    {
        return QuestionnaireInstanceFields::factory()->create(array_merge([
            'instance_id' => $instance->id,
            'type' => 'long_text',
            'mapping_target' => null,
        ], $attrs));
    }

    public function test_dry_run_prints_changes_without_writing(): void
    {
        $field = $this->templateField(['label' => 'Wedding colors']);

        $this->artisan('questionnaires:backfill-mappings', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain("template field #{$field->id} (questionnaire_id {$field->questionnaire_id}) \"Wedding colors\" → wedding.colors")
            ->expectsOutputToContain('Would update 1 template field(s) and 0 instance field(s).')
            ->assertSuccessful();

        $this->assertNull($field->fresh()->mapping_target);
    }

    public function test_real_run_only_touches_null_targets_with_exact_preset_labels(): void
    {
        $toasts = $this->templateField(['label' => 'Names and order of toasts']);
        $colors = $this->templateField(['label' => 'Wedding colors']);
        $anything = $this->templateField(['label' => 'Anything else we should know?']);
        $firstDance = $this->templateField(['label' => 'First Dance', 'type' => 'short_text']);
        $alreadyMapped = $this->templateField(['label' => 'Wedding colors', 'mapping_target' => 'event.notes']);
        $nearMiss = $this->templateField(['label' => 'Wedding colours']);
        $caseMiss = $this->templateField(['label' => 'wedding colors']);
        $incompatible = $this->templateField(['label' => 'First Dance', 'type' => 'yes_no']);

        $this->artisan('questionnaires:backfill-mappings')->assertSuccessful();

        $this->assertSame('wedding.toasts', $toasts->fresh()->mapping_target);
        $this->assertSame('wedding.colors', $colors->fresh()->mapping_target);
        $this->assertSame('performance.notes', $anything->fresh()->mapping_target);
        $this->assertSame('wedding.dance.first', $firstDance->fresh()->mapping_target);
        $this->assertSame('event.notes', $alreadyMapped->fresh()->mapping_target);
        $this->assertNull($nearMiss->fresh()->mapping_target);
        $this->assertNull($caseMiss->fresh()->mapping_target);
        $this->assertNull($incompatible->fresh()->mapping_target);
    }

    public function test_real_run_skips_submitted_and_locked_instances(): void
    {
        $sent = QuestionnaireInstances::factory()->create();
        $inProgress = QuestionnaireInstances::factory()->inProgress()->create();
        $submitted = QuestionnaireInstances::factory()->submitted()->create();
        $locked = QuestionnaireInstances::factory()->locked()->create();

        $sentField = $this->instanceField($sent, ['label' => 'Wedding colors']);
        $inProgressField = $this->instanceField($inProgress, ['label' => 'Names and order of toasts']);
        $submittedField = $this->instanceField($submitted, ['label' => 'Wedding colors']);
        $lockedField = $this->instanceField($locked, ['label' => 'Wedding colors']);

        $this->artisan('questionnaires:backfill-mappings')
            ->expectsOutputToContain('Updated 0 template field(s) and 2 instance field(s).')
            ->assertSuccessful();

        $this->assertSame('wedding.colors', $sentField->fresh()->mapping_target);
        $this->assertSame('wedding.toasts', $inProgressField->fresh()->mapping_target);
        $this->assertNull($submittedField->fresh()->mapping_target);
        $this->assertNull($lockedField->fresh()->mapping_target);
    }
}
