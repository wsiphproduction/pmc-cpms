import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ApprovalStep } from '@/components/ApprovalTimeline';

// ── EMS Survey Report (PMD-PRJ-FRM-19, Environmental Impact Assessment) ─────
// Shared by the project hub, the MEPEO page and the For Approval portal, which
// all show the same record. Mirrors App\Support\EmsReportPresenter.

export interface EmsOption {
    key: string;
    label: string;
}

export interface EmsOptions {
    major_activities: EmsOption[];
    aspects: EmsOption[];
    statuses: EmsOption[];
}

export type EmsStatus = 'draft' | 'with_mepeo' | 'for_approval' | 'approved';

export interface EmsReportRow {
    id: number;
    control_no: string;
    status: EmsStatus;
    status_label: string;
    date_requested: string | null;
    location: string | null;
    contractor: string | null;
    target_start: string | null;
    target_end: string | null;
    major_activities: string[];
    major_activities_other: string | null;
    other_scope: string | null;
    aspects: string[];
    aspects_other: string | null;
    impacts: string | null;
    mitigation: string | null;
    mepeo_status: string | null;
    remarks: string | null;
    /** Why it last came back to the representative, until they resubmit. */
    return_remarks: string | null;
    mepeo_rep: { id: number; name: string } | null;
    prepared_by: string | null;
    sent_at: string | null;
    submitted_at: string | null;
    created_at: string | null;
    approvals: ApprovalStep[];
    current_role: string | null;
    print_url: string;
    project: { id: number; project_no: string; title: string; site: string | null; dept_owner: string | null };
    sub_project_id?: number | null;
    sub_project_no?: string | null;
}

export const EMS_TONE = '#15803d';

/** What each office's signature means on the form. */
export const EMS_SIGN_LABELS: Record<string, { verb: string; action: string }> = {
    mepeo_manager:    { verb: 'Approved', action: 'Approve' },
    pmd_asst_manager: { verb: 'Reviewed', action: 'Mark Reviewed' },
    pmd_dept_manager: { verb: 'Noted',    action: 'Mark Noted' },
};

const STATUS_STYLE: Record<EmsStatus, { bg: string; color: string; border: string }> = {
    draft:        { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' },
    with_mepeo:   { bg: '#fef9c3', color: '#854d0e', border: '#fef08a' },
    for_approval: { bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe' },
    approved:     { bg: '#dcfce7', color: '#166534', border: '#bbf7d0' },
};

export function EmsStatusBadge({ row }: { row: Pick<EmsReportRow, 'status' | 'status_label' | 'return_remarks'> }) {
    const returned = row.status === 'with_mepeo' && !!row.return_remarks;
    const s = returned ? { bg: '#fee2e2', color: '#991b1b', border: '#fecaca' } : STATUS_STYLE[row.status];
    return (
        <span style={{ background: s.bg, color: s.color, border: `1px solid ${s.border}`, borderRadius: '999px', padding: '3px 9px', fontSize: '11px', fontWeight: 800, whiteSpace: 'nowrap' }}>
            {returned ? 'Returned for Revision' : row.status_label}
        </span>
    );
}

export function fmtDate(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(`${value}T00:00:00`);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Sign the step the report waits on, or send it back to the MEPEO
 * representative. A return has to say what needs revising; a signature may
 * carry a remark.
 */
export function EmsSignModal({ report, mode, onClose }: { report: EmsReportRow; mode: 'sign' | 'return'; onClose: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const signing = mode === 'sign';
    const action = EMS_SIGN_LABELS[report.current_role ?? '']?.action ?? 'Approve';

    const submit = () => {
        if (!signing && remarks.trim() === '') { setError('Say what needs revising.'); return; }
        setSaving(true);
        router.patch(route(signing ? 'ems.approve' : 'ems.return', report.id), { remarks }, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: errs => setError(Object.values(errs)[0] ?? 'Could not save.'),
            onFinish: () => setSaving(false),
        });
    };

    const tone = signing ? { bg: '#ecfdf5', color: '#047857', button: '#059669' } : { bg: '#fef2f2', color: '#b91c1c', button: '#dc2626' };

    return (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 600, padding: '20px' }}>
            <div style={{ background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '460px', overflow: 'hidden', boxShadow: '0 20px 50px rgba(0,0,0,0.25)' }}>
                <div style={{ padding: '16px 22px', borderBottom: '1px solid #f1f5f9', background: tone.bg }}>
                    <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 800, color: tone.color }}>
                        {signing ? `${action} — ${report.control_no}` : `Return ${report.control_no} to MEPEO`}
                    </h3>
                </div>
                <div style={{ padding: '22px' }}>
                    <p style={{ margin: '0 0 14px', fontSize: '12.5px', color: '#475569', lineHeight: 1.55 }}>
                        {signing
                            ? 'Your signature moves the report to the next office; the last one completes it.'
                            : 'The representative revises it and submits again, which restarts the sign-off from the MEPEO Manager.'}
                    </p>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                        {signing ? 'Remarks (optional)' : 'What needs revising'}
                    </label>
                    <textarea
                        rows={4}
                        value={remarks}
                        onChange={e => { setRemarks(e.target.value); setError(''); }}
                        style={{ width: '100%', boxSizing: 'border-box', padding: '10px 12px', border: `1.5px solid ${error ? '#fca5a5' : '#e2e8f0'}`, borderRadius: '8px', fontSize: '13px', fontFamily: 'inherit', resize: 'vertical', outline: 'none' }}
                    />
                    {error && <div style={{ color: '#dc2626', fontSize: '12px', marginTop: '6px', fontWeight: 600 }}>{error}</div>}
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button type="button" onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                    <button type="button" onClick={submit} disabled={saving} style={{ padding: '8px 22px', borderRadius: '7px', border: 'none', background: tone.button, color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: saving ? 'not-allowed' : 'pointer' }}>
                        {saving ? 'Saving…' : signing ? action : 'Return to MEPEO'}
                    </button>
                </div>
            </div>
        </div>
    );
}

const label: React.CSSProperties = { fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '3px' };
const band: React.CSSProperties = { background: '#fef08a', color: '#1f2937', fontSize: '11.5px', fontWeight: 800, textAlign: 'center', padding: '6px 10px', borderBottom: '1px solid #e5e7eb' };

/** The project-side header the engineer sets. */
export function EmsHeaderGrid({ row }: { row: EmsReportRow }) {
    const items: [string, string][] = [
        ['Control Number', row.control_no],
        ['Date Requested', fmtDate(row.date_requested)],
        ['Project Number', row.project.project_no],
        ['Project Title', row.project.title],
        ['Project Location', row.location || '—'],
        ['Project Owner', row.project.dept_owner || '—'],
        ['Contractor', row.contractor || '—'],
        ['Target Start Date', fmtDate(row.target_start)],
        ['Target End Date', fmtDate(row.target_end)],
    ];
    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: '12px 18px', border: '1px solid #e2e8f0', borderLeft: `4px solid ${EMS_TONE}`, borderRadius: '8px', background: '#f8fafc', padding: '14px 16px' }}>
            {items.map(([k, v]) => (
                <div key={k} style={{ minWidth: 0 }}>
                    <div style={label}>{k}</div>
                    <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e293b', overflowWrap: 'anywhere' }}>{v}</div>
                </div>
            ))}
        </div>
    );
}

function Check({ on }: { on: boolean }) {
    return (
        <span aria-hidden style={{
            width: '15px', height: '15px', flexShrink: 0, borderRadius: '4px',
            border: `1.5px solid ${on ? EMS_TONE : '#cbd5e1'}`, background: on ? EMS_TONE : '#fff',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: '10px', fontWeight: 900,
        }}>{on ? '✓' : ''}</span>
    );
}

function CheckList({ options, ticked, other }: { options: EmsOption[]; ticked: string[]; other: string | null }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {options.map(o => (
                <div key={o.key} style={{ display: 'flex', alignItems: 'center', gap: '9px', padding: '6px 12px', borderBottom: '1px solid #f1f5f9', fontSize: '12.5px', color: ticked.includes(o.key) ? '#0f172a' : '#94a3b8', fontWeight: ticked.includes(o.key) ? 600 : 400 }}>
                    <Check on={ticked.includes(o.key)} /> {o.label}
                </div>
            ))}
            <div style={{ display: 'flex', alignItems: 'center', gap: '9px', padding: '6px 12px', fontSize: '12.5px', color: other ? '#0f172a' : '#94a3b8' }}>
                <Check on={!!other} /> Others: {other ? <strong>{other}</strong> : ''}
            </div>
        </div>
    );
}

function TextCell({ text }: { text: string | null }) {
    return (
        <div style={{ padding: '10px 12px', fontSize: '12.5px', color: text ? '#334155' : '#94a3b8', whiteSpace: 'pre-wrap', lineHeight: 1.5, flex: 1 }}>
            {text || 'Not filled in yet.'}
        </div>
    );
}

const frame: React.CSSProperties = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', background: '#fff' };
const col: React.CSSProperties = { display: 'flex', flexDirection: 'column', borderRight: '1px solid #e5e7eb', minWidth: 0 };

/** The assessment and MEPEO's finding, read-only, laid out like the paper form. */
export function EmsReportSheet({ row, options }: { row: EmsReportRow; options: EmsOptions }) {
    const status = options.statuses.find(s => s.key === row.mepeo_status);
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
            <div style={frame}>
                <div style={col}>
                    <div style={band}>Major Activities</div>
                    <CheckList options={options.major_activities} ticked={row.major_activities} other={row.major_activities_other} />
                </div>
                <div style={{ ...col, borderRight: 'none' }}>
                    <div style={band}>Other Scope of Work</div>
                    <TextCell text={row.other_scope} />
                </div>
            </div>

            <div style={frame}>
                <div style={col}>
                    <div style={band}>Environmental Aspects</div>
                    <CheckList options={options.aspects} ticked={row.aspects} other={row.aspects_other} />
                </div>
                <div style={{ ...col, borderRight: 'none' }}>
                    <div style={band}>Environmental Impacts</div>
                    <TextCell text={row.impacts} />
                    <div style={{ ...band, borderTop: '1px solid #e5e7eb' }}>Mitigation</div>
                    <TextCell text={row.mitigation} />
                </div>
            </div>

            <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                <div style={band}>Status (MEPEO)</div>
                <div style={{ padding: '10px 12px', fontSize: '12.5px', fontWeight: 700, color: status ? '#0f172a' : '#94a3b8' }}>
                    {status ? status.label : 'Not decided yet.'}
                </div>
                <div style={{ ...band, borderTop: '1px solid #e5e7eb' }}>Remarks/Comments</div>
                <TextCell text={row.remarks} />
            </div>
        </div>
    );
}
