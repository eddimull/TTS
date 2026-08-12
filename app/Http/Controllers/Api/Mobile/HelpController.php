<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\HelpContentService;
use Illuminate\Http\JsonResponse;

class HelpController extends Controller
{
    public function index(HelpContentService $help): JsonResponse
    {
        return response()->json([
            'articles' => $help->index('mobile'),
            'category_labels' => HelpContentService::CATEGORY_LABELS,
        ]);
    }

    public function show(HelpContentService $help, string $slug): JsonResponse
    {
        $article = $help->article($slug);
        abort_unless($article && in_array('mobile', $article['platforms'], true), 404);

        return response()->json(['article' => $article]);
    }
}
