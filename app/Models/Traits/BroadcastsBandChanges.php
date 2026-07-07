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
