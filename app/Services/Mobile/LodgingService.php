<?php

namespace App\Services\Mobile;

use App\Models\Lodging;
use App\Models\LodgingAttachment;

/**
 * Formats lodging records for the mobile wire contract and owns the nested
 * room-set sync semantics shared by store/update.
 *
 * Wire datetimes are always `Y-m-d H:i:s` strings (never ISO-8601) so the
 * Flutter models can parse them with a single format.
 */
class LodgingService
{
    /**
     * List-row shape: no nested rooms/attachments, just their counts.
     *
     * Callers should eager-load counts with `withCount(['rooms', 'attachments'])`
     * to avoid an N+1 — the `?? $lodging->rooms()->count()` fallbacks exist only
     * for callers that hand over a bare model.
     */
    public function formatSummary(Lodging $lodging): array
    {
        return [
            'id'               => $lodging->id,
            'name'             => $lodging->name,
            'address'          => $lodging->address,
            'check_in_at'      => $lodging->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at'     => $lodging->check_out_at?->format('Y-m-d H:i:s'),
            'room_count'       => $lodging->rooms_count ?? $lodging->rooms()->count(),
            'attachment_count' => $lodging->attachments_count ?? $lodging->attachments()->count(),
            'booking_id'       => $lodging->booking_id,
            'event_id'         => $lodging->event_id,
        ];
    }

    public function formatDetail(Lodging $lodging): array
    {
        $lodging->loadMissing(['rooms', 'attachments', 'booking:id,name', 'event:id,title,date']);

        return [
            'id'           => $lodging->id,
            'name'         => $lodging->name,
            'address'      => $lodging->address,
            'latitude'     => $lodging->latitude,
            'longitude'    => $lodging->longitude,
            'check_in_at'  => $lodging->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $lodging->check_out_at?->format('Y-m-d H:i:s'),
            'notes'        => $lodging->notes,
            'booking'      => $lodging->booking ? [
                'id'   => $lodging->booking->id,
                'name' => $lodging->booking->name,
            ] : null,
            'event'        => $lodging->event ? [
                'id'    => $lodging->event->id,
                'title' => $lodging->event->title,
                // Events::$date is cast `date:Y-m-d`, i.e. a Carbon instance —
                // format it so the wire value stays a bare date string.
                'date'  => $lodging->event->date?->format('Y-m-d'),
            ] : null,
            'rooms'        => $lodging->rooms->map(fn ($r) => [
                'id'                  => $r->id,
                'label'               => $r->label,
                'confirmation_number' => $r->confirmation_number,
                'notes'               => $r->notes,
                'sort_order'          => $r->sort_order,
            ])->values()->toArray(),
            'attachments'  => $lodging->attachments->map(fn ($a) => $this->formatAttachment($a))->values()->toArray(),
        ];
    }

    public function formatAttachment(LodgingAttachment $attachment): array
    {
        return [
            'id'        => $attachment->id,
            'filename'  => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            // Sanctum-authenticated serve route (Task 3) — NOT the public /images/ proxy.
            'url'       => url('/api/mobile/lodging-attachments/' . $attachment->id),
        ];
    }

    /**
     * Sync the rooms set: items with id update, items without id insert,
     * db rows whose ids are absent from the payload are deleted.
     *
     * `sort_order` is always rewritten from the payload's array position, so
     * the client controls ordering purely by the order it sends rooms in.
     */
    public function syncRooms(Lodging $lodging, array $rooms): void
    {
        $keptIds = [];
        foreach (array_values($rooms) as $i => $room) {
            $attributes = [
                'label'               => $room['label'],
                'confirmation_number' => $room['confirmation_number'] ?? null,
                'notes'               => $room['notes'] ?? null,
                'sort_order'          => $i,
            ];
            if (!empty($room['id'])) {
                $existing = $lodging->rooms()->find($room['id']);
                if ($existing) {
                    $existing->update($attributes);
                    $keptIds[] = $existing->id;
                    continue;
                }
            }
            $keptIds[] = $lodging->rooms()->create($attributes)->id;
        }
        $lodging->rooms()->whereNotIn('id', $keptIds)->delete();
    }
}
