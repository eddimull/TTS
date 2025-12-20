<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');
        return Auth::user()->canWrite('media', $media->band_id);
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'folder_path' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:media_tags,id'
        ];
    }
}
