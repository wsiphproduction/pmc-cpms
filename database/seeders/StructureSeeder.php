<?php

namespace Database\Seeders;

use App\Models\Structure;
use Illuminate\Database\Seeder;

class StructureSeeder extends Seeder
{
    public function run(): void
    {
        // The source sheet has a separate "typical example" column; the table
        // only has a description, so the examples are folded in after "e.g.".
        $structures = [
            'Building'                    => 'Buildings and facility-related structures, including enclosed spaces and their major components. e.g. Offices, staffhouses, workshops, guard posts, sheds, substations, storage facilities',
            'Structural Support / Platform' => 'Structural elements primarily intended to support equipment, facilities, or access. e.g. Platforms, equipment foundations, pads, pedestals, catwalks, stairways',
            'Fence / Barrier'             => 'Perimeter, security, access-control, and protective barriers. e.g. Fences, gates, railings, safety barriers',
            'Drainage / hydraulic'        => 'Structures for drainage, conveyance, collection, diversion, or control of water. e.g. Canals, culverts, storm drains, sumps, catch basins, spillways, channels',
            'Bridge'                      => 'Structures that provide passage across waterways, depressions, drainage paths, or other obstacles. e.g. Bridges, bridge piers, crossing structures',
            'Slope / Earth Retaining'     => 'Works for slope stabilization, erosion protection, or earth retention. e.g. Gabions, riprap, retaining walls, slope stabilization works',
            'Road'                        => 'Roads and access roads. e.g. Hauling roads, internal roads, driveways, ramps, parking areas, open laydown areas',
            'Site Development'            => 'Development or preparation of an area. e.g. Grading, backfilling, site preparation, open-area development',
            'Embankment Dam / Impoundment' => 'Dams, embankments, dikes, ponds, and other impounding structures. e.g. TSFs, settling ponds, polishing ponds, water dams, diversion dams',
            'Other'                       => null,
        ];

        foreach ($structures as $name => $description) {
            Structure::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
