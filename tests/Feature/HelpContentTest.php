<?php

namespace Tests\Feature;

use App\Services\HelpContentService;
use Tests\TestCase;

class HelpContentTest extends TestCase
{
    private HelpContentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HelpContentService::class);
    }

    /** Every real article parses and carries valid frontmatter. */
    public function test_corpus_is_valid(): void
    {
        $articles = $this->service->index();
        $this->assertNotEmpty($articles);

        $slugs = [];
        foreach ($articles as $a) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $a['slug']);
            $this->assertNotSame('', trim($a['title']));
            $this->assertContains($a['category'], HelpContentService::CATEGORIES);
            $this->assertNotEmpty($a['platforms']);
            foreach ($a['platforms'] as $p) {
                $this->assertContains($p, HelpContentService::PLATFORMS);
            }
            $this->assertIsInt($a['order']);
            $slugs[] = $a['slug'];
        }
        $this->assertSame($slugs, array_unique($slugs), 'duplicate slugs');
    }

    public function test_index_sorts_by_category_rank_then_order(): void
    {
        $articles = $this->service->index();

        $ranks = array_map(
            fn ($a) => array_search($a['category'], HelpContentService::CATEGORIES, true),
            $articles
        );
        $sorted = $ranks;
        sort($sorted);
        $this->assertSame($sorted, $ranks);

        // Within each contiguous category group, order values must be non-decreasing.
        $groups = [];
        foreach ($articles as $a) {
            $groups[$a['category']][] = $a['order'];
        }
        foreach ($groups as $category => $orders) {
            $sortedOrders = $orders;
            sort($sortedOrders);
            $this->assertSame(
                $sortedOrders,
                $orders,
                "Articles in category '{$category}' are not sorted by order"
            );
        }
    }

    public function test_platform_filter(): void
    {
        foreach ($this->service->index('mobile') as $a) {
            $this->assertContains('mobile', $a['platforms']);
        }
    }

    public function test_article_returns_markdown_with_absolute_image_urls(): void
    {
        $article = $this->service->article('created-a-band');
        $this->assertNotNull($article);
        $this->assertArrayHasKey('markdown', $article);
        $this->assertStringNotContainsString('](images/', $article['markdown']);
    }

    /**
     * Pins the image URL root to config('app.url') rather than url(), which is
     * request-context-dependent and would poison the cached corpus with whichever
     * host first filled it (queue worker, artisan, or a request on another host).
     */
    public function test_article_image_urls_use_configured_app_url_not_request_context(): void
    {
        $fixturePath = resource_path('help/__fixture-image-rewrite.md');
        file_put_contents($fixturePath, <<<'MD'
            ---
            title: Fixture
            category: faq
            platforms: [web, mobile]
            order: 999
            ---

            ![Alt](images/foo.png)
            MD);

        try {
            $article = $this->service->article('__fixture-image-rewrite');
            $this->assertNotNull($article);

            $expectedRoot = rtrim(config('app.url'), '/') . '/images/help/';
            $this->assertStringContainsString(
                '](' . $expectedRoot . 'foo.png)',
                $article['markdown']
            );
        } finally {
            unlink($fixturePath);
        }
    }

    public function test_unknown_slug_returns_null(): void
    {
        $this->assertNull($this->service->article('no-such-article'));
    }

    public function test_html_is_sanitized(): void
    {
        $article = $this->service->article('created-a-band');
        $html = $this->service->html($article);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('<h2', $html); // body has a ## heading
    }
}
