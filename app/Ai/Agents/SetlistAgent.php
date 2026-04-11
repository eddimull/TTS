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
#[Model('claude-opus-4-6')]
class SetlistAgent implements Agent, Conversational
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
        return 'You are a professional band manager building setlists and managing live performance logistics.';
    }

    public function messages(): iterable
    {
        return $this->priorMessages;
    }
}
