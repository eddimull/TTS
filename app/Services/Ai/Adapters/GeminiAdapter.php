<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiAdapterInterface;
use GuzzleHttp\Client;

class GeminiAdapter implements AiAdapterInterface
{
    private Client $http;
    private string $apiKey;
    private string $model;

    public function __construct(string $model = 'gemini-3.1-pro-preview', ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.gemini.key', '');
        $this->model  = $model;
        $this->http   = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com',
            'timeout'  => 60,
        ]);
    }

    public function query(string $prompt, int $maxTokens = 256): string
    {
        return $this->send([['text' => $prompt]], $maxTokens);
    }

    public function queryWithImage(string $prompt, array $image, int $maxTokens = 256): string
    {
        return $this->send([
            [
                'inline_data' => [
                    'mime_type' => $image['media_type'],
                    'data'      => $image['data'],
                ],
            ],
            ['text' => $prompt],
        ], $maxTokens);
    }

    private function send(array $parts, int $maxTokens): string
    {
        $response = $this->http->post(
            "/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'json' => [
                    'contents'         => [['parts' => $parts]],
                    'generationConfig' => [
                        'temperature'     => 0,
                        'maxOutputTokens' => $maxTokens,
                    ],
                ],
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
