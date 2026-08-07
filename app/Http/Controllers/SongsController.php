<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
use App\Models\Bands;
use App\Models\Song;
use App\Services\GetSongBpmService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;

class SongsController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $bands = $user->allBands();
        $currentBandId = $request->get('band_id', $bands->first()?->id);

        if (!$currentBandId) {
            return inertia('Songs/Index', [
                'band' => null,
                'songs' => [],
                'rosterMembers' => [],
                'genres' => Song::GENRES,
                'availableBands' => [],
                'canWrite' => false,
            ]);
        }

        $band = Bands::findOrFail($currentBandId);

        if (!$band->everyone()->contains('user_id', $user->id) && !$user->canRead('songs', $band->id)) {
            abort(403, 'Unauthorized');
        }

        $songs = $band->songs()
            ->with(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')])
            ->get();

        $rosterMembers = $band->rosters()
            ->with(['members' => fn($q) => $q->where('is_active', true)->with('user')])
            ->get()
            ->pluck('members')
            ->flatten()
            ->unique('id')
            ->values()
            ->map(fn($m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
            ]);

        return inertia('Songs/Index', [
            'band' => $band,
            'songs' => $songs,
            'rosterMembers' => $rosterMembers,
            'genres' => Song::GENRES,
            'availableBands' => $bands->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'canWrite' => $user->canWrite('songs', $band->id),
        ]);
    }

    public function store(StoreSongRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $band = Bands::findOrFail($validated['band_id']);

        if (!Auth::user()->canWrite('songs', $band->id)) {
            abort(403, 'Permission denied');
        }

        $song = $band->songs()->create($validated);
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json($song, 201);
    }

    public function update(UpdateSongRequest $request, Song $song): JsonResponse
    {
        if (!Auth::user()->canWrite('songs', $song->band_id)) {
            abort(403, 'Permission denied');
        }

        $song->update($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json($song);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
        ]);

        $result = (new GetSongBpmService())->lookup(
            $request->input('title'),
            $request->input('artist')
        );

        return response()->json($result);
    }

    public function destroy(Song $song): JsonResponse
    {
        if (!Auth::user()->ownsBand($song->band_id)) {
            abort(403, 'Only band owners can delete songs');
        }

        $song->delete();

        return response()->json(['message' => 'Song deleted successfully']);
    }

    /**
     * Download the band's active song list as a client-facing PDF.
     */
    public function download(Request $request): Response
    {
        $user = Auth::user();
        $bands = $user->allBands();
        $currentBandId = $request->get('band_id', $bands->first()?->id);

        if (!$currentBandId) {
            abort(404, 'No band available.');
        }

        $band = Bands::findOrFail($currentBandId);

        if (!$band->everyone()->contains('user_id', $user->id) && !$user->canRead('songs', $band->id)) {
            abort(403, 'Unauthorized');
        }

        $songs = $band->songs()
            ->where('active', true)
            ->orderBy('title')
            ->get(['title', 'artist']);

        $html = view('pdf.songList', [
            'band' => $band,
            'songs' => $songs,
            'logoDataUri' => $this->bandLogoDataUri($band),
            'generatedAt' => now(),
        ])->render();

        $pdf = app(PdfGeneratorService::class)->generateFromHtml($html, 'Letter');

        $filename = Str::slug($band->name . ' song list') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Build a base64 data URI for the band logo so it embeds in the PDF.
     * Returns null when no logo is set or it cannot be read.
     */
    private function bandLogoDataUri(Bands $band): ?string
    {
        if (empty($band->logo)) {
            return null;
        }

        try {
            $logoPath = str_replace('/images/', '', $band->logo);

            if (!Storage::disk('s3')->exists($logoPath)) {
                return null;
            }

            $contents = Storage::disk('s3')->get($logoPath);
            $mimeType = Storage::disk('s3')->mimeType($logoPath) ?: 'image/png';

            return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            Log::warning('Could not load band logo for song list PDF', [
                'band_id' => $band->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

}
