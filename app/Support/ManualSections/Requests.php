<?php

namespace App\Support\ManualSections;

/** Project requests: what they are, raising one, reviewing one, and the PMD queue. */
class Requests
{
    public static function sections(): array
    {
        return [
            'requests-overview' => [
                'title' => 'Project Requests',
                'blocks' => [
                    ['path', 'Top bar > Requests'],
                    ['p', 'A project request is how a department asks PMD for work. It describes what is needed and why, carries the drawings and photographs that explain it, and then climbs a three-office approval chain. Only once every signature is in can the request be registered as a project.'],

                    ['h', 'The approval chain'],
                    ['p', 'Three signatures, always in this order. Every step is written out when the request is raised, so the whole chain is visible from the start — you can always see who has signed, who is being waited on, and who has not been reached yet.'],
                    ['table',
                        ['Step', 'Office', 'What their signature means'],
                        [
                            ['1', 'Project Engineer', 'The work is technically sound and PMD is willing to take it on.'],
                            ['2', 'PMD Assistant Manager', 'PMD endorses it upward.'],
                            ['3', 'PMD Department Manager', 'Final approval. The request becomes a project candidate.'],
                        ],
                    ],
                    ['note', 'Steps settle strictly in order, and only the first unsettled step can be acted on. A rejection at any step stops the chain there — it does not skip to the next office. The Assistant Manager may also settle step 1, covering for the project engineer.'],

                    ['h', 'What the statuses mean'],
                    ['table',
                        ['Status', 'Meaning', 'What happens next'],
                        [
                            ['For Approval', 'Raised, waiting on the project engineer.', 'The engineer endorses, holds or rejects it. The requester may still edit it.'],
                            ['Hold', 'The engineer has commented and needs an answer.', 'The requester replies or edits; the request then returns to the status it held before.'],
                            ['In Approval', 'Endorsed and climbing the PMD chain.', 'PMD signs from the For Approval portal. It can no longer be edited.'],
                            ['Approved', 'Every signature is in.', 'A project engineer registers it as a project.'],
                            ['Rejected', 'Refused at one of the steps.', 'Nothing further. Raise a new request if the work is still needed.'],
                            ['Ongoing', 'Work has started against it.', 'Tracked through the project, not the request.'],
                            ['Completed', 'The work is finished.', 'Closed out.'],
                        ],
                    ],

                    ['h', 'Finding a request'],
                    ['ul', [
                        'The search box matches a request number, a project number or a title.',
                        'The dropdowns filter by job type, job location, cost code and status. Status accepts more than one value at a time.',
                        'While a request is unsettled, the row shows which office it is waiting on.',
                    ]],

                    ['h', 'What is on a request'],
                    ['ul', [
                        'The request itself — title, job type, description, location, funding classification.',
                        'Attachments — pictures, drawings, reports and anything else, each one versioned.',
                        'Comments — the running conversation between the requester and the engineer.',
                        'Technical feedback — the engineer’s structured assessment: disciplines involved, permits needed, priority and remarks.',
                        'The approval timeline — every step, who settled it, when, and any remarks they left.',
                        'The audit trail — everything that has happened to the request.',
                    ]],
                ],
            ],

            'requests-raise' => [
                'title' => 'Raising a Project Request',
                'blocks' => [
                    ['path', 'Top bar > Requests > New Request'],
                    ['p', 'This is the department user’s main task in CPMS. A request that is clear and complete gets endorsed quickly; one that leaves the engineer guessing comes back on Hold.'],

                    ['h', 'Filling in the form'],
                    ['kv', [
                        ['Title', 'A short name for the work, as you would say it out loud. Required.'],
                        ['Job Type', 'Chosen from the list the administrator maintains — civil, electrical, mechanical and so on. Required.'],
                        ['Description', 'What is needed, where, and why. Say what the problem is, not only the solution you have in mind — the engineer may know a better one. Required.'],
                        ['Job Location', 'Chosen from the list. If nothing fits, choose Other and type the location in the box that appears. Required.'],
                        ['OPEX / CAPEX', 'How the work is funded. One of the two must be ticked.'],
                        ['Cost Code', 'Required when you tick OPEX or CAPEX. Chosen from the cost codes the administrator maintains.'],
                        ['For Budgeting', 'Tick this when the work has no budget yet and is being raised so it can be budgeted for.'],
                    ]],
                    ['warn', 'You must classify the funding. A request with neither OPEX nor CAPEX ticked will not save, and OPEX or CAPEX without a cost code will not save either.'],

                    ['h', 'Attaching files'],
                    ['ol', [
                        'Click Add Attachment. A row appears.',
                        'Choose the file.',
                        'Pick its type — Picture, Drawing, Report or Other. This is required for every file you attach.',
                        'Add a short description so the engineer knows what they are looking at.',
                        'Repeat for as many files as you need, then save the request.',
                    ]],
                    ['tip', 'Photographs of the actual condition are the single most useful thing you can attach. They answer questions the engineer would otherwise have to raise as a Hold.'],

                    ['h', 'Submitting'],
                    ['p', 'Click Submit. The request is saved with status For Approval, it is given a request number, and the project engineers are notified that it is waiting on them.'],

                    ['h', 'Editing a request you have raised'],
                    ['p', 'Open the request and click Edit. You can change anything on the form, add attachments, and remove ones you added.'],
                    ['warn', 'You may only edit while the request is For Approval or on Hold. Once the engineer endorses it and it enters In Approval, the content is fixed — PMD is signing what they were shown. If something must change after that, ask the engineer to reject it so you can raise it again.'],

                    ['h', 'When a request goes on Hold'],
                    ['p', 'The project engineer puts a request on Hold by commenting on it. You will get a notification saying so. The comment says what they need.'],
                    ['ol', [
                        'Open the request from the notification or from the Requests list.',
                        'Read the engineer’s comment.',
                        'Answer it — reply in the comment box, and edit the request or add attachments if that is what was asked for.',
                        'The moment you reply or save an edit, the Hold lifts and the request returns to the status it had before.',
                    ]],

                    ['h', 'Deleting a request'],
                    ['p', 'You may delete a request you raised, while it is still For Approval or on Hold. Once it has been endorsed it is part of PMD’s record and can no longer be removed.'],
                ],
            ],

            'requests-review' => [
                'title' => 'Reviewing and Endorsing a Request',
                'blocks' => [
                    ['path', 'Top bar > Requests > open a request'],
                    ['p', 'The project engineer holds the first signature on every request. Yours is the technical judgement — is this work PMD should take on, is it described well enough to price, and what will it involve. The two PMD offices above you are signing on the strength of that judgement.'],

                    ['h', 'Reading the request'],
                    ['ul', [
                        'Start with the description and the attachments. Photographs usually tell you more than the text.',
                        'Check the funding classification and the cost code — a request with the wrong cost code will cause trouble later at billing.',
                        'Check the job location, especially where the requester chose Other and typed it in.',
                        'Look at the audit trail if the request has been round before.',
                    ]],

                    ['h', 'Asking the requester for more'],
                    ['p', 'Write in the comment box and submit. Two things happen at once: the requester is notified, and the request is put on Hold so it is clear that the ball is in their court. It comes off Hold by itself as soon as they reply or edit.'],
                    ['tip', 'Ask for everything you need in one comment. Each round trip costs the requester a day.'],
                    ['note', 'Only a comment from a Project Engineer places a Hold. Comments from an assistant manager or the administrator are recorded and notified, but do not change the status.'],

                    ['h', 'Recording technical feedback'],
                    ['p', 'Technical feedback is the structured assessment that travels with the request up the chain. Where a comment is a conversation, this is your finding.'],
                    ['kv', [
                        ['Disciplines', 'Which trades the work involves — civil, electrical, mechanical.'],
                        ['Permits', 'Which permits the work will need before it can start.'],
                        ['Priority', 'How urgent the work is, from the priority list.'],
                        ['Remarks', 'Your assessment in words. Required.'],
                    ]],
                    ['p', 'Submit it and the requester is notified. You may edit feedback you wrote yourself; you cannot edit another engineer’s.'],

                    ['h', 'Endorsing or rejecting'],
                    ['ol', [
                        'Open the request, or find it in the Requests list.',
                        'Click Approve to endorse it, or Reject to refuse it.',
                        'Type your remarks in the box that appears. On a rejection this is the reason the requester will read, so make it specific.',
                        'Confirm.',
                    ]],
                    ['p', 'On endorsement, the request moves to In Approval and the PMD Assistant Manager is notified that it is in their queue. The requester is told who has signed and who is now holding it.'],
                    ['p', 'On rejection, the request is closed as Rejected. The requester is told, with your reason. Anyone who had already signed is told too, so they know the chain died downstream of them.'],
                    ['warn', 'Endorsement is a one-way door. Once you sign, the request belongs to PMD and you can no longer edit the decision from this screen — it is settled from the For Approval portal by the offices above you.'],

                    ['h', 'Other status changes'],
                    ['p', 'From the request page you can also move a request to Ongoing or Completed as work progresses against it, put it on Hold directly, or resume it from Hold. Each change is notified to the requester and written to the audit trail.'],
                ],
            ],

            'approvals-portal' => [
                'title' => 'The For Approval Portal',
                'blocks' => [
                    ['path', 'Top bar > For Approval'],
                    ['p', 'This is the working page for the three PMD and division sign-off offices. It lists exactly what the chain is waiting on you for — nothing else. The badge on the menu item is the size of that queue.'],
                    ['note', 'The portal shows only records whose current step is yours. A request sitting with the office below you, or one already past you, does not appear. The administrator can open the portal too, but holds no queue of their own.'],

                    ['h', 'What is in the queue'],
                    ['ul', [
                        'Project requests awaiting your step of the request chain.',
                        'Notices to Proceed awaiting your step of the NTP chain.',
                    ]],
                    ['p', 'Each row shows the record’s number, its title or contractor, the project it belongs to, who prepared it and when it reached you.'],

                    ['h', 'Approving'],
                    ['ol', [
                        'Open For Approval.',
                        'Read the record. Click through to the request or the project hub if you need the detail behind it — your view there is read-only, which is as intended.',
                        'Click Approve on the row.',
                        'Add remarks. They are stored against your step and shown on the approval timeline for everyone who looks at the record afterwards.',
                        'Confirm.',
                    ]],
                    ['p', 'The record moves to the next office, who are notified. If yours was the last signature, the record completes — a request becomes Approved and the engineers are told it is ready to register; an NTP is issued.'],

                    ['h', 'Rejecting'],
                    ['ol', [
                        'Click Reject on the row.',
                        'Type the reason. Do not leave it blank — it is the only thing the preparer has to work from.',
                        'Confirm.',
                    ]],
                    ['p', 'The chain stops. The requester or the preparing engineer is notified with your reason, and so is every office that had already signed.'],

                    ['h', 'What you may not do here'],
                    ['ul', [
                        'You cannot edit the content of a request or a project. The sign-off offices review; they do not execute.',
                        'You cannot sign out of turn. A step below yours must settle before yours becomes actionable.',
                        'You cannot re-open a record that has already been settled.',
                    ]],
                    ['tip', 'If a chain is stuck because an office is away, the administrator can settle that step on their behalf. Ask them rather than working around the chain.'],
                ],
            ],
        ];
    }
}
