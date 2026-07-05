<?php

namespace App\Services\Push;

use App\Jobs\SendUserPush;
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

        $firstTime = $firstItem['time'] ?? $event->start_time;
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
            $dedupeKey = "event:{$event->id}:{$type}";

            // Idempotency pre-check. NOTE: the log row is written by SendUserPush
            // only after delivery, so two ticks within the grace window could both
            // pass this check and dispatch before either logs — a user could get a
            // duplicate push. The (user_id,dedupe_key) unique index guarantees a
            // single log row regardless. Acceptable for a reminder; if at-most-once
            // delivery is ever required, claim the log row here before dispatching.
            $already = PushNotificationLog::where('user_id', $member->user_id)
                ->where('dedupe_key', $dedupeKey)
                ->exists();
            if ($already) {
                continue;
            }

            SendUserPush::dispatch(
                $member->user_id,
                $this->payload($event, $type, $firstItem, $tz, $firstItemDt),
                $dedupeKey,
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

        // Contract: every push carries a display-ready body so clients that
        // don't know the type can still render it. Known clients ignore this
        // and render richer copy from the structured fields.
        $data['body'] = !empty($event->resolved_venue_address)
            ? ((string) $event->resolved_venue_address) . ' · You have an event today'
            : 'You have an event today';
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
            $timeStr = preg_match('/(\d{1,2}:\d{2})/', (string) $time, $m) ? $m[1] : (string) $time;

            return Carbon::parse("{$dateStr} {$timeStr}", $tz);
        } catch (\Throwable) {
            return null;
        }
    }
}
