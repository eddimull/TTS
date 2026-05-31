<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetlistEditorController extends Controller
{
    public function show(Events $event): JsonResponse
    {
        return response()->json([]);
    }

    public function update(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }

    public function generate(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }

    public function refine(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }
}
