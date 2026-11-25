<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Services\ClientRequestNotesService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Re-reads a booking's questionnaire free text into song requests when the
 * submitted answers changed. Unique per booking until it starts, so a burst
 * of answer saves (dispatched with a delay) runs once on the latest answers.
 */
class ParseClientRequestNotes implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Longer than the AI call's 120s timeout. */
    public int $timeout = 130;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300];

    /** Answers saved within this window are parsed together. */
    public const DEBOUNCE_SECONDS = 30;

    public function __construct(public int $bookingId) {}

    public function uniqueId(): string
    {
        return 'client-notes-' . $this->bookingId;
    }

    public function handle(ClientRequestNotesService $service): void
    {
        $booking = Bookings::find($this->bookingId);
        if ($booking) {
            $service->refresh($booking);
        }
    }

    /**
     * Queues a refresh, after [DEBOUNCE_SECONDS] unless [$now]. Nothing to
     * do without an AI key.
     */
    public static function queueFor(int $bookingId, bool $now = false): void
    {
        if (!config('services.anthropic.key')) {
            return;
        }
        $pending = static::dispatch($bookingId);
        if (!$now) {
            $pending->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        }
    }
}
