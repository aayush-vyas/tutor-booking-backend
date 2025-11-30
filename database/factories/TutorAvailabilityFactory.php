<?php

namespace Database\Factories;

use App\Models\TutorAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TutorAvailability>
 */
class TutorAvailabilityFactory extends Factory
{
    protected $model = TutorAvailability::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $endTime = (clone $startTime)->modify('+'.rand(1, 8).' hours');

        return [
            'tutor_id' => User::factory()->create(['role' => 'tutor'])->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => $this->faker->boolean(80),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the availability is for a specific tutor.
     */
    public function forTutor(User $tutor): static
    {
        return $this->state(fn (array $attributes) => [
            'tutor_id' => $tutor->id,
        ]);
    }

    /**
     * Indicate that the slot is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
        ]);
    }

    /**
     * Indicate that the slot is blocked/unavailable.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
