<?php

namespace Database\Seeders;

use App\Models\JobLocation;
use Illuminate\Database\Seeder;

class JobLocationSeeder extends Seeder
{
    public function run(): void
    {
        $jobLocations = [
            'Mill'      => 'Mill site, Substation, PGECC',
            'Padigusan' => 'Padigusan site',
            'PHSFI'     => 'Philsaga High School',
            'Mine'      => 'Mine site',
            'Sinug-ang' => 'Sinug-ang site',
            'Other'     => 'Masabong quarry, hauling road, exploration areas, community, etc.',
        ];

        foreach ($jobLocations as $name => $description) {
            JobLocation::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
