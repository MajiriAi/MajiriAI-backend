<?php

namespace Database\Factories;

use App\Models\Startup;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'startup_id' => Startup::factory(),
            'goal_id' => null,
            'title' => fake()->sentence(4),
            'description' => null,
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => null,
        ];
    }
}
