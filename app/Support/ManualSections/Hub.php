<?php

namespace App\Support\ManualSections;

/** The thirteen working areas of the Project Hub, plus the supplier portal. */
class Hub
{
    public static function sections(): array
    {
        return [
            'hub-overview' => [
                'title' => 'The Project Hub',
                'blocks' => [
                    ['path', 'Open a project > the hub tabs'],
                    ['p', 'The hub is where a project is actually run. Thirteen sections sit behind one project, each holding one kind of record, and every change made in any of them is written to the project’s audit trail with your name against it.'],

                    ['table',
                        ['Tab', 'Full name', 'What it holds'],
                        [
                            ['RFQ', 'Request for Quotations', 'Quotation requests sent to contractors, and the offers that came back.'],
                            ['NTP', 'Notice to Proceed', 'The notices awarding work, and their five-office approval chain.'],
                            ['SUB', 'Sub-Projects', 'Parts of the project planned, procured and billed on their own.'],
                            ['PER', 'Permits', 'Permits the work needs, with the documents behind them.'],
                            ['VOF', 'Variation Order Form', 'Changes to scope, schedule and cost after award.'],
                            ['QPP', 'Quality Plan & Procedures', 'Quality procedures, inspection points and acceptance criteria.'],
                            ['MTR', 'Materials Test Reports', 'Material submissions, test results and compliance records.'],
                            ['RFP', 'Request for Payment', 'Billings against the work, with retention and payment status.'],
                            ['IOC', 'Input Other Cost', 'Costs outside the main contract line.'],
                            ['ACR', 'Actual Cost Report', 'Paid, committed and actual cost, read together.'],
                            ['PSR', 'Project Status Report', 'Weekly progress, site checklist, issues and action plans.'],
                            ['AT', 'Project Audit Trail', 'Everything that has happened on the project.'],
                            ['TODO', 'Todo List', 'Project tasks with target dates.'],
                        ],
                    ],

                    ['h', 'Who can change what here'],
                    ['p', 'Editing anything in the hub needs edit rights on the project: the administrator and the assistant manager on any project, a project engineer on projects they registered. Everyone else who can see the project — the sign-off offices, department users, division manager users — sees the same tabs read-only.'],
                    ['note', 'Billing status is the one exception. It can also be set by the project manager assigned to the project, even where they did not register it.'],

                    ['h', 'Files in the hub'],
                    ['p', 'Any file attached anywhere in the hub is versioned. Replacing a file keeps the record and adds a version rather than overwriting history, and an earlier version can be put back in front at any time. See Files, Versions and Attachments.'],
                ],
            ],

            'hub-rfq' => [
                'title' => 'RFQ — Request for Quotations',
                'blocks' => [
                    ['path', 'Open a project > RFQ'],
                    ['p', 'An RFQ is a request for a price, sent to one contractor. A project normally carries several — one per contractor invited — and each fills up with the offers actually made against it.'],

                    ['h', 'Sending an RFQ'],
                    ['ol', [
                        'Open the RFQ tab and click Create RFQ Package.',
                        'Type or search the contractor’s name. Suppliers already in master data come up as you type.',
                        'Set the due date — when you need the quotation back.',
                        'Enter the recipient email address, and any additional recipients who should be copied.',
                        'Tick Copy me if you want the outgoing mail in your own inbox.',
                        'Send.',
                    ]],
                    ['p', 'The contractor is emailed a link to the supplier portal, where they fill in their quotation themselves. The RFQ is created with today’s sent date and status Pending, and the dispatch is written to the audit trail.'],
                    ['warn', 'One RFQ per contractor per project. If you try to send a second to the same name, the system will stop you — add another quotation to the existing RFQ instead.'],
                    ['tip', 'Use Resend if the contractor says they never received it. It sends the same link again rather than creating a second RFQ.'],

                    ['h', 'Scope of work'],
                    ['p', 'Set the scope of work on the RFQ and the supplier sees it on their portal page. They quote against it; they cannot rewrite it.'],

                    ['h', 'Quotations'],
                    ['p', 'A contractor may make more than one offer against a single RFQ — a revision after a clarification, say — and exactly one of them is the final one. Offers arrive two ways:'],
                    ['ul', [
                        'Through the portal, filled in by the supplier themselves. These arrive marked as received.',
                        'Entered from the hub by you, for a contractor who sent their price by mail or on paper.',
                    ]],
                    ['ol', [
                        'To add one yourself, click to add a quotation on the RFQ.',
                        'Give it a label if it needs one — "Revision 2", for instance.',
                        'You can copy an existing quotation to start from it. The line items come across; the quotation file stays with the original, since that is the document that offer was quoted on.',
                        'Fill in the line items and the amounts.',
                        'Mark it Received when the offer is confirmed.',
                    ]],
                    ['p', 'When you have decided, mark the winning offer Final. That is the one an NTP is prepared against.'],

                    ['h', 'RFQ statuses'],
                    ['table',
                        ['Status', 'Meaning'],
                        [
                            ['Pending', 'Sent; no offer settled yet.'],
                            ['Submitted', 'A quotation has come back.'],
                            ['Awarded', 'This RFQ won. Set automatically when an NTP against it is issued.'],
                            ['Expired', 'The due date passed with no usable offer.'],
                        ],
                    ],

                    ['h', 'Printing the RFQ'],
                    ['p', 'Print the RFQ as the controlled form PMD-PRJ-FRM-03 from the RFQ row. It opens in a new tab laid out as the paper form, with the line items in place and the signature blocks empty.'],
                ],
            ],

            'supplier-portal' => [
                'title' => 'The Supplier Portal',
                'blocks' => [
                    ['p', 'Contractors have no CPMS accounts. When you send an RFQ, the email carries a link holding a token unique to that RFQ, and holding the link is the whole of the contractor’s authorisation. Everything they can do is scoped to that one RFQ.'],
                    ['note', 'This section describes what the contractor sees, so that you can talk them through it on the telephone.'],

                    ['h', 'What the contractor sees'],
                    ['ul', [
                        'The project number, title, site and owner.',
                        'The RFQ — who it is addressed to, when it was sent, when it is due, and the scope of work you set.',
                        'Their own quotations, and only theirs. Offers your team entered from the hub, and offers from other contractors, are never shown.',
                    ]],

                    ['h', 'How they quote'],
                    ['ol', [
                        'They open the link from the email.',
                        'They fill in the line items — the form offers ten blank rows — with descriptions, quantities and prices.',
                        'They can save a draft and come back to it later using the same link.',
                        'When they are ready they submit. You are notified, and the quotation appears on the RFQ in the hub.',
                    ]],
                    ['p', 'Once a quotation is submitted the contractor can no longer edit it. If they need to revise, they submit a further quotation against the same RFQ.'],

                    ['h', 'If a contractor cannot get in'],
                    ['ul', [
                        'Check the email address on the RFQ is right, then use Resend.',
                        'The link is long — tell them to click it rather than retype it.',
                        'Never forward one contractor’s link to another. It would give them the first contractor’s quotations.',
                    ]],
                    ['warn', 'The portal link is the credential. Treat it like a password: send it only to the contractor it belongs to.'],
                ],
            ],

            'hub-ntp' => [
                'title' => 'NTP — Notice to Proceed',
                'blocks' => [
                    ['path', 'Open a project > NTP'],
                    ['p', 'The Notice to Proceed is what tells a contractor to begin. It is the most heavily controlled document in CPMS: five offices sign it in a fixed order, and only the last signature issues it.'],

                    ['h', 'The approval chain'],
                    ['table',
                        ['Step', 'Office', 'Signs from'],
                        [
                            ['1', 'PMD Assistant Manager', 'For Approval'],
                            ['2', 'PMD Department Manager', 'For Approval'],
                            ['3', 'Division Manager', 'For Approval'],
                            ['4', 'Department User of the owning department', 'NTP Reviews'],
                            ['5', 'Division Manager User of that division', 'NTP Reviews'],
                        ],
                    ],
                    ['p', 'PMD signs first, up through the Division Manager. The notice then goes to the side the work is being done for — the owning department, and last its division manager. The last two steps are held per project rather than by a single office, which is why they are settled from NTP Reviews.'],

                    ['h', 'Preparing an NTP'],
                    ['ol', [
                        'Open the NTP tab and click Prepare NTP.',
                        'Choose the contractor.',
                        'Link the RFQ the price came from. This is what gets awarded when the notice issues.',
                        'Set the baseline start and baseline end. The end must fall after the start.',
                        'Enter the approved cost.',
                        'Submit. The NTP takes its number, is created as Pending Review, its full chain is written out, and the PMD Assistant Manager is notified.',
                    ]],
                    ['warn', 'Only one live NTP per RFQ. If an NTP against that RFQ is already pending review or issued, the system will refuse a second. A rejected NTP, though, may be replaced by a fresh one.'],

                    ['h', 'Following an NTP through the chain'],
                    ['p', 'The NTP row shows the approval timeline: every step in order, who settled it, when, and what remarks they left. You are notified each time it moves, and told which office holds it now.'],

                    ['h', 'What happens when it issues'],
                    ['p', 'The fifth signature does four things at once:'],
                    ['ul', [
                        'The NTP status becomes Issued and the issue date is stamped.',
                        'The linked RFQ is marked Awarded.',
                        'Everyone behind the project’s department is notified.',
                        'The contractor is mailed a link to their copy of the approved notice.',
                    ]],
                    ['note', 'Issuing an NTP does not change the project budget. Project Cost is set by hand on the project, deliberately, so that the budget is a decision rather than a side effect.'],

                    ['h', 'If an NTP is rejected'],
                    ['p', 'The chain stops at the office that rejected it. You are notified with their reason, and the NTP is closed as Rejected. Correct whatever was wrong and prepare a new NTP against the same RFQ — the system allows that once the earlier one is rejected.'],

                    ['h', 'The contractor’s copy'],
                    ['p', 'The link mailed to the contractor is signed and expires on its own. It shows the approved notice with its approval stamps, rendered from the record by the server — never from anything the contractor’s browser sends. Only an issued NTP can be opened this way.'],

                    ['h', 'Printing'],
                    ['p', 'Print the NTP as the controlled form PMD-PRJ-FRM-04 from the NTP row. The printed form carries the approval stamps of the offices that have signed.'],
                ],
            ],

            'hub-subprojects' => [
                'title' => 'SUB — Sub-Projects',
                'blocks' => [
                    ['path', 'Open a project > Sub-Projects'],
                    ['p', 'A sub-project is a part of a project that is planned, procured and billed on its own. It has its own number derived from its parent, its own hub, its own RFQs and NTPs — but it rolls up into its parent’s progress.'],

                    ['h', 'Adding one'],
                    ['ol', [
                        'Open the Sub-Projects tab and click Add Sub-Project.',
                        'Fill in the project form as you would for any project.',
                        'Save. The sub-project takes a number derived from its parent’s.',
                    ]],
                    ['note', 'A sub-project does not need its own approved proposal document — it inherits the one already approved for its parent.'],
                    ['warn', 'The tree may go three levels deep: a project, its sub-projects, and theirs. Beyond that the system will refuse.'],

                    ['h', 'How sub-projects affect the parent'],
                    ['ul', [
                        'The parent’s reported progress is its effective completion, which rolls up its children rather than standing alone.',
                        'Sub-projects are never counted as separate projects on the dashboard — they are counted inside their parent.',
                        'Each sub-project runs its own procurement and billing, so its costs are tracked in its own hub.',
                    ]],
                ],
            ],

            'hub-permits' => [
                'title' => 'PER — Permits',
                'blocks' => [
                    ['path', 'Open a project > Permits'],
                    ['p', 'The permits the work needs, with the documents that prove them. A permit record can carry several files — the application, the approval, the renewal — and each file is versioned.'],
                    ['ol', [
                        'Click Add Permit.',
                        'Give it a label — the permit as you would name it.',
                        'Set the document type.',
                        'Attach the files. At least one is required; each may be up to 20 MB.',
                        'Save.',
                    ]],
                    ['tip', 'The engineer’s technical feedback on the original request lists the permits the work was expected to need. Work through that list here.'],
                ],
            ],

            'hub-vof' => [
                'title' => 'VOF — Variation Order Form',
                'blocks' => [
                    ['path', 'Open a project > Variation Order Form'],
                    ['p', 'A variation order records a change to the work after it has been awarded: a change of scope, of schedule, of cost, or of all three. Each takes its own VO number.'],

                    ['h', 'Raising a variation'],
                    ['kv', [
                        ['Title', 'What the change is. Required.'],
                        ['Description', 'The change in full.'],
                        ['Amount', 'The cost movement. Required.'],
                        ['Duration (days)', 'How much time the change adds.'],
                        ['Requestor', 'Who asked for the change.'],
                        ['Date of Request', 'When they asked.'],
                        ['Priority', 'How urgent it is.'],
                        ['Attachment', 'Supporting document — PDF, image or Word, up to 20 MB.'],
                    ]],
                    ['p', 'Below those, three paired columns record the change itself — original against proposed, with a remark for each:'],
                    ['ul', [
                        'Scope — original, proposed, remark.',
                        'Schedule — original, proposed, remark.',
                        'Cost — original, proposed, remark.',
                    ]],
                    ['tip', 'Fill in the original column even where nothing about it changes. The form is read as a comparison, and a blank original makes the proposal impossible to judge.'],

                    ['h', 'Approving a variation'],
                    ['p', 'A variation is Pending until it is set to Approved or Rejected. Approving stamps the approval date and recalculates the project budget, so an approved variation moves the project’s total cost. Setting it back to Pending clears the approval date.'],
                ],
            ],

            'hub-qpp' => [
                'title' => 'QPP — Quality Plan & Procedures',
                'blocks' => [
                    ['path', 'Open a project > Quality Plan & Procedures'],
                    ['p', 'The quality documents governing the work — procedures, inspection and test plans, acceptance criteria.'],
                    ['ol', [
                        'Click Add Quality Plan.',
                        'Give the document a label and a document type.',
                        'Attach the file, up to 20 MB.',
                        'Save.',
                    ]],
                    ['p', 'Each document is versioned. When a procedure is revised, replace the file on the existing record rather than adding a second one — the revision history then reads as one document over time.'],
                ],
            ],

            'hub-mtr' => [
                'title' => 'MTR — Materials Test Reports',
                'blocks' => [
                    ['path', 'Open a project > Materials Test Reports'],
                    ['p', 'Test results for the materials used, and the compliance record behind them.'],
                    ['ol', [
                        'Click Add Test Report.',
                        'Give it a label and the material type.',
                        'Attach the report, up to 20 MB.',
                        'Save. The test date is recorded as today.',
                    ]],
                ],
            ],

            'hub-rfp' => [
                'title' => 'RFP — Request for Payment',
                'blocks' => [
                    ['path', 'Open a project > Request for Payment'],
                    ['p', 'Billings against the work. Each takes a statement number and may be tied to the NTP it is billed under, so that payment can be read against the award it belongs to.'],

                    ['h', 'Raising a billing'],
                    ['kv', [
                        ['NTP', 'The notice this billing is against. Leave blank for a billing that stands outside one.'],
                        ['Billing Type', 'The kind of billing. Required.'],
                        ['Period From / To', 'The period the billing covers.'],
                        ['Amount', 'The amount billed, before retention. Required.'],
                        ['Progress %', 'How far the work had got in the period.'],
                        ['Summary and Remarks', 'What the billing covers, and anything the approver needs to know.'],
                        ['Recommendation', 'Your recommendation on the billing.'],
                        ['Apply retention', 'Tick to withhold the retention percentage from this billing.'],
                        ['File', 'The billing document, up to 20 MB.'],
                    ]],
                    ['note', 'The retention percentage is set by the administrator in System Settings, but the rate is copied onto the billing when you raise it. Changing the setting later does not restate billings already made.'],

                    ['h', 'Approving a billing'],
                    ['ol', [
                        'Open the billing row.',
                        'Set the status to Approved, or back to Pending.',
                        'Add remarks — they are kept in the billing’s status log.',
                        'Save.',
                    ]],
                    ['p', 'Approving updates what the project records as paid. Every status change is logged with your name, the time and your remarks.'],
                    ['note', 'Billing status is the one hub action open to the assigned project manager as well as to the engineer who registered the project — plus, as always, the administrator and the assistant manager.'],
                ],
            ],

            'hub-ioc' => [
                'title' => 'IOC — Input Other Cost',
                'blocks' => [
                    ['path', 'Open a project > Input Other Cost'],
                    ['p', 'Costs that fall outside the main contract line — materials bought directly, equipment hire, incidental spending. Logging them here is what makes the Actual Cost Report a true picture rather than a contract summary.'],
                    ['ol', [
                        'Click Add Other Cost.',
                        'Describe the cost. Required.',
                        'Give the cost code it is booked against.',
                        'Enter the amount. Required.',
                        'Attach the supporting document if there is one, up to 20 MB.',
                        'Save.',
                    ]],
                ],
            ],

            'hub-acr' => [
                'title' => 'ACR — Actual Cost Report',
                'blocks' => [
                    ['path', 'Open a project > Actual Cost Report'],
                    ['p', 'A read-only view that puts the project’s money in one place: what was budgeted, what has been committed through awarded NTPs and approved variations, what has been billed, what has been paid, and what other costs have been logged.'],
                    ['p', 'Nothing is entered here. The report is built from the RFP, IOC, VOF and NTP records, so it is only as good as what has been logged in them. If a figure looks wrong, correct it in the section it came from.'],
                    ['tip', 'Read the ACR before raising a variation. It tells you how much of the budget is already committed.'],
                ],
            ],

            'hub-psr' => [
                'title' => 'PSR — Project Status Report',
                'blocks' => [
                    ['path', 'Open a project > Project Status Report'],
                    ['p', 'The weekly progress report on a project: how far the work has got, what is in the way, and what is being done about it. The same report can be filed from here or from the Weekly Status page — they write the same record.'],

                    ['h', 'Filing a report'],
                    ['kv', [
                        ['NTP', 'The notice the report covers, where the project has more than one.'],
                        ['Week Code', 'Which week this is. Required — the system suggests the current one.'],
                        ['Completion %', 'How far the work has got, 0 to 100. Required.'],
                        ['Identified Issues', 'What is standing in the way.'],
                        ['Progress Updates', 'What moved this week.'],
                        ['Site checklist', 'Each checklist line marked done, not done, or not applicable, with remarks.'],
                        ['Issues and action plans', 'Each issue paired with the action and the date it is committed to.'],
                        ['File', 'The signed PSR as a PDF, up to 20 MB.'],
                    ]],
                    ['p', 'Only checklist lines and issue rows you actually answer are stored, so an untouched row does not read back as a deliberate blank.'],

                    ['h', 'Importing reports in bulk'],
                    ['ol', [
                        'Download the template from the PSR tab.',
                        'Fill in a row per report.',
                        'Upload the completed workbook.',
                    ]],
                    ['p', 'The completion percentage on the latest report is what drives the project’s reported progress, and through it the completion KPI on the dashboard.'],
                ],
            ],

            'hub-at' => [
                'title' => 'AT — Project Audit Trail',
                'blocks' => [
                    ['path', 'Open a project > Project Audit Trail'],
                    ['p', 'Everything that has happened on the project, newest first: who did it, when, which module it was in, and what changed. Status changes, uploads, approvals, dispatches and deletions all land here.'],
                    ['p', 'The trail is written by the system and cannot be edited by anyone, including the administrator. That is the point of it — when there is a question about who changed a figure or when a notice went out, this is the answer.'],
                    ['tip', 'When a contractor disputes a date, the audit trail usually settles it: the dispatch of the RFQ and the issue of the NTP are both stamped here.'],
                ],
            ],

            'hub-todo' => [
                'title' => 'TODO — Todo List',
                'blocks' => [
                    ['path', 'Open a project > Todo List'],
                    ['p', 'A simple task list for the project — the small things that do not belong in any of the other sections but still have to be done.'],
                    ['ol', [
                        'Click Add Task.',
                        'Name the task and set its target date. Both are required.',
                        'Save.',
                    ]],
                    ['p', 'Tick a task to mark it complete, and untick it if it turns out it was not. Delete a task that is no longer needed. Every change is written to the audit trail.'],
                ],
            ],
        ];
    }
}
