<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        $keys = ['A', 'A#', 'Bb', 'B', 'C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab'];
        $modes = ['maj', 'min'];
        $genres = ['Rock', 'Pop', 'Jazz', 'Blues', 'Country', 'R&B', 'Soul', 'Funk', 'Latin', 'Hip Hop'];

        return [
            'band_id' => Bands::factory(),
            'title' => fake()->words(fake()->numberBetween(1, 4), true),
            'artist' => fake()->optional(0.8)->name(),
            'song_key' => fake()->optional(0.8)->randomElement($keys) . ' ' . fake()->randomElement($modes),
            'genre' => fake()->optional(0.7)->randomElement($genres),
            'bpm' => fake()->optional(0.7)->numberBetween(60, 180),
            'notes' => fake()->optional(0.3)->sentence(),
            'lead_singer_id' => null,
            'transition_song_id' => null,
            'active' => fake()->boolean(85),
        ];
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function forBand(Bands $band): static
    {
        return $this->state(['band_id' => $band->id]);
    }
}
