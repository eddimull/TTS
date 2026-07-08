<?php

namespace App\Models\Traits;

use App\Events\BandDataChanged;
use Illuminate\Support\Str;

/**
 * Opt-in realtime signal: any created/updated/deleted on the model dispatches
 * a thin BandDataChanged broadcast to the model's band channel.
 *
 * Models whose band is reached indirectly override broadcastBandId(); child
 * models whose client-side listing is keyed by a parent (comments, event
 * members) override broadcastParent().
 *
 * Wire model name is Str::snake(class_basename()) — keep the mobile registry
 * in lib/shared/providers/band_realtime_provider.dart in sync when adding
 * models.
 */
trait BroadcastsBandChanges
{
    public static function bootBroadcastsBandChanges(): void
    {
        static::created(fn ($model) => $model->broadcastBandChange('created'));
        static::updated(fn ($model) => $model->broadcastBandChange('updated'));
        static::deleted(fn ($model) => $model->broadcastBandChange('deleted'));
    }

    protected function broadcastBandChange(string $action): void
    {
        try {
            if ($action === 'updated' && ! $this->broadcastHasMeaningfulChanges()) {
                return;
            }

            $bandId = $this->broadcastBandId();
            if (! $bandId) {
                return;
            }

            BandDataChanged::dispatch(
                (int) $bandId,
                Str::snake(class_basename($this)),
                (int) $this->getKey(),
                $action,
                $this->broadcastParent(),
            );
        } catch (\Throwable $e) {
            // A realtime signal must never break the write that caused it.
            report($e);
        }
    }

    /**
     * An `updated` signal is only worth broadcasting when something beyond
     * timestamps and the model's declared derived-cache attributes changed.
     *
     * This is the loop-breaker for read paths with cache-write side effects
     * (e.g. a GET that recomputes and stores a calculation): without it,
     * signal -> client partial reload -> controller re-saves cache -> signal
     * cycles forever.
     */
    protected function broadcastHasMeaningfulChanges(): bool
    {
        $ignored = array_merge($this->broadcastIgnoreDirty(), [
            $this->getUpdatedAtColumn() ?? 'updated_at',
            $this->getCreatedAtColumn() ?? 'created_at',
        ]);

        return array_diff(array_keys($this->getChanges()), $ignored) !== [];
    }

    /**
     * Attributes whose changes never broadcast (derived caches rewritten on
     * read paths). Override per model.
     *
     * @return list<string>
     */
    protected function broadcastIgnoreDirty(): array
    {
        return [];
    }

    protected function broadcastBandId(): ?int
    {
        return isset($this->band_id) ? (int) $this->band_id : null;
    }

    /**
     * @return array{model: string, id: int}|null
     */
    protected function broadcastParent(): ?array
    {
        return null;
    }
}
