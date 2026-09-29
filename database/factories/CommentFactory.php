<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => null,
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_observation' => false,
        ];
    }

    public function observation(): static
    {
        return $this->state(fn () => ['is_observation' => true]);
    }
}
