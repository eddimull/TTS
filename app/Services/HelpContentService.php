<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class HelpContentService
{
    public const CATEGORIES = ['getting-started', 'features', 'how-to', 'faq'];

    public const CATEGORY_LABELS = [
        'getting-started' => 'Getting started',
        'features' => 'Features',
        'how-to' => 'How-tos',
        'faq' => 'FAQ',
    ];

    public const PLATFORMS = ['web', 'mobile'];

    /** Ordered article metadata; optionally filtered to one platform. */
    public function index(?string $platform = null): array
    {
        $articles = array_values(array_filter(
            $this->corpus(),
            fn (array $a) => $platform === null || in_array($platform, $a['platforms'], true)
        ));

        return array_map(
            fn (array $a) => collect($a)->except('markdown')->all(),
            $articles
        );
    }

    /** Full article (meta + raw markdown) or null. */
    public function article(string $slug): ?array
    {
        foreach ($this->corpus() as $a) {
            if ($a['slug'] === $slug) {
                return $a;
            }
        }

        return null;
    }

    /** Sanitized HTML for web rendering. */
    public function html(array $article): string
    {
        return Str::markdown($article['markdown'], [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Parsed corpus, cached until any file changes (mtime fingerprint). */
    protected function corpus(): array
    {
        $files = glob(resource_path('help/*.md')) ?: [];
        $fingerprint = md5(json_encode(array_map(
            fn (string $f) => [basename($f), filemtime($f)],
            $files
        )));

        return Cache::remember(
            "help.corpus.$fingerprint",
            now()->addDay(),
            function () use ($files) {
                $articles = array_map(fn (string $f) => $this->parse($f), $files);
                usort($articles, fn (array $a, array $b) => $this->sortKey($a) <=> $this->sortKey($b));

                return $articles;
            }
        );
    }

    /** Comparable sort key: [category rank, order, title] — all scalars, safe for <=>. */
    protected function sortKey(array $article): array
    {
        return [
            array_search($article['category'], self::CATEGORIES, true),
            $article['order'],
            $article['title'],
        ];
    }

    protected function parse(string $path): array
    {
        $slug = basename($path, '.md');
        $raw = file_get_contents($path);

        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
            throw new RuntimeException("Help article $slug: missing frontmatter block");
        }

        $meta = Yaml::parse($m[1]);
        foreach (['title', 'category', 'platforms', 'order'] as $key) {
            if (! isset($meta[$key])) {
                throw new RuntimeException("Help article $slug: missing '$key'");
            }
        }
        if (! in_array($meta['category'], self::CATEGORIES, true)) {
            throw new RuntimeException("Help article $slug: bad category '{$meta['category']}'");
        }
        foreach ((array) $meta['platforms'] as $platform) {
            if (! in_array($platform, self::PLATFORMS, true)) {
                throw new RuntimeException("Help article $slug: bad platform '$platform'");
            }
        }

        // Relative image refs work on web and in the mobile app alike.
        // Use config('app.url') rather than url() — the corpus is cached across
        // requests/contexts (queue workers, artisan, multiple hosts), and url()
        // derives its root from whichever request context first fills the cache.
        $imageRoot = rtrim(config('app.url'), '/') . '/images/help/';
        $markdown = str_replace('](images/', '](' . $imageRoot, trim($m[2]));

        return [
            'slug' => $slug,
            'title' => (string) $meta['title'],
            'category' => $meta['category'],
            'platforms' => array_values((array) $meta['platforms']),
            'order' => (int) $meta['order'],
            'updated_at' => date(DATE_ATOM, filemtime($path)),
            'markdown' => $markdown,
        ];
    }
}
