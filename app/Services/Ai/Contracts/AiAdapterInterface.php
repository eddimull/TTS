<?php

namespace App\Services\Ai\Contracts;

interface AiAdapterInterface
{
    /**
     * Send a text-only prompt and return the raw response.
     */
    public function query(string $prompt, int $maxTokens = 256): string;

    /**
     * Send an image + prompt and return the raw response.
     *
     * @param  array  $image  ['data' => base64string, 'media_type' => 'image/jpeg']
     */
    public function queryWithImage(string $prompt, array $image, int $maxTokens = 256): string;
}
