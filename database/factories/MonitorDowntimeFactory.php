<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonitorDowntime>
 */
class MonitorDowntimeFactory extends Factory
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
            'started_at' => now(),
            'ended_at' => null,
            'duration_seconds' => null,
            'last_notification_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? now()->subMinutes(30);
            $endedAt = $startedAt->copy()->addMinutes(30);

            return [
                'ended_at' => $endedAt,
                'duration_seconds' => $startedAt->diffInSeconds($endedAt),
            ];
        });
    }
}
