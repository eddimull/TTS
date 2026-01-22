<?php

namespace Database\Factories;

use App\Models\EventMember;
use App\Models\Events;
use App\Models\Bands;
use App\Models\User;
use App\Models\RosterMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMemberFactory extends Factory
{
    protected $model = EventMember::class;

    public function definition(): array
    {
        return [
            'event_id' => Events::factory(),
            'band_id' => Bands::factory(),
            'user_id' => null,
            'roster_member_id' => RosterMember::factory(),
            'name' => null,
            'email' => null,
            'phone' => null,
            'attendance_status' => 'attended',
            'payout_amount' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => 'attended',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => 'absent',
        ]);
    }

    public function excused(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => 'excused',
        ]);
    }

    public function withCustomPayout(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_amount' => (int) ($amount * 100), // Convert to cents
        ]);
    }
}
