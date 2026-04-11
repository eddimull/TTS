<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-opus-4-6')]
class SetlistAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a professional band manager building setlists and managing live performance logistics.';
    }
}
