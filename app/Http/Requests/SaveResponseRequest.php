<?php

namespace App\Http\Requests;

use App\Models\QuestionnaireInstanceFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contact') !== null;
    }

    public function rules(): array
    {
        return [
            'instance_field_id' => 'required|integer',
            'value' => 'nullable',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $instance = $this->route('instance');
            $fieldId = $this->input('instance_field_id');
            if (!$fieldId || !$instance) {
                return;
            }

            $exists = QuestionnaireInstanceFields::where('id', $fieldId)
                ->where('instance_id', $instance->id)
                ->exists();
            if (!$exists) {
                $v->errors()->add('instance_field_id', 'Field does not belong to this instance.');
            }
        });
    }
}
