<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreChartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'file'           => 'required|file|max:20480', // 20 MB
            'display_name'   => 'required|string|max:255',
            'upload_type_id' => 'required|integer|in:1,2,3', // 1=Audio, 2=Video, 3=Sheet Music
            'notes'          => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max'             => 'The file must not exceed 20 MB.',
            'upload_type_id.in'    => 'The upload type must be 1 (Audio), 2 (Video), or 3 (Sheet Music).',
        ];
    }

    /**
     * Additional validation after the basic rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->hasFile('file') || !$this->file('file')->isValid()) {
                return;
            }

            $file   = $this->file('file');
            $typeId = (int) $this->input('upload_type_id');
            $mime   = $file->getMimeType();

            $allowed = match ($typeId) {
                1 => [ // Audio
                    'audio/mpeg', 'audio/wav', 'audio/mp3', 'audio/mp4',
                    'audio/x-m4a', 'audio/x-wav',
                ],
                2 => [ // Video
                    'video/mp4', 'video/avi', 'video/mov', 'video/wmv',
                    'video/quicktime',
                ],
                3 => [ // Sheet Music / Documents
                    'application/pdf',
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                default => [],
            };

            if (!in_array($mime, $allowed, true)) {
                $labels = [1 => 'Audio', 2 => 'Video', 3 => 'Sheet Music'];
                $validator->errors()->add(
                    'file',
                    "The file type ({$mime}) is not allowed for {$labels[$typeId]} uploads."
                );
            }
        });
    }
}
