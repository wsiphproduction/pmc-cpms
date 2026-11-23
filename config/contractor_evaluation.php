<?php

/*
|--------------------------------------------------------------------------
| Contractor Performance Evaluation
|--------------------------------------------------------------------------
|
| From PMC's "Contractor Performance Evaluation Framework" and its forms A–H.
| Each category is one form; each criterion carries its own weight, and a
| category's criterion weights add up to the category's share of 100.
|
|   Weighted score  = (rating ÷ 5) × criterion weight
|   Category score  = sum of its weighted scores   (max = category weight)
|   Project score   = sum of the category scores    (max = 100)
|
| The category keys are also the hub sections (eval-<key>).
|
*/

return [

    'categories' => [
        'schedule' => [
            'letter'  => 'A',
            'title'   => 'Project Schedule and Timeliness',
            'weight'  => 20,
            'purpose' => 'To assess the contractor’s compliance with the approved project commencement date, mobilization requirements, work schedule, activity and project completion targets, delay management, and resource scheduling.',
            'note'    => 'Base the evaluation on the approved NTP, project schedule/Gantt Chart, mobilization records, daily and weekly accomplishment reports, progress measurements, approved extensions of time, and documented delay and recovery plans.',
            'criteria' => [
                ['key' => 'ntp_compliance',       'label' => 'NTP Compliance',       'weight' => 3.0, 'basis' => 'Mobilization and commencement of work within the approved start date specified in the Notice to Proceed (NTP), subject to applicable approved adjustments.'],
                ['key' => 'mobilization',         'label' => 'Project Mobilization', 'weight' => 3.0, 'basis' => 'Timely deployment and readiness of the required manpower, equipment, tools, and materials in accordance with the approved mobilization plan and work requirements.'],
                ['key' => 'schedule_compliance',  'label' => 'Schedule Compliance',  'weight' => 4.0, 'basis' => 'Actual project progress compared with the approved project schedule or Gantt Chart, including identification and explanation of schedule variances.'],
                ['key' => 'activity_completion',  'label' => 'Activity Completion',  'weight' => 3.0, 'basis' => 'Completion of scheduled activities and work packages within their approved or agreed target dates.'],
                ['key' => 'project_completion',   'label' => 'Project Completion',   'weight' => 3.0, 'basis' => 'Completion of the project within the approved contractual duration, taking into account any duly approved extension of time.'],
                ['key' => 'delay_management',     'label' => 'Delay Management',     'weight' => 2.0, 'basis' => 'Timely reporting of actual or anticipated delays, identification of causes, and submission and implementation of appropriate recovery or catch-up plans.'],
                ['key' => 'resource_scheduling',  'label' => 'Resource Scheduling',  'weight' => 2.0, 'basis' => 'Availability and deployment of manpower, equipment, tools, and materials in accordance with the approved work schedule and planned activities.'],
            ],
        ],

        'quality' => [
            'letter'  => 'B',
            'title'   => 'Quality of Workmanship and Technical Compliance',
            'weight'  => 20,
            'purpose' => 'To assess the quality of the contractor’s completed work and compliance with approved drawings, technical specifications, material standards, inspection and testing requirements, defect correction, and project acceptance requirements, including end-user feedback.',
            'note'    => 'Base the evaluation on approved drawings and revisions, technical specifications, material submittals and test results, inspection records, punch lists, defect and rework logs, acceptance documents, and documented end-user feedback.',
            'criteria' => [
                ['key' => 'approved_drawings',     'label' => 'Approved Drawings',        'weight' => 3.0, 'basis' => 'Execution of works in accordance with the latest approved drawings, plans, and details, including approved revisions.'],
                ['key' => 'technical_specs',       'label' => 'Technical Specifications', 'weight' => 3.0, 'basis' => 'Compliance with approved material, construction, installation, and workmanship specifications and applicable project standards.'],
                ['key' => 'workmanship',           'label' => 'Workmanship',              'weight' => 3.0, 'basis' => 'Quality of installation, fabrication, construction, alignment, dimensions, finishing, and overall execution of the works.'],
                ['key' => 'material_quality',      'label' => 'Material Quality',         'weight' => 2.0, 'basis' => 'Materials delivered and incorporated into the works comply with approved submittals, specifications, and applicable standards.'],
                ['key' => 'inspection_compliance', 'label' => 'Inspection Compliance',    'weight' => 2.0, 'basis' => 'Compliance with required inspections, testing, hold points, and quality control procedures, including submission of inspection records.'],
                ['key' => 'defect_rectification',  'label' => 'Defect Rectification',     'weight' => 2.0, 'basis' => 'Timely and effective correction of identified deficiencies and completion of corrective works within agreed timeframes.'],
                ['key' => 'rework_rejection',      'label' => 'Rework and Rejection',     'weight' => 2.0, 'basis' => 'Frequency, extent, and causes of rejected or repeated works, including the contractor’s actions to prevent recurrence.'],
                ['key' => 'final_acceptance',      'label' => 'Final Acceptance',         'weight' => 1.5, 'basis' => 'Compliance with project completion, inspection, punch-list closure, testing, and other applicable acceptance requirements.'],
                ['key' => 'end_user_feedback',     'label' => 'End-User Feedback',        'weight' => 1.5, 'basis' => 'End-user department’s documented satisfaction with work quality, compliance with project requirements, responsiveness to concerns, and overall project deliverables.'],
            ],
        ],

        'manpower' => [
            'letter'  => 'C',
            'title'   => 'Manpower Management and Availability',
            'weight'  => 10,
            'purpose' => 'To assess the contractor’s compliance with approved manpower requirements, personnel qualifications, attendance, supervision, replacement arrangements, and productivity.',
            'note'    => 'Evaluate based on approved manpower schedules, attendance records, personnel documents, daily reports, site observations, and verified work outputs.',
            'criteria' => [
                ['key' => 'manpower_availability',    'label' => 'Manpower Availability',      'weight' => 2.5, 'basis' => 'Availability and deployment of required personnel based on the approved manpower schedule.'],
                ['key' => 'personnel_qualifications', 'label' => 'Personnel Qualifications',   'weight' => 2.0, 'basis' => 'Compliance with required licenses, certifications, training, experience, and competencies for assigned positions.'],
                ['key' => 'attendance',               'label' => 'Attendance and Punctuality', 'weight' => 2.0, 'basis' => 'Attendance and compliance with scheduled working hours, including timely reporting and avoidance of unjustified absences or tardiness.'],
                ['key' => 'replacement_personnel',    'label' => 'Replacement Personnel',      'weight' => 1.0, 'basis' => 'Timeliness and adequacy of replacement personnel when assigned personnel are absent, unavailable, or unable to perform their duties.'],
                ['key' => 'supervision',              'label' => 'Supervision',                'weight' => 1.5, 'basis' => 'Availability and effectiveness of qualified supervisors and project engineers in directing, monitoring, and coordinating site activities.'],
                ['key' => 'productivity',             'label' => 'Productivity',               'weight' => 1.0, 'basis' => 'Manpower productivity and actual work output compared with the planned or required work output.'],
            ],
        ],

        'equipment' => [
            'letter'  => 'D',
            'title'   => 'Equipment and Resource Management',
            'weight'  => 10,
            'purpose' => 'To assess the contractor’s provision, suitability, condition, reliability, replacement, and utilization monitoring of equipment and other resources required for project execution.',
            'note'    => 'Base the evaluation on approved equipment schedules, inspection and maintenance records, breakdown reports, utilization logs, delivery records, and verified site observations.',
            'criteria' => [
                ['key' => 'equipment_availability', 'label' => 'Equipment Availability', 'weight' => 2.0, 'basis' => 'Availability and deployment of required equipment in accordance with the approved project schedule and work requirements.'],
                ['key' => 'equipment_suitability',  'label' => 'Equipment Suitability',  'weight' => 1.5, 'basis' => 'Equipment capacity, type, and technical specifications are appropriate and sufficient for the assigned project activities.'],
                ['key' => 'equipment_condition',    'label' => 'Equipment Condition',    'weight' => 2.0, 'basis' => 'Equipment is safe, serviceable, properly inspected, and maintained in accordance with applicable requirements and manufacturer recommendations.'],
                ['key' => 'equipment_breakdown',    'label' => 'Equipment Breakdown',    'weight' => 1.5, 'basis' => 'Frequency and duration of equipment breakdowns or downtime, including the contractor’s response and restoration of equipment to operating condition.'],
                ['key' => 'replacement_equipment',  'label' => 'Replacement Equipment',  'weight' => 1.0, 'basis' => 'Timely provision of suitable replacement equipment in compliance with contractual requirements when the original unit is unavailable or unserviceable.'],
                ['key' => 'utilization_monitoring', 'label' => 'Utilization Monitoring', 'weight' => 1.0, 'basis' => 'Accuracy, completeness, and timely submission of equipment utilization records, including operating hours and service-meter readings.'],
                ['key' => 'materials_tools',        'label' => 'Materials and Tools',    'weight' => 1.0, 'basis' => 'Availability and adequacy of required construction materials, tools, and supplies to support the planned work activities.'],
            ],
        ],

        'hse' => [
            'letter'  => 'E',
            'title'   => 'Health, Safety and Environmental Compliance',
            'weight'  => 15,
            'purpose' => 'To assess the contractor’s compliance with site safety requirements, approved safety procedures, incident reporting, corrective actions, environmental controls, housekeeping, and applicable permits and clearances.',
            'note'    => 'Base the evaluation on safety orientation records, PPE inspections, approved safety documents, incident reports, inspection findings, corrective action logs, MEPEO records, housekeeping inspections, and valid permits and clearances.',
            'criteria' => [
                ['key' => 'safety_orientation',       'label' => 'Safety Orientation',       'weight' => 1.5, 'basis' => 'Completion of required site safety orientations and other mandatory safety briefings before personnel commence work.'],
                ['key' => 'ppe_compliance',           'label' => 'PPE Compliance',           'weight' => 2.0, 'basis' => 'Proper availability, condition, and use of required personal protective equipment by all assigned personnel.'],
                ['key' => 'safety_procedures',        'label' => 'Safety Procedures',        'weight' => 2.5, 'basis' => 'Compliance with approved safety plans, safe work procedures, risk assessments, toolbox meetings, and site safety instructions.'],
                ['key' => 'incident_reporting',       'label' => 'Incident Reporting',       'weight' => 1.5, 'basis' => 'Timely reporting and documentation of accidents, near misses, unsafe acts, and unsafe conditions in accordance with site requirements.'],
                ['key' => 'corrective_actions',       'label' => 'Corrective Actions',       'weight' => 2.0, 'basis' => 'Timely and effective closure of safety findings, inspection observations, and incident-related corrective actions.'],
                ['key' => 'environmental_compliance', 'label' => 'Environmental Compliance', 'weight' => 2.0, 'basis' => 'Compliance with MEPEO requirements, approved environmental controls, waste management procedures, and applicable environmental conditions.'],
                ['key' => 'housekeeping',             'label' => 'Housekeeping',             'weight' => 1.5, 'basis' => 'Cleanliness, orderliness, and proper maintenance of work areas, including proper storage and disposal of materials and waste.'],
                ['key' => 'permits_clearances',       'label' => 'Permits and Clearances',   'weight' => 2.0, 'basis' => 'Compliance with applicable work permits, clearances, approvals, and regulatory requirements before and during execution of work.'],
            ],
        ],

        'contractual' => [
            'letter'  => 'F',
            'title'   => 'Contractual and Administrative Compliance',
            'weight'  => 10,
            'purpose' => 'To assess the contractor’s compliance with contractual terms, approved scope, billing and documentation requirements, insurance and bonds, agreed deliverables, change management procedures, and other contractual obligations.',
            'note'    => 'Base the evaluation on the approved contract, scope of work, billing submissions, supporting records, insurance and bond documents, approved variation orders, deliverable registers, and other relevant contractual records.',
            'criteria' => [
                ['key' => 'contract_compliance',   'label' => 'Contract Compliance',   'weight' => 1.5, 'basis' => 'Compliance with the approved contract terms and conditions, including applicable timelines, responsibilities, and performance requirements.'],
                ['key' => 'scope_of_work',         'label' => 'Scope of Work',         'weight' => 1.5, 'basis' => 'Execution of work within the approved project scope, drawings, specifications, and authorized instructions; deviations are properly referred for approval.'],
                ['key' => 'billing_accuracy',      'label' => 'Billing Accuracy',      'weight' => 1.5, 'basis' => 'Accuracy, consistency, and completeness of submitted billings in accordance with actual completed work, contract rates, and agreed billing procedures.'],
                ['key' => 'supporting_documents',  'label' => 'Supporting Documents',  'weight' => 1.0, 'basis' => 'Timely submission and completeness of required billing attachments, accomplishment reports, delivery records, and other supporting documents.'],
                ['key' => 'insurance_bonds',       'label' => 'Insurance and Bonds',   'weight' => 1.0, 'basis' => 'Validity and timely renewal or submission of required insurance policies, performance bonds, and other contractual documents.'],
                ['key' => 'contract_deliverables', 'label' => 'Contract Deliverables', 'weight' => 1.0, 'basis' => 'Completion and submission of agreed deliverables and milestones in accordance with contractual requirements and approved schedules.'],
                ['key' => 'change_management',     'label' => 'Change Management',     'weight' => 1.5, 'basis' => 'Proper documentation, evaluation, and approval of variation orders, changes in scope, and other contract amendments before implementation, as required.'],
                ['key' => 'other_obligations',     'label' => 'Other Obligations',     'weight' => 1.0, 'basis' => 'Compliance with other applicable requirements and obligations stipulated in the contract and approved project documents.'],
            ],
        ],

        'documentation' => [
            'letter'  => 'G',
            'title'   => 'Project Documentation and Reporting',
            'weight'  => 5,
            'purpose' => 'To assess the contractor’s timely, accurate, and complete submission of project accomplishment reports, progress updates, site records, testing reports, as-built drawings, and final turnover documentation.',
            'note'    => 'Base the evaluation on submitted daily and weekly reports, site records, inspection and monitoring documents, laboratory and material testing reports, approved as-built drawings, and final turnover records.',
            'criteria' => [
                ['key' => 'daily_reports',      'label' => 'Daily Accomplishment Reports', 'weight' => 1.0,  'basis' => 'Timely, accurate, and complete reporting of daily work activities, manpower, equipment utilization, materials delivered or used, and actual accomplishments, as required.'],
                ['key' => 'weekly_reports',     'label' => 'Weekly Progress Reports',      'weight' => 1.0,  'basis' => 'Submission of updated project progress reports reflecting actual accomplishments, schedule status, issues, delays, and planned activities.'],
                ['key' => 'site_documentation', 'label' => 'Site Documentation',           'weight' => 1.0,  'basis' => 'Completeness and accuracy of site inspection records, monitoring reports, site instructions, meeting records, and progress photographs, as applicable.'],
                ['key' => 'testing_reports',    'label' => 'Testing Reports',              'weight' => 0.75, 'basis' => 'Timely submission of required laboratory, material testing, and quality control reports, including relevant results and certifications.'],
                ['key' => 'as_built',           'label' => 'As-Built Drawings',            'weight' => 0.75, 'basis' => 'Submission of accurate and complete as-built drawings reflecting the actual completed works and approved changes.'],
                ['key' => 'turnover',           'label' => 'Turnover Documents',           'weight' => 0.5,  'basis' => 'Completeness and proper organization of final project records and turnover documents required for project completion and acceptance.'],
            ],
        ],

        'coordination' => [
            'letter'  => 'H',
            'title'   => 'Coordination and Responsiveness',
            'weight'  => 10,
            'purpose' => 'To assess the contractor’s communication, coordination with PMD and concerned departments, participation in meetings, responsiveness in resolving issues and inspection findings, and professional conduct when dealing with PMC personnel.',
            'note'    => 'Base the evaluation on email and correspondence records, PMD instructions, meeting minutes and attendance, issue and action trackers, inspection findings, and documented interactions with PMC personnel.',
            'criteria' => [
                ['key' => 'communication',        'label' => 'Communication',        'weight' => 2.0, 'basis' => 'Timeliness, clarity, and completeness of responses to PMD instructions, requests, and official correspondence.'],
                ['key' => 'coordination',         'label' => 'Coordination',         'weight' => 2.0, 'basis' => 'Effectiveness of coordination with PMD and concerned PMC departments on work planning, site activities, resources, and project requirements.'],
                ['key' => 'meeting_attendance',   'label' => 'Meeting Attendance',   'weight' => 1.5, 'basis' => 'Attendance, punctuality, preparedness, and active participation in scheduled project meetings and coordination discussions.'],
                ['key' => 'issue_resolution',     'label' => 'Issue Resolution',     'weight' => 2.0, 'basis' => 'Timeliness and effectiveness in addressing site concerns, technical issues, work constraints, and matters affecting project implementation.'],
                ['key' => 'corrective_action',    'label' => 'Corrective Action',    'weight' => 1.5, 'basis' => 'Prompt acknowledgment and implementation of actions required to address PMD inspection findings and instructions.'],
                ['key' => 'professional_conduct', 'label' => 'Professional Conduct', 'weight' => 1.0, 'basis' => 'Cooperation, respect, accountability, and professionalism in dealing with PMC personnel and other project stakeholders.'],
            ],
        ],
    ],

    /** The 1–5 rating guide printed on every form. */
    'ratings' => [
        5 => ['label' => 'Excellent',         'description' => 'Consistently exceeds requirements'],
        4 => ['label' => 'Very Good',         'description' => 'Often exceeds requirements'],
        3 => ['label' => 'Satisfactory',      'description' => 'Meets basic requirements'],
        2 => ['label' => 'Needs Improvement', 'description' => 'Partially meets requirements'],
        1 => ['label' => 'Unsatisfactory',    'description' => 'Fails to meet requirements'],
    ],

    /**
     * Overall classification of a project score (0–100), highest band first.
     * Proposed internal thresholds, per the framework — to be confirmed by
     * PMC Management with Procurement and Legal.
     */
    'classifications' => [
        ['min' => 90, 'label' => 'Excellent',         'action' => 'Preferred contractor for future consideration.'],
        ['min' => 80, 'label' => 'Very Good',         'action' => 'Eligible for future project consideration.'],
        ['min' => 70, 'label' => 'Satisfactory',      'action' => 'Continue engagement with regular monitoring.'],
        ['min' => 60, 'label' => 'Needs Improvement', 'action' => 'Require a corrective action plan.'],
        ['min' => 0,  'label' => 'Unsatisfactory',    'action' => 'Management review and possible restriction from future engagement, subject to contract and procurement rules.'],
    ],

    /** When an evaluation is done (framework §7). */
    'period_types' => [
        'monthly'    => 'Monthly',
        'quarterly'  => 'Quarterly',
        'completion' => 'Upon Project Completion',
        'annual'     => 'Annual',
    ],
];
