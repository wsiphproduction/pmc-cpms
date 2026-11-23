import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { useConfirm } from '@/components/useConfirm';
import { Field, HubProject, HubShell, InfoStrip, Modal, inputStyle } from './Common';

interface FileRef { id: number; filename: string; url: string }

export interface FindingRow {
    id: number;
    control_no: string;
    evaluation: { id: number; control_no: string } | null;
    category: string | null;
    category_label: string | null;
    contractor_name: string;
    on_master_data: boolean;
    supplier_emails: string[];
    deficiency: string;
    date_identified: string;
    corrective_action: string;
    target_date: string | null;
    status: 'open' | 'responded' | 'closed';
    status_label: string;
    overdue: boolean;
    days_open: number;
    contractor_response: string | null;
    responded_at: string | null;
    response_recorded_by: string | null;
    closure_date: string | null;
    closure_remarks: string | null;
    closed_by: string | null;
    notified_email: string | null;
    notified_at: string | null;
    portal_url: string;
    raised_by: string | null;
    evidence: FileRef[];
    response_files: FileRef[];
    closure_files: FileRef[];
}

const TONE = '#b91c1c';

const STATUS_STYLE: Record<string, { bg: string; color: string; border: string }> = {
    Open:      { bg: '#fff7ed', color: '#9a3412', border: '#fed7aa' },
    Responded: { bg: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe' },
    Overdue:   { bg: '#fef2f2', color: '#b91c1c', border: '#fecaca' },
    Closed:    { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' },
};

function btn(bg: string, color: string, border = bg): React.CSSProperties {
    return { padding: '7px 12px', borderRadius: '7px', border: `1px solid ${border}`, background: bg, color, fontSize: '12px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', whiteSpace: 'nowrap' };
}

const textarea: React.CSSProperties = { ...inputStyle, minHeight: '80px', resize: 'vertical' };

function StatusPill({ label }: { label: string }) {
    const s = STATUS_STYLE[label] ?? STATUS_STYLE.Open;
    return <span style={{ padding: '2px 10px', borderRadius: '999px', fontSize: '11px', fontWeight: 700, background: s.bg, color: s.color, border: `1px solid ${s.border}` }}>{label === 'Overdue' ? '⚠ Overdue' : label}</span>;
}

function Files({ files, label }: { files: FileRef[]; label: string }) {
    if (files.length === 0) return null;
    return (
        <div style={{ marginTop: '6px', fontSize: '12px' }}>
            <span style={{ color: '#64748b', fontWeight: 700 }}>{label}: </span>
            {files.map((f, i) => (
                <span key={f.id}>{i > 0 && ', '}<a href={f.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none' }}>{f.filename}</a></span>
            ))}
        </div>
    );
}

function ErrorLine({ errors }: { errors: Record<string, string> }) {
    const text = Object.values(errors).join('\n');
    if (!text) return null;
    return <div style={{ marginTop: '10px', padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#b91c1c', fontSize: '12.5px', whiteSpace: 'pre-line' }}>{text}</div>;
}

// ── Log / edit ─────────────────────────────────────────────────────────────

function FindingModal({ project, finding, contractors, categories, onClose }: {
    project: HubProject;
    finding: FindingRow | null;
    contractors: string[];
    categories: { value: string; label: string }[];
    onClose: () => void;
}) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({
        contractor_name:   finding?.contractor_name ?? contractors[0] ?? '',
        category:          finding?.category ?? '',
        deficiency:        finding?.deficiency ?? '',
        date_identified:   finding?.date_identified ?? today,
        corrective_action: finding?.corrective_action ?? '',
        target_date:       finding?.target_date ?? '',
    });
    const [removed, setRemoved] = useState<number[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const filesRef = useRef<HTMLInputElement>(null);
    const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => setForm(f => ({ ...f, [k]: e.target.value }));

    const save = () => {
        setSaving(true);
        const fd = new FormData();
        Object.entries(form).forEach(([k, v]) => fd.append(k, v));
        Array.from(filesRef.current?.files ?? []).forEach(f => fd.append('evidence[]', f));
        removed.forEach(id => fd.append('removed_attachments[]', String(id)));
        const url = finding ? route('hub.ncr.update', [project.id, finding.id]) : route('hub.ncr.store', project.id);
        router.post(url, fd as any, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => onClose(), onError: e => setErrors(e), onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title={finding ? `Edit ${finding.control_no}` : 'Log Non-Compliance'} onClose={onClose} headerBg={TONE} size="680px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#475569', '#cbd5e1')}>Cancel</button>
                <button type="button" onClick={save} disabled={saving} style={btn(TONE, '#fff')}>{saving ? 'Saving…' : finding ? 'Save' : 'Log Record'}</button>
            </>}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                <Field label="Responsible Contractor">
                    <input style={inputStyle} list="ncr-contractors" value={form.contractor_name} onChange={set('contractor_name')} />
                    <datalist id="ncr-contractors">{contractors.map(c => <option key={c} value={c} />)}</datalist>
                </Field>
                <Field label="Evaluation Category (optional)">
                    <select style={inputStyle} value={form.category} onChange={set('category')}>
                        <option value="">—</option>
                        {categories.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                </Field>
                <div style={{ gridColumn: '1 / -1' }}>
                    <Field label="Deficiency"><textarea style={textarea} value={form.deficiency} onChange={set('deficiency')} placeholder="What was found, and where" /></Field>
                </div>
                <Field label="Date Identified"><input type="date" style={inputStyle} max={today} value={form.date_identified} onChange={set('date_identified')} /></Field>
                <Field label="Target Date (optional)"><input type="date" style={inputStyle} min={form.date_identified} value={form.target_date} onChange={set('target_date')} /></Field>
                <div style={{ gridColumn: '1 / -1' }}>
                    <Field label="Required Corrective Action"><textarea style={textarea} value={form.corrective_action} onChange={set('corrective_action')} /></Field>
                </div>
                <div style={{ gridColumn: '1 / -1' }}>
                    <Field label="Evidence (inspection reports, photos…) — internal, not shown to the contractor">
                        {finding && finding.evidence.length > 0 && (
                            <div style={{ display: 'grid', gap: '4px', marginBottom: '6px' }}>
                                {finding.evidence.map(f => (
                                    <div key={f.id} style={{ display: 'flex', gap: '8px', fontSize: '12px' }}>
                                        <a href={f.url} target="_blank" rel="noreferrer" style={{ flex: 1, color: '#2563eb', textDecoration: removed.includes(f.id) ? 'line-through' : 'none' }}>{f.filename}</a>
                                        <button type="button" onClick={() => setRemoved(r => r.includes(f.id) ? r.filter(x => x !== f.id) : [...r, f.id])}
                                            style={{ border: 'none', background: 'none', color: '#dc2626', fontSize: '11.5px', fontWeight: 700, cursor: 'pointer' }}>{removed.includes(f.id) ? 'Undo' : 'Remove'}</button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <input type="file" multiple ref={filesRef} style={inputStyle} />
                    </Field>
                </div>
            </div>
            <ErrorLine errors={errors} />
        </Modal>
    );
}

// ── Send, respond, close ───────────────────────────────────────────────────

function ActionModal({ project, finding, mode, onClose }: { project: HubProject; finding: FindingRow; mode: 'send' | 'response' | 'close'; onClose: () => void }) {
    const today = new Date().toISOString().slice(0, 10);
    const [email, setEmail] = useState(finding.notified_email ?? finding.supplier_emails.join(', '));
    const [text, setText] = useState('');
    const [date, setDate] = useState(today);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const filesRef = useRef<HTMLInputElement>(null);

    const meta = {
        send:     { title: `Send ${finding.control_no} to the Contractor`, verb: 'Send Email', tone: '#1d4ed8' },
        response: { title: `Record Contractor Response — ${finding.control_no}`, verb: 'Record Response', tone: '#1d4ed8' },
        close:    { title: `Close ${finding.control_no}`, verb: 'Close Record', tone: '#16a34a' },
    }[mode];

    const save = () => {
        setSaving(true);
        const fd = new FormData();
        if (mode === 'send') fd.append('email', email);
        if (mode === 'response') fd.append('contractor_response', text);
        if (mode === 'close') { fd.append('closure_date', date); fd.append('closure_remarks', text); }
        Array.from(filesRef.current?.files ?? []).forEach(f => fd.append('files[]', f));
        const name = mode === 'send' ? 'hub.ncr.send' : mode === 'response' ? 'hub.ncr.response' : 'hub.ncr.close';
        router.post(route(name, [project.id, finding.id]), fd as any, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => onClose(), onError: e => setErrors(e), onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title={meta.title} onClose={onClose} headerBg={meta.tone} size="560px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#475569', '#cbd5e1')}>Cancel</button>
                <button type="button" onClick={save} disabled={saving} style={btn(meta.tone, '#fff')}>{saving ? 'Saving…' : meta.verb}</button>
            </>}>
            {mode === 'send' && <>
                <Field label="Contractor email(s), separated by commas">
                    <input style={inputStyle} value={email} onChange={e => setEmail(e.target.value)} />
                </Field>
                <p style={{ fontSize: '12px', color: '#64748b', lineHeight: 1.6, margin: '10px 0 0' }}>
                    The contractor receives the deficiency and the required corrective action, with a private link to send their explanation and documents.
                    {finding.supplier_emails.length === 0 && ' No email is on Master Data for this contractor — enter one.'}
                </p>
            </>}
            {mode === 'response' && <>
                <Field label="Contractor’s explanation or comments"><textarea style={textarea} value={text} onChange={e => setText(e.target.value)} /></Field>
                <p style={{ fontSize: '12px', color: '#64748b', margin: '8px 0 10px' }}>For a response received by letter, email or in a meeting. It is recorded under your name.</p>
                <Field label="Attachments (optional)"><input type="file" multiple ref={filesRef} style={inputStyle} /></Field>
            </>}
            {mode === 'close' && <>
                <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr', gap: '14px' }}>
                    <Field label="Closure Date"><input type="date" style={inputStyle} min={finding.date_identified} max={today} value={date} onChange={e => setDate(e.target.value)} /></Field>
                    <Field label="Proof of closure (optional)"><input type="file" multiple ref={filesRef} style={inputStyle} /></Field>
                </div>
                <div style={{ marginTop: '12px' }}>
                    <Field label="How was the corrective action verified?"><textarea style={textarea} value={text} onChange={e => setText(e.target.value)} /></Field>
                </div>
            </>}
            <ErrorLine errors={errors} />
        </Modal>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────

const FILTERS = ['All', 'Open', 'Responded', 'Overdue', 'Closed'] as const;

/**
 * Non-Compliance and Corrective Actions (hub group G): each deficiency found
 * in the contractor's work, the action required, the contractor's response,
 * and its closure (framework §9).
 */
export default function NcrHub({ project, findings, contractors, categories, canEdit }: {
    project: HubProject;
    findings: FindingRow[];
    contractors: string[];
    categories: { value: string; label: string }[];
    canEdit: boolean;
}) {
    const [filter, setFilter] = useState<(typeof FILTERS)[number]>('All');
    const [editing, setEditing] = useState<FindingRow | 'new' | null>(null);
    const [action, setAction] = useState<{ finding: FindingRow; mode: 'send' | 'response' | 'close' } | null>(null);
    const [copied, setCopied] = useState<number | null>(null);
    const { confirm, dialog } = useConfirm();

    const count = (label: string) => label === 'All' ? findings.length : findings.filter(f => f.status_label === label || (label === 'Overdue' && f.overdue)).length;
    const shown = filter === 'All' ? findings : findings.filter(f => filter === 'Overdue' ? f.overdue : f.status_label === filter);

    const copy = (f: FindingRow) => {
        try {
            void navigator.clipboard.writeText(f.portal_url);
            setCopied(f.id);
            window.setTimeout(() => setCopied(null), 2000);
        } catch { /* clipboard unavailable */ }
    };

    return (
        <HubShell>
            {dialog}
            {editing && <FindingModal project={project} finding={editing === 'new' ? null : editing} contractors={contractors} categories={categories} onClose={() => setEditing(null)} />}
            {action && <ActionModal project={project} finding={action.finding} mode={action.mode} onClose={() => setAction(null)} />}
            <InfoStrip project={project} accent={TONE} />

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap', marginBottom: '16px' }}>
                {FILTERS.map(label => (
                    <button key={label} type="button" onClick={() => setFilter(label)}
                        style={{ ...btn(filter === label ? '#0f172a' : '#fff', filter === label ? '#fff' : '#475569', filter === label ? '#0f172a' : '#e2e8f0'), borderRadius: '999px' }}>
                        {label} <span style={{ opacity: 0.7 }}>{count(label)}</span>
                    </button>
                ))}
                <div style={{ flex: 1 }} />
                {canEdit && <button type="button" onClick={() => setEditing('new')} style={btn(TONE, '#fff')}>+ Log Non-Compliance</button>}
            </div>

            {shown.length === 0 ? (
                <div style={{ padding: '44px 24px', border: '1px dashed #e2e8f0', borderRadius: '10px', textAlign: 'center' }}>
                    <div style={{ fontSize: '13.5px', fontWeight: 700, color: '#64748b', marginBottom: '4px' }}>{findings.length === 0 ? 'No non-compliance records' : `No ${filter.toLowerCase()} records`}</div>
                    <div style={{ fontSize: '12.5px', color: '#94a3b8', maxWidth: '480px', margin: '0 auto' }}>
                        Records are raised automatically from the “Required Corrective Actions” of an approved contractor evaluation, or logged here directly.
                    </div>
                </div>
            ) : (
                <div style={{ display: 'grid', gap: '12px' }}>
                    {shown.map(f => (
                        <div key={f.id} style={{ border: '1px solid #e2e8f0', borderLeft: `4px solid ${f.overdue ? '#dc2626' : f.status === 'closed' ? '#16a34a' : f.status === 'responded' ? '#2563eb' : '#f59e0b'}`, borderRadius: '10px', padding: '14px 16px', background: '#fff' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '8px' }}>
                                <span style={{ fontWeight: 800, color: '#0f172a', fontSize: '13.5px' }}>{f.control_no}</span>
                                <StatusPill label={f.overdue ? 'Overdue' : f.status_label} />
                                <span style={{ fontSize: '12px', color: '#475569' }}>{f.contractor_name}{!f.on_master_data && <span style={{ color: '#b45309' }}> (not on Master Data)</span>}</span>
                                {f.category_label && <span style={{ fontSize: '11.5px', color: '#64748b' }}>· {f.category_label}</span>}
                                {f.evaluation && <span style={{ fontSize: '11.5px', color: '#7c3aed' }}>· from {f.evaluation.control_no}</span>}
                                <div style={{ flex: 1 }} />
                                <span style={{ fontSize: '11.5px', color: '#64748b' }}>
                                    Identified {f.date_identified}{f.target_date ? ` · target ${f.target_date}` : ''} · {f.days_open} day{f.days_open === 1 ? '' : 's'} {f.status === 'closed' ? 'to close' : 'open'}
                                </span>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', fontSize: '12.5px', color: '#0f172a', lineHeight: 1.55 }}>
                                <div><div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Deficiency</div><div style={{ whiteSpace: 'pre-wrap' }}>{f.deficiency}</div><Files files={f.evidence} label="Evidence" /></div>
                                <div><div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Required Corrective Action</div><div style={{ whiteSpace: 'pre-wrap' }}>{f.corrective_action}</div></div>
                            </div>
                            {f.contractor_response && (
                                <div style={{ marginTop: '10px', padding: '10px 12px', background: '#eff6ff', borderRadius: '8px', fontSize: '12.5px', color: '#0f172a' }}>
                                    <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#1d4ed8', textTransform: 'uppercase' }}>
                                        Contractor Response · {f.responded_at}{f.response_recorded_by ? ` · recorded by ${f.response_recorded_by}` : ' · via contractor link'}
                                    </div>
                                    <div style={{ whiteSpace: 'pre-wrap' }}>{f.contractor_response}</div>
                                    <Files files={f.response_files} label="Attached" />
                                </div>
                            )}
                            {f.status === 'closed' && (
                                <div style={{ marginTop: '10px', padding: '10px 12px', background: '#f0fdf4', borderRadius: '8px', fontSize: '12.5px', color: '#0f172a' }}>
                                    <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#166534', textTransform: 'uppercase' }}>Closed {f.closure_date}{f.closed_by ? ` · by ${f.closed_by}` : ''}</div>
                                    <div style={{ whiteSpace: 'pre-wrap' }}>{f.closure_remarks}</div>
                                    <Files files={f.closure_files} label="Proof" />
                                </div>
                            )}
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap', marginTop: '12px' }}>
                                <span style={{ fontSize: '11.5px', color: '#94a3b8', marginRight: 'auto' }}>
                                    {f.notified_at ? `Sent to ${f.notified_email} on ${f.notified_at}` : 'Not yet sent to the contractor'}{f.raised_by ? ` · logged by ${f.raised_by}` : ''}
                                </span>
                                {canEdit && f.status !== 'closed' && <>
                                    <button type="button" onClick={() => setAction({ finding: f, mode: 'send' })} style={btn('#fff', '#1d4ed8', '#bfdbfe')}>{f.notified_at ? 'Resend' : 'Send to Contractor'}</button>
                                    <button type="button" onClick={() => copy(f)} style={btn('#fff', '#475569', '#e2e8f0')}>{copied === f.id ? 'Link copied ✓' : 'Copy Contractor Link'}</button>
                                    <button type="button" onClick={() => setAction({ finding: f, mode: 'response' })} style={btn('#fff', '#1d4ed8', '#bfdbfe')}>Record Response</button>
                                    <button type="button" onClick={() => setEditing(f)} style={btn('#fff', '#475569', '#e2e8f0')}>Edit</button>
                                    <button type="button" onClick={() => setAction({ finding: f, mode: 'close' })} style={btn('#16a34a', '#fff')}>Close</button>
                                    <button type="button" onClick={() => confirm(`Delete ${f.control_no}?`, () => router.delete(route('hub.ncr.destroy', [project.id, f.id]), { preserveScroll: true }), { title: 'Delete Record', confirmLabel: 'Delete', variant: 'danger' })}
                                        style={btn('#fff', '#dc2626', '#fecaca')}>Delete</button>
                                </>}
                                {canEdit && f.status === 'closed' && (
                                    <button type="button" onClick={() => confirm(`Reopen ${f.control_no}? Its closure is cleared.`, () => router.post(route('hub.ncr.reopen', [project.id, f.id]), {}, { preserveScroll: true }), { title: 'Reopen Record', confirmLabel: 'Reopen' })}
                                        style={btn('#fff', '#475569', '#e2e8f0')}>Reopen</button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </HubShell>
    );
}
