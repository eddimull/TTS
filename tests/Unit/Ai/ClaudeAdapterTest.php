<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Adapters\ClaudeAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class ClaudeAdapterTest extends TestCase
{
    private function makeAdapter(array $responses, array &$history = []): ClaudeAdapter
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $adapter = new ClaudeAdapter('claude-opus-4-6', 'test-key');
        $ref = new \ReflectionProperty(ClaudeAdapter::class, 'http');
        $ref->setAccessible(true);
        $ref->setValue($adapter, new Client(['handler' => $stack]));

        return $adapter;
    }

    private function claudeResponse(string $text): Response
    {
        return new Response(200, [], json_encode([
            'content' => [['text' => $text]],
        ]));
    }

    public function test_query_sends_text_only_message(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->claudeResponse('hello')], $history);

        $result = $adapter->query('Say hello', 128);

        $this->assertSame('hello', $result);

        $body     = json_decode($history[0]['request']->getBody()->getContents(), true);
        $messages = $body['messages'];

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Say hello', $messages[0]['content']);
        $this->assertSame(128, $body['max_tokens']);
    }

    public function test_query_with_image_sends_image_and_text_content_blocks(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->claudeResponse('OTHER')], $history);

        $image  = ['data' => base64_encode('fake-bytes'), 'media_type' => 'image/png'];
        $result = $adapter->queryWithImage('Classify this', $image, 64);

        $this->assertSame('OTHER', $result);

        $body    = json_decode($history[0]['request']->getBody()->getContents(), true);
        $content = $body['messages'][0]['content'];

        $this->assertCount(2, $content);
        $this->assertSame('image', $content[0]['type']);
        $this->assertSame('base64', $content[0]['source']['type']);
        $this->assertSame('image/png', $content[0]['source']['media_type']);
        $this->assertSame('text', $content[1]['type']);
        $this->assertSame('Classify this', $content[1]['text']);
    }

    public function test_query_returns_empty_string_when_response_has_no_content(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([new Response(200, [], json_encode([]))], $history);

        $this->assertSame('', $adapter->query('test'));
    }

    public function test_request_includes_required_anthropic_headers(): void
    {
        $history = [];
        $adapter = $this->makeAdapter([$this->claudeResponse('ok')], $history);

        $adapter->query('test');

        $headers = $history[0]['request']->getHeaders();
        $this->assertArrayHasKey('x-api-key', $headers);
        $this->assertArrayHasKey('anthropic-version', $headers);
    }
}
