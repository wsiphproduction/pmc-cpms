<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Major Project' => 'Large-scale works, such as new construction, major structural repairs, or significant modifications to existing facilities. These projects require detailed design, extensive planning, multidisciplinary coordination, and may impact operations, the community, or the environment. They often require multiple permits and typically run for months or longer due to their broad scope.',
            'Minor Project' => 'Small-scale works involving non-structural tasks, simple installations, or repairs to existing structures. These projects require minimal planning, often needing only conceptual sketches, and have little to no impact on operations. They are generally completed within a short period, typically days to weeks.',
            'Non-Project'   => 'Activities that do not constitute a distinct project, such as routine or recurring road maintenance, cleaning of bridge, inspections, technical studies or reports, assessments, investigations, and other support activities that do not require full project planning and controls.',
            'Other'         => null,
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
