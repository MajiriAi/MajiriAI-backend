<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Startup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'startup_id' => Startup::factory(),
            'title' => fake()->sentence(4),
            'description' => null,
            'type' => 'note',
            'occurred_at' => now(),
        ];
    }
}
