<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateFolderRequest extends FormRequest
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
            'folder_path' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'folder_path.required' => 'Folder path is required.',
            'folder_path.max' => 'Folder path cannot exceed 255 characters.',
        ];
    }
}
