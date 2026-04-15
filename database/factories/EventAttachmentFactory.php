<?php

namespace Database\Factories;

use App\Models\EventAttachment;
use App\Models\Events;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventAttachmentFactory extends Factory
{
    protected $model = EventAttachment::class;

    public function definition(): array
    {
        return [
            'event_id' => Events::factory(),
            'filename' => $this->faker->word . '.pdf',
            'stored_filename' => 'test-band/event_uploads/' . $this->faker->uuid . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024),
            'disk' => 's3',
        ];
    }
}
