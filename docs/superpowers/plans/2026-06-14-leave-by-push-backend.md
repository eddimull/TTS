# Leave-By Push Notification Backend — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register mobile device tokens and, on a 5-minute scheduler tick, send two data-only FCM push notifications per rostered event per day (an 8h-before reminder and a departure trigger), at times computed in the venue's timezone, idempotently.

**Architecture:** A Laravel 12 subsystem: `device_tokens` + `push_notification_log` tables, a `DevicesController` for the two mobile endpoints, `kreait/laravel-firebase` behind an `FcmSender`, a `VenueTimezoneResolver` (reusing the configured googlemaps package), and a `LeaveByPushService` orchestrator invoked by a `notifications:tick` command. First-item/show-time are derived in the service using the SAME `additional_data->times` shape and `strtotime` sort that `EventDataService::parseAdditionalData` uses (deliberately kept in lockstep — see Self-Review).

**Tech Stack:** Laravel 12, PHP 8.3, Sanctum, Horizon/queues, `kreait/laravel-firebase`, `alexpechkarev/google-maps` (already configured), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-06-14-leave-by-push-backend-design.md`

**Conventions:** All artisan/composer/phpunit run via `docker compose exec app …`. Branch is off `staging`; PR targets `staging`. `Events` model is plural (`App\Models\Events`).

---

## Prerequisites (manual, external — not code steps)

- [ ] **P1: Firebase service account JSON** — Firebase console → Project Settings → Service accounts → Generate new private key (same Firebase project as the mobile Phase 1 setup). Place the JSON where the server can read it; set `FIREBASE_CREDENTIALS` in `.env` to its path. Keep it out of git.
- [ ] **P2: Enable the Google Time Zone API** on the project owning `GOOGLE_MAPS_API_KEY` (the timezone endpoint is already declared in `config/googlemaps.php`).
- [ ] **P3: Confirm Horizon / queue workers** run in the target environment so dispatched send jobs are processed.

---

## File Structure

New:
- `database/migrations/<ts>_create_device_tokens_table.php`
- `database/migrations/<ts>_create_push_notification_log_table.php`
- `database/migrations/<ts>_add_venue_timezone_to_events_table.php`
- `app/Models/DeviceToken.php`
- `app/Models/PushNotificationLog.php`
- `app/Http/Controllers/Api/Mobile/DevicesController.php`
- `app/Http/Requests/Mobile/StoreDeviceTokenRequest.php`
- `app/Services/Push/VenueTimezoneResolver.php`
- `app/Services/Push/FcmSender.php`
- `app/Services/Push/LeaveByPushService.php`
- `app/Jobs/SendEventPush.php`
- `app/Console/Commands/SendLeaveByNotifications.php`
- `database/factories/DeviceTokenFactory.php`
- Tests under `tests/Feature/Push/` and `tests/Unit/Push/`

Modified:
- `routes/api.php` — two device routes in the `auth:sanctum` mobile group
- `app/Console/Kernel.php` — schedule `notifications:tick` every 5 min, `onOneServer()`
- `composer.json` — `kreait/laravel-firebase`
- `config/firebase.php` — published by the package

Reused (NOT modified): `app/Services/EventDataService.php` (timeline/first-item via existing `formatForShow`/`parseAdditionalData`), `app/Models/EventMember.php`, `app/Models/Events.php`.

---

## Task 1: Install kreait/laravel-firebase

**Files:** `composer.json`, `config/firebase.php`

- [ ] **Step 1: Require the package**

Run: `docker compose exec app composer require kreait/laravel-firebase`
Expected: installs, `composer.json` shows `kreait/laravel-firebase`.

- [ ] **Step 2: Publish config**

Run: `docker compose exec app php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" --tag=config`
Expected: `config/firebase.php` created.

- [ ] **Step 3: Add the credentials env key**

Add to `.env` and `.env.example`:
```
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
```
(The actual file is provided manually per prerequisite P1; gitignore `storage/app/firebase/`.)

Add to `.gitignore`:
```
/storage/app/firebase/
```

- [ ] **Step 4: Verify it boots**

Run: `docker compose exec app php artisan config:clear && docker compose exec app php artisan about`
Expected: no errors (the Firebase provider registers).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/firebase.php .env.example .gitignore
git commit -m "build: add kreait/laravel-firebase for FCM"
```

---

## Task 2: device_tokens migration + model + factory

**Files:**
- Create: `database/migrations/<ts>_create_device_tokens_table.php`
- Create: `app/Models/DeviceToken.php`
- Create: `database/factories/DeviceTokenFactory.php`
- Test: `tests/Unit/Push/DeviceTokenTest.php`

- [ ] **Step 1: Create the migration**

Run: `docker compose exec app php artisan make:migration create_device_tokens_table`
Then set its contents:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 512)->unique();
            $table->enum('platform', ['ios', 'android']);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
```

- [ ] **Step 2: Create the model**

`app/Models/DeviceToken.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'token', 'platform'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create the factory**

`database/factories/DeviceTokenFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'token'    => $this->faker->unique()->sha256(),
            'platform' => $this->faker->randomElement(['ios', 'android']),
        ];
    }
}
```

- [ ] **Step 4: Write the test**

`tests/Unit/Push/DeviceTokenTest.php`:
```php
<?php

namespace Tests\Unit\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($token->user->is($user));
    }

    public function test_token_is_unique(): void
    {
        DeviceToken::factory()->create(['token' => 'abc']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DeviceToken::factory()->create(['token' => 'abc']);
    }
}
```

- [ ] **Step 5: Run migration + test**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Unit/Push/DeviceTokenTest.php`
Expected: 2 passing.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*device_tokens* app/Models/DeviceToken.php database/factories/DeviceTokenFactory.php tests/Unit/Push/DeviceTokenTest.php
git commit -m "feat(push): device_tokens table, model, factory"
```

---

## Task 3: push_notification_log migration + model

**Files:**
- Create: `database/migrations/<ts>_create_push_notification_log_table.php`
- Create: `app/Models/PushNotificationLog.php`
- Test: `tests/Unit/Push/PushNotificationLogTest.php`

- [ ] **Step 1: Migration**

Run: `docker compose exec app php artisan make:migration create_push_notification_log_table`
Contents:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32); // event_reminder_8h | event_departure
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->unique(['event_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_log');
    }
};
```

- [ ] **Step 2: Model**

`app/Models/PushNotificationLog.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationLog extends Model
{
    protected $table = 'push_notification_log';

    protected $fillable = ['event_id', 'user_id', 'type', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Test the unique constraint**

`tests/Unit/Push/PushNotificationLogTest.php`:
```php
<?php

namespace Tests\Unit\Push;

use App\Models\PushNotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_user_type_is_unique(): void
    {
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
    }

    public function test_same_event_user_different_type_allowed(): void
    {
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_departure']);
        $this->assertSame(2, PushNotificationLog::count());
    }
}
```

- [ ] **Step 4: Run**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Unit/Push/PushNotificationLogTest.php`
Expected: 2 passing.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*push_notification_log* app/Models/PushNotificationLog.php tests/Unit/Push/PushNotificationLogTest.php
git commit -m "feat(push): push_notification_log table + model (idempotency ledger)"
```

---

## Task 4: add venue_timezone column to events

**Files:**
- Create: `database/migrations/<ts>_add_venue_timezone_to_events_table.php`

- [ ] **Step 1: Migration**

Run: `docker compose exec app php artisan make:migration add_venue_timezone_to_events_table`
Contents:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('venue_timezone', 64)->nullable()->after('venue_address');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('venue_timezone');
        });
    }
};
```

- [ ] **Step 2: Add to Events fillable**

In `app/Models/Events.php`, add `'venue_timezone'` to the `$fillable` array (next to `venue_address`).

- [ ] **Step 3: Run migration**

Run: `docker compose exec app php artisan migrate`
Expected: column added.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*venue_timezone* app/Models/Events.php
git commit -m "feat(push): cache venue_timezone on events"
```

---

## Task 5: Device registration endpoints

**Files:**
- Create: `app/Http/Requests/Mobile/StoreDeviceTokenRequest.php`
- Create: `app/Http/Controllers/Api/Mobile/DevicesController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Push/DeviceRegistrationTest.php`

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/Push/DeviceRegistrationTest.php`:
```php
<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_auth(): void
    {
        $this->postJson('/api/mobile/devices', ['token' => 't', 'platform' => 'ios'])
            ->assertUnauthorized();
    }

    public function test_register_creates_token_for_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'ios'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id, 'token' => 'tok-abc', 'platform' => 'ios',
        ]);
    }

    public function test_register_is_idempotent_by_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'ios'])->assertOk();
        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'android'])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'tok-abc')->count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'tok-abc', 'platform' => 'android']);
    }

    public function test_validation_rejects_bad_platform(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/devices', ['token' => 't', 'platform' => 'windows'])
            ->assertStatus(422);
    }

    public function test_destroy_removes_only_callers_token(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $other->id, 'token' => 'theirs', 'platform' => 'ios']);
        DeviceToken::factory()->create(['user_id' => $me->id, 'token' => 'mine', 'platform' => 'ios']);

        Sanctum::actingAs($me);
        $this->deleteJson('/api/mobile/devices/mine')->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'mine']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'theirs']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Push/DeviceRegistrationTest.php`
Expected: FAIL (route/controller missing).

- [ ] **Step 3: FormRequest**

`app/Http/Requests/Mobile/StoreDeviceTokenRequest.php`:
```php
<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum
    }

    public function rules(): array
    {
        return [
            'token'    => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:ios,android'],
        ];
    }
}
```

- [ ] **Step 4: Controller**

`app/Http/Controllers/Api/Mobile/DevicesController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'user_id'  => $request->user()->id,
                'platform' => $request->string('platform')->toString(),
            ],
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 5: Routes**

In `routes/api.php`, inside the `auth:sanctum` mobile group (where `mobile.events.show` is declared), add:
```php
    Route::post('/devices', [App\Http\Controllers\Api\Mobile\DevicesController::class, 'store'])->name('mobile.devices.store');
    Route::delete('/devices/{token}', [App\Http\Controllers\Api\Mobile\DevicesController::class, 'destroy'])->name('mobile.devices.destroy');
```
NOTE: match the existing group's path style. The mobile group is prefixed so routes resolve to `/api/mobile/...`. Verify the resulting paths are exactly `/api/mobile/devices` and `/api/mobile/devices/{token}` (the test asserts these). If the group prefix differs, adjust the route path accordingly so the final URL matches.

- [ ] **Step 6: Run to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/Push/DeviceRegistrationTest.php`
Expected: 5 passing.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Mobile/StoreDeviceTokenRequest.php app/Http/Controllers/Api/Mobile/DevicesController.php routes/api.php tests/Feature/Push/DeviceRegistrationTest.php
git commit -m "feat(push): device registration endpoints"
```

---

## Task 6: VenueTimezoneResolver

Resolves an event's IANA timezone from its venue address, caching on `events.venue_timezone`. Uses the configured `alexpechkarev/google-maps` package (geocode → timezone), wrapped so tests fake it.

**Files:**
- Create: `app/Services/Push/VenueTimezoneResolver.php`
- Test: `tests/Unit/Push/VenueTimezoneResolverTest.php`

- [ ] **Step 1: Write the failing test (geocoder faked via a closure seam)**

`tests/Unit/Push/VenueTimezoneResolverTest.php`:
```php
<?php

namespace Tests\Unit\Push;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Services\Push\VenueTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueTimezoneResolverTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithAddress(?string $address): Events
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'venue_address'  => $address,
        ]);
    }

    public function test_returns_cached_timezone_without_calling_lookup(): void
    {
        $event = $this->eventWithAddress('100 Main St');
        $event->venue_timezone = 'America/Chicago';
        $event->save();

        $resolver = new VenueTimezoneResolver(fn () => $this->fail('should not look up'));

        $this->assertSame('America/Chicago', $resolver->forEvent($event));
    }

    public function test_looks_up_caches_and_returns(): void
    {
        $event = $this->eventWithAddress('100 Main St, Austin TX');
        $resolver = new VenueTimezoneResolver(fn (string $addr) => 'America/Chicago');

        $this->assertSame('America/Chicago', $resolver->forEvent($event));
        $this->assertSame('America/Chicago', $event->fresh()->venue_timezone);
    }

    public function test_falls_back_to_app_tz_without_caching_on_failure(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $event = $this->eventWithAddress('nowhere');
        $resolver = new VenueTimezoneResolver(fn (string $addr) => null);

        $this->assertSame('America/New_York', $resolver->forEvent($event));
        $this->assertNull($event->fresh()->venue_timezone);
    }

    public function test_no_address_uses_app_tz(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $event = $this->eventWithAddress(null);
        $resolver = new VenueTimezoneResolver(fn (string $addr) => $this->fail('no lookup for empty address'));

        $this->assertSame('America/New_York', $resolver->forEvent($event));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/Push/VenueTimezoneResolverTest.php`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement**

`app/Services/Push/VenueTimezoneResolver.php`:
```php
<?php

namespace App\Services\Push;

use App\Models\Events;
use Closure;
use Illuminate\Support\Facades\Log;

class VenueTimezoneResolver
{
    /** @var Closure(string): ?string  Address → IANA tz (null on failure). */
    private Closure $lookup;

    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? fn (string $address) => $this->lookupViaGoogle($address);
    }

    public function forEvent(Events $event): string
    {
        if (!empty($event->venue_timezone)) {
            return $event->venue_timezone;
        }

        $address = $event->resolved_venue_address;
        if (empty($address)) {
            return config('app.timezone');
        }

        $tz = ($this->lookup)($address);
        if ($tz === null) {
            Log::warning('VenueTimezoneResolver: lookup failed, using app tz', [
                'event_id' => $event->id,
            ]);
            return config('app.timezone'); // do not cache the fallback
        }

        $event->venue_timezone = $tz;
        $event->save();
        return $tz;
    }

    private function lookupViaGoogle(string $address): ?string
    {
        try {
            $maps = app('GoogleMaps'); // alexpechkarev/google-maps facade/binding
            $geo = $maps->load('geocoding')
                ->setParam(['address' => $address])
                ->get();
            $geo = json_decode($geo, true);
            $loc = $geo['results'][0]['geometry']['location'] ?? null;
            if (!$loc) {
                return null;
            }

            $tzResp = $maps->load('timezone')
                ->setParam([
                    'location'  => "{$loc['lat']},{$loc['lng']}",
                    'timestamp' => now()->timestamp,
                ])
                ->get();
            $tzResp = json_decode($tzResp, true);

            return $tzResp['timeZoneId'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('VenueTimezoneResolver: google lookup threw', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
```
NOTE: the `lookupViaGoogle` body uses the `alexpechkarev/google-maps` API as configured in `config/googlemaps.php`. If the package's facade name or call style differs in this repo, adjust ONLY `lookupViaGoogle` to match — the constructor seam and `forEvent` (which the tests cover) must not change. Verify against the package's existing usage in `LocationController::geocodeAddress`.

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec app php artisan test tests/Unit/Push/VenueTimezoneResolverTest.php`
Expected: 4 passing. (The Google path is not unit-tested; it's exercised only via the injected closure.)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Push/VenueTimezoneResolver.php tests/Unit/Push/VenueTimezoneResolverTest.php
git commit -m "feat(push): venue timezone resolver (cached, app-tz fallback)"
```

---

## Task 7: FcmSender

Wraps `kreait` to send one data-only message; returns a result enum-like string. Not unit-tested against live FCM; the orchestrator test fakes it.

**Files:**
- Create: `app/Services/Push/FcmSender.php`

- [ ] **Step 1: Implement**

`app/Services/Push/FcmSender.php`:
```php
<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

class FcmSender
{
    public const DELIVERED = 'delivered';
    public const PRUNE = 'prune';      // token is dead; caller should delete it
    public const TRANSIENT = 'transient';

    public function __construct(private Messaging $messaging) {}

    /**
     * Send a data-only message to one token.
     * @param array<string,string> $data
     */
    public function sendData(string $token, array $data): string
    {
        try {
            $message = CloudMessage::withTarget('token', $token)->withData($data);
            $this->messaging->send($message);
            return self::DELIVERED;
        } catch (NotFound $e) {
            // Unregistered / invalid token.
            return self::PRUNE;
        } catch (MessagingException $e) {
            Log::warning('FcmSender transient error', ['error' => $e->getMessage()]);
            return self::TRANSIENT;
        }
    }
}
```
NOTE: `Messaging` is resolved from the container by `kreait/laravel-firebase`. Confirm the exact exception class for an unregistered token in the installed kreait version — it is `Kreait\Firebase\Exception\Messaging\NotFound` for HTTP 404 (token not found). If the version differs, adjust the catch to the equivalent "token invalid/unregistered" exception; keep the three return constants.

- [ ] **Step 2: Verify it loads (no test — thin glue)**

Run: `docker compose exec app php artisan tinker --execute="echo class_exists(App\Services\Push\FcmSender::class) ? 'ok' : 'missing';"`
Expected: `ok`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Push/FcmSender.php
git commit -m "feat(push): FcmSender data-only wrapper over kreait"
```

---

## Task 8: SendEventPush job

A queued job that builds the data-only payload for one (event, user, type) and sends to all that user's tokens, logging on success and pruning dead tokens.

**Files:**
- Create: `app/Jobs/SendEventPush.php`
- Test: `tests/Feature/Push/SendEventPushTest.php`

- [ ] **Step 1: Write the failing test (FcmSender faked via container bind)**

`tests/Feature/Push/SendEventPushTest.php`:
```php
<?php

namespace Tests\Feature\Push;

use App\Jobs\SendEventPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\Events;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class _FakeFcm extends FcmSender
{
    public array $sent = [];
    public string $result = FcmSender::DELIVERED;
    public function __construct() {}
    public function sendData(string $token, array $data): string
    {
        $this->sent[] = ['token' => $token, 'data' => $data];
        return $this->result;
    }
}

class SendEventPushTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Events
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'title'          => 'Gig',
            'venue_address'  => '100 Main St',
        ]);
    }

    public function test_sends_data_only_payload_to_each_token_and_logs(): void
    {
        $fake = new _FakeFcm();
        $this->app->instance(FcmSender::class, $fake);

        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'a', 'platform' => 'ios']);
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'b', 'platform' => 'android']);
        $event = $this->event();

        (new SendEventPush(
            eventId: $event->id,
            userId: $user->id,
            type: 'event_reminder_8h',
            payload: [
                'type' => 'event_reminder_8h',
                'eventKey' => $event->key,
                'title' => 'Gig',
                'venueAddress' => '100 Main St',
                'firstItemTitle' => 'Load In',
                'firstItemTime' => '2026-06-14T14:00:00-05:00',
                'showTime' => '2026-06-14T19:00:00-05:00',
            ],
        ))->handle($fake);

        $this->assertCount(2, $fake->sent);
        $this->assertSame('event_reminder_8h', $fake->sent[0]['data']['type']);
        $this->assertSame($event->key, $fake->sent[0]['data']['eventKey']);
        $this->assertDatabaseHas('push_notification_log', [
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_reminder_8h',
        ]);
    }

    public function test_prunes_dead_tokens(): void
    {
        $fake = new _FakeFcm();
        $fake->result = FcmSender::PRUNE;
        $this->app->instance(FcmSender::class, $fake);

        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'dead', 'platform' => 'ios']);
        $event = $this->event();

        (new SendEventPush($event->id, $user->id, 'event_departure', [
            'type' => 'event_departure', 'eventKey' => $event->key, 'title' => 'Gig',
        ]))->handle($fake);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead']);
        // Dead-token send is not a success: no log row.
        $this->assertDatabaseMissing('push_notification_log', [
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_departure',
        ]);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Push/SendEventPushTest.php`
Expected: FAIL (job missing).

- [ ] **Step 3: Implement**

`app/Jobs/SendEventPush.php`:
```php
<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Services\Push\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEventPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string,string> $payload
     */
    public function __construct(
        public int $eventId,
        public int $userId,
        public string $type,
        public array $payload,
    ) {}

    public function handle(FcmSender $fcm): void
    {
        $tokens = DeviceToken::where('user_id', $this->userId)->get();
        $anyDelivered = false;

        foreach ($tokens as $deviceToken) {
            $result = $fcm->sendData($deviceToken->token, $this->payload);
            if ($result === FcmSender::PRUNE) {
                $deviceToken->delete();
            } elseif ($result === FcmSender::DELIVERED) {
                $anyDelivered = true;
            }
        }

        if ($anyDelivered) {
            PushNotificationLog::firstOrCreate(
                ['event_id' => $this->eventId, 'user_id' => $this->userId, 'type' => $this->type],
                ['sent_at' => now()],
            );
        }
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/Push/SendEventPushTest.php`
Expected: 2 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/SendEventPush.php tests/Feature/Push/SendEventPushTest.php
git commit -m "feat(push): SendEventPush job (per-user send, log, prune)"
```

---

## Task 9: LeaveByPushService (orchestrator)

Selects today's rostered events, computes the two send-times per event in venue tz, and dispatches `SendEventPush` for due, not-yet-logged (event,user,type) tuples.

**Files:**
- Create: `app/Services/Push/LeaveByPushService.php`
- Test: `tests/Feature/Push/LeaveByPushServiceTest.php`

- [ ] **Step 1: Write the failing test (time frozen, jobs faked)**

`tests/Feature/Push/LeaveByPushServiceTest.php`:
```php
<?php

namespace Tests\Feature\Push;

use App\Jobs\SendEventPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\LeaveByPushService;
use App\Services\Push\VenueTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeaveByPushServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeRosteredEvent(string $date, string $startTime, string $firstItemTime): array
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'date'           => $date,
            'start_time'     => $startTime,
            'title'          => 'Gig',
            'venue_address'  => '100 Main St',
            'venue_timezone' => 'America/Chicago',
            'additional_data'=> ['times' => [['title' => 'Load In', 'time' => $firstItemTime]]],
        ]);
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'platform' => 'ios']);
        EventMember::create([
            'event_id' => $event->id, 'band_id' => $band->id, 'user_id' => $user->id,
            'attendance_status' => 'confirmed',
        ]);
        return [$event, $user];
    }

    private function service(): LeaveByPushService
    {
        // Resolver returns the cached tz; no Google calls.
        return $this->app->make(LeaveByPushService::class);
    }

    public function test_dispatches_8h_reminder_in_its_window(): void
    {
        Queue::fake();
        // Chicago first item 14:00 → 8h-before = 06:00 Chicago = 11:00 UTC.
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->service()->run(Carbon::now());

        Queue::assertPushed(SendEventPush::class, function ($job) use ($event, $user) {
            return $job->type === 'event_reminder_8h'
                && $job->eventId === $event->id
                && $job->userId === $user->id
                && $job->payload['type'] === 'event_reminder_8h'
                && $job->payload['firstItemTitle'] === 'Load In';
        });
    }

    public function test_does_not_dispatch_when_already_logged(): void
    {
        Queue::fake();
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        PushNotificationLog::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_reminder_8h',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->service()->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class, fn ($j) => $j->type === 'event_reminder_8h');
    }

    public function test_excludes_absent_members(): void
    {
        Queue::fake();
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        EventMember::where('event_id', $event->id)->update(['attendance_status' => 'absent']);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->service()->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class);
    }

    public function test_nothing_dispatched_outside_windows(): void
    {
        Queue::fake();
        $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        // Middle of the night, far from any send window.
        Carbon::setTestNow(Carbon::parse('2026-06-14 02:00:00', 'UTC'));

        $this->service()->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Push/LeaveByPushServiceTest.php`
Expected: FAIL (service missing).

- [ ] **Step 3: Implement**

`app/Services/Push/LeaveByPushService.php`:
```php
<?php

namespace App\Services\Push;

use App\Jobs\SendEventPush;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\PushNotificationLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class LeaveByPushService
{
    public const DEPARTURE_LEAD_MINUTES = 90;
    public const GRACE_WINDOW_MINUTES = 30;

    public function __construct(private VenueTimezoneResolver $tzResolver) {}

    public function run(CarbonInterface $now): void
    {
        foreach ($this->todaysEventsWithRoster($now) as $event) {
            try {
                $this->processEvent($event, $now);
            } catch (\Throwable $e) {
                Log::error('LeaveByPush: event failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** @return iterable<Events> */
    private function todaysEventsWithRoster(CarbonInterface $now): iterable
    {
        // Generous window so venue-tz dates near midnight aren't missed.
        $from = $now->copy()->subDay()->toDateString();
        $to = $now->copy()->addDay()->toDateString();

        return Events::query()
            ->whereBetween('date', [$from, $to])
            ->whereHas('eventMembers', fn ($q) =>
                $q->whereNotIn('attendance_status', ['absent', 'excused'])->whereNull('deleted_at'))
            ->get();
    }

    private function processEvent(Events $event, CarbonInterface $now): void
    {
        $tz = $this->tzResolver->forEvent($event);
        $firstItem = $this->firstTimelineItem($event);

        // firstItemDateTime in venue tz
        $firstTime = $firstItem['time'] ?? $event->start_time; // 'HH:MM' or full
        $firstItemDt = $this->combine($event->date, $firstTime, $tz);
        if ($firstItemDt === null) {
            return;
        }

        $sends = [
            'event_reminder_8h' => $firstItemDt->copy()->subHours(8),
            'event_departure'   => $firstItemDt->copy()->subMinutes(self::DEPARTURE_LEAD_MINUTES),
        ];

        foreach ($sends as $type => $sendAt) {
            if (!$this->isDue($sendAt, $now)) {
                continue;
            }
            $this->dispatchForRecipients($event, $type, $firstItem, $tz, $firstItemDt);
        }
    }

    private function isDue(CarbonInterface $sendAt, CarbonInterface $now): bool
    {
        return $now->greaterThanOrEqualTo($sendAt)
            && $now->lessThan($sendAt->copy()->addMinutes(self::GRACE_WINDOW_MINUTES));
    }

    private function dispatchForRecipients(Events $event, string $type, ?array $firstItem, string $tz, CarbonInterface $firstItemDt): void
    {
        $members = EventMember::where('event_id', $event->id)
            ->whereNotIn('attendance_status', ['absent', 'excused'])
            ->whereNull('deleted_at')
            ->whereHas('user.deviceTokens')
            ->with('user')
            ->get();

        foreach ($members as $member) {
            $already = PushNotificationLog::where('event_id', $event->id)
                ->where('user_id', $member->user_id)
                ->where('type', $type)
                ->exists();
            if ($already) {
                continue;
            }

            SendEventPush::dispatch(
                $event->id,
                $member->user_id,
                $type,
                $this->payload($event, $type, $firstItem, $tz, $firstItemDt),
            );
        }
    }

    /** @return array<string,string> */
    private function payload(Events $event, string $type, ?array $firstItem, string $tz, CarbonInterface $firstItemDt): array
    {
        $data = [
            'type'     => $type,
            'eventKey' => (string) $event->key,
            'title'    => (string) $event->title,
        ];
        if (!empty($event->resolved_venue_address)) {
            $data['venueAddress'] = (string) $event->resolved_venue_address;
        }
        if ($firstItem) {
            $data['firstItemTitle'] = (string) ($firstItem['title'] ?? '');
            $data['firstItemTime'] = $firstItemDt->toIso8601String();
        }
        $showDt = $this->combine($event->date, $event->start_time, $tz);
        if ($showDt !== null) {
            $data['showTime'] = $showDt->toIso8601String();
        }
        return $data;
    }

    /** Earliest timeline entry from additional_data->times, or null. */
    private function firstTimelineItem(Events $event): ?array
    {
        $ad = $event->additional_data;
        $times = is_object($ad) ? ($ad->times ?? null) : (is_array($ad) ? ($ad['times'] ?? null) : null);
        if (!is_array($times) || $times === []) {
            return null;
        }
        $items = [];
        foreach ($times as $t) {
            $t = (array) $t;
            if (!empty($t['time'])) {
                $items[] = ['title' => $t['title'] ?? '', 'time' => $t['time']];
            }
        }
        if ($items === []) {
            return null;
        }
        usort($items, fn ($a, $b) => strtotime($a['time']) <=> strtotime($b['time']));
        return $items[0];
    }

    /** Combine a date + time string into a Carbon in the given tz, or null. */
    private function combine($date, $time, string $tz): ?CarbonInterface
    {
        if (empty($time)) {
            return null;
        }
        try {
            $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
            // $time may be 'HH:MM' or a full datetime; extract HH:MM if full.
            $timeStr = preg_match('/(\d{1,2}:\d{2})/', (string) $time, $m) ? $m[1] : (string) $time;
            return Carbon::parse("{$dateStr} {$timeStr}", $tz);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
```

- [ ] **Step 4: Add the `deviceTokens` relation to User**

In `app/Models/User.php`, add:
```php
    public function deviceTokens()
    {
        return $this->hasMany(\App\Models\DeviceToken::class);
    }
```

- [ ] **Step 5: Run to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/Push/LeaveByPushServiceTest.php`
Expected: 4 passing.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Push/LeaveByPushService.php app/Models/User.php tests/Feature/Push/LeaveByPushServiceTest.php
git commit -m "feat(push): LeaveByPushService orchestrator (due windows, idempotency, recipients)"
```

---

## Task 10: Console command + scheduler

**Files:**
- Create: `app/Console/Commands/SendLeaveByNotifications.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/Push/SendLeaveByNotificationsCommandTest.php`

- [ ] **Step 1: Command**

`app/Console/Commands/SendLeaveByNotifications.php`:
```php
<?php

namespace App\Console\Commands;

use App\Services\Push\LeaveByPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendLeaveByNotifications extends Command
{
    protected $signature = 'notifications:tick';
    protected $description = 'Send due leave-by push notifications for today\'s rostered events';

    public function handle(LeaveByPushService $service): int
    {
        $service->run(Carbon::now());
        $this->info('Leave-by notification tick complete.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Test the command delegates to the service**

`tests/Feature/Push/SendLeaveByNotificationsCommandTest.php`:
```php
<?php

namespace Tests\Feature\Push;

use App\Services\Push\LeaveByPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendLeaveByNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tick_invokes_service_run(): void
    {
        $mock = Mockery::mock(LeaveByPushService::class);
        $mock->shouldReceive('run')->once();
        $this->app->instance(LeaveByPushService::class, $mock);

        $this->artisan('notifications:tick')->assertExitCode(0);
    }
}
```

- [ ] **Step 3: Run**

Run: `docker compose exec app php artisan test tests/Feature/Push/SendLeaveByNotificationsCommandTest.php`
Expected: 1 passing.

- [ ] **Step 4: Register in scheduler**

In `app/Console/Kernel.php` `schedule()`, add (near `horizon:snapshot`):
```php
        $schedule->command('notifications:tick')
            ->everyFiveMinutes()
            ->onOneServer();
```

- [ ] **Step 5: Verify the schedule lists it**

Run: `docker compose exec app php artisan schedule:list`
Expected: `notifications:tick` appears, every five minutes.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/SendLeaveByNotifications.php app/Console/Kernel.php tests/Feature/Push/SendLeaveByNotificationsCommandTest.php
git commit -m "feat(push): notifications:tick command scheduled every 5 minutes"
```

---

## Task 11: Full suite + payload contract test

**Files:**
- Create: `tests/Feature/Push/PayloadContractTest.php`

- [ ] **Step 1: Explicit cross-repo contract test**

The mobile app's `PushPayload.fromData` reads exactly these keys; assert the service produces them. `tests/Feature/Push/PayloadContractTest.php`:
```php
<?php

namespace Tests\Feature\Push;

use App\Jobs\SendEventPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\User;
use App\Services\Push\LeaveByPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayloadContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_payload_keys_match_mobile_contract(): void
    {
        Queue::fake();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => Bookings::class,
            'date' => '2026-06-14', 'start_time' => '19:00', 'title' => 'Gig',
            'venue_address' => '100 Main St', 'venue_timezone' => 'America/Chicago',
            'additional_data' => ['times' => [['title' => 'Load In', 'time' => '2026-06-14 14:00:00']]],
        ]);
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'platform' => 'ios']);
        EventMember::create(['event_id' => $event->id, 'band_id' => $band->id, 'user_id' => $user->id, 'attendance_status' => 'confirmed']);

        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC')); // 8h-before window
        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertPushed(SendEventPush::class, function ($job) {
            $allowed = ['type', 'eventKey', 'title', 'venueAddress', 'firstItemTitle', 'firstItemTime', 'showTime'];
            foreach (array_keys($job->payload) as $k) {
                if (!in_array($k, $allowed, true)) {
                    return false; // no unexpected keys
                }
            }
            return $job->payload['type'] === 'event_reminder_8h'
                && $job->payload['eventKey'] !== ''
                && array_key_exists('firstItemTitle', $job->payload)
                && array_key_exists('showTime', $job->payload);
        });
    }
}
```

- [ ] **Step 2: Run the whole push suite**

Run: `docker compose exec app php artisan test tests/Feature/Push tests/Unit/Push`
Expected: all passing.

- [ ] **Step 3: Run the full suite for regressions**

Run: `docker compose exec app php artisan test`
Expected: no NEW failures (note any pre-existing failures unrelated to this work).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Push/PayloadContractTest.php
git commit -m "test(push): assert payload matches mobile PushPayload contract"
```

---

## Self-Review Notes

- **Spec coverage:** device endpoints (Task 5), kreait/FCM data-only send (Tasks 1,7,8), 5-min tick + due-window + idempotency log (Tasks 3,9,10), venue-tz derivation cached on events (Tasks 4,6), recipients = non-absent EventMembers with a device (Task 9), dead-token pruning (Task 8), per-event isolation on error (Task 9), payload contract (Task 11). Timeline/first-item reuse: the service reads `additional_data->times` with the SAME sort as `EventDataService::parseAdditionalData` (verified shape) rather than extracting the service — simpler and no risk to the existing API. Show time = `event.start_time` per spec.
- **Placeholder scan:** none — full code in every step. The two NOTE blocks (googlemaps call style in Task 6, kreait exception class in Task 7) point at the single line to verify against the installed package; they are not deferred work.
- **Type consistency:** `SendEventPush(eventId, userId, type, payload)` constructor matches its dispatch in `LeaveByPushService` and both tests. `FcmSender` constants (DELIVERED/PRUNE/TRANSIENT) used identically in the job and its test. `VenueTimezoneResolver(?Closure)` seam matches its tests. `deviceTokens()` relation on User used by `whereHas('user.deviceTokens')` and added in Task 9 Step 4. Payload keys identical across Tasks 9 and 11 and the mobile contract.
- **Idempotency nuance:** `SendEventPush` logs only when at least one token was delivered; `LeaveByPushService` also pre-checks the log before dispatching. Double-protection; the unique index is the backstop.
