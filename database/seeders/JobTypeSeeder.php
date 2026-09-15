<?php

namespace Database\Seeders;

use App\Models\JobType;
use Illuminate\Database\Seeder;

class JobTypeSeeder extends Seeder
{
    public function run(): void
    {
        $jobTypes = [
            'Study/Report'       => 'Conduct of technical evaluation, assessment, or analysis.',
            'Design'             => 'Development of plans, drawings, and/or technical specifications required for a project.',
            'Estimate'           => 'Preparation of cost estimates, bill of quantities, or budgetary computations for a planned project.',
            'Construction'       => 'Work involving the building or creation of new structures, facilities, or civil/architectural components.',
            'Installation'       => 'Placement, set-up, and/or assembly of equipment, systems, or components to make them operational.',
            'Retrofitting'       => 'Structural upgrading or strengthening existing structures or systems to meet new standards and improve safety.',
            'Modification'       => 'Changes or alterations to existing structures, systems, or facilities to improve function or performance.',
            'Demolition/Removal' => 'Dismantling, taking down, or clearing existing structures.',
            'Repair'             => 'Restoration of damaged or deteriorated structures, systems, or components to an acceptable condition.',
            'Other'              => 'Other work or services not covered by the defined job types, to be specified as applicable.',
        ];

        foreach ($jobTypes as $name => $description) {
            JobType::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
