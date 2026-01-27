<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChunkedUpload>
 */
class ChunkedUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upload_id' => fake()->uuid(),
            'user_id' => \App\Models\User::factory(),
            'filename' => fake()->word() . '.mp4',
            'filesize' => fake()->numberBetween(100000000, 500000000), // 100MB - 500MB
            'mime_type' => fake()->randomElement(['video/mp4', 'video/quicktime', 'audio/mpeg']),
            'total_chunks' => fake()->numberBetween(50, 250),
            'chunks_uploaded' => 0,
            'status' => 'initiated',
            'media_id' => null,
            'last_chunk_at' => null,
        ];
    }

    /**
     * Indicate that the upload is in progress.
     */
    public function uploading(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'uploading',
            'chunks_uploaded' => fake()->numberBetween(1, $attributes['total_chunks'] - 1),
            'last_chunk_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the upload is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'chunks_uploaded' => $attributes['total_chunks'],
            'last_chunk_at' => now()->subMinutes(fake()->numberBetween(1, 10)),
            'media_id' => \App\Models\MediaFile::factory(),
        ]);
    }

    /**
     * Indicate that the upload has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'last_chunk_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
