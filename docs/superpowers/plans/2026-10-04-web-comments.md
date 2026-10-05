# Web Comments (chat parity slice 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the web app the mobile comments experience — a full-parity comment thread in a slide-over drawer on event, rehearsal, and booking pages, unread pills on dashboard cards, bell notifications for new comments, and `moderate:chat` on the web permissions page.

**Architecture:** The chat backend already exists and is web-agnostic; it is only reachable under `/api/mobile` + Sanctum. We extract the presentation helpers trapped in the mobile controller into `App\Services\Chat\ConversationPresenter`, register session-auth `routes/chat.php` routes over the *same* controller methods, and add Inertia props + a database notification. On the web side a refcounted `private-conversation.{id}` subscriber and a `useConversationThread` composable (mirroring the Flutter `ChatThreadNotifier`) feed a small set of `Components/Chat/*` Vue components, mounted in a PrimeVue `Drawer` from each detail page.

**Tech Stack:** Laravel 10 (PHP 8.3, Docker: `docker compose exec app …`), Spatie permissions (team-scoped), Laravel Echo + Pusher, Inertia + Vue 3 `<script setup>`, PrimeVue 4 (Aura) + Tailwind, Ziggy `route()`, axios (`window.axios` and importable), luxon, Vitest + @vue/test-utils (jsdom).

**Spec:** `docs/superpowers/specs/2026-10-04-web-comments-design.md`

## Global Constraints

- Branch `feat/web-comments` (already created off `origin/staging`); PR targets **staging**.
- **Mobile wire contract is frozen.** `MessageFormatter::format()`, the ThreadPage shape (`conversation, messages, participants, channel, has_more`), the conversation summary shape, and the six `ConversationStreamEvent` names must not change. All 15 suites under `tests/Feature/Api/Mobile/Chat/` must stay green after every task.
- All PHP commands run inside the container: `docker compose exec -T app php artisan test <path>`; never run php/composer on the host.
- Vitest runs in production mode in CI: assert via DOM text, props, and listener spies — **never** `wrapper.vm.*` or `wrapper.emitted()`. Run locally with `npx vitest run <file>`.
- New web routes are named `chat.*` and live under `auth` + `verified`. The booking resolve route lives at `bands/{band}/booking/{booking}/conversation` behind `booking.access` and must be added to `BookingLayout.vue`'s `excludeRoutes`.
- Bell notification `CommentPosted` is **database-only** (`via()` returns `['database']`), topic conversations only, author excluded, and its `routeParams` include `comments => 1`.
- Dark mode: every new Tailwind class pair needs a `dark:` variant consistent with the detail pages (`dark:bg-slate-800`, `dark:text-gray-50`, `dark:text-gray-400`).
- Commit after every task with a conventional-commit message; end commit messages with:
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
  ```

---

## File structure

**Backend (create)**
- `app/Services/Chat/ConversationPresenter.php` — summary/thread/unread presentation shared by mobile + web.
- `app/Notifications/CommentPosted.php` — database-only bell notification.
- `routes/chat.php` — session-auth chat routes.
- `tests/Feature/Services/Chat/ConversationPresenterTest.php`
- `tests/Feature/Web/Chat/ChatWebRoutesTest.php`
- `tests/Feature/Web/Chat/CommentUnreadPropsTest.php`
- `tests/Feature/Web/Chat/DashboardUnreadCommentTest.php`
- `tests/Feature/Web/Chat/CommentPostedNotificationTest.php`
- `tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php`

**Backend (modify)**
- `app/Http/Controllers/Api/Mobile/ConversationsController.php` — delegate to presenter.
- `routes/web.php` — `require __DIR__ . '/chat.php';`
- `app/Http/Controllers/EventsController.php`, `RehearsalController.php`, `BookingsController.php` — `unreadCommentCount` prop.
- `app/Http/Controllers/DashboardController.php` — `unread_comment_count` per row.
- `app/Services/Mobile/DashboardFormatter.php` — expose `conversablePairFor()`.
- `app/Jobs/ProcessChatMessagePush.php` — also notify `CommentPosted` for topics.
- `app/Http/Controllers/UserPermissionsController.php` — `moderate:chat`.

**Frontend (create)** under `resources/js/`
- `realtime/conversationChannel.js` (+ `tests/realtime/conversationChannel.test.js`)
- `composables/useConversationThread.js` (+ `tests/composables/useConversationThread.test.js`)
- `composables/useCommentsDrawer.js` (+ `tests/composables/useCommentsDrawer.test.js`)
- `utils/messageTime.js` (+ `tests/utils/messageTime.test.js`)
- `Components/Chat/CommentsButton.vue` (+ `tests/components/commentsbutton.test.js`)
- `Components/Chat/CommentsDrawer.vue`
- `Components/Chat/ConversationThread.vue`
- `Components/Chat/MessageBubble.vue`
- `Components/Chat/MessageComposer.vue` (+ `tests/components/messagecomposer.test.js`)
- `Components/Chat/ReactionPicker.vue`

**Frontend (modify)**
- `resources/js/app.js` — register PrimeVue `Drawer`.
- `resources/js/Pages/Events/Show.vue`, `Pages/Rehearsals/RehearsalDetail.vue`, `Pages/Bookings/Layout/BookingLayout.vue`, `Components/NavSubmenu.vue`, `Pages/Dashboard.vue`, `Components/EventCard.vue`, `Pages/Band/ShowPermissions.vue`.

---

### Task 1: Extract `ConversationPresenter` from the mobile controller

**Files:**
- Create: `app/Services/Chat/ConversationPresenter.php`
- Create: `tests/Feature/Services/Chat/ConversationPresenterTest.php`
- Modify: `app/Http/Controllers/Api/Mobile/ConversationsController.php`

**Interfaces:**
- Produces:
  ```php
  namespace App\Services\Chat;
  final class ConversationPresenter {
      public function __construct(ConversationService $conversations, MessageFormatter $formatter, TopicUnreadService $topicUnread);
      /** @param Collection<int,int> $ids  @param Collection|null $lastReads null = look them up for $user */
      public function prefetch(Collection $ids, User $user, ?Collection $lastReads = null): array; // ['last'=>…, 'unread'=>…, 'dmOther'=>…]
      public function summarize(Conversation $conversation, User $user, array $prefetch): array;
      public function topicType(Conversation $conversation): ?string;   // 'booking'|'event'|'rehearsal'|null
      public function topicTitle(Conversation $conversation): string;
      /** ThreadPage array: conversation, messages, participants, channel, has_more */
      public function threadPage(User $user, Conversation $conversation, ?int $before = null): array;
      /** null = user may not view that topic; int otherwise (0 when no thread yet) */
      public function unreadCountFor(User $user, Model $target): ?int;
  }
  ```

- [ ] **Step 1: Write the failing presenter test**

`tests/Feature/Services/Chat/ConversationPresenterTest.php`:

```php
<?php

namespace Tests\Feature\Services\Chat;

use App\Services\Chat\ConversationPresenter;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ConversationPresenterTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_unread_count_is_zero_when_no_thread_exists_yet(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->assertSame(0, app(ConversationPresenter::class)->unreadCountFor($owner, $event));
        // Probing must NOT create the conversation.
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_unread_count_counts_other_users_messages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'one']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'two']);
        $topic->messages()->create(['user_id' => $owner->id,  'body' => 'mine']);

        $this->assertSame(2, app(ConversationPresenter::class)->unreadCountFor($owner, $event));
    }

    public function test_unread_count_canonicalises_rehearsal_backed_events(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:rehearsals', 'read:events']);
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);
        $topic = app(ConversationService::class)->topicFor($rehearsal);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'bring charts']);

        $presenter = app(ConversationPresenter::class);
        $this->assertSame(1, $presenter->unreadCountFor($owner, $event));
        $this->assertSame(1, $presenter->unreadCountFor($owner, $rehearsal));
    }

    public function test_unread_count_is_null_when_user_cannot_view_the_topic(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        // Subs never see booking threads.
        $this->assertNull(app(ConversationPresenter::class)->unreadCountFor($sub, $event->eventable));
        // …but do see the event thread they are entitled to.
        $this->assertSame(0, app(ConversationPresenter::class)->unreadCountFor($sub, $event));
    }

    public function test_thread_page_shape_matches_mobile_contract(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $owner->id, 'body' => 'hello']);

        $page = app(ConversationPresenter::class)->threadPage($owner, $topic);

        $this->assertSame(['conversation', 'messages', 'participants', 'channel', 'has_more'], array_keys($page));
        $this->assertSame('private-conversation.' . $topic->id, $page['channel']);
        $this->assertSame('hello', $page['messages'][0]['body']);
        $this->assertSame('event', $page['conversation']['topic_type']);
        $this->assertSame('Test Gig', $page['conversation']['title']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Services/Chat/ConversationPresenterTest.php`
Expected: FAIL — `Class "App\Services\Chat\ConversationPresenter" not found`.

- [ ] **Step 3: Create the presenter (moved code, byte-identical behaviour)**

`app/Services/Chat/ConversationPresenter.php`:

```php
<?php

namespace App\Services\Chat;

use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Events;
use App\Models\Message;
use App\Models\Rehearsal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The one place that turns Conversation/Message rows into the wire shapes
 * both clients (mobile JSON API and web Inertia/axios) consume. Moved out of
 * Api\Mobile\ConversationsController so web and mobile cannot drift.
 */
final class ConversationPresenter
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageFormatter $formatter,
        private readonly TopicUnreadService $topicUnread,
    ) {}

    /**
     * Bulk-load everything summarize() needs for a set of conversations so
     * list endpoints run a constant number of queries instead of ~3 per row.
     *
     * @param  Collection<int, int>  $ids
     * @param  Collection|null  $lastReads  conversation_id => last_read_at for $user.
     *         Pass null to look them up; pass collect() to force the
     *         "no marker" bucket (storeDm relies on this).
     * @return array{last: Collection, unread: Collection, dmOther: Collection}
     */
    public function prefetch(Collection $ids, User $user, ?Collection $lastReads = null): array
    {
        $lastReads ??= ConversationParticipant::where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->pluck('last_read_at', 'conversation_id');

        $latestIds = Message::withTrashed()
            ->whereIn('conversation_id', $ids)
            ->selectRaw('MAX(id) as id')
            ->groupBy('conversation_id')
            ->pluck('id');

        $last = Message::withTrashed()
            ->whereIn('id', $latestIds)
            ->with('attachments')
            ->get()
            ->keyBy('conversation_id');

        // Grouped count of "not mine" messages per conversation newer than that
        // conversation's own last_read_at. Two aggregate queries (with marker /
        // without marker) — O(1) queries, never O(messages) rows into PHP. A
        // participant row with NULL last_read_at falls into the no-marker bucket.
        $withMarkerIds    = $lastReads->filter(fn ($v) => $v !== null)->keys();
        $withoutMarkerIds = collect($ids)->diff($withMarkerIds);

        // pluck() keys by integer conversation_id; Collection::merge() would
        // re-index integer keys, so union() is required to keep the keying.
        $unread = collect();

        if ($withMarkerIds->isNotEmpty()) {
            $unread = $unread->union(
                Message::query()
                    ->whereIn('messages.conversation_id', $withMarkerIds)
                    ->where(fn ($q) => $q->where('messages.user_id', '!=', $user->id)
                        ->orWhereNull('messages.user_id'))
                    ->join('conversation_participants', function ($join) use ($user) {
                        $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                            ->where('conversation_participants.user_id', '=', $user->id);
                    })
                    ->whereColumn('messages.created_at', '>', 'conversation_participants.last_read_at')
                    ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as unread')
                    ->groupBy('messages.conversation_id')
                    ->pluck('unread', 'conversation_id')
                    ->map(fn ($n) => (int) $n)
            );
        }

        if ($withoutMarkerIds->isNotEmpty()) {
            $unread = $unread->union(
                Message::query()
                    ->whereIn('conversation_id', $withoutMarkerIds->values())
                    ->where(fn ($q) => $q->where('user_id', '!=', $user->id)
                        ->orWhereNull('user_id'))
                    ->selectRaw('conversation_id, COUNT(*) as unread')
                    ->groupBy('conversation_id')
                    ->pluck('unread', 'conversation_id')
                    ->map(fn ($n) => (int) $n)
            );
        }

        $dmOther = ConversationParticipant::whereIn('conversation_id', $ids)
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->keyBy('conversation_id');

        return ['last' => $last, 'unread' => $unread, 'dmOther' => $dmOther];
    }

    /**
     * Conversation JSON — the one wire shape for a conversation everywhere.
     *
     * @param array{last: Collection, unread: Collection, dmOther: Collection} $prefetch
     */
    public function summarize(Conversation $conversation, User $user, array $prefetch): array
    {
        $last = $prefetch['last']->get($conversation->id);

        $preview = null;
        $lastAt  = null;
        if ($last) {
            $lastAt  = $last->created_at->toIso8601String();
            $preview = $last->trashed()
                ? null
                : (($last->body !== null && $last->body !== '') ? $last->body : '📷 Photo');
        }

        $unread = $prefetch['unread']->get($conversation->id, 0);

        $title = match ($conversation->type) {
            Conversation::TYPE_BAND  => $conversation->band?->name ?? 'Band',
            Conversation::TYPE_DM    => $prefetch['dmOther']->get($conversation->id)?->user?->name ?? 'Direct message',
            Conversation::TYPE_TOPIC => $this->topicTitle($conversation),
            default => 'Conversation',
        };

        return [
            'id'                   => $conversation->id,
            'type'                 => $conversation->type,
            'band_id'              => $conversation->band_id ? (int) $conversation->band_id : null,
            'title'                => $title,
            'topic_type'           => $this->topicType($conversation),
            'last_message_preview' => $preview,
            'last_message_at'      => $lastAt,
            'unread_count'         => $unread,
            'can_moderate'         => $user->can('moderate', $conversation),
        ];
    }

    /**
     * Row icon discriminator. Frozen wire contract: 'booking' | 'event' |
     * 'rehearsal' for topics, null otherwise. Derived from the loaded
     * conversable so a thread whose item was deleted reports null.
     */
    public function topicType(Conversation $conversation): ?string
    {
        if ($conversation->type !== Conversation::TYPE_TOPIC) {
            return null;
        }

        return match (true) {
            $conversation->conversable instanceof Bookings  => 'booking',
            $conversation->conversable instanceof Events    => 'event',
            $conversation->conversable instanceof Rehearsal => 'rehearsal',
            default => null,
        };
    }

    /**
     * The item's human name. Bookings carry `name`, Events carry `title`,
     * Rehearsals fall back to the child event's title, then the schedule
     * name (same chain as Rehearsal::getGoogleCalendarSummary()).
     */
    public function topicTitle(Conversation $conversation): string
    {
        $target = $conversation->conversable;

        $title = match (true) {
            $target instanceof Bookings  => $target->name,
            $target instanceof Events    => $target->title,
            // ->events (not ->events()) so an eager-loaded collection is reused.
            $target instanceof Rehearsal => $target->events->first()?->title
                ?? $target->rehearsalSchedule?->name,
            default => null,
        };

        $title = is_string($title) ? trim($title) : '';

        if ($title !== '') {
            return $title;
        }

        return $target instanceof Rehearsal ? 'Rehearsal' : 'Thread';
    }

    /**
     * The shared ThreadPage shape. Messages come back oldest→newest;
     * `channel` is what the client subscribes to for live updates.
     *
     * @return array{conversation: array, messages: Collection, participants: Collection, channel: string, has_more: bool}
     */
    public function threadPage(User $user, Conversation $conversation, ?int $before = null): array
    {
        $limit = 50;

        $page = $conversation->messages()->withTrashed()
            ->with(['user', 'attachments', 'reactions'])
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->latest('id')->limit($limit + 1)->get();

        $hasMore  = $page->count() > $limit;
        $messages = $page->take($limit)->reverse()->values()
            ->map(fn ($m) => $this->formatter->format($m));

        $participants = $conversation->participants()->with('user')->get()
            ->map(fn ($p) => [
                'user_id'           => (int) $p->user_id,
                'name'              => $p->user?->name,
                'avatar_url'        => null,
                'last_read_at'      => $p->last_read_at?->toIso8601String(),
                'last_delivered_at' => $p->last_delivered_at?->toIso8601String(),
            ])->values();

        $prefetch = $this->prefetch(collect([$conversation->id]), $user);

        return [
            'conversation' => $this->summarize($conversation, $user, $prefetch),
            'messages'     => $messages,
            'participants' => $participants,
            'channel'      => 'private-conversation.' . $conversation->id,
            'has_more'     => $hasMore,
        ];
    }

    /**
     * Unread comment count for a page's item WITHOUT creating the thread.
     * Returns null when the user could not view that topic at all (so the
     * page can hide its Comments button), 0 when there is no thread yet.
     */
    public function unreadCountFor(User $user, Model $target): ?int
    {
        $target = $this->conversations->canonicalTarget($target);

        $bandId = match (true) {
            $target instanceof Events => $target->eventable?->band_id,
            $target instanceof Rehearsal, $target instanceof Bookings => $target->band_id,
            default => null,
        };

        if ($bandId === null) {
            return null;
        }

        // ConversationPolicy::viewTopic reads only band_id + the conversable,
        // so an unsaved probe answers "could this user see the thread?".
        $probe = (new Conversation())->forceFill([
            'type'    => Conversation::TYPE_TOPIC,
            'band_id' => (int) $bandId,
        ]);
        $probe->setRelation('conversable', $target);

        if ($user->cannot('view', $probe)) {
            return null;
        }

        $key    = get_class($target) . ':' . $target->getKey();
        $counts = $this->topicUnread->unreadCountsForConversables($user, [[get_class($target), (int) $target->getKey()]]);

        return (int) ($counts[$key] ?? 0);
    }
}
```

- [ ] **Step 4: Make the mobile controller delegate**

In `app/Http/Controllers/Api/Mobile/ConversationsController.php`:

1. Replace the constructor with:
```php
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageFormatter $formatter,
        private readonly ConversationPresenter $presenter,
    ) {}
```
and add `use App\Services\Chat\ConversationPresenter;` to the imports.

2. **Delete** the private methods `prefetchSummaryData`, `summarize`, `topicType`, `topicTitle`, and `threadPage` (their doc blocks too). Keep `visibleTopics` and `topicResponse`.

3. Update call sites:

`index()` — replace the `$lastReads = …` + `$prefetch = …` + `$rows = …` block with:
```php
        $prefetch = $this->presenter->prefetch($ids, $user);

        $rows = $all->map(fn (Conversation $c) => $this->presenter->summarize($c, $user, $prefetch))
            ->sortByDesc(fn ($row) => $row['last_message_at'] ?? '')
            ->values();
```

`storeDm()` — replace the two lines after `$conversation = …` with:
```php
        // collect() (not null): the DM was just created or re-found; mobile's
        // established behaviour is the no-marker bucket here.
        $prefetch = $this->presenter->prefetch(collect([$conversation->id]), $me, collect());

        return response()->json(['conversation' => $this->presenter->summarize($conversation, $me, $prefetch)]);
```

`topicResponse()` — last line becomes:
```php
        return response()->json($this->presenter->threadPage($request->user(), $conversation));
```

`messages()` — the return becomes:
```php
        return response()->json($this->presenter->threadPage(
            $request->user(),
            $conversation,
            $validated['before'] ?? null,
        ));
```

4. Remove now-unused imports `ConversationParticipant` only if nothing else in the file uses it (`read()` and `delivered()` still do — keep it). `Bookings`, `Events`, `Rehearsal`, `MorphTo` are still used by `visibleTopics`/route bindings — keep them.

- [ ] **Step 5: Run the new test and every existing chat suite**

Run: `docker compose exec -T app php artisan test tests/Feature/Services/Chat/ConversationPresenterTest.php tests/Feature/Api/Mobile/Chat tests/Feature/Api/Mobile/DashboardUnreadCommentTest.php tests/Feature/Services/Chat/TopicUnreadServiceTest.php`
Expected: all PASS (5 new + the existing ~100).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Chat/ConversationPresenter.php app/Http/Controllers/Api/Mobile/ConversationsController.php tests/Feature/Services/Chat/ConversationPresenterTest.php
git commit -m "refactor(chat): extract ConversationPresenter from the mobile conversations controller"
```

---

### Task 2: Session-auth web routes (`routes/chat.php`)

**Files:**
- Create: `routes/chat.php`
- Modify: `routes/web.php` (the `require` block near line 58)
- Create: `tests/Feature/Web/Chat/ChatWebRoutesTest.php`

**Interfaces:**
- Produces route names used by every later frontend task: `chat.events.conversation {event}`, `chat.rehearsals.conversation {rehearsal}`, `chat.bookings.conversation {band, booking}`, `chat.conversations.messages.index {conversation}`, `chat.conversations.messages.store {conversation}`, `chat.conversations.read {conversation}`, `chat.conversations.typing {conversation}`, `chat.messages.update {message}`, `chat.messages.destroy {message}`, `chat.messages.attachments.show {message, attachment}`, `chat.messages.reactions.store {message}`, `chat.messages.reactions.destroy {message, emoji}`.

- [ ] **Step 1: Write the failing route tests**

`tests/Feature/Web/Chat/ChatWebRoutesTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ChatWebRoutesTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_all_chat_routes_are_registered_with_expected_names(): void
    {
        foreach ([
            'chat.events.conversation', 'chat.rehearsals.conversation', 'chat.bookings.conversation',
            'chat.conversations.messages.index', 'chat.conversations.messages.store',
            'chat.conversations.read', 'chat.conversations.typing',
            'chat.messages.update', 'chat.messages.destroy', 'chat.messages.attachments.show',
            'chat.messages.reactions.store', 'chat.messages.reactions.destroy',
        ] as $name) {
            $this->assertTrue(Route::has($name), "missing route {$name}");
        }
    }

    public function test_member_resolves_event_thread_with_a_session(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $response = $this->actingAs($owner)
            ->getJson(route('chat.events.conversation', $event))
            ->assertOk();

        $this->assertSame('topic', $response->json('conversation.type'));
        $this->assertSame('private-conversation.' . $response->json('conversation.id'), $response->json('channel'));
    }

    public function test_guest_is_redirected_or_401(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->get(route('chat.events.conversation', $event))->assertRedirect(route('login'));
        $this->getJson(route('chat.events.conversation', $event))->assertUnauthorized();
    }

    public function test_outsider_gets_403_from_the_policy(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson(route('chat.events.conversation', $event))
            ->assertForbidden();
    }

    public function test_rehearsal_route_reaches_the_canonical_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);

        $viaEvent     = $this->actingAs($owner)->getJson(route('chat.events.conversation', $event))->assertOk();
        $viaRehearsal = $this->actingAs($owner)->getJson(route('chat.rehearsals.conversation', $rehearsal))->assertOk();

        $this->assertSame($viaEvent->json('conversation.id'), $viaRehearsal->json('conversation.id'));
    }

    public function test_booking_route_is_band_scoped_and_denies_subs(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        $sub     = $this->makeSubAssignedTo($band, $event);

        $this->actingAs($owner)
            ->getJson(route('chat.bookings.conversation', ['band' => $band, 'booking' => $booking]))
            ->assertOk();

        $this->actingAs($sub)
            ->getJson(route('chat.bookings.conversation', ['band' => $band, 'booking' => $booking]))
            ->assertForbidden();
    }

    public function test_member_can_post_edit_react_and_delete(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);

        $created = $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'first'])
            ->assertCreated();
        $id = $created->json('message.id');

        $this->actingAs($owner)
            ->getJson(route('chat.conversations.messages.index', $topic))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'first');

        $this->actingAs($owner)
            ->patchJson(route('chat.messages.update', $id), ['body' => 'edited'])
            ->assertOk()
            ->assertJsonPath('message.body', 'edited');

        $this->actingAs($owner)
            ->postJson(route('chat.messages.reactions.store', $id), ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reactions.0.emoji', '👍');

        $this->actingAs($owner)
            ->deleteJson(route('chat.messages.reactions.destroy', ['message' => $id, 'emoji' => '👍']))
            ->assertOk()
            ->assertJsonPath('reactions', []);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.read', $topic), ['last_read_message_id' => $id])
            ->assertNoContent();

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.typing', $topic))
            ->assertNoContent();

        $this->actingAs($owner)
            ->deleteJson(route('chat.messages.destroy', $id))
            ->assertNoContent();
    }

    public function test_attachment_route_streams_for_a_viewer_and_403s_an_outsider(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);

        $created = $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $topic), [
                'images' => [UploadedFile::fake()->image('photo.jpg', 120, 80)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $messageId    = $created->json('message.id');
        $attachmentId = $created->json('message.attachments.0.id');
        $url = route('chat.messages.attachments.show', ['message' => $messageId, 'attachment' => $attachmentId]);

        $this->actingAs($member)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/ChatWebRoutesTest.php`
Expected: FAIL — `missing route chat.events.conversation`.

- [ ] **Step 3: Create `routes/chat.php`**

```php
<?php

use App\Http\Controllers\Api\Mobile\ConversationsController;
use App\Http\Controllers\Api\Mobile\MessageReactionsController;
use App\Http\Controllers\Api\Mobile\MessagesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat / comments — session-authenticated web surface
|--------------------------------------------------------------------------
| The same controllers as /api/mobile (they are band-agnostic and gate on
| ConversationPolicy), exposed under the web guard so Inertia pages can use
| axios + Ziggy route names. Only what the comments drawer needs is
| registered here; the conversation list / DM / contacts / delivered
| endpoints arrive with the Messages slice.
*/

Route::middleware(['auth', 'verified'])->name('chat.')->group(function () {
    Route::get('chat/events/{event}/conversation', [ConversationsController::class, 'forEvent'])
        ->name('events.conversation');
    Route::get('chat/rehearsals/{rehearsal}/conversation', [ConversationsController::class, 'forRehearsal'])
        ->name('rehearsals.conversation');
    // Mirrors the mobile asymmetry: booking threads are band-scoped and sit
    // behind the same gate as the booking pages (owners + members only).
    Route::get('bands/{band}/booking/{booking}/conversation', [ConversationsController::class, 'forBooking'])
        ->middleware('booking.access')
        ->scopeBindings()
        ->name('bookings.conversation');

    Route::get('chat/conversations/{conversation}/messages', [ConversationsController::class, 'messages'])
        ->name('conversations.messages.index');
    Route::post('chat/conversations/{conversation}/messages', [ConversationsController::class, 'storeMessage'])
        ->name('conversations.messages.store');
    Route::post('chat/conversations/{conversation}/read', [ConversationsController::class, 'read'])
        ->name('conversations.read');
    Route::post('chat/conversations/{conversation}/typing', [ConversationsController::class, 'typing'])
        ->middleware('throttle:chat-typing')
        ->name('conversations.typing');

    Route::patch('chat/messages/{message}', [MessagesController::class, 'update'])->name('messages.update');
    Route::delete('chat/messages/{message}', [MessagesController::class, 'destroy'])->name('messages.destroy');
    Route::get('chat/messages/{message}/attachments/{attachment}', [MessagesController::class, 'attachment'])
        ->name('messages.attachments.show');

    Route::post('chat/messages/{message}/reactions', [MessageReactionsController::class, 'store'])
        ->name('messages.reactions.store');
    Route::delete('chat/messages/{message}/reactions/{emoji}', [MessageReactionsController::class, 'destroy'])
        ->name('messages.reactions.destroy');
});
```

In `routes/web.php`, add after `require __DIR__ . '/booking.php';`:
```php
require __DIR__ . '/chat.php';
```

- [ ] **Step 4: Run the tests**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/ChatWebRoutesTest.php`
Expected: PASS (8 tests). If `test_booking_route_is_band_scoped_and_denies_subs` fails with 404 instead of 403 for the sub, check `app/Http/Middleware/BookingAccessMiddleware.php` — it must run before model binding scoping; if it aborts 404 for non-members, change the assertion to `->assertStatus(403)` only after confirming the middleware's documented behaviour and note it in the commit body.

Also run the Ziggy consumers' sanity check: `docker compose exec -T app php artisan route:list --name=chat.` — expect 12 rows.

- [ ] **Step 5: Commit**

```bash
git add routes/chat.php routes/web.php tests/Feature/Web/Chat/ChatWebRoutesTest.php
git commit -m "feat(chat): session-auth web routes for topic threads, messages, reactions and attachments"
```

---

### Task 3: `unreadCommentCount` prop on the three detail pages

**Files:**
- Modify: `app/Http/Controllers/EventsController.php` (the `Inertia::render('Events/Show', …)` call near line 288)
- Modify: `app/Http/Controllers/RehearsalController.php` (`show()`, near line 181)
- Modify: `app/Http/Controllers/BookingsController.php` (`Inertia::render('Bookings/Show', …)` near line 294)
- Create: `tests/Feature/Web/Chat/CommentUnreadPropsTest.php`

**Interfaces:**
- Consumes: `ConversationPresenter::unreadCountFor(User, Model): ?int` (Task 1).
- Produces: Inertia prop `unreadCommentCount` (`int|null`) on `Events/Show`, `Rehearsals/RehearsalDetail`, `Bookings/Show`.

- [ ] **Step 1: Write the failing prop tests**

`tests/Feature/Web/Chat/CommentUnreadPropsTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class CommentUnreadPropsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_page_carries_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'load-in at 5']);

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('unreadCommentCount', 1));
    }

    public function test_event_page_reports_zero_without_a_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 0));
    }

    public function test_rehearsal_page_and_wrapping_event_page_agree(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:rehearsals', 'read:events']);
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);
        $topic = app(ConversationService::class)->topicFor($rehearsal);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'new tune']);

        $this->actingAs($owner)
            ->get(route('rehearsals.show', [
                'band' => $band, 'rehearsal_schedule' => $rehearsal->rehearsal_schedule_id, 'rehearsal' => $rehearsal,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 1));

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 1));
    }

    public function test_booking_page_carries_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:bookings']);
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        $topic   = app(ConversationService::class)->topicFor($booking);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'deposit in']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'contract signed']);

        $this->actingAs($owner)
            ->get(route('Booking Details', ['band' => $band, 'booking' => $booking]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Show')
                ->where('unreadCommentCount', 2));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/CommentUnreadPropsTest.php`
Expected: FAIL — `Inertia property [unreadCommentCount] does not exist`.

If a page 500s for an unrelated reason (e.g. the event page needs extra factory state), fix the test's setup — these pages are rendered by other passing tests (`DashboardLodgingTest`, `SubEventAccessTest`); copy their setup rather than changing controllers.

- [ ] **Step 3: Add the prop to the three controllers**

`EventsController@show` — change the render call to:
```php
        return Inertia::render('Events/Show', [
            'event' => $event,
            'canEdit' => $canEdit,
            'band' => $band,
            'userPayout' => $userPayout,
            'lodgings' => $lodgings,
            'unreadCommentCount' => app(\App\Services\Chat\ConversationPresenter::class)
                ->unreadCountFor(Auth::user(), $event),
        ]);
```

`RehearsalController@show`:
```php
        return Inertia::render('Rehearsals/RehearsalDetail', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'rehearsal' => $rehearsal,
            'canWrite' => Auth::user()->canWrite('rehearsals', $band->id),
            'unreadCommentCount' => app(\App\Services\Chat\ConversationPresenter::class)
                ->unreadCountFor(Auth::user(), $rehearsal),
        ]);
```

`BookingsController@show` — add one entry to the existing array:
```php
            'unreadCommentCount' => app(\App\Services\Chat\ConversationPresenter::class)
                ->unreadCountFor(Auth::user(), $booking),
```
(`Auth` is already imported in all three controllers; if not, `use Illuminate\Support\Facades\Auth;`.)

- [ ] **Step 4: Run the tests**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/CommentUnreadPropsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EventsController.php app/Http/Controllers/RehearsalController.php app/Http/Controllers/BookingsController.php tests/Feature/Web/Chat/CommentUnreadPropsTest.php
git commit -m "feat(chat): expose unreadCommentCount on event, rehearsal and booking pages"
```

---

### Task 4: `unread_comment_count` on web dashboard rows

**Files:**
- Modify: `app/Services/Mobile/DashboardFormatter.php` (add public `conversablePairFor()`)
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `tests/Feature/Web/Chat/DashboardUnreadCommentTest.php`

**Interfaces:**
- Consumes: `DashboardFormatter::conversablePairs(iterable): array`, `TopicUnreadService::unreadCountsForConversables(User, array): array`.
- Produces: `DashboardFormatter::conversablePairFor(mixed $row): ?array{0: class-string, 1: int}`; each dashboard event row gains integer `unread_comment_count`.

- [ ] **Step 1: Write the failing dashboard test**

`tests/Feature/Web/Chat/DashboardUnreadCommentTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class DashboardUnreadCommentTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_dashboard_rows_carry_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $busy   = $this->makeBookingEvent($band);
        $quiet  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($busy);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'a']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'b']);

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $events = collect($response->viewData('page')['props']['events']);

        $this->assertSame(2, $events->firstWhere('id', $busy->id)['unread_comment_count']);
        $this->assertSame(0, $events->firstWhere('id', $quiet->id)['unread_comment_count']);
    }

    public function test_load_older_events_rows_carry_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $past   = $this->makeBookingEvent($band);
        $past->forceFill(['date' => now()->subDays(10)->format('Y-m-d')])->save();
        $topic = app(ConversationService::class)->topicFor($past);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'thanks all']);

        $response = $this->actingAs($owner)
            ->getJson('/dashboard/load-older-events?before_date=' . now()->subDays(1)->toDateString())
            ->assertOk();

        $row = collect($response->json('events'))->firstWhere('id', $past->id);
        $this->assertNotNull($row, 'older event should be in the page');
        $this->assertSame(1, $row['unread_comment_count']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/DashboardUnreadCommentTest.php`
Expected: FAIL — `Undefined array key "unread_comment_count"`.

- [ ] **Step 3: Expose a single-row pair helper on the formatter**

In `app/Services/Mobile/DashboardFormatter.php`, add directly below `conversablePairs()`:

```php
    /**
     * The conversable pair for ONE dashboard row (model or array), or null
     * for rows that have no topic thread (virtual rehearsal_schedule rows).
     *
     * @return array{0: class-string, 1: int}|null
     */
    public function conversablePairFor(mixed $e): ?array
    {
        return $this->conversableFor($this->toRowArray($e));
    }
```

- [ ] **Step 4: Attach the counts in the web dashboard controller**

In `app/Http/Controllers/DashboardController.php`:

Add imports:
```php
use App\Services\Chat\TopicUnreadService;
use App\Services\Mobile\DashboardFormatter;
use Illuminate\Support\Facades\Auth;
```

In `index()` and `loadOlderEvents()`, after `$events = $this->attachLodgingSummaries($events);` add:
```php
        $events = $this->attachUnreadCommentCounts($events);
```

Add the private method (same model-or-array contract as `attachLodgingSummaries`, documented there):
```php
    /**
     * Mirror of the mobile dashboard's `unread_comment_count`: one batched
     * TopicUnreadService query for the whole page, then a per-row lookup.
     * Rows can be arrays or models (see attachLodgingSummaries); virtual
     * rehearsal_schedule rows have no thread and get 0.
     */
    private function attachUnreadCommentCounts(iterable $events): \Illuminate\Support\Collection
    {
        $formatter = app(DashboardFormatter::class);
        $rows      = collect($events);

        $unreadByKey = app(TopicUnreadService::class)->unreadCountsForConversables(
            Auth::user(),
            $formatter->conversablePairs($rows),
        );

        return $rows->map(function ($event) use ($formatter, $unreadByKey) {
            $pair  = $formatter->conversablePairFor($event);
            $count = $pair !== null ? (int) ($unreadByKey["{$pair[0]}:{$pair[1]}"] ?? 0) : 0;

            if (is_array($event)) {
                $event['unread_comment_count'] = $count;
            } else {
                $event->unread_comment_count = $count;
            }

            return $event;
        })->values();
    }
```

- [ ] **Step 5: Run the tests (plus the lodging test that shares this code path)**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/DashboardUnreadCommentTest.php tests/Feature/DashboardLodgingTest.php tests/Feature/Api/Mobile/DashboardUnreadCommentTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Mobile/DashboardFormatter.php app/Http/Controllers/DashboardController.php tests/Feature/Web/Chat/DashboardUnreadCommentTest.php
git commit -m "feat(chat): unread_comment_count on web dashboard event rows"
```

---

### Task 5: `CommentPosted` bell notification

**Files:**
- Create: `app/Notifications/CommentPosted.php`
- Modify: `app/Jobs/ProcessChatMessagePush.php`
- Create: `tests/Feature/Web/Chat/CommentPostedNotificationTest.php`

**Interfaces:**
- Consumes: `ConversationPresenter::topicTitle(Conversation): string`.
- Produces: `App\Notifications\CommentPosted::__construct(Message $message, Conversation $conversation, string $topicTitle)`; `toArray()` → `['text','route','routeParams','conversation_id','message_id']`; static `routeFor(Conversation): ?array{route: string, routeParams: array}`.

- [ ] **Step 1: Write the failing notification tests**

`tests/Feature/Web/Chat/CommentPostedNotificationTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use App\Jobs\SendUserPush;
use App\Notifications\CommentPosted;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class CommentPostedNotificationTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake([SendUserPush::class]); // ProcessChatMessagePush itself runs inline
    }

    public function test_event_comment_notifies_push_audience_minus_author_database_only(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member     = $this->makeMember($band, ['read:events']);
        $noRead     = $this->makeMember($band, []);
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $entitled   = $this->makeSubAssignedTo($band, $event);
        $unentitled = $this->makeSubAssignedTo($band, $otherEvent);
        $topic      = app(ConversationService::class)->topicFor($event);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'Load-in moved to 4pm, bring the small rig'])
            ->assertCreated();

        foreach ([$member, $entitled] as $recipient) {
            Notification::assertSentTo($recipient, CommentPosted::class, function (CommentPosted $n, array $channels) use ($owner, $event) {
                $data = $n->toArray($owner);

                return $channels === ['database']
                    && $data['route'] === 'events.show'
                    && $data['routeParams'] === ['key' => $event->key, 'comments' => 1]
                    && str_starts_with($data['text'], $owner->name . ' commented on Test Gig: Load-in moved')
                    && $data['conversation_id'] === $n->conversation->id;
            });
        }
        Notification::assertNotSentTo($owner, CommentPosted::class);
        Notification::assertNotSentTo($noRead, CommentPosted::class);
        Notification::assertNotSentTo($unentitled, CommentPosted::class);
    }

    public function test_booking_and_rehearsal_comments_link_to_their_pages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:bookings', 'read:rehearsals', 'read:events']);
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        [$rehearsal] = $this->makeRehearsalEvent($band);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', app(ConversationService::class)->topicFor($booking)), ['body' => 'paid'])
            ->assertCreated();
        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', app(ConversationService::class)->topicFor($rehearsal)), ['body' => 'charts'])
            ->assertCreated();

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            $n->toArray($member)['route'] === 'Booking Details'
            && $n->toArray($member)['routeParams'] === ['band' => $band->id, 'booking' => $booking->id, 'comments' => 1]);

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            $n->toArray($member)['route'] === 'rehearsals.show'
            && $n->toArray($member)['routeParams'] === [
                'band' => $band->id,
                'rehearsal_schedule' => $rehearsal->rehearsal_schedule_id,
                'rehearsal' => $rehearsal->id,
                'comments' => 1,
            ]);
    }

    public function test_image_only_comment_uses_photo_placeholder_text(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $topic  = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $topic), [
                'images' => [\Illuminate\Http\UploadedFile::fake()->image('stage.jpg', 50, 50)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            str_ends_with($n->toArray($member)['text'], ': 📷 Photo'));
    }

    public function test_dm_and_band_channel_messages_do_not_create_bell_notifications(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $dm      = app(ConversationService::class)->dmBetween($owner, $member);
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $dm), ['body' => 'hi'])->assertCreated();
        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $channel), ['body' => 'all'])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_bell_route_params_resolve_to_a_url_with_comments_query(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);
        $message = $topic->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $data = (new CommentPosted($message, $topic, 'Test Gig'))->toArray($owner);
        $url  = route($data['route'], $data['routeParams']);

        $this->assertStringContainsString('/events/' . $event->key, $url);
        $this->assertStringContainsString('comments=1', $url);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/CommentPostedNotificationTest.php`
Expected: FAIL — `Class "App\Notifications\CommentPosted" not found`.

- [ ] **Step 3: Create the notification**

`app/Notifications/CommentPosted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\Events;
use App\Models\Message;
use App\Models\Rehearsal;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Web bell entry for a new comment on an event / rehearsal / booking thread.
 *
 * Database-only on purpose: TTSNotification also mails users who have
 * emailNotifications on, and one email per comment is far too noisy. The
 * data shape matches what Layouts/Authenticated.vue already renders
 * (`text`, `route`, `routeParams`); `comments => 1` makes the target page
 * open its comments drawer.
 */
class CommentPosted extends Notification
{
    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
        public readonly string $topicTitle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $route = self::routeFor($this->conversation) ?? ['route' => 'dashboard', 'routeParams' => []];

        $body    = $this->message->body;
        $snippet = ($body !== null && trim($body) !== '') ? Str::limit(trim($body), 80) : '📷 Photo';
        $sender  = $this->message->user->name ?? 'Someone';

        return [
            'text'            => "{$sender} commented on {$this->topicTitle}: {$snippet}",
            'route'           => $route['route'],
            'routeParams'     => $route['routeParams'],
            'conversation_id' => $this->conversation->id,
            'message_id'      => $this->message->id,
        ];
    }

    /**
     * Web page for a topic conversation's item. Rehearsal-backed events are
     * already canonicalised to the Rehearsal by ConversationService, so an
     * Events conversable here is always a booking/band event page.
     *
     * @return array{route: string, routeParams: array}|null
     */
    public static function routeFor(Conversation $conversation): ?array
    {
        $target = $conversation->conversable;

        return match (true) {
            $target instanceof Events => [
                'route'       => 'events.show',
                'routeParams' => ['key' => $target->key, 'comments' => 1],
            ],
            $target instanceof Bookings => [
                'route'       => 'Booking Details',
                'routeParams' => ['band' => (int) $target->band_id, 'booking' => $target->id, 'comments' => 1],
            ],
            $target instanceof Rehearsal => [
                'route'       => 'rehearsals.show',
                'routeParams' => [
                    'band'               => (int) $target->band_id,
                    'rehearsal_schedule' => (int) $target->rehearsal_schedule_id,
                    'rehearsal'          => $target->id,
                    'comments'           => 1,
                ],
            ],
            default => null,
        };
    }
}
```

- [ ] **Step 4: Dispatch it from the push job**

In `app/Jobs/ProcessChatMessagePush.php`:

Add imports:
```php
use App\Notifications\CommentPosted;
use App\Services\Chat\ConversationPresenter;
```

Change the start of `handle()` to eager-load the conversable and precompute the title:
```php
        $message = Message::with(['conversation.band', 'conversation.conversable', 'user'])->find($this->messageId);
        if (!$message || $message->trashed()) {
            return;
        }

        $conversation = $message->conversation;
        $isTopic      = $conversation->type === Conversation::TYPE_TOPIC;
        $topicTitle   = $isTopic ? app(ConversationPresenter::class)->topicTitle($conversation) : null;
```

Inside the `foreach ($this->recipients($conversation) as $userId)` loop, after the `continue` for the author and **before** `SendUserPush::dispatch(...)`, add:
```php
            // Web bell entry (database only). Same audience as the push by
            // construction; DMs and band channels wait for the Messages slice.
            if ($isTopic) {
                User::find($userId)?->notify(new CommentPosted($message, $conversation, $topicTitle));
            }
```

- [ ] **Step 5: Run the tests (plus the existing push suite)**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/CommentPostedNotificationTest.php tests/Feature/Api/Mobile/Chat/ChatPushTest.php`
Expected: PASS (5 + 5).

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/CommentPosted.php app/Jobs/ProcessChatMessagePush.php tests/Feature/Web/Chat/CommentPostedNotificationTest.php
git commit -m "feat(chat): database-only CommentPosted bell notification for topic comments"
```

---

### Task 6: `moderate:chat` on the web permissions page

**Files:**
- Modify: `app/Http/Controllers/UserPermissionsController.php` (`index()` and `store()`)
- Modify: `resources/js/Pages/Band/ShowPermissions.vue`
- Create: `tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php`

**Interfaces:**
- Produces: the `permissions` Inertia prop gains key `moderate:chat` (bool); `POST /permissions/{band}/{user}` honours `permissions['moderate:chat']`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php`:

```php
<?php

namespace Tests\Feature\Web\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ModerateChatPermissionPageTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_permissions_page_exposes_and_round_trips_moderate_chat(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);

        $this->actingAs($owner)
            ->get("/permissions/{$band->id}/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Band/ShowPermissions')
                ->where('permissions.moderate:chat', false)
                ->where('permissions.read:events', true));

        $this->actingAs($owner)
            ->post("/permissions/{$band->id}/{$member->id}", [
                'permissions' => ['read:events' => true, 'moderate:chat' => true],
            ])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->canModerateChat($band->id));

        $this->actingAs($owner)
            ->post("/permissions/{$band->id}/{$member->id}", [
                'permissions' => ['read:events' => true],
            ])
            ->assertRedirect();

        $this->assertFalse($member->fresh()->canModerateChat($band->id));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php`
Expected: FAIL — `Inertia property [permissions.moderate:chat] does not exist`. (If the GET route differs from `/permissions/{band}/{user}`, read `routes/bands.php` / `routes/management.php` for the `UserPermissionsController@index` URI and use that in the test — the Vue page posts to `/permissions/{band}/{user}`, so `store` is at that path.)

- [ ] **Step 3: Backend**

In `UserPermissionsController@index`, change the permissions build to:
```php
        $permissions = collect(BandResource::cases())
            ->flatMap(fn($r) => [
                $r->readPermission()  => $user->hasPermissionTo($r->readPermission()),
                $r->writePermission() => $user->hasPermissionTo($r->writePermission()),
            ])
            ->put('moderate:chat', $user->hasPermissionTo('moderate:chat'))
            ->all();
```

In `store()`, after the `foreach (BandResource::cases() …)` loop and before `setPermissionsTeamId($band->id);`:
```php
        if (!empty($incoming['moderate:chat'])) {
            $grant[] = 'moderate:chat';
        } else {
            $revoke[] = 'moderate:chat';
        }
```

- [ ] **Step 4: Vue**

In `resources/js/Pages/Band/ShowPermissions.vue`, after the closing `</div>` of the `<!-- Permissions Grid -->` `space-y-6` container and before `<!-- Action Buttons -->`, add:

```html
          <!-- Chat moderation -->
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mt-6">
            <h4 class="text-base font-medium text-gray-900 dark:text-white mb-4">
              Chat
            </h4>
            <div class="flex items-center space-x-3">
              <Checkbox
                v-model="localPermissions['moderate:chat']"
                :binary="true"
                input-id="moderate_chat"
              />
              <label
                for="moderate_chat"
                class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer"
              >
                Moderate chat
                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                  Delete other members' comments and messages in this band's threads
                </span>
              </label>
            </div>
          </div>
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/UserPermissionsController.php resources/js/Pages/Band/ShowPermissions.vue tests/Feature/Web/Chat/ModerateChatPermissionPageTest.php
git commit -m "feat(chat): grant moderate:chat from the web permissions page"
```

---

### Task 7: `conversationChannel.js` — refcounted `private-conversation.{id}` subscriber

**Files:**
- Create: `resources/js/realtime/conversationChannel.js`
- Create: `resources/js/tests/realtime/conversationChannel.test.js`

**Interfaces:**
- Produces:
  ```js
  export const CONVERSATION_EVENTS = { created: '.message.created', updated: '.message.updated', deleted: '.message.deleted', read: '.conversation.read', delivered: '.conversation.delivered', typing: '.conversation.typing' };
  // handlers: { onCreated(message), onUpdated(message), onDeleted(messageId), onRead({user_id,last_read_at}), onDelivered({user_id,last_delivered_at}), onTyping({user_id,name}) } — all optional
  export function subscribeConversation(conversationId, handlers): () => void   // idempotent unsubscribe
  export function __resetConversationChannelState(): void
  ```

- [ ] **Step 1: Write the failing test**

`resources/js/tests/realtime/conversationChannel.test.js`:

```js
import { describe, it, expect, beforeEach } from 'vitest';
import { installEchoMock } from '../mocks/echo';
import {
	subscribeConversation,
	CONVERSATION_EVENTS,
	__resetConversationChannelState,
} from '../../realtime/conversationChannel';

describe('subscribeConversation', () => {
	let echo;
	beforeEach(() => {
		__resetConversationChannelState();
		echo = installEchoMock();
	});

	it('subscribes once per conversation and routes each wire event to its handler', () => {
		const seen = { created: [], updated: [], deleted: [], read: [], delivered: [], typing: [] };
		subscribeConversation(7, {
			onCreated: (m) => seen.created.push(m),
			onUpdated: (m) => seen.updated.push(m),
			onDeleted: (id) => seen.deleted.push(id),
			onRead: (p) => seen.read.push(p),
			onDelivered: (p) => seen.delivered.push(p),
			onTyping: (p) => seen.typing.push(p),
		});

		expect(echo.privateCalls).toEqual(['conversation.7']);

		echo.fire('conversation.7', CONVERSATION_EVENTS.created, { message: { id: 1 } });
		echo.fire('conversation.7', CONVERSATION_EVENTS.updated, { message: { id: 1, body: 'x' } });
		echo.fire('conversation.7', CONVERSATION_EVENTS.deleted, { message_id: 1 });
		echo.fire('conversation.7', CONVERSATION_EVENTS.read, { user_id: 2, last_read_at: 't' });
		echo.fire('conversation.7', CONVERSATION_EVENTS.delivered, { user_id: 2, last_delivered_at: 't' });
		echo.fire('conversation.7', CONVERSATION_EVENTS.typing, { user_id: 2, name: 'Al' });

		expect(seen.created).toEqual([{ id: 1 }]);
		expect(seen.updated).toEqual([{ id: 1, body: 'x' }]);
		expect(seen.deleted).toEqual([1]);
		expect(seen.read).toEqual([{ user_id: 2, last_read_at: 't' }]);
		expect(seen.delivered).toEqual([{ user_id: 2, last_delivered_at: 't' }]);
		expect(seen.typing).toEqual([{ user_id: 2, name: 'Al' }]);
	});

	it('refcounts: leaves only when the last subscriber unsubscribes; unsubscribe is idempotent', () => {
		const offA = subscribeConversation(3, {});
		const offB = subscribeConversation(3, {});
		expect(echo.privateCalls).toEqual(['conversation.3']);

		offA();
		offA();
		expect(echo.leaveCalls).toEqual([]);

		offB();
		expect(echo.leaveCalls).toEqual(['conversation.3']);
	});

	it('is a no-op without a conversation id or without Echo', () => {
		expect(typeof subscribeConversation(null, {})).toBe('function');
		delete window.Echo;
		const off = subscribeConversation(9, {});
		off();
		expect(echo.privateCalls).toEqual([]);
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/realtime/conversationChannel.test.js`
Expected: FAIL — cannot resolve `../../realtime/conversationChannel`.

- [ ] **Step 3: Implement**

`resources/js/realtime/conversationChannel.js`:

```js
// Refcounted subscriptions to the full-payload conversation stream.
//
// Companion to bandChannel.js (thin band signals). An open thread subscribes
// to `private-conversation.{id}` and receives whole messages on create /
// update / delete plus read, delivered and typing events, so it can patch
// local state without a refetch. Refcounting means an Inertia page
// transition that mounts the new drawer before the old one unmounts can
// never Echo.leave() a channel that is still in use.

export const CONVERSATION_EVENTS = {
	created: '.message.created',
	updated: '.message.updated',
	deleted: '.message.deleted',
	read: '.conversation.read',
	delivered: '.conversation.delivered',
	typing: '.conversation.typing',
};

// conversationId -> { count, handlers: Set<object> }
const channels = new Map();

function channelName(conversationId) {
	return `conversation.${conversationId}`;
}

function fanOut(entry, key, value) {
	entry.handlers.forEach((h) => h[key]?.(value));
}

function ensureChannel(conversationId) {
	let entry = channels.get(conversationId);
	if (entry) return entry;

	entry = { count: 0, handlers: new Set() };
	channels.set(conversationId, entry);

	window.Echo.private(channelName(conversationId))
		.subscribed(() => {})
		.error((err) => {
			console.warn(`[conversationChannel] auth/subscribe error on ${channelName(conversationId)}`, err);
		})
		.listen(CONVERSATION_EVENTS.created, (p) => fanOut(entry, 'onCreated', p.message))
		.listen(CONVERSATION_EVENTS.updated, (p) => fanOut(entry, 'onUpdated', p.message))
		.listen(CONVERSATION_EVENTS.deleted, (p) => fanOut(entry, 'onDeleted', p.message_id))
		.listen(CONVERSATION_EVENTS.read, (p) => fanOut(entry, 'onRead', p))
		.listen(CONVERSATION_EVENTS.delivered, (p) => fanOut(entry, 'onDelivered', p))
		.listen(CONVERSATION_EVENTS.typing, (p) => fanOut(entry, 'onTyping', p));

	return entry;
}

/**
 * Subscribe a handler bundle to one conversation. Returns an idempotent
 * unsubscribe. Handlers are copied into a fresh object so the same bundle
 * can be subscribed twice without colliding in the Set.
 */
export function subscribeConversation(conversationId, handlers = {}) {
	if (conversationId === null || conversationId === undefined || !window.Echo) return () => {};

	const entry = ensureChannel(conversationId);
	const bundle = { ...handlers };
	entry.count += 1;
	entry.handlers.add(bundle);

	let done = false;
	return () => {
		if (done) return;
		done = true;
		entry.handlers.delete(bundle);
		entry.count -= 1;
		if (entry.count <= 0) {
			channels.delete(conversationId);
			window.Echo?.leave(channelName(conversationId));
		}
	};
}

/** Reset module state (tests only). */
export function __resetConversationChannelState() {
	channels.clear();
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest run resources/js/tests/realtime/conversationChannel.test.js`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/realtime/conversationChannel.js resources/js/tests/realtime/conversationChannel.test.js
git commit -m "feat(chat): refcounted private-conversation channel subscriber for web"
```

---

### Task 8: `useConversationThread` composable

**Files:**
- Create: `resources/js/composables/useConversationThread.js`
- Create: `resources/js/tests/composables/useConversationThread.test.js`

**Interfaces:**
- Consumes: `subscribeConversation` (Task 7); Ziggy global `route()`; `axios` (default import).
- Produces:
  ```js
  export const MARK_READ_DEBOUNCE_MS = 1500, TYPING_TTL_MS = 5000, TYPING_THROTTLE_MS = 3000;
  export function useConversationThread({ currentUserId }) => {
    conversation, messages, participants, hasMore, loading, loadingOlder, error, sending, typingUsers, readSignal, // refs
    load(url), loadOlder(), send({ body, files }), edit(id, body), remove(id), toggleReaction(id, emoji),
    markRead(), notifyTyping(), destroy()
  }
  ```
  `messages` is oldest→newest; `typingUsers` is `[{ user_id, name }]`; `readSignal` increments after each successful read POST (hosts watch it to zero their unread pill).

- [ ] **Step 1: Write the failing tests**

`resources/js/tests/composables/useConversationThread.test.js`:

```js
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { installEchoMock } from '../mocks/echo';
import { CONVERSATION_EVENTS, __resetConversationChannelState } from '../../realtime/conversationChannel';

vi.mock('axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
import axios from 'axios';
import {
	useConversationThread,
	MARK_READ_DEBOUNCE_MS,
	TYPING_TTL_MS,
	TYPING_THROTTLE_MS,
} from '../../composables/useConversationThread';

const ME = 10;
const OTHER = 20;

function msg(id, overrides = {}) {
	return {
		id, conversation_id: 1, user_id: OTHER, user_name: 'Other', user_avatar_url: null,
		body: `m${id}`, attachments: [], reactions: [], edited_at: null, is_deleted: false,
		created_at: `2026-01-01T10:00:0${id}+00:00`, ...overrides,
	};
}

function page(messages, extra = {}) {
	return {
		data: {
			conversation: { id: 1, type: 'topic', title: 'Gig', can_moderate: false, unread_count: 0 },
			messages,
			participants: [{ user_id: ME, name: 'Me', last_read_at: null, last_delivered_at: null }],
			channel: 'private-conversation.1',
			has_more: false,
			...extra,
		},
	};
}

describe('useConversationThread', () => {
	let echo;
	beforeEach(() => {
		vi.useFakeTimers();
		__resetConversationChannelState();
		echo = installEchoMock();
		vi.stubGlobal('route', (name, params) => `/r/${name}/${encodeURIComponent(JSON.stringify(params ?? null))}`);
		axios.get.mockReset(); axios.post.mockReset(); axios.patch.mockReset(); axios.delete.mockReset();
	});
	afterEach(() => {
		vi.useRealTimers();
		vi.unstubAllGlobals();
	});

	it('load() fills state from a ThreadPage and subscribes to the channel', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1), msg(2)]));
		const t = useConversationThread({ currentUserId: ME });

		await t.load('/topic-url');

		expect(axios.get).toHaveBeenCalledWith('/topic-url');
		expect(t.messages.value.map((m) => m.id)).toEqual([1, 2]);
		expect(t.conversation.value.title).toBe('Gig');
		expect(echo.privateCalls).toEqual(['conversation.1']);
		expect(t.loading.value).toBe(false);
	});

	it('load() failure sets error and leaves messages empty', async () => {
		axios.get.mockRejectedValueOnce(new Error('boom'));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');
		expect(t.error.value).toBeTruthy();
		expect(t.messages.value).toEqual([]);
	});

	it('appends realtime messages once (dedupes own echo) and debounces markRead for others', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(2) });
		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(2) });
		expect(t.messages.value.map((m) => m.id)).toEqual([1, 2]);

		expect(axios.post).not.toHaveBeenCalled();
		vi.advanceTimersByTime(MARK_READ_DEBOUNCE_MS);
		await nextTick();
		expect(axios.post).toHaveBeenCalledWith(
			expect.stringContaining('chat.conversations.read'),
			{ last_read_message_id: 2 },
		);
	});

	it('does not markRead on its own echoed message', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(3, { user_id: ME }) });
		vi.advanceTimersByTime(MARK_READ_DEBOUNCE_MS * 2);
		expect(axios.post).not.toHaveBeenCalled();
	});

	it('updated replaces in place, deleted tombstones', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1), msg(2)]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.updated, { message: msg(1, { body: 'edited', edited_at: 'x' }) });
		expect(t.messages.value[0].body).toBe('edited');

		echo.fire('conversation.1', CONVERSATION_EVENTS.deleted, { message_id: 2 });
		expect(t.messages.value[1]).toMatchObject({ id: 2, is_deleted: true, body: null, attachments: [], reactions: [] });
	});

	it('read/delivered patch participants; typing is ignored for self and expires', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.read, { user_id: OTHER, last_read_at: 'R' });
		echo.fire('conversation.1', CONVERSATION_EVENTS.delivered, { user_id: OTHER, last_delivered_at: 'D' });
		const other = t.participants.value.find((p) => p.user_id === OTHER);
		expect(other).toMatchObject({ last_read_at: 'R', last_delivered_at: 'D' });

		echo.fire('conversation.1', CONVERSATION_EVENTS.typing, { user_id: ME, name: 'Me' });
		echo.fire('conversation.1', CONVERSATION_EVENTS.typing, { user_id: OTHER, name: 'Other' });
		expect(t.typingUsers.value).toEqual([{ user_id: OTHER, name: 'Other' }]);

		vi.advanceTimersByTime(TYPING_TTL_MS);
		expect(t.typingUsers.value).toEqual([]);
	});

	it('loadOlder() prepends and keeps order; stops when has_more is false', async () => {
		axios.get.mockResolvedValueOnce(page([msg(5), msg(6)], { has_more: true }));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		axios.get.mockResolvedValueOnce(page([msg(3), msg(4)], { has_more: false }));
		await t.loadOlder();

		expect(axios.get).toHaveBeenLastCalledWith(
			expect.stringContaining('chat.conversations.messages.index'),
			{ params: { before: 5 } },
		);
		expect(t.messages.value.map((m) => m.id)).toEqual([3, 4, 5, 6]);
		expect(t.hasMore.value).toBe(false);

		await t.loadOlder();
		expect(axios.get).toHaveBeenCalledTimes(2);
	});

	it('send() posts multipart and appends the returned message', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		axios.post.mockResolvedValueOnce({ data: { message: msg(9, { user_id: ME, body: 'hi' }) } });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		const file = new File(['x'], 'a.jpg', { type: 'image/jpeg' });
		await t.send({ body: 'hi', files: [file] });

		const [url, form] = axios.post.mock.calls[0];
		expect(url).toContain('chat.conversations.messages.store');
		expect(form).toBeInstanceOf(FormData);
		expect(form.get('body')).toBe('hi');
		expect(form.getAll('images[]')).toHaveLength(1);
		expect(t.messages.value.map((m) => m.id)).toEqual([9]);
	});

	it('toggleReaction() adds when absent, removes when mine, and serialises per message', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1, { reactions: [{ emoji: '👍', count: 1, user_ids: [ME] }] })]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		let resolveDelete;
		axios.delete.mockReturnValueOnce(new Promise((r) => { resolveDelete = r; }));
		const p = t.toggleReaction(1, '👍');
		t.toggleReaction(1, '🎉'); // ignored while in flight
		resolveDelete({ data: { reactions: [] } });
		await p;

		expect(axios.delete).toHaveBeenCalledTimes(1);
		expect(axios.post).not.toHaveBeenCalled();
		expect(t.messages.value[0].reactions).toEqual([]);

		axios.post.mockResolvedValueOnce({ data: { reactions: [{ emoji: '🎉', count: 1, user_ids: [ME] }] } });
		await t.toggleReaction(1, '🎉');
		expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('chat.messages.reactions.store'), { emoji: '🎉' });
		expect(t.messages.value[0].reactions[0].emoji).toBe('🎉');
	});

	it('notifyTyping() is throttled', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		t.notifyTyping(); t.notifyTyping();
		expect(axios.post).toHaveBeenCalledTimes(1);
		vi.advanceTimersByTime(TYPING_THROTTLE_MS);
		t.notifyTyping();
		expect(axios.post).toHaveBeenCalledTimes(2);
	});

	it('destroy() leaves the channel', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');
		t.destroy();
		expect(echo.leaveCalls).toEqual(['conversation.1']);
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/composables/useConversationThread.test.js`
Expected: FAIL — cannot resolve the composable.

- [ ] **Step 3: Implement**

`resources/js/composables/useConversationThread.js`:

```js
import { getCurrentInstance, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { subscribeConversation } from '../realtime/conversationChannel';

export const MARK_READ_DEBOUNCE_MS = 1500;
export const TYPING_TTL_MS = 5000;
export const TYPING_THROTTLE_MS = 3000;

/**
 * Thread state + actions for ONE conversation. Comment-agnostic: the
 * comments drawer and (later) the Messages page both drive it. Mirrors the
 * Flutter ChatThreadNotifier so the two clients behave the same way:
 *  - realtime `message.created` appends only unseen ids (dedupes own echo)
 *    and debounces a read ack when the author is someone else;
 *  - `message.deleted` tombstones in place; read/delivered patch participants;
 *  - typing entries expire after TYPING_TTL_MS and own typing is ignored;
 *  - reactions are serialised per message so a slow response can't reorder.
 */
export function useConversationThread({ currentUserId }) {
	const conversation = ref(null);
	const messages = ref([]);
	const participants = ref([]);
	const hasMore = ref(false);
	const loading = ref(false);
	const loadingOlder = ref(false);
	const error = ref(null);
	const sending = ref(false);
	const typingUsers = ref([]);
	const readSignal = ref(0);

	let unsubscribe = null;
	let readTimer = null;
	let lastTypingAt = 0;
	const typingTimers = new Map();
	const reactionsInFlight = new Set();

	function indexOf(id) {
		return messages.value.findIndex((m) => m.id === id);
	}

	function upsert(message) {
		const i = indexOf(message.id);
		if (i === -1) {
			messages.value = [...messages.value, message];
			return true;
		}
		messages.value.splice(i, 1, message);
		return false;
	}

	function tombstone(id) {
		const i = indexOf(id);
		if (i === -1) return;
		messages.value.splice(i, 1, {
			...messages.value[i],
			body: null,
			attachments: [],
			reactions: [],
			is_deleted: true,
		});
	}

	function patchParticipant(userId, patch) {
		const i = participants.value.findIndex((p) => p.user_id === userId);
		if (i === -1) {
			participants.value = [
				...participants.value,
				{ user_id: userId, name: null, avatar_url: null, last_read_at: null, last_delivered_at: null, ...patch },
			];
			return;
		}
		participants.value.splice(i, 1, { ...participants.value[i], ...patch });
	}

	function onTyping({ user_id, name }) {
		if (user_id === currentUserId) return;
		if (!typingUsers.value.some((u) => u.user_id === user_id)) {
			typingUsers.value = [...typingUsers.value, { user_id, name }];
		}
		clearTimeout(typingTimers.get(user_id));
		typingTimers.set(
			user_id,
			setTimeout(() => {
				typingUsers.value = typingUsers.value.filter((u) => u.user_id !== user_id);
				typingTimers.delete(user_id);
			}, TYPING_TTL_MS),
		);
	}

	function clearTyping(userId) {
		clearTimeout(typingTimers.get(userId));
		typingTimers.delete(userId);
		typingUsers.value = typingUsers.value.filter((u) => u.user_id !== userId);
	}

	function bind(conversationId) {
		unsubscribe?.();
		unsubscribe = subscribeConversation(conversationId, {
			onCreated: (m) => {
				const isNew = upsert(m);
				clearTyping(m.user_id);
				if (isNew && m.user_id !== currentUserId) scheduleMarkRead();
			},
			onUpdated: upsert,
			onDeleted: tombstone,
			onRead: ({ user_id, last_read_at }) => patchParticipant(user_id, { last_read_at }),
			onDelivered: ({ user_id, last_delivered_at }) => patchParticipant(user_id, { last_delivered_at }),
			onTyping,
		});
	}

	function applyPage(data) {
		conversation.value = data.conversation;
		messages.value = data.messages;
		participants.value = data.participants;
		hasMore.value = Boolean(data.has_more);
	}

	async function load(url) {
		loading.value = true;
		error.value = null;
		try {
			const { data } = await axios.get(url);
			applyPage(data);
			bind(data.conversation.id);
		} catch (e) {
			error.value = e;
		} finally {
			loading.value = false;
		}
	}

	async function loadOlder() {
		if (!hasMore.value || loadingOlder.value || !conversation.value || !messages.value.length) return;
		loadingOlder.value = true;
		try {
			const before = messages.value[0].id;
			const { data } = await axios.get(
				route('chat.conversations.messages.index', conversation.value.id),
				{ params: { before } },
			);
			messages.value = [...data.messages, ...messages.value];
			hasMore.value = Boolean(data.has_more);
		} finally {
			loadingOlder.value = false;
		}
	}

	function scheduleMarkRead() {
		clearTimeout(readTimer);
		readTimer = setTimeout(markRead, MARK_READ_DEBOUNCE_MS);
	}

	async function markRead() {
		clearTimeout(readTimer);
		readTimer = null;
		const last = messages.value[messages.value.length - 1];
		if (!conversation.value || !last) return;
		await axios.post(route('chat.conversations.read', conversation.value.id), {
			last_read_message_id: last.id,
		});
		readSignal.value += 1;
	}

	async function send({ body, files = [] }) {
		if (!conversation.value) return null;
		const form = new FormData();
		if (body && body.trim() !== '') form.append('body', body.trim());
		files.forEach((f) => form.append('images[]', f));
		sending.value = true;
		try {
			const { data } = await axios.post(
				route('chat.conversations.messages.store', conversation.value.id),
				form,
			);
			upsert(data.message);
			return data.message;
		} finally {
			sending.value = false;
		}
	}

	async function edit(id, body) {
		const { data } = await axios.patch(route('chat.messages.update', id), { body });
		upsert(data.message);
	}

	async function remove(id) {
		await axios.delete(route('chat.messages.destroy', id));
		tombstone(id);
	}

	async function toggleReaction(id, emoji) {
		if (reactionsInFlight.has(id)) return;
		const i = indexOf(id);
		if (i === -1) return;
		const mine = (messages.value[i].reactions ?? []).some(
			(r) => r.emoji === emoji && r.user_ids.includes(currentUserId),
		);
		reactionsInFlight.add(id);
		try {
			const { data } = mine
				? await axios.delete(route('chat.messages.reactions.destroy', { message: id, emoji }))
				: await axios.post(route('chat.messages.reactions.store', id), { emoji });
			const j = indexOf(id);
			if (j !== -1) messages.value.splice(j, 1, { ...messages.value[j], reactions: data.reactions });
		} finally {
			reactionsInFlight.delete(id);
		}
	}

	function notifyTyping() {
		if (!conversation.value) return;
		const now = Date.now();
		if (now - lastTypingAt < TYPING_THROTTLE_MS) return;
		lastTypingAt = now;
		axios.post(route('chat.conversations.typing', conversation.value.id)).catch(() => {});
	}

	function destroy() {
		unsubscribe?.();
		unsubscribe = null;
		clearTimeout(readTimer);
		readTimer = null;
		typingTimers.forEach((t) => clearTimeout(t));
		typingTimers.clear();
	}

	if (getCurrentInstance()) onBeforeUnmount(destroy);

	return {
		conversation, messages, participants, hasMore, loading, loadingOlder, error, sending, typingUsers, readSignal,
		load, loadOlder, send, edit, remove, toggleReaction, markRead, notifyTyping, destroy,
	};
}
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run resources/js/tests/composables/useConversationThread.test.js`
Expected: PASS (11 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useConversationThread.js resources/js/tests/composables/useConversationThread.test.js
git commit -m "feat(chat): useConversationThread composable mirroring the mobile thread notifier"
```

---

### Task 9: `messageTime` util, `ReactionPicker`, `MessageBubble`

**Files:**
- Create: `resources/js/utils/messageTime.js`
- Create: `resources/js/tests/utils/messageTime.test.js`
- Create: `resources/js/Components/Chat/ReactionPicker.vue`
- Create: `resources/js/Components/Chat/MessageBubble.vue`

**Interfaces:**
- Produces:
  ```js
  // utils/messageTime.js
  export function needsDateSeparator(prevIso|null, currIso): boolean   // new calendar day OR > 1h gap
  export function dateSeparatorLabel(iso, now = DateTime.now()): string // "Today 3:12 PM" | "Yesterday …" | "Thursday …" | "Mar 4, 2026 …"
  export function bubbleTimeLabel(iso): string                          // "3:12 PM"
  ```
  - `ReactionPicker.vue` — `emits: ['pick']` (emoji string); `export const QUICK_REACTIONS = ['👍','❤️','😂','😮','😢','🎉']` from `Components/Chat/reactions.js`.
  - `MessageBubble.vue` — props `message` (wire shape), `isOwn: Boolean`, `canModerate: Boolean`, `currentUserId: Number`; emits `react(emoji)`, `edit()`, `delete()`, `open-attachment(index)`.

- [ ] **Step 1: Write the failing util test**

`resources/js/tests/utils/messageTime.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { DateTime } from 'luxon';
import { needsDateSeparator, dateSeparatorLabel, bubbleTimeLabel } from '../../utils/messageTime';

// Fixed clock: Thu 2026-03-05 15:00 local. Never use the real now() here.
const NOW = DateTime.fromISO('2026-03-05T15:00:00');

describe('messageTime', () => {
	it('needs a separator for the first message, a new day, or a > 1h gap', () => {
		expect(needsDateSeparator(null, '2026-03-05T10:00:00')).toBe(true);
		expect(needsDateSeparator('2026-03-04T23:50:00', '2026-03-05T00:10:00')).toBe(true);
		expect(needsDateSeparator('2026-03-05T10:00:00', '2026-03-05T11:30:00')).toBe(true);
		expect(needsDateSeparator('2026-03-05T10:00:00', '2026-03-05T10:45:00')).toBe(false);
	});

	it('labels today / yesterday / weekday-within-week / full date', () => {
		expect(dateSeparatorLabel('2026-03-05T09:05:00', NOW)).toBe('Today 9:05 AM');
		expect(dateSeparatorLabel('2026-03-04T21:30:00', NOW)).toBe('Yesterday 9:30 PM');
		expect(dateSeparatorLabel('2026-03-02T08:00:00', NOW)).toBe('Monday 8:00 AM');
		expect(dateSeparatorLabel('2026-02-20T08:00:00', NOW)).toBe('Feb 20, 2026 8:00 AM');
	});

	it('bubble time is hour:minute with meridiem', () => {
		expect(bubbleTimeLabel('2026-03-05T15:07:00')).toBe('3:07 PM');
	});
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/utils/messageTime.test.js`
Expected: FAIL — cannot resolve `../../utils/messageTime`.

- [ ] **Step 3: Implement the util**

`resources/js/utils/messageTime.js`:

```js
import { DateTime } from 'luxon';

// Same rules as the Flutter message_time.dart so both clients group identically.

export function needsDateSeparator(prevIso, currIso) {
	if (!prevIso) return true;
	const prev = DateTime.fromISO(prevIso);
	const curr = DateTime.fromISO(currIso);
	return !prev.hasSame(curr, 'day') || curr.diff(prev, 'hours').hours > 1;
}

export function dateSeparatorLabel(iso, now = DateTime.now()) {
	const d = DateTime.fromISO(iso);
	const time = d.toFormat('h:mm a');
	if (d.hasSame(now, 'day')) return `Today ${time}`;
	if (d.hasSame(now.minus({ days: 1 }), 'day')) return `Yesterday ${time}`;
	if (now.diff(d, 'days').days < 7) return `${d.toFormat('cccc')} ${time}`;
	return d.toFormat('MMM d, yyyy h:mm a');
}

export function bubbleTimeLabel(iso) {
	return DateTime.fromISO(iso).toFormat('h:mm a');
}
```

- [ ] **Step 4: Run the util test**

Run: `npx vitest run resources/js/tests/utils/messageTime.test.js`
Expected: PASS. (If luxon renders a narrow no-break space before AM/PM in your ICU, normalise with `.replace(/ | /g, ' ')` inside the util — do it in the util, not the test, so the UI shows a plain space.)

- [ ] **Step 5: Create `reactions.js` and `ReactionPicker.vue`**

`resources/js/Components/Chat/reactions.js`:
```js
// The quick tapback row, identical to the mobile long-press sheet.
export const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];
```

`resources/js/Components/Chat/ReactionPicker.vue`:
```vue
<template>
  <div
    class="inline-flex items-center gap-1 rounded-full bg-white dark:bg-slate-700 shadow-md border border-gray-200 dark:border-slate-600 px-2 py-1"
    role="group"
    aria-label="React"
  >
    <button
      v-for="emoji in QUICK_REACTIONS"
      :key="emoji"
      type="button"
      class="text-lg leading-none px-1 rounded hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
      :aria-label="`React ${emoji}`"
      @click.stop="$emit('pick', emoji)"
    >
      {{ emoji }}
    </button>
  </div>
</template>

<script setup>
import { QUICK_REACTIONS } from './reactions';

defineEmits(['pick']);
</script>
```

- [ ] **Step 6: Create `MessageBubble.vue`**

```vue
<template>
  <div
    class="group flex flex-col"
    :class="isOwn ? 'items-end' : 'items-start'"
    :data-message-id="message.id"
  >
    <div
      v-if="!isOwn"
      class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5 px-1"
    >
      {{ message.user_name }}
    </div>

    <div class="relative max-w-[85%] flex items-end gap-1" :class="isOwn ? 'flex-row-reverse' : ''">
      <!-- Bubble -->
      <div
        class="rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap break-words"
        :class="bubbleClass"
        :title="timeLabel"
      >
        <span v-if="message.is_deleted" class="italic opacity-70">Message deleted</span>
        <template v-else>
          <div
            v-if="message.attachments.length"
            class="grid gap-1 mb-1"
            :class="message.attachments.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
          >
            <button
              v-for="(att, i) in message.attachments"
              :key="att.id"
              type="button"
              class="block rounded-lg overflow-hidden bg-black/5 dark:bg-white/10"
              @click="$emit('open-attachment', i)"
            >
              <img
                :src="attachmentUrl(att)"
                :width="att.width || undefined"
                :height="att.height || undefined"
                class="max-h-64 w-auto object-cover"
                alt=""
                loading="lazy"
              >
            </button>
          </div>
          <span v-if="message.body">{{ message.body }}</span>
          <span
            v-if="message.edited_at"
            class="ml-1 text-[10px] opacity-70"
          >(edited)</span>
        </template>
      </div>

      <!-- Hover actions -->
      <div
        v-if="!message.is_deleted"
        class="opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity flex items-center gap-0.5 text-gray-500 dark:text-gray-400"
      >
        <button
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Add reaction"
          @click.stop="pickerOpen = !pickerOpen"
        >
          <i class="pi pi-face-smile text-sm" />
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Edit"
          @click="$emit('edit')"
        >
          <i class="pi pi-pencil text-sm" />
        </button>
        <button
          v-if="canDelete"
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Delete"
          @click="$emit('delete')"
        >
          <i class="pi pi-trash text-sm" />
        </button>
      </div>

      <div
        v-if="pickerOpen"
        class="absolute -top-10 z-10"
        :class="isOwn ? 'right-0' : 'left-0'"
      >
        <ReactionPicker @pick="onPick" />
      </div>
    </div>

    <!-- Reaction chips -->
    <div
      v-if="message.reactions?.length"
      class="flex flex-wrap gap-1 mt-1 px-1"
    >
      <button
        v-for="r in message.reactions"
        :key="r.emoji"
        type="button"
        class="text-xs rounded-full px-2 py-0.5 border transition-colors"
        :class="r.user_ids.includes(currentUserId)
          ? 'bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900 dark:border-blue-600 dark:text-blue-100'
          : 'bg-gray-100 border-gray-200 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200'"
        :aria-label="`${r.emoji} ${r.count}`"
        @click="$emit('react', r.emoji)"
      >
        {{ r.emoji }} {{ r.count }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ReactionPicker from './ReactionPicker.vue';
import { bubbleTimeLabel } from '@/utils/messageTime';

const props = defineProps({
  message: { type: Object, required: true },
  isOwn: { type: Boolean, default: false },
  canModerate: { type: Boolean, default: false },
  currentUserId: { type: Number, required: true },
});
const emit = defineEmits(['react', 'edit', 'delete', 'open-attachment']);

const pickerOpen = ref(false);

// Edit is author-only and (as on mobile) hidden for messages with images.
const canEdit = computed(() => props.isOwn && props.message.attachments.length === 0);
const canDelete = computed(() => props.isOwn || props.canModerate);
const timeLabel = computed(() => bubbleTimeLabel(props.message.created_at));

const bubbleClass = computed(() => props.isOwn
  ? 'bg-blue-600 text-white rounded-br-md'
  : 'bg-gray-100 text-gray-900 dark:bg-slate-700 dark:text-gray-50 rounded-bl-md');

function attachmentUrl(att) {
  return route('chat.messages.attachments.show', { message: props.message.id, attachment: att.id });
}

function onPick(emoji) {
  pickerOpen.value = false;
  emit('react', emoji);
}
</script>
```

- [ ] **Step 7: Run the util test again and commit**

Run: `npx vitest run resources/js/tests/utils/messageTime.test.js`
Expected: PASS.

```bash
git add resources/js/utils/messageTime.js resources/js/tests/utils/messageTime.test.js resources/js/Components/Chat/reactions.js resources/js/Components/Chat/ReactionPicker.vue resources/js/Components/Chat/MessageBubble.vue
git commit -m "feat(chat): message time helpers, reaction picker and message bubble components"
```

---

### Task 10: `MessageComposer.vue`

**Files:**
- Create: `resources/js/Components/Chat/MessageComposer.vue`
- Create: `resources/js/tests/components/messagecomposer.test.js`

**Interfaces:**
- Produces: props `disabled: Boolean`, `maxImages: Number = 4`; emits `send({ body: string, files: File[] })`, `typing()`. Enter sends, Shift+Enter inserts a newline. Exposes nothing else.

- [ ] **Step 1: Write the failing component test**

`resources/js/tests/components/messagecomposer.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import MessageComposer from '@/Components/Chat/MessageComposer.vue';

function mountComposer(extra = {}) {
	const onSend = vi.fn();
	const onTyping = vi.fn();
	const wrapper = mount(MessageComposer, {
		props: { onSend, onTyping, ...extra },
		global: { stubs: { Button: { template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>', props: ['disabled', 'icon', 'label', 'loading', 'rounded', 'text'] } } },
	});
	return { wrapper, onSend, onTyping };
}

async function type(wrapper, text) {
	const ta = wrapper.find('textarea');
	await ta.setValue(text);
	return ta;
}

describe('MessageComposer', () => {
	it('send button is disabled while empty and enabled once there is text', async () => {
		const { wrapper } = mountComposer();
		expect(wrapper.find('[data-test="send"]').attributes('disabled')).toBeDefined();
		await type(wrapper, 'hello');
		expect(wrapper.find('[data-test="send"]').attributes('disabled')).toBeUndefined();
	});

	it('Enter sends trimmed text and clears; Shift+Enter does not send', async () => {
		const { wrapper, onSend } = mountComposer();
		const ta = await type(wrapper, '  hi there  ');

		await ta.trigger('keydown', { key: 'Enter', shiftKey: true });
		expect(onSend).not.toHaveBeenCalled();

		await ta.trigger('keydown', { key: 'Enter' });
		expect(onSend).toHaveBeenCalledWith({ body: 'hi there', files: [] });
		expect(wrapper.find('textarea').element.value).toBe('');
	});

	it('emits typing while the user types, not for empty input', async () => {
		const { wrapper, onTyping } = mountComposer();
		await type(wrapper, '');
		expect(onTyping).not.toHaveBeenCalled();
		await type(wrapper, 'x');
		expect(onTyping).toHaveBeenCalledTimes(1);
	});

	it('caps attachments at maxImages and allows image-only sends', async () => {
		const { wrapper, onSend } = mountComposer({ maxImages: 2 });
		const files = [1, 2, 3].map((i) => new File(['x'], `${i}.jpg`, { type: 'image/jpeg' }));
		const input = wrapper.find('input[type="file"]');
		Object.defineProperty(input.element, 'files', { value: files });
		await input.trigger('change');

		expect(wrapper.findAll('[data-test="preview"]')).toHaveLength(2);
		expect(wrapper.text()).toContain('Up to 2 images');

		await wrapper.find('[data-test="send"]').trigger('click');
		expect(onSend).toHaveBeenCalledTimes(1);
		expect(onSend.mock.calls[0][0].body).toBe('');
		expect(onSend.mock.calls[0][0].files).toHaveLength(2);
	});

	it('does nothing while disabled', async () => {
		const { wrapper, onSend } = mountComposer({ disabled: true });
		const ta = await type(wrapper, 'hello');
		await ta.trigger('keydown', { key: 'Enter' });
		expect(onSend).not.toHaveBeenCalled();
	});
});
```

Note: jsdom has no `URL.createObjectURL`; the component guards it (see implementation) so previews render with an empty `src` in tests.

- [ ] **Step 2: Run it to verify it fails**

Run: `npx vitest run resources/js/tests/components/messagecomposer.test.js`
Expected: FAIL — cannot resolve the component.

- [ ] **Step 3: Implement**

`resources/js/Components/Chat/MessageComposer.vue`:

```vue
<template>
  <div class="border-t border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 p-2">
    <!-- Attachment previews -->
    <div
      v-if="files.length"
      class="flex gap-2 mb-2 overflow-x-auto"
    >
      <div
        v-for="(f, i) in files"
        :key="f.key"
        data-test="preview"
        class="relative shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-slate-700"
      >
        <img
          v-if="f.url"
          :src="f.url"
          class="w-full h-full object-cover"
          alt=""
        >
        <button
          type="button"
          class="absolute top-0.5 right-0.5 bg-black/60 text-white rounded-full w-5 h-5 text-xs leading-5"
          aria-label="Remove image"
          @click="removeFile(i)"
        >
          ×
        </button>
      </div>
    </div>
    <p
      v-if="capNotice"
      class="text-xs text-amber-600 dark:text-amber-400 mb-1"
    >
      Up to {{ maxImages }} images per comment.
    </p>

    <div class="flex items-end gap-2">
      <label
        class="p-2 rounded-full cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        :class="{ 'opacity-50 pointer-events-none': disabled || files.length >= maxImages }"
        aria-label="Attach image"
      >
        <i class="pi pi-image text-lg" />
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="hidden"
          :disabled="disabled"
          @change="onPick"
        >
      </label>

      <textarea
        ref="textareaRef"
        v-model="body"
        rows="1"
        :disabled="disabled"
        placeholder="Add a comment…"
        class="flex-1 resize-none max-h-40 rounded-2xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @input="onInput"
        @keydown="onKeydown"
        @paste="onPaste"
      />

      <Button
        data-test="send"
        icon="pi pi-send"
        rounded
        :disabled="!canSend"
        aria-label="Send"
        @click="submit"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  maxImages: { type: Number, default: 4 },
});
const emit = defineEmits(['send', 'typing']);

const body = ref('');
const files = ref([]); // [{ file, url, key }]
const capNotice = ref(false);
const textareaRef = ref(null);
let keyCounter = 0;

const canSend = computed(() => !props.disabled && (body.value.trim() !== '' || files.value.length > 0));

function previewUrl(file) {
  return typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : '';
}

function addFiles(list) {
  const incoming = Array.from(list || []).filter((f) => f.type?.startsWith('image/'));
  const room = props.maxImages - files.value.length;
  capNotice.value = incoming.length > room;
  incoming.slice(0, Math.max(0, room)).forEach((file) => {
    files.value.push({ file, url: previewUrl(file), key: `f${keyCounter++}` });
  });
}

function onPick(e) {
  addFiles(e.target.files);
  e.target.value = '';
}

function onPaste(e) {
  const items = Array.from(e.clipboardData?.items || []).filter((i) => i.type.startsWith('image/'));
  if (!items.length) return;
  e.preventDefault();
  addFiles(items.map((i) => i.getAsFile()).filter(Boolean));
}

function removeFile(i) {
  const [removed] = files.value.splice(i, 1);
  if (removed?.url && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(removed.url);
  capNotice.value = false;
}

function autosize() {
  const el = textareaRef.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}

function onInput() {
  autosize();
  if (body.value.trim() !== '') emit('typing');
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    submit();
  }
}

function submit() {
  if (!canSend.value) return;
  emit('send', { body: body.value.trim(), files: files.value.map((f) => f.file) });
  files.value.forEach((f) => f.url && typeof URL.revokeObjectURL === 'function' && URL.revokeObjectURL(f.url));
  body.value = '';
  files.value = [];
  capNotice.value = false;
  nextTick(autosize);
}
</script>
```

- [ ] **Step 4: Run the test**

Run: `npx vitest run resources/js/tests/components/messagecomposer.test.js`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Chat/MessageComposer.vue resources/js/tests/components/messagecomposer.test.js
git commit -m "feat(chat): message composer with image attachments and Enter-to-send"
```

---

### Task 11: `ConversationThread`, `CommentsDrawer`, `CommentsButton`, `useCommentsDrawer`

**Files:**
- Create: `resources/js/Components/Chat/ConversationThread.vue`
- Create: `resources/js/Components/Chat/CommentsDrawer.vue`
- Create: `resources/js/Components/Chat/CommentsButton.vue`
- Create: `resources/js/composables/useCommentsDrawer.js`
- Create: `resources/js/tests/components/commentsbutton.test.js`
- Create: `resources/js/tests/composables/useCommentsDrawer.test.js`
- Modify: `resources/js/app.js` (register `Drawer`)

**Interfaces:**
- Consumes: `useConversationThread` (Task 8), `MessageBubble`, `MessageComposer` (Tasks 9–10), `ImageLightbox` (`props: show, images[], initialIndex; emits close`).
- Produces:
  - `ConversationThread.vue` — props `loadUrl: String (required)`, `currentUserId: Number (required)`; emits `read` (after each successful read ack).
  - `CommentsDrawer.vue` — props `visible: Boolean`, `title: String`, `loadUrl: String`, `currentUserId: Number`; emits `update:visible`, `read`.
  - `CommentsButton.vue` — props `unreadCount: Number = 0`; emits `click`.
  - `useCommentsDrawer(unreadRef) → { open: Ref<boolean>, unread: Ref<number>, openDrawer(), onRead() }` — auto-opens when `?comments=1`.

- [ ] **Step 1: Write the failing tests**

`resources/js/tests/components/commentsbutton.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CommentsButton from '@/Components/Chat/CommentsButton.vue';

describe('CommentsButton', () => {
	it('shows no pill at zero and the count otherwise', () => {
		const zero = mount(CommentsButton, { props: { unreadCount: 0 } });
		expect(zero.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(zero.text()).toContain('Comments');

		const some = mount(CommentsButton, { props: { unreadCount: 3 } });
		expect(some.find('[data-test="unread-pill"]').text()).toBe('3');
	});

	it('emits click', async () => {
		const onClick = vi.fn();
		const w = mount(CommentsButton, { props: { unreadCount: 0, onClick } });
		await w.find('button').trigger('click');
		expect(onClick).toHaveBeenCalledTimes(1);
	});
});
```

`resources/js/tests/composables/useCommentsDrawer.test.js`:
```js
import { describe, it, expect, afterEach } from 'vitest';
import { defineComponent, h, ref, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { useCommentsDrawer } from '../../composables/useCommentsDrawer';

function mountWith(initialUnread, search) {
	window.history.replaceState({}, '', `/events/abc${search}`);
	const unreadProp = ref(initialUnread);
	let api;
	const Host = defineComponent({
		setup() {
			api = useCommentsDrawer(unreadProp);
			return () => h('div', [
				h('span', { 'data-test': 'open' }, String(api.open.value)),
				h('span', { 'data-test': 'unread' }, String(api.unread.value)),
			]);
		},
	});
	const wrapper = mount(Host);
	return { wrapper, api, unreadProp };
}

describe('useCommentsDrawer', () => {
	afterEach(() => window.history.replaceState({}, '', '/'));

	it('starts closed and mirrors the unread prop', async () => {
		const { wrapper, unreadProp } = mountWith(2, '');
		expect(wrapper.find('[data-test="open"]').text()).toBe('false');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('2');
		unreadProp.value = 5;
		await nextTick();
		expect(wrapper.find('[data-test="unread"]').text()).toBe('5');
	});

	it('auto-opens when the URL carries comments=1', () => {
		const { wrapper } = mountWith(0, '?comments=1');
		expect(wrapper.find('[data-test="open"]').text()).toBe('true');
	});

	it('openDrawer opens; onRead zeroes the local unread count', async () => {
		const { wrapper, api } = mountWith(4, '');
		api.openDrawer();
		api.onRead();
		await nextTick();
		expect(wrapper.find('[data-test="open"]').text()).toBe('true');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('0');
	});

	it('treats a null prop (no access) as zero', () => {
		const { wrapper } = mountWith(null, '');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('0');
	});
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npx vitest run resources/js/tests/components/commentsbutton.test.js resources/js/tests/composables/useCommentsDrawer.test.js`
Expected: FAIL — modules not found.

- [ ] **Step 3: `useCommentsDrawer.js`**

```js
import { onMounted, ref, watch } from 'vue';

/**
 * Host-page state for the comments drawer: open flag, a local unread count
 * that mirrors the Inertia prop but can be zeroed immediately when the thread
 * acks a read, and auto-open from the bell deep link (`?comments=1`).
 */
export function useCommentsDrawer(unreadRef) {
	const open = ref(false);
	const unread = ref(unreadRef.value ?? 0);

	watch(unreadRef, (v) => {
		unread.value = v ?? 0;
	});

	onMounted(() => {
		if (typeof window === 'undefined') return;
		if (new URLSearchParams(window.location.search).get('comments') === '1') open.value = true;
	});

	function openDrawer() {
		open.value = true;
	}

	function onRead() {
		unread.value = 0;
	}

	return { open, unread, openDrawer, onRead };
}
```

- [ ] **Step 4: `CommentsButton.vue`**

```vue
<template>
  <button
    type="button"
    class="relative inline-flex items-center gap-2 rounded-md border border-gray-300 dark:border-slate-500 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
    @click="$emit('click')"
  >
    <i class="pi pi-comments" />
    <span>Comments</span>
    <span
      v-if="unreadCount > 0"
      data-test="unread-pill"
      class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-xs font-semibold"
    >{{ unreadCount }}</span>
  </button>
</template>

<script setup>
defineProps({
  unreadCount: { type: Number, default: 0 },
});
defineEmits(['click']);
</script>
```

- [ ] **Step 5: `ConversationThread.vue`**

```vue
<template>
  <div class="flex flex-col h-full min-h-0">
    <!-- Scrollable messages -->
    <div
      ref="scroller"
      class="flex-1 min-h-0 overflow-y-auto px-3 py-2 space-y-2"
      @scroll="onScroll"
    >
      <div
        v-if="loading && !messages.length"
        class="text-sm text-gray-500 dark:text-gray-400 text-center py-6"
      >
        Loading comments…
      </div>

      <div
        v-else-if="error"
        class="text-sm text-center py-6 text-gray-600 dark:text-gray-300"
      >
        Comments unavailable.
        <button
          type="button"
          class="ml-1 text-blue-600 dark:text-blue-400 underline"
          @click="reload"
        >
          Retry
        </button>
      </div>

      <template v-else>
        <div
          v-if="hasMore"
          class="text-center py-1"
        >
          <button
            type="button"
            class="text-xs text-blue-600 dark:text-blue-400"
            :disabled="loadingOlder"
            @click="loadOlderKeepingOffset"
          >
            {{ loadingOlder ? 'Loading…' : 'Load earlier comments' }}
          </button>
        </div>

        <div
          v-if="!messages.length"
          class="text-sm text-gray-500 dark:text-gray-400 text-center py-6"
        >
          No comments yet. Start the conversation.
        </div>

        <template
          v-for="(m, i) in messages"
          :key="m.id"
        >
          <div
            v-if="needsDateSeparator(messages[i - 1]?.created_at, m.created_at)"
            class="text-center text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500 py-1"
          >
            {{ dateSeparatorLabel(m.created_at) }}
          </div>
          <MessageBubble
            :message="m"
            :is-own="m.user_id === currentUserId"
            :can-moderate="conversation?.can_moderate ?? false"
            :current-user-id="currentUserId"
            @react="toggleReaction(m.id, $event)"
            @edit="startEdit(m)"
            @delete="confirmDelete(m)"
            @open-attachment="openLightbox(m, $event)"
          />
        </template>
      </template>
    </div>

    <!-- Receipts + typing -->
    <div class="px-3 min-h-[1.25rem] text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
      <span v-if="typingLabel">{{ typingLabel }}</span>
      <span v-else />
      <button
        v-if="seenSummary"
        type="button"
        class="hover:underline"
        :title="seenNames"
        @click="namesOpen = !namesOpen"
      >
        {{ seenSummary }}
      </button>
    </div>
    <div
      v-if="namesOpen && seenNames"
      class="px-3 pb-1 text-xs text-gray-500 dark:text-gray-400"
    >
      {{ seenNames }}
    </div>

    <!-- Edit banner -->
    <div
      v-if="editing"
      class="px-3 py-1 text-xs bg-amber-50 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 flex items-center justify-between"
    >
      <span>Editing comment</span>
      <button
        type="button"
        class="underline"
        @click="cancelEdit"
      >
        Cancel
      </button>
    </div>
    <div
      v-if="editing"
      class="px-3 pb-2"
    >
      <textarea
        v-model="editBody"
        rows="2"
        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 px-2 py-1 text-sm"
        @keydown.enter.exact.prevent="saveEdit"
      />
      <div class="flex justify-end gap-2 mt-1">
        <Button
          label="Save"
          size="small"
          :disabled="!editBody.trim()"
          @click="saveEdit"
        />
      </div>
    </div>

    <MessageComposer
      v-else
      :disabled="!conversation || sending"
      @send="onSend"
      @typing="notifyTyping"
    />

    <ImageLightbox
      :show="lightbox.show"
      :images="lightbox.images"
      :initial-index="lightbox.index"
      @close="lightbox.show = false"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import ImageLightbox from '@/Components/ImageLightbox.vue';
import MessageBubble from './MessageBubble.vue';
import MessageComposer from './MessageComposer.vue';
import { useConversationThread } from '@/composables/useConversationThread';
import { needsDateSeparator, dateSeparatorLabel } from '@/utils/messageTime';

const props = defineProps({
  loadUrl: { type: String, required: true },
  currentUserId: { type: Number, required: true },
});
const emit = defineEmits(['read']);

const confirm = useConfirm();
const toast = useToast();

const {
  conversation, messages, participants, hasMore, loading, loadingOlder, error, sending, typingUsers, readSignal,
  load, loadOlder, send, edit, remove, toggleReaction, markRead, notifyTyping,
} = useConversationThread({ currentUserId: props.currentUserId });

const scroller = ref(null);
const namesOpen = ref(false);
const editing = ref(null);
const editBody = ref('');
const lightbox = reactive({ show: false, images: [], index: 0 });

watch(readSignal, () => emit('read'));

function isAtBottom() {
  const el = scroller.value;
  if (!el) return true;
  return el.scrollHeight - el.scrollTop - el.clientHeight < 40;
}

function scrollToBottom() {
  nextTick(() => {
    const el = scroller.value;
    if (el) el.scrollTop = el.scrollHeight;
  });
}

// Newest message appended: follow it if we were already at the bottom or it is ours.
watch(() => messages.value.length, (len, prev) => {
  if (len <= prev) return;
  const last = messages.value[len - 1];
  if (last?.user_id === props.currentUserId || isAtBottom()) scrollToBottom();
});

async function reload() {
  await load(props.loadUrl);
  scrollToBottom();
  if (messages.value.length) markRead();
}

onMounted(reload);

async function loadOlderKeepingOffset() {
  const el = scroller.value;
  const before = el ? el.scrollHeight - el.scrollTop : 0;
  await loadOlder();
  await nextTick();
  if (el) el.scrollTop = el.scrollHeight - before;
}

function onScroll() {
  const el = scroller.value;
  if (el && el.scrollTop < 40 && hasMore.value && !loadingOlder.value) loadOlderKeepingOffset();
}

async function onSend(payload) {
  try {
    await send(payload);
    scrollToBottom();
  } catch (e) {
    const detail = e?.response?.data?.message || 'Could not send your comment. Please try again.';
    toast.add({ severity: 'error', summary: 'Not sent', detail, life: 4000 });
  }
}

function startEdit(m) {
  editing.value = m;
  editBody.value = m.body ?? '';
}

function cancelEdit() {
  editing.value = null;
  editBody.value = '';
}

async function saveEdit() {
  if (!editing.value || !editBody.value.trim()) return;
  try {
    await edit(editing.value.id, editBody.value.trim());
    cancelEdit();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Edit failed', detail: e?.response?.data?.message || 'Please try again.', life: 4000 });
  }
}

function confirmDelete(m) {
  confirm.require({
    message: 'Delete this comment?',
    header: 'Delete comment',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await remove(m.id);
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: e?.response?.data?.message || 'Please try again.', life: 4000 });
      }
    },
  });
}

function openLightbox(m, index) {
  lightbox.images = m.attachments.map((a) => ({
    url: route('chat.messages.attachments.show', { message: m.id, attachment: a.id }),
    alt: `${m.user_name} attachment`,
  }));
  lightbox.index = index;
  lightbox.show = true;
}

const typingLabel = computed(() => {
  const names = typingUsers.value.map((u) => u.name).filter(Boolean);
  if (!names.length) return '';
  if (names.length === 1) return `${names[0]} is typing…`;
  if (names.length === 2) return `${names[0]} and ${names[1]} are typing…`;
  return 'Several people are typing…';
});

const lastMessage = computed(() => messages.value[messages.value.length - 1] ?? null);

const seenBy = computed(() => {
  if (!lastMessage.value) return [];
  return participants.value.filter((p) =>
    p.user_id !== props.currentUserId
    && p.last_read_at
    && p.last_read_at >= lastMessage.value.created_at);
});

const seenSummary = computed(() => {
  if (!lastMessage.value || lastMessage.value.user_id !== props.currentUserId) return '';
  if (conversation.value?.type === 'dm') {
    const other = participants.value.find((p) => p.user_id !== props.currentUserId);
    if (other?.last_read_at && other.last_read_at >= lastMessage.value.created_at) return 'Seen';
    if (other?.last_delivered_at && other.last_delivered_at >= lastMessage.value.created_at) return 'Delivered';
    return '';
  }
  return seenBy.value.length ? `Seen by ${seenBy.value.length}` : '';
});

const seenNames = computed(() => seenBy.value.map((p) => p.name).filter(Boolean).join(', '));
</script>
```

Check `ImageLightbox.vue`'s image item shape before wiring (`grep -n "currentImage\." resources/js/Components/ImageLightbox.vue`): if it reads `image.src` rather than `image.url`, use that key in `openLightbox`.

- [ ] **Step 6: `CommentsDrawer.vue` and register `Drawer`**

```vue
<template>
  <Drawer
    :visible="visible"
    position="right"
    class="!w-full md:!w-[28rem]"
    :pt="{ content: { class: 'flex flex-col p-0 min-h-0' } }"
    @update:visible="$emit('update:visible', $event)"
  >
    <template #header>
      <div class="flex items-center gap-2 min-w-0">
        <i class="pi pi-comments text-gray-500 dark:text-gray-400" />
        <span class="font-semibold truncate text-gray-900 dark:text-gray-50">{{ title || 'Comments' }}</span>
      </div>
    </template>

    <ConversationThread
      v-if="visible"
      :load-url="loadUrl"
      :current-user-id="currentUserId"
      class="flex-1 min-h-0"
      @read="$emit('read')"
    />
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer';
import ConversationThread from './ConversationThread.vue';

defineProps({
  visible: { type: Boolean, default: false },
  title: { type: String, default: 'Comments' },
  loadUrl: { type: String, required: true },
  currentUserId: { type: Number, required: true },
});
defineEmits(['update:visible', 'read']);
</script>
```

(`v-if="visible"` on the thread is deliberate: the thread mounts on first open, which triggers `load` + `markRead`, and unmounts on close, which leaves the channel via `onBeforeUnmount`.)

In `resources/js/app.js`: add `import Drawer from 'primevue/drawer';` next to the other PrimeVue imports and `Drawer,` to the `components` object. (Importing it directly in `CommentsDrawer.vue` already works; global registration keeps the project's convention.)

- [ ] **Step 7: Run the tests**

Run: `npx vitest run resources/js/tests/components/commentsbutton.test.js resources/js/tests/composables/useCommentsDrawer.test.js`
Expected: PASS (6 tests).

Also build once to catch template errors: `npx vite build 2>&1 | tail -5` — expect no errors.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Chat resources/js/composables/useCommentsDrawer.js resources/js/tests/components/commentsbutton.test.js resources/js/tests/composables/useCommentsDrawer.test.js resources/js/app.js
git commit -m "feat(chat): conversation thread, comments drawer and header button components"
```

---

### Task 12: Page integration (events, rehearsals, bookings, dashboard)

**Files:**
- Modify: `resources/js/Pages/Events/Show.vue`
- Modify: `resources/js/Pages/Rehearsals/RehearsalDetail.vue`
- Modify: `resources/js/Pages/Bookings/Layout/BookingLayout.vue`
- Modify: `resources/js/Components/NavSubmenu.vue`
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `resources/js/Components/EventCard.vue`

**Interfaces:**
- Consumes: `CommentsButton`, `CommentsDrawer`, `useCommentsDrawer` (Task 11); props `unreadCommentCount` (Task 3) and `event.unread_comment_count` (Task 4); routes from Task 2.

- [ ] **Step 1: Events/Show.vue**

Imports (add to the `<script setup>` block):
```js
import { toRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CommentsButton from '@/Components/Chat/CommentsButton.vue';
import CommentsDrawer from '@/Components/Chat/CommentsDrawer.vue';
import { useCommentsDrawer } from '@/composables/useCommentsDrawer';
```
(`computed` is already imported from vue; merge `toRef` into that import. If `usePage` is already imported, don't duplicate.)

Add to `defineProps`:
```js
  unreadCommentCount: {
    type: Number,
    default: null,
  },
```

After `useBandRealtime(props.band.id, { … })`, add `message: ['unreadCommentCount'],` as a new key inside that map, then below it:
```js
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const comments = useCommentsDrawer(toRef(props, 'unreadCommentCount'));
const commentsUrl = computed(() => route('chat.events.conversation', props.event.key));
```

Template — in the header row, directly before the `<Link :href="route('setlists.show', event.key)">` Setlist button:
```html
            <CommentsButton
              v-if="unreadCommentCount !== null"
              :unread-count="comments.unread.value"
              @click="comments.openDrawer()"
            />
```
and at the very end of the outer `<Container>` content (before the closing `</Container>`):
```html
      <CommentsDrawer
        v-if="unreadCommentCount !== null"
        v-model:visible="comments.open.value"
        :title="`Comments · ${event.title}`"
        :load-url="commentsUrl"
        :current-user-id="currentUserId"
        @read="comments.onRead()"
      />
```

- [ ] **Step 2: Rehearsals/RehearsalDetail.vue**

Same imports as Step 1 (this page uses `<script setup>`; it imports `Link` from `@inertiajs/vue3` — extend that import with `usePage`). Add the `unreadCommentCount` prop (default `null`). In its `useBandRealtime` map add `message: ['unreadCommentCount'],`. Add:
```js
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const comments = useCommentsDrawer(toRef(props, 'unreadCommentCount'));
const commentsUrl = computed(() => route('chat.rehearsals.conversation', props.rehearsal.id));
const rehearsalTitle = computed(() => props.rehearsal.events?.[0]?.title || 'Rehearsal');
```
(import `computed, toRef` from vue.)

Template — inside the header's right-hand `<div class="flex gap-2">`, as the first child:
```html
                  <CommentsButton
                    v-if="unreadCommentCount !== null"
                    :unread-count="comments.unread.value"
                    @click="comments.openDrawer()"
                  />
```
and before the closing `</Container>`:
```html
      <CommentsDrawer
        v-if="unreadCommentCount !== null"
        v-model:visible="comments.open.value"
        :title="`Comments · ${rehearsalTitle}`"
        :load-url="commentsUrl"
        :current-user-id="currentUserId"
        @read="comments.onRead()"
      />
```

- [ ] **Step 3: Bookings — `NavSubmenu.vue` + `BookingLayout.vue`**

`NavSubmenu.vue`: add props and an emit, and render the button next to the status pill.

Props block becomes:
```js
const props = defineProps({
    routes: { type: Object, required: true },
    booking: { type: Object, required: true },
    unreadCommentCount: { type: Number, default: null },
});
const emit = defineEmits(['open-comments']);
```
Add `import CommentsButton from '@/Components/Chat/CommentsButton.vue';`.

Template — wrap the status pill so the button sits beside it:
```html
            <div class="flex items-center gap-2">
              <CommentsButton
                v-if="unreadCommentCount !== null"
                :unread-count="unreadCommentCount"
                @click="emit('open-comments')"
              />
              <span
                data-test="status-pill"
                :class="statusClass"
                class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide whitespace-nowrap"
              >
                {{ booking.status }}
              </span>
            </div>
```

`BookingLayout.vue`:
```vue
<template>
  <BreezeAuthenticatedLayout>
    <container>
      <NavSubmenu
        :routes="filteredRoutes"
        :booking="booking"
        :unread-comment-count="comments.unread.value"
        @open-comments="comments.openDrawer()"
      />
      <slot />
      <CommentsDrawer
        v-if="unreadCommentCount !== null"
        v-model:visible="comments.open.value"
        :title="`Comments · ${booking.name}`"
        :load-url="commentsUrl"
        :current-user-id="currentUserId"
        @read="comments.onRead()"
      />
    </container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import { computed, toRef } from "vue"
import { usePage } from '@inertiajs/vue3'
import { Ziggy } from '@/ziggy'
import NavSubmenu from '@/Components/NavSubmenu.vue';
import CommentsDrawer from '@/Components/Chat/CommentsDrawer.vue';
import { useCommentsDrawer } from '@/composables/useCommentsDrawer';
import { useBandRealtime } from '@/composables/useBandRealtime';

const props = defineProps({
  booking: Object,
  unreadCommentCount: { type: Number, default: null },
})

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const comments = useCommentsDrawer(toRef(props, 'unreadCommentCount'));
const commentsUrl = computed(() => route('chat.bookings.conversation', { band: props.booking.band_id, booking: props.booking.id }));

// Layout-level: refresh the pill when a comment lands on any booking tab.
useBandRealtime(props.booking.band_id, { message: ['unreadCommentCount'] });

const excludeRoutes = ['Create Booking', 'Booking Receipt', 'Download Booking Contract', 'bookings.history', 'bookings.historyJson', 'View Booking Contract', 'portal', 'chat.bookings.conversation']
const filteredRoutes = computed(() => {
  return Object.entries(Ziggy.routes).reduce((acc, [name, route]) => {
    if (route.uri.includes('booking/') &&
    !excludeRoutes.includes(name) &&
    !route.uri.includes('portal') &&
    route.methods.includes('GET')) {
      acc[name] = route
    }
    return acc
  }, {})
})
</script>
```

Note: only `Bookings/Show` sends `unreadCommentCount` (Task 3). On the other booking tabs the prop is absent → `null` → no button. That matches the spec's "prop on the three show pages"; if the button should appear on every booking tab, add the same prop to the other booking tab controllers — flag this in the PR rather than expanding scope silently.

- [ ] **Step 4: Dashboard — `EventCard.vue` + `Dashboard.vue`**

`EventCard.vue` — add a pill right after the opening `<div :class="[…]">` root element, before the rehearsal edit button:
```html
    <span
      v-if="event.unread_comment_count > 0"
      data-test="unread-comments"
      class="absolute top-2 inline-flex items-center gap-1 rounded-full bg-red-500 text-white text-xs font-semibold px-2 py-0.5"
      :class="isRehearsal && canEditRehearsal ? 'right-12' : 'right-2'"
      :title="`${event.unread_comment_count} unread comment${event.unread_comment_count === 1 ? '' : 's'}`"
    >
      <i class="pi pi-comments text-[10px]" />
      {{ event.unread_comment_count }}
    </span>
```

`Dashboard.vue` — in the `useBandRealtime(usePage().props.auth?.user?.band_ids ?? [], { … })` map add:
```js
      message: ['events'],
```

- [ ] **Step 5: Build and run the whole Vitest suite**

Run: `npx vite build 2>&1 | tail -5` — expect no errors.
Run: `npx vitest run` — expect all green (new + pre-existing). If `eventcardlodging.test.js` or other EventCard tests break because of the new pill, they should not — the pill is `v-if`-gated on a field those fixtures do not set.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Events/Show.vue resources/js/Pages/Rehearsals/RehearsalDetail.vue resources/js/Pages/Bookings/Layout/BookingLayout.vue resources/js/Components/NavSubmenu.vue resources/js/Pages/Dashboard.vue resources/js/Components/EventCard.vue
git commit -m "feat(chat): comments drawer on event, rehearsal and booking pages; dashboard unread pills"
```

---

### Task 13: Full verification, browser check, PR

**Files:** none new.

- [ ] **Step 1: Full backend + frontend suites**

Run: `docker compose exec -T app php artisan test tests/Feature/Web/Chat tests/Feature/Services/Chat tests/Feature/Api/Mobile/Chat tests/Feature/Api/Mobile/DashboardUnreadCommentTest.php tests/Feature/DashboardLodgingTest.php`
Expected: PASS.
Run: `NODE_ENV=production npx vitest run --mode=pipeline` (CI's mode — catches `wrapper.vm`/`emitted()` reliance).
Expected: PASS.

- [ ] **Step 2: Browser verification against the local Docker backend**

Use the Chrome DevTools MCP tools (`new_page`, `navigate_page`, `take_snapshot`, `click`, `fill`, `take_screenshot`, `list_network_requests`). Local web URL and credentials are in `~/.claude/projects/-home-eddie-github-tts-bandmate/memory/reference_on_device_driving.md`.

Script:
1. Log in as an owner, open an upcoming event page. Confirm the **Comments** button shows next to Setlist and the drawer opens with "No comments yet".
2. Post a text comment → bubble appears, composer clears, `POST …/messages` is 201 in the network panel.
3. In a second page/tab logged in as a member, open the same event: the comment is there; post a reply. Back in tab 1 the reply appears **without a reload** (realtime), "Seen by 1" appears under your own last message once tab 2 has it open.
4. Hover a bubble → react 👍; chip appears in both tabs. Edit your own text comment; "(edited)" shows. Delete → "Message deleted" tombstone in both tabs.
5. Attach a JPEG → thumbnail renders (attachment request 200 with `image/jpeg`); click → lightbox.
6. Close the drawer, post from tab 2 → the Comments pill increments on tab 1 within ~1s (band signal → prop reload). Dashboard shows the red pill on that event card.
7. Bell: as the member, open the bell → a "… commented on …" row; click it → event page opens with the drawer already open (`?comments=1`).
8. Booking page: Comments button beside the status pill; rehearsal page: button in the action row; both drawers load.
9. Permissions page for a member: tick "Moderate chat", save, reload — still ticked; that member can now delete the owner's comment.
Take screenshots of 1, 3, 5, 7 into the scratchpad for the PR description.

Fix anything found before opening the PR (each fix is its own commit).

- [ ] **Step 3: Open the PR (base `staging`)**

```bash
git push -u origin feat/web-comments
gh pr create --base staging --title "feat(chat): web comments drawer on event, rehearsal and booking pages (parity slice 1)" --body-file - <<'EOF'
## Summary
Web phase of the unified comments/chat system (deferred by the 2026-07-12 spec). Spec: `docs/superpowers/specs/2026-10-04-web-comments-design.md`.

- `ConversationPresenter` extracted from the mobile controller (byte-identical output; all mobile chat suites unchanged)
- `routes/chat.php`: session-auth routes over the same controllers
- `unreadCommentCount` prop on Events/Show, RehearsalDetail, Bookings/Show; `unread_comment_count` on web dashboard rows
- `CommentPosted` database-only bell notification (topic threads, push audience minus author, deep-links with `?comments=1`)
- `moderate:chat` on the web permissions page
- Vue: `conversationChannel.js`, `useConversationThread`, `Components/Chat/*` (drawer, thread, bubble, composer, reactions), page integration + dashboard pills

No migration, no mobile change, wire contract untouched.

## Test plan
- [ ] `php artisan test tests/Feature/Web/Chat tests/Feature/Services/Chat tests/Feature/Api/Mobile/Chat`
- [ ] `NODE_ENV=production npx vitest run --mode=pipeline`
- [ ] Browser: post / live reply / react / edit / delete / image + lightbox / pill refresh / bell deep link / booking + rehearsal drawers / moderate:chat round-trip

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_012ZDh9GpWe1T87HGLYpc8oQ
EOF
```

- [ ] **Step 4: Wait for the Copilot review and address every comment** (project convention), then report the PR URL, test results, and anything left open (e.g. Comments button on non-Show booking tabs).

---

## Self-review notes

- **Spec coverage:** presenter (T1), routes (T2), props (T3), dashboard (T4), bell (T5), moderate:chat (T6), channel (T7), composable (T8), bubble/reactions/time (T9), composer (T10), thread/drawer/button/auto-open (T11), page integration + dashboard pills + reload maps (T12), browser verification + rollout (T13). Error states (loading / empty / error+retry / send toast) live in T11's `ConversationThread`. "Button absent when `unreadCommentCount` is null" is enforced by every `v-if="unreadCommentCount !== null"` in T12.
- **Type consistency:** `unreadCountFor(): ?int` (T1) ⇄ prop default `null` (T12) ⇄ `useCommentsDrawer` treats null as 0 (T11). `readSignal` (T8) ⇄ `watch(readSignal, () => emit('read'))` (T11) ⇄ `@read="comments.onRead()"` (T12). Route names in T2 match every `route('chat.…')` call in T8, T9, T11, T12. `CommentPosted::routeFor` route names match `routes/events.php` (`events.show`), `routes/booking.php` (`Booking Details`), `routes/rehearsals.php` (`rehearsals.show`).
- **Known scope edge (flagged, not expanded):** only `Bookings/Show` receives `unreadCommentCount`, so the Comments button renders on the booking overview tab only; other booking tabs would need the prop added to their controllers.
