<?php

namespace App\Support\ManualSections;

/** Master data, accounts, settings, and the back matter. */
class Administration
{
    public static function sections(): array
    {
        return [
            'master-data' => [
                'title' => 'Master Data',
                'blocks' => [
                    ['path', 'Top bar > Master Data'],
                    ['p', 'Master data is the set of lists every form in CPMS draws its dropdowns from. Keeping it clean is what stops the same site being spelled three ways across a year of projects. It is maintained by project engineers, assistant managers and the administrator.'],

                    ['h', 'The lists'],
                    ['table',
                        ['List', 'Used by'],
                        [
                            ['Job Types', 'Project requests.'],
                            ['Job Locations', 'Project requests.'],
                            ['Cost Codes', 'Requests, projects and other costs.'],
                            ['Sites', 'Projects.'],
                            ['Class', 'Projects.'],
                            ['Priority', 'Requests, projects and variations.'],
                            ['Status', 'Project status values.'],
                            ['Departments', 'User accounts and project ownership.'],
                            ['Divisions', 'Departments and division manager users.'],
                            ['Categories', 'Projects.'],
                            ['Service Types', 'Projects.'],
                            ['Work Forces', 'Projects.'],
                            ['Structures', 'Projects.'],
                            ['Suppliers', 'RFQ contractor lookup.'],
                        ],
                    ],

                    ['h', 'Adding and editing an entry'],
                    ['ol', [
                        'Open the tab for the list.',
                        'Click the add button at the top of the tab.',
                        'Fill in the name and description.',
                        'Save.',
                    ]],
                    ['p', 'Edit an entry from its row. Editing changes the label everywhere it is offered from then on.'],

                    ['h', 'Retiring an entry'],
                    ['p', 'Every list carries an active toggle. Switching an entry inactive takes it out of the dropdowns without touching the records that already use it — which is almost always what you want. Deleting is for entries created by mistake and never used.'],
                    ['warn', 'Prefer deactivating to deleting. A deleted cost code leaves the projects booked against it holding a code that no longer exists anywhere.'],

                    ['h', 'Bulk import'],
                    ['p', 'Cost codes and suppliers can be imported from a spreadsheet rather than typed in one by one. Open the tab, download or prepare the file in the expected format, and upload it.'],

                    ['h', 'Suppliers'],
                    ['p', 'The supplier list is what the RFQ form searches when you start typing a contractor’s name. Keeping supplier names and email addresses current here is what makes sending an RFQ a matter of a few keystrokes.'],
                ],
            ],

            'users' => [
                'title' => 'User Management',
                'blocks' => [
                    ['path', 'Top bar > Users'],
                    ['p', 'Administrator only. This is where every account in CPMS is created, given its role, and retired. There is one role per user, and the role is the whole of what that user may do.'],

                    ['h', 'Creating an account'],
                    ['ol', [
                        'Open Users and click to add a user.',
                        'Enter their name and email address. The address must be unique — it is what they sign in with.',
                        'Set a password of at least eight characters.',
                        'Choose the role.',
                        'If the role is Department User, choose their department. If it is Division Manager User, choose their division. Either is required for that role.',
                        'Save, then pass the password to the user and ask them to change it from their Account page.',
                    ]],
                    ['warn', 'The department on a Department User account is what decides which projects they see and which NTPs they are called on to sign. Getting it wrong sends NTPs to the wrong people, and the chain stalls.'],

                    ['h', 'The singleton roles'],
                    ['p', 'PMD Department Manager and Division Manager may each be held by only one active user at a time, because each is a single office whose signature a chain waits on. Assigning one to a second user is refused, and so is restoring a deleted user whose role has since been given to somebody else.'],
                    ['ol', [
                        'To move one of these offices, first change the current holder to another role, or delete their account.',
                        'Then assign the role to the new holder.',
                    ]],

                    ['h', 'Editing an account'],
                    ['p', 'Change a user’s name, email address or role from their row. Changing the role clears the department or division if the new role does not use one.'],
                    ['note', 'Changing somebody’s role does not rewrite approval steps they have already settled. Their signature stays on the record under the role they held when they gave it.'],

                    ['h', 'Resetting a password'],
                    ['ol', [
                        'Find the user and choose to reset their password.',
                        'Type the new password twice. It must be at least eight characters.',
                        'Save, and tell the user what it is.',
                    ]],
                    ['tip', 'Prefer sending the user through Forgot your password? on the sign-in page. It means the password never passes through you.'],

                    ['h', 'Deleting and restoring'],
                    ['p', 'Deleting a user moves them to the trash rather than erasing them, so the records they touched keep their name. A deleted user can be restored, provided their role is still free. Permanent deletion is available from the trash and cannot be undone.'],
                    ['note', 'You cannot delete your own account.'],
                ],
            ],

            'system-settings' => [
                'title' => 'System Settings',
                'blocks' => [
                    ['path', 'Top bar > Settings'],
                    ['p', 'Administrator only. Three things are set here, and each one shows up somewhere everybody sees.'],

                    ['h', 'Project completion KPI'],
                    ['p', 'The target completion percentage, from 1 to 100. It is the line the dashboard measures the average effective completion of all top-level projects against. Default is 80.'],

                    ['h', 'Retention percentage'],
                    ['p', 'The percentage withheld from a billing until the project is completed, from 0 to 100. Default is 5.'],
                    ['warn', 'Changing this does not restate billings already raised. Each billing copies the rate in force when it was made, deliberately, so that history stays true.'],

                    ['h', 'Signatories'],
                    ['p', 'The names printed under the signature blocks on the controlled PMD forms:'],
                    ['ul', [
                        'PMD Assistant Manager',
                        'PMD Manager',
                        'ECS Division Manager',
                        'Operations Director',
                    ]],
                    ['p', 'For the three roles that exist as accounts, the printed name is taken from whoever actually holds that role, and the name set here is only used while the seat is vacant. The Operations Director is not a CPMS role, so that name always comes from this page.'],
                    ['tip', 'Review these whenever somebody changes office. A form printed with a departed manager’s name has to be printed again.'],
                ],
            ],

            'account' => [
                'title' => 'Your Account',
                'blocks' => [
                    ['path', 'Top bar > your initials > Account'],
                    ['p', 'Your own details and your password. Every user has this page, whatever their role.'],

                    ['h', 'Changing your details'],
                    ['ol', [
                        'Open your Account page.',
                        'Change your name or your email address.',
                        'Save.',
                    ]],
                    ['note', 'When you change your email address, both the old address and the new one are notified. That is deliberate: it means an account cannot be quietly moved out of its owner’s reach.'],

                    ['h', 'Changing your password'],
                    ['ol', [
                        'On the same page, enter your current password.',
                        'Enter the new password twice.',
                        'Save.',
                    ]],
                    ['p', 'You will be asked for your current password, so somebody who finds your machine unlocked still cannot lock you out of it.'],
                    ['tip', 'Change the password your administrator gave you the first time you sign in.'],

                    ['h', 'What you cannot change'],
                    ['p', 'Your role, your department and your division are set by the administrator. If any of them is wrong — and a wrong department means you will not see your own projects — ask the administrator to correct it.'],
                ],
            ],

            'troubleshooting' => [
                'title' => 'Common Questions',
                'blocks' => [
                    ['h', 'I cannot see a menu item this manual describes'],
                    ['p', 'It belongs to a different role. Each role sees only the sections it uses. If you believe your role is wrong, ask the administrator to check it on the Users page.'],

                    ['h', 'I cannot edit my own request any more'],
                    ['p', 'A request can only be edited while it is For Approval or on Hold. Once the engineer endorses it, PMD is signing what they were shown, so the content is fixed. Ask the engineer to reject it if something substantial must change.'],

                    ['h', 'The Approve button is missing on something waiting for me'],
                    ['p', 'Check three things: that an earlier step has not still to be settled — steps go strictly in order; that you are on the right page — PMD offices sign from For Approval, department and division manager users from NTP Reviews; and that the record has not already been settled by somebody else.'],

                    ['h', 'A request or project has disappeared from my list'],
                    ['p', 'Department users see only their own requests and their department’s projects. If a project you expect is missing, the likely cause is that its department owner is set to another department, or that your account is registered against the wrong one.'],

                    ['h', 'The system will not let me send a second RFQ to a contractor'],
                    ['p', 'One RFQ per contractor per project. Add another quotation to the RFQ already there instead — that is how a revised offer is recorded.'],

                    ['h', 'The system will not let me prepare an NTP'],
                    ['p', 'An NTP against that RFQ is already pending review or already issued. Only once an NTP has been rejected may a fresh one be prepared against the same RFQ.'],

                    ['h', 'The contractor says they never got the RFQ'],
                    ['p', 'Check the recipient address on the RFQ, then use Resend. It sends the same portal link again rather than creating a second RFQ. Ask them to check their junk folder too.'],

                    ['h', 'An approval chain is stuck because somebody is away'],
                    ['p', 'The administrator may settle any step of any chain. Ask them rather than raising the record again — a duplicate leaves two records where the audit trail should show one.'],

                    ['h', 'A project shows as Delayed but the work is on track'],
                    ['p', 'Health compares reported progress against where the schedule says the project should be today. If the work has moved but the weekly status has not been filed, the system has nothing newer to read. File the report.'],

                    ['h', 'I uploaded the wrong file'],
                    ['p', 'Replace it. The record keeps its place and the wrong file becomes a previous version, so nothing is lost and the history stays honest.'],

                    ['h', 'A printed form has empty boxes'],
                    ['p', 'That is by design — anything the system does not hold prints as a box to write in by hand. If a box should have been filled, find the field it came from and fill it in, then print again.'],

                    ['h', 'I have forgotten my password'],
                    ['p', 'Use Forgot your password? on the sign-in page. If the message does not arrive, check your junk folder, then ask the administrator to reset it.'],
                ],
            ],

            'glossary' => [
                'title' => 'Glossary',
                'blocks' => [
                    ['kv', [
                        ['ACR', 'Actual Cost Report — the hub view putting budget, commitment, billing and payment side by side.'],
                        ['Approval chain', 'The fixed sequence of offices that must sign a record, in order.'],
                        ['AT', 'Audit Trail — the unalterable record of everything done on a project.'],
                        ['Baseline', 'The planned start and end of work, against which the actual is measured.'],
                        ['CAPEX', 'Capital expenditure — funding for an asset rather than for running costs.'],
                        ['CPMS', 'Construction Project Management System — this system.'],
                        ['Effective completion', 'A project’s progress with its sub-projects rolled up into it.'],
                        ['Health', 'Whether a project’s progress is where its schedule says it should be today.'],
                        ['Hold', 'A request paused because the project engineer has asked the requester for something.'],
                        ['IOC', 'Input Other Cost — spending outside the main contract line.'],
                        ['JIP', 'Joint implementation — work carried out jointly.'],
                        ['KPI', 'Key Performance Indicator — here, the target average project completion.'],
                        ['MTR', 'Materials Test Report — test results and compliance records for materials used.'],
                        ['NTP', 'Notice to Proceed — the document that tells a contractor to begin.'],
                        ['OPEX', 'Operating expenditure — funding for running costs rather than for an asset.'],
                        ['PMD', 'Project Management Department.'],
                        ['PSR', 'Project Status Report — the weekly progress report on a project.'],
                        ['QPP', 'Quality Plan and Procedures.'],
                        ['Retention', 'A percentage withheld from a billing until the project is completed.'],
                        ['RFP', 'Request for Payment — a billing against the work.'],
                        ['RFQ', 'Request for Quotation — a request to a contractor for a price.'],
                        ['Singleton role', 'A role only one active user may hold, because it is a single office.'],
                        ['Sub-project', 'A part of a project planned, procured and billed on its own.'],
                        ['VOF', 'Variation Order Form — a change to scope, schedule or cost after award.'],
                        ['Week code', 'The label identifying which week a status report covers.'],
                        ['WR No.', 'Work Request number — the reference the work was raised under.'],
                    ]],
                ],
            ],
        ];
    }
}
