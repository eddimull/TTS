<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\QuestionnaireInstances;
use App\Models\Song;

/**
 * Collects client must-play / do-not-play song picks from submitted (or
 * locked) questionnaire instances on a booking, so setlist surfaces can
 * highlight the band's catalog accordingly.
 */
class ClientSongRequestsService
{
    /**
     * @return array{
     *   must_play: array<int>,
     *   do_not_play: array<int>,
     *   source: array{instance_id: int, name: string, recipient_name: string|null, submitted_at: string|null}
     * }|null  null when no submitted instance carries any song picks.
     */
    public function forBooking(Bookings $booking): ?array
    {
        $instances = QuestionnaireInstances::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', [
                QuestionnaireInstances::STATUS_SUBMITTED,
                QuestionnaireInstances::STATUS_LOCKED,
            ])
            ->with([
                'fields' => fn ($q) => $q->where('type', 'song_picker'),
                'responses',
                'recipientContact',
            ])
            ->orderByDesc('submitted_at')
            ->get();

        // Per-instance picks, most recently submitted first.
        $picks = [];
        foreach ($instances as $instance) {
            $responses = $instance->responses->keyBy('instance_field_id');
            $must = collect();
            $skip = collect();

            foreach ($instance->fields as $field) {
                $purpose = $field->settings['purpose'] ?? null;
                if (!in_array($purpose, ['must_play', 'do_not_play'], true)) {
                    continue;
                }
                $decoded = json_decode((string) ($responses->get($field->id)?->value ?? ''), true);
                if (!is_array($decoded)) {
                    continue;
                }
                $ids = collect($decoded)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
                if ($purpose === 'must_play') {
                    $must = $must->merge($ids);
                } else {
                    $skip = $skip->merge($ids);
                }
            }

            if ($must->isNotEmpty() || $skip->isNotEmpty()) {
                $picks[] = ['instance' => $instance, 'must' => $must, 'skip' => $skip];
            }
        }

        if ($picks === []) {
            return null;
        }

        $mustPlay = collect($picks)->flatMap(fn ($p) => $p['must']);
        $doNotPlay = collect($picks)->flatMap(fn ($p) => $p['skip']);

        // Only songs still in the band's active catalog are meaningful.
        $valid = Song::query()
            ->where('band_id', $booking->band_id)
            ->where('active', true)
            ->whereIn('id', $mustPlay->merge($doNotPlay)->unique())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $doNotPlay = $doNotPlay->unique()->filter(fn ($id) => $valid->contains($id))->values();
        // A song the client flagged both ways is treated as do-not-play.
        $mustPlay = $mustPlay->unique()
            ->filter(fn ($id) => $valid->contains($id) && !$doNotPlay->contains($id))
            ->values();

        if ($mustPlay->isEmpty() && $doNotPlay->isEmpty()) {
            return null;
        }

        // Headline source: the most recent instance that contributes at least
        // one surviving pick (an instance whose songs were all removed from the
        // catalog shouldn't be credited).
        $source = null;
        foreach ($picks as $p) {
            $contributes = $p['must']->merge($p['skip'])->contains(fn ($id) => $valid->contains($id));
            if ($contributes) {
                $instance = $p['instance'];
                $source = [
                    'instance_id'    => $instance->id,
                    'name'           => $instance->name,
                    'recipient_name' => $instance->recipientContact?->name,
                    'submitted_at'   => $instance->submitted_at?->toIso8601String(),
                ];
                break;
            }
        }

        return [
            'must_play'   => $mustPlay->all(),
            'do_not_play' => $doNotPlay->all(),
            'source'      => $source,
        ];
    }
}
