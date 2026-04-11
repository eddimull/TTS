<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class GetSongBpmService
{
    private Client $http;
    private string $apiKey;
    private const BASE_URL = 'https://api.getsong.co';

    public function __construct()
    {
        $this->apiKey = config('services.getsongbpm.key');
        $this->http = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 10,
        ]);
    }

    /**
     * Look up a song by title and optional artist, returning key, BPM, and genre.
     * Falls back to Claude if GetSongBPM has no result.
     *
     * @return array{bpm: int|null, song_key: string|null, genre: string|null, artist: string|null}
     */
    public function lookup(string $title, ?string $artist = null): array
    {
        $songId = $this->searchSongId($title, $artist);

        if ($songId) {
            $result = $this->getSongDetails($songId);
            Log::info('GetSongBPM result', ['songId' => $songId, 'result' => $result]);
            // If we got useful data, return it
            if ($result['bpm'] || $result['song_key']) {
                return $result;
            }
        }

        Log::info('GetSongBPM: no result, falling back to Claude', ['title' => $title, 'artist' => $artist]);
        // Fall back to Claude
        return (new SetlistAiService())->lookupSongDetails($title, $artist);
    }

    private function searchSongId(string $title, ?string $artist): ?string
    {
        try {
            $type = $artist ? 'both' : 'song';
            $lookup = $artist
                ? 'song:' . $title . ' artist:' . $artist
                : $title;

            $response = $this->http->get('/search/', [
                'query' => [
                    'api_key' => $this->apiKey,
                    'type'    => $type,
                    'lookup'  => $lookup,
                    'limit'   => 1,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['search'][0]['id'] ?? null;

        } catch (RequestException $e) {
            Log::warning('GetSongBPM search failed', ['error' => $e->getMessage(), 'title' => $title]);
            return null;
        }
    }

    private function getSongDetails(string $songId): array
    {
        try {
            $response = $this->http->get('/song/', [
                'query' => [
                    'api_key' => $this->apiKey,
                    'id'      => $songId,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $song = $data['song'] ?? null;

            if (!$song) {
                return ['bpm' => null, 'song_key' => null, 'genre' => null, 'artist' => null];
            }

            $genres = $song['artist']['genres'] ?? [];

            // key_of format: "Em", "Bb", "F#m", "C" — trailing 'm' = minor, absent = major
            $keyOf = $song['key_of'] ?? null;
            $songKey = null;
            if ($keyOf) {
                $isMinor = str_ends_with($keyOf, 'm');
                $note = $isMinor ? substr($keyOf, 0, -1) : $keyOf;
                $songKey = $note . ($isMinor ? ' min' : ' maj');
            }

            return [
                'bpm'      => isset($song['tempo']) ? (int) $song['tempo'] : null,
                'song_key' => $songKey,
                'genre'    => !empty($genres) ? ucfirst($genres[0]) : null,
                'artist'   => $song['artist']['name'] ?? null,
            ];

        } catch (RequestException $e) {
            Log::warning('GetSongBPM song details failed', ['error' => $e->getMessage(), 'id' => $songId]);
            return ['bpm' => null, 'song_key' => null, 'genre' => null, 'artist' => null];
        }
    }
}
