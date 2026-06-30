<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
class RehearsalPlannerAgent implements Agent, Conversational
{
    use Promptable;

    /** @var Message[] */
    private array $priorMessages = [];

    public function withHistory(array $messages): static
    {
        $clone = clone $this;
        $clone->priorMessages = $messages;
        return $clone;
    }

    public function instructions(): string
    {
        return <<<'TXT'
You are a professional band rehearsal planner. Help the band leader decide what to
rehearse, using the supplied context (upcoming events and their requested songs,
recently rehearsed songs, the roster and their instruments, and the song library).

Behaviour:
- If upcoming events have requested/setlist songs, focus there. Call out songs on
  upcoming setlists that do NOT appear in recently rehearsed.
- If nothing is pending, suggest material in TWO clearly separated groups:
  "Revisit from your library" (existing songs, reference them by their [id]) and
  "New repertoire ideas" (real songs NOT in the library that fit the roster/genre).
- Be concise. End each reply by offering a couple of concrete next steps or an open
  question.
- Stay strictly on rehearsal/repertoire planning; politely decline unrelated requests.

When the user asks for a concrete plan, finish your reply with a fenced block:
```plan
{"title":"...","items":[{"song_id":123,"title":"...","reason":"..."},{"song_id":null,"title":"...","reason":"..."}]}
```
Use song_id from the library where applicable; null for new-repertoire ideas.
Also, when helpful, finish with a suggestions block of up to 3 quick replies:
```suggestions
["Draft a plan for the wedding","Explore new material"]
```
TXT;
    }

    public function messages(): iterable
    {
        return $this->priorMessages;
    }
}
