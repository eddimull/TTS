<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Tests\TestCase;

class HelpApiTest extends TestCase
{
    public function test_requires_auth(): void
    {
        $this->getJson('/api/mobile/help')->assertUnauthorized();
    }

    public function test_index_returns_mobile_articles(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/help')
            ->assertOk()
            ->assertJsonStructure([
                'articles' => [['slug', 'title', 'category', 'platforms', 'order', 'updated_at']],
                'category_labels',
            ]);

        foreach ($response->json('articles') as $article) {
            $this->assertContains('mobile', $article['platforms']);
        }
    }

    public function test_show_returns_markdown_body(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)->getJson('/api/mobile/help/created-a-band')
            ->assertOk()
            ->assertJsonPath('article.slug', 'created-a-band')
            ->assertJsonStructure(['article' => ['markdown', 'updated_at']]);
    }

    public function test_show_404s_on_unknown_or_web_only_slug(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)->getJson('/api/mobile/help/nope')->assertNotFound();
        $this->withToken($token)->getJson('/api/mobile/help/finances')->assertNotFound();
    }

    public function test_index_excludes_web_only_articles(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/help')->assertOk();

        $slugs = array_column($response->json('articles'), 'slug');
        $this->assertNotContains('finances', $slugs);
    }
}
