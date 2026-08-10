<?php

namespace App\Http\Controllers;

use App\Services\HelpContentService;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function index(HelpContentService $help): Response
    {
        return Inertia::render('Help/Index', [
            'articles' => $help->index(),
            'categoryLabels' => HelpContentService::CATEGORY_LABELS,
        ]);
    }

    public function show(HelpContentService $help, string $slug): Response
    {
        $article = $help->article($slug);
        abort_unless((bool) $article, 404);

        $siblings = array_values(array_filter(
            $help->index(),
            fn (array $a) => $a['category'] === $article['category']
        ));

        return Inertia::render('Help/Article', [
            'article' => collect($article)->except('markdown')
                ->put('html', $help->html($article))
                ->all(),
            'siblings' => $siblings,
        ]);
    }
}
