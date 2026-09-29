<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los estados generan combinaciones coherentes de status / progress / fechas (docs/03, §4).
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assigned_to' => null,
            'created_by' => fn (array $attributes) => Project::find($attributes['project_id'])->leader_id,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(12),
            'status' => TaskStatus::Pendiente,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'progress' => 0,
            'start_date' => now()->subDays(3),
            'due_date' => now()->addDays(fake()->numberBetween(3, 30)),
        ];
    }

    public function inProgress(int $progress = 50): static
    {
        return $this->state(fn () => ['status' => TaskStatus::EnProgreso, 'progress' => $progress]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Completada,
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Vencida,
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(2),
        ]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn () => ['due_date' => now()->addDays($days)]);
    }
}
