<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type'       => Conversation::TYPE_BAND,
            'band_id'    => null,
            'unique_key' => 'test:' . Str::uuid(),
        ];
    }
}
