# Song List Mobile API + Songs↔Charts Link (Laravel) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose full song CRUD + BPM lookup on the mobile API, add the songs↔charts relationship, and surface chart↔song linking in the web UI, per the approved spec `docs/superpowers/specs/2026-07-13-mobile-song-list-design.md`.

**Architecture:** New nullable `song_id` FK on `charts`. Shared FormRequests keep web and mobile song validation identical. A new `Api\Mobile\SongsController` owns all mobile song endpoints (the read endpoint moves out of `MusicController`), gated by a new `songs` token ability. Web `ChartsController` + Vue pages gain the linking UI; user-facing "Charts" copy becomes "Sheet Music".

**Tech Stack:** Laravel 12, PHPUnit 11, Sanctum token abilities, Inertia v1 + Vue 3 (Options API pages, PrimeVue globally registered), Vitest.

## Global Constraints

- **All commands run inside the app container:** `docker-compose exec app php artisan …` — never on the host.
- **Test method names use the `test_` prefix** (never `it_`, never `/** @test */`).
- **Never edit** `database/schema/mysql-schema.sql` / `mysql-test-schema.sql`; never run `schema:dump` on this branch.
- **Migrations are generated** with `php artisan make:migration`, then edited — never written from scratch. Never modify already-deployed migrations.
- Branch: `feat/mobile-song-list` (already created). PRs target `staging`.
- Backend permission keys, routes, table names stay `charts`/`songs` — only display copy changes to "Sheet Music".
- PHP: explicit return types, curly braces always, PHPDoc over inline comments.
- Run focused tests per task: `docker-compose exec app php artisan test --filter=<Name>`.

---

### Task 1: `charts.song_id` migration + model relationships

**Files:**
- Create: `database/migrations/<timestamp>_add_song_id_to_charts_table.php` (via make:migration)
- Modify: `app/Models/Charts.php`
- Modify: `app/Models/Song.php`
- Test: `tests/Feature/SongChartLinkTest.php`

**Interfaces:**
- Produces: `Charts::song(): BelongsTo` (nullable), `Song::charts(): HasMany`, `charts.song_id` column (nullable FK, `nullOnDelete`). Later tasks (4, 8, 9, 11) rely on these exact names.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SongChartLinkTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongChartLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_can_be_linked_to_a_song(): void
    {
        $band = Bands::factory()->create();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->assertTrue($chart->song->is($song));
        $this->assertTrue($song->charts->first()->is($chart));
    }

    public function test_deleting_a_song_nulls_the_chart_link_without_deleting_the_chart(): void
    {
        $band = Bands::factory()->create();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $song->delete();

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => null]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec app php artisan test --filter=SongChartLinkTest`
Expected: FAIL — SQL error, `song_id` column doesn't exist.

- [ ] **Step 3: Generate and fill the migration**

Run: `docker-compose exec app php artisan make:migration add_song_id_to_charts_table --no-interaction`

Edit the generated file so `up`/`down` read:

```php
public function up(): void
{
    Schema::table('charts', function (Blueprint $table) {
        $table->foreignId('song_id')
            ->nullable()
            ->after('band_id')
            ->constrained('songs')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('charts', function (Blueprint $table) {
        $table->dropConstrainedForeignId('song_id');
    });
}
```

- [ ] **Step 4: Add the relationships**

In `app/Models/Charts.php`, after `uploads()`:

```php
public function song()
{
    return $this->belongsTo(Song::class, 'song_id');
}
```

In `app/Models/Song.php`, after the existing relationship methods (match that file's return-type style):

```php
public function charts(): HasMany
{
    return $this->hasMany(Charts::class, 'song_id');
}
```

(`Song.php` already imports `HasMany` for `setlistSongs()`; `Charts.php` follows its own no-return-type style — match each file.)

- [ ] **Step 5: Run test to verify it passes**

Run: `docker-compose exec app php artisan test --filter=SongChartLinkTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/Charts.php app/Models/Song.php tests/Feature/SongChartLinkTest.php
git commit -m "feat(songs): add nullable song_id link on charts"
```

---

### Task 2: Shared Song FormRequests + web `SongsController` refactor + backfilled web tests

**Files:**
- Create: `app/Http/Requests/StoreSongRequest.php`, `app/Http/Requests/UpdateSongRequest.php` (via `php artisan make:request`)
- Modify: `app/Http/Controllers/SongsController.php` (also move `GENRES` to `Song`)
- Modify: `app/Models/Song.php` (add `GENRES` const)
- Test: `tests/Feature/SongsWebTest.php`

**Interfaces:**
- Produces: `StoreSongRequest` (fills `band_id` from a `{band}` route param when present — mobile reuses this in Task 5), `UpdateSongRequest`, `Song::GENRES` (public const array). Rules are the current web rules verbatim.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SongsWebTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongsWebTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        return [$user, $band];
    }

    private function makeMemberWithWrite(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        $user->givePermissionTo('read:songs');
        $user->givePermissionTo('write:songs');
        setPermissionsTeamId(0);

        return [$user, $band];
    }

    public function test_owner_can_create_a_song(): void
    {
        [$user, $band] = $this->makeOwner();

        $resp = $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'Superstition',
            'artist'  => 'Stevie Wonder',
            'bpm'     => 100,
            'active'  => true,
        ]);

        $resp->assertCreated()->assertJsonPath('title', 'Superstition');
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'title' => 'Superstition']);
    }

    public function test_member_with_write_permission_can_create_a_song(): void
    {
        [$user, $band] = $this->makeMemberWithWrite();

        $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'My Girl',
            'active'  => true,
        ])->assertCreated();
    }

    public function test_member_without_write_permission_cannot_create_a_song(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'Nope',
        ])->assertForbidden();
    }

    public function test_store_requires_a_title(): void
    {
        [$user, $band] = $this->makeOwner();

        $this->actingAs($user)->postJson('/songs', ['band_id' => $band->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_owner_can_update_a_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'Old']);

        $this->actingAs($user)->patchJson("/songs/{$song->id}", [
            'title'  => 'New Title',
            'active' => true,
        ])->assertOk()->assertJsonPath('title', 'New Title');
    }

    public function test_only_owner_can_delete_a_song(): void
    {
        [$member, $band] = $this->makeMemberWithWrite();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($member)->deleteJson("/songs/{$song->id}")->assertForbidden();

        $owner = User::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $this->actingAs($owner)->deleteJson("/songs/{$song->id}")->assertOk();
        $this->assertDatabaseMissing('songs', ['id' => $song->id]);
    }
}
```

- [ ] **Step 2: Run tests — expect them to PASS against current code**

Run: `docker-compose exec app php artisan test --filter=SongsWebTest`
Expected: PASS. These tests pin current behavior BEFORE the refactor. If any fail, the assumptions are wrong — stop and investigate (check `User::factory()` produces a verified user; web routes require `verified`).

- [ ] **Step 3: Commit the pinning tests**

```bash
git add tests/Feature/SongsWebTest.php
git commit -m "test(songs): backfill web SongsController coverage"
```

- [ ] **Step 4: Create the FormRequests**

Run: `docker-compose exec app php artisan make:request StoreSongRequest --no-interaction && docker-compose exec app php artisan make:request UpdateSongRequest --no-interaction`

`app/Http/Requests/StoreSongRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission enforced by songs.write / mobile.band middleware + controller checks.
    }

    /**
     * Mobile routes carry the band as a route param (/bands/{band}/songs);
     * web sends band_id in the body. Normalize so one rule set serves both.
     */
    protected function prepareForValidation(): void
    {
        $band = $this->route('band');

        if ($band !== null) {
            $this->merge(['band_id' => is_object($band) ? $band->id : $band]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'band_id' => 'required|integer|exists:bands,id',
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => 'nullable|integer|exists:songs,id',
            'active' => 'boolean',
        ];
    }
}
```

`app/Http/Requests/UpdateSongRequest.php` — identical minus `band_id` and `prepareForValidation`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission enforced by songs.write / mobile.band middleware + controller checks.
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => 'nullable|integer|exists:songs,id',
            'active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Move `GENRES` to the model and refactor the controller**

In `app/Models/Song.php`, add inside the class (top, before relations):

```php
public const GENRES = [
    'Blues', 'Country', 'Funk', 'Hip Hop', 'Jazz', 'Latin',
    'Pop', 'R&B', 'Rock', 'Soul',
];
```

In `app/Http/Controllers/SongsController.php`:
- Delete the private `GENRES` const; replace both `self::GENRES` usages with `Song::GENRES`.
- Add imports: `use App\Http\Requests\StoreSongRequest;` and `use App\Http\Requests\UpdateSongRequest;`.
- Replace `store` and `update` signatures/validation (permission checks and response shape unchanged):

```php
public function store(StoreSongRequest $request): JsonResponse
{
    $validated = $request->validated();
    $band = Bands::findOrFail($validated['band_id']);

    if (!Auth::user()->canWrite('songs', $band->id)) {
        abort(403, 'Permission denied');
    }

    $song = $band->songs()->create($validated);
    $song->load(['leadSinger.user', 'transitionSong:id,title,artist']);

    return response()->json($song, 201);
}

public function update(UpdateSongRequest $request, Song $song): JsonResponse
{
    if (!Auth::user()->canWrite('songs', $song->band_id)) {
        abort(403, 'Permission denied');
    }

    $song->update($request->validated());
    $song->load(['leadSinger.user', 'transitionSong:id,title,artist']);

    return response()->json($song);
}
```

- [ ] **Step 6: Run tests to verify the refactor changed nothing**

Run: `docker-compose exec app php artisan test --filter=SongsWebTest`
Expected: PASS (all 7).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/StoreSongRequest.php app/Http/Requests/UpdateSongRequest.php app/Http/Controllers/SongsController.php app/Models/Song.php
git commit -m "refactor(songs): extract shared FormRequests and Song::GENRES"
```

---

### Task 3: Add `songs` to mobile token abilities

**Files:**
- Modify: `app/Services/Mobile/TokenService.php:10`
- Test: `tests/Feature/Api/Mobile/TokenAbilitiesSongsTest.php`

**Interfaces:**
- Produces: tokens minted with `read:songs` / `write:songs` when `User::canRead/canWrite('songs', $bandId)` holds. Tasks 4–7 route middleware depends on these ability strings.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/Mobile/TokenAbilitiesSongsTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAbilitiesSongsTest extends TestCase
{
    use RefreshDatabase;

    public function test_band_owner_token_includes_song_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $abilities = (new TokenService())->buildAbilities($user->fresh());

        $this->assertContains('read:songs', $abilities);
        $this->assertContains('write:songs', $abilities);
    }

    public function test_member_with_read_only_gets_read_but_not_write(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        $user->givePermissionTo('read:songs');
        setPermissionsTeamId(0);

        $abilities = (new TokenService())->buildAbilities($user->fresh());

        $this->assertContains('read:songs', $abilities);
        $this->assertNotContains('write:songs', $abilities);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec app php artisan test --filter=TokenAbilitiesSongsTest`
Expected: FAIL — `read:songs` not in abilities.

- [ ] **Step 3: Add the resource**

In `app/Services/Mobile/TokenService.php` change line 10 to:

```php
private const RESOURCES = ['bookings', 'events', 'media', 'rehearsals', 'charts', 'songs'];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker-compose exec app php artisan test --filter=TokenAbilitiesSongsTest`
Expected: PASS.

- [ ] **Step 5: Run the token regression suite**

Run: `docker-compose exec app php artisan test --filter=TokenRefreshTest`
Expected: PASS (stale-token refresh path unaffected).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Mobile/TokenService.php tests/Feature/Api/Mobile/TokenAbilitiesSongsTest.php
git commit -m "feat(mobile-api): mint read:songs/write:songs token abilities"
```

---

### Task 4: Mobile songs index — new `SongsController@index`, re-gated, expanded payload

**Files:**
- Create: `app/Http/Controllers/Api/Mobile/SongsController.php` (via `php artisan make:controller Api/Mobile/SongsController --no-interaction`)
- Modify: `routes/api.php` (move the songs route out of the `read:charts` group)
- Modify: `app/Http/Controllers/Api/Mobile/MusicController.php` (delete `songs()`)
- Test: `tests/Feature/Api/Mobile/MobileSongsTest.php`

**Interfaces:**
- Consumes: `Song::charts()` (Task 1), `read:songs` ability (Task 3).
- Produces: `GET /api/mobile/bands/{band}/songs` → `{"songs": [{id, band_id, title, artist, song_key, genre, bpm, notes, rating, energy, active, lead_singer: {id, display_name}|null, transition_song: {id, title, artist}|null, charts: [{id, title}]}], "genres": [...]}`. Default active-only; `?include_inactive=1` returns all. `SongsController::songPayload(Song $song): array` — private formatter reused by Tasks 5–6.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/Mobile/MobileSongsTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSongsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, band: Bands, headers: array<string, mixed>} */
    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device', ['mobile', 'read:songs', 'write:songs'])->plainTextToken;

        return [
            'user' => $user,
            'band' => $band,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    /** Member holding only the given per-band permissions, token minted with matching abilities. */
    private function makeMember(Bands $band, array $permissions, array $abilities): array
    {
        $user = User::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        setPermissionsTeamId(0);
        $token = $user->createToken('test-device', array_merge(['mobile'], $abilities))->plainTextToken;

        return [
            'user' => $user,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    public function test_index_returns_expanded_song_payload(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->active()->create([
            'title' => 'Uptown Funk', 'rating' => 8, 'energy' => 9, 'notes' => 'Horns!',
            'lead_singer_id' => null, 'transition_song_id' => null,
        ]);
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id, 'title' => 'Uptown Funk - Horns']);

        $resp = $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs");

        $resp->assertOk()
            ->assertJsonPath('songs.0.title', 'Uptown Funk')
            ->assertJsonPath('songs.0.rating', 8)
            ->assertJsonPath('songs.0.energy', 9)
            ->assertJsonPath('songs.0.notes', 'Horns!')
            ->assertJsonPath('songs.0.active', true)
            ->assertJsonPath('songs.0.charts.0.title', 'Uptown Funk - Horns')
            ->assertJsonStructure(['songs', 'genres']);
    }

    public function test_index_excludes_inactive_by_default_and_includes_with_flag(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        Song::factory()->forBand($band)->active()->create(['title' => 'Active One']);
        Song::factory()->forBand($band)->inactive()->create(['title' => 'Retired One']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertOk()->assertJsonCount(1, 'songs');

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs?include_inactive=1")
            ->assertOk()->assertJsonCount(2, 'songs');
    }

    public function test_index_requires_read_songs(): void
    {
        ['band' => $band] = $this->makeOwner();
        // Member with charts perms only — old-style token without read:songs.
        ['headers' => $headers] = $this->makeMember($band, ['read:charts'], ['read:charts']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertForbidden();
    }

    public function test_index_allows_member_with_read_songs(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs'], ['read:songs']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: FAIL — payload lacks `rating`/`genres`; gating test fails (route still accepts `read:charts`).

- [ ] **Step 3: Create the controller**

Run: `docker-compose exec app php artisan make:controller Api/Mobile/SongsController --no-interaction`

Replace its contents:

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongsController extends Controller
{
    /**
     * List a band's songs. Active-only by default (search + setlist picker
     * behavior); pass include_inactive=1 for the management screen.
     */
    public function index(Request $request, Bands $band): JsonResponse
    {
        $query = Song::where('band_id', $band->id)
            ->with([
                'leadSinger.user',
                'transitionSong:id,title,artist',
                'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads'),
            ])
            ->orderBy('title');

        if (!$request->boolean('include_inactive')) {
            $query->where('active', true);
        }

        return response()->json([
            'songs' => $query->get()->map(fn (Song $s) => $this->songPayload($s))->values(),
            'genres' => Song::GENRES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function songPayload(Song $song): array
    {
        return [
            'id' => $song->id,
            'band_id' => $song->band_id,
            'title' => $song->title ?? '',
            'artist' => $song->artist ?? '',
            'song_key' => $song->song_key ?? '',
            'genre' => $song->genre ?? '',
            'bpm' => $song->bpm ?? 0,
            'notes' => $song->notes ?? '',
            'rating' => $song->rating,
            'energy' => $song->energy,
            'active' => (bool) $song->active,
            'lead_singer' => $song->leadSinger ? [
                'id' => $song->leadSinger->id,
                'display_name' => $song->leadSinger->display_name,
            ] : null,
            'transition_song' => $song->transitionSong ? [
                'id' => $song->transitionSong->id,
                'title' => $song->transitionSong->title ?? '',
                'artist' => $song->transitionSong->artist ?? '',
            ] : null,
            'charts' => $song->charts->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?? '',
            ])->values(),
        ];
    }
}
```

Note the `->without('uploads')` on the charts eager load — `Charts` has `protected $with = ['uploads']`, which would otherwise drag every upload row into a list payload.

- [ ] **Step 4: Rewire the route and delete `MusicController@songs`**

In `routes/api.php`, remove this line from the `mobile.band:read:charts` group:

```php
Route::get('/bands/{band}/songs', [App\Http\Controllers\Api\Mobile\MusicController::class, 'songs'])->name('mobile.songs.index');
```

Directly above the `── Music / Charts (read)` group, add:

```php
// ── Songs (read) ───────────────────────────────────────────────
Route::middleware('mobile.band:read:songs')->group(function () {
    Route::get('/bands/{band}/songs', [App\Http\Controllers\Api\Mobile\SongsController::class, 'index'])->name('mobile.songs.index');
});
```

Delete the `songs()` method from `app/Http/Controllers/Api/Mobile/MusicController.php` (and its now-unused `use App\Models\Song;` import if nothing else references it).

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Run adjacent regressions**

Run: `docker-compose exec app php artisan test --filter=MobileSetlistEditorTest`
Expected: PASS (setlist editor consumes songs via its own path; confirm nothing coupled to the old method).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/SongsController.php app/Http/Controllers/Api/Mobile/MusicController.php routes/api.php tests/Feature/Api/Mobile/MobileSongsTest.php
git commit -m "feat(mobile-api): expanded songs index gated by read:songs"
```

---

### Task 5: Mobile song create + update

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/SongsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Mobile/MobileSongsTest.php` (extend)

**Interfaces:**
- Consumes: `StoreSongRequest`/`UpdateSongRequest` (Task 2 — `prepareForValidation` maps the `{band}` route param to `band_id`), `songPayload()` (Task 4), `write:songs` ability (Task 3).
- Produces: `POST /api/mobile/bands/{band}/songs` → 201 `{"song": {...}}`; `PATCH /api/mobile/bands/{band}/songs/{song}` → 200 `{"song": {...}}` (404 on band mismatch).

- [ ] **Step 1: Write the failing tests** — append to `MobileSongsTest`:

```php
    public function test_member_with_write_songs_can_create_a_song(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs', 'write:songs'], ['read:songs', 'write:songs']);

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [
            'title' => 'September',
            'artist' => 'Earth, Wind & Fire',
            'bpm' => 126,
            'energy' => 10,
            'active' => true,
        ]);

        $resp->assertCreated()
            ->assertJsonPath('song.title', 'September')
            ->assertJsonPath('song.energy', 10);
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'title' => 'September']);
    }

    public function test_member_without_write_songs_cannot_create(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs'], ['read:songs']);

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [
            'title' => 'Nope',
        ])->assertForbidden();
    }

    public function test_create_requires_title(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_edits_a_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->active()->create(['title' => 'Old']);

        $resp = $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/songs/{$song->id}", [
            'title' => 'New Title',
            'active' => false,
        ]);

        $resp->assertOk()
            ->assertJsonPath('song.title', 'New Title')
            ->assertJsonPath('song.active', false);
    }

    public function test_update_rejects_song_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $otherBand = Bands::factory()->create();
        $foreign = Song::factory()->forBand($otherBand)->create();

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/songs/{$foreign->id}", [
            'title' => 'Hijack',
        ])->assertNotFound();
    }
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: new tests FAIL with 405 (routes don't exist); Task 4 tests still PASS.

- [ ] **Step 3: Implement store + update**

Add imports to `app/Http/Controllers/Api/Mobile/SongsController.php`:

```php
use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
```

Add methods:

```php
    public function store(StoreSongRequest $request, Bands $band): JsonResponse
    {
        $song = $band->songs()->create($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json(['song' => $this->songPayload($song)], 201);
    }

    public function update(UpdateSongRequest $request, Bands $band, Song $song): JsonResponse
    {
        if ((int) $song->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Song not found.'], 404);
        }

        $song->update($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json(['song' => $this->songPayload($song)]);
    }
```

In `routes/api.php`, below the Songs (read) group, add:

```php
// ── Songs (write) ──────────────────────────────────────────────
Route::middleware('mobile.band:write:songs')->group(function () {
    Route::post('/bands/{band}/songs', [App\Http\Controllers\Api\Mobile\SongsController::class, 'store'])->name('mobile.songs.store');
    Route::patch('/bands/{band}/songs/{song}', [App\Http\Controllers\Api\Mobile\SongsController::class, 'update'])->name('mobile.songs.update');
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/SongsController.php routes/api.php tests/Feature/Api/Mobile/MobileSongsTest.php
git commit -m "feat(mobile-api): song create and update endpoints"
```

---

### Task 6: Mobile song delete (owner-only)

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/SongsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Mobile/MobileSongsTest.php` (extend)

**Interfaces:**
- Produces: `DELETE /api/mobile/bands/{band}/songs/{song}` → 200 `{"message": "Song deleted."}`; 403 for non-owners (even with `write:songs`), 404 on band mismatch.

- [ ] **Step 1: Write the failing tests** — append to `MobileSongsTest`:

```php
    public function test_owner_can_delete_a_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();

        $this->withHeaders($headers)->deleteJson("/api/mobile/bands/{$band->id}/songs/{$song->id}")
            ->assertOk();
        $this->assertDatabaseMissing('songs', ['id' => $song->id]);
    }

    public function test_member_with_write_songs_cannot_delete(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs', 'write:songs'], ['read:songs', 'write:songs']);
        $song = Song::factory()->forBand($band)->create();

        $this->withHeaders($headers)->deleteJson("/api/mobile/bands/{$band->id}/songs/{$song->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('songs', ['id' => $song->id]);
    }
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: new tests FAIL with 405.

- [ ] **Step 3: Implement destroy**

Add to `SongsController`:

```php
    public function destroy(Request $request, Bands $band, Song $song): JsonResponse
    {
        if ((int) $song->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Song not found.'], 404);
        }

        if (!$request->user()->ownsBand($band->id)) {
            return response()->json(['message' => 'Only band owners can delete songs.'], 403);
        }

        $song->delete();

        return response()->json(['message' => 'Song deleted.']);
    }
```

Add to the Songs (write) route group:

```php
    Route::delete('/bands/{band}/songs/{song}', [App\Http\Controllers\Api\Mobile\SongsController::class, 'destroy'])->name('mobile.songs.destroy');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: PASS (11 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/SongsController.php routes/api.php tests/Feature/Api/Mobile/MobileSongsTest.php
git commit -m "feat(mobile-api): owner-only song delete"
```

---

### Task 7: Mobile BPM lookup endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/SongsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Mobile/MobileSongsTest.php` (extend)

**Interfaces:**
- Consumes: `App\Services\GetSongBpmService::lookup(string $title, ?string $artist)` (existing; web `SongsController@lookup` wraps the same call).
- Produces: `GET /api/mobile/songs/lookup?title=…&artist=…` → JSON passthrough of the service result. Resolved from the container so tests can mock it.

- [ ] **Step 1: Write the failing test** — append to `MobileSongsTest` (add `use App\Services\GetSongBpmService;` to the imports):

```php
    public function test_bpm_lookup_proxies_the_service(): void
    {
        ['headers' => $headers] = $this->makeOwner();

        $this->mock(GetSongBpmService::class)
            ->shouldReceive('lookup')
            ->once()
            ->with('Superstition', 'Stevie Wonder')
            ->andReturn(['bpm' => 100, 'song_key' => 'E♭m']);

        $this->withHeaders($headers)
            ->getJson('/api/mobile/songs/lookup?title=Superstition&artist=Stevie%20Wonder')
            ->assertOk()
            ->assertJsonPath('bpm', 100);
    }

    public function test_bpm_lookup_requires_title(): void
    {
        ['headers' => $headers] = $this->makeOwner();

        $this->withHeaders($headers)->getJson('/api/mobile/songs/lookup')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: new tests FAIL with 404 (route missing).

- [ ] **Step 3: Implement lookup**

Add `use App\Services\GetSongBpmService;` to `SongsController` imports, then:

```php
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
        ]);

        $result = resolve(GetSongBpmService::class)->lookup(
            $request->input('title'),
            $request->input('artist')
        );

        return response()->json($result);
    }
```

In `routes/api.php`, inside the `auth:sanctum` group but OUTSIDE the band-gated groups (it is band-independent, like the web route), add near the Songs groups:

```php
Route::get('/songs/lookup', [App\Http\Controllers\Api\Mobile\SongsController::class, 'lookup'])->name('mobile.songs.lookup');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test --filter=MobileSongsTest`
Expected: PASS (13 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/SongsController.php routes/api.php tests/Feature/Api/Mobile/MobileSongsTest.php
git commit -m "feat(mobile-api): BPM lookup endpoint"
```

---

### Task 8: Mobile chart endpoints — `song_id` on create, new PATCH, song in payloads

**Files:**
- Modify: `app/Http/Requests/Mobile/StoreChartRequest.php`
- Create: `app/Http/Requests/Mobile/UpdateChartRequest.php` (via make:request)
- Modify: `app/Http/Controllers/Api/Mobile/MusicController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Mobile/MobileChartSongLinkTest.php`

**Interfaces:**
- Consumes: `charts.song_id` (Task 1), `write:charts` ability (existing).
- Produces: `POST /bands/{band}/charts` accepts optional `song_id`; new `PATCH /bands/{band}/charts/{chart}` (route name `mobile.charts.update`) accepts partial `title/composer/description/price/is_public/song_id`; chart payloads from `charts`, `chartDetail`, `storeChart`, `updateChart` include `"song": {id, title, artist}|null`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/Mobile/MobileChartSongLinkTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileChartSongLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device', ['mobile', 'read:charts', 'write:charts'])->plainTextToken;

        return [
            'band' => $band,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    public function test_chart_can_be_created_with_a_linked_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/charts", [
            'title' => 'Horn Chart',
            'song_id' => $song->id,
        ]);

        $resp->assertCreated()->assertJsonPath('chart.song.id', $song->id);
        $this->assertDatabaseHas('charts', ['title' => 'Horn Chart', 'song_id' => $song->id]);
    }

    public function test_chart_create_rejects_song_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $foreignSong = Song::factory()->forBand(Bands::factory()->create())->create();

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/charts", [
            'title' => 'Sneaky',
            'song_id' => $foreignSong->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['song_id']);
    }

    public function test_chart_can_be_relinked_and_unlinked_via_patch(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$chart->id}", [
            'song_id' => $song->id,
        ])->assertOk()->assertJsonPath('chart.song.id', $song->id);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$chart->id}", [
            'song_id' => null,
        ])->assertOk()->assertJsonPath('chart.song', null);
    }

    public function test_patch_rejects_chart_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $foreignChart = Charts::factory()->create(['band_id' => Bands::factory()->create()->id]);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$foreignChart->id}", [
            'title' => 'Hijack',
        ])->assertNotFound();
    }

    public function test_chart_list_includes_linked_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/charts")
            ->assertOk()->assertJsonPath('charts.0.song.title', 'My Girl');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker-compose exec app php artisan test --filter=MobileChartSongLinkTest`
Expected: FAIL (`song_id` ignored, PATCH route missing, no `song` key in payloads).

- [ ] **Step 3: Update the requests**

In `app/Http/Requests/Mobile/StoreChartRequest.php`, add `use Illuminate\Validation\Rule;` and append to `rules()`:

```php
            'song_id' => ['nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $this->route('band')?->id)),
            ],
```

Run: `docker-compose exec app php artisan make:request Mobile/UpdateChartRequest --no-interaction` and replace its contents:

```php
<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'       => 'sometimes|required|string|max:255',
            'composer'    => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'price'       => 'sometimes|nullable|numeric|min:0',
            'is_public'   => 'sometimes|nullable|boolean',
            'song_id'     => ['sometimes', 'nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $this->route('band')?->id)),
            ],
        ];
    }
}
```

- [ ] **Step 4: Implement controller changes**

In `app/Http/Controllers/Api/Mobile/MusicController.php`:

1. Add import `use App\Http\Requests\Mobile\UpdateChartRequest;`.
2. Add a private formatter for the song block and use it in `charts()`, `chartsForUser()`, `chartDetail()`, `storeChart()` payload maps (add the `'song' => …` key to each existing array):

```php
    /**
     * @return array{id: int, title: string, artist: string}|null
     */
    private function songBlock(?Song $song): ?array
    {
        return $song ? [
            'id' => $song->id,
            'title' => $song->title ?? '',
            'artist' => $song->artist ?? '',
        ] : null;
    }
```

In `charts()` add `->with('song:id,title,artist')` to the query and `'song' => $this->songBlock($ch->song),` to the map. Same `with` + map key in `chartsForUser()`. In `chartDetail()` and `storeChart()` add `'song' => $this->songBlock($chart->song),` (call `$chart->loadMissing('song')` alongside the existing `loadMissing('uploads.type')`).

3. In `storeChart()`, add `'song_id' => $request->validated('song_id'),` to the `Charts::create([...])` array.
4. Add the update method:

```php
    /**
     * Partially update a chart (title/composer/description/price/public/linked song).
     */
    public function updateChart(UpdateChartRequest $request, Bands $band, Charts $chart): JsonResponse
    {
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        $validated = $request->validated();

        foreach (['title', 'composer', 'description', 'price'] as $field) {
            if (array_key_exists($field, $validated)) {
                $chart->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('is_public', $validated)) {
            $chart->public = $request->boolean('is_public');
        }
        if (array_key_exists('song_id', $validated)) {
            $chart->song_id = $validated['song_id'];
        }
        $chart->save();

        $chart->loadMissing(['uploads.type', 'song']);

        return response()->json([
            'chart' => [
                'id'            => $chart->id,
                'band_id'       => $chart->band_id,
                'title'         => $chart->title ?? '',
                'composer'      => $chart->composer ?? '',
                'description'   => $chart->description ?? '',
                'price'         => $chart->price ?? 0,
                'public'        => (bool) $chart->public,
                'song'          => $this->songBlock($chart->song),
                'uploads_count' => $chart->uploads->count(),
            ],
        ]);
    }
```

5. In `routes/api.php`, add to the `mobile.band:write:charts` group:

```php
            Route::patch('/bands/{band}/charts/{chart}', [App\Http\Controllers\Api\Mobile\MusicController::class, 'updateChart'])->name('mobile.charts.update');
```

- [ ] **Step 5: Run tests to verify they pass, plus chart regressions**

Run: `docker-compose exec app php artisan test --filter=MobileChartSongLinkTest`
Expected: PASS (5 tests).
Run: `docker-compose exec app php artisan test tests/Feature/Api/Mobile/`
Expected: PASS — existing mobile suites unaffected by the added payload key.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Mobile app/Http/Controllers/Api/Mobile/MusicController.php routes/api.php tests/Feature/Api/Mobile/MobileChartSongLinkTest.php
git commit -m "feat(mobile-api): chart song linking and PATCH chart endpoint"
```

---

### Task 9: Web `ChartsController` — `song_id` on store/update, songs for edit, song on show

**Files:**
- Modify: `app/Http/Controllers/ChartsController.php` (`store`, `update`, `edit`, `show`)
- Test: `tests/Feature/ChartSongLinkWebTest.php`

**Interfaces:**
- Consumes: `charts.song_id` (Task 1).
- Produces: Inertia props — `Charts/Edit` gets `songs: [{id, title, artist}]` and `chart.song_id`; `Charts/Show` gets `chart.song` loaded. `charts.update` accepts `song_id` (nullable, same-band). Task 10's Vue work consumes these exact props.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ChartSongLinkWebTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChartSongLinkWebTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        return [$user, $band];
    }

    public function test_edit_page_provides_band_songs(): void
    {
        [$user, $band] = $this->makeOwner();
        Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->get("/charts/{$chart->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Charts/Edit')
                ->has('songs', 1)
                ->where('songs.0.title', 'My Girl'));
    }

    public function test_update_links_a_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->post("/charts/{$chart->id}", [
            'title' => $chart->title,
            'composer' => $chart->composer,
            'public' => false,
            'description' => '',
            'song_id' => $song->id,
        ]);

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => $song->id]);
    }

    public function test_update_rejects_cross_band_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $foreignSong = Song::factory()->forBand(Bands::factory()->create())->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->from("/charts/{$chart->id}/edit")->post("/charts/{$chart->id}", [
            'title' => $chart->title,
            'composer' => $chart->composer,
            'public' => false,
            'description' => '',
            'song_id' => $foreignSong->id,
        ])->assertSessionHasErrors(['song_id']);

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => null]);
    }

    public function test_show_page_includes_linked_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->actingAs($user)->get("/charts/{$chart->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Charts/Show')
                ->where('chart.song.title', 'My Girl'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker-compose exec app php artisan test --filter=ChartSongLinkWebTest`
Expected: FAIL — no `songs` prop, `song_id` not persisted, no `chart.song`.

Note: check `routes/charts.php` middleware on these routes first (read/write charts); if a `charts.read`/`charts.write`-style middleware needs the owner's permissions, `makeOwner` covers it via the owner shortcut.

- [ ] **Step 3: Implement controller changes**

In `app/Http/Controllers/ChartsController.php` add imports:

```php
use App\Models\Song;
use Illuminate\Validation\Rule;
```

`store()` — validate before creating (this method currently has no validation; add it):

```php
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'composer' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'band_id' => 'required|integer|exists:bands,id',
            'song_id' => ['nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $request->input('band_id'))),
            ],
        ]);

        $chart = Charts::create([
            'title' => $validated['name'],
            'composer' => $validated['composer'] ?? null,
            'price' => $validated['price'] ?? 0,
            'band_id' => $validated['band_id'],
            'song_id' => $validated['song_id'] ?? null,
        ]);

        return redirect('/charts/' . $chart->id);
    }
```

`update()` — validate `song_id`, assign it with the existing fields:

```php
    public function update(Charts $chart, Request $request)
    {
        $validated = $request->validate([
            'song_id' => ['nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $chart->band_id)),
            ],
        ]);

        $chart->title = $request->title;
        $chart->composer = $request->composer;
        $chart->description = $request->description;
        $chart->public = $request->public === true;
        $chart->song_id = $validated['song_id'] ?? null;
        $chart->save();

        return back()->with('successMessage', 'Updated ' . $chart->title);
    }
```

`edit()` — add the songs prop:

```php
    public function edit(Charts $chart)
    {
        $chartData = $chart->fresh()->load('uploads.type');

        return Inertia::render('Charts/Edit', [
            'chart' => $chartData,
            'songs' => Song::where('band_id', $chart->band_id)
                ->orderBy('title')
                ->get(['id', 'title', 'artist']),
        ]);
    }
```

`show()` — load the song:

```php
        return Inertia::render('Charts/Show', [
            'chart' => $chart->load('song:id,title,artist'),
            'canEdit' => $canEdit
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test --filter=ChartSongLinkWebTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Run the charts regression sweep**

Run: `docker-compose exec app php artisan test --filter=Chart`
Expected: PASS (any pre-existing chart tests unaffected; `store` gaining validation is the risk point — if a pre-existing test posts without `name`, adapt the assertion conversation with the user, don't silently loosen validation).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ChartsController.php tests/Feature/ChartSongLinkWebTest.php
git commit -m "feat(charts): song linking on web chart store/update/show/edit"
```

---

### Task 10: Vue — linked-song select on `Charts/Edit.vue`, display on `Charts/Show.vue`

**Files:**
- Modify: `resources/js/Pages/Charts/Edit.vue`
- Modify: `resources/js/Pages/Charts/Show.vue`

**Interfaces:**
- Consumes: `songs` prop + `chart.song_id` (Task 9), `chart.song` on Show (Task 9). PrimeVue `Dropdown` is globally registered (see `resources/js/app.js` global registration loop).

- [ ] **Step 1: Add the `songs` prop and select to Edit.vue**

In `resources/js/Pages/Charts/Edit.vue`, find the component's `props` (script section, `chart` prop) and add:

```js
songs: {
    type: Array,
    default: () => [],
},
```

In the template, after the Composer field's closing `</div>` (the `space-y-2` block around `chartData.composer`), insert:

```vue
            <div class="space-y-2">
              <label
                for="linkedSong"
                class="block text-sm font-medium"
              >Linked Song</label>
              <Dropdown
                id="linkedSong"
                v-model="chartData.song_id"
                :options="songs"
                option-value="id"
                :option-label="(song) => song.artist ? `${song.title} — ${song.artist}` : song.title"
                filter
                show-clear
                placeholder="No linked song"
                class="w-full"
              />
            </div>
```

In the `updateChart()` method, add `song_id` to the posted payload:

```js
                {
                    title: this.chartData.title,
                    composer: this.chartData.composer,
                    public: this.chartData.public,
                    description: this.chartData.description,
                    song_id: this.chartData.song_id ?? null,
                },
```

- [ ] **Step 2: Show the linked song on Show.vue**

In `resources/js/Pages/Charts/Show.vue`, in the `#header` template, after the `{{ chartData.title }}` line inside the `<h2>`, add:

```vue
          <span
            v-if="chartData.song"
            class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400"
          >
            ♪ {{ chartData.song.title }}<template v-if="chartData.song.artist"> — {{ chartData.song.artist }}</template>
          </span>
```

(If `Show.vue` maps the `chart` prop into `chartData` in `data()`, no further change; verify the prop name in the script block matches.)

- [ ] **Step 3: Verify programmatically**

Run: `docker-compose exec app npm run build`
Expected: build succeeds with no errors.
Run: `docker-compose exec app php artisan test --filter=ChartSongLinkWebTest`
Expected: PASS (props these views consume are pinned by Task 9's tests).
Run: `docker-compose exec app npm run test:pipeline`
Expected: PASS (existing Vitest suite unaffected).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Charts/Edit.vue resources/js/Pages/Charts/Show.vue
git commit -m "feat(charts): linked song select and display in web UI"
```

---

### Task 11: Web songs page — sheet music chips

**Files:**
- Modify: `app/Http/Controllers/SongsController.php` (`index`, `store`, `update` eager loads)
- Modify: `resources/js/Pages/Songs/Index.vue`
- Test: `tests/Feature/SongsWebTest.php` (extend)

**Interfaces:**
- Consumes: `Song::charts()` (Task 1). `Tag` + `Link` usage: `Tag` is globally registered; `Link` must be imported from `@inertiajs/vue3` if not already present in the file.
- Produces: songs payloads include `charts: [{id, song_id, title}]`.

- [ ] **Step 1: Write the failing test** — append to `SongsWebTest` (add `use App\Models\Charts;` and `use Inertia\Testing\AssertableInertia as Assert;` imports):

```php
    public function test_index_includes_linked_charts_for_songs(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id, 'title' => 'Horn Chart']);

        $this->actingAs($user)->get("/songs?band_id={$band->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Songs/Index')
                ->where('songs.0.charts.0.title', 'Horn Chart'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec app php artisan test --filter=test_index_includes_linked_charts_for_songs`
Expected: FAIL — `charts` key absent.

- [ ] **Step 3: Add the eager loads**

In `app/Http/Controllers/SongsController.php`, extend all three `with`/`load` calls (in `index`, `store`, `update`) from:

```php
['leadSinger.user', 'transitionSong:id,title,artist']
```

to:

```php
['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]
```

(`->without('uploads')` matters: `Charts` auto-eager-loads uploads via `$with`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker-compose exec app php artisan test --filter=SongsWebTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Add the chips column**

In `resources/js/Pages/Songs/Index.vue`:

1. In the `<script>` section, add `Link` to the imports from `@inertiajs/vue3` (the file already imports `router` from there) and register it: if the component has a `components:` option add `Link`, otherwise add `components: { Link },`.
2. In the template, after the `Notes` column (`</Column>` at the notes block) and before the `Active` column, add:

```vue
          <Column header="Sheet Music" style="width: 170px">
            <template #body="{ data }">
              <div class="flex flex-wrap gap-1">
                <Link
                  v-for="chart in data.charts || []"
                  :key="chart.id"
                  :href="route('charts.show', chart.id)"
                >
                  <Tag
                    :value="chart.title"
                    severity="info"
                    class="cursor-pointer"
                  />
                </Link>
              </div>
            </template>
          </Column>
```

- [ ] **Step 6: Verify programmatically**

Run: `docker-compose exec app npm run build`
Expected: build succeeds.
Run: `docker-compose exec app npm run test:pipeline`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SongsController.php resources/js/Pages/Songs/Index.vue tests/Feature/SongsWebTest.php
git commit -m "feat(songs): show linked sheet music chips on web song list"
```

---

### Task 12: Relabel "Charts" → "Sheet Music" in user-facing web copy

**Files:**
- Modify: `resources/js/config/navigation.js:85`
- Modify: `resources/js/Pages/Charts/Index.vue` (heading "Band Charts")
- Modify: `resources/js/Pages/Charts/Show.vue` (breadcrumb "Charts", button "Edit Chart")
- Modify: `resources/js/Pages/Charts/Edit.vue` (buttons "Update Chart"/"Delete Chart")
- Possibly: `resources/js/Components/Search/SearchComponent.vue` (results section heading, if it says "Charts")

Display copy only — route names, permission keys, file names all stay `charts`.

- [ ] **Step 1: Sweep the user-facing strings**

Run: `grep -rn "Chart" resources/js/config/navigation.js resources/js/Pages/Charts resources/js/Components/Search/SearchComponent.vue`

Apply, in template/label strings only (not identifiers, routes, or prop names):
- `navigation.js` line 85: `label: 'Chart Library'` → `label: 'Sheet Music'`
- `Charts/Index.vue` heading `Band Charts` → `Sheet Music`
- `Charts/Show.vue` breadcrumb link text `Charts` → `Sheet Music`; button `label="Edit Chart"` → `label="Edit"`
- `Charts/Edit.vue` button `label="Update Chart"` → `label="Update"`, `label="Delete Chart"` → `label="Delete"`
- `SearchComponent.vue`: any visible "Charts" section heading → "Sheet Music"

Leave the Edit.vue in-page panel already labeled "Sheet Music" (uploads panel) as-is — after this change the page reads consistently.

- [ ] **Step 2: Verify programmatically**

Run: `docker-compose exec app npm run build && docker-compose exec app npm run test:pipeline`
Expected: both succeed — copy changes must not break any snapshot/text assertions; if a Vitest test asserts the old label, update that assertion in the same commit.

- [ ] **Step 3: Commit**

```bash
git add resources/js/config/navigation.js resources/js/Pages/Charts resources/js/Components/Search/SearchComponent.vue
git commit -m "feat(ui): relabel Charts as Sheet Music in web copy"
```

---

### Task 13: Full regression + PR

- [ ] **Step 1: Run the full backend suite**

Run: `docker-compose exec app php artisan test --parallel --processes=4`
Expected: all green.

- [ ] **Step 2: Run the full frontend suite + build**

Run: `docker-compose exec app npm run test:pipeline && docker-compose exec app npm run build`
Expected: all green.

- [ ] **Step 3: Push and open the PR (target `staging`)**

```bash
git push -u origin feat/mobile-song-list
gh pr create --base staging --title "feat: mobile song CRUD API + songs↔charts link + Sheet Music web UI" --body "$(cat <<'EOF'
Implements docs/superpowers/specs/2026-07-13-mobile-song-list-design.md (backend + web half):

- charts.song_id nullable FK (nullOnDelete) + Charts::song()/Song::charts()
- Shared Store/UpdateSongRequest used by web and mobile; web SongsController refactored, tests backfilled
- songs token ability; mobile songs index re-gated read:songs with expanded payload + include_inactive
- Mobile song create/update/delete (owner-only) + BPM lookup
- Mobile chart PATCH + song_id linking; chart payloads include linked song
- Web chart edit/show linking UI; song list shows sheet-music chips
- User-facing "Charts" copy relabeled "Sheet Music" (no backend renames)

The Flutter app changes land separately in tts_bandmate (segmented Library tab: "Song list | Sheet music").

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-Review Notes (already applied)

- Spec coverage: data model (T1), shared validation (T2), token ability (T3), mobile read (T4), write (T5–6), BPM (T7), mobile chart linking incl. the PATCH the Flutter edit affordance needs (T8), web linking (T9–10), song-side chips (T11), Sheet Music copy (T12). Mobile *UI* is intentionally out of scope here — separate plan in `tts_bandmate`.
- `include_inactive` default keeps the existing app's search/setlist behavior unchanged.
- `->without('uploads')` guards against the `Charts::$with` eager-load leaking uploads into list payloads (T4, T11).
- Old tokens lacking `read:songs` hit 403 once; the app's stale-token refresh re-mints with the new ability (T3 keeps `TokenRefreshTest` green).
