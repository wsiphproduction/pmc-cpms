<?php

namespace App\Support\ManualSections;

/** Orientation: what CPMS is, how to sign in, who does what, and the dashboard. */
class Introduction
{
    public static function sections(): array
    {
        return [
            'about' => [
                'title' => 'About CPMS',
                'blocks' => [
                    ['p', 'CPMS is the Construction Project Management System of the Project Management Department (PMD) of Philsaga Mining Corporation and Mindanao Mineral Processing and Refining Corporation. It carries a piece of work from the moment a department asks for it through to the certificate that closes it, and it keeps the paperwork, the approvals and the money in one place instead of across mailboxes and spreadsheets.'],

                    ['h', 'What the system holds'],
                    ['ul', [
                        'Project requests raised by the operating departments, with their attachments and the conversation about them.',
                        'Projects registered by PMD, each with a Project Hub holding thirteen working areas — procurement, permits, variations, quality, billing, cost and progress.',
                        'Two sequential sign-off chains: one for a project request, one for a Notice to Proceed.',
                        'The controlled PMD forms — RFQ, NTP, the Acceptance Certificate and the Completion Summary — printed from the record rather than typed again.',
                        'A supplier portal that lets contractors quote against an RFQ without holding an account.',
                        'Master data — job types, cost codes, sites, suppliers and the rest — that every form draws its dropdowns from.',
                    ]],

                    ['h', 'The shape of the flow'],
                    ['ol', [
                        'A department user raises a project request describing the work needed.',
                        'A PMD project engineer reviews it, may hold it for more information, and endorses it.',
                        'The PMD Assistant Manager and then the PMD Department Manager sign it. The request is now approved.',
                        'An engineer registers the approved request as a project, which gets a project number and a Project Hub.',
                        'The engineer sends RFQs to contractors, receives quotations and marks one final.',
                        'The engineer prepares a Notice to Proceed against the winning quotation and sends it for approval.',
                        'Five offices sign the NTP in order. The last signature issues it, awards the RFQ, and mails the contractor a copy.',
                        'Work proceeds. The engineer files weekly status reports, logs permits, variations, materials tests and billings.',
                        'When the work is done the engineer records completion and prints the Acceptance Certificate and Completion Summary.',
                    ]],

                    ['note', 'Every user holds exactly one role, and that role decides which menu items appear and which buttons are live. If this manual describes a screen you cannot see, it belongs to a different role.'],
                ],
            ],

            'getting-started' => [
                'title' => 'Getting Started',
                'blocks' => [
                    ['h', 'Signing in'],
                    ['ol', [
                        'Open CPMS in your browser. You will land on the sign-in page.',
                        'Enter the email address your administrator registered for you, and your password.',
                        'Tick Remember me on a machine only you use, so you are not asked again each day.',
                        'Click Log in. You arrive at your Dashboard.',
                    ]],
                    ['p', 'Accounts are created for you by the administrator — you do not sign yourself up. If you have no account, ask the administrator to create one and tell them which department or division you belong to.'],

                    ['h', 'If you have forgotten your password'],
                    ['ol', [
                        'Click Forgot your password? on the sign-in page.',
                        'Type your registered email address and click Email Password Reset Link.',
                        'Open the message that arrives and click its link. It takes you to a page where you set a new password.',
                        'Sign in with the new password.',
                    ]],
                    ['tip', 'If the reset message does not arrive within a few minutes, check your junk folder first, then ask the administrator to reset the password for you from the Users page.'],

                    ['h', 'The top bar'],
                    ['p', 'Every page in CPMS carries the same bar across the top. From left to right it holds the brand, the main menu, the notification bell, and your own account menu.'],
                    ['table',
                        ['Item', 'What it does'],
                        [
                            ['Main menu', 'The sections your role may open. Items with a sub-menu expand when you hover or tap them.'],
                            ['Badges', 'A number beside NTP Reviews or For Approval is how many items are waiting on you right now.'],
                            ['Bell', 'Your notifications. Click one to jump straight to the record it is about; Mark all read clears the count.'],
                            ['Your initials', 'Your account menu — Account settings and Log out.'],
                        ],
                    ],
                    ['p', 'On a narrow screen the menu collapses behind a button. Tap it to open the same list as a drawer.'],

                    ['h', 'Finding your way around a list'],
                    ['p', 'The Requests, Projects, Users and Master Data pages all work the same way. A search box filters by number or title; the dropdowns beside it narrow the list further; the table below shows what matched. Buttons at the right of each row are the actions you are allowed to take on that record — if an action is not yours to take, the button is not shown at all.'],

                    ['h', 'Signing out'],
                    ['ol', [
                        'Click your initials at the far right of the top bar.',
                        'Choose Log out.',
                    ]],
                    ['warn', 'Always sign out on a shared or public machine. Your session carries your approval authority, and anything done in it is recorded against your name in the audit trail.'],
                ],
            ],

            'roles' => [
                'title' => 'Roles and What Each One Can Do',
                'blocks' => [
                    ['p', 'CPMS has eight roles. A user holds exactly one. The role decides three things: which menu items appear, which records are visible, and which buttons are live on a record you can see.'],

                    ['table',
                        ['Role', 'Belongs to', 'Chiefly responsible for'],
                        [
                            ['Department User', 'A requesting department', 'Raising project requests; the fourth signature on their department’s NTPs.'],
                            ['Project Engineer', 'PMD', 'Reviewing requests, registering projects, procurement, weekly reporting.'],
                            ['Assistant Manager', 'PMD (engineering side)', 'Covering for project engineers; may edit any project.'],
                            ['PMD Assistant Manager', 'PMD (sign-off)', 'Second signature on a request; first on an NTP.'],
                            ['PMD Department Manager', 'PMD (sign-off)', 'Final signature on a request; second on an NTP.'],
                            ['Division Manager', 'ECS Division (sign-off)', 'Third signature on an NTP.'],
                            ['Division Manager User', 'The owning division', 'Fifth and final signature on an NTP.'],
                            ['Admin', 'System administration', 'Accounts, master data, settings; may settle any approval step.'],
                        ],
                    ],

                    ['h', 'Who sees what'],
                    ['p', 'Everyone inside PMD — project engineers, assistant managers, the three sign-off offices and the administrator — sees every request and every project, unscoped. Department users and Division Manager Users are the ones held to a narrower view:'],
                    ['ul', [
                        'A Department User sees the requests they themselves raised, and the projects owned by their department or descended from a request they raised.',
                        'A Division Manager User sees the projects of every department inside their division.',
                    ]],

                    ['h', 'Who may change what'],
                    ['table',
                        ['Action', 'Who may do it'],
                        [
                            ['Raise a project request', 'Anyone except the three PMD/division sign-off offices.'],
                            ['Edit or delete a request', 'The requester who raised it, and only while it is For Approval or on Hold.'],
                            ['Endorse or reject a request', 'Project Engineer, Assistant Manager, Admin — at the first step only.'],
                            ['Register a project', 'Project Engineer, Assistant Manager, Admin.'],
                            ['Edit a project and its hub', 'Admin and Assistant Manager on any project; a Project Engineer on projects they registered.'],
                            ['Change a billing’s status', 'The assigned project manager or the engineer who registered the project, plus Admin and Assistant Manager.'],
                            ['File a weekly status report', 'Project Engineer, Assistant Manager, Admin.'],
                            ['Maintain master data', 'Project Engineer, Assistant Manager, Admin.'],
                            ['Manage users and system settings', 'Admin only.'],
                        ],
                    ],

                    ['note', 'Two roles are singletons: only one active user may hold PMD Department Manager, and only one may hold Division Manager, because each is a single office whose signature the chain waits on.'],
                ],
            ],

            'dashboard' => [
                'title' => 'The Dashboard',
                'blocks' => [
                    ['path', 'Top bar > Dashboard'],
                    ['p', 'The dashboard is the page you land on after signing in. It answers one question — what needs my attention — and it shows a different answer to delivery staff than it shows to a requesting department.'],

                    ['h', 'If you are in PMD'],
                    ['p', 'Project engineers, assistant managers, the sign-off offices and the administrator see four counters across the top:'],
                    ['kv', [
                        ['Active Projects', 'Top-level projects that are not Completed, Closed or Canceled. Sub-projects are counted inside their parent, never separately.'],
                        ['Delayed', 'Active projects whose progress has fallen behind where the schedule says it should be.'],
                        ['About to Lapse', 'Active projects whose deadline falls within the next seven days.'],
                        ['Pending Requests', 'Requests not yet settled — For Approval, In Approval, or on Hold.'],
                    ]],
                    ['p', 'Below the counters sits the completion KPI: the target the administrator has set, against the average effective completion of every top-level project. "Effective" means a parent project’s figure rolls up the progress of its sub-projects, so a parent is never reported as finished while its parts are not.'],

                    ['h', 'If you are a department user'],
                    ['p', 'You see three counters instead — Active Projects, unread comment notifications, and the number of requests you have raised — followed by a list of the NTPs on your department’s projects that are waiting for your signature.'],

                    ['h', 'The tables below'],
                    ['ul', [
                        'Notifications — your eight most recent. Click one to open the record it concerns.',
                        'Projects — your five most recent, with status, health and progress.',
                        'Requests — your five most recent, with requester and status.',
                        'Audit trail — the last eight things that happened on projects you can see, with who did them and when.',
                    ]],
                    ['tip', 'Health is the quickest read on the list: it compares where a project’s progress actually is against where its schedule says it should be by today.'],
                ],
            ],
        ];
    }
}
