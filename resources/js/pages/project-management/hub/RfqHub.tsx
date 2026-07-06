import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActionBtns, Badge, Button, DataTable, Field, HubProject, HubShell, Modal, ModalSection, inputStyle } from './Common';
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
    quotation_file?: string | null;
    recipient_email?: string | null;
    has_ntp: boolean;
    items?: RfqItem[];
}

interface RfqPageProps {
    auth: { user: { email: string } };
    [key: string]: unknown;
}

const STATUS_TONE: Record<RfqStatus, 'yellow' | 'green' | 'slate' | 'red'> = {
    Awarded: 'yellow', Submitted: 'green', Pending: 'slate', Expired: 'red',
};

function escapeHtml(value: string | number | null | undefined): string {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
function RfqViewModal({ row, project, onClose, canEdit = true }: { row: RfqRow; project: HubProject; onClose: () => void; canEdit?: boolean }) {
    const [scope, setScope]           = useState(row.scope_of_work ?? '');
    const [due, setDue]               = useState(row.due_raw ?? '');
    const [duration, setDuration]     = useState(row.duration_days?.toString() ?? '');
    const [terms, setTerms]           = useState(row.terms ?? '');
    const [inclusions, setInclusions] = useState(row.inclusions ?? '');
    const [exclusions, setExclusions] = useState(row.exclusions ?? '');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');
    const [quotationFile, setQuotationFile] = useState<File | null>(null);
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

        const basePayload = {
            scope_of_work:    scope,
            due_date:         due || null,
            duration_days:    duration || null,
            terms_conditions: terms,
            inclusions,
            exclusions,
            items: rows.filter(r => r.description?.trim()) as any,
        };

        const opts = { preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false) };

        if (quotationFile) {
            // PHP only parses $_FILES for POST — use method-spoofed POST so the file is accessible
            router.post(route('hub.rfq.update', [project.id, row.id]),
                { ...basePayload, _method: 'patch', quotation_file: quotationFile },
                opts,
            );
        } else {
            router.patch(route('hub.rfq.update', [project.id, row.id]), basePayload, opts);
        }
    };

    return (
        <Modal title="RFQ & Quotation Details" onClose={onClose} size="900px"
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
                {row.quotation_file && (
                    <div style={{ flexShrink: 0, paddingTop: '20px' }}>
                        <a
                            href={row.quotation_file}
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

// ── Send Confirm Modal ─────────────────────────────────────────────────────
function SendConfirmModal({ contractor, dueDate, email, onClose, onSend }: {
    contractor: string;
    dueDate: string;
    email: string;
    onClose: () => void;
    onSend: (email: string, additionalRecipients: string[], ccSelf: boolean) => void;
}) {
    const { auth } = usePage<RfqPageProps>().props;
    const [recipientEmail, setRecipientEmail]   = useState(email);
    const [additionalInput, setAdditionalInput] = useState('');
    const [ccSelf, setCcSelf]                   = useState(false);
    const [emailError, setEmailError]           = useState('');

    const parseAdditional = () =>
        additionalInput.split(',').map(e => e.trim()).filter(Boolean);

    const handleConfirm = () => {
        if (!recipientEmail.trim()) { setEmailError('Email address is required.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipientEmail.trim())) { setEmailError('Please enter a valid email address.'); return; }
        const additional = parseAdditional();
        const invalid = additional.find(e => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e));
        if (invalid) { setEmailError(`"${invalid}" is not a valid email address.`); return; }
        setEmailError('');
        onSend(recipientEmail.trim(), additional, ccSelf);
    };

    return (
        <Modal title="Confirm & Send RFQ" onClose={onClose} size="460px" headerBg="#2563eb"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleConfirm} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send RFQ
                </button>
            </>}
        >
            {/* Contractor info strip */}
            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '8px', padding: '12px 16px', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <div>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Service Contractor</div>
                    <div style={{ fontSize: '14px', fontWeight: 700, color: '#1e3a8a' }}>{contractor}</div>
                </div>
                {dueDate && (
                    <div style={{ marginLeft: 'auto', textAlign: 'right' }}>
                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Due Date</div>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e3a8a' }}>{dueDate}</div>
                    </div>
                )}
            </div>

            {/* Email input */}
            <div style={{ marginBottom: '6px' }}>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                    Recipient Email Address
                </label>
                <div style={{ position: 'relative' }}>
                    <span style={{ position: 'absolute', left: '11px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input
                        type="email"
                        value={recipientEmail}
                        onChange={e => { setRecipientEmail(e.target.value); setEmailError(''); }}
                        placeholder="contractor@example.com"
                        autoFocus
                        style={{
                            width: '100%', boxSizing: 'border-box', padding: '9px 11px 9px 34px',
                            border: `1.5px solid ${emailError ? '#fca5a5' : '#e2e8f0'}`,
                            borderRadius: '7px', fontSize: '13px', fontFamily: 'inherit', outline: 'none',
                            background: emailError ? '#fff7f7' : '#fff',
                        }}
                    />
                </div>
                {emailError && <div style={{ color: '#dc2626', fontSize: '11.5px', fontWeight: 600, marginTop: '5px' }}>{emailError}</div>}
                <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '5px' }}>You can edit the email before sending.</div>
            </div>

            {/* Additional recipients */}
            <div style={{ marginBottom: '14px' }}>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                    Additional Recipients (Optional)
                </label>
                <input
                    type="text"
                    value={additionalInput}
                    onChange={e => { setAdditionalInput(e.target.value); setEmailError(''); }}
                    placeholder="another@example.com, another2@example.com"
                    style={{
                        width: '100%', boxSizing: 'border-box', padding: '9px 11px',
                        border: '1.5px solid #e2e8f0', borderRadius: '7px', fontSize: '13px', fontFamily: 'inherit', outline: 'none',
                    }}
                />
                <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '5px' }}>Separate multiple email addresses with commas.</div>
            </div>

            {/* Send me a copy */}
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12.5px', color: '#334155', cursor: 'pointer' }}>
                <input type="checkbox" checked={ccSelf} onChange={e => setCcSelf(e.target.checked)} />
                Send me a copy of this email ({auth.user.email})
            </label>
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
export default function RfqHub({ project, rfqs, suppliers = [], canEdit = true }: { project: HubProject; rfqs: RfqRow[]; suppliers?: { name: string; email: string }[]; canEdit?: boolean }) {
    const [dispatchContractor, setDispatchContractor] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [showSuccess, setShowSuccess]   = useState(false);
    const [sentContractor, setSentContractor] = useState('');
    const [showSendModal, setShowSendModal]   = useState(false);
    const [viewRow, setViewRow]           = useState<RfqRow | null>(null);
    const [ntpRow, setNtpRow]             = useState<RfqRow | null>(null);
    const [sending, setSending]           = useState(false);
    const [sendError, setSendError]       = useState('');

    // Set of contractors that already have an RFQ on this project
    const usedContractors = new Set(rfqs.map(r => r.contractor));

    const selectedContractor = suppliers.find(c => c.name === dispatchContractor) ?? null;

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

    const handlePrint = (row: RfqRow) => {
        const items = row.items ?? [];
        const grandTotal = items.reduce((s, i) => s + Number(i.total_cost ?? 0), 0);
        const win = window.open('', '_blank', 'width=900,height=700');
        if (!win) return;
        win.document.write(`<!DOCTYPE html><html><head>
            <title>RFQ — ${escapeHtml(row.contractor)}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #0f172a; }
                * { box-sizing: border-box; }
                table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                th, td { border: 1px solid #e2e8f0; padding: 8px 12px; font-size: 13px; text-align: left; }
                th { background: #f8fafc; font-weight: 700; }
                .meta { font-size: 13px; color: #475569; margin-bottom: 4px; }
                @media print { body { margin: 20px; } }
            </style>
        </head><body>
            <h2>Request for Quotation</h2>
            <div class="meta"><strong>Contractor:</strong> ${escapeHtml(row.contractor)}</div>
            <div class="meta"><strong>Sent Date:</strong> ${escapeHtml(row.sent) || '—'}</div>
            <div class="meta"><strong>Due Date:</strong> ${escapeHtml(row.due) || '—'}</div>
            <div class="meta"><strong>Status:</strong> ${escapeHtml(row.status)}</div>
            ${row.scope_of_work ? `<div class="meta"><strong>Scope of Work:</strong> ${escapeHtml(row.scope_of_work)}</div>` : ''}
            <table>
                <thead><tr><th>Seq</th><th>Description</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Total Cost</th></tr></thead>
                <tbody>
                    ${items.map((i, idx) => `<tr><td>${idx + 1}</td><td>${escapeHtml(i.description)}</td><td>${escapeHtml(i.qty)}</td><td>${escapeHtml(i.unit)}</td><td>${i.unit_cost != null ? i.unit_cost.toLocaleString(undefined, { minimumFractionDigits: 2 }) : ''}</td><td>${i.total_cost != null ? i.total_cost.toLocaleString(undefined, { minimumFractionDigits: 2 }) : ''}</td></tr>`).join('')}
                    <tr><td colspan="5" style="text-align:right;font-weight:700;">Grand Total:</td><td style="font-weight:700;">PhP ${grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td></tr>
                </tbody>
            </table>
        </body></html>`);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 250);
    };

    const filePreviewCell = (row: RfqRow) => {
        if (!row.quotation_file) return <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>;
        return (
            <a
                href={row.quotation_file}
                target="_blank"
                rel="noreferrer"
                title="View quotation file"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', padding: '4px 10px', borderRadius: '6px', border: '1px solid #bfdbfe', background: '#eff6ff', color: '#2563eb', fontSize: '11.5px', fontWeight: 700, textDecoration: 'none', whiteSpace: 'nowrap' }}
            >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                View File
            </a>
        );
    };

    const canEditRfq = (row: RfqRow) => canEdit && row.status !== 'Submitted' && row.status !== 'Awarded' && !row.has_ntp;

    const viewOrEditBtns = (row: RfqRow) => (
        <ActionBtns
            view={!canEditRfq(row)}
            edit={canEditRfq(row)}
            onView={() => setViewRow(row)}
            onEdit={() => setViewRow(row)}
        />
    );

    const actionCell = (row: RfqRow) => {
        if (row.status === 'Awarded') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {canEdit && (row.has_ntp ? (
                    <span style={{ padding: '5px 12px', borderRadius: '6px', background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#15803d', fontSize: '11.5px', fontWeight: 700, whiteSpace: 'nowrap' }}>
                        ✓ NTP Issued
                    </span>
                ) : (
                    <Button variant="success" onClick={() => setNtpRow(row)}>Create NTP</Button>
                ))}
            </div>
        );
        if (row.status === 'Submitted') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {canEdit && <ActionBtns trophy onTrophy={() => handleStatus(row, 'awarded', 'Awarded')} />}
                {canEdit && <ActionBtns del onDelete={() => handleDelete(row)} />}
            </div>
        );
        if (row.status === 'Pending') return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
                {canEdit && (
                    <>
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
                    </>
                )}
            </div>
        );
        return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {viewOrEditBtns(row)}
                <ActionBtns print onPrint={() => handlePrint(row)} />
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
                    email={selectedContractor.email}
                    onClose={() => setShowSendModal(false)}
                    onSend={handleSend}
                />
            )}
            {viewRow       && <RfqViewModal row={viewRow} project={project} onClose={() => setViewRow(null)} canEdit={canEditRfq(viewRow)} />}
            {ntpRow        && <NtpModal row={ntpRow} project={project} onClose={() => setNtpRow(null)} />}

            {canEdit && (
                <>
                    <h3 style={{ margin: '0 0 18px', color: '#2563eb', fontSize: '18px' }}>Dispatch New RFQ</h3>

                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '18px', marginBottom: '22px' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 180px', gap: '14px', alignItems: 'start' }}>
                            <Field label="Select Supplier">
                                <select style={inputStyle} value={dispatchContractor} onChange={e => { setDispatchContractor(e.target.value); setSendError(''); }}>
                                    <option value="" disabled>
                                        {suppliers.length ? 'Choose from registered suppliers...' : 'No suppliers in master data yet'}
                                    </option>
                                    {suppliers.map(c => (
                                        <option key={c.name} value={c.name} disabled={usedContractors.has(c.name)}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <div style={{ marginTop: '5px', fontSize: '11.5px', color: '#64748b', display: 'flex', alignItems: 'center', gap: '5px', minHeight: '15px' }}>
                                    {selectedContractor && (
                                        <>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                            {selectedContractor.email}
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
                headers={['Contractor Name', 'Sent Date', 'Due Date', 'Status', 'Total Amount', 'File', 'Actions']}
                rows={rfqs.map(row => {
                    const grandTotal = (row.items ?? []).reduce((s, i) => s + Number(i.total_cost ?? 0), 0);
                    return [
                        <strong>{row.contractor}</strong>,
                        row.sent,
                        row.due,
                        <Badge tone={STATUS_TONE[row.status]}>{row.status}</Badge>,
                        grandTotal > 0
                            ? <strong style={{ color: '#2563eb' }}>PhP {grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>
                            : <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>,
                        filePreviewCell(row),
                        actionCell(row),
                    ];
                })}
            />
        </HubShell>
    );
}
