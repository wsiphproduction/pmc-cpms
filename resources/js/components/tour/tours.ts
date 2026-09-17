// The guided tours, one per page, for the department-side roles. Each step
// points at a `data-tour` anchor on the page; a step whose anchor is not on
// the page when the tour starts (a card that only shows when there is
// something to review, a button only some records get) is skipped, so the
// text below is written to stand on its own either way. A step with no target
// is shown centred, which is how each tour opens and closes.

export type TourRole = 'requestor' | 'division_manager_user';

export const TOUR_ROLES: TourRole[] = ['requestor', 'division_manager_user'];

export interface TourStep {
    /** The data-tour anchor to spotlight; none means a centred step. */
    target?: string;
    title: string;
    body: string;
    /** Only shown to these roles; every role otherwise. */
    roles?: TourRole[];
}

export interface Tour {
    /** The page as the guide panel names it. */
    name: string;
    /** One line under the name in the guide panel. */
    summary: string;
    steps: TourStep[];
}

// The bits of the top bar every tour can point at. Spelt out once here and
// spliced into the dashboard tour, where the whole system is introduced.
const NAVIGATION: TourStep[] = [
    {
        target: 'nav',
        title: 'Main navigation',
        body: 'Every part of the system you can reach is on this bar. The highlighted item is the page you are on now.',
    },
    {
        target: 'nav-requests',
        title: 'Requests',
        body: 'The registry of project requests from your department: raise a new one, follow its approval, and read the comments and feedback from PMD.',
        roles: ['requestor'],
    },
    {
        target: 'nav-requests',
        title: 'Requests',
        body: 'The registry of project requests across your division: raise a new one, follow its approval, and read the comments and feedback from PMD.',
        roles: ['division_manager_user'],
    },
    {
        target: 'nav-projects',
        title: 'Projects',
        body: 'Once a request is approved and registered by an engineer it becomes a project. This list shows their progress, payments and target dates.',
    },
    {
        target: 'nav-ntp-reviews',
        title: 'NTP Reviews',
        body: 'Notices to Proceed that need your signature. The red badge counts the ones waiting on you right now.',
    },
    {
        target: 'nav-reports',
        title: 'Reports',
        body: 'Excel exports of project records registered under your name.',
    },
    {
        target: 'nav-manual',
        title: 'User Manual',
        body: 'The full manual for your role, readable inside the system or downloaded as a PDF.',
    },
    {
        target: 'notifications',
        title: 'Notifications',
        body: 'Approvals, comments and status changes on your requests land here. Click one to jump straight to the record it is about.',
    },
    {
        target: 'account-menu',
        title: 'Your account',
        body: 'Update your name, email address or password from My Account, and sign out from here.',
    },
];

const FLOATER_STEP: TourStep = {
    target: 'tour-floater',
    title: 'The guide button',
    body: 'Need this again? The Guide button sits here on every page, with a quick summary of the page and a button to replay its tour.',
};

export const TOURS: Record<string, Tour> = {
    'dashboard': {
        name: 'Dashboard',
        summary: 'Your home page: shortcuts, counters and the latest activity.',
        steps: [
            {
                title: 'Welcome to CPMS',
                body: 'This short tour walks you through the parts of the system you will use. Each page has a tour of its own; this one starts with the navigation and then your Dashboard.',
            },
            ...NAVIGATION,
            {
                target: 'dash-new-request',
                title: 'New Request',
                body: 'The quickest way to raise a project request. It opens the request form directly.',
            },
            {
                target: 'dash-stats',
                title: 'Your counters',
                body: 'Active projects, unread comments on your requests, and the number of requests you have submitted. Each card opens the matching list.',
            },
            {
                target: 'dash-ntp-review',
                title: 'NTPs waiting on you',
                body: 'When a Notice to Proceed reaches your step in the sign-off chain it is listed here. Review all opens the NTP Reviews page.',
            },
            {
                target: 'dash-notifications',
                title: 'Recent notifications',
                body: 'Your latest notifications, with unread ones in bold. Mark all read clears the badge in the top bar.',
            },
            {
                target: 'dash-projects',
                title: 'Projects at a glance',
                body: 'The projects raised from your requests, with their status, health and completion percentage.',
            },
            {
                target: 'dash-requests',
                title: 'Recent requests',
                body: 'Your most recent requests and where each one stands. View All opens the full registry.',
            },
            {
                target: 'dash-audit',
                title: 'Audit trail',
                body: 'A log of recent actions on your records, so you can see who did what and when.',
            },
            FLOATER_STEP,
            {
                title: 'That is the Dashboard',
                body: 'Open Requests, Projects or NTP Reviews from the top bar and press Guide there for that page\'s tour.',
            },
        ],
    },

    'requests/index': {
        name: 'Project Requests',
        summary: 'The registry of requests: search, filter, raise and follow them.',
        steps: [
            {
                title: 'Project Requests Registry',
                body: 'Every request you can see, newest first, with its status and the project it became once approved.',
            },
            {
                target: 'requests-search',
                title: 'Search',
                body: 'Type a request number, title or project number and press Enter.',
            },
            {
                target: 'requests-advanced-search',
                title: 'Advanced Search',
                body: 'Narrow the list by job type, job location, cost code or one or more statuses.',
            },
            {
                target: 'requests-add',
                title: 'Add Request',
                body: 'Opens the request form. Attachments are optional; funding classification and a cost code (for OPEX) are required.',
            },
            {
                target: 'requests-table',
                title: 'The registry',
                body: 'Request ID, title, requester, date, status and project number. Hover a row to pick it out; the buttons on the right act on it.',
            },
            {
                target: 'requests-status',
                title: 'Status',
                body: 'For Approval means the engineer has not acted yet; In Approval means it is moving through PMD, and the line under the badge names who has it. Hold means PMD has a question for you — read the comments.',
            },
            {
                target: 'requests-actions',
                title: 'Row actions',
                body: 'View opens the full record. Edit and Delete appear only while the request is still yours to change. The speech bubble opens the comment thread, with a red count for unread comments.',
            },
            {
                target: 'requests-pagination',
                title: 'Pages',
                body: 'Long lists are split into pages; the count on the left says which rows you are looking at.',
            },
            FLOATER_STEP,
            {
                title: 'That is the registry',
                body: 'Open a request with the eye icon to see its approval chain, attachments, technical feedback and comments.',
            },
        ],
    },

    'requests/create': {
        name: 'New Request',
        summary: 'The form for raising a project request.',
        steps: [
            {
                title: 'Project Request Form',
                body: 'Fill in the request in three parts: general information, financials, and any supporting files. Fields marked * are required.',
            },
            {
                target: 'request-title',
                title: 'Project title',
                body: 'A short name for the work. It is what everyone will see in lists, notifications and the project record later on.',
            },
            {
                target: 'request-job-type',
                title: 'Job type',
                body: 'Start typing to search the list — construction, design, installation and so on — or pick from the dropdown.',
            },
            {
                target: 'request-job-location',
                title: 'Job location',
                body: 'Where the work is. Choose Other for somewhere not on the list and a box appears to spell it out.',
            },
            {
                target: 'request-description',
                title: 'Description',
                body: 'The scope of works in detail. The engineer reviews the request from this, so the more precise it is the fewer questions come back.',
            },
            {
                target: 'request-funding',
                title: 'Funding classification',
                body: 'Tick at least one of OPEX, CAPEX or For Budgeting. Ticking OPEX adds a Cost Code field, which is then required.',
            },
            {
                target: 'request-attachments',
                title: 'Supporting documents',
                body: 'Pictures, draft drawings, reports and other files, each with a short description. Use the + button to add another file of the same kind. All of this is optional.',
            },
            {
                target: 'request-submit',
                title: 'Submit',
                body: 'Sends the request to PMD. You are taken back to the registry, where it shows as For Approval.',
            },
            {
                title: 'That is the form',
                body: 'You can still edit the request afterwards, until PMD starts acting on it.',
            },
        ],
    },

    'requests/edit': {
        name: 'Edit Request',
        summary: 'Change a request that is still yours to change.',
        steps: [
            {
                title: 'Edit Project Request',
                body: 'The same form as when the request was raised, with the current values filled in. Change what you need and save.',
            },
            {
                target: 'request-title',
                title: 'Title and details',
                body: 'Title, job type, job location and description can all be changed here.',
            },
            {
                target: 'request-funding',
                title: 'Funding and cost code',
                body: 'At least one funding classification stays required, and OPEX still needs a cost code.',
            },
            {
                target: 'request-current-attachments',
                title: 'Current attachments',
                body: 'The files already on the request. Remove one with its delete button — it is only taken off once you save — or replace its file to keep the earlier version on record.',
            },
            {
                target: 'request-attachments',
                title: 'New attachments',
                body: 'Add more files here, grouped the same way as on the request form.',
            },
            {
                target: 'request-save',
                title: 'Save Changes',
                body: 'Writes your changes and returns you to the registry.',
            },
        ],
    },

    'requests/show': {
        name: 'Request Record',
        summary: 'One request in full: chain, details, files, feedback and comments.',
        steps: [
            {
                title: 'Viewing a request',
                body: 'Everything about one request on a single page, from its approval chain to the comments underneath.',
            },
            {
                target: 'request-actions',
                title: 'Actions',
                body: 'Print gives a paper copy. Edit Request appears while the request is still yours to change.',
            },
            {
                target: 'request-header',
                title: 'Title and status',
                body: 'The request title, who submitted it and when, with the current status badge on the right.',
            },
            {
                target: 'request-approval-chain',
                title: 'Approval chain',
                body: 'Each signature the request needs, in order, with who has signed and who it is waiting on. Remarks left by an approver show underneath.',
            },
            {
                target: 'request-details',
                title: 'Details',
                body: 'Job type, location, cost code, funding and the full description. Once a project is registered, its number here links to the project record.',
            },
            {
                target: 'request-attachments',
                title: 'Attached files',
                body: 'Your uploads grouped by kind. Click a file to open it.',
            },
            {
                target: 'request-cancel',
                title: 'Cancel Request',
                body: 'Withdraws the request entirely. It cannot be undone, so you are asked to confirm.',
            },
            {
                target: 'request-feedback',
                title: 'Technical feedback',
                body: 'The engineer\'s assessment: priority, disciplines involved and permits needed, with remarks.',
            },
            {
                target: 'request-comments',
                title: 'Comments',
                body: 'The conversation on this request. A comment from PMD can put the request on Hold until it is answered, so check here when the status changes.',
            },
            FLOATER_STEP,
        ],
    },

    'project-management/index': {
        name: 'Projects',
        summary: 'Every project raised from your requests, with progress and payments.',
        steps: [
            {
                title: 'Project Management',
                body: 'The projects you can see, with who is handling each, how far along it is and when it is due.',
            },
            {
                target: 'projects-search',
                title: 'Search',
                body: 'Type a project number or title and press Enter.',
            },
            {
                target: 'projects-advanced-filter',
                title: 'Advanced Filter',
                body: 'Filter by engineer, site, department, class, priority, work force and more.',
            },
            {
                target: 'projects-status-filter',
                title: 'Status filter',
                body: 'The filter people reach for most, so it has its own dropdown: ongoing, on hold, completed and so on.',
            },
            {
                target: 'projects-show-subs',
                title: 'Sub-projects',
                body: 'A large project may be split into sub-projects, each with its own NTP. Tick this to list them under their parent.',
            },
            {
                target: 'projects-table',
                title: 'The list',
                body: 'Project number, title, engineer, completion, payment status, department owner, target completion and status. Click a project number to open it.',
            },
            {
                target: 'projects-progress',
                title: 'Completion and payment',
                body: 'Completion comes from the engineer\'s latest weekly report. Payment shows how much of the project cost has been paid so far.',
            },
            {
                target: 'projects-actions',
                title: 'View',
                body: 'Opens the project record with its timeline, budget, details and procurement documents.',
            },
            {
                target: 'projects-pagination',
                title: 'Pages',
                body: 'Long lists are split into pages.',
            },
            FLOATER_STEP,
        ],
    },

    'project-management/show': {
        name: 'Project Record',
        summary: 'One project in full: timeline, budget, details and its RFQs and NTPs.',
        steps: [
            {
                title: 'Viewing a project',
                body: 'The project record is read-only for you: it shows what the engineer has registered and where the work stands.',
            },
            {
                target: 'project-actions',
                title: 'Export PDF',
                body: 'Prints the record as it appears here.',
            },
            {
                target: 'project-parent-link',
                title: 'Sub-project',
                body: 'This is a sub-project. Procurement lives on the parent, which this banner links back to.',
            },
            {
                target: 'project-header',
                title: 'Title, site and engineer',
                body: 'The project title, whether it is a major or minor project, its site and the engineer handling it.',
            },
            {
                target: 'project-status',
                title: 'Lifecycle status',
                body: 'Where the project is in its life: from RFQ and proposal review, through NTP processing, to ongoing, completed and closed.',
            },
            {
                target: 'project-analytics',
                title: 'Timeline, budget, completion and health',
                body: 'Days remaining to the deadline, how much of the cost has been paid, physical completion from the latest weekly report, and whether the project is on time.',
            },
            {
                target: 'project-details',
                title: 'Details',
                body: 'Asset ID, cost code, project cost, the originating request (WR No.), priority, department owner and the classification the engineer set. Click the WR number to open the request.',
            },
            {
                target: 'project-procurement',
                title: 'RFQs and NTPs',
                body: 'The requests for quotation sent to contractors and the Notices to Proceed issued for this project, read-only. Switch between the two tabs.',
            },
            FLOATER_STEP,
        ],
    },

    'ntp-reviews/index': {
        name: 'NTP Reviews',
        summary: 'Notices to Proceed waiting for your signature, and their history.',
        steps: [
            {
                title: 'NTP Reviews',
                body: 'A Notice to Proceed is PMD\'s go-ahead to a contractor. After PMD\'s three signatures it comes to you, the department user, to sign; the division manager user then issues it.',
                roles: ['requestor'],
            },
            {
                title: 'NTP Reviews',
                body: 'A Notice to Proceed is PMD\'s go-ahead to a contractor. After PMD\'s three signatures and the department user\'s, it comes to you to issue — yours is the final signature.',
                roles: ['division_manager_user'],
            },
            {
                target: 'ntp-tabs',
                title: 'Queues',
                body: 'For Review holds the NTPs still awaiting a decision; Issued and Rejected are the history. The page opens on For Review whenever something is waiting.',
            },
            {
                target: 'ntp-card',
                title: 'An NTP',
                body: 'NTP number, status, the project it belongs to and a one-line summary. Click the heading to fold or unfold the details.',
            },
            {
                target: 'ntp-card-actions',
                title: 'Approve or reject',
                body: 'When the NTP is on your step, Approve and Reject appear here. Rejecting asks for remarks so the engineer knows what to fix. Otherwise, Show details unfolds the record.',
            },
            {
                target: 'ntp-approval-chain',
                title: 'Approval chain',
                body: 'Who has signed, who is next, and when. Your step is highlighted when it is your turn.',
            },
            {
                target: 'ntp-card-details',
                title: 'The details',
                body: 'Contractor, approved cost, baseline dates, scope of work and the itemised quotation the NTP is based on. Read these before signing.',
            },
            FLOATER_STEP,
        ],
    },

    'reports/index': {
        name: 'Reports',
        summary: 'Excel export of project records registered under your name.',
        steps: [
            {
                title: 'Reports',
                body: 'An Excel workbook of the projects registered under your name and the weekly progress reports filed against them.',
            },
            {
                target: 'reports-summary',
                title: 'What the export contains',
                body: 'How many projects and weekly reports would go into the workbook right now.',
            },
            {
                target: 'reports-period',
                title: 'Period',
                body: 'Optionally limit the weekly reports to a date range. The project list itself is always complete.',
            },
            {
                target: 'reports-download',
                title: 'Download Excel',
                body: 'Builds the workbook and downloads it. The button is greyed out when there is nothing to export or the dates are the wrong way round.',
            },
            FLOATER_STEP,
        ],
    },

    'manuals/index': {
        name: 'User Manual',
        summary: 'The manual for your role, readable here or downloaded.',
        steps: [
            {
                title: 'User Manual',
                body: 'The written manual, in the booklet for your role and the complete edition covering every role.',
            },
            {
                target: 'manual-list',
                title: 'Booklets',
                body: 'Pick which edition to read. The one marked For your role covers exactly what you can do.',
            },
            {
                target: 'manual-actions',
                title: 'Open or download',
                body: 'Open the PDF in a new tab, or download it to keep.',
            },
            {
                target: 'manual-reader',
                title: 'The reader',
                body: 'The manual itself, readable without leaving the system.',
            },
            FLOATER_STEP,
        ],
    },

    'account/index': {
        name: 'My Account',
        summary: 'Your name, email address and password.',
        steps: [
            {
                title: 'My Account',
                body: 'Your own details. Changes are confirmed to your email address.',
            },
            {
                target: 'account-details',
                title: 'Account details',
                body: 'Your name and email address. Notifications go to this address, so keep it current.',
            },
            {
                target: 'account-password',
                title: 'Change password',
                body: 'Enter your current password and the new one twice.',
            },
            FLOATER_STEP,
        ],
    },
};

/** The steps of a page's tour a given role gets, in order. */
export function stepsFor(component: string, role: string | null | undefined): TourStep[] {
    const tour = TOURS[component];
    if (!tour) return [];

    return tour.steps.filter(step => !step.roles || step.roles.includes(role as TourRole));
}
