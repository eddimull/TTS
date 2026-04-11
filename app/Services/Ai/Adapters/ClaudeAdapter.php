<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiAdapterInterface;
use GuzzleHttp\Client;

class ClaudeAdapter implements AiAdapterInterface
{
    private Client $http;
    private string $apiKey;
    private string $model;

    public function __construct(string $model = 'claude-opus-4-6', ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.anthropic.key', '');
        $this->model  = $model;
        $this->http   = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout'  => 60,
        ]);
    }

    public function query(string $prompt, int $maxTokens = 256): string
    {
        return $this->send([['role' => 'user', 'content' => $prompt]], $maxTokens);
    }

    public function queryWithImage(string $prompt, array $image, int $maxTokens = 256): string
    {
        return $this->send([
            [
                'role' => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $image['media_type'],
                            'data'       => $image['data'],
                        ],
                    ],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ],
        ], $maxTokens);
    }

    private function send(array $messages, int $maxTokens): string
    {
        $response = $this->http->post('/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'messages'   => $messages,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['content'][0]['text'] ?? '';
    }
}
