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
        $booking = $bookingId ? Bookings::select('id', 'band_id')->find($bookingId) : null;
        if (!$booking) {
            return $this->dashboard();
        }
        $bandId = (int) ($booking->band_id ?: $bandId);

        return ['booking', "/bookings/{$bandId}/{$booking->id}", "/bands/{$bandId}/booking/{$booking->id}"];
    }

    private function eventByKey(mixed $key): array
    {
        $event = is_string($key) && $key !== '' ? Events::select('id', 'key')->where('key', $key)->first() : null;

        return $event ? ['event', "/events/{$event->key}", "/events/{$event->key}"] : $this->dashboard();
    }

    private function eventById(int $id): array
    {
        $event = $id ? Events::select('id', 'key')->find($id) : null;

        return $event ? ['event', "/events/{$event->key}", "/events/{$event->key}"] : $this->dashboard();
    }

    private function rehearsal(int $id): array
    {
        $rehearsal = $id ? Rehearsal::select('id', 'band_id', 'rehearsal_schedule_id')->find($id) : null;
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
        $instance = QuestionnaireInstances::select('id', 'questionnaire_id')->find($id);
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
