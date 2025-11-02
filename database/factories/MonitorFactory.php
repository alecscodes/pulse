<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['website', 'ip']),
            'url' => fake()->url(),
            'method' => fake()->randomElement(['GET', 'POST']),
            'headers' => [],
            'parameters' => [],
            'enable_content_validation' => false,
            'expected_title' => null,
            'expected_content' => null,
            'is_active' => true,
            'check_interval' => 60,
        ];
    }
}
