<?php

namespace App\Console\Commands;

use App\Models\QuestionnaireFields;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnairePresetRegistry;
use Illuminate\Console\Command;

/**
 * Gives existing questionnaires the mapping targets the wedding preset
 * gained later (toasts, colors, "Anything else"). Only fields with no
 * mapping_target whose label exactly matches a preset label are touched,
 * and only on templates and instances the client hasn't submitted yet —
 * a submitted instance's fields are a record of what the client answered.
 */
class BackfillQuestionnaireMappings extends Command
{
    protected $signature = 'questionnaires:backfill-mappings
                            {--dry-run : List what would change without writing}';

    protected $description = 'Set wedding-preset mapping targets on unmapped template and unsubmitted instance fields';

    public function handle(QuestionnairePresetRegistry $presets, QuestionnaireMappingRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $byLabel = $presets->mappedLabels('wedding');
        $labels = array_keys($byLabel);

        $templateFields = QuestionnaireFields::query()
            ->whereNull('mapping_target')
            ->whereIn('label', $labels)
            ->whereHas('questionnaire')
            ->orderBy('id')
            ->get();

        $instanceFields = QuestionnaireInstanceFields::query()
            ->whereNull('mapping_target')
            ->whereIn('label', $labels)
            ->whereHas('instance', fn ($q) => $q
                ->whereIn('status', [QuestionnaireInstances::STATUS_SENT, QuestionnaireInstances::STATUS_IN_PROGRESS])
                ->whereNull('submitted_at'))
            ->orderBy('id')
            ->get();

        $templateCount = $this->backfill($templateFields, 'template', 'questionnaire_id', $byLabel, $registry, $dryRun);
        $instanceCount = $this->backfill($instanceFields, 'instance', 'instance_id', $byLabel, $registry, $dryRun);

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$templateCount} template field(s) and {$instanceCount} instance field(s).");

        return self::SUCCESS;
    }

    /**
     * @param  iterable<QuestionnaireFields|QuestionnaireInstanceFields>  $fields
     * @param  array<string, array{type: string, mapping_target: string}>  $byLabel
     */
    private function backfill(
        iterable $fields,
        string $kind,
        string $parentColumn,
        array $byLabel,
        QuestionnaireMappingRegistry $registry,
        bool $dryRun,
    ): int {
        $count = 0;
        foreach ($fields as $field) {
            // whereIn on label is collation-insensitive; the match must be exact.
            $preset = $byLabel[$field->label] ?? null;
            if ($preset === null) {
                continue;
            }
            $target = $preset['mapping_target'];
            if (!$registry->isCompatible($target, $field->type)) {
                $this->line("  skip {$kind} field #{$field->id} \"{$field->label}\": type {$field->type} not compatible with {$target}");
                continue;
            }

            $this->line("  {$kind} field #{$field->id} ({$parentColumn} {$field->{$parentColumn}}) \"{$field->label}\" → {$target}");
            if (!$dryRun) {
                $field->update(['mapping_target' => $target]);
            }
            $count++;
        }

        return $count;
    }
}
