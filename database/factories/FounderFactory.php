<?php

namespace Database\Factories;

use App\Models\Founder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Founder>
 */
class FounderFactory extends Factory
{
    protected $model = Founder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'background' => null,
            'skills' => null,
            'experience' => null,
            'interests' => null,
            'personal_goals' => null,
            'resources' => null,
        ];
    }
}
