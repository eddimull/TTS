<?php

namespace Database\Factories;

use App\Models\MediaFile;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    public function definition()
    {
        return [
            'band_id' => Bands::factory(),
            'user_id' => User::factory(),
            'filename' => $this->faker->word() . '.jpg',
            'stored_filename' => 'test-band/media/' . $this->faker->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(1000, 5000000),
            'disk' => 's3',
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'media_type' => 'image',
        ];
    }

    public function image()
    {
        return $this->state(function (array $attributes) {
            return [
                'media_type' => 'image',
                'mime_type' => 'image/jpeg',
            ];
        });
    }

    public function video()
    {
        return $this->state(function (array $attributes) {
            return [
                'media_type' => 'video',
                'mime_type' => 'video/mp4',
                'filename' => $this->faker->word() . '.mp4',
            ];
        });
    }

    public function audio()
    {
        return $this->state(function (array $attributes) {
            return [
                'media_type' => 'audio',
                'mime_type' => 'audio/mpeg',
                'filename' => $this->faker->word() . '.mp3',
            ];
        });
    }

    public function document()
    {
        return $this->state(function (array $attributes) {
            return [
                'media_type' => 'document',
                'mime_type' => 'application/pdf',
                'filename' => $this->faker->word() . '.pdf',
            ];
        });
    }
}
