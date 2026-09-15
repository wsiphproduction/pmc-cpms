<?php

namespace Database\Seeders;

use App\Models\Priority;
use Illuminate\Database\Seeder;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        // sequence_no orders the list most urgent first.
        $priorities = [
            ['Critical', 1, 'Requires immediate action to prevent any serious disruption to operations, safety hazards, financial loss, or damage to assets.'],
            ['High',     2, 'Does not pose an immediate risk to safety, operations, or financial loss but may escalate into operational or financial impacts if left unattended. These tasks should be acted upon within a week.'],
            ['Medium',   3, 'Necessary tasks that are not time-sensitive and can be scheduled within a month.'],
            ['Low',      4, 'Minor defects or non-urgent tasks that do not affect operations or safety and may be addressed at a convenient time.'],
        ];

        foreach ($priorities as [$name, $sequenceNo, $description]) {
            Priority::firstOrCreate(
                ['name' => $name],
                ['sequence_no' => $sequenceNo, 'description' => $description, 'is_active' => true]
            );
        }
    }
}
