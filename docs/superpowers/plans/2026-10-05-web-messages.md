# Web Messages (chat parity slice 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the web app a Messages inbox — DMs, band channels and topic threads in a two-pane page — with a header icon + live unread badge, DM creation from a contact picker and from band member rows, and bell entries for direct messages.

**Architecture:** The backend already has every endpoint; we move the list assembly into the shared `ConversationPresenter`, expose the remaining mobile controller methods under session-auth web routes, add a thin `Web\MessagesController` for the Inertia page + unread count, and a database-only `DirectMessageReceived` notification. On the web, the Vuex user store gains `chatUnread`/`chatSignal`; the authenticated layout owns the realtime subscriptions and feeds the store; the Messages page is server-seeded, refreshes its list over axios on store signals, and mounts the existing `ConversationThread` for the selected conversation.

**Tech Stack:** Laravel 10 (PHP 8.3, Docker `docker compose exec -T app …`), Spatie permissions, Echo + Pusher, Inertia + Vue 3, PrimeVue 4 (Aura) + Tailwind (`darkMode` = media), Vuex, Ziggy `route()`, axios, luxon, Vitest + @vue/test-utils (jsdom).

**Spec:** `docs/superpowers/specs/2026-10-05-web-messages-design.md`

## Global Constraints

- Branch `feat/web-messages` (off `origin/staging`, already created); PR targets **staging**.
- **Mobile wire contract frozen**: conversation summary shape (`id,type,band_id,title,topic_type,last_message_preview,last_message_at,unread_count,can_moderate`), ThreadPage, `MessageFormatter`, the six stream event names, and the mobile push payload/`SendUserPush::dispatch` arguments must not change. All suites under `tests/Feature/Api/Mobile/Chat/` stay green and unedited.
- All PHP in the container: `docker compose exec -T app php artisan test <path>`.
- **Dark mode is a requirement**: every new colour class pair uses the spec §7 palette (`bg-white dark:bg-slate-800`, `bg-gray-50 dark:bg-slate-900`, `border-gray-200 dark:border-slate-600`, `text-gray-900 dark:text-gray-50`, `text-gray-500 dark:text-gray-400`, selected `bg-blue-50 dark:bg-blue-900/40`, hover `hover:bg-gray-100 dark:hover:bg-slate-700`); the badge pill is `bg-red-500 text-white` in both schemes. Browser verification runs in light AND emulated dark.
- Vitest runs in production mode in CI: assert via DOM / props / listener spies only — never `wrapper.vm` or `wrapper.emitted()`. `.vue` files 2-space indent; `.js` files tabs (match the neighbouring file).
- Only the authenticated layout subscribes to `App.Models.User.{id}`; pages must never `Echo.leave()` it. Pages consume realtime through the Vuex store (`chatSignal`).
- Web route names: `messages.index` (page, optional `{conversation}`), `chat.conversations.index`, `chat.conversations.dm`, `chat.contacts`, `chat.conversations.delivered`, `chat.unread-count`.
- `DirectMessageReceived` is database-only, DM conversations only, recipient = the other participant, `routeParams = ['conversation' => id]`.
- Commit after every task; end commit messages with:
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
  ```

---

## File structure

**Backend (create)**
- `app/Http/Controllers/Web/MessagesController.php` — Inertia page + unread count.
- `app/Notifications/DirectMessageReceived.php`
- `tests/Feature/Services/Chat/ConversationPresenterListTest.php`
- `tests/Feature/Web/Chat/MessagesPageTest.php`
- `tests/Feature/Web/Chat/DirectMessageReceivedTest.php`

**Backend (modify)**
- `app/Services/Chat/ConversationPresenter.php` — `listFor()`, `unreadTotalFor()`, `visibleTopics()` (moved).
- `app/Http/Controllers/Api/Mobile/ConversationsController.php` — `index()` delegates; `visibleTopics()` deleted.
- `routes/chat.php` — six new routes.
- `app/Jobs/ProcessChatMessagePush.php` — DM bell dispatch.
- `tests/Feature/Web/Chat/ChatWebRoutesTest.php` — new route coverage.

**Frontend (create)** under `resources/js/`
- `Components/Chat/MessagesNavIcon.vue` (+ `tests/components/messagesnavicon.test.js`)
- `Pages/Messages/Index.vue` (+ `tests/pages/messagesindex.test.js`)
- `Pages/Messages/Components/ConversationRow.vue` (+ `tests/components/conversationrow.test.js`)
- `Pages/Messages/Components/ConversationList.vue`
- `Pages/Messages/Components/NewMessageDialog.vue` (+ `tests/components/newmessagedialog.test.js`)
- `tests/store/userStore.test.js`

**Frontend (modify)**
- `Store/userStore.js` — `chatUnread`, `chatSignal`, `fetchChatUnread`, `signalChatChange`.
- `Layouts/Authenticated.vue` — icon in both headers, user-channel + band-signal `message` handling.
- `Pages/Band/Components/EditMembers.vue` — Message buttons (+ `tests/components/editmembers.test.js`).

---

### Task 1: `listFor()` / `unreadTotalFor()` on the presenter; mobile index delegates

**Files:**
- Modify: `app/Services/Chat/ConversationPresenter.php`
- Modify: `app/Http/Controllers/Api/Mobile/ConversationsController.php` (`index()` ~L40-60, `visibleTopics()` ~L79-100)
- Create: `tests/Feature/Services/Chat/ConversationPresenterListTest.php`

**Interfaces:**
- Produces: `ConversationPresenter::listFor(User $user): \Illuminate\Support\Collection` (array rows in the frozen summary shape, sorted by `last_message_at` desc); `ConversationPresenter::unreadTotalFor(User $user): int`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Services/Chat/ConversationPresenterListTest.php`:

```php
<?php

namespace Tests\Feature\Services\Chat;

use App\Services\Chat\ConversationPresenter;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ConversationPresenterListTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_list_contains_lazily_created_band_channel_and_dms_sorted_by_last_message(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $service = app(ConversationService::class);

        $dm = $service->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hey there']);

        $rows = app(ConversationPresenter::class)->listFor($owner);

        $this->assertCount(2, $rows);
        $this->assertSame('dm', $rows[0]['type'], 'conversation with the newest message sorts first');
        $this->assertSame('band', $rows[1]['type']);
        $this->assertSame($band->name, $rows[1]['title']);
        $this->assertSame(1, $rows[0]['unread_count']);
        $this->assertSame(
            ['id', 'type', 'band_id', 'title', 'topic_type', 'last_message_preview', 'last_message_at', 'unread_count', 'can_moderate'],
            array_keys($rows[0]),
        );
    }

    public function test_topic_threads_are_listed_only_when_they_have_messages_and_are_visible(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events', 'read:bookings']);
        $service = app(ConversationService::class);

        $eventWithMsg = $this->makeBookingEvent($band);
        $eventEmpty   = $this->makeBookingEvent($band);
        $service->topicFor($eventWithMsg)->messages()->create(['user_id' => $owner->id, 'body' => 'load-in 5']);
        $service->topicFor($eventEmpty); // created, never posted in

        $booking = $eventWithMsg->eventable;
        $service->topicFor($booking)->messages()->create(['user_id' => $owner->id, 'body' => 'deposit paid']);

        $sub = $this->makeSubAssignedTo($band, $eventWithMsg);

        $memberRows = app(ConversationPresenter::class)->listFor($member);
        $this->assertCount(3, $memberRows, 'band channel + event thread + booking thread');
        $this->assertEqualsCanonicalizing(['band', 'topic', 'topic'], $memberRows->pluck('type')->all());

        $subRows = app(ConversationPresenter::class)->listFor($sub);
        $this->assertCount(1, $subRows, 'subs: no band channel, no booking thread, only the entitled event thread');
        $this->assertSame('event', $subRows[0]['topic_type']);
    }

    public function test_unread_total_sums_every_row(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events']);
        $service = app(ConversationService::class);

        $dm = $service->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'a']);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'b']);
        $service->bandChannelFor($band)->messages()->create(['user_id' => $member->id, 'body' => 'c']);
        $service->topicFor($this->makeBookingEvent($band))->messages()->create(['user_id' => $member->id, 'body' => 'd']);

        $this->assertSame(4, app(ConversationPresenter::class)->unreadTotalFor($owner));
        $this->assertSame(0, app(ConversationPresenter::class)->unreadTotalFor($member));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Services/Chat/ConversationPresenterListTest.php`
Expected: FAIL — `Call to undefined method App\Services\Chat\ConversationPresenter::listFor()`.

- [ ] **Step 3: Add the methods to the presenter**

In `app/Services/Chat/ConversationPresenter.php` add imports `use App\Models\Bands;` (if not present), `use Illuminate\Database\Eloquent\Relations\MorphTo;`, and add these public/private methods (place them above `prefetch()`):

```php
    /**
     * Every conversation the user can see, for the inbox / Messages list:
     * one band channel per owned/member band (lazily created so it is
     * always present), the user's DMs, and every topic thread they can view
     * that someone has actually posted in. One prefetch for all ids; rows
     * in the frozen summary shape, newest message first.
     *
     * @return Collection<int, array>
     */
    public function listFor(User $user): Collection
    {
        $channels = $user->bands()->unique('id')->values()
            ->map(fn ($band) => $this->conversations->bandChannelFor($band));

        $dms = Conversation::where('type', Conversation::TYPE_DM)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $all = $channels->concat($dms)->concat($this->visibleTopics($user));

        $prefetch = $this->prefetch($all->pluck('id'), $user);

        return $all->map(fn (Conversation $c) => $this->summarize($c, $user, $prefetch))
            ->sortByDesc(fn ($row) => $row['last_message_at'] ?? '')
            ->values();
    }

    /** Sum of unread_count across listFor() — the header badge number. */
    public function unreadTotalFor(User $user): int
    {
        return (int) $this->listFor($user)->sum('unread_count');
    }

    /**
     * Topic threads the user may see. Candidates are narrowed in SQL to the
     * bands the user has any standing in (owner/member/sub) and to threads
     * someone has posted in (soft-deleted messages still count — a thread
     * whose only message was deleted stays listed with a null preview).
     * Final visibility is ConversationPolicy; the conversable morph is
     * eager-loaded because the policy and topicTitle() read it per row.
     *
     * @return Collection<int, Conversation>
     */
    private function visibleTopics(User $user): Collection
    {
        $bandIds = $user->allBands()->pluck('id');

        if ($bandIds->isEmpty()) {
            return collect();
        }

        return Conversation::where('type', Conversation::TYPE_TOPIC)
            ->whereIn('band_id', $bandIds)
            ->whereHas('messages', fn ($q) => $q->withTrashed())
            ->with(['conversable' => fn (MorphTo $morph) => $morph->morphWith([
                Rehearsal::class => ['events', 'rehearsalSchedule'],
            ])])
            ->get()
            ->filter(fn (Conversation $c) => $user->can('view', $c))
            ->values();
    }
```

- [ ] **Step 4: Make the mobile index delegate and delete its copy**

In `app/Http/Controllers/Api/Mobile/ConversationsController.php`:

Replace the whole body of `index()` with:
```php
    public function index(Request $request): JsonResponse
    {
        return response()->json(['conversations' => $this->presenter->listFor($request->user())]);
    }
```
Delete the private `visibleTopics()` method and its docblock. Remove the now-unused `MorphTo` import (keep `Rehearsal`, `Bookings`, `Events` — route bindings still use them; check with `grep -n "MorphTo\|Rehearsal\|Bookings\b\|Events\b" app/Http/Controllers/Api/Mobile/ConversationsController.php` and drop only imports with no remaining use).

- [ ] **Step 5: Run the new test and the mobile index suites**

Run: `docker compose exec -T app php artisan test tests/Feature/Services/Chat/ConversationPresenterListTest.php tests/Feature/Api/Mobile/Chat/ConversationsIndexTest.php tests/Feature/Api/Mobile/Chat/ConversationsIndexTopicsTest.php tests/Feature/Services/Chat/ConversationPresenterTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Chat/ConversationPresenter.php app/Http/Controllers/Api/Mobile/ConversationsController.php tests/Feature/Services/Chat/ConversationPresenterListTest.php
git commit -m "refactor(chat): move conversation list assembly into ConversationPresenter::listFor"
```

---

### Task 2: Web routes + `Web\MessagesController` (Inertia page, unread count)

**Files:**
- Create: `app/Http/Controllers/Web/MessagesController.php`
- Modify: `routes/chat.php`
- Create: `tests/Feature/Web/Chat/MessagesPageTest.php`
- Modify: `tests/Feature/Web/Chat/ChatWebRoutesTest.php` (add tests at the end of the class)

**Interfaces:**
- Produces routes: `messages.index` (`GET messages/{conversation?}` → Inertia `Messages/Index` with props `conversations: array[]`, `initialConversationId: int|null`), `chat.conversations.index`, `chat.conversations.dm`, `chat.contacts`, `chat.conversations.delivered`, `chat.unread-count` (`{ count: int }`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Web/Chat/MessagesPageTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class MessagesPageTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_messages_page_renders_the_inbox_with_no_selection(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hi']);

        $this->actingAs($owner)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Index')
                ->has('conversations', 2)
                ->where('conversations.0.type', 'dm')
                ->where('conversations.0.unread_count', 1)
                ->where('initialConversationId', null));
    }

    public function test_messages_page_preselects_a_viewable_conversation(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->get(route('messages.index', ['conversation' => $dm->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('initialConversationId', $dm->id));
    }

    public function test_messages_page_403s_for_a_conversation_the_viewer_cannot_see(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('messages.index', ['conversation' => $dm->id]))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('messages.index'))->assertRedirect(route('login'));
    }
}
```

Append to `tests/Feature/Web/Chat/ChatWebRoutesTest.php` (inside the class, before the final `}`):

```php
    public function test_inbox_routes_are_registered(): void
    {
        foreach (['messages.index', 'chat.conversations.index', 'chat.conversations.dm', 'chat.contacts', 'chat.conversations.delivered', 'chat.unread-count'] as $name) {
            $this->assertTrue(Route::has($name), "missing route {$name}");
        }
    }

    public function test_list_dm_contacts_delivered_and_unread_count_work_with_a_session(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $contacts = $this->actingAs($owner)->getJson(route('chat.contacts'))->assertOk();
        $this->assertContains($member->id, collect($contacts->json('contacts'))->pluck('id')->all());

        $dm = $this->actingAs($owner)
            ->postJson(route('chat.conversations.dm'), ['user_id' => $member->id])
            ->assertOk();
        $this->assertSame('dm', $dm->json('conversation.type'));

        $this->actingAs($member)
            ->postJson(route('chat.conversations.messages.store', $dm->json('conversation.id')), ['body' => 'yo'])
            ->assertCreated();

        $this->actingAs($owner)->getJson(route('chat.unread-count'))->assertOk()->assertJson(['count' => 1]);

        $list = $this->actingAs($owner)->getJson(route('chat.conversations.index'))->assertOk();
        $this->assertSame(1, collect($list->json('conversations'))->firstWhere('type', 'dm')['unread_count']);

        $this->actingAs($owner)->postJson(route('chat.conversations.delivered'))->assertNoContent();
    }

    public function test_dm_requires_a_shared_band(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $stranger = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.dm'), ['user_id' => $stranger->id])
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/MessagesPageTest.php tests/Feature/Web/Chat/ChatWebRoutesTest.php`
Expected: FAIL — `Route [messages.index] not defined.` (and the registration assertion).

Also check for a pre-existing `/messages` web route that would collide: `grep -rn "'messages\|\"messages\|/messages" routes/*.php`. If one exists, STOP and report (do not override it).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Web/MessagesController.php`:

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Chat\ConversationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The web Messages inbox. The list/thread/DM/contacts JSON endpoints are the
 * mobile controllers under routes/chat.php; this class only renders the page
 * and serves the header badge count.
 */
class MessagesController extends Controller
{
    public function __construct(private readonly ConversationPresenter $presenter) {}

    /** GET /messages/{conversation?} */
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        if ($conversation) {
            $this->authorize('view', $conversation);
        }

        return Inertia::render('Messages/Index', [
            'conversations'         => $this->presenter->listFor($request->user()),
            'initialConversationId' => $conversation?->id,
        ]);
    }

    /** GET /chat/unread-count → { count } */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->presenter->unreadTotalFor($request->user())]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/chat.php`, add `use App\Http\Controllers\Web\MessagesController;` to the imports, and append a second group after the existing `chat.` group:

```php
// ── Inbox (slice 2) ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // The page itself is not under the chat. name prefix: bell deep links and
    // the header icon use `messages.index`.
    Route::get('messages/{conversation?}', [MessagesController::class, 'index'])->name('messages.index');

    Route::name('chat.')->group(function () {
        Route::get('chat/conversations', [ConversationsController::class, 'index'])->name('conversations.index');
        Route::post('chat/conversations/dm', [ConversationsController::class, 'storeDm'])->name('conversations.dm');
        Route::get('chat/contacts', [ConversationsController::class, 'contacts'])->name('contacts');
        Route::post('chat/conversations/delivered', [ConversationsController::class, 'delivered'])->name('conversations.delivered');
        Route::get('chat/unread-count', [MessagesController::class, 'unreadCount'])->name('unread-count');
    });
});
```

- [ ] **Step 5: Run the tests**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/MessagesPageTest.php tests/Feature/Web/Chat/ChatWebRoutesTest.php`
Expected: PASS. (`Inertia::render` of a page component that doesn't exist yet is fine in tests — Inertia testing doesn't resolve Vue files.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/MessagesController.php routes/chat.php tests/Feature/Web/Chat/MessagesPageTest.php tests/Feature/Web/Chat/ChatWebRoutesTest.php
git commit -m "feat(chat): web Messages page route, inbox JSON routes and unread-count endpoint"
```

---

### Task 3: `DirectMessageReceived` bell notification

**Files:**
- Create: `app/Notifications/DirectMessageReceived.php`
- Modify: `app/Jobs/ProcessChatMessagePush.php`
- Create: `tests/Feature/Web/Chat/DirectMessageReceivedTest.php`

**Interfaces:**
- Produces: `DirectMessageReceived::__construct(Message $message, Conversation $conversation)`; `toArray()` → `['text','route' => 'messages.index','routeParams' => ['conversation' => id],'conversation_id','message_id']`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Web/Chat/DirectMessageReceivedTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Jobs\SendUserPush;
use App\Notifications\CommentPosted;
use App\Notifications\DirectMessageReceived;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class DirectMessageReceivedTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake([SendUserPush::class]);
    }

    public function test_dm_notifies_only_the_other_participant_database_only(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $dm), ['body' => 'Soundcheck moved to 4, can you make it?'])
            ->assertCreated();

        Notification::assertSentTo($member, DirectMessageReceived::class, function (DirectMessageReceived $n, array $channels) use ($owner, $dm) {
            $data = $n->toArray($owner);

            return $channels === ['database']
                && $data['route'] === 'messages.index'
                && $data['routeParams'] === ['conversation' => $dm->id]
                && $data['text'] === $owner->name . ': Soundcheck moved to 4, can you make it?'
                && $data['conversation_id'] === $dm->id;
        });
        Notification::assertNotSentTo($owner, DirectMessageReceived::class);
    }

    public function test_bell_route_params_resolve_to_the_inbox_url(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $data = (new DirectMessageReceived($message, $dm))->toArray($member);

        $this->assertStringEndsWith('/messages/' . $dm->id, route($data['route'], $data['routeParams']));
    }

    public function test_image_only_dm_uses_photo_placeholder(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $dm), [
                'images' => [\Illuminate\Http\UploadedFile::fake()->image('p.jpg', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        Notification::assertSentTo($member, DirectMessageReceived::class, fn (DirectMessageReceived $n) =>
            str_ends_with($n->toArray($member)['text'], ': 📷 Photo'));
    }

    public function test_band_channel_messages_create_no_bell_entry_and_topics_still_use_comment_posted(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events']);
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $topic   = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $channel), ['body' => 'all'])->assertCreated();
        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'cmt'])->assertCreated();

        Notification::assertNotSentTo($member, DirectMessageReceived::class);
        Notification::assertSentTo($member, CommentPosted::class);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/DirectMessageReceivedTest.php`
Expected: FAIL — `Class "App\Notifications\DirectMessageReceived" not found`.

- [ ] **Step 3: Create the notification**

`app/Notifications/DirectMessageReceived.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Web bell entry for a new direct message. Database-only (no mail) and
 * shaped for Layouts/Authenticated.vue (`text`, `route`, `routeParams`);
 * the link opens the inbox with this conversation selected.
 */
class DirectMessageReceived extends Notification
{
    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $body    = $this->message->body;
        $snippet = ($body !== null && trim($body) !== '') ? Str::limit(trim($body), 80) : '📷 Photo';
        $sender  = $this->message->user->name ?? 'Deleted user';

        return [
            'text'            => "{$sender}: {$snippet}",
            'route'           => 'messages.index',
            'routeParams'     => ['conversation' => $this->conversation->id],
            'conversation_id' => $this->conversation->id,
            'message_id'      => $this->message->id,
        ];
    }
}
```

- [ ] **Step 4: Dispatch it from the push job**

In `app/Jobs/ProcessChatMessagePush.php`: add `use App\Notifications\DirectMessageReceived;`. Next to the existing `$isTopic` line add `$isDm = $conversation->type === Conversation::TYPE_DM;`. In the recipient loop, directly after the existing `if ($isTopic) { … }` block (keep that block and the `SendUserPush::dispatch(...)` line untouched), add:

```php
            // Web bell entry for DMs (database only). Band-channel chatter
            // deliberately stays off the bell — the Messages badge is its signal.
            if ($isDm) {
                $users->get($userId)?->notify(new DirectMessageReceived($message, $conversation));
            }
```
(`$users` is the `whereIn(...)->keyBy('id')` map already built before the loop in slice 1.)

- [ ] **Step 5: Run the tests plus the push + comment suites**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/DirectMessageReceivedTest.php tests/Feature/Web/Chat/CommentPostedNotificationTest.php tests/Feature/Api/Mobile/Chat/ChatPushTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/DirectMessageReceived.php app/Jobs/ProcessChatMessagePush.php tests/Feature/Web/Chat/DirectMessageReceivedTest.php
git commit -m "feat(chat): database-only DirectMessageReceived bell notification"
```

---

### Task 4: Store state, header icon, layout realtime wiring

**Files:**
- Modify: `resources/js/Store/userStore.js`
- Create: `resources/js/Components/Chat/MessagesNavIcon.vue`
- Modify: `resources/js/Layouts/Authenticated.vue`
- Create: `resources/js/tests/store/userStore.test.js`
- Create: `resources/js/tests/components/messagesnavicon.test.js`

**Interfaces:**
- Produces: Vuex `user` module state `chatUnread: number`, `chatSignal: number`; mutations `SET_CHAT_UNREAD(n)`, `BUMP_CHAT_SIGNAL()`; actions `fetchChatUnread()` (GET `route('chat.unread-count')`), `signalChatChange()` (bump + fetch). `MessagesNavIcon.vue` props `count: Number = 0`, `href: String (required)`.

- [ ] **Step 1: Write the failing tests**

`resources/js/tests/store/userStore.test.js`:

```js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createStore } from 'vuex';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: null } } }) }));
import axios from 'axios';
import userStore from '../../Store/userStore';

describe('user store — chat unread + signal', () => {
	let store;
	beforeEach(() => {
		axios.get.mockReset();
		vi.stubGlobal('route', (name) => `/r/${name}`);
		store = createStore({ modules: { user: userStore } });
	});

	it('fetchChatUnread stores the count from chat.unread-count', async () => {
		axios.get.mockResolvedValueOnce({ data: { count: 7 } });
		await store.dispatch('user/fetchChatUnread');
		expect(axios.get).toHaveBeenCalledWith('/r/chat.unread-count');
		expect(store.state.user.chatUnread).toBe(7);
	});

	it('fetchChatUnread keeps the last value when the request fails', async () => {
		axios.get.mockResolvedValueOnce({ data: { count: 3 } });
		await store.dispatch('user/fetchChatUnread');
		axios.get.mockRejectedValueOnce(new Error('offline'));
		await store.dispatch('user/fetchChatUnread');
		expect(store.state.user.chatUnread).toBe(3);
	});

	it('signalChatChange bumps chatSignal and refetches the count', async () => {
		axios.get.mockResolvedValue({ data: { count: 1 } });
		await store.dispatch('user/signalChatChange');
		await store.dispatch('user/signalChatChange');
		expect(store.state.user.chatSignal).toBe(2);
		expect(axios.get).toHaveBeenCalledTimes(2);
	});
});
```

`resources/js/tests/components/messagesnavicon.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MessagesNavIcon from '@/Components/Chat/MessagesNavIcon.vue';

const stubs = { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } };

describe('MessagesNavIcon', () => {
	it('links to the inbox and hides the pill at zero', () => {
		const w = mount(MessagesNavIcon, { props: { count: 0, href: '/messages' }, global: { stubs } });
		expect(w.find('a').attributes('href')).toBe('/messages');
		expect(w.find('[data-test="chat-unread-pill"]').exists()).toBe(false);
		expect(w.find('a').attributes('aria-label')).toBe('Messages');
	});

	it('shows the count in the pill and the aria label', () => {
		const w = mount(MessagesNavIcon, { props: { count: 4, href: '/messages' }, global: { stubs } });
		expect(w.find('[data-test="chat-unread-pill"]').text()).toBe('4');
		expect(w.find('a').attributes('aria-label')).toBe('Messages, 4 unread');
	});
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npx vitest run resources/js/tests/store/userStore.test.js resources/js/tests/components/messagesnavicon.test.js`
Expected: FAIL — `chatUnread` undefined / component not found.

- [ ] **Step 3: Extend the store**

In `resources/js/Store/userStore.js`:

`state`:
```js
  state: () => ({
    navigation: null,
    notifications: [],
    chatUnread: 0,
    chatSignal: 0,
  }),
```
`mutations` (add):
```js
    SET_CHAT_UNREAD(state, count) {
      state.chatUnread = Number(count) || 0
    },
    BUMP_CHAT_SIGNAL(state) {
      state.chatSignal += 1
    },
```
`actions` (add):
```js
    async fetchChatUnread({ commit }) {
      try {
        const { data } = await axios.get(route('chat.unread-count'))
        commit('SET_CHAT_UNREAD', data.count ?? 0)
      } catch (error) {
        // Badge is best-effort: keep the last known count.
      }
    },

    // A message changed somewhere the user can see (user channel for DMs,
    // band channel for everything else). Pages watch chatSignal to refresh.
    async signalChatChange({ commit, dispatch }) {
      commit('BUMP_CHAT_SIGNAL')
      await dispatch('fetchChatUnread')
    },
```

- [ ] **Step 4: Create `MessagesNavIcon.vue`**

`resources/js/Components/Chat/MessagesNavIcon.vue`:

```vue
<template>
  <Link
    :href="href"
    class="relative inline-flex items-center p-2 rounded-md text-gray-400 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
    :aria-label="count > 0 ? `Messages, ${count} unread` : 'Messages'"
  >
    <i class="pi pi-comments text-xl" />
    <span
      v-if="count > 0"
      data-test="chat-unread-pill"
      class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-none"
    >{{ count }}</span>
  </Link>
</template>

<script setup>
defineProps({
  count: { type: Number, default: 0 },
  href: { type: String, required: true },
});
</script>
```
(`Link` is registered globally in `app.js`; the test stubs it.)

- [ ] **Step 5: Wire the layout**

In `resources/js/Layouts/Authenticated.vue`:

1. Import + register: add `import MessagesNavIcon from '@/Components/Chat/MessagesNavIcon.vue';` next to the other component imports and `MessagesNavIcon,` to the `components` map.
2. `computed`: change `...mapState("user", ["navigation", "notifications"])` to `...mapState("user", ["navigation", "notifications", "chatUnread"])`.
3. `methods`: add `"fetchChatUnread"` and `"signalChatChange"` to the `...mapActions("user", [...])` list, and add:
```js
        onChatSignal() {
            // Coalesce a burst of message signals into one badge refetch.
            clearTimeout(this._chatSignalTimer);
            this._chatSignalTimer = setTimeout(() => this.signalChatChange(), 300);
        },
```
4. `mounted()`: add `this.fetchChatUnread();` as the first line.
5. `beforeUnmount()`: add `clearTimeout(this._chatSignalTimer);`.
6. `subscribeToUserChannel()`: chain a second listener after the `.SetlistSessionStarted` one:
```js
                .listen('.user.data-changed', (p) => {
                    if (p?.model === 'message') this.onChatSignal();
                });
```
(the `window.Echo.private(...)` call returns the channel; both `.listen` calls chain on it.)
7. `subscribeToBandSignals()`: replace `this._bandUnsubscribe = subscribeBandSignals(bandIds, this._bellRefresher.onSignal);` with:
```js
            this._bandUnsubscribe = subscribeBandSignals(bandIds, (payload) => {
                this._bellRefresher.onSignal(payload);
                if (payload?.model === 'message') this.onChatSignal();
            });
```
8. Desktop header: inside `<div class="hidden sm:flex sm:items-center sm:ml-6 …">`, directly BEFORE the `<div class="ml-3 relative">` that wraps the bell `breeze-dropdown`, add:
```html
            <MessagesNavIcon
              :count="chatUnread"
              :href="route('messages.index')"
              class="ml-3"
            />
```
9. Mobile header: inside `<div class="flex items-center sm:hidden pr-4">`, as the first child before the bell `breeze-dropdown`, add:
```html
            <MessagesNavIcon
              :count="chatUnread"
              :href="route('messages.index')"
              class="mr-1"
            />
```

- [ ] **Step 6: Run the tests and build**

Run: `npx vitest run resources/js/tests/store/userStore.test.js resources/js/tests/components/messagesnavicon.test.js` — PASS (5 tests).
Run: `npx vite build 2>&1 | tail -3` — no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Store/userStore.js resources/js/Components/Chat/MessagesNavIcon.vue resources/js/Layouts/Authenticated.vue resources/js/tests/store/userStore.test.js resources/js/tests/components/messagesnavicon.test.js
git commit -m "feat(chat): header Messages icon with live unread badge driven by the user store"
```

---

### Task 5: `ConversationRow.vue` + `ConversationList.vue`

**Files:**
- Create: `resources/js/Pages/Messages/Components/ConversationRow.vue`
- Create: `resources/js/Pages/Messages/Components/ConversationList.vue`
- Create: `resources/js/tests/components/conversationrow.test.js`

**Interfaces:**
- `ConversationRow.vue` props `conversation` (summary row), `selected: Boolean`; emits `select(id)`.
- `ConversationList.vue` props `conversations: Array`, `selectedId: Number|null`; emits `select(id)`, `new`.

- [ ] **Step 1: Write the failing test**

`resources/js/tests/components/conversationrow.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ConversationRow from '@/Pages/Messages/Components/ConversationRow.vue';

function row(overrides = {}) {
	return {
		id: 1, type: 'dm', band_id: null, title: 'Taylor Campo', topic_type: null,
		last_message_preview: 'see you at 5', last_message_at: new Date(Date.now() - 60_000).toISOString(),
		unread_count: 0, can_moderate: false, ...overrides,
	};
}

describe('ConversationRow', () => {
	it('renders title, preview, relative time and no pill at zero', () => {
		const w = mount(ConversationRow, { props: { conversation: row() } });
		expect(w.text()).toContain('Taylor Campo');
		expect(w.text()).toContain('see you at 5');
		expect(w.text()).toMatch(/minute|ago/);
		expect(w.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(w.find('i').classes()).toContain('pi-user');
	});

	it('maps icons by type and shows the pill', () => {
		const cases = [
			[{ type: 'band' }, 'pi-users'],
			[{ type: 'topic', topic_type: 'booking' }, 'pi-briefcase'],
			[{ type: 'topic', topic_type: 'event' }, 'pi-calendar'],
			[{ type: 'topic', topic_type: 'rehearsal' }, 'pi-headphones'],
			[{ type: 'topic', topic_type: null }, 'pi-comment'],
		];
		for (const [o, icon] of cases) {
			const w = mount(ConversationRow, { props: { conversation: row({ ...o, unread_count: 3 }) } });
			expect(w.find('i').classes(), icon).toContain(icon);
			expect(w.find('[data-test="unread-pill"]').text()).toBe('3');
		}
	});

	it('falls back for empty previews and emits select on click', async () => {
		const onSelect = vi.fn();
		const w = mount(ConversationRow, { props: { conversation: row({ id: 9, last_message_preview: null, last_message_at: null }), onSelect } });
		expect(w.text()).toContain('No messages yet');
		await w.find('button').trigger('click');
		expect(onSelect).toHaveBeenCalledWith(9);
	});

	it('highlights when selected', () => {
		const w = mount(ConversationRow, { props: { conversation: row(), selected: true } });
		expect(w.find('button').attributes('aria-current')).toBe('true');
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/components/conversationrow.test.js`
Expected: FAIL — component not found.

- [ ] **Step 3: Create `ConversationRow.vue`**

```vue
<template>
  <button
    type="button"
    class="w-full text-left flex items-start gap-3 px-3 py-2.5 rounded-lg transition-colors"
    :class="selected
      ? 'bg-blue-50 dark:bg-blue-900/40'
      : 'hover:bg-gray-100 dark:hover:bg-slate-700'"
    :aria-current="selected ? 'true' : undefined"
    @click="$emit('select', conversation.id)"
  >
    <span class="mt-0.5 inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 shrink-0">
      <i :class="['pi', icon]" />
    </span>
    <span class="min-w-0 flex-1">
      <span class="flex items-center justify-between gap-2">
        <span
          class="truncate font-medium"
          :class="conversation.unread_count > 0 ? 'text-gray-900 dark:text-gray-50' : 'text-gray-800 dark:text-gray-200'"
        >{{ conversation.title }}</span>
        <span
          v-if="timeLabel"
          class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
        >{{ timeLabel }}</span>
      </span>
      <span class="flex items-center justify-between gap-2 mt-0.5">
        <span class="truncate text-sm text-gray-500 dark:text-gray-400">{{ preview }}</span>
        <span
          v-if="conversation.unread_count > 0"
          data-test="unread-pill"
          class="shrink-0 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-semibold"
        >{{ conversation.unread_count }}</span>
      </span>
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
  conversation: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});
defineEmits(['select']);

const ICONS = { dm: 'pi-user', band: 'pi-users', booking: 'pi-briefcase', event: 'pi-calendar', rehearsal: 'pi-headphones' };

const icon = computed(() => {
  const c = props.conversation;
  if (c.type === 'topic') return ICONS[c.topic_type] ?? 'pi-comment';
  return ICONS[c.type] ?? 'pi-comment';
});

const preview = computed(() => props.conversation.last_message_preview ?? 'No messages yet');

const timeLabel = computed(() => {
  const at = props.conversation.last_message_at;
  if (!at) return '';
  return DateTime.fromISO(at).toRelative({ style: 'narrow' }) ?? '';
});
</script>
```

- [ ] **Step 4: Create `ConversationList.vue`**

```vue
<template>
  <div class="flex flex-col h-full min-h-0 bg-white dark:bg-slate-800">
    <div class="p-3 border-b border-gray-200 dark:border-slate-600 flex items-center gap-2">
      <span class="relative flex-1">
        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm" />
        <input
          v-model="query"
          type="search"
          placeholder="Search messages"
          aria-label="Search conversations"
          class="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </span>
      <Button
        icon="pi pi-pencil"
        rounded
        aria-label="New message"
        data-test="new-message"
        @click="$emit('new')"
      />
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto p-2 space-y-0.5">
      <p
        v-if="!filtered.length"
        class="text-sm text-gray-500 dark:text-gray-400 text-center py-8"
      >
        {{ conversations.length ? 'No conversations match.' : 'No messages yet.' }}
      </p>
      <ConversationRow
        v-for="c in filtered"
        :key="c.id"
        :conversation="c"
        :selected="c.id === selectedId"
        @select="$emit('select', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ConversationRow from './ConversationRow.vue';

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
});
defineEmits(['select', 'new']);

const query = ref('');

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return props.conversations;
  return props.conversations.filter((c) =>
    (c.title ?? '').toLowerCase().includes(q) || (c.last_message_preview ?? '').toLowerCase().includes(q));
});
</script>
```

- [ ] **Step 5: Run the test and build**

Run: `npx vitest run resources/js/tests/components/conversationrow.test.js` — PASS (4 tests). `npx vite build 2>&1 | tail -3` — clean.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Messages/Components/ConversationRow.vue resources/js/Pages/Messages/Components/ConversationList.vue resources/js/tests/components/conversationrow.test.js
git commit -m "feat(chat): conversation list and row components for the web inbox"
```

---

### Task 6: `NewMessageDialog.vue`

**Files:**
- Create: `resources/js/Pages/Messages/Components/NewMessageDialog.vue`
- Create: `resources/js/tests/components/newmessagedialog.test.js`

**Interfaces:**
- Props `visible: Boolean`; emits `update:visible(bool)`, `created(conversation)`. Loads `route('chat.contacts')` when opened; posts `route('chat.conversations.dm')` `{ user_id }`.

- [ ] **Step 1: Write the failing test**

`resources/js/tests/components/newmessagedialog.test.js`:

```js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
import axios from 'axios';
import NewMessageDialog from '@/Pages/Messages/Components/NewMessageDialog.vue';

const stubs = {
	Dialog: { props: ['visible'], template: '<div v-if="visible"><slot name="header" /><slot /></div>' },
};

const contacts = [
	{ id: 20, name: 'Taylor Campo', avatar_url: null, context: 'Three Thirty Seven', is_sub: false },
	{ id: 31, name: 'Sam Sub', avatar_url: null, context: 'Sub — Three Thirty Seven', is_sub: true },
];

describe('NewMessageDialog', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset();
		vi.stubGlobal('route', (name) => `/r/${name}`);
	});

	it('loads contacts when opened, filters by name, flags subs', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		const w = mount(NewMessageDialog, { props: { visible: true }, global: { stubs } });
		await flushPromises();

		expect(axios.get).toHaveBeenCalledWith('/r/chat.contacts');
		expect(w.text()).toContain('Taylor Campo');
		expect(w.text()).toContain('Sub');

		await w.find('input').setValue('tay');
		expect(w.findAll('[data-test="contact-row"]')).toHaveLength(1);
	});

	it('choosing a contact creates the DM and emits created + closes', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		axios.post.mockResolvedValueOnce({ data: { conversation: { id: 77, type: 'dm', title: 'Taylor Campo', unread_count: 0 } } });
		const onCreated = vi.fn();
		const onUpdateVisible = vi.fn();
		const w = mount(NewMessageDialog, { props: { visible: true, onCreated, 'onUpdate:visible': onUpdateVisible }, global: { stubs } });
		await flushPromises();

		await w.find('[data-test="contact-row"]').trigger('click');
		await flushPromises();

		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.dm', { user_id: 20 });
		expect(onCreated).toHaveBeenCalledWith(expect.objectContaining({ id: 77 }));
		expect(onUpdateVisible).toHaveBeenCalledWith(false);
	});

	it('shows the server message when the DM cannot be created', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		axios.post.mockRejectedValueOnce({ response: { data: { message: 'You do not share a band with this user.' } } });
		const w = mount(NewMessageDialog, { props: { visible: true }, global: { stubs } });
		await flushPromises();
		await w.find('[data-test="contact-row"]').trigger('click');
		await flushPromises();
		expect(w.text()).toContain('You do not share a band with this user.');
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/components/newmessagedialog.test.js` — FAIL (component not found).

- [ ] **Step 3: Create the dialog**

`resources/js/Pages/Messages/Components/NewMessageDialog.vue`:

```vue
<template>
  <Dialog
    :visible="visible"
    modal
    header="New message"
    :style="{ width: '28rem', maxWidth: '95vw' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="space-y-3">
      <input
        v-model="query"
        type="search"
        placeholder="Search people"
        aria-label="Search people"
        class="w-full px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >

      <p
        v-if="error"
        class="text-sm text-red-600 dark:text-red-400"
      >
        {{ error }}
      </p>

      <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-slate-700">
        <p
          v-if="loading"
          class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center"
        >
          Loading people…
        </p>
        <p
          v-else-if="!filtered.length"
          class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center"
        >
          No one matches.
        </p>
        <button
          v-for="c in filtered"
          :key="c.id"
          type="button"
          data-test="contact-row"
          class="w-full text-left flex items-center justify-between gap-3 px-2 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md disabled:opacity-50"
          :disabled="creating"
          @click="choose(c)"
        >
          <span class="min-w-0">
            <span class="block truncate font-medium text-gray-900 dark:text-gray-50">{{ c.name }}</span>
            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ c.context }}</span>
          </span>
          <Tag
            v-if="c.is_sub"
            value="Sub"
            severity="secondary"
            rounded
          />
        </button>
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  visible: { type: Boolean, default: false },
});
const emit = defineEmits(['update:visible', 'created']);

const contacts = ref([]);
const query = ref('');
const loading = ref(false);
const creating = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(route('chat.contacts'));
    contacts.value = data.contacts ?? [];
  } catch (e) {
    error.value = 'Could not load people. Please try again.';
  } finally {
    loading.value = false;
  }
}

watch(() => props.visible, (v) => {
  if (v) {
    query.value = '';
    load();
  }
}, { immediate: true });

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return q ? contacts.value.filter((c) => c.name.toLowerCase().includes(q)) : contacts.value;
});

async function choose(contact) {
  creating.value = true;
  error.value = '';
  try {
    const { data } = await axios.post(route('chat.conversations.dm'), { user_id: contact.id });
    emit('created', data.conversation);
    emit('update:visible', false);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Could not start the conversation.';
  } finally {
    creating.value = false;
  }
}
</script>
```
(`Dialog` and `Tag` are globally registered in `app.js`; the test stubs `Dialog` and lets `Tag` resolve from `setup.js`'s PrimeVue plugin — if `Tag` is not globally registered in the test setup, add `Tag: { props: ['value'], template: '<span>{{ value }}</span>' }` to the test's `stubs`.)

- [ ] **Step 4: Run the test and build**

`npx vitest run resources/js/tests/components/newmessagedialog.test.js` — PASS (3). `npx vite build 2>&1 | tail -3` — clean.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Messages/Components/NewMessageDialog.vue resources/js/tests/components/newmessagedialog.test.js
git commit -m "feat(chat): new-message contact picker dialog"
```

---

### Task 7: `Pages/Messages/Index.vue` (two-pane inbox)

**Files:**
- Create: `resources/js/Pages/Messages/Index.vue`
- Create: `resources/js/tests/pages/messagesindex.test.js`

**Interfaces:**
- Consumes: `ConversationList` (T5), `NewMessageDialog` (T6), `ConversationThread` (slice 1: props `loadUrl`, `currentUserId`; emits `read`), store `user.chatSignal`, `user/fetchChatUnread`.
- Props from the server: `conversations: Array`, `initialConversationId: Number|null`.

- [ ] **Step 1: Write the failing test**

`resources/js/tests/pages/messagesindex.test.js`:

```js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const storeState = reactive({ user: { chatSignal: 0 } });
const dispatch = vi.fn();
vi.mock('vuex', () => ({ useStore: () => ({ state: storeState, dispatch }) }));
vi.mock('@inertiajs/vue3', () => ({
	usePage: () => ({ props: { auth: { user: { id: 10 } } } }),
	Head: { template: '<div />' },
}));
import axios from 'axios';
import MessagesIndex from '@/Pages/Messages/Index.vue';

const rows = [
	{ id: 1, type: 'band', title: 'Three Thirty Seven', topic_type: null, last_message_preview: 'gig!', last_message_at: '2026-10-05T10:00:00+00:00', unread_count: 2, can_moderate: true, band_id: 1 },
	{ id: 2, type: 'dm', title: 'Taylor Campo', topic_type: null, last_message_preview: 'yo', last_message_at: '2026-10-05T09:00:00+00:00', unread_count: 0, can_moderate: false, band_id: null },
];

const stubs = {
	ConversationThread: { props: ['loadUrl', 'currentUserId'], template: '<div data-test="thread" :data-url="loadUrl" @click="$emit(\'read\')" />' },
	NewMessageDialog: { props: ['visible'], template: '<div data-test="dialog" :data-visible="visible" />' },
};

function mountPage(props = {}) {
	return mount(MessagesIndex, {
		props: { conversations: rows, initialConversationId: null, ...props },
		global: { stubs },
	});
}

describe('Messages/Index', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset(); dispatch.mockReset();
		storeState.user.chatSignal = 0;
		vi.stubGlobal('route', (name, p) => `/r/${name}/${p ?? ''}`);
		vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
		axios.post.mockResolvedValue({ data: {} });
	});

	it('renders rows and the empty thread state', () => {
		const w = mountPage();
		expect(w.text()).toContain('Three Thirty Seven');
		expect(w.text()).toContain('Select a conversation');
		expect(w.find('[data-test="thread"]').exists()).toBe(false);
	});

	it('posts the delivered ack on mount', async () => {
		mountPage();
		await flushPromises();
		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.delivered/');
	});

	it('selecting a row mounts the thread for it and rewrites the URL', async () => {
		const w = mountPage();
		await w.findAll('button[aria-current], button').filter((b) => b.text().includes('Taylor Campo'))[0].trigger('click');
		await nextTick();
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/2');
		expect(window.history.replaceState).toHaveBeenCalledWith(null, '', '/r/messages.index/2');
		expect(w.text()).toContain('Direct message');
	});

	it('preselects initialConversationId', () => {
		const w = mountPage({ initialConversationId: 1 });
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/1');
		expect(w.text()).toContain('Band channel');
	});

	it('refreshes the list when the store signal changes', async () => {
		axios.get.mockResolvedValueOnce({ data: { conversations: [{ ...rows[1], unread_count: 5 }] } });
		const w = mountPage();
		await flushPromises();
		storeState.user.chatSignal += 1;
		await flushPromises();
		expect(axios.get).toHaveBeenCalledWith('/r/chat.conversations.index/');
		expect(w.find('[data-test="unread-pill"]').text()).toBe('5');
	});

	it('read from the thread zeroes that row and refetches the badge', async () => {
		const w = mountPage({ initialConversationId: 1 });
		expect(w.find('[data-test="unread-pill"]').text()).toBe('2');
		await w.find('[data-test="thread"]').trigger('click');
		await nextTick();
		expect(w.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(dispatch).toHaveBeenCalledWith('user/fetchChatUnread');
	});

	it('inserts and selects a conversation created from the dialog', async () => {
		const w = mountPage();
		await w.find('[data-test="new-message"]').trigger('click');
		expect(w.find('[data-test="dialog"]').attributes('data-visible')).toBe('true');
		w.findComponent({ name: 'NewMessageDialog' }).vm.$emit('created', { id: 99, type: 'dm', title: 'New Person', unread_count: 0, last_message_at: null, last_message_preview: null, topic_type: null, band_id: null, can_moderate: false });
		await nextTick();
		expect(w.text()).toContain('New Person');
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/99');
	});
});
```

Note: `findComponent(...).vm.$emit` is the stub emitting an event (driving the child's output), not reading component state — acceptable under the production-mode rule; everything asserted is DOM.

- [ ] **Step 2: Run it to verify it fails**

`npx vitest run resources/js/tests/pages/messagesindex.test.js` — FAIL (component not found).

- [ ] **Step 3: Create the page**

`resources/js/Pages/Messages/Index.vue`:

```vue
<template>
  <Head title="Messages" />
  <Container class="md:container md:mx-auto bg-transparent dark:bg-transparent">
    <div class="max-w-6xl mx-auto px-0 sm:px-4 py-0 sm:py-4">
      <div
        class="h-[calc(100vh-8rem)] min-h-[32rem] rounded-none sm:rounded-lg overflow-hidden border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 md:grid md:grid-cols-[20rem_1fr]"
      >
        <!-- List pane -->
        <aside
          class="h-full min-h-0 border-r border-gray-200 dark:border-slate-600"
          :class="mobileShowThread ? 'hidden md:block' : 'block'"
        >
          <ConversationList
            :conversations="rows"
            :selected-id="selectedId"
            @select="select"
            @new="dialogOpen = true"
          />
        </aside>

        <!-- Thread pane -->
        <section
          class="h-full min-h-0 flex flex-col bg-gray-50 dark:bg-slate-900"
          :class="mobileShowThread ? 'flex' : 'hidden md:flex'"
        >
          <template v-if="selected">
            <header class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800">
              <button
                type="button"
                class="md:hidden p-1 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700"
                aria-label="Back to conversations"
                data-test="back"
                @click="mobileShowThread = false"
              >
                <i class="pi pi-arrow-left" />
              </button>
              <div class="min-w-0">
                <h2 class="truncate font-semibold text-gray-900 dark:text-gray-50">
                  {{ selected.title }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ subtitle }}
                </p>
              </div>
            </header>
            <ConversationThread
              :key="selected.id"
              :load-url="route('chat.conversations.messages.index', selected.id)"
              :current-user-id="currentUserId"
              class="flex-1 min-h-0"
              @read="onRead(selected.id)"
            />
          </template>
          <div
            v-else
            class="flex-1 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
          >
            Select a conversation
          </div>
        </section>
      </div>
    </div>

    <NewMessageDialog
      v-model:visible="dialogOpen"
      @created="onCreated"
    />
  </Container>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useStore } from 'vuex';
import axios from 'axios';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import ConversationThread from '@/Components/Chat/ConversationThread.vue';
import ConversationList from './Components/ConversationList.vue';
import NewMessageDialog from './Components/NewMessageDialog.vue';

defineOptions({ layout: BreezeAuthenticatedLayout });

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  initialConversationId: { type: Number, default: null },
});

const page = usePage();
const store = useStore();

const currentUserId = computed(() => page.props.auth?.user?.id);
const rows = ref([...props.conversations]);
const selectedId = ref(props.initialConversationId);
const mobileShowThread = ref(props.initialConversationId !== null);
const dialogOpen = ref(false);

const selected = computed(() => rows.value.find((c) => c.id === selectedId.value) ?? null);

const subtitle = computed(() => {
  const c = selected.value;
  if (!c) return '';
  if (c.type === 'dm') return 'Direct message';
  if (c.type === 'band') return 'Band channel';
  return { booking: 'Booking thread', event: 'Event thread', rehearsal: 'Rehearsal thread' }[c.topic_type] ?? 'Thread';
});

function select(id) {
  selectedId.value = id;
  mobileShowThread.value = true;
  if (typeof window !== 'undefined') {
    window.history.replaceState(null, '', route('messages.index', id));
  }
}

// Keep rows in sync with the server; "delivered" means "my inbox has
// everything up to now" — same hook the mobile app fires on list fetch.
async function refreshList() {
  try {
    const { data } = await axios.get(route('chat.conversations.index'));
    rows.value = data.conversations ?? rows.value;
  } catch (e) {
    // keep the current rows; the next signal retries
  }
  axios.post(route('chat.conversations.delivered')).catch(() => {});
}

onMounted(() => {
  axios.post(route('chat.conversations.delivered')).catch(() => {});
});

watch(() => store.state.user.chatSignal, () => refreshList());

function onRead(id) {
  const i = rows.value.findIndex((c) => c.id === id);
  if (i !== -1) rows.value.splice(i, 1, { ...rows.value[i], unread_count: 0 });
  store.dispatch('user/fetchChatUnread');
}

function onCreated(conversation) {
  const i = rows.value.findIndex((c) => c.id === conversation.id);
  if (i === -1) rows.value = [conversation, ...rows.value];
  else rows.value.splice(i, 1, conversation);
  select(conversation.id);
}
</script>
```

If `Container` is not globally registered in the test environment, add `Container: { template: '<div><slot /></div>' }` to the test's `stubs` (it IS global at runtime via `app.js`).

- [ ] **Step 4: Run the test and build**

`npx vitest run resources/js/tests/pages/messagesindex.test.js` — PASS (7). `npx vite build 2>&1 | tail -3` — clean.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Messages/Index.vue resources/js/tests/pages/messagesindex.test.js
git commit -m "feat(chat): two-pane web Messages inbox page"
```

---

### Task 8: "Message" buttons on band member rows

**Files:**
- Modify: `resources/js/Pages/Band/Components/EditMembers.vue`
- Create: `resources/js/tests/components/editmembers.test.js`

**Interfaces:**
- Consumes `route('chat.conversations.dm')`, `route('messages.index', id)`, Inertia `router.visit`.

- [ ] **Step 1: Write the failing test**

`resources/js/tests/components/editmembers.test.js`:

```js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({
	router: { visit: vi.fn() },
	Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import EditMembers from '@/Pages/Band/Components/EditMembers.vue';

const band = {
	id: 1,
	members: [{ id: 5, user: { id: 20, name: 'Taylor Campo', email: 't@x.com' } }],
	owners: [{ id: 6, user: { id: 10, name: 'Edward Muller', email: 'e@x.com' } }],
	pending_invites: [],
};

function mountMembers() {
	return mount(EditMembers, {
		props: { band, inviting: false, invite: { email: '' } },
		global: {
			mocks: { $page: { props: { auth: { user: { id: 10 } } } }, $toast: { add: vi.fn() } },
			config: { globalProperties: { route: (name, p) => `/r/${name}/${p ?? ''}` } },
			stubs: { Button: { props: ['label', 'icon', 'disabled'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>' } },
		},
	});
}

describe('EditMembers — Message action', () => {
	beforeEach(() => { axios.post.mockReset(); router.visit.mockReset(); });

	it('shows a Message button for other people but not for the current user', () => {
		const w = mountMembers();
		const buttons = w.findAll('[data-test="message-user"]');
		expect(buttons).toHaveLength(1);
		expect(buttons[0].attributes('aria-label')).toBe('Message Taylor Campo');
	});

	it('clicking creates the DM and visits the inbox', async () => {
		axios.post.mockResolvedValueOnce({ data: { conversation: { id: 44 } } });
		const w = mountMembers();
		await w.find('[data-test="message-user"]').trigger('click');
		await flushPromises();
		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.dm/', { user_id: 20 });
		expect(router.visit).toHaveBeenCalledWith('/r/messages.index/44');
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

`npx vitest run resources/js/tests/components/editmembers.test.js` — FAIL (no `[data-test="message-user"]`).

- [ ] **Step 3: Add the buttons**

In `resources/js/Pages/Band/Components/EditMembers.vue`:

Members section — replace the `<Link :href="'/permissions/' + band.id + '/' + member.user.id" …>Edit Permissions</Link>` element with a flex wrapper holding the new button plus the existing link:
```html
          <div class="flex items-center gap-2">
            <Button
              v-if="member.user.id !== currentUserId"
              icon="pi pi-comment"
              label="Message"
              size="small"
              text
              data-test="message-user"
              :aria-label="`Message ${member.user.name}`"
              :disabled="messaging === member.user.id"
              @click="messageUser(member.user)"
            />
            <Link
              :href="'/permissions/' + band.id + '/' + member.user.id"
              class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
            >
              Edit Permissions
            </Link>
          </div>
```
Owners section — inside each owner row's right-hand side (next to the existing delete/owner controls; keep them), add the same `Button` with `owner.user` in place of `member.user`.

Script — add at the top of `<script>`: `import axios from 'axios';` and `import { router } from '@inertiajs/vue3';` (keep the existing `Link` usage; if `Link` is only globally registered, leave it). Add `data()`:
```js
  data() {
    return { messaging: null };
  },
```
Add `computed`:
```js
  computed: {
    currentUserId() {
      return this.$page?.props?.auth?.user?.id ?? null;
    },
  },
```
Add to `methods`:
```js
    async messageUser(user) {
      this.messaging = user.id;
      try {
        const { data } = await axios.post(route('chat.conversations.dm'), { user_id: user.id });
        router.visit(route('messages.index', data.conversation.id));
      } catch (e) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Could not start the conversation',
          detail: e?.response?.data?.message || 'Please try again.',
          life: 4000,
        });
      } finally {
        this.messaging = null;
      }
    },
```

- [ ] **Step 4: Run the test and build**

`npx vitest run resources/js/tests/components/editmembers.test.js` — PASS (2). `npx vite build 2>&1 | tail -3` — clean.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Band/Components/EditMembers.vue resources/js/tests/components/editmembers.test.js
git commit -m "feat(chat): Message action on band member and owner rows"
```

---

### Task 9: Full verification (light + dark), PR

**Files:** none new.

- [ ] **Step 1: Suites and build**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat tests/Feature/Services/Chat tests/Feature/Api/Mobile/Chat` — PASS.
Run: `NODE_ENV=production npx vitest run --mode=pipeline` — PASS.
Run: `npx vite build 2>&1 | tail -3` — clean.

- [ ] **Step 2: Browser verification — run the script TWICE: light, then dark**

Local app https://local.tts.band:8710, owner `eddimull@gmail.com` / `password`; second user: band-1 member id 20 (password already reset to `password` in slice 1; re-run the tinker line from the slice-1 plan if login fails). Use the Chrome DevTools MCP tools; for the dark pass emulate `prefers-color-scheme: dark` (DevTools `emulate` with a dark colour scheme, or `page.emulateMediaFeatures`) and confirm `window.matchMedia('(prefers-color-scheme: dark)').matches` is true via `evaluate_script` before screenshots. Screenshots to `/tmp/claude-1000/-home-eddie-github-tts-bandmate/ae197b67-1707-45ba-92bc-3aef8db34fff/scratchpad/web-messages/{light,dark}/`.

1. Header shows the Messages icon beside the bell (desktop) and in the mobile header (resize to 390 px wide); badge hidden at 0.
2. `/messages`: band channel + any topic rows with messages + DMs; unread pills; "Select a conversation" empty state.
3. New message → picker lists members with band context; choose the member → DM thread opens, URL becomes `/messages/{id}`.
4. Second tab as the member: reply in the DM → first tab shows it live; header badge increments in a THIRD page (owner, dashboard) within ~1 s; the list row preview/time update.
5. Open the thread in tab 1 → badge drops to 0 after the read ack; row pill gone.
6. Member's bell shows "Edward Muller: …" row → click → `/messages/{id}` with the DM open.
7. Band settings → Members → "Message" on a member → lands in that DM.
8. Narrow viewport (390 px): list only; select → thread with back; back returns to the list.
9. Contrast spot-check in the dark pass: list rows, selected row, thread header, dialog, badge pill all legible; no white panels.

Fix only proven, local defects (own commits with trailer); report anything else.

- [ ] **Step 3: PR**

```bash
git push -u origin feat/web-messages
gh pr create --base staging --title "feat(chat): web Messages inbox — DMs, band channel, header badge (parity slice 2)" --body-file - <<'EOF'
## Summary
Slice 2 of web chat parity. Spec: `docs/superpowers/specs/2026-10-05-web-messages-design.md`.

- `ConversationPresenter::listFor()` / `unreadTotalFor()` (mobile index now delegates; wire shape unchanged)
- `/messages/{conversation?}` two-pane inbox (server-seeded list, live thread, URL rewritten in place), `chat.conversations.index|dm|delivered`, `chat.contacts`, `chat.unread-count`
- Header Messages icon with live unread badge (Vuex `chatUnread`/`chatSignal`; layout owns the user-channel + band-signal subscriptions)
- New-message contact picker; "Message" on band member/owner rows
- `DirectMessageReceived` database-only bell notification (DMs only; band channel stays off the bell)
- Dark mode verified on every new surface (screenshots: light + dark)

No migration, no mobile change.

## Test plan
- [ ] `php artisan test tests/Feature/Web/Chat tests/Feature/Services/Chat tests/Feature/Api/Mobile/Chat`
- [ ] `NODE_ENV=production npx vitest run --mode=pipeline`
- [ ] Browser (light + dark): inbox rows, DM via picker, live reply + badge, read clears badge, bell deep link, member-row Message, narrow stacking

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
EOF
```

- [ ] **Step 4: Wait for Copilot and address every comment.**

---

## Self-review notes

- **Spec coverage:** §1 presenter (T1); §2 routes + controller (T2); §3 DM bell (T3); §4 store + header icon + layout signals (T4); §5 list/row (T5), picker (T6), page incl. selection/URL/refresh/delivered/read handling (T7); §6 member rows (T8); §7 dark mode (palette in every component + two-scheme browser pass in T9); error handling (list refresh keeps rows, DM create error surfaces in the dialog, 403 from the controller, Echo-less operation) — T7/T6/T2.
- **Type consistency:** `listFor()` rows carry the frozen summary keys (asserted in T1) and are what T5/T7 render; `route('messages.index', id)` (T7, T8) ↔ `routeParams ['conversation' => id]` (T3) ↔ `{conversation?}` (T2); `ConversationThread` props/emits unchanged from slice 1; store action names `fetchChatUnread`/`signalChatChange` match the layout's `mapActions` (T4) and the page's `dispatch('user/fetchChatUnread')` (T7).
- **Known edges:** the page does not handle `popstate` (deep links are full Inertia visits); bell rows are not marked read when the thread is read (out of scope, as in slice 1).
