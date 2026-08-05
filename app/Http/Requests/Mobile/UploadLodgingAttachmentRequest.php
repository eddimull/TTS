<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UploadLodgingAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band:write:lodging)
    }

    public function rules(): array
    {
        return ['file' => 'required|file|max:10240']; // 10MB, matches event attachments
    }
}
