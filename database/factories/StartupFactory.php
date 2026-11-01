<?php

namespace Database\Factories;

use App\Models\Founder;
use App\Models\Startup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Startup>
 */
class StartupFactory extends Factory
{
    protected $model = Startup::class;

    public function definition(): array
    {
        return [
            'founder_id' => Founder::factory(),
            'name' => fake()->company(),
            'description' => null,
            'problem' => null,
            'solution' => null,
            'target_customers' => null,
            'industry' => fake()->word(),
            'stage' => 'idea',
            'current_challenges' => null,
        ];
    }
}
