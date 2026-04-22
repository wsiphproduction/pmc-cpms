<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'         => fake()->sentence(3),
            'job_type'      => fake()->randomElement(['civil', 'electrical', 'mechanical']),
            'description'   => fake()->paragraph(),
            'job_location'  => fake()->city(),
            'costcode'      => fake()->bothify('CC-###'),
            'opex'          => false,
            'capex'         => false,
            'for_budgeting' => false,
            'status'        => 'pending',
            'requester_id'  => User::factory(),
        ];
    }
}