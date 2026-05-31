<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\SetlistPromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetlistPromptTemplateController extends Controller
{
    public function index(Bands $band): JsonResponse
    {
        return response()->json([]);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        return response()->json([]);
    }

    public function update(Request $request, Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        return response()->json([]);
    }

    public function destroy(Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        return response()->json(null, 204);
    }
}
