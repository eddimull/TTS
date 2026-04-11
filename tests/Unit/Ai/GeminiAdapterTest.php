<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Adapters\GeminiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GeminiAdapterTest extends TestCase
{
    private function makeAdapter(array $responses, array &$history = []): GeminiAdapter
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $adapter = new GeminiAdapter('gemini-3.1-pro-preview', 'test-key');
        $ref = new \ReflectionProperty(GeminiAdapter::class, 'http');
        $ref->setAccessible(true);
        $ref->setValue($adapter, new Client(['handler' => $stack]));

        return $adapter;
    }

    private function geminiResponse(string $text): Response
    {
        return new Response(200, [], json_encode([
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
            ]],
        ]));
    }

    public function test_query_sends_text_only_request(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->geminiResponse('hello')], $history);

        $result = $adapter->query('Say hello', 128);

        $this->assertSame('hello', $result);
        $this->assertCount(1, $history);

        $body  = json_decode($history[0]['request']->getBody()->getContents(), true);
        $parts = $body['contents'][0]['parts'];

        $this->assertCount(1, $parts);
        $this->assertSame('Say hello', $parts[0]['text']);
        $this->assertSame(128, $body['generationConfig']['maxOutputTokens']);
    }

    public function test_query_with_image_sends_image_and_text(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->geminiResponse('MARKED_SETLIST')], $history);

        $image  = ['data' => base64_encode('fake-bytes'), 'media_type' => 'image/jpeg'];
        $result = $adapter->queryWithImage('Classify this', $image, 64);

        $this->assertSame('MARKED_SETLIST', $result);

        $body  = json_decode($history[0]['request']->getBody()->getContents(), true);
        $parts = $body['contents'][0]['parts'];

        $this->assertCount(2, $parts);
        $this->assertArrayHasKey('inline_data', $parts[0]);
        $this->assertSame('image/jpeg', $parts[0]['inline_data']['mime_type']);
        $this->assertSame('Classify this', $parts[1]['text']);
    }

    public function test_query_returns_empty_string_when_response_has_no_text(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([new Response(200, [], json_encode([]))], $history);

        $this->assertSame('', $adapter->query('test'));
    }

    public function test_query_uses_zero_temperature(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->geminiResponse('ok')], $history);

        $adapter->query('test');

        $body = json_decode($history[0]['request']->getBody()->getContents(), true);
        $this->assertSame(0, $body['generationConfig']['temperature']);
    }

    public function test_default_max_tokens_is_256(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->geminiResponse('ok')], $history);

        $adapter->query('test');

        $body = json_decode($history[0]['request']->getBody()->getContents(), true);
        $this->assertSame(256, $body['generationConfig']['maxOutputTokens']);
    }
}
