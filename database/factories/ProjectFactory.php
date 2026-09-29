<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 week');

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'objectives' => fake()->paragraph(),
            'status' => ProjectStatus::Planeacion,
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween((clone $start)->modify('+1 month'), (clone $start)->modify('+4 months')),
            'leader_id' => User::factory(),
            'teacher_id' => null,
            'created_by' => fn (array $attributes) => $attributes['leader_id'],
        ];
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * Registra al líder como integrante, igual que lo hará ProjectService.
     */
    public function withLeaderAsMember(): static
    {
        return $this->afterCreating(fn (Project $project) => $project->members()->attach($project->leader_id));
    }
}
