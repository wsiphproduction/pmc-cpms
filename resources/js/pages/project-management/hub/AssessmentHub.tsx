import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ApprovalTimeline, { type ApprovalStep } from '@/components/ApprovalTimeline';
import { useConfirm } from '@/components/useConfirm';
import { Field, HubProject, HubShell, InfoStrip, Modal, inputStyle } from './Common';

// ── Types ──────────────────────────────────────────────────────────────────

type FieldType = 'text' | 'textarea' | 'date' | 'number' | 'money' | 'percent' | 'select' | 'checklist' | 'table' | 'billing' | 'risk';

interface FieldDef {
    key: string;
    label: string;
    type: FieldType;
    required?: boolean;
    help?: string;
    options?: Record<string, string>;
    items?: Record<string, string>;
    columns?: FieldDef[];
    of?: [string, string];
}

export interface AssessmentDefinition {
    type: string;
    label: string;
    prefix: string;
    source?: 'completion';
    fields: FieldDef[];
    chain_labels: string[];
}

export interface AssessmentRow {
    id: number;
    control_no: string;
    title: string | null;
    data: Record<string, any>;
    status: 'draft' | 'for_approval' | 'approved';
    status_label: string;
    prepared_by: string | null;
    created: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    return_remarks: string | null;
    approvals: ApprovalStep[];
    can_sign: boolean;
    files: { id: number; filename: string; url: string }[];
}

interface BillingOption { value: string; label: string; amount: number; progress: number | null }

const TONE = '#0f766e';

const STATUS_TONE: Record<AssessmentRow['status'], { bg: string; color: string; border: string }> = {
    draft:        { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' },
    for_approval: { bg: '#fefce8', color: '#854d0e', border: '#fef08a' },
    approved:     { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' },
};

const RISK_DOT: Record<string, string> = { Low: '#0ca30c', Medium: '#fab219', High: '#ec835a', Extreme: '#d03b3b' };

function btn(bg: string, color: string, border = bg): React.CSSProperties {
    return { padding: '8px 14px', borderRadius: '8px', border: `1px solid ${border}`, background: bg, color, fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', whiteSpace: 'nowrap' };
}

function riskOf(a: unknown, b: unknown): { score: number; rating: string } | null {
    const x = Number(a), y = Number(b);
    if (!x || !y) return null;
    const score = x * y;
    return { score, rating: score >= 20 ? 'Extreme' : score >= 10 ? 'High' : score >= 5 ? 'Medium' : 'Low' };
}

function RiskBadge({ risk }: { risk: { score: number; rating: string } | null }) {
    if (!risk) return <span style={{ color: '#94a3b8' }}>—</span>;
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', fontWeight: 700, fontSize: '12px', color: '#0f172a', whiteSpace: 'nowrap' }}>
            <span aria-hidden style={{ width: '8px', height: '8px', borderRadius: '999px', background: RISK_DOT[risk.rating] }} />
            {risk.score} {risk.rating}
        </span>
    );
}

export function StatusPill({ row }: { row: Pick<AssessmentRow, 'status' | 'status_label'> }) {
    const t = STATUS_TONE[row.status];
    return <span style={{ padding: '3px 10px', borderRadius: '999px', fontSize: '11px', fontWeight: 700, background: t.bg, color: t.color, border: `1px solid ${t.border}`, whiteSpace: 'nowrap' }}>{row.status_label}</span>;
}

// ── Sign-off (shared with the completion certificates) ─────────────────────

function SignModal({ row, label, mode, onClose }: { row: AssessmentRow; label: string; mode: 'approve' | 'return'; onClose: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const verb = mode === 'approve' ? 'Approve' : 'Return for Revision';

    const save = () => {
        setSaving(true);
        router.patch(route(mode === 'approve' ? 'assessments.approve' : 'assessments.return', row.id), { remarks }, {
            preserveScroll: true, onSuccess: () => onClose(), onError: e => setError(e.remarks ?? 'Could not save.'), onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title={`${verb} — ${label} ${row.control_no}`} onClose={onClose} headerBg={mode === 'approve' ? '#16a34a' : '#b45309'} size="520px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#475569', '#cbd5e1')}>Cancel</button>
                <button type="button" onClick={save} disabled={saving} style={btn(mode === 'approve' ? '#16a34a' : '#b45309', '#fff')}>{saving ? 'Saving…' : verb}</button>
            </>}>
            <Field label={mode === 'return' ? 'What needs revising?' : 'Remarks (optional)'}>
                <textarea style={{ ...inputStyle, minHeight: '90px', resize: 'vertical' }} value={remarks} onChange={e => setRemarks(e.target.value)} />
            </Field>
            {error && <div style={{ color: '#dc2626', fontSize: '12px', marginTop: '6px' }}>{error}</div>}
        </Modal>
    );
}

/** The chain, any return remarks, and Approve / Return for whoever it waits on. */
export function SignoffBar({ row, label, chain }: { row: AssessmentRow; label: string; chain: string[] }) {
    const [mode, setMode] = useState<'approve' | 'return' | null>(null);

    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '10px', padding: '12px 14px', background: '#f8fafc' }}>
            {mode && <SignModal row={row} label={label} mode={mode} onClose={() => setMode(null)} />}
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                <span style={{ fontSize: '11px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Electronic Approval</span>
                <StatusPill row={row} />
                {row.prepared_by && <span style={{ fontSize: '12px', color: '#475569' }}>Prepared by {row.prepared_by}{row.submitted_at ? ` · submitted ${row.submitted_at}` : ''}</span>}
                <div style={{ flex: 1 }} />
                {row.can_sign && <>
                    <button type="button" onClick={() => setMode('approve')} style={btn('#16a34a', '#fff')}>Approve</button>
                    <button type="button" onClick={() => setMode('return')} style={btn('#fff', '#b45309', '#fed7aa')}>Return for Revision</button>
                </>}
            </div>
            <div style={{ marginTop: '10px' }}>
                {row.approvals.length > 0
                    ? <ApprovalTimeline steps={row.approvals} compact />
                    : <span style={{ fontSize: '12px', color: '#94a3b8' }}>Signed by: {chain.join(' → ')}</span>}
            </div>
            {row.return_remarks && row.status === 'draft' && (
                <div style={{ marginTop: '10px', padding: '8px 12px', background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: '8px', fontSize: '12.5px', color: '#9a3412' }}>
                    <strong>Returned for revision:</strong> {row.return_remarks}
                </div>
            )}
        </div>
    );
}

// ── Field rendering ────────────────────────────────────────────────────────

function scalarInput(field: FieldDef, value: any, onChange: (v: any) => void, disabled: boolean) {
    if (field.type === 'textarea') {
        return <textarea disabled={disabled} style={{ ...inputStyle, minHeight: '70px', resize: 'vertical' }} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
    }
    if (field.type === 'select') {
        return (
            <select disabled={disabled} style={inputStyle} value={value ?? ''} onChange={e => onChange(e.target.value)}>
                <option value="">—</option>
                {Object.entries(field.options ?? {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
        );
    }
    const type = field.type === 'date' ? 'date' : ['number', 'money', 'percent'].includes(field.type) ? 'number' : 'text';
    return <input disabled={disabled} type={type} step={type === 'number' ? '0.01' : undefined} min={field.type === 'money' || field.type === 'percent' ? 0 : undefined}
        max={field.type === 'percent' ? 100 : undefined} style={inputStyle} value={value ?? ''} onChange={e => onChange(e.target.value)} />;
}

function displayScalar(field: FieldDef, value: any): string {
    if (value === null || value === undefined || value === '') return '—';
    if (field.type === 'select') return field.options?.[String(value)] ?? String(value);
    if (field.type === 'money') return `PhP ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    if (field.type === 'percent') return `${value}%`;
    return String(value);
}

function TableField({ field, rows, onChange, disabled }: { field: FieldDef; rows: Record<string, any>[]; onChange: (rows: Record<string, any>[]) => void; disabled: boolean }) {
    const columns = field.columns ?? [];
    const update = (i: number, key: string, v: any) => onChange(rows.map((r, j) => (j === i ? { ...r, [key]: v } : r)));

    return (
        <div>
            <div style={{ overflowX: 'auto', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px', minWidth: `${columns.length * 120}px` }}>
                    <thead style={{ background: '#f1f5f9' }}>
                        <tr>
                            <th style={{ padding: '8px', fontSize: '10px', color: '#64748b', textAlign: 'left' }}>#</th>
                            {columns.map(c => <th key={c.key} style={{ padding: '8px', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', textAlign: 'left', whiteSpace: 'nowrap' }}>{c.label}{c.required ? ' *' : ''}</th>)}
                            {!disabled && <th />}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && <tr><td colSpan={columns.length + 2} style={{ padding: '14px', textAlign: 'center', color: '#94a3b8' }}>No rows yet.</td></tr>}
                        {rows.map((row, i) => (
                            <tr key={i} style={{ borderTop: '1px solid #f1f5f9', verticalAlign: 'top' }}>
                                <td style={{ padding: '6px 8px', color: '#94a3b8' }}>{i + 1}</td>
                                {columns.map(c => (
                                    <td key={c.key} style={{ padding: '6px', minWidth: c.type === 'textarea' ? '200px' : c.type === 'risk' ? '90px' : '120px' }}>
                                        {c.type === 'risk'
                                            ? <RiskBadge risk={riskOf(row[c.of![0]], row[c.of![1]])} />
                                            : disabled
                                                ? <span style={{ whiteSpace: 'pre-wrap' }}>{displayScalar(c, row[c.key])}</span>
                                                : scalarInput(c, row[c.key], v => update(i, c.key, v), false)}
                                    </td>
                                ))}
                                {!disabled && (
                                    <td style={{ padding: '6px' }}>
                                        <button type="button" onClick={() => onChange(rows.filter((_, j) => j !== i))} title="Remove row"
                                            style={{ border: 'none', background: 'none', color: '#dc2626', cursor: 'pointer', fontSize: '15px' }}>×</button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!disabled && <button type="button" onClick={() => onChange([...rows, {}])} style={{ ...btn('#fff', TONE, '#99f6e4'), marginTop: '8px' }}>+ Add Row</button>}
        </div>
    );
}

function ChecklistField({ field, value, onChange, disabled }: { field: FieldDef; value: Record<string, { answer: string | null; remark: string | null }>; onChange: (v: any) => void; disabled: boolean }) {
    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden' }}>
            {Object.entries(field.items ?? {}).map(([key, label], i) => {
                const item = value?.[key] ?? { answer: null, remark: null };
                const set = (patch: Partial<typeof item>) => onChange({ ...value, [key]: { ...item, ...patch } });
                return (
                    <div key={key} style={{ display: 'grid', gridTemplateColumns: 'minmax(200px, 1fr) auto minmax(160px, 260px)', gap: '10px', alignItems: 'center', padding: '8px 10px', borderTop: i ? '1px solid #f1f5f9' : 'none', fontSize: '12.5px' }}>
                        <span style={{ color: '#0f172a' }}>{label}</span>
                        <div role="radiogroup" aria-label={label} style={{ display: 'inline-flex', gap: '4px' }}>
                            {[['yes', 'Yes'], ['no', 'No'], ['na', 'N/A']].map(([v, l]) => {
                                const on = item.answer === v;
                                return (
                                    <button key={v} type="button" role="radio" aria-checked={on} disabled={disabled} onClick={() => set({ answer: v })}
                                        style={{ padding: '4px 10px', borderRadius: '6px', fontSize: '11.5px', fontWeight: 700, fontFamily: 'inherit', cursor: disabled ? 'default' : 'pointer',
                                            border: `1.5px solid ${on ? (v === 'no' ? '#dc2626' : TONE) : '#e2e8f0'}`, background: on ? (v === 'no' ? '#dc2626' : TONE) : '#fff', color: on ? '#fff' : '#475569', opacity: disabled && !on ? 0.5 : 1 }}>
                                        {l}
                                    </button>
                                );
                            })}
                        </div>
                        {disabled
                            ? <span style={{ color: '#475569' }}>{item.remark ?? ''}</span>
                            : <input style={{ ...inputStyle, padding: '5px 8px' }} placeholder="Remark" value={item.remark ?? ''} onChange={e => set({ remark: e.target.value })} />}
                    </div>
                );
            })}
        </div>
    );
}

// ── Editor / viewer ────────────────────────────────────────────────────────

function AssessmentForm({ project, definition, row, billings, editable, onDone }: {
    project: HubProject;
    definition: AssessmentDefinition;
    row: AssessmentRow | null;
    billings: BillingOption[];
    editable: boolean;
    onDone: () => void;
}) {
    const [data, setData] = useState<Record<string, any>>(() => JSON.parse(JSON.stringify(row?.data ?? {})));
    const [removed, setRemoved] = useState<number[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const filesRef = useRef<HTMLInputElement>(null);
    const { confirm, dialog } = useConfirm();

    const set = (key: string) => (v: any) => setData(d => ({ ...d, [key]: v }));

    const payload = () => {
        const fd = new FormData();
        const append = (prefix: string, value: any) => {
            if (value === null || value === undefined) return;
            if (typeof value === 'object') Object.entries(value).forEach(([k, v]) => append(`${prefix}[${k}]`, v));
            else fd.append(prefix, String(value));
        };
        Object.entries(data).forEach(([k, v]) => append(`data[${k}]`, v));
        Array.from(filesRef.current?.files ?? []).forEach(f => fd.append('files[]', f));
        removed.forEach(id => fd.append('removed_attachments[]', String(id)));
        return fd;
    };

    const save = (then?: () => void) => {
        setSaving(true);
        setErrors({});
        const url = row ? route('hub.assessments.update', [project.id, row.id]) : route('hub.assessments.store', [project.id, definition.type]);
        router.post(url, payload() as any, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { setRemoved([]); if (filesRef.current) filesRef.current.value = ''; (then ?? onDone)(); },
            onError: e => setErrors(e),
            onFinish: () => setSaving(false),
        });
    };

    const submit = () => {
        if (!row) return;
        confirm(`Save and submit ${row.control_no} for electronic approval? It is locked unless returned.`, () => save(() => {
            router.post(route('hub.assessments.submit', [project.id, row.id]), {}, { preserveScroll: true, onError: e => setErrors(e) });
        }), { title: 'Submit for Approval', confirmLabel: 'Submit' });
    };

    // Picking a billing fills in what it claims, where still blank.
    const pickBilling = (value: string) => {
        const b = billings.find(x => x.value === value);
        setData(d => ({
            ...d,
            billing: value,
            claimed_amount: d.claimed_amount ?? b?.amount ?? null,
            claimed_progress: d.claimed_progress ?? b?.progress ?? null,
        }));
    };

    return (
        <div>
            {dialog}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: '14px' }}>
                {definition.fields.map(field => {
                    const wide = ['textarea', 'table', 'checklist'].includes(field.type);
                    const label = `${field.label}${field.required ? ' *' : ''}`;
                    let control: React.ReactNode;

                    if (field.type === 'table') {
                        control = <TableField field={field} rows={data[field.key] ?? []} onChange={set(field.key)} disabled={!editable} />;
                    } else if (field.type === 'checklist') {
                        control = <ChecklistField field={field} value={data[field.key] ?? {}} onChange={set(field.key)} disabled={!editable} />;
                    } else if (field.type === 'billing') {
                        control = editable
                            ? <select style={inputStyle} value={data.billing ?? ''} onChange={e => pickBilling(e.target.value)}>
                                <option value="">{billings.length ? 'Choose a billing…' : 'No billings on this project yet'}</option>
                                {billings.map(b => <option key={b.value} value={b.value}>{b.label}</option>)}
                              </select>
                            : <div style={{ fontSize: '13px' }}>{billings.find(b => b.value === String(data.billing))?.label ?? '—'}</div>;
                    } else {
                        control = editable
                            ? scalarInput(field, data[field.key], set(field.key), false)
                            : <div style={{ fontSize: '13px', color: '#0f172a', whiteSpace: 'pre-wrap' }}>{displayScalar(field, data[field.key])}</div>;
                    }

                    return (
                        <div key={field.key} style={{ gridColumn: wide ? '1 / -1' : undefined }}>
                            <Field label={label}>{control}</Field>
                            {field.help && <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>{field.help}</div>}
                            {errors[`data.${field.key}`] && <div style={{ fontSize: '11.5px', color: '#dc2626', marginTop: '4px' }}>{errors[`data.${field.key}`]}</div>}
                        </div>
                    );
                })}
            </div>

            <div style={{ marginTop: '16px' }}>
                <Field label="Supporting Documents">
                    <div style={{ display: 'grid', gap: '4px' }}>
                        {(row?.files ?? []).map(f => (
                            <div key={f.id} style={{ display: 'flex', gap: '8px', fontSize: '12.5px' }}>
                                <a href={f.url} target="_blank" rel="noreferrer" style={{ flex: 1, color: '#2563eb', textDecoration: removed.includes(f.id) ? 'line-through' : 'none' }}>{f.filename}</a>
                                {editable && <button type="button" onClick={() => setRemoved(r => r.includes(f.id) ? r.filter(x => x !== f.id) : [...r, f.id])}
                                    style={{ border: 'none', background: 'none', color: '#dc2626', fontSize: '11.5px', fontWeight: 700, cursor: 'pointer' }}>{removed.includes(f.id) ? 'Undo' : 'Remove'}</button>}
                            </div>
                        ))}
                        {(row?.files ?? []).length === 0 && !editable && <span style={{ fontSize: '12px', color: '#94a3b8' }}>None attached.</span>}
                        {editable && <input type="file" multiple ref={filesRef} style={inputStyle} />}
                    </div>
                </Field>
            </div>

            {(errors.submit || errors.files) && (
                <div style={{ marginTop: '12px', padding: '10px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#b91c1c', fontSize: '12.5px', whiteSpace: 'pre-line' }}>
                    {errors.submit ?? errors.files}
                </div>
            )}

            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', flexWrap: 'wrap', marginTop: '16px', borderTop: '1px solid #e2e8f0', paddingTop: '14px' }}>
                <button type="button" onClick={onDone} style={btn('#fff', '#475569', '#cbd5e1')}>{editable ? 'Cancel' : 'Back'}</button>
                {editable && row && (
                    <button type="button" onClick={() => confirm(`Delete ${row.control_no}?`, () => router.delete(route('hub.assessments.destroy', [project.id, row.id]), { preserveScroll: true, onSuccess: onDone }), { title: 'Delete', confirmLabel: 'Delete', variant: 'danger' })}
                        style={btn('#fff', '#dc2626', '#fecaca')}>Delete</button>
                )}
                {editable && <button type="button" onClick={() => save()} disabled={saving} style={btn('#fff', TONE, '#99f6e4')}>{saving ? 'Saving…' : 'Save Draft'}</button>}
                {editable && row && <button type="button" onClick={submit} disabled={saving} style={btn(TONE, '#fff')}>Submit for Approval</button>}
            </div>
            {editable && !row && <div style={{ fontSize: '11px', color: '#94a3b8', textAlign: 'right', marginTop: '6px' }}>Save the draft first; Submit for Approval appears once it is saved.</div>}
        </div>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────

/**
 * A hub document with an electronic approver process — Safety Risk
 * Assessment, Geological Assessment or Billing Validation Report. The form
 * is drawn from its definition (config/project_assessments.php).
 */
export default function AssessmentHub({ project, definition, assessments, billings, canEdit }: {
    project: HubProject;
    definition: AssessmentDefinition;
    assessments: AssessmentRow[];
    billings: BillingOption[];
    canEdit: boolean;
}) {
    const [open, setOpen] = useState<number | 'new' | null>(null);
    const row = typeof open === 'number' ? assessments.find(a => a.id === open) ?? null : null;

    return (
        <HubShell>
            <InfoStrip project={project} accent={TONE} />

            {open !== null ? (
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '14px', flexWrap: 'wrap' }}>
                        <span style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{row ? `${row.control_no}${row.title ? ' — ' + row.title : ''}` : `New ${definition.label}`}</span>
                        {row && <StatusPill row={row} />}
                    </div>
                    {row && <div style={{ marginBottom: '16px' }}><SignoffBar row={row} label={definition.label} chain={definition.chain_labels} /></div>}
                    <AssessmentForm key={row?.id ?? 'new'} project={project} definition={definition} row={row} billings={billings}
                        editable={canEdit && (!row || row.status === 'draft')} onDone={() => setOpen(null)} />
                </div>
            ) : (
                <>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '14px', flexWrap: 'wrap' }}>
                        <span style={{ fontSize: '12.5px', color: '#475569' }}>
                            Prepared by the engineer, then signed electronically by: <strong>{definition.chain_labels.join(' → ')}</strong>
                        </span>
                        <div style={{ flex: 1 }} />
                        {canEdit && <button type="button" onClick={() => setOpen('new')} style={btn(TONE, '#fff')}>+ New {definition.label}</button>}
                    </div>
                    {assessments.length === 0 ? (
                        <div style={{ padding: '44px 24px', border: '1px dashed #e2e8f0', borderRadius: '10px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
                            No {definition.label.toLowerCase()} yet.
                        </div>
                    ) : (
                        <div style={{ display: 'grid', gap: '10px' }}>
                            {assessments.map(a => (
                                <button key={a.id} type="button" onClick={() => setOpen(a.id)}
                                    style={{ textAlign: 'left', border: '1px solid #e2e8f0', borderRadius: '10px', padding: '12px 14px', background: '#fff', cursor: 'pointer', fontFamily: 'inherit', display: 'grid', gap: '8px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                                        <span style={{ fontWeight: 800, color: '#0f172a' }}>{a.control_no}</span>
                                        <StatusPill row={a} />
                                        <span style={{ fontSize: '12.5px', color: '#334155' }}>{a.title ?? 'Untitled'}</span>
                                        <div style={{ flex: 1 }} />
                                        <span style={{ fontSize: '11.5px', color: '#64748b' }}>{a.prepared_by ?? '—'} · {a.created}</span>
                                        {a.can_sign && <span style={{ fontSize: '11px', fontWeight: 800, color: '#b45309' }}>Awaiting your signature</span>}
                                    </div>
                                    {a.approvals.length > 0 && <ApprovalTimeline steps={a.approvals} compact />}
                                </button>
                            ))}
                        </div>
                    )}
                </>
            )}
        </HubShell>
    );
}
