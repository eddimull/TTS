<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'title' => $this->title,
            'description' => $this->description,
            'media_type' => $this->media_type,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'formatted_size' => $this->getFormattedSizeAttribute(),
            'folder_path' => $this->folder_path,
            'thumbnail_url' => $this->getThumbnailUrlAttribute(),
            'url' => $this->getUrlAttribute(),
            'is_system_file' => $this->is_system_file ?? false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include tag info with all fields the frontend needs
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color ?? null,
                ]);
            }),

            // Only include uploader name, not full object
            'uploader' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ];
            }),
        ];
    }
}
