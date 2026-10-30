<?php

return [

    /*
    |---------------------------------------------------------------------------
    | EMS Survey Report — Environmental Impact Assessment (PMD-PRJ-FRM-19)
    |---------------------------------------------------------------------------
    |
    | The checklists on the controlled form, keyed by what is stored on the
    | report. The MEPEO page, the hub view and the printed form all read these,
    | so adding an item here flows through to all three. Each list also has an
    | "Others" line, held as free text on the report rather than as a key here.
    |
    */

    'major_activities' => [
        'construction' => 'Construction of new structure/s',
        'renovation'   => 'Renovation or modification of existing structure',
        'repair'       => 'Repair or rehabilitation of existing structure',
        'demolition'   => 'Demolition of structure',
    ],

    'aspects' => [
        'vegetation_removal'     => 'Removal of forest cover or vegetation',
        'topography_change'      => 'Change in topography',
        'transport_vehicle'      => 'Use of transportation vehicle',
        'heavy_equipment'        => 'Use of heavy equipment',
        'natural_resources'      => 'Use of resources (rocks, gravel, sand, etc.)',
        'manufactured_materials' => 'Use of manufactured construction materials',
        'water'                  => 'Use of water',
        'electricity'            => 'Use of Electricity',
        'solid_waste'            => 'Generation of solid waste materials',
        'hazardous_waste'        => 'Generation of hazardous waste materials',
        'dust'                   => 'Generation of dust',
        'construction_tools'     => 'Use of construction tools',
        'solid_waste_reuse'      => 'Reuse of construction solid waste materials',
        'slope_stabilization'    => 'Installation of slope stabilization',
    ],

    // MEPEO's finding, the "Status" block of the form.
    'statuses' => [
        'proceed'         => 'Notice To Proceed with the activity (no need for an Environmental Survey Report)',
        'survey_required' => 'Environmental Survey Report is needed prior to commencing the activity',
    ],

];
