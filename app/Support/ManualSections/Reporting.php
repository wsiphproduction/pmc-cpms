<?php

namespace App\Support\ManualSections;

/** Reporting, files, notifications and printing — the cross-cutting parts. */
class Reporting
{
    public static function sections(): array
    {
        return [
            'weekly-status' => [
                'title' => 'Weekly Status',
                'blocks' => [
                    ['path', 'Top bar > Weekly Status'],
                    ['p', 'One page from which a project engineer files the weekly reporting owed across every project they handle, instead of opening each project’s hub in turn. A report filed here is the same record the PSR tab writes: it shows up in the project’s hub and rolls into its completion exactly as if it had been filed there.'],

                    ['h', 'What the page shows'],
                    ['ul', [
                        'Every project you handle, with its status, its completion and the NTPs on it.',
                        'The most recent reports filed against them, newest first.',
                        'Who filed each report, when, and the file attached to it.',
                    ]],

                    ['h', 'Filing one report'],
                    ['ol', [
                        'Choose the project, and the NTP if the project has more than one.',
                        'Set the week code. The current week is suggested for you.',
                        'Enter the completion percentage, 0 to 100.',
                        'Write the identified issues and the progress updates.',
                        'Work through the site checklist, marking each line and adding remarks where they are needed.',
                        'List the issues with their action plans and commitment dates.',
                        'Attach the signed PSR as a PDF if you have it.',
                        'Submit.',
                    ]],

                    ['h', 'Filing a week in bulk'],
                    ['p', 'Where you handle many projects, one upload can cover the whole week at once.'],
                    ['ol', [
                        'Click to download the template. It comes pre-loaded with your own projects and their NTP numbers.',
                        'Fill in a row per report. The project number column is what ties each row to its project, so a single workbook can mix projects, sub-projects and NTPs.',
                        'Upload the completed workbook.',
                        'Check the reports that appear in the list below.',
                    ]],
                    ['tip', 'Download a fresh template each week rather than reusing last week’s. It is generated against your current project list, so a project registered since will be in it.'],

                    ['h', 'Correcting a report'],
                    ['p', 'Edit or delete a report from its row, so long as you may edit the project it belongs to. Revising a report lets you attach a replacement PDF at the same time.'],

                    ['h', 'Why it matters'],
                    ['p', 'The completion percentage on the latest report is what the project reports as its progress. That figure feeds the project’s health, its parent’s rolled-up completion, and the completion KPI on everyone’s dashboard. A week not filed is a week the whole department reads as no progress.'],
                ],
            ],

            'reports' => [
                'title' => 'Reports',
                'blocks' => [
                    ['path', 'Top bar > Reports'],
                    ['p', 'The accomplishment report is a project engineer’s record of their own work: every project they registered, with the weekly progress reports filed against each. It downloads as an Excel workbook.'],

                    ['h', 'Generating one'],
                    ['ol', [
                        'Open Reports. The page tells you how many projects and how many weekly reports an export would cover.',
                        'Set a date range if you want one, or leave it blank for everything.',
                        'Generate. The workbook downloads.',
                    ]],
                    ['note', 'The date range filters the weekly reports, not the projects. A period export still lists every project you registered, so the reports in that period can be read in context.'],
                    ['p', 'Each export is written to the audit trail, with the number of projects it covered.'],
                ],
            ],

            'files' => [
                'title' => 'Files, Versions and Attachments',
                'blocks' => [
                    ['p', 'Every file in CPMS — an attachment on a request, a permit document, a quality procedure, a billing, a PSR — is versioned. Nothing is ever silently overwritten.'],

                    ['h', 'Replacing a file'],
                    ['ol', [
                        'Find the record the file is attached to.',
                        'Choose to replace the file.',
                        'Pick the new file, up to 20 MB.',
                        'Add a note saying what changed — the next person to look will thank you.',
                        'Save.',
                    ]],
                    ['p', 'The record keeps its identity and its place. The new file becomes the current one; the old becomes a previous version.'],

                    ['h', 'Going back to an earlier version'],
                    ['ol', [
                        'Open the record’s version history.',
                        'Find the version you want.',
                        'Restore it. It is put back in front as the current file.',
                    ]],
                    ['p', 'Restoring does not delete anything. The version you were on stays in the history, so you can go forward again.'],

                    ['h', 'Attachments on a request'],
                    ['p', 'Request attachments carry a type — Picture, Drawing, Report or Other — and a description. The type is required, and it is what lets the engineer find the drawing among the photographs. Attachments can be added and removed by the requester while the request is still editable, and replaced or rolled back at any time afterwards.'],

                    ['h', 'Practical limits'],
                    ['ul', [
                        'Most uploads are capped at 20 MB per file.',
                        'Completion photographs are capped at 5 MB each, twelve per project.',
                        'The PSR attachment must be a PDF. Other uploads accept the usual document and image formats.',
                    ]],
                    ['tip', 'Scan at a readable resolution rather than the highest one. A 20 MB scan of an A4 permit takes a minute to open on site.'],
                ],
            ],

            'notifications' => [
                'title' => 'Notifications and Email',
                'blocks' => [
                    ['p', 'CPMS tells you when something needs you. Notifications appear under the bell in the top bar; some events are emailed as well.'],

                    ['h', 'Using the bell'],
                    ['ol', [
                        'The number on the bell is how many you have not read.',
                        'Click it to see the most recent.',
                        'Click a notification to open the record it is about — it is marked read as you go.',
                        'Mark all read clears the count without opening anything.',
                    ]],

                    ['h', 'What you will be told about'],
                    ['table',
                        ['Event', 'Who hears about it'],
                        [
                            ['A request is raised', 'The project engineers.'],
                            ['A request is endorsed', 'The requester, and the next office in the chain.'],
                            ['A request is fully approved', 'The requester, and the engineers who can register it.'],
                            ['A request is rejected', 'The requester, and everyone who had already signed.'],
                            ['A comment is added', 'The other side of the conversation — the requester, or the engineers.'],
                            ['A request goes on Hold', 'The requester.'],
                            ['Technical feedback is added', 'The requester.'],
                            ['An NTP is submitted', 'The PMD Assistant Manager.'],
                            ['An NTP moves a step', 'The engineer who prepared it, and the next office.'],
                            ['An NTP is issued', 'The preparing engineer, and everyone behind the owning department.'],
                            ['A quotation is submitted', 'The project team.'],
                            ['Your account details change', 'You, at both the old and the new email address.'],
                        ],
                    ],

                    ['h', 'The badges'],
                    ['p', 'Separate from the bell, the NTP Reviews and For Approval menu items carry a badge showing how many records are waiting on you specifically. The bell tells you what has happened; the badges tell you what is blocked on you.'],

                    ['h', 'Email'],
                    ['p', 'Some events are also mailed: the RFQ sent to a contractor, the issued NTP sent to the awarded contractor, a password reset, and changes to your own account details. An account email change is announced to the old address as well as the new one, so a hijacked account cannot quietly move itself out of its owner’s reach.'],
                    ['tip', 'If email is not arriving at all, that is a server-side matter — raise it with the administrator rather than retrying.'],
                ],
            ],

            'printing' => [
                'title' => 'Printing PMD Forms',
                'blocks' => [
                    ['p', 'Four controlled forms print from CPMS, laid out as facsimiles of the paper the department already signs by hand:'],
                    ['table',
                        ['Form', 'Number', 'Printed from'],
                        [
                            ['Request for Quotation', 'PMD-PRJ-FRM-03', 'The RFQ row in the hub.'],
                            ['Notice to Proceed', 'PMD-PRJ-FRM-04', 'The NTP row in the hub.'],
                            ['Project Completion and Acceptance Certificate', 'PMD-PRJ-FRM-06', 'The project, once completion details are saved.'],
                            ['Project Completion Summary', 'PMD-PRJ-FRM-12', 'The project, once completion details are saved.'],
                        ],
                    ],

                    ['h', 'How printing works'],
                    ['ol', [
                        'Click the print action on the record.',
                        'The form opens in a new tab.',
                        'Depending on how your server is configured, either a finished PDF is shown, or the form appears as a page that opens your browser’s print dialog by itself.',
                        'Print it, or save it as a PDF from the print dialog.',
                    ]],
                    ['note', 'Both routes produce the same document — the difference is only whether the server or your own browser runs the print engine.'],

                    ['h', 'What is on a printed form'],
                    ['ul', [
                        'The header banner with the corporate and department crests.',
                        'The record’s own details and line items, taken from the system.',
                        'The signatory names, filled in from whoever holds each role, falling back to the names the administrator has configured while a seat is vacant.',
                        'Empty signature boxes, for wet signatures.',
                        'On an NTP, the approval stamps of the offices that have signed it in the system.',
                    ]],
                    ['warn', 'Anything the system does not hold prints as an empty box rather than being left out, so there is always somewhere to write it in by hand. Check the form before circulating it — an empty box may mean a field nobody filled in.'],
                    ['note', 'The forms are built by the server from the stored record, never from anything your browser sends up. That is what makes a printed approval stamp trustworthy.'],
                ],
            ],
        ];
    }
}
