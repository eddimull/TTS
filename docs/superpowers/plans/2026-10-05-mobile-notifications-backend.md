# Mobile Notifications — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose the web bell (Laravel database notifications) to the mobile app: a paginated mobile API with server-resolved deep links, read/seen endpoints, a thin realtime signal, and (behind a flag) push parity for every notification type that today reaches only the bell.

**Architecture:** A `NotificationPresenter` turns a stored `Bandnotification` row into `{kind, text, deeplink, web_url, …}`, tolerating every payload shape in the table. A thin `Api\Mobile\NotificationsController` serves list/unseen-count/read/read-all/seen. A `NotificationChanged` broadcast (model `notification`) rides the user's private channel on created/read/seen. A `NotificationSent` listener dispatches `SendNotificationPush` for database notifications that lack their own push job, gated by `config('push.notifications_feed')`. Two defective payload writers are corrected at the source.

**Tech Stack:** Laravel 12 (PHP 8.3, Docker `docker compose exec -T app …`), Sanctum, Pusher broadcasting, existing `SendUserPush`/`FcmSender` push rails, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-10-05-mobile-notifications-design.md`

## Global Constraints

- Branch `feat/mobile-notifications-api` (off `origin/staging`, created); PR targets **staging**.
- No migrations. `Bandnotification` gains only a `seen_at` datetime cast.
- Existing push payloads/jobs for chat, rehearsal cancel/restore/sub, questionnaire submitted, and leave-by are untouched; `tests/Feature/Api/Mobile/Chat/ChatPushTest.php`, `tests/Feature/RehearsalCancelledNotificationTest.php`, `tests/Feature/Web/Chat/*NotificationTest.php` must stay green and unedited.
- `kind` ∈ `booking | event | rehearsal | conversation | band | questionnaire | dashboard`. Mobile deeplinks are exactly: `/bookings/{band}/{booking}`, `/events/{key}`, `/rehearsals/{id}`, `/conversations/{id}`, `/questionnaires/{questionnaireId}/instances/{instanceId}`, `/band-settings`, `/dashboard`.
- Push payload for feed pushes: `['type' => 'notification', 'notificationId' => (string) id, 'kind' => …, 'title' => …, 'body' => text, 'deeplink' => …]`, dedupe key `notification:{id}`, `alert: true`. Only when `config('push.notifications_feed')` is true (default **false**).
- Classes that already push (never doubled): `CommentPosted`, `DirectMessageReceived`, `RehearsalCancelled`, `QuestionnaireSubmitted`.
- Seen endpoint marks rows with `seen_at IS NULL` (not the web `/seentIt` bug).
- All PHP in the container; TDD per task; commit after each task with the trailer:
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
  ```

---

## File structure

**Create**
- `app/Services/Notifications/NotificationPresenter.php`
- `app/Http/Controllers/Api/Mobile/NotificationsController.php`
- `app/Events/NotificationChanged.php`
- `app/Listeners/BroadcastDatabaseNotification.php` (created → realtime signal)
- `app/Listeners/PushDatabaseNotification.php` (created → push, flag-gated)
- `app/Jobs/SendNotificationPush.php`
- `config/push.php`
- `tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`
- `tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php`
- `tests/Feature/Api/Mobile/Notifications/NotificationRealtimeTest.php`
- `tests/Feature/Api/Mobile/Notifications/NotificationPushTest.php`
- `tests/Feature/Api/Mobile/Notifications/PayloadCorrectionsTest.php`

**Modify**
- `app/Models/Bandnotification.php` (cast)
- `routes/api.php` (5 routes in the `auth:sanctum` mobile group)
- `app/Providers/EventServiceProvider.php` (`$listen`)
- `routes/notifications.php` (fire `NotificationChanged` on web read/seen)
- `app/Jobs/ProcessEventUpdated.php`, `app/Http/Controllers/BandsController.php` (payload corrections)

---

### Task 1: `NotificationPresenter`

**Files:**
- Create: `app/Services/Notifications/NotificationPresenter.php`
- Modify: `app/Models/Bandnotification.php`
- Create: `tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`

**Interfaces:**
- Produces: `NotificationPresenter::present(Bandnotification $n, User $viewer): array` → keys `id, kind, text, deeplink, web_url, read_at, seen_at, created_at` (ISO-8601 strings or null). `NotificationPresenter::KINDS` constant.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Models\Bandnotification;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\Chat\ConversationService;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationPresenterTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    /** Store a raw database notification row for $user with the given data. */
    private function row(User $user, array $data, array $attrs = []): Bandnotification
    {
        $user->notify(new TTSNotification($data));
        $row = Bandnotification::where('notifiable_id', $user->id)->latest('id')->first();
        if ($attrs) {
            $row->forceFill($attrs)->save();
            $row->refresh();
        }

        return $row;
    }

    private function presentFor(User $user, array $data): array
    {
        return app(NotificationPresenter::class)->present($this->row($user, $data), $user);
    }

    public function test_output_shape_and_timestamps(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $row = $this->row($owner, ['text' => 'hello'], ['read_at' => now(), 'seen_at' => now()]);

        $out = app(NotificationPresenter::class)->present($row, $owner);

        $this->assertSame(['id', 'kind', 'text', 'deeplink', 'web_url', 'read_at', 'seen_at', 'created_at'], array_keys($out));
        $this->assertSame($row->id, $out['id']);
        $this->assertSame('hello', $out['text']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['read_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['seen_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['created_at']);
    }

    public function test_booking_details_assoc_params(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $out = $this->presentFor($owner, [
            'text' => 'Payment received', 'route' => 'Booking Details',
            'routeParams' => ['band' => $band->id, 'booking' => $booking->id],
        ]);

        $this->assertSame('booking', $out['kind']);
        $this->assertSame("/bookings/{$band->id}/{$booking->id}", $out['deeplink']);
        $this->assertSame("/bands/{$band->id}/booking/{$booking->id}", $out['web_url']);
    }

    public function test_events_show_with_scalar_key_and_events_advance(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        foreach (['events.show', 'events.advance'] as $route) {
            $out = $this->presentFor($owner, ['text' => 'x', 'route' => $route, 'routeParams' => $event->key]);
            $this->assertSame('event', $out['kind'], $route);
            $this->assertSame("/events/{$event->key}", $out['deeplink'], $route);
            $this->assertSame("/events/{$event->key}", $out['web_url'], $route);
        }

        $assoc = $this->presentFor($owner, ['text' => 'x', 'route' => 'events.show', 'routeParams' => ['key' => $event->key]]);
        $this->assertSame("/events/{$event->key}", $assoc['deeplink']);
    }

    public function test_broken_event_details_route_resolves_through_the_event_id(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $out = $this->presentFor($owner, [
            'text' => "Event status changed", 'route' => 'Event Details',
            'routeParams' => ['band' => $band->id, 'event' => $event->id],
            'url' => "/bands/{$band->id}/events/{$event->id}",
        ]);

        $this->assertSame('event', $out['kind']);
        $this->assertSame("/events/{$event->key}", $out['deeplink']);
        $this->assertSame("/events/{$event->key}", $out['web_url']);
    }

    public function test_rehearsal_cancelled_payload_has_no_route_but_has_rehearsal_id(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal] = $this->makeRehearsalEvent($band);

        $out = $this->presentFor($owner, ['text' => 'Rehearsal cancelled', 'link' => '/rehearsal-schedules', 'rehearsal_id' => $rehearsal->id]);

        $this->assertSame('rehearsal', $out['kind']);
        $this->assertSame("/rehearsals/{$rehearsal->id}", $out['deeplink']);
        $this->assertSame("/bands/{$band->id}/rehearsal-schedules/{$rehearsal->rehearsal_schedule_id}/rehearsals/{$rehearsal->id}", $out['web_url']);
    }

    public function test_conversation_ids_win_over_route_for_comments_and_dms(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);

        $out = $this->presentFor($owner, [
            'text' => 'A commented on Test Gig: hi', 'route' => 'events.show',
            'routeParams' => ['key' => $event->key, 'comments' => 1], 'conversation_id' => $topic->id, 'message_id' => 1,
        ]);
        $this->assertSame('conversation', $out['kind']);
        $this->assertSame("/conversations/{$topic->id}", $out['deeplink']);
        $this->assertSame("/messages/{$topic->id}", $out['web_url']);

        $dm = $this->presentFor($owner, ['text' => 'B: yo', 'route' => 'messages.index', 'routeParams' => ['conversation' => 42], 'conversation_id' => 42, 'message_id' => 2]);
        $this->assertSame('/conversations/42', $dm['deeplink']);
    }

    public function test_questionnaire_submitted_resolves_to_the_instance_screen(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;
        $questionnaire = Questionnaires::factory()->create(['band_id' => $band->id]);
        $instance = QuestionnaireInstances::factory()->create(['questionnaire_id' => $questionnaire->id, 'booking_id' => $booking->id]);

        $out = $this->presentFor($owner, ['instance_id' => $instance->id, 'questionnaire_name' => 'Q', 'text' => 'Client submitted the Q', 'route' => 'dashboard', 'routeParams' => []]);

        $this->assertSame('questionnaire', $out['kind']);
        $this->assertSame("/questionnaires/{$questionnaire->id}/instances/{$instance->id}", $out['deeplink']);
    }

    public function test_band_routes_resolve_to_band_settings_for_owners_and_dashboard_for_others(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        foreach ([
            ['route' => 'bands.edit', 'routeParams' => $band->id, 'url' => "/bands/{$band->id}/edit"],
            ['route' => 'bands', 'routeParams' => null, 'url' => "/bands/{$band->id}/edit"],   // logo-upload shape
        ] as $data) {
            $data['text'] = 'band changed';
            $o = $this->presentFor($owner, $data);
            $this->assertSame('band', $o['kind']);
            $this->assertSame('/band-settings', $o['deeplink']);
            $this->assertSame("/bands/{$band->id}/edit", $o['web_url']);

            $m = $this->presentFor($member, $data);
            $this->assertSame('dashboard', $m['kind']);
            $this->assertSame('/dashboard', $m['deeplink']);
        }
    }

    public function test_dashboard_fallbacks_and_default_text(): void
    {
        [$owner] = $this->makeOwnerWithBand();

        $nullParams = $this->presentFor($owner, ['text' => 'sub invited', 'route' => 'dashboard', 'routeParams' => null]);
        $this->assertSame(['dashboard', '/dashboard', '/dashboard'], [$nullParams['kind'], $nullParams['deeplink'], $nullParams['web_url']]);

        $defaults = $this->presentFor($owner, []); // TTSNotification fills text '', route 'dashboard', routeParams ''
        $this->assertSame('dashboard', $defaults['kind']);
        $this->assertSame('New notification', $defaults['text']);

        $deleted = $this->presentFor($owner, ['text' => 'gone', 'route' => 'events.show', 'routeParams' => 'no-such-key']);
        $this->assertSame('dashboard', $deleted['kind']);
        $this->assertSame('/dashboard', $deleted['deeplink']);
    }

    public function test_url_patterns_are_used_when_route_is_unknown(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $out = $this->presentFor($owner, ['text' => 'x', 'route' => 'something.unknown', 'url' => "/bands/{$band->id}/booking/{$booking->id}"]);
        $this->assertSame("/bookings/{$band->id}/{$booking->id}", $out['deeplink']);
    }
}
```

If `Questionnaires`/`QuestionnaireInstances` factories do not exist (`ls database/factories | grep -i question`), create minimal ones with the required columns (band_id/name for the questionnaire; questionnaire_id, booking_id, name, status for the instance) — check the models' `$fillable` and the migrations for NOT NULL columns.

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`
Expected: FAIL — `Class "App\Services\Notifications\NotificationPresenter" not found`.

- [ ] **Step 3: Cast `seen_at`**

`app/Models/Bandnotification.php` — add inside the class:
```php
    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
        'seen_at' => 'datetime',
    ];
```
(`DatabaseNotification` already casts `data`/`read_at`; restating them keeps the parent behaviour while adding `seen_at`.)

- [ ] **Step 4: Implement the presenter**

`app/Services/Notifications/NotificationPresenter.php`:

```php
<?php

namespace App\Services\Notifications;

use App\Models\Bandnotification;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\Rehearsal;
use App\Models\User;

/**
 * Turns a stored database notification into what a client needs to render
 * and open it. Stored payloads are not uniform (routeParams may be an assoc
 * array, a bare scalar, null or ''; some rows carry only ids; one writer
 * used a route name that never existed), so every shape is handled here and
 * nothing ever fails — an unresolvable row lands on the dashboard.
 *
 * `deeplink` is a MOBILE route; `web_url` is the corrected web path for the
 * same target (the web bell can switch to it later).
 */
final class NotificationPresenter
{
    public const KINDS = ['booking', 'event', 'rehearsal', 'conversation', 'band', 'questionnaire', 'dashboard'];

    public function present(Bandnotification $n, User $viewer): array
    {
        $data = is_array($n->data) ? $n->data : (array) ($n->data ?? []);

        [$kind, $deeplink, $webUrl] = $this->resolve($data, $viewer);

        return [
            'id'         => $n->id,
            'kind'       => $kind,
            'text'       => $this->text($data),
            'deeplink'   => $deeplink,
            'web_url'    => $webUrl,
            'read_at'    => $n->read_at?->toIso8601String(),
            'seen_at'    => $n->seen_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    private function text(array $data): string
    {
        foreach (['text', 'message'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'New notification';
    }

    /** @return array{0: string, 1: string, 2: string} [kind, deeplink, web_url] */
    private function resolve(array $data, User $viewer): array
    {
        // 1. Explicit ids beat route names (chat rows carry the conversation;
        //    rehearsal-cancel rows have no route at all).
        if (!empty($data['conversation_id'])) {
            return $this->conversation((int) $data['conversation_id']);
        }
        if (!empty($data['rehearsal_id'])) {
            return $this->rehearsal((int) $data['rehearsal_id']);
        }
        if (!empty($data['instance_id'])) {
            return $this->questionnaireInstance((int) $data['instance_id']);
        }

        // 2. Route name + params in any of the stored shapes.
        $route  = isset($data['route']) && is_string($data['route']) ? $data['route'] : null;
        $params = $this->params($data['routeParams'] ?? null);

        switch ($route) {
            case 'Booking Details':
                return $this->booking((int) ($params['booking'] ?? 0), (int) ($params['band'] ?? 0));
            case 'events.show':
            case 'events.advance':
                return $this->eventByKey($params['key'] ?? $params[0] ?? null);
            case 'Event Details': // never a real route; the writer stored the event id
                return $this->eventById((int) ($params['event'] ?? 0));
            case 'rehearsals.show':
                return $this->rehearsal((int) ($params['rehearsal'] ?? 0));
            case 'messages.index':
                $id = (int) ($params['conversation'] ?? 0);

                return $id ? $this->conversation($id) : $this->dashboard();
            case 'bands.edit':
            case 'bands':
                $bandId = (int) ($params['band'] ?? $params[0] ?? 0) ?: $this->bandIdFromUrl($data);

                return $this->band($bandId, $viewer);
        }

        // 3. Path patterns in url/link.
        $url = $data['url'] ?? $data['link'] ?? null;
        if (is_string($url)) {
            if (preg_match('#^/events/([^/?]+)#', $url, $m)) {
                return $this->eventByKey($m[1]);
            }
            if (preg_match('#^/bands/(\d+)/booking/(\d+)#', $url, $m)) {
                return $this->booking((int) $m[2], (int) $m[1]);
            }
            if (preg_match('#^/bands/(\d+)/edit#', $url, $m)) {
                return $this->band((int) $m[1], $viewer);
            }
            if (preg_match('#^/messages/(\d+)#', $url, $m)) {
                return $this->conversation((int) $m[1]);
            }
        }

        return $this->dashboard();
    }

    /** routeParams as stored: assoc array, bare scalar (→ [0 => scalar]), null, or ''. */
    private function params(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_scalar($raw) && (string) $raw !== '') {
            return [0 => $raw];
        }

        return [];
    }

    private function bandIdFromUrl(array $data): int
    {
        $url = $data['url'] ?? $data['link'] ?? '';

        return is_string($url) && preg_match('#^/bands/(\d+)#', $url, $m) ? (int) $m[1] : 0;
    }

    private function booking(int $bookingId, int $bandId): array
    {
        $booking = $bookingId ? Bookings::find($bookingId) : null;
        if (!$booking) {
            return $this->dashboard();
        }
        $bandId = (int) ($booking->band_id ?: $bandId);

        return ['booking', "/bookings/{$bandId}/{$booking->id}", "/bands/{$bandId}/booking/{$booking->id}"];
    }

    private function eventByKey(mixed $key): array
    {
        $event = is_string($key) && $key !== '' ? Events::where('key', $key)->first() : null;

        return $event ? ['event', "/events/{$event->key}", "/events/{$event->key}"] : $this->dashboard();
    }

    private function eventById(int $id): array
    {
        $event = $id ? Events::find($id) : null;

        return $event ? ['event', "/events/{$event->key}", "/events/{$event->key}"] : $this->dashboard();
    }

    private function rehearsal(int $id): array
    {
        $rehearsal = $id ? Rehearsal::find($id) : null;
        if (!$rehearsal) {
            return $this->dashboard();
        }

        return [
            'rehearsal',
            "/rehearsals/{$rehearsal->id}",
            "/bands/{$rehearsal->band_id}/rehearsal-schedules/{$rehearsal->rehearsal_schedule_id}/rehearsals/{$rehearsal->id}",
        ];
    }

    private function conversation(int $id): array
    {
        return ['conversation', "/conversations/{$id}", "/messages/{$id}"];
    }

    private function questionnaireInstance(int $id): array
    {
        $instance = QuestionnaireInstances::find($id);
        if (!$instance) {
            return $this->dashboard();
        }

        return [
            'questionnaire',
            "/questionnaires/{$instance->questionnaire_id}/instances/{$instance->id}",
            "/questionnaires/{$instance->questionnaire_id}",
        ];
    }

    /** Band settings is owner-only on mobile; everyone else lands on the dashboard. */
    private function band(int $bandId, User $viewer): array
    {
        if ($bandId && $viewer->ownsBand($bandId)) {
            return ['band', '/band-settings', "/bands/{$bandId}/edit"];
        }

        return $this->dashboard();
    }

    private function dashboard(): array
    {
        return ['dashboard', '/dashboard', '/dashboard'];
    }
}
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`
Expected: PASS (10 tests). If the `web_url` for questionnaires needs a different web path, keep `/questionnaires/{questionnaireId}` (no web instance page exists; it's informational).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Notifications/NotificationPresenter.php app/Models/Bandnotification.php tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php database/factories
git commit -m "feat(notifications): NotificationPresenter resolves kind, text and mobile deeplink for every stored payload shape"
```

---

### Task 2: Mobile notifications API

**Files:**
- Create: `app/Http/Controllers/Api/Mobile/NotificationsController.php`
- Modify: `routes/api.php` (after the `/devices` routes, inside the `auth:sanctum` group)
- Create: `tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php`

**Interfaces:**
- Produces routes: `mobile.notifications.index` (`GET /api/mobile/notifications?cursor=&limit=`), `mobile.notifications.unseen` (`GET /api/mobile/notifications/unseen-count`), `mobile.notifications.read` (`POST /api/mobile/notifications/{notification}/read`), `mobile.notifications.read-all` (`POST /api/mobile/notifications/read-all`), `mobile.notifications.seen` (`POST /api/mobile/notifications/seen`).
- Consumes: `NotificationPresenter::present()`. Task 3 adds the `NotificationChanged` broadcast calls into `read()/readAll()/seen()` — leave a clearly marked spot.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Models\Bandnotification;
use App\Models\User;
use App\Notifications\TTSNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private function seed(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user->notify(new TTSNotification(['text' => "n{$i}", 'route' => 'dashboard', 'routeParams' => null]));
            // distinct created_at so the cursor is deterministic
            Bandnotification::where('notifiable_id', $user->id)->latest('id')->first()
                ->forceFill(['created_at' => now()->subMinutes($count - $i)])->save();
        }
    }

    public function test_index_paginates_past_fifty_with_a_cursor_and_reports_unseen(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $this->seed($owner, 55);

        $first = $this->actingAs($owner)->getJson('/api/mobile/notifications?limit=30')->assertOk();
        $this->assertCount(30, $first->json('notifications'));
        $this->assertSame('n55', $first->json('notifications.0.text'), 'newest first');
        $this->assertSame(['id', 'kind', 'text', 'deeplink', 'web_url', 'read_at', 'seen_at', 'created_at'], array_keys($first->json('notifications.0')));
        $this->assertNotNull($first->json('next_cursor'));
        $this->assertSame(55, $first->json('unseen_count'));

        $second = $this->actingAs($owner)->getJson('/api/mobile/notifications?limit=30&cursor=' . urlencode($first->json('next_cursor')))->assertOk();
        $this->assertCount(25, $second->json('notifications'));
        $this->assertSame('n25', $second->json('notifications.0.text'));
        $this->assertSame('n1', $second->json('notifications.24.text'));
        $this->assertNull($second->json('next_cursor'));

        $ids = array_merge($first->json('notifications.*.id'), $second->json('notifications.*.id'));
        $this->assertCount(55, array_unique($ids), 'no duplicates across pages');
    }

    public function test_read_read_all_and_seen_semantics(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $this->seed($owner, 3);
        $rows = Bandnotification::where('notifiable_id', $owner->id)->get();

        $this->actingAs($owner)->postJson("/api/mobile/notifications/{$rows[0]->id}/read")->assertNoContent();
        $this->assertNotNull($rows[0]->fresh()->read_at);
        $this->assertNull($rows[0]->fresh()->seen_at, 'read does not imply seen');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/seen')->assertNoContent();
        $this->assertSame(0, Bandnotification::where('notifiable_id', $owner->id)->whereNull('seen_at')->count());
        $this->assertSame(2, Bandnotification::where('notifiable_id', $owner->id)->whereNull('read_at')->count());
        $this->actingAs($owner)->getJson('/api/mobile/notifications/unseen-count')->assertOk()->assertJson(['count' => 0]);

        $this->actingAs($owner)->postJson('/api/mobile/notifications/read-all')->assertNoContent();
        $this->assertSame(0, Bandnotification::where('notifiable_id', $owner->id)->whereNull('read_at')->count());
    }

    public function test_cannot_read_another_users_notification(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $this->seed($owner, 1);
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        $this->actingAs($member)->postJson("/api/mobile/notifications/{$row->id}/read")->assertNotFound();
        $this->assertNull($row->fresh()->read_at);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/mobile/notifications')->assertUnauthorized();
        $this->postJson('/api/mobile/notifications/seen')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Controller**

`app/Http/Controllers/Api/Mobile/NotificationsController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bandnotification;
use App\Models\User;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The mobile feed over Laravel database notifications. Queries the table
 * directly rather than User::notifications(), which is capped at 50 rows.
 */
class NotificationsController extends Controller
{
    public function __construct(private readonly NotificationPresenter $presenter) {}

    /** GET /api/mobile/notifications?cursor={created_at|id}&limit=30 */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $limit = max(1, min(100, (int) $request->input('limit', 30)));

        $query = $this->own($user)->orderByDesc('created_at')->orderByDesc('id');

        if ($cursor = (string) $request->input('cursor', '')) {
            [$at, $id] = array_pad(explode('|', $cursor, 2), 2, null);
            if ($at) {
                $query->where(fn (Builder $q) => $q
                    ->where('created_at', '<', $at)
                    ->orWhere(fn (Builder $tie) => $tie->where('created_at', $at)->where('id', '<', (string) $id)));
            }
        }

        $rows    = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows    = $rows->take($limit)->values();
        $last    = $rows->last();

        return response()->json([
            'notifications' => $rows->map(fn (Bandnotification $n) => $this->presenter->present($n, $user))->values(),
            'next_cursor'   => $hasMore && $last ? $last->created_at->format('Y-m-d H:i:s') . '|' . $last->id : null,
            'unseen_count'  => $this->own($user)->whereNull('seen_at')->count(),
        ]);
    }

    /** GET /api/mobile/notifications/unseen-count */
    public function unseenCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->own($request->user())->whereNull('seen_at')->count()]);
    }

    /** POST /api/mobile/notifications/{notification}/read — 404 unless it is the caller's. */
    public function read(Request $request, string $notification): Response
    {
        $row = $this->own($request->user())->where('id', $notification)->firstOrFail();
        $row->markAsRead();

        // Task 3: broadcast NotificationChanged(user, id, 'read') here.

        return response()->noContent();
    }

    /** POST /api/mobile/notifications/read-all */
    public function readAll(Request $request): Response
    {
        $this->own($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        // Task 3: broadcast NotificationChanged(user, null, 'read') here.

        return response()->noContent();
    }

    /** POST /api/mobile/notifications/seen — marks every UNSEEN row (not "unread"). */
    public function seen(Request $request): Response
    {
        $this->own($request->user())->whereNull('seen_at')->update(['seen_at' => now()]);

        // Task 3: broadcast NotificationChanged(user, null, 'seen') here.

        return response()->noContent();
    }

    private function own(User $user): Builder
    {
        return Bandnotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id);
    }
}
```

- [ ] **Step 4: Routes**

In `routes/api.php`, directly after the two `/devices` routes:
```php
        // In-app notification feed (the web "bell") — personal, band-agnostic.
        Route::get('/notifications', [App\Http\Controllers\Api\Mobile\NotificationsController::class, 'index'])->name('mobile.notifications.index');
        Route::get('/notifications/unseen-count', [App\Http\Controllers\Api\Mobile\NotificationsController::class, 'unseenCount'])->name('mobile.notifications.unseen');
        Route::post('/notifications/read-all', [App\Http\Controllers\Api\Mobile\NotificationsController::class, 'readAll'])->name('mobile.notifications.read-all');
        Route::post('/notifications/seen', [App\Http\Controllers\Api\Mobile\NotificationsController::class, 'seen'])->name('mobile.notifications.seen');
        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\Mobile\NotificationsController::class, 'read'])->name('mobile.notifications.read');
```
(Order matters: the literal `read-all`/`seen`/`unseen-count` segments are registered before `{notification}/read` so they can't be captured; the `{notification}` parameter is a raw string, not model-bound, because ownership is checked in the controller.)

- [ ] **Step 5: Run the tests**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php`
Expected: PASS (4 tests). If the cursor comparison fails on MySQL because `created_at` loses sub-second precision, the tie-break on `id` handles equal timestamps — confirm the `seed()` helper's `subMinutes` makes them distinct anyway.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/NotificationsController.php routes/api.php tests/Feature/Api/Mobile/Notifications/NotificationsApiTest.php
git commit -m "feat(notifications): mobile feed API — paginated index, unseen count, read, read-all, seen"
```

---

### Task 3: `NotificationChanged` realtime signal

**Files:**
- Create: `app/Events/NotificationChanged.php`
- Create: `app/Listeners/BroadcastDatabaseNotification.php`
- Modify: `app/Providers/EventServiceProvider.php`
- Modify: `app/Http/Controllers/Api/Mobile/NotificationsController.php` (the three Task-2 markers)
- Modify: `routes/notifications.php`
- Create: `tests/Feature/Api/Mobile/Notifications/NotificationRealtimeTest.php`

**Interfaces:**
- Produces: `NotificationChanged(int $userId, ?string $notificationId, string $action)` broadcasting `user.data-changed` on `private-App.Models.User.{id}` with `{ model: 'notification', id, action }` (`action` ∈ `created|read|seen`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/Mobile/Notifications/NotificationRealtimeTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Events\NotificationChanged;
use App\Models\Bandnotification;
use App\Notifications\TTSNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationRealtimeTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_shape(): void
    {
        $e = new NotificationChanged(7, 'abc', 'created');

        $this->assertSame('user.data-changed', $e->broadcastAs());
        $this->assertSame('private-App.Models.User.7', $e->broadcastOn()[0]->name);
        $this->assertSame(['model' => 'notification', 'id' => 'abc', 'action' => 'created'], $e->broadcastWith());
    }

    public function test_created_read_and_seen_broadcast_for_the_owner(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner] = $this->makeOwnerWithBand();

        $owner->notify(new TTSNotification(['text' => 'hi', 'route' => 'dashboard', 'routeParams' => null]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === $row->id && $e->action === 'created');

        $this->actingAs($owner)->postJson("/api/mobile/notifications/{$row->id}/read")->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === $row->id && $e->action === 'read');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/seen')->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === null && $e->action === 'seen');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/read-all')->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === null && $e->action === 'read');
    }

    public function test_web_bell_routes_broadcast_too(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner] = $this->makeOwnerWithBand();
        $owner->notify(new TTSNotification(['text' => 'hi', 'route' => 'dashboard', 'routeParams' => null]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        $this->actingAs($owner)->post("/notification/{$row->id}");
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === $row->id && $e->action === 'read');

        $this->actingAs($owner)->post('/seentIt');
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->action === 'seen');

        $this->actingAs($owner)->post('/readAllNotifications');
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === null && $e->action === 'read');
    }

    public function test_notifications_to_contacts_do_not_broadcast(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $contact = \App\Models\Contacts::factory()->create(['band_id' => $band->id]);

        $contact->notify(new TTSNotification(['text' => 'portal', 'route' => 'dashboard', 'routeParams' => null]));

        Event::assertNotDispatched(NotificationChanged::class);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationRealtimeTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Event + listener**

`app/Events/NotificationChanged.php`:
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thin per-user signal that the notification feed changed — the bell
 * analogue of ConversationChanged. Clients refetch the feed/badge; nothing
 * is carried beyond ids. Not toOthers(): the acting device refreshes too.
 */
class NotificationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public ?string $notificationId,
        public string $action, // created | read | seen
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.data-changed';
    }

    public function broadcastWith(): array
    {
        return ['model' => 'notification', 'id' => $this->notificationId, 'action' => $this->action];
    }
}
```

`app/Listeners/BroadcastDatabaseNotification.php`:
```php
<?php

namespace App\Listeners;

use App\Events\NotificationChanged;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/** Every database notification stored for a User announces itself to that user's channel. */
class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !$event->notifiable instanceof User) {
            return;
        }

        $id = $event->response instanceof DatabaseNotification ? $event->response->id : null;

        NotificationChanged::dispatch($event->notifiable->id, $id, 'created');
    }
}
```

`app/Providers/EventServiceProvider.php` — add to `$listen`:
```php
        \Illuminate\Notifications\Events\NotificationSent::class => [
            \App\Listeners\BroadcastDatabaseNotification::class,
        ],
```

- [ ] **Step 4: Fire from the endpoints and the web routes**

In `NotificationsController`, replace the three `// Task 3:` markers with:
- `read()`: `NotificationChanged::dispatch($request->user()->id, $row->id, 'read');`
- `readAll()`: `NotificationChanged::dispatch($request->user()->id, null, 'read');`
- `seen()`: `NotificationChanged::dispatch($request->user()->id, null, 'seen');`
(add `use App\Events\NotificationChanged;`).

In `routes/notifications.php`, add `use App\Events\NotificationChanged;` and:
- inside `POST /notification/{id}` after `$notification->markAsRead();` → `NotificationChanged::dispatch(Auth::id(), $notification->id, 'read');`
- at the end of `POST /readAllNotifications`'s loop → `NotificationChanged::dispatch(Auth::id(), null, 'read');`
- at the end of `POST /seentIt`'s loop → `NotificationChanged::dispatch(Auth::id(), null, 'seen');`
Do not change what those routes return.

- [ ] **Step 5: Run the tests**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications tests/Feature/Api/Mobile/Chat/ChatBroadcastingTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Events/NotificationChanged.php app/Listeners/BroadcastDatabaseNotification.php app/Providers/EventServiceProvider.php app/Http/Controllers/Api/Mobile/NotificationsController.php routes/notifications.php tests/Feature/Api/Mobile/Notifications/NotificationRealtimeTest.php
git commit -m "feat(notifications): NotificationChanged user-channel signal on created, read and seen"
```

---

### Task 4: Push parity behind a flag

**Files:**
- Create: `config/push.php`
- Create: `app/Jobs/SendNotificationPush.php`
- Create: `app/Listeners/PushDatabaseNotification.php`
- Modify: `app/Providers/EventServiceProvider.php` (`$listen`)
- Create: `tests/Feature/Api/Mobile/Notifications/NotificationPushTest.php`

**Interfaces:**
- Produces: `config('push.notifications_feed')` (env `PUSH_NOTIFICATIONS_FEED`, default false); `SendNotificationPush(string $notificationId)` queued job; `PushDatabaseNotification::SELF_PUSHING` allowlist constant.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/Mobile/Notifications/NotificationPushTest.php`:

```php
<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Jobs\SendNotificationPush;
use App\Jobs\SendUserPush;
use App\Models\Bandnotification;
use App\Notifications\CommentPosted;
use App\Notifications\TTSNotification;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationPushTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_flag_off_by_default_means_no_feed_push(): void
    {
        Queue::fake([SendNotificationPush::class, SendUserPush::class]);
        [$owner] = $this->makeOwnerWithBand();

        $owner->notify(new TTSNotification(['text' => 'status changed', 'route' => 'dashboard', 'routeParams' => null]));

        $this->assertFalse(config('push.notifications_feed'));
        Queue::assertNotPushed(SendNotificationPush::class);
    }

    public function test_flag_on_pushes_bell_only_notifications_with_type_notification_and_deeplink(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendUserPush::class]); // SendNotificationPush runs inline
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $owner->notify(new TTSNotification([
            'text' => "Booking '{$booking->name}' status changed from pending to confirmed",
            'route' => 'Booking Details', 'routeParams' => ['band' => $band->id, 'booking' => $booking->id],
        ]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        Queue::assertPushed(SendUserPush::class, 1);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) =>
            $job->userId === $owner->id
            && $job->alert === true
            && $job->dedupeKey === 'notification:' . $row->id
            && $job->data['type'] === 'notification'
            && $job->data['notificationId'] === $row->id
            && $job->data['kind'] === 'booking'
            && $job->data['deeplink'] === "/bookings/{$band->id}/{$booking->id}"
            && str_contains($job->data['body'], 'status changed')
            && is_string($job->data['title']) && $job->data['title'] !== '');
    }

    public function test_self_pushing_notification_classes_are_not_doubled(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendUserPush::class, SendNotificationPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $topic  = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        // A comment: ProcessChatMessagePush sends its own chat_message push AND stores CommentPosted.
        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$topic->id}/messages", ['body' => 'hey'])
            ->assertCreated();

        Queue::assertNotPushed(SendNotificationPush::class);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['type'] === 'chat_message');
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['type'] === 'notification');
    }

    public function test_contact_notifications_never_push(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendNotificationPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $contact = \App\Models\Contacts::factory()->create(['band_id' => $band->id]);

        $contact->notify(new TTSNotification(['text' => 'portal', 'route' => 'dashboard', 'routeParams' => null]));

        Queue::assertNotPushed(SendNotificationPush::class);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/NotificationPushTest.php`
Expected: FAIL — `SendNotificationPush` not found / config null.

- [ ] **Step 3: Config, job, listener**

`config/push.php`:
```php
<?php

return [
    /*
    | Push every database (bell) notification to the user's devices. Kept off
    | until the mobile build that can route `type: notification` pushes is
    | live in the stores — older builds would receive pushes they cannot open.
    */
    'notifications_feed' => (bool) env('PUSH_NOTIFICATIONS_FEED', false),
];
```

`app/Jobs/SendNotificationPush.php`:
```php
<?php

namespace App\Jobs;

use App\Models\Bandnotification;
use App\Models\User;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push one stored bell notification to its user's devices, carrying the
 * presenter's deeplink so a tap lands where the in-app feed would go.
 */
class SendNotificationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $notificationId) {}

    public function handle(NotificationPresenter $presenter): void
    {
        $row = Bandnotification::find($this->notificationId);
        if (!$row || !$row->notifiable instanceof User) {
            return;
        }

        $user      = $row->notifiable;
        $presented = $presenter->present($row, $user);

        SendUserPush::dispatch($user->id, [
            'type'           => 'notification',
            'notificationId' => (string) $row->id,
            'kind'           => $presented['kind'],
            'title'          => config('app.name', 'TTS Band'),
            'body'           => $presented['text'],
            'deeplink'       => $presented['deeplink'],
        ], 'notification:' . $row->id, true);
    }
}
```

`app/Listeners/PushDatabaseNotification.php`:
```php
<?php

namespace App\Listeners;

use App\Jobs\SendNotificationPush;
use App\Models\User;
use App\Notifications\CommentPosted;
use App\Notifications\DirectMessageReceived;
use App\Notifications\QuestionnaireSubmitted;
use App\Notifications\RehearsalCancelled;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Push parity for the bell: every database notification a User receives
 * also goes to their devices — except classes whose producer already sends
 * a push of its own (they would arrive twice).
 */
class PushDatabaseNotification
{
    public const SELF_PUSHING = [
        CommentPosted::class,
        DirectMessageReceived::class,
        RehearsalCancelled::class,
        QuestionnaireSubmitted::class,
    ];

    public function handle(NotificationSent $event): void
    {
        if (!config('push.notifications_feed')) {
            return;
        }
        if ($event->channel !== 'database' || !$event->notifiable instanceof User) {
            return;
        }
        if (in_array(get_class($event->notification), self::SELF_PUSHING, true)) {
            return;
        }
        if (!$event->response instanceof DatabaseNotification) {
            return;
        }

        SendNotificationPush::dispatch($event->response->id);
    }
}
```

`EventServiceProvider::$listen` — extend the `NotificationSent` entry:
```php
        \Illuminate\Notifications\Events\NotificationSent::class => [
            \App\Listeners\BroadcastDatabaseNotification::class,
            \App\Listeners\PushDatabaseNotification::class,
        ],
```

- [ ] **Step 4: Run the tests plus every existing push/notification suite**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications tests/Feature/Api/Mobile/Chat/ChatPushTest.php tests/Feature/RehearsalCancelledNotificationTest.php tests/Feature/Web/Chat/CommentPostedNotificationTest.php tests/Feature/Web/Chat/DirectMessageReceivedTest.php`
Expected: PASS. If any pre-existing test now sees an unexpected `SendUserPush` because it sets the flag — it cannot (default off) — but if a test environment file sets `PUSH_NOTIFICATIONS_FEED=true`, remove it from `.env.testing`/`phpunit.xml`.

- [ ] **Step 5: Commit**

```bash
git add config/push.php app/Jobs/SendNotificationPush.php app/Listeners/PushDatabaseNotification.php app/Providers/EventServiceProvider.php tests/Feature/Api/Mobile/Notifications/NotificationPushTest.php
git commit -m "feat(notifications): push every bell notification to devices behind PUSH_NOTIFICATIONS_FEED"
```

---

### Task 5: Correct the two defective payload writers

**Files:**
- Modify: `app/Jobs/ProcessEventUpdated.php` (`SendNotification()`, the `$notificationData` array)
- Modify: `app/Http/Controllers/BandsController.php` (`uploadLogo`, the `TTSNotification` payload)
- Create: `tests/Feature/Api/Mobile/Notifications/PayloadCorrectionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Jobs\ProcessEventUpdated;
use App\Models\Bandnotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class PayloadCorrectionsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_status_change_notification_names_a_real_web_route(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $event->forceFill(['status' => 'confirmed'])->save();

        // Mirror the observer: originalData carries the previous status.
        (new ProcessEventUpdated($event, ['status' => 'pending']))->SendNotification();

        $row = Bandnotification::where('notifiable_id', $owner->id)->latest('id')->firstOrFail();
        $this->assertSame('events.show', $row->data['route']);
        $this->assertSame(['key' => $event->key], $row->data['routeParams']);
        $this->assertSame("/events/{$event->key}", $row->data['url']);
        $this->assertTrue(Route::has($row->data['route']));
    }
}
```

Read `ProcessEventUpdated`'s constructor first (`grep -n "__construct" -A6 app/Jobs/ProcessEventUpdated.php`) and adapt the instantiation to its real signature (it takes the event and the original attribute array; `SendNotification()` is public). If the job's constructor dispatches side effects, construct it with `Queue::fake()` active.

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/PayloadCorrectionsTest.php`
Expected: FAIL — `'Event Details'` !== `'events.show'`.

- [ ] **Step 3: Fix the writers**

`app/Jobs/ProcessEventUpdated.php` — in `SendNotification()` replace the three keys:
```php
                'route' => 'events.show',
                'routeParams' => ['key' => $this->event->key],
                'url' => "/events/{$this->event->key}"
```

`app/Http/Controllers/BandsController.php` — in `uploadLogo`, replace `'route' => 'bands', 'routeParams' => null,` with:
```php
                    'route' => 'bands.edit',
                    'routeParams' => $band->id,
```
(the `url` already says `/bands/{id}/edit`; the presenter handles both the old and new shapes).

- [ ] **Step 4: Run the test and the presenter suite**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications/PayloadCorrectionsTest.php tests/Feature/Api/Mobile/Notifications/NotificationPresenterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ProcessEventUpdated.php app/Http/Controllers/BandsController.php tests/Feature/Api/Mobile/Notifications/PayloadCorrectionsTest.php
git commit -m "fix(notifications): event-status and logo notifications name real web routes"
```

---

### Task 6: Verification + PR

- [ ] **Step 1:** `docker compose exec -T app php artisan test tests/Feature/Api/Mobile/Notifications tests/Feature/Api/Mobile/Chat tests/Feature/Web/Chat tests/Feature/RehearsalCancelledNotificationTest.php tests/Feature/BookingObserverTest.php` — PASS.
- [ ] **Step 2:** Smoke the API against local data: `docker compose exec -T app php artisan tinker --execute='$u=App\Models\User::find(1); $p=app(App\Services\Notifications\NotificationPresenter::class); foreach(App\Models\Bandnotification::where("notifiable_id",1)->latest()->limit(15)->get() as $n){ $o=$p->present($n,$u); echo $o["kind"]," ",$o["deeplink"]," | ",mb_substr($o["text"],0,50),PHP_EOL; }'` — every row prints a kind and a deeplink (no exceptions); eyeball that the mix looks right (bookings → `/bookings/…`, comments → `/conversations/…`).
- [ ] **Step 3:** Realtime smoke: with the local broker up, `docker compose exec -T app php artisan tinker --execute='App\Models\User::find(1)->notify(new App\Notifications\TTSNotification(["text"=>"realtime smoke","route"=>"dashboard","routeParams"=>null]));'` and confirm the web layout's user-channel listener receives `user.data-changed` with `model: notification` (console) — it is ignored by the web today, which is fine.
- [ ] **Step 4:** PR to staging:

```bash
git push -u origin feat/mobile-notifications-api
gh pr create --base staging --title "feat(notifications): mobile feed API, realtime signal, and flag-gated push parity for the bell" --body-file - <<'EOF'
## Summary
Backend half of bringing the web bell to the mobile app. Spec: `docs/superpowers/specs/2026-10-05-mobile-notifications-design.md`.

- `NotificationPresenter` — kind + text + **mobile deeplink** + corrected `web_url` for every stored payload shape (assoc/scalar/null/'' routeParams, id-only rows, the never-existed `Event Details` route, the logo-upload route/url mismatch).
- `GET /api/mobile/notifications` (cursor-paginated past the 50-row relation cap, with `unseen_count`), `unseen-count`, `read`, `read-all`, `seen` (marks unseen — not the web `/seentIt` "unread" bug).
- `NotificationChanged` → `user.data-changed {model: notification}` on the user's private channel for created/read/seen (mobile + web endpoints).
- **Push parity behind `PUSH_NOTIFICATIONS_FEED`** (default off): every database notification a user gets also pushes with `type: notification` + deeplink; classes with their own push (chat, rehearsal cancel, questionnaire submitted) are allowlisted so nothing doubles.
- Fixed two payload writers: event-status notifications named a non-existent `Event Details` route (the web bell link threw); logo-upload rows disagreed between `route` and `url`.

No migrations; `Bandnotification` gains a `seen_at` cast. Existing push jobs/payloads untouched.

## Rollout
Merge + deploy with the flag **off**. Flip `PUSH_NOTIFICATIONS_FEED=true` only after the mobile release that routes `type: notification` pushes is live in the stores.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
EOF
```
- [ ] **Step 5:** Wait for Copilot and address every comment.

---

## Self-review notes

- **Spec coverage:** §1 presenter (T1), §2 API (T2), §3 realtime incl. web routes (T3), §4 push + flag + allowlist (T4), §5 payload corrections (T5), rollout (T6). Error handling: presenter never throws (fallbacks), controller 404s on foreign rows.
- **Type consistency:** `present()` output keys asserted identically in T1 and T2; `NotificationChanged` ctor `(int, ?string, string)` used the same way in T3's controller/web-route calls and tests; `SendUserPush::dispatch($userId, $data, $dedupeKey, $alert)` matches its constructor; the push payload keys in T4's job match the test and the spec.
- **Known edges:** notification ids are UUID strings, so the cursor tie-break orders by string id (stable, arbitrary); `web_url` for questionnaires points at the questionnaire page (no web instance page exists).
