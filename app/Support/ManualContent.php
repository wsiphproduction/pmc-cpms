<?php

namespace App\Support;

use App\Models\User;

/**
 * The written content of the CPMS user manuals.
 *
 * The manual is kept as data rather than as one Blade page per role so that a
 * section written once — how the approval chain runs, say — reads identically
 * in the complete manual and in every role booklet that includes it. A role
 * manual is a named subset of these sections plus its own opening page.
 *
 * Blocks are tagged arrays the manual view knows how to render:
 *   ['h', 'Sub-heading']
 *   ['p', 'A paragraph.']
 *   ['ul', ['bullet', ...]]
 *   ['ol', ['numbered step', ...]]
 *   ['kv', [['Field', 'What to put in it'], ...]]
 *   ['table', ['Col A', 'Col B'], [['a1', 'b1'], ...]]
 *   ['note'|'tip'|'warn', 'Callout text.']
 *   ['path', 'Top bar > Projects > Open a project']
 */
class ManualContent
{
    /** Printed on every cover page and in the page footers. */
    public const VERSION = '1.0';

    /** Document control number, in the style of the PMD paper forms. */
    public const DOC_NO = 'PMD-CPMS-MAN-01';

    /**
     * Every manual the system publishes, keyed by the slug used for its file
     * name and its URL. `role` ties a booklet to the role that receives it;
     * the complete manual has none and is offered to everyone.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manuals(): array
    {
        return [
            'complete' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'Complete Edition — every role, every module',
                'role' => null,
                'audience' => 'Everyone who uses CPMS, and anyone who needs the whole picture of how a request becomes a finished project.',
                'at_a_glance' => [
                    'Covers all eight roles and every module of the system, end to end.',
                    'Use the table of contents to jump to the part of the flow you are in.',
                    'Each role also has a shorter booklet holding only the parts that role uses.',
                ],
                'sections' => [
                    'about', 'getting-started', 'roles', 'dashboard',
                    'requests-overview', 'requests-raise', 'requests-review', 'approvals-portal',
                    'projects-overview', 'projects-register', 'hub-overview',
                    'hub-rfq', 'supplier-portal', 'hub-ntp', 'ntp-reviews', 'hub-subprojects',
                    'hub-permits', 'hub-vof', 'hub-qpp', 'hub-mtr', 'hub-rfp', 'hub-ioc',
                    'hub-acr', 'hub-psr', 'hub-at', 'hub-todo',
                    'weekly-status', 'completion', 'reports',
                    'files', 'notifications', 'master-data', 'users', 'system-settings',
                    'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'department-user' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the Department User',
                'role' => User::ROLE_REQUESTOR,
                'audience' => 'Staff in a requesting department who raise project requests and sign off the Notices to Proceed for work done for their department.',
                'at_a_glance' => [
                    'You raise project requests and attach the drawings, pictures and reports that explain them.',
                    'You answer the project engineer while a request is on Hold, and you may edit it until it is endorsed.',
                    'You give the fourth signature on every NTP for your department, from the NTP Reviews page.',
                    'You see your own requests and your department’s projects — never another department’s.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'requests-raise', 'files',
                    'projects-overview', 'ntp-reviews', 'hub-ntp',
                    'notifications', 'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'project-engineer' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the Project Engineer',
                'role' => User::ROLE_ENGINEER,
                'audience' => 'PMD project engineers who review incoming requests, register projects, run procurement and report progress.',
                'at_a_glance' => [
                    'You hold the first signature on every project request — endorse it, put it on Hold, or reject it.',
                    'You register an approved request as a project and then run it from the Project Hub.',
                    'You raise RFQs, compare quotations, prepare NTPs, and log permits, variations, billings and costs.',
                    'You file the weekly status for every project you handle and generate your accomplishment report.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'requests-review',
                    'projects-overview', 'projects-register', 'hub-overview',
                    'hub-rfq', 'supplier-portal', 'hub-ntp', 'hub-subprojects',
                    'hub-permits', 'hub-vof', 'hub-qpp', 'hub-mtr', 'hub-rfp', 'hub-ioc',
                    'hub-acr', 'hub-psr', 'hub-at', 'hub-todo',
                    'weekly-status', 'completion', 'reports',
                    'files', 'notifications', 'master-data',
                    'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'assistant-manager' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the Assistant Manager (Engineering)',
                'role' => User::ROLE_ASSISTANT_MANAGER,
                'audience' => 'The engineering-side assistant manager who covers for project engineers and can take over any project.',
                'at_a_glance' => [
                    'You can do everything a project engineer can, on any project — not only the ones you registered.',
                    'Use that reach to cover for an engineer who is away, or to correct work already filed.',
                    'You may settle the first step of a request’s approval chain, the same step the engineer holds.',
                    'You are not one of the PMD sign-off offices; the For Approval queue belongs to them.',
                ],
                'sections' => [
                    'about', 'getting-started', 'roles', 'dashboard',
                    'requests-overview', 'requests-review',
                    'projects-overview', 'projects-register', 'hub-overview',
                    'hub-rfq', 'supplier-portal', 'hub-ntp', 'hub-subprojects',
                    'hub-permits', 'hub-vof', 'hub-qpp', 'hub-mtr', 'hub-rfp', 'hub-ioc',
                    'hub-acr', 'hub-psr', 'hub-at', 'hub-todo',
                    'weekly-status', 'completion', 'reports',
                    'files', 'notifications', 'master-data',
                    'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'pmd-assistant-manager' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the PMD Assistant Manager',
                'role' => User::ROLE_PMD_ASST_MANAGER,
                'audience' => 'The PMD Assistant Manager — the second signature on a project request and the first on a Notice to Proceed.',
                'at_a_glance' => [
                    'Everything waiting on you is in For Approval; the badge beside it is your queue size.',
                    'You sign requests after the project engineer has endorsed them.',
                    'You sign NTPs first, before the PMD Department Manager.',
                    'Your view of requests and projects is read-only — you review the work rather than execute it.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'approvals-portal',
                    'projects-overview', 'hub-ntp',
                    'notifications', 'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'pmd-department-manager' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the PMD Department Manager',
                'role' => User::ROLE_PMD_DEPT_MANAGER,
                'audience' => 'The PMD Department Manager — the final signature on a project request and the second on a Notice to Proceed.',
                'at_a_glance' => [
                    'Your signature is what turns a project request into an approved one, ready to be registered.',
                    'You sign NTPs after the PMD Assistant Manager and before the Division Manager.',
                    'Only one active user may hold this role at a time.',
                    'Your view of requests and projects is read-only.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'approvals-portal',
                    'projects-overview', 'hub-ntp',
                    'notifications', 'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'division-manager' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the Division Manager',
                'role' => User::ROLE_DIVISION_MANAGER,
                'audience' => 'The ECS Division Manager — the third signature on a Notice to Proceed.',
                'at_a_glance' => [
                    'You sign NTPs after PMD has signed and before the work goes to the owning department.',
                    'Your name prints as the ECS Division Manager signatory on the controlled PMD forms.',
                    'Only one active user may hold this role at a time.',
                    'Your view of requests and projects is read-only.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'approvals-portal',
                    'projects-overview', 'hub-ntp',
                    'notifications', 'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'division-manager-user' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the Division Manager User',
                'role' => User::ROLE_DIVISION_MANAGER_USER,
                'audience' => 'The manager of the division that owns the work, who gives the last signature on a Notice to Proceed.',
                'at_a_glance' => [
                    'Yours is the fifth and final signature on an NTP — giving it issues the notice and awards the RFQ.',
                    'You work from NTP Reviews, not from the PMD For Approval portal.',
                    'You are tied to a division, and you see the projects of every department inside it.',
                ],
                'sections' => [
                    'about', 'getting-started', 'dashboard',
                    'requests-overview', 'projects-overview',
                    'ntp-reviews', 'hub-ntp',
                    'notifications', 'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],

            'admin' => [
                'title' => 'CPMS User Manual',
                'subtitle' => 'For the System Administrator',
                'role' => User::ROLE_ADMIN,
                'audience' => 'The CPMS administrator, who keeps accounts, master data and system settings in order and can unblock a stalled flow.',
                'at_a_glance' => [
                    'You create every account and assign its one role.',
                    'You may settle any step of any approval chain — that is how a flow stalled by an absent office is unblocked.',
                    'You maintain the master data every form draws its dropdowns from.',
                    'You set the completion KPI, the retention percentage and the names printed as signatories.',
                ],
                'sections' => [
                    'about', 'getting-started', 'roles', 'dashboard',
                    'requests-overview', 'requests-raise', 'requests-review', 'approvals-portal',
                    'projects-overview', 'projects-register', 'hub-overview',
                    'hub-rfq', 'supplier-portal', 'hub-ntp', 'ntp-reviews', 'hub-subprojects',
                    'hub-permits', 'hub-vof', 'hub-qpp', 'hub-mtr', 'hub-rfp', 'hub-ioc',
                    'hub-acr', 'hub-psr', 'hub-at', 'hub-todo',
                    'weekly-status', 'completion', 'reports',
                    'files', 'notifications', 'master-data', 'users', 'system-settings',
                    'account', 'printing', 'troubleshooting', 'glossary',
                ],
            ],
        ];
    }

    /** The manual published to a given role, falling back to the complete one. */
    public static function slugForRole(?string $role): string
    {
        foreach (self::manuals() as $slug => $manual) {
            if ($manual['role'] !== null && $manual['role'] === $role) {
                return $slug;
            }
        }

        return 'complete';
    }

    /** One manual by slug, or null when the slug is not published. */
    public static function manual(string $slug): ?array
    {
        return self::manuals()[$slug] ?? null;
    }

    /**
     * The sections a manual is built from, in the order it lists them.
     *
     * @return array<int, array{id: string, title: string, blocks: array}>
     */
    public static function sectionsFor(string $slug): array
    {
        $manual = self::manual($slug);
        $library = self::sections();

        if ($manual === null) {
            return [];
        }

        $out = [];

        foreach ($manual['sections'] as $id) {
            if (isset($library[$id])) {
                $out[] = ['id' => $id] + $library[$id];
            }
        }

        return $out;
    }

    /**
     * The section library. Every manual draws from this one set, so a
     * procedure is written — and corrected — in exactly one place.
     *
     * @return array<string, array{title: string, blocks: array}>
     */
    public static function sections(): array
    {
        return array_merge(
            ManualSections\Introduction::sections(),
            ManualSections\Requests::sections(),
            ManualSections\Projects::sections(),
            ManualSections\Hub::sections(),
            ManualSections\Reporting::sections(),
            ManualSections\Administration::sections(),
        );
    }
}
