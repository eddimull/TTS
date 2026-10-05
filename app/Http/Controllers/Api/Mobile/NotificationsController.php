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
