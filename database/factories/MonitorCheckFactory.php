<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonitorCheck>
 */
class MonitorCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'status' => fake()->randomElement(['up', 'down']),
            'response_time' => fake()->numberBetween(50, 500),
            'status_code' => fake()->randomElement([200, 404, 500]),
            'response_body' => fake()->optional()->text(500),
            'error_message' => null,
            'content_valid' => null,
            'checked_at' => now(),
        ];
    }

    public function up(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'up',
            'status_code' => 200,
            'response_time' => fake()->numberBetween(50, 300),
            'error_message' => null,
        ]);
    }

    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'down',
            'status_code' => fake()->randomElement([404, 500, null]),
            'error_message' => fake()->sentence(),
            'response_time' => null,
        ]);
    }
}
