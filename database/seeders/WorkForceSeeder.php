<?php

namespace Database\Seeders;

use App\Models\WorkForce;
use Illuminate\Database\Seeder;

class WorkForceSeeder extends Seeder
{
    public function run(): void
    {
        $workForces = [
            'PMD - Construction Section'         => 'PMD personnel',
            'Austral Construction Services'      => 'Outsource contractor',
            'BJ Construction Services'           => 'Outsource contractor',
            'Primo Konstruk Builders and Supply' => 'Outsource contractor',
            'Optijobs Manpower Agency'           => 'Outsource contractor',
        ];

        foreach ($workForces as $name => $description) {
            WorkForce::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
