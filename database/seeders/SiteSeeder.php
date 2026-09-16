<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            'Mill'      => 'Mill site, Substation, PGECC',
            'Padigusan' => 'Padigusan site',
            'PHSFI'     => 'Philsaga High School',
            'Mine'      => 'Mine site',
            'Sinug-ang' => 'Sinug-ang site',
            'Other'     => 'Masabong quarry, hauling road, exploration areas, community, etc.',
        ];

        foreach ($sites as $name => $description) {
            Site::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
