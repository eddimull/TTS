<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\SetlistPromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetlistPromptTemplateController extends Controller
{
    public function index(Bands $band): JsonResponse
    {
        if (!Auth::user()->canRead('events', $band->id)) {
            abort(403);
        }

        $templates = $band->setlistPromptTemplates()
            ->orderBy('name')
            ->get(['id', 'name', 'prompt']);

        return response()->json($templates);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'name'   => 'required|string|max:100',
            'prompt' => 'required|string|max:2000',
        ]);

        $template = $band->setlistPromptTemplates()->create($validated);

        return response()->json($template->only(['id', 'name', 'prompt']), 201);
    }

    public function update(Request $request, Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        if ($template->band_id !== $band->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:100',
            'prompt' => 'sometimes|string|max:2000',
        ]);

        $template->update($validated);

        return response()->json($template->only(['id', 'name', 'prompt']));
    }

    public function destroy(Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        if ($template->band_id !== $band->id) {
            abort(404);
        }

        $template->delete();

        return response()->json(null, 204);
    }
}
