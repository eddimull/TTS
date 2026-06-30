<?php

namespace App\Services;

use App\Ai\Agents\RehearsalPlannerAgent;
use App\Events\RehearsalPlannerStreamEvent;
use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\Message;

class RehearsalPlannerService
{
    public function __construct(
        private RehearsalPlannerContextBuilder $contextBuilder,
    ) {}

    public function runTurn(
        RehearsalPlannerSession $session,
        RehearsalPlannerMessage $assistantMessage,
        ?string $userText,
    ): void {
        try {
            $session->loadMissing('band');
            $context = $this->contextBuilder->build($session->band);

            $history = $this->historyForTurn($session, $assistantMessage, $userText);

            // The opening turn has no userText; prompt the agent to assess the context.
            $prompt = $userText !== null && $userText !== ''
                ? $userText
                : 'Assess what the band should rehearse and open the conversation.';

            // Prepend the context as a system-style preamble on the first user turn.
            $promptWithContext = "BAND CONTEXT:\n{$context['text']}\n\n---\n{$prompt}";

            $agent = (new RehearsalPlannerAgent())->withHistory($history);

            $full = '';
            $stream = $agent->stream($promptWithContext, timeout: 120);
            foreach ($stream as $event) {
                $arr = method_exists($event, 'toArray') ? $event->toArray() : (array) $event;
                if (($arr['type'] ?? null) === 'text_delta' && isset($arr['delta'])) {
                    $full .= $arr['delta'];
                    RehearsalPlannerStreamEvent::dispatch($session->id, 'text_delta', ['delta' => $arr['delta']]);
                }
            }

            $plan        = self::parsePlan($full);
            $suggestions = self::parseSuggestions($full);
            $visible     = self::stripBlocks($full);

            $assistantMessage->update([
                'content' => $visible,
                'payload' => ['suggestions' => $suggestions, 'plan' => $plan],
                'status'  => 'complete',
            ]);

            RehearsalPlannerStreamEvent::dispatch($session->id, 'done', [
                'message_id'  => $assistantMessage->id,
                'content'     => $visible,
                'suggestions' => $suggestions,
                'plan'        => $plan,
            ]);
        } catch (\Throwable $e) {
            Log::error('RehearsalPlannerService failed', ['error' => $e->getMessage()]);
            $assistantMessage->update(['status' => 'failed']);
            RehearsalPlannerStreamEvent::dispatch($session->id, 'error', [
                'message_id' => $assistantMessage->id,
                'error'      => 'The planner failed to respond. Please retry.',
            ]);
        }
    }

    /**
     * Build the replayed history for a turn while keeping it disjoint from the
     * current prompt. History must contain only PRIOR complete turns; the
     * current user turn (when present) is carried solely by the prompt, so it
     * is excluded here to avoid the agent seeing it twice.
     *
     * The user row is created immediately before the assistant placeholder, so
     * for a message() turn the current turn's earliest row is the most recent
     * user message below the assistant placeholder; history must stop strictly
     * before it. For the opening start() turn there is no user text, so we
     * simply exclude the assistant placeholder itself.
     *
     * Made protected so the disjointness invariant is unit-testable via a thin
     * subclass seam.
     *
     * @return Message[]
     */
    protected function historyForTurn(
        RehearsalPlannerSession $session,
        RehearsalPlannerMessage $assistantMessage,
        ?string $userText,
    ): array {
        $boundaryId = $assistantMessage->id;

        if ($userText !== null && $userText !== '') {
            $currentUserId = $session->messages()
                ->where('role', 'user')
                ->where('id', '<', $assistantMessage->id)
                ->max('id');
            if ($currentUserId !== null) {
                $boundaryId = $currentUserId;
            }
        }

        return $this->buildHistory($session, $boundaryId);
    }

    /**
     * Replay only complete turns strictly before $excludeMessageId.
     *
     * @return Message[]
     */
    protected function buildHistory(RehearsalPlannerSession $session, int $excludeMessageId): array
    {
        return $session->messages()
            ->where('id', '<', $excludeMessageId)
            ->where('status', 'complete')
            ->get()
            ->map(fn (RehearsalPlannerMessage $m) => new Message($m->role, (string) $m->content))
            ->all();
    }

    public static function parsePlan(string $text): ?array
    {
        // Anchor on the fences (brace-count-agnostic) so nested objects in a
        // multi-item plan are captured whole, not truncated at the first `}`.
        if (!preg_match('/```plan\s*(.*?)```/s', $text, $m)) {
            return null;
        }
        $decoded = json_decode(trim($m[1]), true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<int,string> */
    public static function parseSuggestions(string $text): array
    {
        if (!preg_match('/```suggestions\s*(.*?)```/s', $text, $m)) {
            return [];
        }
        $decoded = json_decode(trim($m[1]), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public static function stripBlocks(string $text): string
    {
        $text = preg_replace('/```plan\s*.*?```/s', '', $text);
        $text = preg_replace('/```suggestions\s*.*?```/s', '', $text);
        return trim($text);
    }
}
