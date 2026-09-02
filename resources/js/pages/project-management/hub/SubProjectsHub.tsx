import { router } from '@inertiajs/react';
import { Badge, DataTable, HubProject, HubShell, money } from './Common';

/**
 * Sub-projects of the project whose hub this is.
 *
 * A sub-project is an ordinary project that happens to hang off another: it
 * runs its own RFQs, NTPs, billings and reports. Only the depth cap limits
 * what it can do — a tree runs at most three deep.
 */

export interface SubProjectRow {
    id: number;
    project_no: string;
    title: string;
    status_key: string;
    completion: number;
    budget_total: number;
    budget_paid: number;
    deadline: string | null;
    contractor: string | null;
    /** How many sub-projects this one has of its own. */
    children_count: number;
    can_have_subs: boolean;
    /** Set only on rows raised the old way, from an issued NTP. */
    source_ntp_no: string | null;
}

const STATUS_TONE: Record<string, 'slate' | 'green' | 'yellow' | 'blue' | 'red'> = {
    PLANNING: 'slate', ONGOING: 'blue', COMPLETED: 'green',
    ON_HOLD: 'yellow', CANCELLED: 'red',
};

function ProgressBar({ percent }: { percent: number }) {
    const pct = Math.max(0, Math.min(100, percent));
    return (
        <div style={{ minWidth: '110px' }}>
            <div style={{ height: '6px', background: '#f1f5f9', borderRadius: '999px', overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: pct >= 100 ? '#16a34a' : '#2563eb' }} />
            </div>
            <div style={{ fontSize: '11px', color: '#64748b', marginTop: '3px', fontWeight: 600 }}>{pct}%</div>
        </div>
    );
}

export default function SubProjectsHub({ project, subprojects, depth, maxDepth, canAdd, canEdit = true }: {
    project: HubProject;
    subprojects: SubProjectRow[];
    depth: number;
    maxDepth: number;
    canAdd: boolean;
    canEdit?: boolean;
}) {
    const open = (id: number) => router.visit(route('projects.show', id));

    return (
        <HubShell>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '16px', marginBottom: '18px', flexWrap: 'wrap' }}>
                <div>
                    <h3 style={{ margin: '0 0 4px', color: '#4338ca', fontSize: '18px' }}>Sub-Projects</h3>
                    <div style={{ fontSize: '12.5px', color: '#64748b', lineHeight: 1.6 }}>
                        Break this project into parts that run on their own — each with its own RFQs, NTPs, billings and reports.
                        {' '}This project sits at level {depth} of {maxDepth}.
                    </div>
                </div>

                {canEdit && canAdd && (
                    <button
                        type="button"
                        onClick={() => router.visit(route('projects.create', { parent: project.id }))}
                        style={{ padding: '9px 18px', borderRadius: '8px', border: 'none', background: '#4338ca', color: '#fff', fontSize: '12.5px', fontWeight: 800, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Sub-Project
                    </button>
                )}
            </div>

            {canEdit && !canAdd && (
                <div style={{ padding: '11px 15px', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '8px', color: '#92400e', fontSize: '12.5px', marginBottom: '18px', lineHeight: 1.6 }}>
                    This project is already at the deepest level a tree may go ({maxDepth}), so it cannot have sub-projects of its own.
                </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px', alignItems: 'center' }}>
                <strong style={{ color: '#475569' }}>Sub-Project Register</strong>
                <span style={{ padding: '3px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px', fontSize: '12px', color: '#475569' }}>
                    Total Records: {subprojects.length}
                </span>
            </div>

            {subprojects.length === 0 ? (
                <div style={{ border: '1px dashed #cbd5e1', borderRadius: '10px', padding: '44px 24px', textAlign: 'center', color: '#94a3b8', fontSize: '13px', background: '#f8fafc' }}>
                    No sub-projects yet.
                    {canEdit && canAdd && ' Add one to split this project into parts that are tracked separately.'}
                </div>
            ) : (
                <DataTable
                    headers={['Project No.', 'Title', 'Status', 'Progress', 'Project Cost', 'Paid', 'Target Date', 'Actions']}
                    rows={subprojects.map(row => [
                        <div>
                            <strong style={{ color: '#4338ca' }}>{row.project_no}</strong>
                            <div style={{ display: 'flex', gap: '4px', marginTop: '3px', flexWrap: 'wrap' }}>
                                {/* The marks that say "this is a sub-project", kept visible everywhere. */}
                                <span style={{ fontSize: '10px', fontWeight: 700, color: '#4338ca', background: '#eef2ff', border: '1px solid #c7d2fe', borderRadius: '5px', padding: '1px 6px' }}>
                                    Sub-project
                                </span>
                                {row.children_count > 0 && (
                                    <span style={{ fontSize: '10px', fontWeight: 700, color: '#0f766e', background: '#f0fdfa', border: '1px solid #99f6e4', borderRadius: '5px', padding: '1px 6px' }}>
                                        {row.children_count} sub
                                    </span>
                                )}
                                {row.source_ntp_no && (
                                    <span title="Raised from this NTP before sub-projects became independent"
                                        style={{ fontSize: '10px', fontWeight: 700, color: '#92400e', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '5px', padding: '1px 6px' }}>
                                        {row.source_ntp_no}
                                    </span>
                                )}
                            </div>
                        </div>,
                        <span style={{ display: 'block', maxWidth: '260px', fontSize: '12.5px', color: '#334155', lineHeight: 1.45 }}>{row.title}</span>,
                        <Badge tone={STATUS_TONE[row.status_key] ?? 'slate'}>{row.status_key?.replace(/_/g, ' ') ?? '—'}</Badge>,
                        <ProgressBar percent={row.completion} />,
                        <span style={{ fontWeight: 700, color: '#0f172a' }}>{money(row.budget_total)}</span>,
                        <span style={{ color: row.budget_paid > 0 ? '#15803d' : '#cbd5e1', fontWeight: 700 }}>{money(row.budget_paid)}</span>,
                        <span style={{ fontSize: '12.5px', color: '#475569' }}>{row.deadline ?? '—'}</span>,
                        <button
                            type="button"
                            onClick={() => open(row.id)}
                            style={{ padding: '6px 13px', borderRadius: '7px', border: '1px solid #c7d2fe', background: '#eef2ff', color: '#4338ca', fontSize: '11.5px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}
                        >
                            Open Hub
                        </button>,
                    ])}
                />
            )}
        </HubShell>
    );
}
