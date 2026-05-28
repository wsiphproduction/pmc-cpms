import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionBtns, Badge, Button, DataTable, Field, HubProject, HubShell, Modal, ModalSection, inputStyle } from './Common';
import { CONTRACTORS } from './contractors';
import { useConfirm } from '@/components/useConfirm';

type RfqStatus = 'Awarded' | 'Submitted' | 'Pending' | 'Expired';

interface RfqItem { seq: number; description: string | null; qty: number | null; unit: string | null; unit_cost: number | null; total_cost: number | null }
interface RfqRow {
    id: number;
    contractor: string;
    sent: string;
    due: string;
    due_raw: string | null;
    status: RfqStatus;
    scope_of_work?: string;
    duration_days?: number | null;
    terms?: string;
    inclusions?: string;
    exclusions?: string;
    items?: RfqItem[];
}

const STATUS_TONE: Record<RfqStatus, 'yellow' | 'green' | 'slate' | 'red'> = {
    Awarded: 'yellow', Submitted: 'green', Pending: 'slate', Expired: 'red',
};

const UNITS = ['—', 'pcs', 'lot', 'set', 'unit', 'lm', 'sqm', 'cbm', 'kg', 'ton', 'hr', 'day', 'wk', 'mo', 'L', 'bag', 'roll', 'sht', 'box'];

// ── Quotation rows ─────────────────────────────────────────────────────────
function QuotationRows({ rows, onRowChange }: {
    rows: RfqItem[];
    onRowChange: (idx: number, field: keyof RfqItem, val: string | number | null) => void;
}) {
    const inp: React.CSSProperties = { border: 'none', background: 'transparent', width: '100%', fontSize: '12.5px', fontFamily: 'inherit', outline: 'none', padding: '2px 4px' };
    const grandTotal = rows.reduce((s, r) => s + Number(r.total_cost ?? 0), 0);

    return (
        <>
            {rows.map((item, i) => (
                <tr key={i} style={{ borderBottom: '1px solid #e5e7eb' }}>
                    <td style={{ padding: '6px 10px', textAlign: 'center', color: '#94a3b8', fontSize: '12px', width: '36px' }}>{i + 1}</td>
                    <td style={{ padding: '3px 5px' }}>
                        <input style={inp} placeholder="Activity description..." value={item.description ?? ''} onChange={e => onRowChange(i, 'description', e.target.value)} />
                    </td>
                    <td style={{ padding: '3px 5px', width: '70px' }}>
                        <input type="number" step="any" style={{ ...inp, textAlign: 'right' }} placeholder="—" value={item.qty ?? ''} onChange={e => onRowChange(i, 'qty', e.target.value ? Number(e.target.value) : null)} />
                    </td>
                    <td style={{ padding: '3px 5px', width: '80px' }}>
                        <select style={{ ...inp, cursor: 'pointer' }} value={item.unit ?? '—'} onChange={e => onRowChange(i, 'unit', e.target.value === '—' ? '' : e.target.value)}>
                            {UNITS.map(u => <option key={u} value={u}>{u}</option>)}
                        </select>
                    </td>
                    <td style={{ padding: '3px 5px', width: '110px' }}>
                        <input type="number" step="any" style={{ ...inp, textAlign: 'right' }} placeholder="—" value={item.unit_cost ?? ''} onChange={e => onRowChange(i, 'unit_cost', e.target.value ? Number(e.target.value) : null)} />
                    </td>
                    <td style={{ padding: '8px 10px', background: '#f8fafc', fontWeight: 700, textAlign: 'right', width: '110px', color: (item.total_cost ?? 0) > 0 ? '#2563eb' : '#94a3b8', fontSize: '12.5px' }}>
                        {(item.total_cost ?? 0) > 0
                            ? item.total_cost!.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                            : '—'}
                    </td>
                </tr>
            ))}
            <tr style={{ background: '#f8fafc' }}>
                <td colSpan={5} style={{ padding: '8px 10px', textAlign: 'right', fontWeight: 800, fontSize: '12.5px', color: '#1e293b' }}>Grand Total:</td>
                <td style={{ padding: '8px 10px', fontWeight: 900, color: '#2563eb', background: '#fefce8', textAlign: 'right' }}>
                    Php {grandTotal.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}
                </td>
            </tr>
        </>
    );
}

// ── RFQ View Modal ─────────────────────────────────────────────────────────
function RfqViewModal({ row, project, onClose }: { row: RfqRow; project: HubProject; onClose: () => void }) {
    const [scope, setScope]           = useState(row.scope_of_work ?? '');
    const [due, setDue]               = useState(row.due_raw ?? '');
    const [duration, setDuration]     = useState(row.duration_days?.toString() ?? '');
    const [terms, setTerms]           = useState(row.terms ?? '');
    const [inclusions, setInclusions] = useState(row.inclusions ?? '');
    const [exclusions, setExclusions] = useState(row.exclusions ?? '');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');
    const [rows, setRows]             = useState<RfqItem[]>(() =>
        Array.from({ length: 10 }, (_, i) => {
            const src = row.items?.[i];
            return {
                seq:         i + 1,
                description: src?.description ?? null,
                qty:         src?.qty        != null ? Number(src.qty)        : null,
                unit:        src?.unit        ?? null,
                unit_cost:   src?.unit_cost   != null ? Number(src.unit_cost)  : null,
                total_cost:  src?.total_cost  != null ? Number(src.total_cost) : null,
            };
        })
    );

    const updateRow = (idx: number, field: keyof RfqItem, val: string | number | null) => {
        setRows(prev => {
            const next = [...prev];
            const r = { ...next[idx], [field]: val };
            if (field === 'qty' || field === 'unit_cost') {
                const q  = field === 'qty'       ? (val as number | null) : r.qty;
                const uc = field === 'unit_cost'  ? (val as number | null) : r.unit_cost;
                r.total_cost = (q != null && uc != null) ? Math.round(q * uc * 100) / 100 : null;
            }
            next[idx] = r;
            return next;
        });
    };

    const thStyle: React.CSSProperties = { padding: '8px 10px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', background: '#f1f5f9', borderBottom: '1px solid #e5e7eb' };

    const handleSave = () => {
        if (!scope.trim()) { setError('Scope of Work is required.'); return; }
        setError('');
        setSaving(true);
        router.patch(route('hub.rfq.update', [project.id, row.id]), {
            scope_of_work:    scope,
            due_date:         due || null,
            duration_days:    duration || null,
            terms_conditions: terms,
            inclusions,
            exclusions,
            items: rows.filter(r => r.description?.trim()) as any,
        }, { preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false) });
    };

    return (
        <Modal title="RFQ & Quotation Details" onClose={onClose} size="900px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                <button type="button" onClick={handleSave} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Saving...' : 'Save Quotation Changes'}
                </button>
            </>}
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>{error}</div>}

            <ModalSection>I. Project Specifications</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '14px', marginBottom: '22px' }}>
                {[
                    ['Service Contractor', row.contractor],
                    ['Date Sent',          row.sent],
                    ['Project Number',     project.project_no],
                    ['Project Title',      project.title],
                    ['Project Owner',      project.project_manager],
                ].map(([label, value]) => (
                    <div key={label}>
                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '3px' }}>{label}</div>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e293b' }}>{value}</div>
                    </div>
                ))}
                <div>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '3px' }}>Date Needed</div>
                    <input type="date" value={due} onChange={e => setDue(e.target.value)} style={{ ...inputStyle, fontSize: '13px', fontWeight: 700 }} />
                </div>
                <div style={{ gridColumn: '1/-1' }}>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '3px' }}>Job Site / Location</div>
                    <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e293b' }}>{project.site}</div>
                </div>
                <div style={{ gridColumn: '1/-1' }}>
                    <Field label="Scope of Work *">
                        <textarea rows={3} value={scope} onChange={e => { setScope(e.target.value); setError(''); }} style={{ ...inputStyle, resize: 'vertical' }} placeholder="Describe the full scope of work..." />
                    </Field>
                </div>
            </div>

            <ModalSection>II. Itemized Quotation</ModalSection>
            <div style={{ border: '1px solid #e5e7eb', borderRadius: '6px', overflow: 'hidden', marginBottom: '22px' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px' }}>
                    <thead>
                        <tr>
                            {['Seq', 'Deliverables or Activities', 'Qty', 'Unit', 'Unit Cost', 'Total Cost'].map(h => (
                                <th key={h} style={thStyle}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody><QuotationRows rows={rows} onRowChange={updateRow} /></tbody>
                </table>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '22px' }}>
                <div>
                    <ModalSection>III. Execution Timeline</ModalSection>
                    <Field label="Target Project Duration">
                        <div style={{ display: 'flex', gap: '0' }}>
                            <input type="number" value={duration} onChange={e => setDuration(e.target.value)} placeholder="e.g., 45" style={{ ...inputStyle, borderRadius: '7px 0 0 7px', borderRight: 'none' }} />
                            <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '0 7px 7px 0', fontSize: '12.5px', color: '#475569', whiteSpace: 'nowrap' }}>Calendar Days</span>
                        </div>
                    </Field>
                </div>
                <div>
                    <ModalSection>IV. Legal & Technical Provisions</ModalSection>
                    <Field label="Terms and Conditions">
                        <textarea rows={2} value={terms} onChange={e => setTerms(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '10px' }}>
                        <Field label="Inclusions">
                            <textarea rows={3} value={inclusions} onChange={e => setInclusions(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} placeholder="What is included..." />
                        </Field>
                        <Field label="Exclusions">
                            <textarea rows={3} value={exclusions} onChange={e => setExclusions(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} placeholder="What is not included..." />
                        </Field>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

// ── NTP Modal ──────────────────────────────────────────────────────────────
function NtpModal({ row, project, onClose }: { row: RfqRow; project: HubProject; onClose: () => void }) {
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const [cost, setCost] = useState(project.budget_total?.toString() ?? '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const handleIssue = () => {
        if (!start || !end) { setError('Baseline Start and End dates are required.'); return; }
        if (!cost || Number(cost) <= 0) { setError('Approved project cost must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.ntp.store', project.id), {
            contractor_name: row.contractor,
            project_rfq_id:  row.id,
            baseline_start:  start,
            baseline_end:    end,
            approved_cost:   cost,
        }, { preserveScroll: true, onFinish: () => setSaving(false), onSuccess: onClose });
    };

    return (
        <Modal title="Generate Notice to Proceed" onClose={onClose} headerBg="#059669" size="480px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleIssue} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Issuing...' : 'Issue Notice to Proceed'}
                </button>
            </>}
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '12px' }}>{error}</div>}
            <div style={{ padding: '10px 14px', background: '#e0f2fe', border: '1px solid #bae6fd', borderRadius: '8px', marginBottom: '18px', fontSize: '12.5px', color: '#075985' }}>
                Issuing NTP for: <strong>{row.contractor}</strong>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Baseline Start Date"><input type="date" style={inputStyle} value={start} onChange={e => setStart(e.target.value)} /></Field>
                <Field label="Baseline End Date"><input type="date" style={inputStyle} value={end} onChange={e => setEnd(e.target.value)} /></Field>
            </div>
            <Field label="Approved Project Cost">
                <div style={{ display: 'flex', gap: '0' }}>
                    <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '7px 0 0 7px', fontSize: '13px', fontWeight: 700, color: '#475569' }}>Php</span>
                    <input type="number" step="0.01" value={cost} onChange={e => setCost(e.target.value)} style={{ ...inputStyle, borderRadius: '0 7px 7px 0', borderLeft: 'none', fontWeight: 700, color: '#2563eb' }} />
                </div>
                <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>Default project budget assigned to this contract.</div>
            </Field>
        </Modal>
    );
}

// ── Success Modal ──────────────────────────────────────────────────────────
function SuccessModal({ contractor, onClose }: { contractor: string; onClose: () => void }) {
    return (
        <Modal title="Request Sent" onClose={onClose} size="400px"
            footer={<button type="button" onClick={onClose} style={{ padding: '7px 28px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Close</button>}
        >
            <div style={{ textAlign: 'center', padding: '16px 0 8px' }}>
                <svg width="56" height="56" viewBox="0 0 24 24" fill="#22c55e"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" fill="none" stroke="#22c55e" strokeWidth="2"/><polyline points="22 4 12 14.01 9 11.01" fill="none" stroke="#22c55e" strokeWidth="2.5"/></svg>
                <h3 style={{ margin: '12px 0 6px', fontWeight: 800, color: '#0f172a' }}>Request Sent!</h3>
                <p style={{ color: '#64748b', fontSize: '13.5px', margin: 0, lineHeight: 1.6 }}>
                    Request for Quotation was successfully sent to<br /><strong style={{ color: '#0f172a' }}>{contractor}</strong>
                </p>
            </div>
        </Modal>
    );
}

// ── Main Component ─────────────────────────────────────────────────────────
export default function RfqHub({ project, rfqs }: { project: HubProject; rfqs: RfqRow[] }) {
    const [dispatchContractor, setDispatchContractor] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [showSuccess, setShowSuccess] = useState(false);
    const [sentContractor, setSentContractor] = useState('');
    const [viewRow, setViewRow]         = useState<RfqRow | null>(null);
    const [ntpRow, setNtpRow]           = useState<RfqRow | null>(null);
    const [sending, setSending]         = useState(false);
    const [sendError, setSendError]     = useState('');

    const handleSend = () => {
        if (!dispatchContractor) { setSendError('Please select a contractor before sending.'); return; }
        setSendError('');
        setSending(true);
        router.post(route('hub.rfq.store', project.id), {
            contractor_name: dispatchContractor,
            due_date: dueDate || null,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSentContractor(dispatchContractor); setShowSuccess(true); setDispatchContractor(''); setDueDate(''); },
            onFinish: () => setSending(false),
        });
    };

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (rfq: RfqRow) => {
        showConfirm(`Delete RFQ for ${rfq.contractor}?`, () => {
            router.delete(route('hub.rfq.destroy', [project.id, rfq.id]), { preserveScroll: true });
        }, { title: 'Delete RFQ', confirmLabel: 'Delete', variant: 'danger' });
    };

    const handleStatus = (rfq: RfqRow, status: string, label: string) => {
        showConfirm(`Mark RFQ for "${rfq.contractor}" as ${label}?`, () => {
            router.patch(route('hub.rfq.update-status', [project.id, rfq.id]), { status }, { preserveScroll: true });
        }, { title: 'Update Status', confirmLabel: 'Confirm', variant: 'warning' });
    };

    const actionCell = (row: RfqRow) => {
        if (row.status === 'Awarded') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <ActionBtns view onView={() => setViewRow(row)} />
                <ActionBtns print />
                <Button variant="success" onClick={() => setNtpRow(row)}>Create NTP</Button>
            </div>
        );
        if (row.status === 'Submitted') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <ActionBtns view onView={() => setViewRow(row)} />
                <ActionBtns print />
                <ActionBtns trophy onTrophy={() => handleStatus(row, 'awarded', 'Awarded')} />
                <ActionBtns del onDelete={() => handleDelete(row)} />
            </div>
        );
        if (row.status === 'Pending') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <Button variant="outline" onClick={() => setViewRow(row)}>+ Quotation</Button>
                <button
                    type="button"
                    title="Mark as Received / Submitted"
                    onClick={() => handleStatus(row, 'submitted', 'Submitted')}
                    style={{ padding: '5px 10px', borderRadius: '6px', border: '1px solid #bbf7d0', background: '#f0fdf4', color: '#15803d', fontSize: '11px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}
                >
                    ✓ Received
                </button>
                <button
                    type="button"
                    title="Mark as Expired"
                    onClick={() => handleStatus(row, 'expired', 'Expired')}
                    style={{ padding: '5px 10px', borderRadius: '6px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', fontSize: '11px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}
                >
                    Expired
                </button>
                <ActionBtns del onDelete={() => handleDelete(row)} />
            </div>
        );
        return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <ActionBtns refresh onRefresh={() => handleStatus(row, 'pending', 'Re-activated')} />
                <ActionBtns del onDelete={() => handleDelete(row)} />
            </div>
        );
    };

    return (
        <HubShell>
            {confirmDialog}
            {showSuccess   && <SuccessModal contractor={sentContractor} onClose={() => setShowSuccess(false)} />}
            {viewRow       && <RfqViewModal row={viewRow} project={project} onClose={() => setViewRow(null)} />}
            {ntpRow        && <NtpModal row={ntpRow} project={project} onClose={() => setNtpRow(null)} />}

            <h3 style={{ margin: '0 0 18px', color: '#2563eb', fontSize: '18px' }}>Dispatch New RFQ</h3>

            <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 180px', gap: '14px', alignItems: 'end' }}>
                    <Field label="Select Contractor">
                        <select style={inputStyle} value={dispatchContractor} onChange={e => setDispatchContractor(e.target.value)}>
                            <option value="" disabled>Choose from registered contractors...</option>
                            {CONTRACTORS.map(name => (
                                <option key={name}>{name}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Due Date (Optional)">
                        <input type="date" style={inputStyle} value={dueDate} onChange={e => setDueDate(e.target.value)} />
                    </Field>
                    <div>
                        <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                        <button type="button" onClick={handleSend} disabled={sending} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            {sending ? 'Sending...' : 'Send RFQ'}
                        </button>
                    </div>
                </div>
                {sendError && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{sendError}</div>}
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px', alignItems: 'center' }}>
                <strong style={{ color: '#475569' }}>RFQ Dispatch & Quotation Tracking</strong>
                <span style={{ padding: '3px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px', fontSize: '12px', color: '#475569' }}>Total Records: {rfqs.length}</span>
            </div>

            <DataTable
                headers={['Contractor Name', 'Sent Date', 'Due Date', 'Status', 'Actions']}
                rows={rfqs.map(row => [
                    <strong>{row.contractor}</strong>,
                    row.sent,
                    row.due,
                    <Badge tone={STATUS_TONE[row.status]}>{row.status}</Badge>,
                    actionCell(row),
                ])}
            />
        </HubShell>
    );
}
