<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Startup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'startup_id' => Startup::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => 'active',
            'progress' => 0,
            'deadline' => null,
        ];
    }
}
