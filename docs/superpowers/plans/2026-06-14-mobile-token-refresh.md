# Mobile Token Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop mobile Sanctum tokens from going stale after a user's bands/roles change, so a user who goes solo (or otherwise gains a permission) can act on it without a misleading "Insufficient token permissions" 403.

**Architecture:** Add a `TokenService::reissueForCurrentDevice()` helper used by (a) a new `POST /api/mobile/token/refresh` endpoint and (b) `goSolo()`, both of which re-mint the token from current abilities and delete the old one. The Flutter client persists the new token on goSolo and adds a Dio interceptor that, on the specific `"Insufficient token permissions"` 403, refreshes once and retries once.

**Tech Stack:** Laravel 11 + Sanctum (backend, tested via `docker compose exec app php artisan test`); Flutter + Dio + Riverpod + flutter_secure_storage (client).

**Repos:** TTS (backend, branch `feat/mobile-token-refresh` off `staging`); tts_bandmate (client, new branch off `main`).

---

## Backend (TTS)

### Task 1: `TokenService::reissueForCurrentDevice()` helper

**Files:**
- Modify: `app/Services/Mobile/TokenService.php`
- Test: `tests/Feature/Api/Mobile/TokenRefreshTest.php` (created here)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/Mobile/TokenRefreshTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_reissue_for_current_device_mints_token_with_current_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Old token issued with NO abilities (simulates a stale token).
        $user->createToken('iphone', ['mobile'])->plainTextToken;
        $current = $user->tokens()->first();

        $plain = app(TokenService::class)->reissueForCurrentDevice($user, $current);

        // New token string is returned and carries write:bookings for the owned band.
        $this->assertIsString($plain);
        $newToken = $user->tokens()->latest('id')->first();
        $this->assertContains('write:bookings', $newToken->abilities);
        $this->assertSame('iphone', $newToken->name);

        // Old token is gone.
        $this->assertNull($user->tokens()->find($current->id));
    }

    public function test_reissue_falls_back_to_mobile_device_name_when_current_null(): void
    {
        $user = User::factory()->create();

        $plain = app(TokenService::class)->reissueForCurrentDevice($user, null);

        $this->assertIsString($plain);
        $this->assertSame('mobile', $user->tokens()->latest('id')->first()->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/TokenRefreshTest.php`
Expected: FAIL — `Call to undefined method ...reissueForCurrentDevice()`.

- [ ] **Step 3: Implement the helper**

In `app/Services/Mobile/TokenService.php`, add the import at the top of the file:

```php
use Laravel\Sanctum\PersonalAccessToken;
```

Add this method to the `TokenService` class (after `buildAbilities()`):

```php
/**
 * Re-mint the calling device's token from the user's CURRENT abilities and
 * delete the old one. Returns the new plain-text token.
 *
 * Used by the refresh endpoint and goSolo so a token can't stay stale after
 * the user's bands/roles change. $current is the token being replaced (the
 * caller's currentAccessToken), or null when none is resolvable — in which
 * case we fall back to a generic device name.
 */
public function reissueForCurrentDevice(User $user, ?PersonalAccessToken $current): string
{
    $deviceName = $current?->name ?: 'mobile';
    $abilities  = $this->buildAbilities($user);

    $new = $user->createToken($deviceName, $abilities)->plainTextToken;

    $current?->delete();

    return $new;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/TokenRefreshTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Mobile/TokenService.php tests/Feature/Api/Mobile/TokenRefreshTest.php
git commit -m "feat(mobile): add TokenService::reissueForCurrentDevice helper"
```

---

### Task 2: `POST /api/mobile/token/refresh` endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/AuthController.php`
- Modify: `routes/api.php` (the `auth:sanctum` group near line 71-73)
- Test: `tests/Feature/Api/Mobile/TokenRefreshTest.php`

- [ ] **Step 1: Write the failing tests**

Append these methods to `tests/Feature/Api/Mobile/TokenRefreshTest.php` (add
`use App\Services\Mobile\TokenService;` already present; add
`use App\Models\Bookings;` and `use App\Models\BandMembers;` to the imports):

```php
    public function test_refresh_requires_authentication(): void
    {
        $this->postJson('/api/mobile/token/refresh')->assertUnauthorized();
    }

    public function test_refresh_reissues_token_with_current_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Issue a stale token (mobile only — no write:bookings).
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $response = $this->withToken($stale)
            ->postJson('/api/mobile/token/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'bands']);

        $newToken = $user->tokens()->latest('id')->first();
        $this->assertContains('write:bookings', $newToken->abilities);
        $this->assertSame('iphone', $newToken->name);
    }

    public function test_refresh_does_not_grant_access_to_a_band_the_user_is_not_in(): void
    {
        $user = User::factory()->create();
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        // A band the user has NO relationship with.
        $otherBand = Bands::factory()->create();

        // Refresh the token.
        $newToken = $this->withToken($stale)
            ->postJson('/api/mobile/token/refresh')
            ->assertOk()
            ->json('token');

        // Even refreshed, the user cannot create a booking on a band they're not in.
        $this->withToken($newToken)
            ->withHeaders(['X-Band-ID' => $otherBand->id])
            ->postJson("/api/mobile/bands/{$otherBand->id}/bookings", [
                'name' => 'Sneaky Gig',
                'date' => now()->addDays(5)->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/TokenRefreshTest.php`
Expected: FAIL — `test_refresh_requires_authentication` gets 404 (route missing), refresh tests 404.

- [ ] **Step 3: Add the `refresh` action**

In `app/Http/Controllers/Api/Mobile/AuthController.php`, add this method after `logout()`:

```php
    public function refresh(Request $request): JsonResponse
    {
        $user  = $request->user();
        $token = $this->tokenService->reissueForCurrentDevice(
            $user,
            $user->currentAccessToken(),
        );

        return response()->json([
            'token' => $token,
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, inside the `Route::middleware('auth:sanctum')->group(...)`
block (the one starting near line 71 that holds `/auth/me` and `/auth/token`
logout), add below the logout route:

```php
        Route::post('/token/refresh', [MobileAuthController::class, 'refresh'])->name('mobile.auth.refresh');
```

(`MobileAuthController` is the alias already imported at the top of
`routes/api.php` for `App\Http\Controllers\Api\Mobile\AuthController`. Verify the
alias name by checking the existing `/auth/me` line; reuse whatever it uses.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/TokenRefreshTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/AuthController.php routes/api.php tests/Feature/Api/Mobile/TokenRefreshTest.php
git commit -m "feat(mobile): add POST /token/refresh endpoint"
```

---

### Task 3: `goSolo` returns a fresh token

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/OnboardingController.php` (the
  `goSolo()` method, ~line 230-266)
- Test: `tests/Feature/Api/Mobile/GoSoloTokenTest.php` (created here)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/Mobile/GoSoloTokenTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoSoloTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_solo_returns_token_that_can_create_a_personal_booking(): void
    {
        $user = User::factory()->create();
        // Stale token: user currently owns nothing, so no write:bookings.
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $response = $this->withToken($stale)
            ->postJson('/api/mobile/bands/solo')
            ->assertStatus(201)
            ->assertJsonStructure(['token', 'bands']);

        $newToken  = $response->json('token');
        $personalBandId = collect($response->json('bands'))
            ->firstWhere('is_personal', true)['id'];

        // The freshly returned token can create a booking on the new personal band.
        $this->withToken($newToken)
            ->withHeaders(['X-Band-ID' => $personalBandId])
            ->postJson("/api/mobile/bands/{$personalBandId}/bookings", [
                'name' => 'My Solo Gig',
                'date' => now()->addDays(5)->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_go_solo_idempotent_call_also_returns_a_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iphone', ['mobile'])->plainTextToken;

        // First call creates the personal band (consumes/deletes the token).
        $this->withToken($token)->postJson('/api/mobile/bands/solo')->assertStatus(201);

        // Second call (idempotent path) with a fresh token must STILL return a
        // token + bands, not just bands.
        $callToken = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $this->withToken($callToken)
            ->postJson('/api/mobile/bands/solo')
            ->assertOk()
            ->assertJsonStructure(['token', 'bands']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/GoSoloTokenTest.php`
Expected: FAIL — response has no `token` key; the booking POST gets 403
("Insufficient token permissions") on the stale token.

- [ ] **Step 3: Update `goSolo()` to mint and return a token**

In `app/Http/Controllers/Api/Mobile/OnboardingController.php`, modify `goSolo()`.
Replace the idempotent early-return block:

```php
        if ($existing) {
            return response()->json([
                'bands' => $this->tokenService->formatBands($user),
            ]);
        }
```

with:

```php
        if ($existing) {
            return response()->json([
                'token' => $this->tokenService->reissueForCurrentDevice(
                    $user,
                    $user->currentAccessToken(),
                ),
                'bands' => $this->tokenService->formatBands($user),
            ]);
        }
```

And replace the final return:

```php
        return response()->json([
            'bands' => $this->tokenService->formatBands($user),
        ], 201);
```

with:

```php
        return response()->json([
            'token' => $this->tokenService->reissueForCurrentDevice(
                $user,
                $user->currentAccessToken(),
            ),
            'bands' => $this->tokenService->formatBands($user),
        ], 201);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/Api/Mobile/GoSoloTokenTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full mobile auth/onboarding suite for regressions**

Run: `docker compose exec app php artisan test --filter "Auth|Onboarding|GoSolo|TokenRefresh|TokenService"`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/OnboardingController.php tests/Feature/Api/Mobile/GoSoloTokenTest.php
git commit -m "feat(mobile): goSolo reissues token so personal gigs work immediately"
```

---

## Client (tts_bandmate)

> Switch repos: `cd /home/eddie/github/tts_bandmate`. Create a branch:
> `git checkout -b feat/mobile-token-refresh main`.

### Task 4: API endpoint constant + `refreshToken()` repository method

**Files:**
- Modify: `lib/core/network/api_endpoints.dart`
- Modify: `lib/features/auth/data/auth_repository.dart`
- Test: `test/features/auth/auth_repository_refresh_test.dart` (created here)

- [ ] **Step 1: Write the failing test**

Create `test/features/auth/auth_repository_refresh_test.dart`:

```dart
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http_mock_adapter/http_mock_adapter.dart';
import 'package:tts_bandmate/features/auth/data/auth_repository.dart';
import 'package:tts_bandmate/core/network/api_endpoints.dart';

void main() {
  test('refreshToken posts to refresh endpoint and returns new token', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://localhost'));
    final adapter = DioAdapter(dio: dio);
    adapter.onPost(
      ApiEndpoints.mobileTokenRefresh,
      (server) => server.reply(200, {
        'token': 'new-token-123',
        'user': {'id': 1, 'name': 'Wes', 'email': 'w@example.com', 'avatar_url': null},
        'bands': <dynamic>[],
      }),
    );

    final repo = AuthRepository(dio);
    final result = await repo.refreshToken();

    expect(result.token, 'new-token-123');
  });
}
```

- [ ] **Step 2: Verify `http_mock_adapter` is available**

Run: `cd /home/eddie/github/tts_bandmate && grep http_mock_adapter pubspec.yaml`
Expected: it is listed under dev_dependencies. If MISSING, run
`flutter pub add --dev http_mock_adapter` and commit the pubspec change as a
separate prep commit before continuing. (Check existing tests in `test/` first —
if they use a different HTTP mocking approach, mirror that instead and adapt the
test above to match the established pattern.)

- [ ] **Step 3: Run test to verify it fails**

Run: `flutter test test/features/auth/auth_repository_refresh_test.dart`
Expected: FAIL — `mobileTokenRefresh` undefined / `refreshToken` undefined.

- [ ] **Step 4: Add the endpoint constant**

In `lib/core/network/api_endpoints.dart`, add near the other auth constants
(e.g. below `mobileToken`):

```dart
  static const String mobileTokenRefresh = '/api/mobile/token/refresh';
```

- [ ] **Step 5: Add `refreshToken()` to AuthRepository**

In `lib/features/auth/data/auth_repository.dart`, add this method (mirrors the
shape of `login()`):

```dart
  /// Re-mint the current device's token from the user's current permissions.
  ///
  /// Used after the user's bands/roles change (e.g. going solo) and by the
  /// auto-retry interceptor when a request fails with "Insufficient token
  /// permissions". Returns the new token, user, and bands.
  Future<({String token, AuthUser user, List<BandSummary> bands})>
      refreshToken() async {
    final response = await _dio.post<Map<String, dynamic>>(
      ApiEndpoints.mobileTokenRefresh,
    );

    final data = response.data!;
    final token = data['token'] as String;
    final user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);
    final bandList = (data['bands'] as List<dynamic>)
        .map((b) => BandSummary.fromJson(b as Map<String, dynamic>))
        .toList();

    return (token: token, user: user, bands: bandList);
  }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `flutter test test/features/auth/auth_repository_refresh_test.dart`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add lib/core/network/api_endpoints.dart lib/features/auth/data/auth_repository.dart test/features/auth/auth_repository_refresh_test.dart
git commit -m "feat: add refreshToken endpoint + repository method"
```

---

### Task 5: `goSolo()` persists the returned token

**Files:**
- Modify: `lib/features/bands/data/bands_repository.dart` (the `goSolo()` method,
  ~line 41-50)
- Test: `test/features/bands/bands_repository_solo_test.dart` (created here)

- [ ] **Step 1: Inspect how BandsRepository is constructed**

Run: `cd /home/eddie/github/tts_bandmate && sed -n '1,40p' lib/features/bands/data/bands_repository.dart`
Note whether the repo has access to `SecureStorage`. If it only has a `Dio`,
this task adds a `SecureStorage` dependency to its constructor and updates its
Riverpod provider to inject `secureStorageProvider`. Record the constructor
signature you find; the steps below assume you add `SecureStorage _storage`.

- [ ] **Step 2: Write the failing test**

Create `test/features/bands/bands_repository_solo_test.dart`. Use a fake
`SecureStorage` (mirror the `FakeSecureStorage` pattern already used in the
existing test suite — search `test/` for `FakeSecureStorage`; if one exists,
import and reuse it rather than redefining):

```dart
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http_mock_adapter/http_mock_adapter.dart';
import 'package:tts_bandmate/core/network/api_endpoints.dart';
import 'package:tts_bandmate/features/bands/data/bands_repository.dart';
// import the shared FakeSecureStorage used elsewhere in test/.

void main() {
  test('goSolo writes the returned token to storage', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://localhost'));
    final adapter = DioAdapter(dio: dio);
    final storage = FakeSecureStorage();

    adapter.onPost(
      ApiEndpoints.mobileBandsSolo,
      (server) => server.reply(201, {
        'token': 'solo-token-xyz',
        'bands': [
          {'id': 7, 'name': "Wes's Band", 'is_owner': true, 'is_personal': true, 'logo_url': null},
        ],
      }),
    );

    final repo = BandsRepository(dio, storage);
    final bands = await repo.goSolo();

    expect(await storage.readToken(), 'solo-token-xyz');
    expect(bands.single.id, 7);
  });
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `flutter test test/features/bands/bands_repository_solo_test.dart`
Expected: FAIL — constructor arity mismatch / token not written.

- [ ] **Step 4: Update `goSolo()` to persist the token**

In `lib/features/bands/data/bands_repository.dart`:

1. Add the storage dependency to the constructor (adapt to the real signature
   found in Step 1). Example:

```dart
  BandsRepository(this._dio, this._storage);

  final Dio _dio;
  final SecureStorage _storage;
```

   Add the import: `import '../../../core/storage/secure_storage.dart';`

2. Update `goSolo()` to read and persist the token (the response now includes
   one; guard for null to stay backward-compatible):

```dart
  /// Create a personal auto-band. Persists the freshly-issued token (which now
  /// carries write:bookings for the new personal band) and returns the updated
  /// bands list.
  Future<List<BandSummary>> goSolo() async {
    final response = await _dio.post<Map<String, dynamic>>(
      ApiEndpoints.mobileBandsSolo,
    );

    final data = response.data!;
    final token = data['token'] as String?;
    if (token != null) {
      await _storage.writeToken(token);
    }

    final bandList = (data['bands'] as List<dynamic>)
        .map((b) => BandSummary.fromJson(b as Map<String, dynamic>))
        .toList();
    return bandList;
  }
```

3. Update the `BandsRepository` Riverpod provider (in this same file or wherever
   it is declared — search `bandsRepositoryProvider`) to pass storage:

```dart
final bandsRepositoryProvider = Provider<BandsRepository>((ref) {
  final dio = ref.watch(apiClientProvider).dio;
  final storage = ref.watch(secureStorageProvider);
  return BandsRepository(dio, storage);
});
```

   Ensure imports for `apiClientProvider` and `secureStorageProvider` are present
   (they may already be).

- [ ] **Step 5: Run test to verify it passes**

Run: `flutter test test/features/bands/bands_repository_solo_test.dart`
Expected: PASS.

- [ ] **Step 6: Run analyzer**

Run: `flutter analyze lib/features/bands lib/core`
Expected: no new errors.

- [ ] **Step 7: Commit**

```bash
git add lib/features/bands/data/bands_repository.dart test/features/bands/bands_repository_solo_test.dart
git commit -m "feat: persist token returned by goSolo"
```

---

### Task 6: Refresh + retry interceptor on the specific 403

**Files:**
- Modify: `lib/core/network/api_client.dart` (the `_buildDio()` interceptor)
- Test: `test/core/network/api_client_retry_test.dart` (created here)

**Design note:** The existing `onRequest` interceptor reads the token from
storage on every request, so after a refresh writes a new token, a re-fired
request automatically carries it. The retry path therefore: detect the specific
403 → call refresh (writes new token to storage) → re-fire the original request
via a fresh Dio → resolve with its response. Guard against loops with a per-request
flag and by never refreshing for the refresh call itself.

- [ ] **Step 1: Write the failing test**

Create `test/core/network/api_client_retry_test.dart`:

```dart
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http_mock_adapter/http_mock_adapter.dart';
import 'package:tts_bandmate/core/network/api_client.dart';
import 'package:tts_bandmate/core/network/api_endpoints.dart';
// import the shared FakeSecureStorage.

void main() {
  test('refreshes once and retries once on Insufficient token permissions 403',
      () async {
    final storage = FakeSecureStorage();
    await storage.writeToken('stale-token');

    final client = ApiClient(storage: storage);
    final adapter = DioAdapter(dio: client.dio);

    var attempts = 0;
    adapter.onGet('/protected', (server) {
      attempts++;
      if (attempts == 1) {
        server.reply(403, {'message': 'Insufficient token permissions.'});
      } else {
        server.reply(200, {'ok': true});
      }
    });
    adapter.onPost(
      ApiEndpoints.mobileTokenRefresh,
      (server) => server.reply(200, {
        'token': 'fresh-token',
        'user': {'id': 1, 'name': 'W', 'email': 'w@e.com', 'avatar_url': null},
        'bands': <dynamic>[],
      }),
    );

    final res = await client.dio.get<Map<String, dynamic>>('/protected');

    expect(res.statusCode, 200);
    expect(await storage.readToken(), 'fresh-token');
    expect(attempts, 2); // original + one retry
  });

  test('does not refresh on other 403 messages', () async {
    final storage = FakeSecureStorage();
    await storage.writeToken('stale-token');
    final client = ApiClient(storage: storage);
    final adapter = DioAdapter(dio: client.dio);

    var refreshCalled = false;
    adapter.onGet('/protected',
        (server) => server.reply(403, {'message': 'You are not a member of this band.'}));
    adapter.onPost(ApiEndpoints.mobileTokenRefresh, (server) {
      refreshCalled = true;
      server.reply(200, {'token': 'x', 'user': {}, 'bands': <dynamic>[]});
    });

    await expectLater(
      client.dio.get<Map<String, dynamic>>('/protected'),
      throwsA(isA<DioException>()),
    );
    expect(refreshCalled, isFalse);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/core/network/api_client_retry_test.dart`
Expected: FAIL — first test throws instead of retrying; storage keeps stale token.

- [ ] **Step 3: Implement the retry in `_buildDio()`**

In `lib/core/network/api_client.dart`, replace the `onError` handler with:

```dart
        onError: (error, handler) async {
          final status = error.response?.statusCode;

          if (status == 401) {
            await _storage.deleteToken();
            await _storage.deleteBandId();
            _onUnauthorized?.call();
            handler.next(error);
            return;
          }

          // Reactively refresh a stale token: the EnsureUserInBand middleware
          // returns this exact message when the token lacks an ability the user
          // actually has (e.g. after going solo). Refresh once, retry once.
          final isStaleTokenError = status == 403 &&
              (error.response?.data is Map) &&
              (error.response?.data as Map)['message'] ==
                  'Insufficient token permissions.';

          final req = error.requestOptions;
          final alreadyRetried = req.extra['__retried_after_refresh'] == true;
          final isRefreshCall = req.path == ApiEndpoints.mobileTokenRefresh;

          if (isStaleTokenError && !alreadyRetried && !isRefreshCall) {
            try {
              final refreshed = await _dio.post<Map<String, dynamic>>(
                ApiEndpoints.mobileTokenRefresh,
              );
              final newToken = refreshed.data?['token'] as String?;
              if (newToken != null) {
                await _storage.writeToken(newToken);

                // Re-fire the original request. onRequest will attach the new
                // token from storage. Mark it so a second 403 won't loop.
                final retryOptions = req.copyWith(
                  extra: {...req.extra, '__retried_after_refresh': true},
                );
                final retryResponse = await _dio.fetch<dynamic>(retryOptions);
                handler.resolve(retryResponse);
                return;
              }
            } catch (_) {
              // Fall through to surfacing the original error.
            }
          }

          handler.next(error);
        },
```

Add the import at the top if not present:
`import 'api_endpoints.dart';`

- [ ] **Step 4: Run test to verify it passes**

Run: `flutter test test/core/network/api_client_retry_test.dart`
Expected: PASS (2 tests).

- [ ] **Step 5: Run analyzer + full test suite**

Run: `flutter analyze lib/core/network && flutter test`
Expected: no new analyzer errors; all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add lib/core/network/api_client.dart test/core/network/api_client_retry_test.dart
git commit -m "feat: refresh token and retry once on stale-token 403"
```

---

## Wrap-up

### Task 7: Full suites + PRs

- [ ] **Step 1: Backend full suite**

Run (in TTS): `docker compose exec app php artisan test --filter "Auth|Onboarding|GoSolo|Token|Booking|Sub|Dashboard|Event"`
Expected: all PASS.

- [ ] **Step 2: Client full suite + analyze**

Run (in tts_bandmate): `flutter test && flutter analyze`
Expected: all PASS, no new analyzer issues.

- [ ] **Step 3: Push and open PRs**

```bash
# TTS
cd /home/eddie/github/TTS
git push -u origin feat/mobile-token-refresh
gh pr create --base staging --title "feat(mobile): token refresh to fix stale abilities" --body "<summary + test plan>"

# tts_bandmate
cd /home/eddie/github/tts_bandmate
git push -u origin feat/mobile-token-refresh
gh pr create --base main --title "feat: refresh stale token on goSolo and on 'Insufficient token permissions' 403" --body "<summary + test plan>"
```

Note: TTS PRs target `staging`; tts_bandmate targets `main`.
