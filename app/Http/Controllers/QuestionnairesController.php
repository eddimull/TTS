<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionnaireRequest;
use App\Http\Requests\UpdateQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Questionnaires;
use App\Services\FieldSettingsValidator;
use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnairePresetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuestionnairesController extends Controller
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private FieldSettingsValidator $settingsValidator,
        private QuestionnairePresetRegistry $presetRegistry,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $availableBands = $user->allBands();

        if ($availableBands->isEmpty()) {
            abort(403);
        }

        $bandId = $request->query('band_id');
        $band = $bandId
            ? $availableBands->firstWhere('id', (int) $bandId)
            : $availableBands->first();

        if (!$band) {
            abort(403);
        }

        if (!$user->canRead('questionnaires', $band->id)) {
            abort(403);
        }

        $questionnaires = $band->questionnaires()
            ->orderBy('archived_at')
            ->orderBy('name')
            ->withCount('instances')
            ->get();

        return Inertia::render('Questionnaires/Index', [
            'band' => $band->only(['id', 'name', 'site_name']),
            'questionnaires' => $questionnaires,
            'availableBands' => $availableBands->map->only(['id', 'name']),
            'presets' => $this->presetRegistry->catalog(),
        ]);
    }

    public function store(StoreQuestionnaireRequest $request): RedirectResponse
    {
        $band = Bands::findOrFail($request->input('band_id'));
        $presetKey = $request->input('preset_key');

        $questionnaire = DB::transaction(function () use ($request, $band, $presetKey) {
            $row = new Questionnaires([
                'description' => $request->input('description'),
            ]);
            $row->band_id = $band->id;
            $row->name = $request->input('name'); // triggers slug generation
            $row->save();

            if ($presetKey && $this->presetRegistry->exists($presetKey)) {
                $this->cloneFieldsFromPreset($row, $presetKey);
            }

            return $row;
        });

        return redirect()->route('questionnaires.edit', [$band, $questionnaire]);
    }

    private function cloneFieldsFromPreset(Questionnaires $questionnaire, string $key): void
    {
        $preset = $this->presetRegistry->get($key);
        $position = 10;
        foreach ($preset['fields'] as $field) {
            $questionnaire->fields()->create([
                'type' => $field['type'],
                'label' => $field['label'],
                'help_text' => $field['help_text'] ?? null,
                'required' => $field['required'] ?? false,
                'position' => $position,
                'settings' => $field['settings'] ?? null,
                'mapping_target' => $field['mapping_target'] ?? null,
                'visibility_rule' => null,
            ]);
            $position += 10;
        }
    }

    public function edit(Bands $band, Questionnaires $questionnaire): Response
    {
        $this->authorize('view', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        return Inertia::render('Questionnaires/Edit', [
            'band' => $band->only(['id', 'name', 'site_name']),
            'questionnaire' => $questionnaire->only(['id', 'name', 'slug', 'description', 'archived_at']),
            'fields' => $questionnaire->fields,
            'fieldTypeCatalog' => $this->typeRegistry->catalog(),
            'mappingTargetCatalog' => $this->mappingRegistry->catalog(),
        ]);
    }

    public function show(Bands $band, Questionnaires $questionnaire): Response
    {
        $this->authorize('view', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        $rawInstances = $questionnaire->instances()
            ->with([
                'recipientContact:id,name',
                'booking:id,name,date,band_id',
                'fields' => fn ($q) => $q->orderBy('position'),
                'responses',
            ])
            ->orderByDesc('sent_at')
            ->get();

        $songLookup = $this->buildSongLookupForInstances($rawInstances, $band->id);

        $instances = $rawInstances->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'status' => $i->status,
            'sent_at' => $i->sent_at?->format('M j, Y'),
            'submitted_at' => $i->submitted_at?->format('M j, Y'),
            'recipient_name' => $i->recipientContact->name ?? 'Unknown',
            'booking' => [
                'id' => $i->booking->id,
                'name' => $i->booking->name,
                'date' => $i->booking->date?->format('M j, Y'),
            ],
            'fields' => $i->fields->map(fn ($f) => [
                'id' => $f->id,
                'type' => $f->type,
                'label' => $f->label,
                'required' => (bool) $f->required,
                'settings' => $f->settings,
            ])->values(),
            'responses' => $i->responses->mapWithKeys(fn ($r) => [
                $r->instance_field_id => ['value' => $r->value],
            ]),
            'song_lookup' => $songLookup,
        ]);

        $bookingIdsAlreadySent = $questionnaire->instances()
            ->whereNull('deleted_at')
            ->pluck('booking_id')
            ->all();

        $bookings = $band->bookings()
            ->with(['contacts:id,name'])
            ->whereDate('date', '>=', today())
            ->get(['id', 'name', 'date', 'band_id'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'date' => $b->date?->format('M j, Y'),
                'already_sent' => in_array($b->id, $bookingIdsAlreadySent, true),
                'contacts' => $b->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_primary' => (bool) ($c->pivot->is_primary ?? false),
                ])->values(),
            ]);

        return Inertia::render('Questionnaires/Show', [
            'band' => $band->only(['id', 'name', 'site_name']),
            'questionnaire' => $questionnaire->only(['id', 'name', 'slug', 'description', 'archived_at', 'created_at', 'updated_at']),
            'fieldCount' => $questionnaire->fields()->count(),
            'instances' => $instances,
            'bookings' => $bookings,
        ]);
    }

    public function update(UpdateQuestionnaireRequest $request, Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        abort_if($questionnaire->band_id !== $band->id, 404);
        $this->validateBulkSavePayload($request, $questionnaire);

        DB::transaction(function () use ($request, $questionnaire) {
            $questionnaire->name = $request->input('name');
            $questionnaire->description = $request->input('description');
            $questionnaire->save();

            $this->upsertFields($request->input('fields', []), $questionnaire);
        });

        return redirect()->route('questionnaires.edit', [$band, $questionnaire])
            ->with('success', 'Questionnaire saved.');
    }

    public function preview(Bands $band, Questionnaires $questionnaire): Response
    {
        $this->authorize('view', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        return Inertia::render('Questionnaires/Preview', [
            'band' => $band->only(['id', 'name']),
            'questionnaire' => $questionnaire,
            'fields' => $questionnaire->fields,
        ]);
    }

    public function archive(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('update', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        $questionnaire->update(['archived_at' => now()]);

        return back()->with('success', 'Archived.');
    }

    public function restore(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('update', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        $questionnaire->update(['archived_at' => null]);

        return back()->with('success', 'Restored.');
    }

    public function destroy(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('delete', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        if ($questionnaire->instances()->exists()) {
            return back()->with('error', 'Cannot delete a template that has been sent. Archive it instead.')
                ->setStatusCode(409);
        }

        $questionnaire->delete();

        return redirect()->route('questionnaires.index', ['band_id' => $band->id])->with('success', 'Deleted.');
    }

    /**
     * Combined custom validation: per-type settings, mapping-target compatibility,
     * forward-visibility check.
     */
    private function validateBulkSavePayload(Request $request, Questionnaires $questionnaire): void
    {
        $errors = [];
        $fields = $request->input('fields', []);

        // Position-by-client_id map for forward-reference detection
        $positionByClientId = [];
        foreach ($fields as $f) {
            $positionByClientId[$f['client_id']] = $f['position'] ?? PHP_INT_MAX;
        }

        foreach ($fields as $i => $f) {
            $type = $f['type'] ?? null;
            $settings = $f['settings'] ?? null;
            $rule = $f['visibility_rule'] ?? null;
            $mapping = $f['mapping_target'] ?? null;

            // Per-type settings shape
            $settingsErrors = $this->settingsValidator->validate($type, $settings);
            foreach ($settingsErrors as $err) {
                $errors["fields.{$i}.settings"][] = $err;
            }

            // Mapping-target compatibility
            if (!empty($mapping)) {
                $compatible = $this->mappingRegistry->compatibleFieldTypes($mapping);
                if (!in_array($type, $compatible, true)) {
                    $errors["fields.{$i}.mapping_target"][] =
                        "Field type '{$type}' is not compatible with mapping target '{$mapping}'.";
                }
            }

            // Forward-reference check
            if (!empty($rule['depends_on'])) {
                $thisPos = $f['position'] ?? PHP_INT_MAX;
                $depPos = $positionByClientId[$rule['depends_on']] ?? null;
                if ($depPos === null) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule references unknown field '{$rule['depends_on']}'.";
                } elseif ($depPos >= $thisPos) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule must reference a field that comes earlier in the questionnaire.";
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Diff existing fields vs payload by id, upsert present, delete missing.
     * Two-pass: first upsert, then rewrite visibility_rule depends_on to use
     * permanent ids resolved from client_ids.
     */
    private function upsertFields(array $payloadFields, Questionnaires $questionnaire): void
    {
        $payloadIds = collect($payloadFields)->pluck('id')->filter()->all();
        $questionnaire->fields()->whereNotIn('id', $payloadIds)->delete();

        $clientIdToPersistedId = [];

        foreach ($payloadFields as $f) {
            $attributes = [
                'questionnaire_id' => $questionnaire->id,
                'type' => $f['type'],
                'label' => $f['label'],
                'help_text' => $f['help_text'] ?? null,
                'required' => $f['required'] ?? false,
                'position' => $f['position'],
                'settings' => $f['settings'] ?? null,
                'mapping_target' => $f['mapping_target'] ?? null,
                // visibility_rule rewritten in second pass
                'visibility_rule' => null,
            ];

            if (!empty($f['id'])) {
                $field = $questionnaire->fields()->find($f['id']);
                if ($field) {
                    $field->update($attributes);
                    $clientIdToPersistedId[$f['client_id']] = $field->id;
                    continue;
                }
            }

            $created = $questionnaire->fields()->create($attributes);
            $clientIdToPersistedId[$f['client_id']] = $created->id;
        }

        // Second pass: rewrite visibility_rule.depends_on
        foreach ($payloadFields as $f) {
            if (empty($f['visibility_rule']['depends_on'])) {
                continue;
            }
            $persistedId = $clientIdToPersistedId[$f['client_id']];
            $depClientId = $f['visibility_rule']['depends_on'];
            $depPersistedId = $clientIdToPersistedId[$depClientId] ?? null;
            if ($depPersistedId === null) {
                continue;
            }

            $rewritten = [
                'depends_on' => $depPersistedId,
                'operator' => $f['visibility_rule']['operator'],
                'value' => $f['visibility_rule']['value'] ?? null,
            ];
            $questionnaire->fields()->where('id', $persistedId)->update([
                'visibility_rule' => json_encode($rewritten),
            ]);
        }
    }

    /**
     * Build a song-id => {title, artist} lookup for any song_picker
     * responses across the given instances. Removed songs appear with a
     * "(removed song #N)" placeholder title.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuestionnaireInstances>  $instances
     */
    private function buildSongLookupForInstances($instances, int $bandId): array
    {
        $songIds = collect();
        foreach ($instances as $instance) {
            $songPickerFieldIds = $instance->fields
                ->where('type', 'song_picker')
                ->pluck('id');

            foreach ($instance->responses as $response) {
                if (!$songPickerFieldIds->contains($response->instance_field_id)) {
                    continue;
                }
                $decoded = json_decode((string) $response->value, true);
                if (is_array($decoded)) {
                    $songIds = $songIds->merge($decoded);
                }
            }
        }
        $songIds = $songIds->unique()->filter(fn ($id) => is_numeric($id))->values();

        if ($songIds->isEmpty()) {
            return [];
        }

        $songs = \App\Models\Song::where('band_id', $bandId)
            ->whereIn('id', $songIds)
            ->get(['id', 'title', 'artist']);

        $lookup = [];
        foreach ($songs as $song) {
            $lookup[$song->id] = ['title' => $song->title, 'artist' => $song->artist];
        }
        foreach ($songIds as $id) {
            if (!isset($lookup[$id])) {
                $lookup[$id] = ['title' => "(removed song #{$id})", 'artist' => null];
            }
        }
        return $lookup;
    }
}
