import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ActionBtns, Badge, Button, DataTable, Field, HubProject, HubShell, Modal, ModalSection, SubTag, inputStyle } from './Common';
import { SendConfirmModal } from './SendConfirmModal';
import { useConfirm } from '@/components/useConfirm';

type RfqStatus = 'Awarded' | 'Submitted' | 'Pending' | 'Expired';

interface RfqItem { seq: number; description: string | null; qty: number | null; unit: string | null; unit_cost: number | null; total_cost: number | null }

/** One offer against an RFQ. Exactly one per RFQ is `is_final`. */
interface QuotationRow {
    id: number;
    seq: number;
    label: string | null;
    /** The label, or "Quotation #N" when it has none. */
    name: string;
    is_final: boolean;
    /** Supplier lifecycle: a draft is not yet on the table. */
    status: 'draft' | 'submitted' | 'received';
    /** 'supplier' when filled in through the portal, 'staff' when typed here. */
    origin: 'staff' | 'supplier';
    /** False while the supplier still has it as an unsent draft. */
    selectable: boolean;
    submitted_at: string | null;
    received_at: string | null;
    due: string;
    due_raw: string | null;
    scope_of_work: string | null;
    duration_days: number | null;
    terms: string | null;
    inclusions: string | null;
    exclusions: string | null;
    quotation_file: string | null;
    grand_total: number;
    created_at: string;
    items: RfqItem[];
}

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
    quotation_file?: string | null;
    recipient_email?: string | null;
    has_ntp: boolean;
    ntp_status?: 'pending_review' | 'issued' | 'rejected' | null;
    audit_trail?: { action: string; user: string; date: string; type: string; fields?: { field: string; old: string; new: string }[] }[];
    /** The final quotation's items — what this row is costed and NTP'd on. */
    items?: RfqItem[];
    quotations: QuotationRow[];
    final_quotation_id: number | null;
    sub_project_id: number | null;
    sub_project_no: string | null;
}

const STATUS_TONE: Record<RfqStatus, 'yellow' | 'green' | 'slate' | 'red'> = {
    Awarded: 'yellow', Submitted: 'green', Pending: 'slate', Expired: 'red',
};

/** A supplier may list several mailboxes; the first is the recipient, the rest are CC'd. */
function splitEmails(value: string | null | undefined): string[] {
    return (value ?? '').split(/[,;]+/).map(e => e.trim()).filter(Boolean);
}

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
function RfqViewModal({ row, quotation, project, onClose, canEdit = true }: { row: RfqRow; quotation: QuotationRow; project: HubProject; onClose: () => void; canEdit?: boolean }) {
    const [label, setLabel]           = useState(quotation.label ?? '');
    const [scope, setScope]           = useState(quotation.scope_of_work ?? '');
    const [due, setDue]               = useState(quotation.due_raw ?? '');
    const [duration, setDuration]     = useState(quotation.duration_days?.toString() ?? '');
    const [terms, setTerms]           = useState(quotation.terms ?? '');
    const [inclusions, setInclusions] = useState(quotation.inclusions ?? '');
    const [exclusions, setExclusions] = useState(quotation.exclusions ?? '');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');
    const [quotationFile, setQuotationFile] = useState<File | null>(null);
    const [rows, setRows]             = useState<RfqItem[]>(() =>
        Array.from({ length: 10 }, (_, i) => {
            const src = quotation.items?.[i];
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
        // All fields are required except the file attachment.
        const filledItems = rows.filter(r => r.description?.trim());
        const hasTotal = filledItems.some(r => Number(r.total_cost ?? 0) > 0);
        if (!scope.trim())        { setError('Scope of Work is required.'); return; }
        if (!due)                 { setError('Date Needed is required.'); return; }
        if (filledItems.length === 0 || !hasTotal) { setError('An itemized quotation with at least one costed item is required.'); return; }
        if (!duration || Number(duration) <= 0) { setError('Target Project Duration (calendar days) is required.'); return; }
        if (!terms.trim())        { setError('Terms and Conditions are required.'); return; }
        if (!inclusions.trim())   { setError('Inclusions are required.'); return; }
        if (!exclusions.trim())   { setError('Exclusions are required.'); return; }
        setError('');
        setSaving(true);

        const basePayload = {
            label:            label.trim() || null,
            scope_of_work:    scope,
            due_date:         due || null,
            duration_days:    duration || null,
            terms_conditions: terms,
            inclusions,
            exclusions,
            items: filledItems as any,
        };

        const opts = { preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false) };

        const target = route('hub.rfq.quotations.update', [project.id, row.id, quotation.id]);

        if (quotationFile) {
            // PHP only parses $_FILES for POST — use method-spoofed POST so the file is accessible
            router.post(target, { ...basePayload, _method: 'patch', quotation_file: quotationFile }, opts);
        } else {
            router.patch(target, basePayload, opts);
        }
    };

    return (
        <Modal title={`${quotation.name}${quotation.is_final ? ' (Final)' : ''} — ${row.contractor}`} onClose={onClose} size="900px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                {canEdit && (
                    <button type="button" onClick={handleSave} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                        {saving ? 'Saving...' : 'Save Quotation Changes'}
                    </button>
                )}
            </>}
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>{error}</div>}

            <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-end', marginBottom: '18px' }}>
                <div style={{ flex: 1 }}>
                    <Field label="Quotation Label (Optional)">
                        <input
                            value={label}
                            onChange={e => setLabel(e.target.value)}
                            placeholder={`e.g. Revised offer — defaults to "Quotation #${quotation.seq}"`}
                            disabled={!canEdit}
                            style={inputStyle}
                        />
                    </Field>
                </div>
                <div style={{ paddingBottom: '8px' }}>
                    {quotation.is_final
                        ? <Badge tone="green">Final quotation</Badge>
                        : <Badge tone="slate">Not final</Badge>}
                </div>
            </div>

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
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '3px' }}>Date Needed *</div>
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
                    <Field label="Target Project Duration *">
                        <div style={{ display: 'flex', gap: '0' }}>
                            <input type="number" value={duration} onChange={e => setDuration(e.target.value)} placeholder="e.g., 45" style={{ ...inputStyle, borderRadius: '7px 0 0 7px', borderRight: 'none' }} />
                            <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '0 7px 7px 0', fontSize: '12.5px', color: '#475569', whiteSpace: 'nowrap' }}>Calendar Days</span>
                        </div>
                    </Field>
                </div>
                <div>
                    <ModalSection>IV. Legal & Technical Provisions</ModalSection>
                    <Field label="Terms and Conditions *">
                        <textarea rows={2} value={terms} onChange={e => setTerms(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '10px' }}>
                        <Field label="Inclusions *">
                            <textarea rows={3} value={inclusions} onChange={e => setInclusions(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} placeholder="What is included..." />
                        </Field>
                        <Field label="Exclusions *">
                            <textarea rows={3} value={exclusions} onChange={e => setExclusions(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} placeholder="What is not included..." />
                        </Field>
                    </div>
                </div>
            </div>

            <ModalSection>V. Quotation File Attachment</ModalSection>
            <div style={{ background: '#f8fafc', border: '1.5px solid #e2e8f0', borderRadius: '8px', padding: '16px', display: 'flex', gap: '18px', alignItems: 'flex-start' }}>
                <div style={{ flex: 1 }}>
                    <Field label="Upload Quotation File (PDF, Word, Excel, Image — max 20 MB)">
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                            onChange={e => setQuotationFile(e.target.files?.[0] ?? null)}
                            style={{ ...inputStyle, padding: '6px 8px', fontSize: '12.5px', cursor: 'pointer' }}
                        />
                    </Field>
                    {quotationFile && (
                        <div style={{ marginTop: '6px', fontSize: '12px', color: '#2563eb', fontWeight: 600 }}>
                            Selected: {quotationFile.name}
                        </div>
                    )}
                </div>
                {quotation.quotation_file && (
                    <div style={{ flexShrink: 0, paddingTop: '20px' }}>
                        <a
                            href={quotation.quotation_file}
                            target="_blank"
                            rel="noreferrer"
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: '1px solid #bfdbfe', background: '#eff6ff', color: '#2563eb', fontSize: '12px', fontWeight: 700, textDecoration: 'none' }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            View Current File
                        </a>
                        <div style={{ marginTop: '4px', fontSize: '11px', color: '#94a3b8' }}>Upload new file to replace</div>
                    </div>
                )}
            </div>
        </Modal>
    );
}

const QUOTE_STATUS: Record<QuotationRow['status'], { tone: 'slate' | 'blue' | 'green'; label: string }> = {
    draft:     { tone: 'slate', label: 'Supplier draft' },
    submitted: { tone: 'blue',  label: 'Submitted' },
    received:  { tone: 'green', label: 'Received' },
};

// ── Quotations Modal ───────────────────────────────────────────────────────
// A vendor may put several offers against one RFQ. They are all listed here;
// whichever is marked final is the one the hub table, the printed form and any
// NTP raised from this RFQ use.
function QuotationsModal({ row, project, canEdit, onClose, onEdit }: {
    row: RfqRow;
    project: HubProject;
    canEdit: boolean;
    onClose: () => void;
    onEdit: (quotation: QuotationRow) => void;
}) {
    const [adding, setAdding]   = useState(false);
    const [label, setLabel]     = useState('');
    const [copyFrom, setCopyFrom] = useState<string>('');
    const [busy, setBusy]       = useState(false);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const quotations = row.quotations ?? [];
    const peso = (n: number) => `PhP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const handleAdd = () => {
        setBusy(true);
        router.post(route('hub.rfq.quotations.store', [project.id, row.id]), {
            label:     label.trim() || null,
            copy_from: copyFrom || null,
        }, {
            preserveScroll: true,
            onSuccess: () => { setAdding(false); setLabel(''); setCopyFrom(''); },
            onFinish:  () => setBusy(false),
        });
    };

    const handleReceive = (q: QuotationRow) => {
        showConfirm(
            `Mark ${q.name} from ${row.contractor} as received? ${row.contractor} will no longer be able to edit it.`,
            () => router.patch(route('hub.rfq.quotations.received', [project.id, row.id, q.id]), {}, { preserveScroll: true }),
            { title: 'Mark Received', confirmLabel: 'Mark Received', variant: 'warning' },
        );
    };

    const handleSetFinal = (q: QuotationRow) => {
        showConfirm(
            `Use ${q.name} as the final quotation for ${row.contractor}? The RFQ table, the printed form and any NTP will follow this offer.`,
            () => router.patch(route('hub.rfq.quotations.final', [project.id, row.id, q.id]), {}, { preserveScroll: true }),
            { title: 'Set Final Quotation', confirmLabel: 'Set as Final', variant: 'warning' },
        );
    };

    const handleDelete = (q: QuotationRow) => {
        showConfirm(
            `Delete ${q.name} for ${row.contractor}? Its line items and attachment go with it.`,
            () => router.delete(route('hub.rfq.quotations.destroy', [project.id, row.id, q.id]), { preserveScroll: true }),
            { title: 'Delete Quotation', confirmLabel: 'Delete', variant: 'danger' },
        );
    };

    const smallBtn = (text: string, onClick: () => void, tone: 'blue' | 'green' | 'red' | 'plain'): React.ReactNode => {
        const palette = {
            blue:  ['#eff6ff', '#bfdbfe', '#2563eb'],
            green: ['#f0fdf4', '#bbf7d0', '#15803d'],
            red:   ['#fef2f2', '#fecaca', '#dc2626'],
            plain: ['#fff',    '#e2e8f0', '#475569'],
        }[tone];
        return (
            <button type="button" onClick={onClick}
                style={{ padding: '5px 11px', borderRadius: '6px', background: palette[0], border: `1px solid ${palette[1]}`, color: palette[2], fontSize: '11.5px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                {text}
            </button>
        );
    };

    return (
        <Modal title={`Quotations — ${row.contractor}`} onClose={onClose} size="820px" headerBg="#1e3a8a"
            footer={<button type="button" onClick={onClose} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Close</button>}
        >
            {confirmDialog}

            <div style={{ padding: '10px 14px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '8px', marginBottom: '18px', fontSize: '12.5px', color: '#1e40af', lineHeight: 1.6 }}>
                {row.contractor} fills these in through the link in their RFQ email. Mark one <strong>Received</strong> to close it for further edits, then set it <strong>Final</strong> — that is the offer the RFQ table shows and the one an NTP is issued from.
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                {quotations.length === 0 && (
                    <div style={{ textAlign: 'center', padding: '22px 0', color: '#94a3b8', fontSize: '13px' }}>
                        No quotations recorded yet.
                    </div>
                )}

                {quotations.map(q => (
                    <div key={q.id} style={{
                        border: `1.5px solid ${q.is_final ? '#86efac' : '#e2e8f0'}`,
                        background: q.is_final ? '#f0fdf4' : '#fff',
                        borderRadius: '9px', padding: '13px 16px',
                    }}>
                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap' }}>
                            <div style={{ flex: 1, minWidth: '220px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px', flexWrap: 'wrap' }}>
                                    <strong style={{ fontSize: '13.5px', color: '#0f172a' }}>{q.name}</strong>
                                    {q.is_final && <Badge tone="green">Final</Badge>}
                                    {q.origin === 'supplier' && <Badge tone={QUOTE_STATUS[q.status].tone}>{QUOTE_STATUS[q.status].label}</Badge>}
                                </div>
                                <div style={{ fontSize: '11.5px', color: '#64748b' }}>
                                    {q.origin === 'supplier' ? `From ${row.contractor}` : 'Entered by the project team'} · Added {q.created_at}
                                    {q.submitted_at ? ` · sent ${q.submitted_at}` : ''}
                                    {q.received_at && q.origin === 'supplier' ? ` · received ${q.received_at}` : ''}
                                    {q.duration_days ? ` · ${q.duration_days} calendar day${q.duration_days === 1 ? '' : 's'}` : ''}
                                    {q.due_raw ? ` · due ${q.due}` : ''}
                                    {` · ${q.items.length} item${q.items.length === 1 ? '' : 's'}`}
                                </div>
                                {q.scope_of_work && (
                                    <div style={{ fontSize: '12px', color: '#475569', marginTop: '5px', lineHeight: 1.5 }}>
                                        {q.scope_of_work.length > 120 ? `${q.scope_of_work.slice(0, 120)}…` : q.scope_of_work}
                                    </div>
                                )}
                            </div>
                            <div style={{ textAlign: 'right', minWidth: '130px' }}>
                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Total</div>
                                <strong style={{ fontSize: '14px', color: q.grand_total > 0 ? '#2563eb' : '#cbd5e1' }}>
                                    {q.grand_total > 0 ? peso(q.grand_total) : '—'}
                                </strong>
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: '6px', marginTop: '11px', flexWrap: 'wrap', alignItems: 'center' }}>
                            {smallBtn(canEdit ? 'Edit quotation' : 'View quotation', () => onEdit(q), 'blue')}
                            {canEdit && q.status === 'submitted' && smallBtn('Mark received', () => handleReceive(q), 'green')}
                            {q.quotation_file && (
                                <a href={q.quotation_file} target="_blank" rel="noreferrer"
                                    style={{ padding: '5px 11px', borderRadius: '6px', background: '#fff', border: '1px solid #e2e8f0', color: '#475569', fontSize: '11.5px', fontWeight: 700, textDecoration: 'none', whiteSpace: 'nowrap' }}>
                                    Attached file
                                </a>
                            )}
                            {canEdit && !q.is_final && (q.selectable
                                ? smallBtn('Set as final', () => handleSetFinal(q), 'green')
                                : <span style={{ fontSize: '11px', color: '#94a3b8', fontWeight: 600 }}>
                                    Awaiting the supplier to send it
                                </span>)}
                            {canEdit && quotations.length > 1 && smallBtn('Delete', () => handleDelete(q), 'red')}
                        </div>
                    </div>
                ))}
            </div>

            {canEdit && (
                <div style={{ marginTop: '18px', borderTop: '1px solid #e5e7eb', paddingTop: '16px' }}>
                    {adding ? (
                        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '9px', padding: '14px' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                                <Field label="Label (Optional)">
                                    <input value={label} onChange={e => setLabel(e.target.value)} placeholder="e.g. Revised offer" style={inputStyle} />
                                </Field>
                                <Field label="Start From">
                                    <select value={copyFrom} onChange={e => setCopyFrom(e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                        <option value="">Blank quotation</option>
                                        {quotations.map(q => <option key={q.id} value={q.id}>Copy of {q.name}</option>)}
                                    </select>
                                </Field>
                            </div>
                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '12px' }}>
                                <button type="button" onClick={() => setAdding(false)} style={{ padding: '7px 16px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                                <button type="button" onClick={handleAdd} disabled={busy} style={{ padding: '7px 20px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                                    {busy ? 'Adding…' : 'Add Quotation'}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <Button onClick={() => setAdding(true)}>+ Add Quotation</Button>
                    )}
                </div>
            )}
        </Modal>
    );
}

// ── NTP Modal ──────────────────────────────────────────────────────────────
// Add N calendar days to a YYYY-MM-DD date, returning YYYY-MM-DD.
// Number(days) guards against `days` arriving as a string (e.g. "30"), which
// would otherwise make `getDate() + days` concatenate instead of add.
function todayIso(): string {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function addDays(dateStr: string, days: number): string {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() + Number(days));
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`;
}

function NtpModal({ row, project, onClose }: { row: RfqRow; project: HubProject; onClose: () => void }) {
    // Auto-fill the approved cost from the RFQ's itemized quotation total.
    const rfqTotal = (row.items ?? []).reduce((s, i) => s + Number(i.total_cost ?? 0), 0);
    const calendarDays = row.duration_days ?? null;

    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const [cost, setCost] = useState((rfqTotal > 0 ? rfqTotal : (project.budget_total ?? 0)).toString());
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    // Back-dating is legitimate when the paperwork lags the work, so a past
    // baseline is a warning rather than a block — but it is far more often a
    // typo or a stale draft, and it skews every schedule metric downstream.
    const today = todayIso();
    const pastDates = [
        start && start < today ? 'Baseline Start' : null,
        end && end < today ? 'Baseline End' : null,
    ].filter(Boolean) as string[];

    // Baseline end date is auto-suggested from the start date + the RFQ's target
    // duration (calendar days) whenever the start changes, but stays editable so
    // the user can override it.
    useEffect(() => {
        if (calendarDays && start) setEnd(addDays(start, calendarDays));
    }, [start, calendarDays]);

    const handleIssue = () => {
        if (!start) { setError('Baseline Start date is required.'); return; }
        if (!end)   { setError('Baseline End date is required.'); return; }
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
        <Modal title="Submit Notice to Proceed for Review" onClose={onClose} headerBg="#059669" size="480px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleIssue} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Submitting...' : 'Submit for Review'}
                </button>
            </>}
        >
            {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '12px' }}>{error}</div>}
            {pastDates.length > 0 && (
                <div style={{ display: 'flex', gap: '9px', padding: '10px 14px', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '8px', color: '#92400e', fontSize: '12.5px', marginBottom: '12px', lineHeight: 1.6 }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '2px' }}>
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    <span>
                        <strong>{pastDates.join(' and ')}</strong> {pastDates.length > 1 ? 'are' : 'is'} earlier than today.
                        Check the date{pastDates.length > 1 ? 's' : ''} before submitting — you can still proceed if the work genuinely started already.
                    </span>
                </div>
            )}
            <div style={{ padding: '10px 14px', background: '#e0f2fe', border: '1px solid #bae6fd', borderRadius: '8px', marginBottom: '18px', fontSize: '12.5px', color: '#075985' }}>
                This NTP will be sent to the department user for review before it is issued.<br />
                For: <strong>{row.contractor}</strong>
                {calendarDays != null && <> · Duration: <strong>{calendarDays} calendar day{calendarDays === 1 ? '' : 's'}</strong></>}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Baseline Start Date"><input type="date" style={inputStyle} value={start} onChange={e => setStart(e.target.value)} /></Field>
                <Field label="Baseline End Date">
                    <input
                        type="date"
                        style={inputStyle}
                        value={end}
                        onChange={e => setEnd(e.target.value)}
                    />
                    {calendarDays
                        ? <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>Auto-filled from start + {calendarDays} calendar day{calendarDays === 1 ? '' : 's'} — you can adjust it.</div>
                        : <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>No RFQ duration set — enter the end date manually.</div>}
                </Field>
            </div>
            <Field label="Approved Project Cost">
                <div style={{ display: 'flex', gap: '0' }}>
                    <span style={{ padding: '8px 12px', background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '7px 0 0 7px', fontSize: '13px', fontWeight: 700, color: '#475569' }}>Php</span>
                    <input type="number" step="0.01" value={cost} onChange={e => setCost(e.target.value)} style={{ ...inputStyle, borderRadius: '0 7px 7px 0', borderLeft: 'none', fontWeight: 700, color: '#2563eb' }} />
                </div>
                <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>
                    {rfqTotal > 0 ? 'Auto-filled from the RFQ itemized quotation total. You can override it.' : 'Default project budget assigned to this contract.'}
                </div>
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

// ── Supplier Select (select2-style searchable dropdown) ────────────────────
function SupplierSelect({ suppliers, value, onChange, usedContractors }: {
    suppliers: { name: string; email: string }[];
    value: string;
    onChange: (name: string) => void;
    usedContractors: Set<string>;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onClickAway = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) { setOpen(false); setQuery(''); }
        };
        document.addEventListener('mousedown', onClickAway);
        return () => document.removeEventListener('mousedown', onClickAway);
    }, []);

    const needle = query.trim().toLowerCase();
    const filtered = needle
        ? suppliers.filter(s => s.name.toLowerCase().includes(needle) || s.email.toLowerCase().includes(needle))
        : suppliers;

    return (
        <div ref={wrapRef} style={{ position: 'relative' }}>
            <input
                value={open ? query : value}
                placeholder={suppliers.length ? 'Search suppliers…' : 'No suppliers in master data yet'}
                role="combobox"
                aria-expanded={open}
                autoComplete="off"
                onFocus={() => setOpen(true)}
                onChange={e => { setQuery(e.target.value); setOpen(true); }}
                style={{ ...inputStyle, paddingRight: '30px', cursor: 'text' }}
            />
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"
                style={{ position: 'absolute', right: '11px', top: '11px', pointerEvents: 'none' }}>
                <polyline points="6 9 12 15 18 9" />
            </svg>
            {open && (
                <div role="listbox" style={{ position: 'absolute', top: 'calc(100% + 4px)', left: 0, right: 0, zIndex: 60, background: '#fff', border: '1px solid #e2e8f0', borderRadius: '7px', boxShadow: '0 12px 28px rgba(15,23,42,0.14)', maxHeight: '240px', overflowY: 'auto' }}>
                    {filtered.length ? filtered.map(s => {
                        const used = usedContractors.has(s.name);
                        return (
                            <button
                                key={s.name}
                                type="button"
                                disabled={used}
                                onMouseDown={e => e.preventDefault()}
                                onClick={() => { if (!used) { onChange(s.name); setOpen(false); setQuery(''); } }}
                                style={{ width: '100%', border: 'none', textAlign: 'left', cursor: used ? 'not-allowed' : 'pointer', padding: '8px 12px', fontSize: '13px', background: s.name === value ? '#eff6ff' : '#fff', opacity: used ? 0.5 : 1 }}
                            >
                                <div style={{ fontWeight: 600, color: '#1e293b' }}>{s.name}{used && <span style={{ fontWeight: 400, color: '#94a3b8', fontSize: '11.5px' }}> · RFQ already sent</span>}</div>
                                {s.email && <div style={{ fontSize: '11px', color: '#94a3b8' }}>{s.email}</div>}
                            </button>
                        );
                    }) : (
                        <div style={{ padding: '10px 12px', fontSize: '12.5px', color: '#94a3b8' }}>No suppliers found</div>
                    )}
                </div>
            )}
        </div>
    );
}

// Renders a "field: old → new" list for audit entries carrying change detail.
// Short values render inline; long/multi-line values (e.g. line items) stack.
function ChangeDetail({ fields }: { fields?: { field: string; old: string; new: string }[] }) {
    if (!fields || fields.length === 0) return null;
    const oldPill: React.CSSProperties = { padding: '2px 7px', borderRadius: '4px', background: '#fef2f2', color: '#b91c1c', whiteSpace: 'pre-wrap', wordBreak: 'break-word' };
    const newPill: React.CSSProperties = { padding: '2px 7px', borderRadius: '4px', background: '#f0fdf4', color: '#15803d', whiteSpace: 'pre-wrap', wordBreak: 'break-word' };
    const empty = (v: string) => v.trim() === '';
    return (
        <div style={{ marginTop: '7px', display: 'flex', flexDirection: 'column', gap: '6px' }}>
            {fields.map((f, i) => {
                const isBlock = f.old.length > 45 || f.new.length > 45 || f.old.includes('\n') || f.new.includes('\n');
                return (
                    <div key={i} style={{ fontSize: '11.5px', color: '#64748b' }}>
                        <span style={{ fontWeight: 700, color: '#475569' }}>{f.field}:</span>
                        {isBlock ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '3px', marginTop: '3px' }}>
                                <div><span style={{ color: '#94a3b8', fontWeight: 600 }}>Old </span><span style={{ ...oldPill, textDecoration: empty(f.old) ? 'none' : 'line-through' }}>{empty(f.old) ? '—' : f.old}</span></div>
                                <div><span style={{ color: '#94a3b8', fontWeight: 600 }}>New </span><span style={newPill}>{empty(f.new) ? '—' : f.new}</span></div>
                            </div>
                        ) : (
                            <span style={{ display: 'inline-flex', flexWrap: 'wrap', alignItems: 'center', gap: '6px', marginLeft: '6px' }}>
                                <span style={{ ...oldPill, textDecoration: empty(f.old) ? 'none' : 'line-through' }}>{empty(f.old) ? '—' : f.old}</span>
                                <span style={{ color: '#94a3b8' }}>→</span>
                                <span style={newPill}>{empty(f.new) ? '—' : f.new}</span>
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

// ── RFQ Audit Trail Modal ──────────────────────────────────────────────────
function RfqHistoryModal({ row, onClose }: { row: RfqRow; onClose: () => void }) {
    const entries = row.audit_trail ?? [];
    const dotColor: Record<string, string> = {
        create: '#2563eb', update: '#f59e0b', delete: '#dc2626', upload: '#0891b2', finance: '#059669',
    };

    return (
        <Modal title={`Audit Trail — ${row.contractor}`} onClose={onClose} size="820px" headerBg="#0f172a"
            footer={<button type="button" onClick={onClose} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Close</button>}
        >
            {entries.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '24px 0', color: '#94a3b8', fontSize: '13px' }}>
                    No history recorded for this RFQ yet.
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                    {entries.map((e, i) => (
                        <div key={i} style={{ display: 'flex', gap: '12px', padding: '10px 4px', borderBottom: i < entries.length - 1 ? '1px solid #f1f5f9' : 'none' }}>
                            <div style={{ width: '9px', height: '9px', borderRadius: '50%', background: dotColor[e.type] ?? '#64748b', marginTop: '4px', flexShrink: 0 }} />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontSize: '13px', fontWeight: 600, color: '#1e293b', lineHeight: 1.4 }}>{e.action}</div>
                                <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '2px' }}>{e.date} · {e.user}</div>
                                <ChangeDetail fields={e.fields} />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

// ── Main Component ─────────────────────────────────────────────────────────
export default function RfqHub({ project, rfqs, suppliers = [], canEdit = true }: { project: HubProject; rfqs: RfqRow[]; suppliers?: { name: string; email: string }[]; canEdit?: boolean }) {
    const [dispatchContractor, setDispatchContractor] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [showSuccess, setShowSuccess]   = useState(false);
    const [sentContractor, setSentContractor] = useState('');
    const [showSendModal, setShowSendModal]   = useState(false);
    const [viewIds, setViewIds]           = useState<{ rfq: number; quotation: number } | null>(null);
    const [quotesRfqId, setQuotesRfqId]   = useState<number | null>(null);
    const [resendRfqId, setResendRfqId]   = useState<number | null>(null);
    const [ntpRow, setNtpRow]             = useState<RfqRow | null>(null);
    const [historyRfqId, setHistoryRfqId] = useState<number | null>(null);
    const [sending, setSending]           = useState(false);
    const [sendError, setSendError]       = useState('');

    // Modals read live rows, so adding, editing or re-finaling a quotation is
    // reflected without closing and reopening them.
    const rfqById   = (id: number | null) => (id == null ? null : rfqs.find(r => r.id === id) ?? null);
    const quotesRow = rfqById(quotesRfqId);
    const resendRow = rfqById(resendRfqId);
    const historyRow = rfqById(historyRfqId);
    const viewRow   = rfqById(viewIds?.rfq ?? null);
    const viewQuotation = viewRow?.quotations?.find(q => q.id === viewIds?.quotation) ?? null;

    // Set of contractors that already have an RFQ on this project
    // Own rows only — sub-project RFQs are shown read-only and shouldn't block
    // dispatching to the same contractor on this project.
    const usedContractors = new Set(rfqs.filter(r => !r.sub_project_id).map(r => r.contractor));
    const hasSubRows = rfqs.some(r => !!r.sub_project_id);

    const selectedContractor = suppliers.find(c => c.name === dispatchContractor) ?? null;
    const supplierEmails = splitEmails(selectedContractor?.email);

    const handleOpenSendModal = () => {
        if (!dispatchContractor) { setSendError('Please select a contractor before sending.'); return; }
        if (usedContractors.has(dispatchContractor)) {
            setSendError(`An RFQ has already been sent to ${dispatchContractor} for this project.`);
            return;
        }
        setSendError('');
        setShowSendModal(true);
    };

    const handleSend = (recipientEmail: string, additionalRecipients: string[], ccSelf: boolean) => {
        setShowSendModal(false);
        setSending(true);
        router.post(route('hub.rfq.store', project.id), {
            contractor_name: dispatchContractor,
            due_date:        dueDate || null,
            recipient_email: recipientEmail,
            additional_recipients: additionalRecipients,
            cc_self:         ccSelf,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSentContractor(dispatchContractor); setShowSuccess(true); setDispatchContractor(''); setDueDate(''); },
            onFinish: () => setSending(false),
            onError:  (errors) => { if (errors.contractor_name) setSendError(errors.contractor_name); },
        });
    };

    const handleResend = (recipientEmail: string, additionalRecipients: string[], ccSelf: boolean) => {
        const rfqId = resendRfqId;
        setResendRfqId(null);
        if (rfqId == null) return;
        router.post(route('hub.rfq.resend', [project.id, rfqId]), {
            recipient_email: recipientEmail,
            additional_recipients: additionalRecipients,
            cc_self: ccSelf,
        }, { preserveScroll: true });
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

    /**
     * Request for Quotation — PMD-PRJ-FRM-03, rendered to PDF server-side and
     * previewed in a new tab. A sub-project's RFQ belongs to the sub-project,
     * so that is the project the form is printed against.
     */
    const handlePrint = (row: RfqRow) => {
        window.open(route('print.rfq', [row.sub_project_id ?? project.id, row.id]), '_blank');
    };

    // Quotation stays editable at any status until an NTP has been created for it.
    const canEditRfq = (row: RfqRow) => canEdit && !row.has_ntp;

    /** Open the quotation form on a specific offer, defaulting to the final one. */
    const openQuotation = (row: RfqRow, quotation?: QuotationRow) => {
        const target = quotation
            ?? row.quotations?.find(q => q.is_final)
            ?? row.quotations?.[0];
        if (target) setViewIds({ rfq: row.id, quotation: target.id });
    };

    const quotationsBtn = (row: RfqRow) => (
        <button
            type="button"
            title="Manage quotations for this vendor"
            onClick={() => setQuotesRfqId(row.id)}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', padding: '5px 10px', borderRadius: '6px', border: '1px solid #c7d2fe', background: '#eef2ff', color: '#4338ca', fontSize: '11px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
            Quotations · {row.quotations?.length ?? 0}
        </button>
    );

    const resendBtn = (row: RfqRow) => (
        <button
            type="button"
            title="Re-send the RFQ email to this vendor"
            onClick={() => setResendRfqId(row.id)}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', padding: '5px 10px', borderRadius: '6px', border: '1px solid #bfdbfe', background: '#eff6ff', color: '#2563eb', fontSize: '11px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Resend
        </button>
    );

    const historyBtn = (row: RfqRow) => (
        <button
            type="button"
            title="View audit history"
            onClick={() => setHistoryRfqId(row.id)}
            style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '5px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', color: '#64748b', cursor: 'pointer' }}
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </button>
    );

    const viewOrEditBtns = (row: RfqRow) => (
        <ActionBtns
            view={!canEditRfq(row)}
            edit={canEditRfq(row)}
            onView={() => openQuotation(row)}
            onEdit={() => openQuotation(row)}
        />
    );

    /** Names which of several quotations the row's figures come from. */
    const finalNote = (row: RfqRow) => {
        const count = row.quotations?.length ?? 0;
        if (count < 2) return null;
        const final = row.quotations.find(q => q.is_final);
        return (
            <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#4338ca', marginTop: '3px' }}>
                {final ? `${final.name} of ${count}` : `${count} quotations · none final`}
            </div>
        );
    };

    const actionCell = (row: RfqRow) => {
        // Sub-project RFQs are read-only in the parent — view/print, or open the sub-project.
        if (row.sub_project_id) return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <ActionBtns
                    view print open
                    onView={() => openQuotation(row)}
                    onPrint={() => handlePrint(row)}
                    onOpen={() => router.visit(route('projects.hub.rfq', row.sub_project_id!))}
                />
                {quotationsBtn(row)}
                {historyBtn(row)}
            </div>
        );
        if (row.status === 'Awarded') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {quotationsBtn(row)}
                {canEdit && resendBtn(row)}
                {historyBtn(row)}
                {canEdit && (
                    row.ntp_status === 'issued' ? (
                        <span style={{ padding: '5px 12px', borderRadius: '6px', background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#15803d', fontSize: '11.5px', fontWeight: 700, whiteSpace: 'nowrap' }}>
                            ✓ NTP Issued
                        </span>
                    ) : row.ntp_status === 'pending_review' ? (
                        <span style={{ padding: '5px 12px', borderRadius: '6px', background: '#fef3c7', border: '1px solid #fde68a', color: '#92400e', fontSize: '11.5px', fontWeight: 700, whiteSpace: 'nowrap' }}>
                            ⏳ NTP Pending Review
                        </span>
                    ) : (
                        <Button variant="success" onClick={() => setNtpRow(row)}>Create NTP</Button>
                    )
                )}
            </div>
        );
        if (row.status === 'Submitted') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {quotationsBtn(row)}
                {canEdit && resendBtn(row)}
                {historyBtn(row)}
                {canEdit && <ActionBtns trophy onTrophy={() => handleStatus(row, 'awarded', 'Awarded')} />}
                {canEdit && <ActionBtns del onDelete={() => handleDelete(row)} />}
            </div>
        );
        if (row.status === 'Pending') {
        const rfqTotal = (row.items ?? []).reduce((s, i) => s + Number(i.total_cost ?? 0), 0);
        const canReceive = rfqTotal > 0;
        return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {quotationsBtn(row)}
                {canEdit && resendBtn(row)}
                {historyBtn(row)}
                {canEdit && (
                    <>
                        <button
                            type="button"
                            disabled={!canReceive}
                            title={canReceive ? 'Accept the submitted quotation' : 'Add an itemized quotation with a total cost before accepting'}
                            onClick={() => canReceive && handleStatus(row, 'submitted', 'Accepted')}
                            style={{ padding: '5px 10px', borderRadius: '6px', border: `1px solid ${canReceive ? '#bbf7d0' : '#e5e7eb'}`, background: canReceive ? '#f0fdf4' : '#f8fafc', color: canReceive ? '#15803d' : '#cbd5e1', fontSize: '11px', fontWeight: 700, cursor: canReceive ? 'pointer' : 'not-allowed', whiteSpace: 'nowrap' }}
                        >
                            ✓ Accept
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
                    </>
                )}
            </div>
        );
        }
        return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {quotationsBtn(row)}
                {canEdit && resendBtn(row)}
                {historyBtn(row)}
                {canEdit && <ActionBtns refresh onRefresh={() => handleStatus(row, 'pending', 'Re-activated')} />}
                {canEdit && <ActionBtns del onDelete={() => handleDelete(row)} />}
            </div>
        );
    };

    return (
        <HubShell>
            {confirmDialog}
            {showSuccess   && <SuccessModal contractor={sentContractor} onClose={() => setShowSuccess(false)} />}
            {showSendModal && selectedContractor && (
                <SendConfirmModal
                    contractor={selectedContractor.name}
                    dueDate={dueDate}
                    email={supplierEmails[0] ?? ''}
                    additional={supplierEmails.slice(1).join(', ')}
                    onClose={() => setShowSendModal(false)}
                    onSend={handleSend}
                />
            )}
            {resendRow && (
                <SendConfirmModal
                    contractor={resendRow.contractor}
                    dueDate={resendRow.due_raw ? resendRow.due : ''}
                    email={resendRow.recipient_email ?? ''}
                    title="Re-send RFQ"
                    sendLabel="Re-send RFQ"
                    note={`This sends the Request for Quotation to ${resendRow.contractor} again, download link for the quotation template included.`}
                    onClose={() => setResendRfqId(null)}
                    onSend={handleResend}
                />
            )}
            {quotesRow && (
                <QuotationsModal
                    row={quotesRow}
                    project={project}
                    canEdit={canEditRfq(quotesRow) && !quotesRow.sub_project_id}
                    onClose={() => setQuotesRfqId(null)}
                    onEdit={q => { setQuotesRfqId(null); openQuotation(quotesRow, q); }}
                />
            )}
            {viewRow && viewQuotation && (
                <RfqViewModal
                    key={viewQuotation.id}
                    row={viewRow}
                    quotation={viewQuotation}
                    project={project}
                    onClose={() => setViewIds(null)}
                    canEdit={canEditRfq(viewRow) && !viewRow.sub_project_id}
                />
            )}
            {ntpRow        && <NtpModal row={ntpRow} project={project} onClose={() => setNtpRow(null)} />}
            {historyRow    && <RfqHistoryModal row={historyRow} onClose={() => setHistoryRfqId(null)} />}

            {canEdit && (
                <>
                    <h3 style={{ margin: '0 0 18px', color: '#2563eb', fontSize: '18px' }}>Dispatch New RFQ</h3>

                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '18px', marginBottom: '22px' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 180px', gap: '14px', alignItems: 'start' }}>
                            <Field label="Select Supplier">
                                <SupplierSelect
                                    suppliers={suppliers}
                                    value={dispatchContractor}
                                    onChange={name => { setDispatchContractor(name); setSendError(''); }}
                                    usedContractors={usedContractors}
                                />
                                <div style={{ marginTop: '5px', fontSize: '11.5px', color: '#64748b', display: 'flex', alignItems: 'center', gap: '5px', minHeight: '15px' }}>
                                    {selectedContractor && (
                                        <>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                            {supplierEmails[0] ?? 'No email on file'}
                                            {supplierEmails.length > 1 && ` +${supplierEmails.length - 1} more`}
                                        </>
                                    )}
                                </div>
                            </Field>
                            <Field label="Due Date (Optional)">
                                <input type="date" style={inputStyle} value={dueDate} onChange={e => setDueDate(e.target.value)} />
                            </Field>
                            <div>
                                <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                                <button type="button" onClick={handleOpenSendModal} disabled={sending} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                    {sending ? 'Sending...' : 'Send RFQ'}
                                </button>
                            </div>
                        </div>
                        {sendError && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{sendError}</div>}
                    </div>
                </>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px', alignItems: 'center' }}>
                <strong style={{ color: '#475569' }}>RFQ Dispatch & Quotation Tracking</strong>
                <span style={{ padding: '3px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px', fontSize: '12px', color: '#475569' }}>Total Records: {rfqs.length}</span>
            </div>

            <DataTable
                headers={['Contractor Name', ...(hasSubRows ? ['Project'] : []), 'Scope of Work', 'Sent Date', 'Due Date', 'Status', 'Total Amount', 'Actions']}
                rows={rfqs.map(row => {
                    const grandTotal = (row.items ?? []).reduce((s, i) => s + Number(i.total_cost ?? 0), 0);
                    return [
                        <strong>{row.contractor}</strong>,
                        ...(hasSubRows ? [<SubTag no={row.sub_project_no} />] : []),
                        row.scope_of_work
                            ? <span title={row.scope_of_work} style={{ display: 'block', minWidth: '160px', maxWidth: '260px', fontSize: '12.5px', color: '#475569', whiteSpace: 'normal', wordBreak: 'break-word', lineHeight: 1.45 }}>{row.scope_of_work.length > 50 ? row.scope_of_work.slice(0, 50) + '…' : row.scope_of_work}</span>
                            : <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>,
                        row.sent,
                        row.due,
                        <Badge tone={STATUS_TONE[row.status]}>{row.status}</Badge>,
                        grandTotal > 0
                            ? (
                                <div>
                                    <strong style={{ color: '#2563eb' }}>PhP {grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginTop: '2px' }}>
                                        {row.duration_days ? `${row.duration_days} calendar day${row.duration_days === 1 ? '' : 's'}` : 'No duration set'}
                                    </div>
                                    {finalNote(row)}
                                </div>
                            )
                            : <div>
                                <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>
                                {finalNote(row)}
                            </div>,
                        actionCell(row),
                    ];
                })}
            />
        </HubShell>
    );
}
