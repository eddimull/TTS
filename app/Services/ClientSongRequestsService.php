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

        $mustPlay = collect();
        $doNotPlay = collect();
        $source = null;

        foreach ($instances as $instance) {
            $responses = $instance->responses->keyBy('instance_field_id');
            $picked = false;

            foreach ($instance->fields as $field) {
                $purpose = $field->settings['purpose'] ?? null;
                if (!in_array($purpose, ['must_play', 'do_not_play'], true)) {
                    continue;
                }
                $decoded = json_decode((string) ($responses->get($field->id)?->value ?? ''), true);
                if (!is_array($decoded) || $decoded === []) {
                    continue;
                }
                $ids = collect($decoded)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
                if ($ids->isEmpty()) {
                    continue;
                }
                $picked = true;
                if ($purpose === 'must_play') {
                    $mustPlay = $mustPlay->merge($ids);
                } else {
                    $doNotPlay = $doNotPlay->merge($ids);
                }
            }

            // Most recently submitted instance with picks is the headline source.
            if ($picked && $source === null) {
                $source = [
                    'instance_id'    => $instance->id,
                    'name'           => $instance->name,
                    'recipient_name' => $instance->recipientContact?->name,
                    'submitted_at'   => $instance->submitted_at?->toIso8601String(),
                ];
            }
        }

        if ($mustPlay->isEmpty() && $doNotPlay->isEmpty()) {
            return null;
        }

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

        return [
            'must_play'   => $mustPlay->all(),
            'do_not_play' => $doNotPlay->all(),
            'source'      => $source,
        ];
    }
}
