<?php

namespace App\Http\Requests;

use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnaireRoutingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of the batch "Send to event" (mobile and web). Band / event access
 * is checked by the controllers; per-item compatibility (target vs field
 * type) is checked inside the routing service's transaction.
 */
class RouteQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $targets = app(QuestionnaireMappingRegistry::class)->keys();

        return [
            'items' => ['present', 'array'],
            'items.*.response_id' => ['required', 'integer', 'distinct'],
            'items.*.target' => ['required', 'string', Rule::in($targets)],
            'items.*.remember' => ['sometimes', 'boolean'],
            'resolutions' => ['sometimes', 'nullable', 'array'],
            'resolutions.songs' => ['sometimes', 'nullable', 'array'],
            'resolutions.songs.*' => ['required', Rule::in(QuestionnaireRoutingService::SONG_RESOLUTIONS)],
            'resolutions.venue' => ['sometimes', 'nullable', Rule::in(QuestionnaireRoutingService::VENUE_RESOLUTIONS)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach (array_keys((array) $this->input('resolutions.songs', [])) as $songId) {
                if (!ctype_digit((string) $songId)) {
                    $v->errors()->add('resolutions.songs', 'Song ids must be integers.');
                    return;
                }
            }
        });
    }

    /** @return array<int, array{response_id: int, target: string, remember?: bool}> */
    public function items(): array
    {
        return array_values($this->validated('items', []));
    }

    /** Whether any item asks to remember its target on the template. */
    public function remembersAny(): bool
    {
        return collect($this->items())->contains(fn ($item) => !empty($item['remember']));
    }

    /** @return array{songs?: array<int|string, string>, venue?: string|null} */
    public function resolutions(): array
    {
        return (array) ($this->validated('resolutions') ?? []);
    }
}
