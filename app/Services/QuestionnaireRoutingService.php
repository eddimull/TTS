<?php

namespace App\Services;

use App\Exceptions\QuestionnaireRoutingException;
use App\Models\Events;
use App\Models\QuestionnaireApplications;
use App\Models\QuestionnaireFields;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The "Send to Event" review: groups a questionnaire's answers by the part
 * of the event they belong to, flags conflicts, applies a chosen batch in
 * one transaction, and undoes it from a snapshot.
 */
class QuestionnaireRoutingService
{
    /** Answers that mean "not decided yet" and shouldn't be sent by default. */
    private const PLACEHOLDERS = ['', 'tbd', 'n/a', 'none', '?', '-'];

    /** Field types that never become a routable item. */
    private const NON_ROUTABLE_TYPES = ['header', 'instructions', 'song_picker'];

    private const TEXT_TYPES = ['short_text', 'long_text'];

    public const SONG_RESOLUTIONS = ['must_play', 'do_not_play'];
    public const VENUE_RESOLUTIONS = ['booking', 'form'];

    public function __construct(
        private QuestionnaireMappingRegistry $registry,
        private QuestionnaireMappingService $mappingService,
    ) {
    }

    public function preview(QuestionnaireInstances $instance, ?Events $event = null): array
    {
        $instance->loadMissing(['fields', 'responses', 'booking.contacts']);
        $event ??= $this->mappingService->resolveEvent($instance);

        $fields = $instance->fields->sortBy('position')->values();
        $responses = $instance->responses->keyBy('instance_field_id');

        $venueField = $this->venueField($fields);
        $writeInFields = $this->writeInFields($fields);
        $groups = [];
        $unrouted = [];

        foreach ($fields as $field) {
            if (in_array($field->type, self::NON_ROUTABLE_TYPES, true)) {
                continue;
            }
            $response = $responses->get($field->id);
            if (!$response) {
                continue;
            }

            $display = $this->mappingService->displayValue($response->value, $field->type);
            $target = $this->effectiveTarget($response, $field);

            if ($target === null) {
                $isSpecial = in_array($field->type, ['email', 'phone'], true)
                    || $field->id === $venueField?->id
                    || $writeInFields['must_play']->contains('id', $field->id)
                    || $writeInFields['do_not_play']->contains('id', $field->id);
                if ($isSpecial || $display === '') {
                    continue;
                }
                $unrouted[] = [
                    'response_id' => $response->id,
                    'field_label' => $field->label,
                    'field_type' => $field->type,
                    'value_display' => $display,
                    'compatible_targets' => $this->compatibleTargets($field->type),
                ];
                continue;
            }

            $status = $this->status($response);
            $groups[$this->registry->group($target)][] = [
                'response_id' => $response->id,
                'field_label' => $field->label,
                'field_type' => $field->type,
                'value_display' => $display,
                'target' => $target,
                'target_label' => $this->registry->shortLabel($target),
                'status' => $status,
                'default_checked' => !$this->isPlaceholder($display) && $status !== 'applied',
                'compatible_targets' => $this->compatibleTargets($field->type),
            ];
        }

        $songRequests = $this->songRequests($instance, $fields, $responses, $writeInFields);

        return [
            'event_id' => $event->id,
            'groups' => collect(QuestionnaireMappingRegistry::GROUPS)
                ->filter(fn ($label, $key) => !empty($groups[$key]))
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'items' => $groups[$key]])
                ->values()
                ->all(),
            'conflicts' => array_values(array_filter([
                ...$songRequests['conflicts'],
                $this->venueConflict($event, $venueField, $venueField ? $responses->get($venueField->id) : null),
            ])),
            'song_requests' => $songRequests['summary'],
            'contacts' => $this->contacts($instance, $fields, $responses),
            'unrouted' => $unrouted,
            'targets' => collect($this->registry->keys())->map(fn ($key) => [
                'key' => $key,
                'label' => $this->registry->shortLabel($key),
                'group' => $this->registry->group($key),
            ])->all(),
        ];
    }

    /**
     * Apply a reviewed batch. Everything happens in one transaction: a bad
     * item rolls back every write before it.
     *
     * @param  array<int, array{response_id: int, target: string, remember?: bool}>  $items
     * @param  array{songs?: array<int|string, string>, venue?: string|null}  $resolutions
     * @return array{application: QuestionnaireApplications, applied: int, event: Events}
     */
    public function route(
        QuestionnaireInstances $instance,
        array $items,
        array $resolutions,
        User $user,
        ?Events $event = null,
    ): array {
        $event ??= $this->resolveEventOrFail($instance);

        return DB::transaction(function () use ($instance, $items, $resolutions, $user, $event) {
            $event = Events::query()->lockForUpdate()->findOrFail($event->id);
            $instance = QuestionnaireInstances::query()->lockForUpdate()->findOrFail($instance->id);
            $instance->load('fields');
            // Lock the answers too, so a portal edit can't land between
            // reading a value and stamping it applied.
            $instance->setRelation('responses', $instance->responses()
                ->lockForUpdate()
                ->with('instanceField')
                ->get());

            $before = [
                'additional_data' => json_decode(json_encode($event->additional_data), true),
                'notes' => $event->notes,
                'venue_name' => $event->venue_name,
                'song_overrides' => $instance->song_overrides,
            ];

            $responses = $instance->responses->keyBy('id');
            $appliedIds = [];
            $now = now();

            foreach ($items as $item) {
                $responseId = (int) $item['response_id'];
                $target = (string) $item['target'];

                /** @var QuestionnaireResponses|null $response */
                $response = $responses->get($responseId);
                $field = $response?->instanceField;
                if (!$response || !$field) {
                    throw new QuestionnaireRoutingException("Answer #{$responseId} isn't part of this questionnaire.");
                }
                if (!$this->registry->isCompatible($target, $field->type)) {
                    throw new QuestionnaireRoutingException(
                        "\"{$field->label}\" can't be sent to {$target}."
                    );
                }

                $this->rememberStamp($before, $response);
                $this->mappingService->writeTarget($event, $target, $field, $response->value);

                $response->routed_target = $target === $field->mapping_target ? null : $target;
                $this->stampApplied($response, $user, $now);
                $appliedIds[] = $response->id;

                if (!empty($item['remember'])) {
                    $this->rememberOnTemplate($instance, $field, $target);
                }
            }

            $songs = $resolutions['songs'] ?? [];
            if (!empty($songs)) {
                $overrides = $instance->song_overrides ?? [];
                foreach ($songs as $songId => $choice) {
                    $overrides[(string) (int) $songId] = $choice;
                }
                $instance->song_overrides = $overrides;
                $instance->save();
            }

            $venue = $resolutions['venue'] ?? null;
            if ($venue !== null) {
                $venueField = $this->venueField($instance->fields);
                $venueResponse = $venueField ? $instance->responses->firstWhere('instance_field_id', $venueField->id) : null;
                if (!$venueResponse) {
                    throw new QuestionnaireRoutingException('This questionnaire has no venue answer to resolve.');
                }
                $this->rememberStamp($before, $venueResponse);
                if ($venue === 'form') {
                    $event->venue_name = $this->mappingService->displayValue($venueResponse->value, $venueField->type);
                }
                $this->stampApplied($venueResponse, $user, $now);
                $appliedIds[] = $venueResponse->id;
            }

            $event->save();
            $event->refresh();

            $application = QuestionnaireApplications::create([
                'instance_id' => $instance->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'before' => $before,
                'applied_response_ids' => array_values(array_unique($appliedIds)),
                'event_updated_at' => $event->updated_at,
                'event_fingerprint' => $this->fingerprint($event),
            ]);

            return ['application' => $application, 'applied' => count($items), 'event' => $event];
        });
    }

    /**
     * Restore what a batch overwrote, as long as nobody edited the event
     * since. Clears the applied stamp on the answers the batch sent.
     */
    public function undo(QuestionnaireApplications $application): Events
    {
        return DB::transaction(function () use ($application) {
            $application = QuestionnaireApplications::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->undone_at) {
                throw QuestionnaireRoutingException::conflict('This send was already undone.');
            }

            // Undo is a stack: a later send (even a songs-only one that left
            // the event untouched) snapshotted state this one would clobber.
            QuestionnaireInstances::withTrashed()->lockForUpdate()->find($application->instance_id);
            $newer = QuestionnaireApplications::query()
                ->where('id', '>', $application->id)
                ->whereNull('undone_at')
                ->where(fn ($q) => $q->where('instance_id', $application->instance_id)
                    ->orWhere('event_id', $application->event_id))
                ->exists();
            if ($newer) {
                throw QuestionnaireRoutingException::conflict('A later send must be undone first.');
            }

            $event = Events::query()->lockForUpdate()->find($application->event_id);
            if (!$event) {
                throw QuestionnaireRoutingException::conflict('The event no longer exists.');
            }
            if (!$event->updated_at || !$application->event_updated_at
                || !$event->updated_at->equalTo($application->event_updated_at)
                || ($application->event_fingerprint && $application->event_fingerprint !== $this->fingerprint($event))) {
                throw QuestionnaireRoutingException::conflict('Event was edited since — undo manually');
            }

            $before = $application->before ?? [];
            // Only assign what actually differs; re-encoding an identical
            // additional_data would dirty the row and bump updated_at.
            $restoredData = $before['additional_data'] ?? null;
            if (json_decode(json_encode($event->additional_data), true) !== $restoredData) {
                $event->additional_data = $restoredData;
            }
            if ($event->notes !== ($before['notes'] ?? null)) {
                $event->notes = $before['notes'] ?? null;
            }
            if ($event->venue_name !== ($before['venue_name'] ?? null)) {
                $event->venue_name = $before['venue_name'] ?? null;
            }
            $event->save();
            $event->refresh();

            // The event is now exactly as an earlier, still-applied batch
            // left it; re-arm that batch's undo guard so it can be undone next.
            $fingerprint = $this->fingerprint($event);
            QuestionnaireApplications::query()
                ->where('event_id', $event->id)
                ->where('id', '<', $application->id)
                ->whereNull('undone_at')
                ->where('event_fingerprint', $fingerprint)
                ->update(['event_updated_at' => $event->updated_at]);

            $instance = QuestionnaireInstances::withTrashed()->find($application->instance_id);
            if ($instance) {
                $instance->song_overrides = $before['song_overrides'] ?? null;
                $instance->save();
            }

            // Put each answer back the way this batch found it: an answer an
            // earlier batch had already sent stays applied (and a kept venue
            // stays resolved) rather than reverting to pending.
            $previous = $before['responses'] ?? [];
            QuestionnaireResponses::query()
                ->where('instance_id', $application->instance_id)
                ->whereIn('id', $application->applied_response_ids ?? [])
                ->get()
                ->each(function (QuestionnaireResponses $r) use ($previous) {
                    $prior = $previous[$r->id] ?? $previous[(string) $r->id] ?? [];
                    $r->update([
                        'applied_to_event_at' => $prior['applied_to_event_at'] ?? null,
                        'applied_by_user_id' => $prior['applied_by_user_id'] ?? null,
                        'applied_value_hash' => $prior['applied_value_hash'] ?? null,
                        'routed_target' => array_key_exists('routed_target', $prior) ? $prior['routed_target'] : $r->routed_target,
                    ]);
                });

            $application->update(['undone_at' => now()]);

            return $event->fresh();
        });
    }

    /** Record an answer's applied stamp before this batch overwrites it. */
    private function rememberStamp(array &$before, QuestionnaireResponses $response): void
    {
        if (isset($before['responses'][$response->id])) {
            return;
        }
        $before['responses'][$response->id] = [
            'applied_to_event_at' => $response->applied_to_event_at?->toDateTimeString(),
            'applied_by_user_id' => $response->applied_by_user_id,
            'applied_value_hash' => $response->applied_value_hash,
            'routed_target' => $response->routed_target,
        ];
    }

    /** Hash of the event fields a batch writes. */
    private function fingerprint(Events $event): string
    {
        return hash('sha256', json_encode([
            json_decode(json_encode($event->additional_data), true),
            $event->notes,
            $event->venue_name,
        ]));
    }

    /**
     * updated_at is pinned to the same instant as applied_to_event_at so a
     * save that straddles a second boundary doesn't read back as "stale".
     */
    private function stampApplied(QuestionnaireResponses $response, User $user, \Illuminate\Support\Carbon $now): void
    {
        $response->applied_to_event_at = $now;
        $response->applied_by_user_id = $user->id;
        $response->applied_value_hash = QuestionnaireMappingService::valueHash($response->value);
        $response->updated_at = $now;
        $response->save();
    }

    public function isPlaceholder(?string $display): bool
    {
        return in_array(strtolower(trim((string) $display)), self::PLACEHOLDERS, true);
    }

    private function resolveEventOrFail(QuestionnaireInstances $instance): Events
    {
        try {
            return $this->mappingService->resolveEvent($instance);
        } catch (RuntimeException $e) {
            throw new QuestionnaireRoutingException($e->getMessage());
        }
    }

    /** @return array<int, array{key: string, label: string}> */
    private function compatibleTargets(?string $fieldType): array
    {
        return array_map(
            fn ($key) => ['key' => $key, 'label' => $this->registry->shortLabel($key)],
            $this->registry->compatibleTargetsFor($fieldType),
        );
    }

    private function effectiveTarget(QuestionnaireResponses $response, QuestionnaireInstanceFields $field): ?string
    {
        foreach ([$response->routed_target, $field->mapping_target] as $candidate) {
            if ($candidate && $this->registry->isCompatible($candidate, $field->type)) {
                return $candidate;
            }
        }
        return null;
    }

    /** pending: never sent; applied: sent and unchanged since; stale: answer edited after it was sent. */
    private function status(QuestionnaireResponses $response): string
    {
        if (!$response->applied_to_event_at) {
            return 'pending';
        }
        // The hash of the value that was sent is authoritative: it catches
        // same-second edits, and it survives an undo that restores an earlier
        // stamp (and so bumps updated_at). Rows sent before the hash existed
        // fall back to comparing timestamps.
        if ($response->applied_value_hash !== null) {
            return $response->applied_value_hash === QuestionnaireMappingService::valueHash($response->value)
                ? 'applied'
                : 'stale';
        }
        return $response->updated_at && $response->updated_at->greaterThan($response->applied_to_event_at)
            ? 'stale'
            : 'applied';
    }

    /**
     * "Remember for this questionnaire": future instances of the template
     * get this target. Other already-sent instances are left alone.
     */
    private function rememberOnTemplate(QuestionnaireInstances $instance, QuestionnaireInstanceFields $field, string $target): void
    {
        if (!$field->source_field_id || !$instance->questionnaire_id) {
            return;
        }
        $source = QuestionnaireFields::query()
            ->where('id', $field->source_field_id)
            ->where('questionnaire_id', $instance->questionnaire_id)
            ->first();
        if ($source && $this->registry->isCompatible($target, $source->type)) {
            $source->update(['mapping_target' => $target]);
        }
    }

    private function venueField(Collection $fields): ?QuestionnaireInstanceFields
    {
        return $fields->first(fn ($f) => in_array($f->type, self::TEXT_TYPES, true)
            && str_contains(strtolower($f->label), 'venue name'));
    }

    /**
     * Free-text "Other must-play / do-not-play songs" fields. Shown as text
     * only; the live session's AI parser turns them into requests.
     *
     * @return array{must_play: Collection, do_not_play: Collection}
     */
    private function writeInFields(Collection $fields): array
    {
        $text = $fields->filter(fn ($f) => in_array($f->type, self::TEXT_TYPES, true)
            && str_contains(strtolower($f->label), 'other'));

        return [
            'must_play' => $text->filter(fn ($f) => preg_match('/must[\s-]?play/i', $f->label) === 1)->values(),
            'do_not_play' => $text->filter(fn ($f) => preg_match('/do[\s-]?not[\s-]?play/i', $f->label) === 1)->values(),
        ];
    }

    /**
     * @return array{summary: array, conflicts: array<int, array>}
     */
    private function songRequests(
        QuestionnaireInstances $instance,
        Collection $fields,
        Collection $responses,
        array $writeInFields,
    ): array {
        $must = collect();
        $skip = collect();
        foreach ($fields->where('type', 'song_picker') as $field) {
            $purpose = $field->settings['purpose'] ?? null;
            $decoded = json_decode((string) ($responses->get($field->id)?->value ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            $ids = collect($decoded)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
            if ($purpose === 'must_play') {
                $must = $must->merge($ids);
            } elseif ($purpose === 'do_not_play') {
                $skip = $skip->merge($ids);
            }
        }
        $must = $must->unique()->values();
        $skip = $skip->unique()->values();

        $both = $must->intersect($skip)->values();
        $titles = $both->isEmpty()
            ? collect()
            : Song::query()
                ->where('band_id', $instance->booking->band_id)
                ->whereIn('id', $both)
                ->get(['id', 'title', 'artist'])
                ->keyBy('id');

        $overrides = $instance->song_overrides ?? [];
        $conflicts = $both->map(fn ($id) => [
            'kind' => 'song_both_lists',
            'song_id' => $id,
            'title' => $titles->get($id)?->title ?? "(removed song #{$id})",
            'artist' => $titles->get($id)?->artist ?? '',
            'resolution' => in_array($overrides[(string) $id] ?? null, self::SONG_RESOLUTIONS, true)
                ? $overrides[(string) $id]
                : null,
        ])->all();

        $lines = fn (Collection $fs) => $fs
            ->flatMap(fn ($f) => preg_split('/\r?\n/', (string) ($responses->get($f->id)?->value ?? '')))
            ->map(fn ($line) => trim($line))
            ->reject(fn ($line) => $this->isPlaceholder($line))
            ->values()
            ->all();

        return [
            'summary' => [
                'must_play' => $must->count(),
                'do_not_play' => $skip->count(),
                'write_ins' => $lines($writeInFields['must_play']),
                'do_not_play_write_ins' => $lines($writeInFields['do_not_play']),
            ],
            'conflicts' => $conflicts,
        ];
    }

    /**
     * A mismatch to show, never to auto-fix: case and whitespace are
     * normalized away, but an I/l swap ("Il Mercato" vs "II Mercato") is
     * reported so a human picks the right spelling.
     */
    private function venueConflict(Events $event, ?QuestionnaireInstanceFields $field, ?QuestionnaireResponses $response): ?array
    {
        if (!$field || !$response) {
            return null;
        }
        $bookingValue = trim((string) $event->resolved_venue_name);
        $formValue = $this->mappingService->displayValue($response->value, $field->type);
        if ($this->isPlaceholder($bookingValue) || $this->isPlaceholder($formValue)) {
            return null;
        }

        $normalize = fn (string $s) => preg_replace('/\s+/', ' ', strtolower(trim($s)));
        if ($normalize($bookingValue) === $normalize($formValue)) {
            return null;
        }

        // Kept the booking's spelling in an earlier send (and the answer
        // hasn't changed since): show it as resolved.
        $resolution = $this->status($response) === 'applied' ? 'booking' : null;

        return [
            'kind' => 'venue_mismatch',
            'response_id' => $response->id,
            'booking_value' => $bookingValue,
            'form_value' => $formValue,
            'resolution' => $resolution,
        ];
    }

    /**
     * Contact details the client gave, and whether they're already on the
     * booking (matched by email, or by the last 10 phone digits).
     *
     * @return array<int, array{name: string, email: string, phone: string, on_booking: bool, contact_id: int|null}>
     */
    private function contacts(QuestionnaireInstances $instance, Collection $fields, Collection $responses): array
    {
        $answer = fn ($f) => trim((string) ($responses->get($f->id)?->value ?? ''));
        // Keep empty slots so the Nth email still pairs with the Nth phone.
        $emails = $fields->where('type', 'email')->map($answer)->values();
        $phones = $fields->where('type', 'phone')->map($answer)->values();
        if ($emails->filter()->isEmpty() && $phones->filter()->isEmpty()) {
            return [];
        }

        $nameField = $fields->first(fn ($f) => in_array($f->type, self::TEXT_TYPES, true)
            && str_contains(strtolower($f->label), 'name')
            && !str_contains(strtolower($f->label), 'venue')
            && $answer($f) !== '');
        $fallbackName = $nameField ? $answer($nameField) : '';

        $digits = fn (?string $p) => substr(preg_replace('/\D+/', '', (string) $p), -10);
        $bookingContacts = $instance->booking->contacts;

        $out = [];
        $count = max($emails->count(), $phones->count());
        for ($i = 0; $i < $count; $i++) {
            $email = (string) ($emails->get($i) ?? '');
            $phone = (string) ($phones->get($i) ?? '');
            if ($email === '' && $phone === '') {
                continue;
            }
            $match = $bookingContacts->first(function ($c) use ($email, $phone, $digits) {
                if ($email !== '' && strcasecmp(trim((string) $c->email), $email) === 0) {
                    return true;
                }
                $d = $digits($phone);
                return strlen($d) >= 7 && $digits($c->phone) === $d;
            });

            $out[] = [
                'name' => $match?->name ?? $fallbackName,
                'email' => $email,
                'phone' => $phone,
                'on_booking' => $match !== null,
                'contact_id' => $match?->id,
            ];
        }

        return $out;
    }
}
