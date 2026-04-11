<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.0-flash')]
class VisionAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a vision analysis assistant that classifies and extracts structured information from images.';
    }
}
