<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\Startup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'startup_id' => Startup::factory(),
            'content' => fake()->sentence(),
            'category' => 'insight',
            'importance' => 5,
            'source' => 'conversation',
            'confidence' => 'explicit',
            'key' => null,
            'superseded' => false,
        ];
    }
}
