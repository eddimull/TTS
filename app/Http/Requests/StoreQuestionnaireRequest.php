<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bandId = (int) $this->input('band_id');
        return $this->user()->canWrite('questionnaires', $bandId);
    }

    public function rules(): array
    {
        return [
            'band_id' => 'required|integer|exists:bands,id',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'preset_key' => 'nullable|string|max:60',
        ];
    }
}
