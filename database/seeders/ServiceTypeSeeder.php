<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $serviceTypes = [
            'Project'                => 'Project planning and or construction',
            'Repair and Maintenance' => 'Road maintenance, bridge cleaning, gabion maintenance',
            'Study/Report'           => 'Conduct of technical evaluation, assessment, or analysis.',
            'Vehicle / Equipment'    => 'Vehicle and/or heavy equipment rental',
            'Others'                 => null,
        ];

        foreach ($serviceTypes as $name => $description) {
            ServiceType::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
