<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RenameFolderRequest extends FormRequest
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
            'old_path' => 'required|string|max:255',
            'new_path' => 'required|string|max:255|different:old_path',
        ];
    }

    public function messages(): array
    {
        return [
            'new_path.different' => 'The new folder name must be different from the current name.',
        ];
    }
}
