<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BulkMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bandId = $this->input('band_id');
        return Auth::user()->canWrite('media', $bandId);
    }

    public function rules(): array
    {
        return [
            'band_id' => 'required|exists:bands,id',
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'exists:media_files,id',
            'folder_path' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'media_ids.required' => 'Please select at least one file to move.',
            'media_ids.min' => 'Please select at least one file to move.',
        ];
    }
}
