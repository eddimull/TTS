<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UploadInitiateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'filename'     => 'required|string|max:255',
            'filesize'     => 'required|integer|min:1|max:5368709120',
            'mime_type'    => 'required|string',
            'total_chunks' => 'required|integer|min:1',
            'folder_path'  => 'nullable|string|max:255',
            'event_id'     => 'nullable|integer|exists:events,id',
        ];
    }
}
