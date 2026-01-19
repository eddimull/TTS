<?php

namespace Database\Factories;

use App\Models\RosterMember;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RosterMemberFactory extends Factory
{
    protected $model = RosterMember::class;

    public function definition(): array
    {
        $isUser = fake()->boolean(70); // 70% chance it's a registered user

        return [
            'roster_id' => Roster::factory(),
            'user_id' => $isUser ? User::factory() : null,
            'name' => $isUser ? null : fake()->name(),
            'email' => $isUser ? null : fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'role' => fake()->optional()->randomElement(['Guitar', 'Bass', 'Drums', 'Vocals', 'Keys', 'Saxophone']),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function user(User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user ? $user->id : User::factory(),
            'name' => null,
            'email' => null,
        ]);
    }

    public function nonUser(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
