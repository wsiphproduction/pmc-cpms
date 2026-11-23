<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Operations Hub documents "with Electronic approver process"
|--------------------------------------------------------------------------
|
| Each type is one hub item. The engineer prepares it ("Prepared by") and
| submits it; the roles in `chain` then sign in order, and any of them may
| return it for revision. `verbs` says what each signature means on the
| record.
|
| `fields` is the form. Field types:
|   text, textarea, date, number, money, percent      — single values
|   select     (options)                              — one of a list
|   checklist  (items)                                — Yes / No / N/A each, with a remark
|   table      (columns)                              — repeating rows; a `risk` column
|                                                        is likelihood × severity
|   billing                                           — one of the project's billings
|
| A type with `source => completion` has no form of its own: it signs off
| the project's completion details (Project Completion and Acceptance
| Certificate, Project Completion Summary).
|
| The Safety, Geological and Billing Validation forms below are a standard
| starting point, pending PMC's own forms — adjust fields and chains here.
|
*/

$likelihood = ['1' => '1 – Rare', '2' => '2 – Unlikely', '3' => '3 – Possible', '4' => '4 – Likely', '5' => '5 – Almost certain'];
$severity   = ['1' => '1 – Insignificant', '2' => '2 – Minor', '3' => '3 – Moderate', '4' => '4 – Major', '5' => '5 – Catastrophic'];

return [

    'types' => [

        'safety-risk' => [
            'label'  => 'Safety Risk Assessment',
            'prefix' => 'SRA',
            'chain'  => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER],
            'verbs'  => [User::ROLE_PMD_ASST_MANAGER => 'reviewed', User::ROLE_PMD_DEPT_MANAGER => 'approved'],
            'title_field' => 'work_activity',
            'fields' => [
                ['key' => 'work_activity',   'label' => 'Work Activity / Scope Assessed', 'type' => 'text', 'required' => true],
                ['key' => 'work_location',   'label' => 'Work Location',                  'type' => 'text', 'required' => true],
                ['key' => 'assessment_date', 'label' => 'Date of Assessment',             'type' => 'date', 'required' => true],
                ['key' => 'contractor',      'label' => 'Contractor',                     'type' => 'text'],
                ['key' => 'hazards', 'label' => 'Hazard Identification and Risk Assessment', 'type' => 'table', 'required' => true,
                    'help' => 'Risk = likelihood × severity: 1–4 Low, 5–9 Medium, 10–16 High, 20–25 Extreme. Re-rate after controls for the residual risk.',
                    'columns' => [
                        ['key' => 'task',       'label' => 'Task / Step',            'type' => 'text', 'required' => true],
                        ['key' => 'hazard',     'label' => 'Hazard',                 'type' => 'text', 'required' => true],
                        ['key' => 'consequence', 'label' => 'Possible Consequence',  'type' => 'text'],
                        ['key' => 'likelihood', 'label' => 'Likelihood',             'type' => 'select', 'options' => $likelihood, 'required' => true],
                        ['key' => 'severity',   'label' => 'Severity',               'type' => 'select', 'options' => $severity, 'required' => true],
                        ['key' => 'risk',       'label' => 'Risk',                   'type' => 'risk', 'of' => ['likelihood', 'severity']],
                        ['key' => 'controls',   'label' => 'Control Measures',       'type' => 'textarea', 'required' => true],
                        ['key' => 'residual_likelihood', 'label' => 'Residual Likelihood', 'type' => 'select', 'options' => $likelihood],
                        ['key' => 'residual_severity',   'label' => 'Residual Severity',   'type' => 'select', 'options' => $severity],
                        ['key' => 'residual_risk',       'label' => 'Residual Risk',       'type' => 'risk', 'of' => ['residual_likelihood', 'residual_severity']],
                        ['key' => 'responsible', 'label' => 'Responsible',           'type' => 'text'],
                    ],
                ],
                ['key' => 'ppe',         'label' => 'Required PPE',                        'type' => 'textarea'],
                ['key' => 'permits',     'label' => 'Work Permits Required',               'type' => 'textarea'],
                ['key' => 'emergency',   'label' => 'Emergency Response Measures',         'type' => 'textarea'],
                ['key' => 'remarks',     'label' => 'Remarks',                             'type' => 'textarea'],
            ],
        ],

        'geological' => [
            'label'  => 'Geological Assessment',
            'prefix' => 'GEO',
            'chain'  => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER],
            'verbs'  => [User::ROLE_PMD_ASST_MANAGER => 'reviewed', User::ROLE_PMD_DEPT_MANAGER => 'approved'],
            'title_field' => 'site_location',
            'fields' => [
                ['key' => 'site_location',   'label' => 'Site / Location Assessed', 'type' => 'text', 'required' => true],
                ['key' => 'assessment_date', 'label' => 'Date of Assessment',       'type' => 'date', 'required' => true],
                ['key' => 'assessment_type', 'label' => 'Type of Assessment',       'type' => 'select', 'required' => true, 'options' => [
                    'desk'           => 'Desk study',
                    'reconnaissance' => 'Site reconnaissance',
                    'geotechnical'   => 'Geotechnical investigation (boreholes / test pits)',
                    'slope'          => 'Slope stability assessment',
                    'other'          => 'Other',
                ]],
                ['key' => 'assessed_by',     'label' => 'Assessed By (consultant / geologist)', 'type' => 'text'],
                ['key' => 'soil_rock',       'label' => 'Soil and Rock Conditions',  'type' => 'textarea', 'required' => true],
                ['key' => 'groundwater',     'label' => 'Groundwater',               'type' => 'text', 'help' => 'Not encountered, or the depth it was found at.'],
                ['key' => 'bearing_capacity', 'label' => 'Allowable Bearing Capacity', 'type' => 'text'],
                ['key' => 'slope_stability', 'label' => 'Slope Stability',           'type' => 'select', 'options' => [
                    'stable' => 'Stable', 'marginal' => 'Marginal', 'unstable' => 'Unstable', 'na' => 'Not applicable',
                ]],
                ['key' => 'geohazards', 'label' => 'Geohazards Identified', 'type' => 'checklist', 'items' => [
                    'landslide'    => 'Landslide / slope failure',
                    'subsidence'   => 'Ground subsidence / settlement',
                    'sinkhole'     => 'Sinkhole / voids (incl. old workings)',
                    'flooding'     => 'Flooding / poor drainage',
                    'liquefaction' => 'Liquefaction',
                    'fault'        => 'Active fault proximity',
                    'erosion'      => 'Erosion',
                ]],
                ['key' => 'findings',        'label' => 'Findings',                  'type' => 'textarea', 'required' => true],
                ['key' => 'recommendations', 'label' => 'Recommendations (incl. foundation / ground treatment)', 'type' => 'textarea', 'required' => true],
            ],
        ],

        'billing-validation' => [
            'label'  => 'Billing Validation Report',
            'prefix' => 'BVR',
            'chain'  => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER],
            'verbs'  => [User::ROLE_PMD_ASST_MANAGER => 'reviewed', User::ROLE_PMD_DEPT_MANAGER => 'approved'],
            'title_field' => 'billing',
            'fields' => [
                ['key' => 'billing',           'label' => 'Billing Validated',              'type' => 'billing', 'required' => true],
                ['key' => 'validation_date',   'label' => 'Date of Validation',             'type' => 'date', 'required' => true],
                ['key' => 'claimed_progress',  'label' => 'Claimed Accomplishment (%)',     'type' => 'percent', 'required' => true],
                ['key' => 'verified_progress', 'label' => 'Verified Accomplishment (%)',    'type' => 'percent', 'required' => true],
                ['key' => 'claimed_amount',    'label' => 'Amount Billed (PhP)',            'type' => 'money', 'required' => true],
                ['key' => 'deductions',        'label' => 'Deductions — retention, penalties, previous billings (PhP)', 'type' => 'money'],
                ['key' => 'validated_amount',  'label' => 'Validated Amount Payable (PhP)', 'type' => 'money', 'required' => true],
                ['key' => 'checks', 'label' => 'Validation Checklist', 'type' => 'checklist', 'required' => true, 'items' => [
                    'site_verified'    => 'Accomplishment verified on site',
                    'quantities'       => 'Quantities agree with the approved BOQ / contract',
                    'rates'            => 'Unit rates agree with the contract',
                    'documents'        => 'Supporting documents complete (accomplishment report, photos, delivery receipts)',
                    'variations'       => 'Only approved variation orders are billed',
                    'previous'         => 'Previous billings correctly deducted',
                    'retention'        => 'Retention correctly computed',
                    'non_compliance'   => 'No open non-compliance blocking payment',
                ]],
                ['key' => 'recommendation', 'label' => 'Recommendation', 'type' => 'select', 'required' => true, 'options' => [
                    'approve'  => 'Approve as billed',
                    'adjust'   => 'Approve with adjustments',
                    'return'   => 'Return to contractor for correction',
                ]],
                ['key' => 'remarks', 'label' => 'Remarks / Basis of Adjustments', 'type' => 'textarea'],
            ],
        ],

        'acceptance' => [
            'label'  => 'Project Completion and Acceptance Certificate',
            'prefix' => 'PCA',
            'source' => 'completion',
            'chain'  => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER, User::ROLE_DIVISION_MANAGER],
            'verbs'  => [User::ROLE_PMD_ASST_MANAGER => 'reviewed', User::ROLE_PMD_DEPT_MANAGER => 'approved', User::ROLE_DIVISION_MANAGER => 'approved'],
            'fields' => [],
        ],

        'completion-summary' => [
            'label'  => 'Project Completion Summary',
            'prefix' => 'PCS',
            'source' => 'completion',
            'chain'  => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER, User::ROLE_DIVISION_MANAGER],
            'verbs'  => [User::ROLE_PMD_ASST_MANAGER => 'reviewed', User::ROLE_PMD_DEPT_MANAGER => 'approved', User::ROLE_DIVISION_MANAGER => 'approved'],
            'fields' => [],
        ],
    ],
];
