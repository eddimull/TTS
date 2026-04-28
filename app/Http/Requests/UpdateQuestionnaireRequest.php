<?php

namespace App\Http\Requests;

use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $questionnaire = $this->route('questionnaire');
        return $this->user()->canWrite('questionnaires', $questionnaire->band_id);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'fields' => 'present|array',
            'fields.*.id' => 'nullable|integer',
            'fields.*.client_id' => 'required|string',
            'fields.*.type' => ['required', Rule::in($this->typeRegistry->knownTypes())],
            'fields.*.label' => 'required|string|max:255',
            'fields.*.help_text' => 'nullable|string',
            'fields.*.required' => 'boolean',
            'fields.*.position' => 'required|integer|min:0',
            'fields.*.settings' => 'nullable|array',
            'fields.*.visibility_rule' => 'nullable|array',
            'fields.*.visibility_rule.depends_on' => 'required_with:fields.*.visibility_rule|string',
            'fields.*.visibility_rule.operator' => [
                'required_with:fields.*.visibility_rule',
                Rule::in(['equals', 'not_equals', 'contains', 'empty', 'not_empty']),
            ],
            'fields.*.visibility_rule.value' => 'nullable',
            'fields.*.mapping_target' => ['nullable', Rule::in($this->mappingRegistry->keys())],
        ];
    }
}
