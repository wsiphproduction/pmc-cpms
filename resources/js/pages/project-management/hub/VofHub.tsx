import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionBtns, Badge, Button, DataTable, Field, HubProject, HubShell, InfoStrip, Modal, ModalSection, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

import { FileHistory, FileVersion, VersionBadge } from '@/components/FileVersions';

interface VofRow {
    id: number;
    versions?: FileVersion[];
    vo_no: string;
    title: string;
    description: string | null;
    amount: number;
    duration_days: number | null;
    status: string;
    submitted_date: string;
    approved_date: string | null;
    requestor: string | null;
    date_of_request: string | null;
    priority: string | null;
    attachment_url: string | null;
    scope_original: string | null;
    scope_proposed: string | null;
    scope_remark: string | null;
    schedule_original: string | null;
    schedule_proposed: string | null;
    schedule_remark: string | null;
    cost_original: string | null;
    cost_proposed: string | null;
    cost_remark: string | null;
    sub_project_id: number | null;
    sub_project_no: string | null;
}

const ACCENT = '#f59e0b';
const PRIORITIES = ['Immediate', 'High', 'Essential', 'Medium', 'Urgent', 'Low'];

const STATUS_TONE: Record<string, 'green' | 'slate' | 'red'> = {
    Approved: 'green', Pending: 'slate', Rejected: 'red',
};

const money = (n: number) => `PhP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Contracted cost + approved variations = project cost. Only approved
 * variations count, so a pending or rejected one leaves the total alone.
 */
function CostBreakdown({ project }: { project: HubProject }) {
    const base      = project.budget_base ?? project.budget_total;
    const variation = project.budget_total - base;

    const cell = (label: string, value: string, strong = false) => (
        <div>
            <div style={{ fontSize: '10px', fontWeight: 800, color: '#92400e', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '3px' }}>{label}</div>
            <div style={{ fontSize: strong ? '15px' : '13.5px', fontWeight: strong ? 900 : 700, color: '#78350f' }}>{value}</div>
        </div>
    );

    return (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr auto 1fr auto 1fr', gap: '14px', alignItems: 'center', padding: '14px 18px', marginBottom: '18px', borderRadius: '10px', background: '#fffbeb', border: `1px solid ${ACCENT}33` }}>
            {cell('Contracted Cost', money(base))}
            <div style={{ fontSize: '18px', fontWeight: 800, color: '#b45309' }}>+</div>
            {cell('Approved Variations', money(variation))}
            <div style={{ fontSize: '18px', fontWeight: 800, color: '#b45309' }}>=</div>
            {cell('Project Cost', money(project.budget_total), true)}
        </div>
    );
}

// ── Shared detail table ────────────────────────────────────────────────────
function DetailTable({
    scope, setScope, schedule, setSchedule, cost, setCost,
}: {
    scope:    { original: string; proposed: string; remark: string };
    setScope: (v: { original: string; proposed: string; remark: string }) => void;
    schedule: { original: string; proposed: string; remark: string };
    setSchedule: (v: { original: string; proposed: string; remark: string }) => void;
    cost:    { original: string; proposed: string; remark: string };
    setCost: (v: { original: string; proposed: string; remark: string }) => void;
}) {
    const rows = [
        { label: 'Scope',    state: scope,    set: setScope },
        { label: 'Schedule', state: schedule, set: setSchedule },
        { label: 'Cost',     state: cost,     set: setCost },
    ];

    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', marginBottom: '20px' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                <thead>
                    <tr style={{ background: '#fefce8' }}>
                        {['Aspect', 'Original Details', 'Proposed Change', 'Reason / Remark'].map(h => (
                            <th key={h} style={{ padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#92400e', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #fde68a' }}>{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map(({ label, state, set }, i) => (
                        <tr key={label} style={{ borderBottom: i < 2 ? '1px solid #f1f5f9' : 'none' }}>
                            <td style={{ padding: '8px 12px', fontWeight: 700, color: '#374151', whiteSpace: 'nowrap', verticalAlign: 'top' }}>{label}</td>
                            {(['original', 'proposed', 'remark'] as const).map(key => (
                                <td key={key} style={{ padding: '4px' }}>
                                    <textarea
                                        rows={3}
                                        style={{ ...inputStyle, border: 'none', borderRadius: 0, resize: 'vertical', minWidth: '140px' }}
                                        value={state[key]}
                                        onChange={e => set({ ...state, [key]: e.target.value })}
                                    />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── Priority checkboxes ────────────────────────────────────────────────────
function PriorityGroup({ priorities, onChange }: { priorities: string[]; onChange: (p: string[]) => void }) {
    const toggle = (item: string) =>
        onChange(priorities.includes(item) ? priorities.filter(p => p !== item) : [...priorities, item]);
    return (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px', fontSize: '12px', color: '#334155', marginTop: '2px' }}>
            {PRIORITIES.map(item => (
                <label key={item} style={{ display: 'flex', alignItems: 'center', gap: '5px', cursor: 'pointer' }}>
                    <input type="checkbox" checked={priorities.includes(item)} onChange={() => toggle(item)} />
                    {item}
                </label>
            ))}
        </div>
    );
}

// ── Create Modal ───────────────────────────────────────────────────────────
function CreateModal({ project, onClose }: { project: HubProject; onClose: () => void }) {
    const [title,      setTitle]      = useState('');
    const [desc,       setDesc]       = useState('');
    const [amount,     setAmount]     = useState('');
    const [duration,   setDuration]   = useState('');
    const [requestor,  setRequestor]  = useState('');
    const [dateReq,    setDateReq]    = useState('');
    const [priorities, setPriorities] = useState<string[]>([]);
    const [attachment, setAttachment] = useState<File | null>(null);
    const [scope,      setScope]      = useState({ original: '', proposed: '', remark: '' });
    const [schedule,   setSchedule]   = useState({ original: '', proposed: '', remark: '' });
    const [cost,       setCost]       = useState({ original: '', proposed: '', remark: '' });
    const [saving,     setSaving]     = useState(false);
    const [error,      setError]      = useState('');

    const handleSubmit = () => {
        if (!title.trim())                  { setError('Title / Subject is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Amount must be greater than zero.'); return; }
        setError(''); setSaving(true);

        router.post(route('hub.vof.store', project.id), {
            title, description: desc, amount,
            duration_days: duration,
            requestor, date_of_request: dateReq,
            priority: priorities.join(','),
            attachment,
            scope_original:    scope.original,    scope_proposed:    scope.proposed,    scope_remark:    scope.remark,
            schedule_original: schedule.original, schedule_proposed: schedule.proposed, schedule_remark: schedule.remark,
            cost_original:     cost.original,     cost_proposed:     cost.proposed,     cost_remark:     cost.remark,
        } as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title="New Variation Order" onClose={onClose} headerBg={ACCENT} size="900px"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button variant="dark" onClick={handleSubmit}>{saving ? 'Submitting…' : 'Submit Variation Order'}</Button>
                </>
            }
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>{error}</div>}

            <ModalSection color={ACCENT}>I. Variation Order Information</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '20px' }}>
                <Field label="V.O. Number">
                    <input style={{ ...inputStyle, background: '#f8fafc', color: '#94a3b8' }} placeholder="Auto-assigned on submit" readOnly />
                </Field>
                <Field label="Date of Request">
                    <input type="date" style={inputStyle} value={dateReq} onChange={e => setDateReq(e.target.value)} />
                </Field>
                <Field label="Requestor">
                    <input style={inputStyle} placeholder="Enter full name" value={requestor} onChange={e => setRequestor(e.target.value)} />
                </Field>
            </div>

            <ModalSection color={ACCENT}>II. Variation Order Details</ModalSection>
            <DetailTable scope={scope} setScope={setScope} schedule={schedule} setSchedule={setSchedule} cost={cost} setCost={setCost} />

            <ModalSection color={ACCENT}>III. Variation Order Form</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Priority">
                    <PriorityGroup priorities={priorities} onChange={setPriorities} />
                </Field>
                <Field label="Title / Subject *">
                    <input style={inputStyle} placeholder="Brief title of this VO" value={title} onChange={e => setTitle(e.target.value)} />
                </Field>
                <Field label="Amount (PhP) *">
                    <input type="number" style={inputStyle} placeholder="0.00" min="0" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} />
                </Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Description / Justification">
                    <textarea rows={3} style={inputStyle} placeholder="Describe the variation and why it is needed…" value={desc} onChange={e => setDesc(e.target.value)} />
                </Field>
                <Field label="Duration (Calendar Days)">
                    <div style={{ display: 'flex', gap: '0' }}>
                        <input type="number" min="0" step="1" style={{ ...inputStyle, borderRadius: '7px 0 0 7px', borderRight: 'none' }} placeholder="e.g., 15" value={duration} onChange={e => setDuration(e.target.value)} />
                        <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '0 7px 7px 0', fontSize: '12.5px', color: '#475569', whiteSpace: 'nowrap' }}>Days</span>
                    </div>
                </Field>
            </div>
            <Field label="Attachment (PDF / Image / Document)">
                <input type="file" style={{ ...inputStyle, padding: '6px' }}
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                    onChange={e => setAttachment(e.target.files?.[0] ?? null)} />
            </Field>
        </Modal>
    );
}

// ── Read-only detail table (View modal) ────────────────────────────────────
function ReadOnlyDetailTable({
    scope, schedule, cost,
}: {
    scope:    { original: string; proposed: string; remark: string };
    schedule: { original: string; proposed: string; remark: string };
    cost:    { original: string; proposed: string; remark: string };
}) {
    const rows = [
        { label: 'Scope',    state: scope },
        { label: 'Schedule', state: schedule },
        { label: 'Cost',     state: cost },
    ];

    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', marginBottom: '20px' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                <thead>
                    <tr style={{ background: '#fefce8' }}>
                        {['Aspect', 'Original Details', 'Proposed Change', 'Reason / Remark'].map(h => (
                            <th key={h} style={{ padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#92400e', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #fde68a' }}>{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map(({ label, state }, i) => (
                        <tr key={label} style={{ borderBottom: i < 2 ? '1px solid #f1f5f9' : 'none' }}>
                            <td style={{ padding: '8px 12px', fontWeight: 700, color: '#374151', whiteSpace: 'nowrap', verticalAlign: 'top' }}>{label}</td>
                            {(['original', 'proposed', 'remark'] as const).map(key => (
                                <td key={key} style={{ padding: '8px 12px', verticalAlign: 'top', color: '#0f172a', whiteSpace: 'pre-wrap' }}>{state[key] || '—'}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── View Modal (read-only) ─────────────────────────────────────────────────
function ViewVofModal({ vof, onClose }: { vof: VofRow; onClose: () => void }) {
    const priorities = vof.priority ? vof.priority.split(',').filter(Boolean) : [];

    return (
        <Modal title={`${vof.vo_no} — View Details`} onClose={onClose} headerBg={ACCENT} size="920px"
            footer={<Button variant="outline" onClick={onClose}>Close</Button>}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: '14px', padding: '12px 16px', border: '1px solid #fde68a', borderRadius: '8px', background: '#fffbeb', marginBottom: '20px' }}>
                <div style={{ fontSize: '20px', fontWeight: 900, color: ACCENT }}>{vof.vo_no}</div>
                <div style={{ fontSize: '12px', color: '#64748b' }}>Submitted: {vof.submitted_date}</div>
                <Badge tone={STATUS_TONE[vof.status] ?? 'slate'}>{vof.status}</Badge>
            </div>

            <ModalSection color={ACCENT}>I. Variation Order Information</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '20px' }}>
                <Field label="V.O. Number"><div>{vof.vo_no}</div></Field>
                <Field label="Date of Request"><div>{vof.date_of_request || '—'}</div></Field>
                <Field label="Requestor"><div>{vof.requestor || '—'}</div></Field>
            </div>

            <ModalSection color={ACCENT}>II. Variation Order Details</ModalSection>
            <ReadOnlyDetailTable
                scope={{ original: vof.scope_original ?? '', proposed: vof.scope_proposed ?? '', remark: vof.scope_remark ?? '' }}
                schedule={{ original: vof.schedule_original ?? '', proposed: vof.schedule_proposed ?? '', remark: vof.schedule_remark ?? '' }}
                cost={{ original: vof.cost_original ?? '', proposed: vof.cost_proposed ?? '', remark: vof.cost_remark ?? '' }}
            />

            <ModalSection color={ACCENT}>III. Variation Order Form</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Priority"><div>{priorities.length ? priorities.join(', ') : '—'}</div></Field>
                <Field label="Title / Subject"><div>{vof.title}</div></Field>
                <Field label="Amount (PhP)"><div>PhP {vof.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Description / Justification"><div style={{ whiteSpace: 'pre-wrap' }}>{vof.description || '—'}</div></Field>
                <Field label="Duration (Calendar Days)"><div>{vof.duration_days != null ? `${vof.duration_days} day${vof.duration_days === 1 ? '' : 's'}` : '—'}</div></Field>
            </div>
            <div style={{ marginBottom: '20px' }}>
                <Field label="Attachment">
                    {vof.attachment_url ? (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <a href={vof.attachment_url} target="_blank" rel="noopener noreferrer"
                                   style={{ fontSize: '12px', color: '#2563eb', fontWeight: 600, textDecoration: 'none' }}>
                                    📎 View File ↗
                                </a>
                                <VersionBadge versions={vof.versions} />
                            </div>
                            <FileHistory versions={vof.versions} />
                        </>
                    ) : <div style={{ color: '#94a3b8', fontSize: '12px' }}>No attachment.</div>}
                </Field>
            </div>

            <ModalSection color={ACCENT}>IV. Status Management</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                <Field label="Status"><div>{vof.status}</div></Field>
                <Field label="Approval / Decision Date"><div>{vof.approved_date || '—'}</div></Field>
            </div>
        </Modal>
    );
}

// ── Edit Modal ──────────────────────────────────────────────────────────────
function EditModal({ project, vof, onClose }: { project: HubProject; vof: VofRow; onClose: () => void }) {
    const [title,       setTitle]       = useState(vof.title);
    const [desc,        setDesc]        = useState(vof.description ?? '');
    const [amount,      setAmount]      = useState(String(vof.amount));
    const [duration,    setDuration]    = useState(vof.duration_days != null ? String(vof.duration_days) : '');
    const [requestor,   setRequestor]   = useState(vof.requestor ?? '');
    const [dateReq,     setDateReq]     = useState(vof.date_of_request ?? '');
    const [priorities,  setPriorities]  = useState<string[]>(vof.priority ? vof.priority.split(',').filter(Boolean) : []);
    const [newFile,     setNewFile]     = useState<File | null>(null);
    const [scope,       setScope]       = useState({ original: vof.scope_original ?? '', proposed: vof.scope_proposed ?? '', remark: vof.scope_remark ?? '' });
    const [schedule,    setSchedule]    = useState({ original: vof.schedule_original ?? '', proposed: vof.schedule_proposed ?? '', remark: vof.schedule_remark ?? '' });
    const [cost,        setCost]        = useState({ original: vof.cost_original ?? '', proposed: vof.cost_proposed ?? '', remark: vof.cost_remark ?? '' });
    const [status,      setStatus]      = useState(vof.status.toLowerCase());
    const [approvedDate,setApproved]    = useState(vof.approved_date ?? '');
    const [saving,      setSaving]      = useState(false);
    const [error,       setError]       = useState('');

    const handleSave = () => {
        if (!title.trim())                  { setError('Title is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Amount must be greater than zero.'); return; }
        setError(''); setSaving(true);

        router.post(route('hub.vof.update', [project.id, vof.id]), {
            _method: 'patch',
            title, description: desc, amount,
            duration_days: duration,
            status, approved_date: approvedDate || null,
            requestor, date_of_request: dateReq,
            priority: priorities.join(','),
            attachment: newFile,
            scope_original:    scope.original,    scope_proposed:    scope.proposed,    scope_remark:    scope.remark,
            schedule_original: schedule.original, schedule_proposed: schedule.proposed, schedule_remark: schedule.remark,
            cost_original:     cost.original,     cost_proposed:     cost.proposed,     cost_remark:     cost.remark,
        } as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            title={`${vof.vo_no} — Edit Details`}
            onClose={onClose}
            headerBg={ACCENT}
            size="920px"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button variant="dark" onClick={handleSave}>{saving ? 'Saving…' : 'Save Changes'}</Button>
                </>
            }
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>{error}</div>}

            {/* Header strip */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '14px', padding: '12px 16px', border: '1px solid #fde68a', borderRadius: '8px', background: '#fffbeb', marginBottom: '20px' }}>
                <div style={{ fontSize: '20px', fontWeight: 900, color: ACCENT }}>{vof.vo_no}</div>
                <div style={{ fontSize: '12px', color: '#64748b' }}>Submitted: {vof.submitted_date}</div>
                <Badge tone={STATUS_TONE[vof.status] ?? 'slate'}>{vof.status}</Badge>
            </div>

            <ModalSection color={ACCENT}>I. Variation Order Information</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '20px' }}>
                <Field label="V.O. Number">
                    <input style={{ ...inputStyle, background: '#f8fafc', color: '#94a3b8' }} value={vof.vo_no} readOnly />
                </Field>
                <Field label="Date of Request">
                    <input type="date" style={inputStyle} value={dateReq} onChange={e => setDateReq(e.target.value)} />
                </Field>
                <Field label="Requestor">
                    <input style={inputStyle} placeholder="Enter full name" value={requestor} onChange={e => setRequestor(e.target.value)} />
                </Field>
            </div>

            <ModalSection color={ACCENT}>II. Variation Order Details</ModalSection>
            <DetailTable scope={scope} setScope={setScope} schedule={schedule} setSchedule={setSchedule} cost={cost} setCost={setCost} />

            <ModalSection color={ACCENT}>III. Variation Order Form</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Priority">
                    <PriorityGroup priorities={priorities} onChange={setPriorities} />
                </Field>
                <Field label="Title / Subject *">
                    <input style={inputStyle} placeholder="Brief title of this VO" value={title} onChange={e => setTitle(e.target.value)} />
                </Field>
                <Field label="Amount (PhP) *">
                    <input type="number" style={inputStyle} placeholder="0.00" min="0" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} />
                </Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Description / Justification">
                    <textarea rows={3} style={inputStyle} placeholder="Describe the variation and why it is needed…" value={desc} onChange={e => setDesc(e.target.value)} />
                </Field>
                <Field label="Duration (Calendar Days)">
                    <div style={{ display: 'flex', gap: '0' }}>
                        <input type="number" min="0" step="1" style={{ ...inputStyle, borderRadius: '7px 0 0 7px', borderRight: 'none' }} placeholder="e.g., 15" value={duration} onChange={e => setDuration(e.target.value)} />
                        <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '0 7px 7px 0', fontSize: '12.5px', color: '#475569', whiteSpace: 'nowrap' }}>Days</span>
                    </div>
                </Field>
            </div>
            <div style={{ marginBottom: '20px' }}>
                <Field label="Attachment">
                    {vof.attachment_url && (
                        <div style={{ marginBottom: '6px' }}>
                            <a href={vof.attachment_url} target="_blank" rel="noopener noreferrer"
                               style={{ fontSize: '12px', color: '#2563eb', fontWeight: 600, textDecoration: 'none' }}>
                                📎 View Current File ↗
                            </a>
                            <span style={{ fontSize: '11px', color: '#94a3b8', marginLeft: '8px' }}>Upload below to replace</span>
                        </div>
                    )}
                    <input type="file" style={{ ...inputStyle, padding: '6px' }}
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        onChange={e => setNewFile(e.target.files?.[0] ?? null)} />
                </Field>
            </div>

            <ModalSection color={ACCENT}>IV. Status Management</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                <Field label="Status">
                    <select style={inputStyle} value={status} onChange={e => setStatus(e.target.value)}>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </Field>
                <Field label="Approval / Decision Date">
                    <input type="date" style={inputStyle} value={approvedDate} onChange={e => setApproved(e.target.value)} />
                </Field>
            </div>
        </Modal>
    );
}

function quickStatusBtn(bg: string, border: string, color: string, title: string, icon: React.ReactNode, onClick: () => void) {
    return (
        <button type="button" title={title} onClick={onClick} style={{ width: '30px', height: '30px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: bg, border: `1px solid ${border}`, color }}>
            {icon}
        </button>
    );
}
const CheckIcon = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg>;
const XIcon      = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>;

// ── Main Component ─────────────────────────────────────────────────────────
export default function VofHub({ project, vofs, canEdit = true }: { project: HubProject; vofs: VofRow[]; canEdit?: boolean }) {
    const [showCreate, setShowCreate] = useState(false);
    const [viewVof,    setViewVof]    = useState<VofRow | null>(null);
    const [editVof,    setEditVof]    = useState<VofRow | null>(null);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const hasSubRows = vofs.some(v => !!v.sub_project_id);

    const handleDelete = (vof: VofRow) => {
        showConfirm(`Delete variation order ${vof.vo_no}?`, () => {
            router.delete(route('hub.vof.destroy', [project.id, vof.id]), { preserveScroll: true });
        }, { title: 'Delete Variation Order', confirmLabel: 'Delete', variant: 'danger' });
    };

    const handleQuickStatus = (vof: VofRow, status: 'approved' | 'rejected') => {
        router.patch(route('hub.vof.update-status', [project.id, vof.id]), { status }, { preserveScroll: true });
    };

    return (
        <HubShell>
            {confirmDialog}
            {showCreate && <CreateModal    project={project} onClose={() => setShowCreate(false)} />}
            {viewVof    && <ViewVofModal   vof={viewVof} onClose={() => setViewVof(null)} />}
            {editVof    && <EditModal      project={project} vof={editVof} onClose={() => setEditVof(null)} />}

            <InfoStrip project={project} accent={ACCENT} />

            {/* How the project cost is composed. Derived from the project's own
                figures rather than from the rows above, which on a parent also
                list its sub-projects' variations — those move the sub-project's
                cost, not this one's. */}
            <CostBreakdown project={project} />

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <div style={{ borderLeft: `4px solid ${ACCENT}`, background: '#f8fafc', padding: '9px 14px', fontSize: '12px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                    {vofs.length > 0 ? `Variation Orders (${vofs.length})` : 'Variation Orders'}
                </div>
                {canEdit && <Button variant="dark" onClick={() => setShowCreate(true)}>+ Create Variation Order</Button>}
            </div>

            {vofs.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '48px 24px', border: '1px dashed #e2e8f0', borderRadius: '10px', color: '#94a3b8' }}>
                    <div style={{ fontSize: '28px', marginBottom: '8px' }}>📋</div>
                    <div style={{ fontWeight: 700, marginBottom: '4px', color: '#64748b' }}>No variation orders yet</div>
                    <div style={{ fontSize: '12px' }}>Click "Create Variation Order" to add one.</div>
                </div>
            ) : (
                <DataTable
                    headers={['VO No.', ...(hasSubRows ? ['Project'] : []), 'Title', 'Amount (PhP)', 'Status', 'Date Submitted', 'Actions']}
                    rows={vofs.map(vo => [
                        <strong style={{ color: ACCENT }}>{vo.vo_no}</strong>,
                        ...(hasSubRows ? [<SubTag no={vo.sub_project_no} />] : []),
                        <span style={{ fontWeight: 600, color: '#1e293b' }}>{vo.title}</span>,
                        <strong>PhP {vo.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                        <Badge tone={STATUS_TONE[vo.status] ?? 'slate'}>{vo.status}</Badge>,
                        <span style={{ fontSize: '12px', color: '#64748b' }}>{vo.submitted_date}</span>,
                        vo.sub_project_id ? (
                            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                                <ActionBtns view open onView={() => setViewVof(vo)} onOpen={() => router.visit(route('projects.hub.vof', vo.sub_project_id!))} />
                            </div>
                        ) : (
                            <div style={{ display: 'flex', gap: '4px' }}>
                                {canEdit && vo.status === 'Pending' && quickStatusBtn('#f0fdf4', '#bbf7d0', '#15803d', 'Approve', <CheckIcon />, () => handleQuickStatus(vo, 'approved'))}
                                {canEdit && vo.status === 'Pending' && quickStatusBtn('#fef2f2', '#fecaca', '#dc2626', 'Reject',  <XIcon />,     () => handleQuickStatus(vo, 'rejected'))}
                                <ActionBtns view edit={canEdit} del={canEdit} onView={() => setViewVof(vo)} onEdit={() => setEditVof(vo)} onDelete={() => handleDelete(vo)} />
                            </div>
                        ),
                    ])}
                />
            )}
        </HubShell>
    );
}
