<?php

namespace Database\Factories;

use App\Models\ProjectRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'filename'       => fake()->word() . '.pdf',
            'filepath'       => 'requests/1/drawings/file.pdf',
            'reference_id'   => ProjectRequest::factory(),
            'reference_type' => ProjectRequest::class,
            'description'    => fake()->sentence(),
        ];
    }
}