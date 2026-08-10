<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpEndpointsTest extends TestCase
{
    public function test_help_requires_auth(): void
    {
        $this->get('/help')->assertRedirect();
    }

    public function test_index_renders_with_articles(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get('/help')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Help/Index', false)
                ->has('articles')
                ->has('categoryLabels'));
    }

    public function test_show_renders_article_html(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get('/help/created-a-band')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Help/Article', false)
                ->where('article.slug', 'created-a-band')
                ->has('article.html')
                ->has('siblings'));
    }

    public function test_show_404_on_unknown_slug(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get('/help/nope')
            ->assertNotFound();
    }
}
