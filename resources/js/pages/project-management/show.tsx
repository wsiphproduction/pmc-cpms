import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { HubProject } from './hub/Common';
import AcrHub from './hub/AcrHub';
import AuditTrailHub from './hub/AuditTrailHub';
import IocHub from './hub/IocHub';
import MtrHub from './hub/MtrHub';
import NtpHub from './hub/NtpHub';
import PermitsHub from './hub/PermitsHub';
import PsrHub from './hub/PsrHub';
import QppHub from './hub/QppHub';
import RfpHub from './hub/RfpHub';
import RfqHub from './hub/RfqHub';
import TodoHub from './hub/TodoHub';
import VofHub from './hub/VofHub';

// ── Types ──────────────────────────────────────────────────────────────────
interface StatusLog {
    id: number;
    date: string;
    time: string;
    user: string;
    status: string;
    status_key: string;
    remarks: string;
}

interface TechnicalPlan {
    civil: boolean;
    electrical: boolean;
    mechanical: boolean;
}

interface Project {
    id: number;
    project_no: string;
    title: string;
    site: string;
    project_manager: string;
    encoded_by: string;
    encoded_at: string;
    status: string;
    status_key: string;
    deadline: string;
    days_elapsed: number;
    days_remaining: number;
    budget_total: number;
    budget_paid: number;
    completion_percent: number;
    project_health: 'On-Time' | 'Delayed' | 'Advanced';
    // Details
    asset_id: string;
    cost_code: string;
    wr_no: string;
    wr_date: string;
    priority: string;
    dept_owner: string;
    owner_email: string;
    cls: string;
    category: string;
    service_type: string;
    work_force: string;
    jip: string;
    structure_type: string;
    technical_plans: TechnicalPlan;
    admin_notes: string;
    status_logs: StatusLog[];
    project_type: 'major' | 'minor';
    proposal_document_url: string | null;
}

interface Props {
    project: Project;
    active_section?: string;
    hub_data?: Record<string, any>;
}

// ── Status Meta ────────────────────────────────────────────────────────────
const STATUS_META: Record<string, { bg: string; color: string; border: string; text: string }> = {
    PLANNING:        { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1', text: 'For Planning' },
    RFQ_SUBMITTED:   { bg: '#fefce8', color: '#854d0e', border: '#fef08a', text: 'RFQ/RFP Submitted' },
    PROPOSAL_REVIEW: { bg: '#fefce8', color: '#854d0e', border: '#fef08a', text: 'Proposal Under Review' },
    DESIGN_REVIEW:   { bg: '#fefce8', color: '#854d0e', border: '#fef08a', text: 'Detailed Design Under Review' },
    EXEC_ENDORSED:   { bg: '#f0f9ff', color: '#0369a1', border: '#bae6fd', text: 'Endorsed for Executive Approval' },
    NTP_PROCESSING:  { bg: '#f0f9ff', color: '#0369a1', border: '#bae6fd', text: 'NTP & Contract Processing' },
    SCHEDULING:      { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0', text: 'For Scheduling' },
    ONGOING:         { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0', text: 'Ongoing' },
    ON_HOLD:         { bg: '#fff7ed', color: '#9a3412', border: '#ffedd5', text: 'On Hold' },
    COMPLETED:       { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0', text: 'Completed' },
    CLOSED:          { bg: '#f5f3ff', color: '#5b21b6', border: '#ddd6fe', text: 'Closed' },
    CANCELED:        { bg: '#fef2f2', color: '#991b1b', border: '#fecaca', text: 'Canceled' },
};

const STATUS_HINTS: Record<string, string> = {
    PLANNING:        'Project request received and logged. Awaiting field verification, scoping, requirements gathering, or initial PM review.',
    RFQ_SUBMITTED:   'PM Engineer submitted RFQ/RFP to contractor. Waiting for contractor quotation/proposal.',
    PROPOSAL_REVIEW: 'Contractor submitted proposal. PM Engineer evaluating cost and scope.',
    DESIGN_REVIEW:   'Contractor submitted detailed plan. PM Engineer evaluating for revision or endorsement.',
    EXEC_ENDORSED:   'PMD review done. Submitted to ECS Division/Executive for final approval.',
    NTP_PROCESSING:  'Proposal approved. Awaiting completion of signatures.',
    SCHEDULING:      'Docs approved. Project cleared to start mobilization.',
    ONGOING:         'Works actively being executed. Monitoring & QA/QC in progress.',
    ON_HOLD:         'Project paused due to issues/constraints. Awaiting resolution.',
    COMPLETED:       'Construction finished. Processing billing and documentation.',
    CLOSED:          'Works and documentation finalized. Project officially closed.',
    CANCELED:        'Project has been canceled and nullified.',
};

const OPS_MENU = [
    { key: 'rfq', label: 'Request for Quotations', short: 'RFQ', action: 'Create RFQ Package', summary: 'Prepare quotation requests, contractor scopes, and bid comparison notes.' },
    { key: 'ntp', label: 'Notice to Proceed', short: 'NTP', action: 'Prepare NTP', summary: 'Track approval readiness, signatures, and notice issuance details.' },
    { key: 'permits', label: 'Permits', short: 'PER', action: 'Add Permit', summary: 'Monitor required permits, filing dates, and release status.' },
    { key: 'vof', label: 'Variation Order Form', short: 'VOF', action: 'Create Variation', summary: 'Log scope changes, cost movement, and approval remarks.' },
    { key: 'qpp', label: 'Quality Plan & Procedures', short: 'QPP', action: 'Add Quality Plan', summary: 'Maintain quality procedures, inspection points, and acceptance criteria.' },
    { key: 'mtr', label: 'Materials Test Reports', short: 'MTR', action: 'Add Test Report', summary: 'Track material submissions, test results, and compliance records.' },
    { key: 'rfp', label: 'Request for Payment', short: 'RFP', action: 'Create Payment Request', summary: 'Prepare payment requests, billing references, and payable amounts.' },
    { key: 'ioc', label: 'Input Other Cost', short: 'IOC', action: 'Add Other Cost', summary: 'Capture miscellaneous project costs outside the main budget line.' },
    { key: 'acr', label: 'Actual Cost Report', short: 'ACR', action: 'Generate Cost Report', summary: 'Review paid, committed, and actual project cost movement.' },
    { key: 'psr', label: 'Project Status Report', short: 'PSR', action: 'Create Status Report', summary: 'Summarize progress, blockers, photos, milestones, and next actions.' },
    { key: 'at',   label: 'Project Audit Trail', short: 'AT',  action: 'View Audit Trail',   summary: 'Review project events, status changes, and user activity history.' },
    { key: 'todo', label: 'Todo List',           short: 'TODO', action: 'Add Task',           summary: 'Track project tasks with target dates and completion status.' },
];

function renderHubSection(
    section: string,
    hubProject: import('./hub/Common').HubProject,
    hubData: Record<string, any>,
) {
    switch (section) {
        case 'rfq':     return <RfqHub     project={hubProject} rfqs={hubData.rfqs ?? []} />;
        case 'ntp':     return <NtpHub     project={hubProject} ntps={hubData.ntps ?? []} />;
        case 'permits': return <PermitsHub project={hubProject} permits={hubData.permits ?? []} />;
        case 'vof':     return <VofHub     project={hubProject} vofs={hubData.vofs ?? []} />;
        case 'qpp':     return <QppHub     project={hubProject} qpps={hubData.qpps ?? []} />;
        case 'mtr':     return <MtrHub     project={hubProject} mtrs={hubData.mtrs ?? []} />;
        case 'rfp':     return <RfpHub     project={hubProject} billings={hubData.billings ?? []} ntps={hubData.ntps ?? []} />;
        case 'ioc':     return <IocHub     project={hubProject} iocs={hubData.iocs ?? []} costCodes={hubData.cost_codes ?? []} />;
        case 'acr':     return <AcrHub     project={hubProject} iocs={hubData.iocs ?? []} />;
        case 'psr':     return <PsrHub     project={hubProject} reports={hubData.reports ?? []} />;
        case 'at':      return <AuditTrailHub project={hubProject} logs={hubData.logs ?? []} />;
        case 'todo':    return <TodoHub     project={hubProject} tasks={hubData.tasks ?? []} />;
        default:        return <RfqHub     project={hubProject} rfqs={[]} />;
    }
}

// ── Mini Doughnut (SVG-based, no Chart.js dep) ─────────────────────────────
function DonutChart({ percent, color, size = 120 }: { percent: number; color: string; size?: number }) {
    const r = 42;
    const cx = 60;
    const cy = 60;
    const circ = 2 * Math.PI * r;
    const dash = (percent / 100) * circ;
    return (
        <svg viewBox="0 0 120 120" width={size} height={size}>
            <circle cx={cx} cy={cy} r={r} fill="none" stroke="#e2e8f0" strokeWidth="14" />
            <circle cx={cx} cy={cy} r={r} fill="none" stroke={color} strokeWidth="14"
                strokeDasharray={`${dash} ${circ}`}
                strokeDashoffset={circ / 4}
                strokeLinecap="round"
                style={{ transition: 'stroke-dasharray 0.6s ease' }}
            />
        </svg>
    );
}

function SemiDonut({ elapsed, remaining, color }: { elapsed: number; remaining: number; color: string }) {
    const total = elapsed + remaining;
    const pct = total > 0 ? (remaining / total) * 100 : 0;
    const r = 42;
    const circ = Math.PI * r; // half circle
    const dash = (pct / 100) * circ;
    return (
        <svg viewBox="0 0 120 70" width={120} height={70}>
            <path d="M 10 60 A 50 50 0 0 1 110 60" fill="none" stroke="#e2e8f0" strokeWidth="14" />
            <path d="M 10 60 A 50 50 0 0 1 110 60" fill="none" stroke={color} strokeWidth="14"
                strokeDasharray={`${dash} ${circ}`}
                strokeLinecap="round"
                style={{ transition: 'stroke-dasharray 0.6s ease' }}
            />
        </svg>
    );
}

// ── Status Badge ───────────────────────────────────────────────────────────
function StatusBadge({ statusKey }: { statusKey: string }) {
    const s = STATUS_META[statusKey] ?? { bg: '#f3f4f6', color: '#374151', border: '#e5e7eb', text: statusKey };
    return (
        <span style={{ padding: '4px 12px', borderRadius: '50px', fontWeight: 700, fontSize: '11px', background: s.bg, color: s.color, border: `1px solid ${s.border}`, whiteSpace: 'nowrap' }}>
            {s.text}
        </span>
    );
}

// ── Info Field ─────────────────────────────────────────────────────────────
function InfoField({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div style={{ marginBottom: '20px' }}>
            <div style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.8px', marginBottom: '4px' }}>{label}</div>
            <div style={{ fontSize: '14px', fontWeight: 500, color: '#1e293b' }}>{children}</div>
        </div>
    );
}

// ── Plan Status Card ───────────────────────────────────────────────────────
function PlanCard({ label, required }: { label: string; required: boolean }) {
    return (
        <div style={{
            padding: '10px 14px', borderRadius: '8px', display: 'flex', alignItems: 'center', gap: '10px',
            border: `1px solid ${required ? '#93c5fd' : '#e2e8f0'}`,
            background: required ? '#eff6ff' : '#f8fafc',
        }}>
            {required ? (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            ) : (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            )}
            <div>
                <div style={{ fontSize: '12px', fontWeight: 700, color: required ? '#1e40af' : '#94a3b8' }}>{label}</div>
                <div style={{ fontSize: '11px', color: '#94a3b8' }}>{required ? 'Required' : 'Not Needed'}</div>
            </div>
        </div>
    );
}

// ── Health Badge ───────────────────────────────────────────────────────────
function HealthBadge({ health }: { health: Project['project_health'] }) {
    const map = {
        'On-Time': { bg: '#dcfce7', color: '#15803d', border: '#bbf7d0', icon: '✓' },
        'Delayed':  { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca', icon: '!' },
        'Advanced': { bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe', icon: '↑' },
    };
    const s = map[health];
    return (
        <span style={{ padding: '8px 18px', borderRadius: '50px', fontWeight: 800, fontSize: '13px', display: 'inline-flex', alignItems: 'center', gap: '6px', background: s.bg, color: s.color, border: `1px solid ${s.border}` }}>
            {s.icon} {health.toUpperCase()}
        </span>
    );
}

// ── Status Update Modal ────────────────────────────────────────────────────
function StatusUpdateModal({
    project, logs, onClose, onUpdate,
}: {
    project: Project;
    logs: StatusLog[];
    onClose: () => void;
    onUpdate: (key: string, text: string, remarks: string) => void;
}) {
    const [selectedKey, setSelectedKey] = useState('');
    const [remarks, setRemarks]         = useState('');
    const [posting, setPosting]         = useState(false);

    const selectedMeta  = STATUS_META[selectedKey];
    const selectedHint  = STATUS_HINTS[selectedKey];

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedKey) return;
        setPosting(true);
        try {
            await router.patch(route('projects.update-status', project.id), {
                status_key: selectedKey,
                remarks: remarks || 'No remarks.',
            });
            onUpdate(selectedKey, selectedMeta?.text ?? selectedKey, remarks || 'No remarks.');
            setRemarks('');
            setSelectedKey('');
        } catch (err) {
            console.error(err);
        } finally {
            setPosting(false);
        }
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 300, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)' }} />
            <div style={{
                position: 'relative', background: '#fff', borderRadius: '12px', zIndex: 301,
                width: '100%', maxWidth: '720px', maxHeight: '90vh',
                display: 'flex', flexDirection: 'column',
                boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
            }}>
                {/* Header */}
                <div style={{ padding: '12px 20px', background: '#1e293b', borderRadius: '12px 12px 0 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexShrink: 0 }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>Update Project Phase</span>
                    <button onClick={onClose} style={{ width: '26px', height: '26px', borderRadius: '5px', border: '1px solid rgba(255,255,255,0.2)', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Body */}
                <div style={{ overflowY: 'auto', padding: '24px', flex: 1 }}>
                    {/* Section I */}
                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#2563eb', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                        I. Change Status Form
                    </div>
                    <form id="statusUpdateForm" onSubmit={handleSubmit}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                            <div>
                                <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Select New Project Status</label>
                                <select
                                    value={selectedKey}
                                    onChange={e => setSelectedKey(e.target.value)}
                                    required
                                    style={{ width: '100%', padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none', fontFamily: 'inherit' }}
                                >
                                    <option value="" disabled>Choose status…</option>
                                    {Object.entries(STATUS_META).map(([k, v]) => (
                                        <option key={k} value={k}>{v.text}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div style={{ marginBottom: '14px' }}>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Modification Remarks</label>
                            <textarea
                                rows={2}
                                value={remarks}
                                onChange={e => setRemarks(e.target.value)}
                                placeholder="Explain the phase change..."
                                style={{ width: '100%', padding: '8px 12px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', resize: 'vertical', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box' }}
                            />
                        </div>

                        {selectedKey && selectedMeta && (
                            <div style={{ padding: '14px', borderRadius: '8px', background: selectedMeta.bg, border: `1px solid ${selectedMeta.border}`, color: selectedMeta.color, fontSize: '12.5px' }}>
                                <div style={{ fontWeight: 700, textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.3px', marginBottom: '4px' }}>
                                    Phase Guidelines: {selectedMeta.text}
                                </div>
                                <div>{selectedHint}</div>
                            </div>
                        )}
                    </form>

                    {/* Section II */}
                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.4px', marginTop: '32px' }}>
                        II. Status Log & Audit Trail
                    </div>
                    <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px' }}>
                            <thead>
                                <tr style={{ background: '#f8fafc' }}>
                                    {['Date & Time', 'Authorized User', 'Status Changed To', 'Remarks'].map(h => (
                                        <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontSize: '10px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e5e7eb' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log, i) => (
                                    <tr key={log.id ?? i} style={{ borderBottom: '1px solid #f3f4f6' }}>
                                        <td style={{ padding: '10px 14px' }}>
                                            <div style={{ fontWeight: 700, color: '#475569' }}>{log.date}</div>
                                            <div style={{ fontSize: '11px', color: '#9ca3af' }}>{log.time}</div>
                                        </td>
                                        <td style={{ padding: '10px 14px', fontWeight: 600, color: '#1e293b' }}>{log.user}</td>
                                        <td style={{ padding: '10px 14px' }}><StatusBadge statusKey={log.status_key} /></td>
                                        <td style={{ padding: '10px 14px', color: '#6b7280', fontSize: '12px' }}>{log.remarks}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Footer */}
                <div style={{ padding: '12px 20px', borderTop: '1px solid #e5e7eb', background: '#f8fafc', borderRadius: '0 0 12px 12px', display: 'flex', justifyContent: 'flex-end', gap: '8px', flexShrink: 0 }}>
                    <button onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151' }}>Close</button>
                    <button
                        type="submit"
                        form="statusUpdateForm"
                        disabled={posting || !selectedKey}
                        style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: posting || !selectedKey ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: posting || !selectedKey ? 'not-allowed' : 'pointer' }}
                    >
                        {posting ? 'Updating…' : 'Update Phase'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function ProjectShow({ project, active_section, hub_data = {} }: Props) {
    const [showStatusModal, setShowStatusModal] = useState(false);
    const [currentStatusKey, setCurrentStatusKey] = useState(project.status_key);
    const [logs, setLogs] = useState<StatusLog[]>(project.status_logs ?? []);

    const activeMenu = active_section ?? null;

    const currentMeta = STATUS_META[currentStatusKey] ?? STATUS_META['PLANNING'];
    const budgetPct   = project.budget_total > 0 ? Math.round((project.budget_paid / project.budget_total) * 100) : 0;

    const hubProject: import('./hub/Common').HubProject = {
        id:                 project.id,
        project_no:         project.project_no,
        title:              project.title,
        project_manager:    project.project_manager,
        site:               project.site,
        budget_total:       project.budget_total,
        budget_paid:        project.budget_paid,
        completion_percent: project.completion_percent,
        deadline:           project.deadline,
        cost_code:          project.cost_code,
        owner_email:        project.owner_email,
    };

    const handleStatusUpdate = (key: string, text: string, remarks: string) => {
        const now = new Date();
        const newLog: StatusLog = {
            id: Date.now(),
            date: now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
            time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            user: 'Current User',
            status: text,
            status_key: key,
            remarks,
        };
        setLogs(prev => [newLog, ...prev]);
        setCurrentStatusKey(key);
        setShowStatusModal(false);
    };

    const fmt = (n: number) =>
        n >= 1_000_000 ? `PhP ${(n / 1_000_000).toFixed(1)}M` : `PhP ${(n / 1_000).toFixed(0)}K`;

    const sectionLabel = (text: string) => (
        <div style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.8px', marginBottom: '6px' }}>{text}</div>
    );

    return (
        <AuthenticatedLayout>
            <Head title={`${project.project_no} | Project Record`} />

            {showStatusModal && (
                <StatusUpdateModal
                    project={project}
                    logs={logs}
                    onClose={() => setShowStatusModal(false)}
                    onUpdate={handleStatusUpdate}
                />
            )}

            {/* Breadcrumb / Top Bar */}
            <div style={{ background: '#fff', padding: '10px 0', borderBottom: '1px solid #e5e7eb', marginBottom: '24px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ fontSize: '13px', color: '#64748b', fontWeight: 500 }}>
                    Projects / View / <span style={{ color: '#1e293b', fontWeight: 700 }}>{project.project_no}</span>
                </div>
                <div style={{ display: 'flex', gap: '8px' }}>
                    <button
                        onClick={() => window.print()}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151' }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Export PDF
                    </button>
                    <button
                        onClick={() => router.visit(route('projects.edit', project.id))}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 18px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Project
                    </button>
                </div>
            </div>

            {/* Record Card */}
            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)', marginBottom: '32px' }}>

                {/* Header */}
                <div style={{ padding: '24px 30px', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '8px' }}>
                            <h2 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0 }}>{project.title}</h2>
                            <span style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                minHeight: '24px',
                                padding: '3px 10px',
                                borderRadius: '999px',
                                fontSize: '11px',
                                fontWeight: 900,
                                letterSpacing: '0.4px',
                                textTransform: 'uppercase',
                                background: project.project_type === 'major' ? '#dbeafe' : '#f1f5f9',
                                color: project.project_type === 'major' ? '#1d4ed8' : '#475569',
                                border: `1px solid ${project.project_type === 'major' ? '#93c5fd' : '#cbd5e1'}`,
                            }}>
                                {project.project_type}
                            </span>
                        </div>
                        <div style={{ fontSize: '13px', color: '#64748b' }}>
                            <span style={{ marginRight: '16px' }}>📍 Site: <strong style={{ color: '#374151' }}>{project.site}</strong></span>
                            <span style={{ marginRight: '16px' }}>PM: <strong style={{ color: '#374151' }}>{project.project_manager}</strong></span>
                            <span>Encoded By: <strong style={{ color: '#374151' }}>{project.encoded_by}</strong></span>
                        </div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.8px', marginBottom: '8px' }}>Current Lifecycle Status</div>
                        <button
                            onClick={() => setShowStatusModal(true)}
                            style={{
                                padding: '8px 18px', borderRadius: '50px', fontWeight: 700, fontSize: '13px',
                                cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '6px',
                                background: currentMeta.bg, color: currentMeta.color,
                                border: `1px solid ${currentMeta.border}`,
                                transition: 'box-shadow 0.2s',
                            }}
                            onMouseEnter={e => (e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)')}
                            onMouseLeave={e => (e.currentTarget.style.boxShadow = 'none')}
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15" stroke="currentColor" strokeWidth="2"/></svg>
                            {currentMeta.text}
                        </button>
                        <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '8px' }}>
                            ℹ️ Click to update project phase
                        </div>
                    </div>
                </div>

                {/* Analytics Strip */}
                <div style={{ padding: '24px 30px', borderBottom: '1px solid #e5e7eb', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '0', background: '#fafafa', border: '1px solid #e2e8f0', borderRadius: '12px', margin: '20px 30px' }}>
                    {/* Timeline */}
                    <div style={{ padding: '0 24px 0 0', borderRight: '1px solid #e5e7eb', textAlign: 'center' }}>
                        {sectionLabel('Timeline (Days Remaining)')}
                        <div style={{ position: 'relative', display: 'inline-block', marginBottom: '8px' }}>
                            <SemiDonut elapsed={project.days_elapsed} remaining={project.days_remaining} color="#f59e0b" />
                            <div style={{ position: 'absolute', bottom: '0', left: '50%', transform: 'translateX(-50%)', fontWeight: 800, fontSize: '22px', color: '#1e293b' }}>
                                {project.days_remaining}
                            </div>
                        </div>
                        <div style={{ fontSize: '12px', color: '#9ca3af' }}>Deadline: <strong style={{ color: '#374151' }}>{project.deadline}</strong></div>
                    </div>

                    {/* Budget */}
                    <div style={{ padding: '0 24px', borderRight: '1px solid #e5e7eb', textAlign: 'center' }}>
                        {sectionLabel('Financial Utilization')}
                        <div style={{ margin: '8px 0' }}>
                            <DonutChart percent={budgetPct} color="#2563eb" size={110} />
                        </div>
                        <div style={{ fontSize: '12px', color: '#6b7280' }}>
                            Paid: <strong style={{ color: '#2563eb' }}>{fmt(project.budget_paid)}</strong> / <strong>{fmt(project.budget_total)}</strong>
                        </div>
                    </div>

                    {/* Completion */}
                    <div style={{ padding: '0 24px', borderRight: '1px solid #e5e7eb', textAlign: 'center' }}>
                        {sectionLabel('Physical Completion')}
                        <div style={{ position: 'relative', display: 'inline-block', margin: '8px 0' }}>
                            <DonutChart percent={project.completion_percent} color="#16a34a" size={110} />
                            <div style={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', fontWeight: 800, fontSize: '20px', color: '#1e293b' }}>
                                {project.completion_percent}%
                            </div>
                        </div>
                        <div style={{ fontSize: '12px', color: '#9ca3af' }}>Based on latest weekly report</div>
                    </div>

                    {/* Health */}
                    <div style={{ padding: '0 0 0 24px', textAlign: 'center', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
                        {sectionLabel('Project Health')}
                        <div style={{ margin: '12px 0' }}>
                            <HealthBadge health={project.project_health} />
                        </div>
                        <p style={{ fontSize: '12px', color: '#9ca3af', margin: 0, maxWidth: '160px', textAlign: 'center' }}>
                            Current progress aligns with the approved baseline schedule.
                        </p>
                    </div>
                </div>

                {/* Details Grid */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr' }}>
                    {/* Left */}
                    <div style={{ padding: '28px', borderRight: '1px solid #e5e7eb', background: 'rgba(248,250,252,0.5)' }}>
                        <InfoField label="Project Number">{project.project_no}</InfoField>
                        <InfoField label="Encoded By">{project.encoded_by}</InfoField>
                        <InfoField label="Encoded At">{project.encoded_at}</InfoField>
                        <hr style={{ border: 'none', borderTop: '1px solid #e5e7eb', margin: '16px 0' }} />
                        <InfoField label="Asset ID">{project.asset_id}</InfoField>
                        <InfoField label="Cost Code">{project.cost_code}</InfoField>
                        <hr style={{ border: 'none', borderTop: '1px solid #e5e7eb', margin: '16px 0' }} />
                        <InfoField label="WR No. & Date">
                            {project.wr_no} <span style={{ color: '#9ca3af', marginLeft: '8px' }}>(Received: {project.wr_date})</span>
                        </InfoField>
                        <InfoField label="Priority Number">
                            <span style={{ background: '#1e293b', color: '#fff', padding: '2px 10px', borderRadius: '4px', fontSize: '12px', fontWeight: 700 }}>{project.priority}</span>
                        </InfoField>
                        <hr style={{ border: 'none', borderTop: '1px solid #e5e7eb', margin: '16px 0' }} />
                        <InfoField label="Department Owner">{project.dept_owner}</InfoField>
                        <InfoField label="Owner's Email">
                            <a href={`mailto:${project.owner_email}`} style={{ color: '#2563eb', textDecoration: 'none' }}>{project.owner_email}</a>
                        </InfoField>
                    </div>

                    {/* Right */}
                    <div style={{ padding: '28px' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '0 16px', marginBottom: '20px' }}>
                            <InfoField label="Class">{project.cls}</InfoField>
                            <InfoField label="Category">{project.category}</InfoField>
                            <InfoField label="Service Type">{project.service_type}</InfoField>
                            <InfoField label="Project Type">
                                {project.project_type === 'major' ? (
                                    <span style={{ background: '#1e3a5f', color: '#fff', padding: '2px 10px', borderRadius: '4px', fontSize: '12px', fontWeight: 700 }}>★ MAJOR</span>
                                ) : (
                                    <span style={{ background: '#f1f5f9', color: '#475569', padding: '2px 10px', borderRadius: '4px', fontSize: '12px', fontWeight: 700, border: '1px solid #cbd5e1' }}>MINOR</span>
                                )}
                            </InfoField>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0 16px', marginBottom: '20px' }}>
                            <InfoField label="Work Force">{project.work_force}</InfoField>
                            <InfoField label="JIP">{project.jip}</InfoField>
                            <InfoField label="Structure Type">{project.structure_type}</InfoField>
                        </div>

                        <div style={{ marginBottom: '24px' }}>
                            <div style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.8px', marginBottom: '12px' }}>Technical Plan Status</div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px' }}>
                                <PlanCard label="Civil Plans"       required={project.technical_plans.civil} />
                                <PlanCard label="Electrical Plans"  required={project.technical_plans.electrical} />
                                <PlanCard label="Mechanical Plans"  required={project.technical_plans.mechanical} />
                            </div>
                        </div>

                        {project.project_type === 'major' && (
                            <div style={{ marginBottom: '24px', padding: '14px 16px', border: '1px solid #bfdbfe', borderRadius: '8px', background: '#eff6ff', display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: '11px', fontWeight: 700, color: '#1d4ed8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '2px' }}>Approved Proposal Document</div>
                                    {project.proposal_document_url ? (
                                        <a href={project.proposal_document_url} target="_blank" rel="noopener noreferrer"
                                           style={{ fontSize: '13px', color: '#2563eb', textDecoration: 'none', fontWeight: 600 }}>
                                            View / Download Document ↗
                                        </a>
                                    ) : (
                                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>No document uploaded yet.</span>
                                    )}
                                </div>
                            </div>
                        )}

                        <div>
                            <div style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.8px', marginBottom: '8px' }}>Administrative Notes</div>
                            <div style={{ padding: '14px', border: '1px solid #e5e7eb', borderRadius: '8px', background: '#fff', fontSize: '13px', color: '#475569', lineHeight: 1.7 }}>
                                {project.admin_notes}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Operations Hub */}
            <div style={{ marginBottom: '32px' }}>
                <h5 style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                    Project Operations Hub
                </h5>

                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', minHeight: '600px', display: 'grid', gridTemplateColumns: '240px 1fr' }}>
                    {/* Sidebar */}
                    <div style={{ borderRight: '1px solid #e5e7eb' }}>
                        <div style={{ padding: '10px 14px', background: '#f8fafc', borderBottom: '1px solid #e5e7eb' }}>
                            <span style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Action Menu</span>
                        </div>
                        {OPS_MENU.map(item => {
                            const isActive = activeMenu === item.key;
                            return (
                                <button
                                    key={item.key}
                                    onClick={() => router.visit(route('projects.hub.' + item.key, project.id), { preserveScroll: true })}
                                    style={{
                                        width: '100%', padding: '13px 18px',
                                        display: 'flex', alignItems: 'center', gap: '10px',
                                        fontSize: '13px', fontWeight: isActive ? 600 : 500,
                                        color: isActive ? '#2563eb' : '#475569',
                                        background: isActive ? '#eff6ff' : 'transparent',
                                        borderLeft: `3px solid ${isActive ? '#2563eb' : 'transparent'}`,
                                        border: 'none', borderBottom: '1px solid #f3f4f6',
                                        cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.12s',
                                    }}
                                    onMouseEnter={e => { if (!isActive) e.currentTarget.style.background = '#f8fafc'; }}
                                    onMouseLeave={e => { if (!isActive) e.currentTarget.style.background = 'transparent'; }}
                                >
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>

                    {/* Content pane */}
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        <div style={{ padding: '8px 16px', background: '#f8fafc', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontSize: '11px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                {OPS_MENU.find(m => m.key === activeMenu)?.label ?? 'Select an action'}
                            </span>
                            <span style={{ fontSize: '11.5px', color: '#94a3b8' }}>Workspace</span>
                        </div>
                        {activeMenu
                            ? renderHubSection(activeMenu, hubProject, hub_data)
                            : (
                                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '60px 32px', color: '#94a3b8', textAlign: 'center', gap: '12px' }}>
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                                    <div style={{ fontSize: '14px', fontWeight: 600, color: '#64748b' }}>Select an action from the menu</div>
                                    <div style={{ fontSize: '12.5px', color: '#94a3b8' }}>Choose an item on the left to load its workspace.</div>
                                </div>
                            )
                        }
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
