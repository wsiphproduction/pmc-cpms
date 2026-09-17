<?php

namespace App\Support\ManualSections;

/** Projects: the register, creating one, NTP review, and closing out. */
class Projects
{
    public static function sections(): array
    {
        return [
            'projects-overview' => [
                'title' => 'Projects',
                'blocks' => [
                    ['path', 'Top bar > Projects'],
                    ['p', 'A project is a piece of work PMD has taken on. It carries a project number, a manager, a schedule, a budget and a Project Hub holding every document the work produces. Most projects begin life as an approved request; an engineer may also register one directly.'],

                    ['h', 'The project list'],
                    ['p', 'The list shows top-level projects. Search by number or title, and filter by status. Each row carries the project number, title, status, health and progress.'],
                    ['ul', [
                        'Progress is the effective completion — a parent project rolls up the progress of its sub-projects rather than reporting its own figure alone.',
                        'Health compares actual progress against where the schedule says the project should be today. A project behind that line reads as Delayed.',
                    ]],

                    ['h', 'Project statuses'],
                    ['table',
                        ['Status', 'Meaning'],
                        [
                            ['For Planning', 'Registered; scope and approach still being worked out.'],
                            ['RFQ/RFP Submitted', 'Quotation requests are out with contractors.'],
                            ['Proposal Under Review', 'Offers received and being evaluated.'],
                            ['Detailed Design Under Review', 'The design is being checked before procurement proceeds.'],
                            ['Endorsed for Executive Approval', 'Waiting on a decision above PMD.'],
                            ['NTP & Contract Processing', 'The Notice to Proceed is being prepared or is climbing its approval chain.'],
                            ['For Scheduling', 'Awarded; the start is being scheduled.'],
                            ['Ongoing', 'Work is in progress on site.'],
                            ['On Hold', 'Work has stopped for a reason recorded in the remarks.'],
                            ['Completed', 'The work is finished and accepted.'],
                            ['Closed', 'Finished and closed out administratively.'],
                            ['Canceled', 'Abandoned before completion.'],
                        ],
                    ],
                    ['note', 'Completed, Closed and Canceled are the inactive statuses. Projects in them drop out of the active counts on the dashboard.'],

                    ['h', 'Changing a project’s status'],
                    ['ol', [
                        'Open the project.',
                        'Click the status control and choose the new status.',
                        'Type your modification remarks — say why it moved.',
                        'Save.',
                    ]],
                    ['p', 'Every change is kept in the project’s status log with your name, the time and your remarks, and it appears in the audit trail.'],

                    ['h', 'The project page'],
                    ['p', 'Opening a project shows its details — number, encoder, asset ID, cost code, project cost, WR number and date, priority, department owner, owner’s email, class, category, service type, structure, workforce, deadline and the disciplines the work needs — followed by the thirteen hub sections and the summary cards for RFQs and NTPs.'],

                    ['h', 'What you may change'],
                    ['ul', [
                        'The administrator and the assistant manager may edit any project.',
                        'A project engineer may edit projects they registered.',
                        'The three PMD and division sign-off offices, department users and division manager users have a read-only view.',
                    ]],
                ],
            ],

            'projects-register' => [
                'title' => 'Registering a Project',
                'blocks' => [
                    ['path', 'Top bar > Projects > New Project, or from an approved request'],
                    ['p', 'Registering turns an approved request into a live project with its own number and hub. It is the project engineer’s job, and it is the point at which PMD takes formal ownership of the work.'],

                    ['h', 'From an approved request'],
                    ['ol', [
                        'Open Requests and find the request. It must read Approved.',
                        'Click Create Project on the row.',
                        'The project form opens with the request already linked. Fill in the rest.',
                        'Save. The project takes the next project number and the request is tied to it.',
                    ]],
                    ['warn', 'A request can only be registered once, and only while it is Approved. If the button is missing, either the chain is not finished or a project already exists.'],

                    ['h', 'Filling in the project form'],
                    ['kv', [
                        ['Title', 'The project name. Required.'],
                        ['Project Manager', 'The project engineer who will run it, chosen from the engineers on the system.'],
                        ['Site', 'Where the work is, from the site list.'],
                        ['Asset ID', 'The asset the work is against.'],
                        ['Class, Category, Service Type', 'Classification, all from master data.'],
                        ['Priority', 'How urgent the work is.'],
                        ['Status', 'Where the project starts — normally For Planning.'],
                        ['Work Force', 'Who will carry out the work.'],
                        ['WR No. and WR Date', 'The work request reference and its date.'],
                        ['Department Owner', 'The department the work is for. This is what decides which department users review its NTPs.'],
                        ['Owner’s Email', 'Where notices about the project are copied.'],
                        ['Cost Code', 'The code the spending is booked against.'],
                        ['Project Cost', 'The budget. This is set here by hand — issuing an NTP does not change it.'],
                        ['Deadline', 'The date the work is due. Health and the About to Lapse counter are measured against this.'],
                        ['Structure Type', 'The kind of structure involved, where it applies.'],
                        ['JIP', 'Tick if the work is part of a joint implementation.'],
                        ['Civil / Electrical / Mechanical', 'The disciplines the work needs.'],
                        ['Project Type', 'Major or minor. A major project must carry its approved proposal document.'],
                        ['Notes', 'Anything else worth recording.'],
                    ]],
                    ['warn', 'A major project will not save without its approved proposal document attached. Sub-projects are exempt — they inherit the proposal already approved for their parent.'],

                    ['h', 'The department owner matters'],
                    ['p', 'The department owner is not just a label. It decides who sees the project, and which department users are called on for the fourth signature when an NTP is raised against it. Getting it wrong means the NTP chain stalls on the wrong people.'],

                    ['h', 'Editing and deleting'],
                    ['p', 'Open the project and click Edit to change its details. Deleting a project is possible for whoever may edit it, but it removes the work from the register — confirm it is really a mistaken entry and not a project that should instead be marked Canceled.'],
                ],
            ],

            'ntp-reviews' => [
                'title' => 'NTP Reviews',
                'blocks' => [
                    ['path', 'Top bar > NTP Reviews'],
                    ['p', 'This is where the owning side of the business signs a Notice to Proceed. Department users give the fourth signature; the Division Manager User gives the fifth and last. Both work from this page rather than from the PMD For Approval portal, because these two steps are held per project — they belong to whoever is behind that particular project’s department or division, not to a single office.'],
                    ['note', 'These steps only come round once PMD has signed: the PMD Assistant Manager, the PMD Department Manager and the Division Manager sign first, in that order.'],

                    ['h', 'What the page shows'],
                    ['p', 'Every NTP you follow, with those awaiting review listed first, then the issued and rejected ones as history. Each row carries the NTP number, the contractor, the project, who prepared it, the approval timeline so far, and the RFQ line items behind it.'],
                    ['ul', [
                        'A department user follows NTPs on the projects they requested and on the projects their department owns.',
                        'A Division Manager User follows every department inside their division.',
                    ]],
                    ['p', 'Only the step that is actually yours is actionable. Rows further up or down the chain are there so you can see the whole picture, not so you can sign them.'],

                    ['h', 'Approving an NTP'],
                    ['ol', [
                        'Open NTP Reviews. The badge on the menu item tells you how many are waiting.',
                        'Read the NTP — the contractor, the approved cost, the baseline start and end dates, and the RFQ line items it was priced from.',
                        'Click Approve.',
                        'Confirm.',
                    ]],
                    ['p', 'If you are the department user, the NTP moves on to the Division Manager User, who is notified. If you are the Division Manager User, yours is the final signature and three things happen at once:'],
                    ['ul', [
                        'The NTP is issued, stamped with today’s date.',
                        'The RFQ it was priced from is marked Awarded.',
                        'Everyone behind the project’s department is notified, and the contractor is mailed their copy.',
                    ]],
                    ['warn', 'Issuing an NTP commits the company to the contractor at the approved cost. Check the amount and the dates before you approve — there is no un-issuing it afterwards.'],

                    ['h', 'Rejecting an NTP'],
                    ['ol', [
                        'Click Reject on the row.',
                        'Type the reason. The engineer who prepared the NTP will see exactly this.',
                        'Confirm.',
                    ]],
                    ['p', 'The NTP is closed as rejected, the chain stops, and the preparing engineer is notified with your reason. They must prepare a fresh NTP if the work is still to go ahead.'],
                ],
            ],

            'completion' => [
                'title' => 'Completion and Acceptance',
                'blocks' => [
                    ['path', 'Open a project > Completion'],
                    ['p', 'When the work is finished, the completion record is what closes it. It feeds the two controlled forms that end a project: the Project Completion and Acceptance Certificate (PMD-PRJ-FRM-06) and the Project Completion Summary (PMD-PRJ-FRM-12).'],

                    ['h', 'Recording completion'],
                    ['kv', [
                        ['Reference No.', 'The completion reference for the certificate.'],
                        ['Sub-Project Title', 'Where the certificate covers one part rather than the whole.'],
                        ['Classification', 'How the finished work is classified.'],
                        ['Planning baseline / actual', 'Planned start and end against what actually happened, for the planning phase.'],
                        ['Construction baseline / actual', 'The same pair for the construction phase.'],
                        ['Contractor', 'Who carried out the work.'],
                        ['Baseline Amount / Actual Amount', 'What was approved against what was spent.'],
                        ['Payment Status', 'Where the money stands at close-out.'],
                        ['Completion Status', 'The state the work was accepted in.'],
                        ['Request Date, Date Prepared, Issued On', 'The dates printed on the certificate.'],
                        ['Received By, Accepted By, Acknowledged By', 'The names printed under the signature blocks.'],
                        ['Photos', 'Up to twelve completion photographs, each up to 5 MB.'],
                    ]],
                    ['ol', [
                        'Open the project and go to the completion area.',
                        'Fill in the dates and amounts. Leave anything you do not have blank — it prints as an empty box for someone to write in by hand.',
                        'Add the completion photographs.',
                        'Save. The record is written to the audit trail.',
                    ]],
                    ['tip', 'The baseline against actual pairs are the point of the summary form. Filling both in is what lets the finished project be read against what was promised.'],

                    ['h', 'Printing the certificates'],
                    ['p', 'Once the details are saved, print the Acceptance Certificate and the Completion Summary from the project. Both open in a new tab, laid out exactly like the paper forms, with the signature blocks left empty for wet signatures and the signatory names filled in from the system. See Printing PMD Forms for how printing works.'],

                    ['h', 'Closing the project'],
                    ['p', 'Set the project status to Completed, and then to Closed once the administrative close-out is done. It drops out of the active counts on the dashboard at that point.'],
                ],
            ],
        ];
    }
}
