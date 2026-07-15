<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Questionnaires;
use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnairePresetRegistry;
use App\Services\QuestionnaireTemplateService;

class QuestionnairesController extends Controller
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private QuestionnairePresetRegistry $presetRegistry,
        private QuestionnaireTemplateService $templateService,
    ) {
    }

    public function index(Bands $band)
    {
        $questionnaires = $band->questionnaires()
            ->withCount('instances')
            ->orderBy('name')
            ->get()
            ->map(fn (Questionnaires $q) => $this->summary($q));

        return response()->json(['questionnaires' => $questionnaires]);
    }

    public function catalog(Bands $band)
    {
        return response()->json([
            'field_types' => $this->typeRegistry->catalog(),
            'mapping_targets' => $this->mappingRegistry->catalog(),
            'presets' => $this->presetRegistry->catalog(),
        ]);
    }

    public function show(Bands $band, Questionnaires $questionnaire)
    {
        $this->ensureBelongsToBand($band, $questionnaire);
        $questionnaire->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)]);
    }

    private function ensureBelongsToBand(Bands $band, Questionnaires $questionnaire): void
    {
        abort_if($questionnaire->band_id !== $band->id, 404, 'Questionnaire does not belong to this band');
    }

    private function summary(Questionnaires $q): array
    {
        return [
            'id' => $q->id,
            'name' => $q->name,
            'description' => $q->description,
            'archived_at' => $q->archived_at?->toIso8601String(),
            'instances_count' => $q->instances_count ?? 0,
            'updated_at' => $q->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Questionnaires $q): array
    {
        return $this->summary($q) + [
            'fields' => $q->fields->map(fn ($f) => [
                'id' => $f->id,
                'type' => $f->type,
                'label' => $f->label,
                'help_text' => $f->help_text,
                'required' => (bool) $f->required,
                'position' => $f->position,
                'settings' => $f->settings,
                'visibility_rule' => $f->visibility_rule,
                'mapping_target' => $f->mapping_target,
            ])->values()->all(),
        ];
    }
}
