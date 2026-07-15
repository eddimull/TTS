<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Questionnaires;
use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnairePresetRegistry;
use App\Services\QuestionnaireTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Authorization is handled at the route layer via the mobile.band middleware. */
class QuestionnairesController extends Controller
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private QuestionnairePresetRegistry $presetRegistry,
        private QuestionnaireTemplateService $templateService,
    ) {
    }

    public function index(Bands $band): JsonResponse
    {
        $questionnaires = $band->questionnaires()
            ->withCount('instances')
            ->orderBy('name')
            ->get()
            ->map(fn (Questionnaires $q) => $this->summary($q));

        return response()->json(['questionnaires' => $questionnaires]);
    }

    public function catalog(Bands $band): JsonResponse
    {
        return response()->json([
            'field_types' => $this->typeRegistry->catalog(),
            'mapping_targets' => $this->mappingRegistry->catalog(),
            'presets' => $this->presetRegistry->catalog(),
        ]);
    }

    public function show(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        $this->ensureBelongsToBand($band, $questionnaire);
        $questionnaire->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)]);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'preset_key' => 'nullable|string|max:60',
        ]);

        $questionnaire = DB::transaction(function () use ($band, $validated) {
            $q = new Questionnaires();
            $q->band_id = $band->id; // must be set before name: slug de-dupes per band
            $q->name = $validated['name'];
            $q->description = $validated['description'] ?? null;
            $q->save();

            if (! empty($validated['preset_key'])) {
                $this->templateService->applyPreset($q, $validated['preset_key']);
            }

            return $q;
        });

        $questionnaire->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)], 201);
    }

    public function update(UpdateQuestionnaireRequest $request, Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        $this->ensureBelongsToBand($band, $questionnaire);
        $validated = $request->validated();

        $this->templateService->validateFieldsPayload($validated['fields']);

        DB::transaction(function () use ($questionnaire, $validated) {
            $questionnaire->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);
            $this->templateService->upsertFields($questionnaire, $validated['fields']);
        });

        $questionnaire->refresh()->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)]);
    }

    public function archive(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        $this->ensureBelongsToBand($band, $questionnaire);
        $questionnaire->archived_at = now();
        $questionnaire->save();
        $questionnaire->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)]);
    }

    public function restore(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        $this->ensureBelongsToBand($band, $questionnaire);
        $questionnaire->archived_at = null;
        $questionnaire->save();
        $questionnaire->load('fields')->loadCount('instances');

        return response()->json(['questionnaire' => $this->detail($questionnaire)]);
    }

    public function destroy(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        $this->ensureBelongsToBand($band, $questionnaire);

        if ($questionnaire->instances()->exists()) {
            return response()->json([
                'message' => 'This questionnaire has been sent and cannot be deleted. Archive it instead.',
            ], 409);
        }

        $questionnaire->delete();

        return response()->json(['message' => 'Questionnaire deleted']);
    }

    private function ensureBelongsToBand(Bands $band, Questionnaires $questionnaire): void
    {
        abort_if($questionnaire->band_id !== $band->id, 404);
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
