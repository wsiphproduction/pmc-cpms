import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/**
 * The supplier's quotation portal.
 *
 * Reached from the RFQ email by an unguessable token — no account, no login —
 * so the page is deliberately self-contained: it explains what is being asked
 * for, shows what the supplier has already sent, and lets them add more.
 */

interface QuoteItem {
    seq: number;
    description: string | null;
    qty: number | null;
    unit: string | null;
    unit_cost: number | null;
    total_cost: number | null;
}

type QuoteStatus = 'draft' | 'submitted' | 'received';

interface Quotation {
    id: number;
    name: string;
    label: string | null;
    scope_of_work: string | null;
    status: QuoteStatus;
    /** False once the project team marks it received. */
    editable: boolean;
    due_raw: string | null;
    duration_days: number | null;
    terms: string | null;
    inclusions: string | null;
    exclusions: string | null;
    quotation_file: string | null;
    grand_total: number;
    submitted_at: string | null;
    received_at: string | null;
    items: QuoteItem[];
}

interface PageProps {
    rfq: {
        contractor: string;
        sent: string;
        due: string | null;
        due_raw: string | null;
        scope_of_work: string | null;
        token: string;
    };
    project: { project_no: string; title: string; site: string | null; owner: string | null };
    quotations: Quotation[];
    form_rows: number;
    flash?: { success?: string; error?: string };
    errors?: Record<string, string>;
    [key: string]: unknown;
}

const UNITS = ['—', 'pcs', 'lot', 'set', 'unit', 'lm', 'sqm', 'cbm', 'kg', 'ton', 'hr', 'day', 'wk', 'mo', 'L', 'bag', 'roll', 'sht', 'box'];

const STATUS_STYLE: Record<QuoteStatus, { bg: string; fg: string; border: string; label: string }> = {
    draft:     { bg: '#f1f5f9', fg: '#475569', border: '#cbd5e1', label: 'Draft — not sent' },
    submitted: { bg: '#dbeafe', fg: '#1e40af', border: '#bfdbfe', label: 'Sent' },
    received:  { bg: '#dcfce7', fg: '#166534', border: '#bbf7d0', label: 'Received — locked' },
};

const peso = (n: number) => `PhP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const input: React.CSSProperties = {
    width: '100%', boxSizing: 'border-box', border: '1.5px solid #e2e8f0', borderRadius: '8px',
    padding: '9px 11px', fontSize: '13px', fontFamily: 'inherit', outline: 'none', background: '#fff',
};

function Label({ children }: { children: React.ReactNode }) {
    return <span style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>{children}</span>;
}

function Card({ children, style }: { children: React.ReactNode; style?: React.CSSProperties }) {
    return <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '22px 24px', ...style }}>{children}</div>;
}

function StatusPill({ status }: { status: QuoteStatus }) {
    const s = STATUS_STYLE[status];
    return (
        <span style={{ background: s.bg, color: s.fg, border: `1px solid ${s.border}`, borderRadius: '999px', padding: '3px 11px', fontSize: '11px', fontWeight: 800, whiteSpace: 'nowrap' }}>
            {s.label}
        </span>
    );
}

// ── Line items ─────────────────────────────────────────────────────────────
function ItemRows({ rows, readOnly, onChange }: {
    rows: QuoteItem[];
    readOnly: boolean;
    onChange: (idx: number, field: keyof QuoteItem, value: string | number | null) => void;
}) {
    const cell: React.CSSProperties = { border: 'none', background: 'transparent', width: '100%', fontSize: '12.5px', fontFamily: 'inherit', outline: 'none', padding: '3px 4px' };
    const th: React.CSSProperties = { padding: '8px 10px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', background: '#f1f5f9', borderBottom: '1px solid #e5e7eb' };
    const total = rows.reduce((sum, r) => sum + Number(r.total_cost ?? 0), 0);

    return (
        <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px', minWidth: '620px' }}>
                <thead>
                    <tr>{['#', 'Deliverable or Activity', 'Qty', 'Unit', 'Unit Cost', 'Total Cost'].map(h => <th key={h} style={th}>{h}</th>)}</tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i} style={{ borderBottom: '1px solid #f1f5f9' }}>
                            <td style={{ padding: '6px 10px', textAlign: 'center', color: '#94a3b8', width: '34px' }}>{i + 1}</td>
                            <td style={{ padding: '3px 5px' }}>
                                <input style={cell} readOnly={readOnly} placeholder="Describe the work or item…" value={row.description ?? ''} onChange={e => onChange(i, 'description', e.target.value)} />
                            </td>
                            <td style={{ padding: '3px 5px', width: '72px' }}>
                                <input type="number" step="any" readOnly={readOnly} style={{ ...cell, textAlign: 'right' }} placeholder="—" value={row.qty ?? ''} onChange={e => onChange(i, 'qty', e.target.value ? Number(e.target.value) : null)} />
                            </td>
                            <td style={{ padding: '3px 5px', width: '84px' }}>
                                <select style={{ ...cell, cursor: readOnly ? 'default' : 'pointer' }} disabled={readOnly} value={row.unit ?? '—'} onChange={e => onChange(i, 'unit', e.target.value === '—' ? '' : e.target.value)}>
                                    {UNITS.map(u => <option key={u} value={u}>{u}</option>)}
                                </select>
                            </td>
                            <td style={{ padding: '3px 5px', width: '110px' }}>
                                <input type="number" step="any" readOnly={readOnly} style={{ ...cell, textAlign: 'right' }} placeholder="—" value={row.unit_cost ?? ''} onChange={e => onChange(i, 'unit_cost', e.target.value ? Number(e.target.value) : null)} />
                            </td>
                            <td style={{ padding: '8px 10px', background: '#f8fafc', fontWeight: 700, textAlign: 'right', width: '120px', color: (row.total_cost ?? 0) > 0 ? '#2563eb' : '#cbd5e1' }}>
                                {(row.total_cost ?? 0) > 0 ? row.total_cost!.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}
                            </td>
                        </tr>
                    ))}
                    <tr style={{ background: '#f8fafc' }}>
                        <td colSpan={5} style={{ padding: '9px 10px', textAlign: 'right', fontWeight: 800, color: '#1e293b' }}>Grand Total</td>
                        <td style={{ padding: '9px 10px', fontWeight: 900, color: '#2563eb', background: '#fefce8', textAlign: 'right' }}>{peso(total)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

// ── Quotation form ─────────────────────────────────────────────────────────
function QuotationForm({ quotation, token, formRows, defaultScope, defaultDue, onCancel }: {
    quotation: Quotation | null;
    token: string;
    formRows: number;
    /** A new quotation opens pre-filled from what the RFQ asked for. */
    defaultScope: string;
    defaultDue: string;
    onCancel: () => void;
}) {
    const readOnly = quotation ? !quotation.editable : false;

    const [label, setLabel]           = useState(quotation?.label ?? '');
    const [scope, setScope]           = useState(quotation?.scope_of_work ?? defaultScope);
    const [due, setDue]               = useState(quotation?.due_raw ?? defaultDue);
    const [duration, setDuration]     = useState(quotation?.duration_days?.toString() ?? '');
    const [terms, setTerms]           = useState(quotation?.terms ?? '');
    const [inclusions, setInclusions] = useState(quotation?.inclusions ?? '');
    const [exclusions, setExclusions] = useState(quotation?.exclusions ?? '');
    const [file, setFile]             = useState<File | null>(null);
    const [busy, setBusy]             = useState(false);
    const [error, setError]           = useState('');

    const [rows, setRows] = useState<QuoteItem[]>(() =>
        Array.from({ length: Math.max(formRows, quotation?.items.length ?? 0) }, (_, i) => {
            const src = quotation?.items[i];
            return {
                seq:         i + 1,
                description: src?.description ?? null,
                qty:         src?.qty        != null ? Number(src.qty)        : null,
                unit:        src?.unit       ?? null,
                unit_cost:   src?.unit_cost  != null ? Number(src.unit_cost)  : null,
                total_cost:  src?.total_cost != null ? Number(src.total_cost) : null,
            };
        }),
    );

    const updateRow = (idx: number, field: keyof QuoteItem, value: string | number | null) => {
        setRows(prev => {
            const next = [...prev];
            const row = { ...next[idx], [field]: value };
            if (field === 'qty' || field === 'unit_cost') {
                const qty  = field === 'qty'       ? (value as number | null) : row.qty;
                const cost = field === 'unit_cost' ? (value as number | null) : row.unit_cost;
                row.total_cost = qty != null && cost != null ? Math.round(qty * cost * 100) / 100 : null;
            }
            next[idx] = row;
            return next;
        });
    };

    const submit = (send: boolean) => {
        const filled = rows.filter(r => r.description?.trim());

        if (send) {
            if (!scope.trim())                                    { setError('Please describe the scope of work your quotation covers.'); return; }
            if (!filled.some(r => Number(r.total_cost ?? 0) > 0)) { setError('Add at least one line item with a total cost before sending.'); return; }
            if (!due)                                             { setError('Please give the date the work is needed by.'); return; }
            if (!duration || Number(duration) <= 0)               { setError('Please give the project duration in calendar days.'); return; }
            if (!terms.trim())                                    { setError('Please fill in your terms and conditions.'); return; }
            if (!inclusions.trim())                               { setError('Please fill in what your quotation includes.'); return; }
            if (!exclusions.trim())                               { setError('Please fill in what your quotation excludes.'); return; }
        }

        setError('');
        setBusy(true);

        const payload: Record<string, any> = {
            label:            label.trim() || null,
            scope_of_work:    scope || null,
            due_date:         due || null,
            duration_days:    duration || null,
            terms_conditions: terms || null,
            inclusions:       inclusions || null,
            exclusions:       exclusions || null,
            items:            filled,
            send:             send ? 1 : 0,
        };
        if (file) payload.quotation_file = file;

        const opts = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onCancel,
            onError: (formErrors: Record<string, string>) => setError(Object.values(formErrors)[0] ?? 'Please check the form and try again.'),
            onFinish: () => setBusy(false),
        };

        if (quotation) {
            // PHP only parses uploads on POST, so PATCH is method-spoofed.
            router.post(route('supplier-quote.update', [token, quotation.id]), { ...payload, _method: 'patch' }, opts);
        } else {
            router.post(route('supplier-quote.store', token), payload, opts);
        }
    };

    return (
        <Card style={{ borderColor: '#bfdbfe', boxShadow: '0 8px 28px rgba(37,99,235,0.08)' }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', marginBottom: '18px', flexWrap: 'wrap' }}>
                <h2 style={{ margin: 0, fontSize: '16px', fontWeight: 800, color: '#0f172a' }}>
                    {quotation ? quotation.name : 'New Quotation'}
                </h2>
                {quotation && <StatusPill status={quotation.status} />}
            </div>

            {readOnly && (
                <div style={{ padding: '11px 14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', color: '#166534', fontSize: '12.5px', fontWeight: 600, marginBottom: '18px', lineHeight: 1.6 }}>
                    The project team has marked this quotation as received, so it can no longer be changed.
                    If something needs correcting, reply to the RFQ email or send a new quotation.
                </div>
            )}

            {error && (
                <div style={{ padding: '10px 13px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '18px' }}>
                    {error}
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px', marginBottom: '20px' }}>
                <label>
                    <Label>Reference / Label (optional)</Label>
                    <input value={label} readOnly={readOnly} onChange={e => setLabel(e.target.value)} placeholder="e.g. Revised offer" style={input} />
                </label>
                <label>
                    <Label>Date needed *</Label>
                    <input type="date" value={due} readOnly={readOnly} onChange={e => setDue(e.target.value)} style={input} />
                </label>
                <label>
                    <Label>Project duration (calendar days) *</Label>
                    <input type="number" min="1" value={duration} readOnly={readOnly} onChange={e => setDuration(e.target.value)} placeholder="e.g. 45" style={input} />
                </label>
            </div>

            <div style={{ marginBottom: '20px' }}>
                <Label>Scope of work *</Label>
                <textarea
                    rows={4}
                    value={scope}
                    readOnly={readOnly}
                    onChange={e => setScope(e.target.value)}
                    placeholder="Describe the work your quotation covers…"
                    style={{ ...input, resize: 'vertical' }}
                />
                <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '6px' }}>
                    Pre-filled from the request above — edit it if your offer covers more or less than what was asked for.
                </div>
            </div>

            <div style={{ marginBottom: '20px' }}>
                <Label>Itemised quotation *</Label>
                <ItemRows rows={rows} readOnly={readOnly} onChange={updateRow} />
                <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '6px' }}>
                    Total cost is worked out from quantity × unit cost. Leave unused rows blank.
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '14px', marginBottom: '20px' }}>
                <label>
                    <Label>Terms and conditions *</Label>
                    <textarea rows={4} value={terms} readOnly={readOnly} onChange={e => setTerms(e.target.value)} placeholder="Payment terms, warranty, validity…" style={{ ...input, resize: 'vertical' }} />
                </label>
                <label>
                    <Label>Inclusions *</Label>
                    <textarea rows={4} value={inclusions} readOnly={readOnly} onChange={e => setInclusions(e.target.value)} placeholder="What your price covers…" style={{ ...input, resize: 'vertical' }} />
                </label>
                <label>
                    <Label>Exclusions *</Label>
                    <textarea rows={4} value={exclusions} readOnly={readOnly} onChange={e => setExclusions(e.target.value)} placeholder="What your price does not cover…" style={{ ...input, resize: 'vertical' }} />
                </label>
            </div>

            <div style={{ marginBottom: '22px' }}>
                <Label>Supporting document (optional)</Label>
                {!readOnly && (
                    <input
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        onChange={e => setFile(e.target.files?.[0] ?? null)}
                        style={{ ...input, padding: '7px 9px', cursor: 'pointer' }}
                    />
                )}
                <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '6px' }}>
                    {file
                        ? `Selected: ${file.name}`
                        : quotation?.quotation_file
                            ? <>A file is already attached — <a href={quotation.quotation_file} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontWeight: 700 }}>view it</a>{!readOnly && '. Choosing a new file replaces it.'}</>
                            : 'PDF, Word, Excel or image, up to 20 MB.'}
                </div>
            </div>

            <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
                <button type="button" onClick={onCancel}
                    style={{ padding: '10px 20px', borderRadius: '8px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '13px', fontWeight: 600, cursor: 'pointer', color: '#374151' }}>
                    {readOnly ? 'Close' : 'Cancel'}
                </button>
                {!readOnly && (
                    <>
                        <button type="button" onClick={() => submit(false)} disabled={busy}
                            style={{ padding: '10px 22px', borderRadius: '8px', border: '1.5px solid #2563eb', background: '#fff', color: '#2563eb', fontSize: '13px', fontWeight: 800, cursor: busy ? 'wait' : 'pointer' }}>
                            {busy ? 'Working…' : 'Save'}
                        </button>
                        <button type="button" onClick={() => submit(true)} disabled={busy}
                            style={{ padding: '10px 24px', borderRadius: '8px', border: 'none', background: '#059669', color: '#fff', fontSize: '13px', fontWeight: 800, cursor: busy ? 'wait' : 'pointer' }}>
                            {busy ? 'Working…' : 'Save & Send'}
                        </button>
                    </>
                )}
            </div>
        </Card>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────
export default function SupplierQuotePortal() {
    const { rfq, project, quotations, form_rows: formRows, flash } = usePage<PageProps>().props;

    // `null` = closed, `'new'` = blank form, a number = that quotation.
    const [open, setOpen] = useState<number | 'new' | null>(quotations.length === 0 ? 'new' : null);

    const active = useMemo(
        () => (typeof open === 'number' ? quotations.find(q => q.id === open) ?? null : null),
        [open, quotations],
    );

    const detail = (label: string, value: React.ReactNode) => (
        <div>
            <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '3px' }}>{label}</div>
            <div style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>{value || '—'}</div>
        </div>
    );

    return (
        <>
            <Head title={`Quotation — ${project.project_no}`} />

            <div style={{ minHeight: '100vh', background: '#f1f5f9', padding: '0 0 60px', fontFamily: "'Segoe UI', system-ui, -apple-system, sans-serif", color: '#0f172a' }}>
                {/* Masthead */}
                <div style={{ background: '#1e3a8a', padding: '30px 24px 34px' }}>
                    <div style={{ maxWidth: '980px', margin: '0 auto' }}>
                        <div style={{ fontSize: '11px', fontWeight: 800, color: '#93c5fd', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '6px' }}>
                            Request for Quotation
                        </div>
                        <h1 style={{ margin: 0, fontSize: '24px', fontWeight: 800, color: '#fff', lineHeight: 1.25 }}>{project.title}</h1>
                        <div style={{ marginTop: '8px', fontSize: '13px', color: '#bfdbfe' }}>
                            {project.project_no} · Prepared for <strong style={{ color: '#fff' }}>{rfq.contractor}</strong>
                        </div>
                    </div>
                </div>

                <div style={{ maxWidth: '980px', margin: '-18px auto 0', padding: '0 24px', display: 'flex', flexDirection: 'column', gap: '18px' }}>
                    {flash?.success && (
                        <div style={{ padding: '13px 17px', background: '#dcfce7', border: '1px solid #bbf7d0', borderRadius: '10px', color: '#166534', fontSize: '13px', fontWeight: 700 }}>
                            {flash.success}
                        </div>
                    )}

                    {/* What is being asked for */}
                    <Card>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: '16px' }}>
                            {detail('Project Number', project.project_no)}
                            {detail('Job Site', project.site)}
                            {detail('Project Owner', project.owner)}
                            {detail('Date Sent', rfq.sent)}
                            {detail('Reply By', rfq.due ?? 'To be advised')}
                        </div>
                        {rfq.scope_of_work && (
                            <div style={{ marginTop: '18px', paddingTop: '18px', borderTop: '1px solid #f1f5f9' }}>
                                {detail('Scope of Work Requested', <span style={{ fontWeight: 500, lineHeight: 1.65, whiteSpace: 'pre-wrap' }}>{rfq.scope_of_work}</span>)}
                            </div>
                        )}
                    </Card>

                    {/* What they have sent so far */}
                    {quotations.length > 0 && (
                        <Card>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', marginBottom: '14px', flexWrap: 'wrap' }}>
                                <h2 style={{ margin: 0, fontSize: '15px', fontWeight: 800 }}>Your Quotations</h2>
                                {open === null && (
                                    <button type="button" onClick={() => setOpen('new')}
                                        style={{ padding: '9px 18px', borderRadius: '8px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 800, cursor: 'pointer' }}>
                                        + Add Another Quotation
                                    </button>
                                )}
                            </div>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: '9px' }}>
                                {quotations.map(q => (
                                    <div key={q.id} style={{ border: '1px solid #e2e8f0', borderRadius: '10px', padding: '13px 16px', display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap' }}>
                                        <div style={{ flex: 1, minWidth: '200px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '9px', flexWrap: 'wrap' }}>
                                                <strong style={{ fontSize: '13.5px' }}>{q.name}</strong>
                                                <StatusPill status={q.status} />
                                            </div>
                                            <div style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px' }}>
                                                {q.items.length} item{q.items.length === 1 ? '' : 's'}
                                                {q.duration_days ? ` · ${q.duration_days} calendar day${q.duration_days === 1 ? '' : 's'}` : ''}
                                                {q.submitted_at ? ` · sent ${q.submitted_at}` : ''}
                                                {q.received_at ? ` · received ${q.received_at}` : ''}
                                            </div>
                                        </div>
                                        <div style={{ textAlign: 'right', minWidth: '120px' }}>
                                            <strong style={{ fontSize: '14px', color: q.grand_total > 0 ? '#2563eb' : '#cbd5e1' }}>
                                                {q.grand_total > 0 ? peso(q.grand_total) : '—'}
                                            </strong>
                                        </div>
                                        <button type="button" onClick={() => setOpen(q.id)}
                                            style={{ padding: '7px 15px', borderRadius: '7px', border: '1px solid #bfdbfe', background: '#eff6ff', color: '#2563eb', fontSize: '12px', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                                            {q.editable ? 'Edit' : 'View'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}

                    {open !== null ? (
                        <QuotationForm
                            key={typeof open === 'number' ? open : 'new'}
                            quotation={active}
                            token={rfq.token}
                            formRows={formRows}
                            defaultScope={rfq.scope_of_work ?? ''}
                            defaultDue={rfq.due_raw ?? ''}
                            onCancel={() => setOpen(null)}
                        />
                    ) : (
                        <div style={{ textAlign: 'center', padding: '4px 0 0' }}>
                            <button type="button" onClick={() => setOpen('new')}
                                style={{ padding: '12px 28px', borderRadius: '9px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '13.5px', fontWeight: 800, cursor: 'pointer' }}>
                                + Submit a Quotation
                            </button>
                        </div>
                    )}

                    <div style={{ textAlign: 'center', fontSize: '11.5px', color: '#94a3b8', lineHeight: 1.7, marginTop: '6px' }}>
                        This link is unique to you — keep it private, and use it again any time to add or finish a quotation.<br />
                        Questions about this RFQ? Reply to the email it came from.
                    </div>
                </div>
            </div>
        </>
    );
}
