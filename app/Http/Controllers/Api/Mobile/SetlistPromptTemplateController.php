<?php

namespace App\Http\Controllers\Api\Mobile;

// Mirrors App\Http\Controllers\SetlistPromptTemplateController (web) intentionally — kept separate per mobile API plan.

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\SetlistPromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Authorization is handled at the route layer via the mobile.band middleware. */
class SetlistPromptTemplateController extends Controller
{
    public function index(Bands $band): JsonResponse
    {
        $templates = $band->setlistPromptTemplates()
            ->orderBy('name')
            ->get(['id', 'name', 'prompt']);

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:100',
            'prompt' => 'required|string|max:2000',
        ]);

        $template = $band->setlistPromptTemplates()->create($validated);

        return response()->json(['data' => $template->only(['id', 'name', 'prompt'])], 201);
    }

    public function update(Request $request, Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        abort_if($template->band_id !== $band->id, 404);

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:100',
            'prompt' => 'sometimes|string|max:2000',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->only(['id', 'name', 'prompt'])]);
    }

    public function destroy(Bands $band, SetlistPromptTemplate $template): JsonResponse
    {
        abort_if($template->band_id !== $band->id, 404);

        $template->delete();

        return response()->json(null, 204);
    }
}
