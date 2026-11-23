import { router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import ApprovalTimeline, { type ApprovalStep } from '@/components/ApprovalTimeline';
import { FileHistory, type FileVersion } from '@/components/FileVersions';
import { useConfirm } from '@/components/useConfirm';
import { Field, HubProject, HubShell, Modal, inputStyle } from './Common';

// ── Types ──────────────────────────────────────────────────────────────────

interface Criterion { key: string; label: string; weight: number; basis: string }

interface CategoryDef {
    key: string;
    letter: string;
    title: string;
    weight: number;
    purpose: string;
    note: string;
    criteria: Criterion[];
}

export interface EvaluationDefinition {
    categories: CategoryDef[];
    ratings: { value: number; label: string; description: string }[];
    classifications: { min: number; label: string; action: string }[];
    period_types: { value: string; label: string }[];
}

interface EvalFile { id: number; filename: string; url: string; versions?: FileVersion[] }

interface CategoryData {
    ratings: Record<string, number>;
    score: number | null;
    complete: boolean;
    comments: string | null;
    corrective_actions: string | null;
    form: EvalFile | null;
    evidence: EvalFile[];
}

export interface EvaluationRow {
    id: number;
    control_no: string;
    period_type: string;
    period_label: string;
    period_start: string | null;
    period_end: string | null;
    contractor_name: string;
    supplier: { id: number; company: string } | null;
    status: 'draft' | 'for_approval' | 'approved';
    status_label: string;
    total_score: number | null;
    classification: { label: string; action: string } | null;
    return_remarks: string | null;
    evaluated_by: string | null;
    created: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    approvals: ApprovalStep[];
    can_sign: boolean;
    categories: Record<string, CategoryData>;
}

const TONE = '#7c3aed';

const STATUS_TONE: Record<EvaluationRow['status'], { bg: string; color: string; border: string }> = {
    draft:        { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' },
    for_approval: { bg: '#fefce8', color: '#854d0e', border: '#fef08a' },
    approved:     { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' },
};

const CLASS_TONE: Record<string, string> = {
    'Excellent': '#15803d',
    'Very Good': '#2563eb',
    'Satisfactory': '#0f766e',
    'Needs Improvement': '#c2410c',
    'Unsatisfactory': '#b91c1c',
};

const fmt = (n: number | null | undefined, digits = 2) =>
    n === null || n === undefined ? '—' : Number(n).toFixed(digits);

/** Which evaluation the page is on: the one named in the URL, else the newest. */
function selectedIdFromUrl(): number | null {
    try {
        const raw = new URLSearchParams(window.location.search).get('evaluation');
        return raw ? Number(raw) : null;
    } catch {
        return null;
    }
}

function visit(section: string, projectId: number, evaluationId?: number) {
    const url = evaluationId
        ? route('projects.hub.' + section, { project: projectId, evaluation: evaluationId })
        : route('projects.hub.' + section, projectId);
    router.visit(url, { preserveScroll: true });
}

// ── Small pieces ───────────────────────────────────────────────────────────

function StatusPill({ row }: { row: EvaluationRow }) {
    const t = STATUS_TONE[row.status];
    return (
        <span style={{ padding: '3px 10px', borderRadius: '999px', fontSize: '11px', fontWeight: 700, background: t.bg, color: t.color, border: `1px solid ${t.border}`, whiteSpace: 'nowrap' }}>
            {row.status_label}
        </span>
    );
}

function ClassPill({ label }: { label: string | undefined }) {
    if (!label) return <span style={{ fontSize: '12px', color: '#94a3b8' }}>Not yet evaluated</span>;
    const color = CLASS_TONE[label] ?? '#475569';
    return <span style={{ fontSize: '12px', fontWeight: 800, color }}>{label}</span>;
}

function ErrorBox({ message }: { message: string }) {
    if (!message) return null;
    return (
        <div style={{ padding: '10px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#b91c1c', fontSize: '12.5px', fontWeight: 600, whiteSpace: 'pre-line', marginBottom: '14px' }}>
            {message}
        </div>
    );
}

function btn(bg: string, color: string, border = bg): React.CSSProperties {
    return {
        padding: '8px 14px', borderRadius: '8px', border: `1px solid ${border}`, background: bg, color,
        fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', whiteSpace: 'nowrap',
    };
}

// ── New / edit evaluation ─────────────────────────────────────────────────

function HeaderModal({ project, definition, contractors, evaluation, onClose }: {
    project: HubProject;
    definition: EvaluationDefinition;
    contractors: string[];
    evaluation: EvaluationRow | null;
    onClose: () => void;
}) {
    const [form, setForm] = useState({
        period_type:     evaluation?.period_type ?? 'monthly',
        period_label:    evaluation?.period_label ?? '',
        period_start:    evaluation?.period_start ?? '',
        period_end:      evaluation?.period_end ?? '',
        contractor_name: evaluation?.contractor_name ?? contractors[0] ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm(f => ({ ...f, [key]: e.target.value }));

    const save = () => {
        setSaving(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (e: Record<string, string>) => setErrors(e),
            onFinish: () => setSaving(false),
        };
        if (evaluation) router.patch(route('hub.evaluations.update', [project.id, evaluation.id]), form, options);
        else router.post(route('hub.evaluations.store', project.id), form, options);
    };

    return (
        <Modal
            title={evaluation ? `Edit ${evaluation.control_no}` : 'New Contractor Evaluation'}
            onClose={onClose}
            headerBg={TONE}
            size="560px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#475569', '#cbd5e1')}>Cancel</button>
                <button type="button" onClick={save} disabled={saving} style={btn(TONE, '#fff')}>{saving ? 'Saving…' : evaluation ? 'Save' : 'Create Evaluation'}</button>
            </>}
        >
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                <Field label="Evaluation Type">
                    <select style={inputStyle} value={form.period_type} onChange={set('period_type')}>
                        {definition.period_types.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                    </select>
                </Field>
                <Field label="Evaluation Period">
                    <input style={inputStyle} value={form.period_label} onChange={set('period_label')} placeholder='e.g. "October 2026" or "Q4 2026"' />
                    {errors.period_label && <div style={{ color: '#dc2626', fontSize: '11.5px', marginTop: '4px' }}>{errors.period_label}</div>}
                </Field>
                <Field label="Period Start (optional)">
                    <input type="date" style={inputStyle} value={form.period_start} onChange={set('period_start')} />
                </Field>
                <Field label="Period End (optional)">
                    <input type="date" style={inputStyle} value={form.period_end} onChange={set('period_end')} />
                    {errors.period_end && <div style={{ color: '#dc2626', fontSize: '11.5px', marginTop: '4px' }}>{errors.period_end}</div>}
                </Field>
                <div style={{ gridColumn: '1 / -1' }}>
                    <Field label="Contractor">
                        <input style={inputStyle} list="cpe-contractors" value={form.contractor_name} onChange={set('contractor_name')} placeholder="Contractor being evaluated" />
                        <datalist id="cpe-contractors">{contractors.map(c => <option key={c} value={c} />)}</datalist>
                        {errors.contractor_name && <div style={{ color: '#dc2626', fontSize: '11.5px', marginTop: '4px' }}>{errors.contractor_name}</div>}
                    </Field>
                    <div style={{ fontSize: '11.5px', color: '#64748b', marginTop: '6px' }}>
                        Suggested from this project’s NTPs. A name matching a contractor on Master Data links the evaluation to that contractor’s profile.
                    </div>
                </div>
            </div>
        </Modal>
    );
}

// ── Sign-off ───────────────────────────────────────────────────────────────

function SignModal({ evaluation, mode, onClose }: { evaluation: EvaluationRow; mode: 'approve' | 'return'; onClose: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const current = evaluation.approvals.find(s => s.is_current);
    const verb = mode === 'return' ? 'Return for Revision' : current?.role === 'pmd_asst_manager' ? 'Mark Reviewed' : 'Approve';

    const save = () => {
        setSaving(true);
        router.patch(route(mode === 'approve' ? 'evaluations.approve' : 'evaluations.return', evaluation.id), { remarks }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: e => setError(e.remarks ?? 'Could not save.'),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            title={`${verb} — ${evaluation.control_no}`}
            onClose={onClose}
            headerBg={mode === 'approve' ? '#16a34a' : '#b45309'}
            size="520px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#475569', '#cbd5e1')}>Cancel</button>
                <button type="button" onClick={save} disabled={saving} style={btn(mode === 'approve' ? '#16a34a' : '#b45309', '#fff')}>{saving ? 'Saving…' : verb}</button>
            </>}
        >
            <div style={{ fontSize: '13px', color: '#334155', marginBottom: '12px' }}>
                {evaluation.contractor_name} · {evaluation.period_label} · Score <strong>{fmt(evaluation.total_score)}</strong> ({evaluation.classification?.label ?? '—'})
            </div>
            <Field label={mode === 'return' ? 'What needs revising?' : 'Remarks (optional)'}>
                <textarea style={{ ...inputStyle, minHeight: '90px', resize: 'vertical' }} value={remarks} onChange={e => setRemarks(e.target.value)} />
            </Field>
            {error && <div style={{ color: '#dc2626', fontSize: '12px', marginTop: '6px' }}>{error}</div>}
        </Modal>
    );
}

// ── Summary ────────────────────────────────────────────────────────────────

function SummaryView({ project, evaluation, evaluations, definition, canEdit, onEdit, onSign }: {
    project: HubProject;
    evaluation: EvaluationRow;
    evaluations: EvaluationRow[];
    definition: EvaluationDefinition;
    canEdit: boolean;
    onEdit: () => void;
    onSign: (mode: 'approve' | 'return') => void;
}) {
    const [submitError, setSubmitError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const { confirm, dialog } = useConfirm();
    const editable = canEdit && evaluation.status === 'draft';
    const classTone = CLASS_TONE[evaluation.classification?.label ?? ''] ?? '#94a3b8';

    const submit = () => confirm(
        `Submit ${evaluation.control_no} to the PMD Assistant Manager for review? It can no longer be changed unless it is returned.`,
        () => {
            setSubmitting(true);
            router.post(route('hub.evaluations.submit', [project.id, evaluation.id]), {}, {
                preserveScroll: true,
                onSuccess: () => setSubmitError(''),
                onError: e => setSubmitError(e.submit ?? 'Could not submit.'),
                onFinish: () => setSubmitting(false),
            });
        },
        { title: 'Submit Evaluation', confirmLabel: 'Submit' },
    );

    const remove = () => confirm(
        `Delete ${evaluation.control_no}? Its ratings and files are removed.`,
        () => router.delete(route('hub.evaluations.destroy', [project.id, evaluation.id])),
        { title: 'Delete Evaluation', confirmLabel: 'Delete', variant: 'danger' },
    );

    return (
        <>
            {dialog}
            {evaluation.return_remarks && evaluation.status === 'draft' && (
                <div style={{ padding: '10px 14px', marginBottom: '14px', background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: '8px', fontSize: '12.5px', color: '#9a3412' }}>
                    <strong>Returned for revision:</strong> {evaluation.return_remarks}
                </div>
            )}
            <ErrorBox message={submitError} />

            {/* Score card */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 220px', gap: '16px', marginBottom: '18px' }}>
                <div style={{ border: '1px solid #e2e8f0', borderLeft: `4px solid ${TONE}`, borderRadius: '10px', padding: '16px', background: '#faf5ff' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '10px' }}>
                        <span style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{evaluation.control_no}</span>
                        <StatusPill row={evaluation} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '10px 16px', fontSize: '12.5px' }}>
                        {[
                            ['Contractor', <>{evaluation.contractor_name}{evaluation.supplier
                                ? <span title="Linked to the contractor on Master Data" style={{ marginLeft: '6px', color: '#16a34a', fontWeight: 700 }}>✓ Master Data</span>
                                : <span title="No contractor on Master Data has this exact name, so this evaluation will not consolidate into a contractor profile." style={{ marginLeft: '6px', color: '#b45309', fontWeight: 700 }}>not on Master Data</span>}</>],
                            ['Evaluation Period', `${definition.period_types.find(p => p.value === evaluation.period_type)?.label ?? evaluation.period_type} — ${evaluation.period_label}`],
                            ['Evaluated By', evaluation.evaluated_by ?? '—'],
                        ].map(([label, value], i) => (
                            <div key={i}>
                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '2px' }}>{label}</div>
                                <div style={{ color: '#0f172a', fontWeight: 600 }}>{value}</div>
                            </div>
                        ))}
                    </div>
                    {evaluation.approvals.length > 0 && (
                        <div style={{ marginTop: '14px' }}><ApprovalTimeline steps={evaluation.approvals} compact /></div>
                    )}
                    <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '14px' }}>
                        {editable && <>
                            <button type="button" onClick={submit} disabled={submitting} style={btn(TONE, '#fff')}>{submitting ? 'Submitting…' : 'Submit for Review'}</button>
                            <button type="button" onClick={onEdit} style={btn('#fff', TONE, '#ddd6fe')}>Edit Details</button>
                            <button type="button" onClick={remove} style={btn('#fff', '#dc2626', '#fecaca')}>Delete</button>
                        </>}
                        {evaluation.can_sign && <>
                            <button type="button" onClick={() => onSign('approve')} style={btn('#16a34a', '#fff')}>
                                {evaluation.approvals.find(s => s.is_current)?.role === 'pmd_asst_manager' ? 'Mark Reviewed' : 'Approve'}
                            </button>
                            <button type="button" onClick={() => onSign('return')} style={btn('#fff', '#b45309', '#fed7aa')}>Return for Revision</button>
                        </>}
                    </div>
                </div>
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '10px', padding: '16px', textAlign: 'center', display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: '4px' }}>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Project Score</div>
                    <div style={{ fontSize: '34px', fontWeight: 900, color: classTone, lineHeight: 1.1 }}>{fmt(evaluation.total_score)}</div>
                    <div style={{ fontSize: '11px', color: '#94a3b8' }}>out of 100</div>
                    <ClassPill label={evaluation.classification?.label} />
                    {evaluation.classification && <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>{evaluation.classification.action}</div>}
                </div>
            </div>

            {/* Category breakdown */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', marginBottom: '22px' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f1f5f9' }}>
                            {['Category', 'Weight', 'Score', 'Status', 'Form', 'Evidence', ''].map(h => (
                                <th key={h} style={{ padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {definition.categories.map(cat => {
                            const data = evaluation.categories[cat.key];
                            const done = data?.complete && (data?.evidence.length ?? 0) > 0;
                            return (
                                <tr key={cat.key} style={{ borderTop: '1px solid #f1f5f9' }}>
                                    <td style={{ padding: '10px 12px', fontWeight: 600, color: '#0f172a' }}>{cat.letter}. {cat.title}</td>
                                    <td style={{ padding: '10px 12px', color: '#475569' }}>{cat.weight}%</td>
                                    <td style={{ padding: '10px 12px', fontWeight: 700, color: '#0f172a' }}>{fmt(data?.score)} <span style={{ color: '#94a3b8', fontWeight: 500 }}>/ {cat.weight}</span></td>
                                    <td style={{ padding: '10px 12px' }}>
                                        {done
                                            ? <span style={{ color: '#16a34a', fontWeight: 700, fontSize: '12px' }}>✓ Complete</span>
                                            : <span style={{ color: '#b45309', fontWeight: 700, fontSize: '12px' }}>{data?.complete ? 'Needs evidence' : 'Not rated'}</span>}
                                    </td>
                                    <td style={{ padding: '10px 12px', fontSize: '12px', color: data?.form ? '#16a34a' : '#94a3b8' }}>{data?.form ? 'Attached' : '—'}</td>
                                    <td style={{ padding: '10px 12px', fontSize: '12px', color: '#475569' }}>{data?.evidence.length ?? 0}</td>
                                    <td style={{ padding: '10px 12px', textAlign: 'right' }}>
                                        <button type="button" onClick={() => visit('eval-' + cat.key, project.id, evaluation.id)} style={btn('#fff', TONE, '#ddd6fe')}>
                                            {editable ? 'Rate' : 'View'}
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* All evaluations on this project */}
            {evaluations.length > 1 && (
                <>
                    <div style={{ fontSize: '12px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '8px' }}>All Evaluations on this Project</div>
                    <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px' }}>
                            <tbody>
                                {evaluations.map(e => (
                                    <tr key={e.id} onClick={() => visit('eval-summary', project.id, e.id)}
                                        style={{ borderTop: '1px solid #f1f5f9', cursor: 'pointer', background: e.id === evaluation.id ? '#faf5ff' : '#fff' }}>
                                        <td style={{ padding: '9px 12px', fontWeight: 700, color: TONE }}>{e.control_no}</td>
                                        <td style={{ padding: '9px 12px' }}>{e.period_label}</td>
                                        <td style={{ padding: '9px 12px' }}>{e.contractor_name}</td>
                                        <td style={{ padding: '9px 12px' }}><StatusPill row={e} /></td>
                                        <td style={{ padding: '9px 12px', fontWeight: 700 }}>{fmt(e.total_score)}</td>
                                        <td style={{ padding: '9px 12px' }}><ClassPill label={e.classification?.label} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </>
    );
}

// ── One category's form ────────────────────────────────────────────────────

function CategoryForm({ project, evaluation, category, definition, canEdit }: {
    project: HubProject;
    evaluation: EvaluationRow;
    category: CategoryDef;
    definition: EvaluationDefinition;
    canEdit: boolean;
}) {
    const data = evaluation.categories[category.key];
    const editable = canEdit && evaluation.status === 'draft';

    const [ratings, setRatings] = useState<Record<string, number>>({ ...(data?.ratings ?? {}) });
    const [comments, setComments] = useState(data?.comments ?? '');
    const [corrective, setCorrective] = useState(data?.corrective_actions ?? '');
    const [removed, setRemoved] = useState<number[]>([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const formRef = useRef<HTMLInputElement>(null);
    const evidenceRef = useRef<HTMLInputElement>(null);

    const rated = category.criteria.filter(c => ratings[c.key]).length;
    const score = useMemo(() => {
        if (rated < category.criteria.length) return null;
        return category.criteria.reduce((sum, c) => sum + (ratings[c.key] / 5) * c.weight, 0);
    }, [ratings, rated, category]);

    const idx = definition.categories.findIndex(c => c.key === category.key);
    const prev = definition.categories[idx - 1];
    const next = definition.categories[idx + 1];

    const save = () => {
        setSaving(true);
        setError('');
        const fd = new FormData();
        Object.entries(ratings).forEach(([k, v]) => fd.append(`ratings[${k}]`, String(v)));
        fd.append('comments', comments);
        fd.append('corrective_actions', corrective);
        const form = formRef.current?.files?.[0];
        if (form) fd.append('form', form);
        Array.from(evidenceRef.current?.files ?? []).forEach(f => fd.append('evidence[]', f));
        removed.forEach(id => fd.append('removed_attachments[]', String(id)));
        router.post(route('hub.evaluations.category', [project.id, evaluation.id, category.key]), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setRemoved([]);
                if (formRef.current) formRef.current.value = '';
                if (evidenceRef.current) evidenceRef.current.value = '';
            },
            onError: e => setError(Object.values(e).join('\n') || 'Could not save.'),
            onFinish: () => setSaving(false),
        });
    };

    const fileLink = (f: EvalFile) => (
        <div key={f.id} style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 10px', border: '1px solid #e2e8f0', borderRadius: '7px', background: removed.includes(f.id) ? '#fef2f2' : '#fff' }}>
            <a href={f.url} target="_blank" rel="noreferrer" style={{ flex: 1, fontSize: '12.5px', color: TONE, fontWeight: 600, textDecoration: removed.includes(f.id) ? 'line-through' : 'none', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{f.filename}</a>
            {editable && (
                <button type="button" onClick={() => setRemoved(r => r.includes(f.id) ? r.filter(x => x !== f.id) : [...r, f.id])}
                    style={{ border: 'none', background: 'none', color: removed.includes(f.id) ? '#475569' : '#dc2626', fontSize: '11.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {removed.includes(f.id) ? 'Undo' : 'Remove'}
                </button>
            )}
        </div>
    );

    return (
        <>
            <div style={{ marginBottom: '14px' }}>
                <div style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{category.letter}. {category.title} — {category.weight}%</div>
                <div style={{ fontSize: '12.5px', color: '#475569', marginTop: '4px', lineHeight: 1.5 }}>{category.purpose}</div>
            </div>

            {/* Rating guide */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '6px', marginBottom: '16px' }}>
                {definition.ratings.map(r => (
                    <div key={r.value} style={{ padding: '7px 8px', border: '1px solid #e2e8f0', borderRadius: '7px', background: '#f8fafc', textAlign: 'center' }}>
                        <div style={{ fontSize: '12px', fontWeight: 800, color: '#0f172a' }}>{r.value} – {r.label}</div>
                        <div style={{ fontSize: '10.5px', color: '#64748b' }}>{r.description}</div>
                    </div>
                ))}
            </div>

            {/* Criteria */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', marginBottom: '16px' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f1f5f9' }}>
                            {['Evaluation Criteria', 'Weight', 'Rating (1–5)', 'Weighted Score'].map(h => (
                                <th key={h} style={{ padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {category.criteria.map((c, i) => {
                            const value = ratings[c.key];
                            return (
                                <tr key={c.key} style={{ borderTop: '1px solid #f1f5f9', verticalAlign: 'top' }}>
                                    <td style={{ padding: '10px 12px' }}>
                                        <div style={{ fontWeight: 700, color: '#0f172a' }}>{i + 1}. {c.label}</div>
                                        <div style={{ fontSize: '11.5px', color: '#64748b', marginTop: '2px', lineHeight: 1.45 }}>{c.basis}</div>
                                    </td>
                                    <td style={{ padding: '10px 12px', color: '#475569' }}>{c.weight.toFixed(2)}</td>
                                    <td style={{ padding: '10px 12px', whiteSpace: 'nowrap' }}>
                                        <div role="radiogroup" aria-label={`${c.label} rating`} style={{ display: 'inline-flex', gap: '4px' }}>
                                            {[1, 2, 3, 4, 5].map(n => {
                                                const on = value === n;
                                                return (
                                                    <button key={n} type="button" role="radio" aria-checked={on} disabled={!editable}
                                                        title={definition.ratings.find(r => r.value === n)?.label}
                                                        onClick={() => setRatings(r => ({ ...r, [c.key]: n }))}
                                                        style={{
                                                            width: '30px', height: '30px', borderRadius: '7px', fontWeight: 800, fontSize: '12.5px',
                                                            border: `1.5px solid ${on ? TONE : '#e2e8f0'}`,
                                                            background: on ? TONE : '#fff', color: on ? '#fff' : '#475569',
                                                            cursor: editable ? 'pointer' : 'default', fontFamily: 'inherit',
                                                            opacity: !editable && !on ? 0.5 : 1,
                                                        }}>{n}</button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td style={{ padding: '10px 12px', fontWeight: 700, color: '#0f172a' }}>{value ? ((value / 5) * c.weight).toFixed(2) : '—'}</td>
                                </tr>
                            );
                        })}
                        <tr style={{ borderTop: '2px solid #e2e8f0', background: '#faf5ff' }}>
                            <td style={{ padding: '10px 12px', fontWeight: 800, color: '#0f172a' }}>Total Category Score</td>
                            <td style={{ padding: '10px 12px', fontWeight: 700 }}>{category.weight.toFixed(2)}</td>
                            <td style={{ padding: '10px 12px', fontSize: '12px', color: '#64748b' }}>{rated} of {category.criteria.length} rated</td>
                            <td style={{ padding: '10px 12px', fontWeight: 900, color: TONE }}>{score === null ? '—' : score.toFixed(2)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '16px' }}>
                <Field label="Evaluation Comments / Observations">
                    <textarea disabled={!editable} style={{ ...inputStyle, minHeight: '90px', resize: 'vertical' }} value={comments} onChange={e => setComments(e.target.value)} />
                </Field>
                <Field label="Required Corrective Actions (if any)">
                    <textarea disabled={!editable} style={{ ...inputStyle, minHeight: '90px', resize: 'vertical' }} value={corrective} onChange={e => setCorrective(e.target.value)} />
                </Field>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <div>
                    <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '6px' }}>Evaluation Form (signed copy, optional)</div>
                    <div style={{ display: 'grid', gap: '6px' }}>
                        {data?.form ? <>{fileLink(data.form)}<FileHistory versions={data.form.versions} tone={TONE} /></> : <div style={{ fontSize: '12px', color: '#94a3b8' }}>No form attached.</div>}
                        {editable && <input type="file" ref={formRef} style={inputStyle} />}
                    </div>
                </div>
                <div>
                    <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '6px' }}>Supporting Evidence (required to submit)</div>
                    <div style={{ display: 'grid', gap: '6px' }}>
                        {(data?.evidence ?? []).length > 0 ? data!.evidence.map(fileLink) : <div style={{ fontSize: '12px', color: '#94a3b8' }}>No evidence attached.</div>}
                        {editable && <input type="file" multiple ref={evidenceRef} style={inputStyle} />}
                    </div>
                </div>
            </div>
            <div style={{ fontSize: '11.5px', color: '#64748b', marginBottom: '16px', lineHeight: 1.5 }}><strong>Note:</strong> {category.note}</div>

            {error && <ErrorBox message={error} />}

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap', borderTop: '1px solid #e2e8f0', paddingTop: '14px' }}>
                {prev && <button type="button" onClick={() => visit('eval-' + prev.key, project.id, evaluation.id)} style={btn('#fff', '#475569', '#cbd5e1')}>← {prev.letter}. {prev.title}</button>}
                <div style={{ flex: 1 }} />
                <button type="button" onClick={() => visit('eval-summary', project.id, evaluation.id)} style={btn('#fff', TONE, '#ddd6fe')}>Summary</button>
                {editable && <button type="button" onClick={save} disabled={saving} style={btn(TONE, '#fff')}>{saving ? 'Saving…' : 'Save'}</button>}
                {next && <button type="button" onClick={() => visit('eval-' + next.key, project.id, evaluation.id)} style={btn('#fff', '#475569', '#cbd5e1')}>{next.letter}. {next.title} →</button>}
            </div>
            {editable && <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '8px', textAlign: 'right' }}>Save before moving to another category — unsaved ratings are not kept.</div>}
        </>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────

/**
 * Contractor Performance Evaluation in the Operations Hub (group G). With no
 * category it shows the evaluation summary; with one, that category's form.
 */
export default function ContractorEvaluationHub({ project, category, evaluations, definition, contractors, canEdit }: {
    project: HubProject;
    /** A category key (schedule, quality…), or null for the summary. */
    category: string | null;
    evaluations: EvaluationRow[];
    definition: EvaluationDefinition;
    contractors: string[];
    canEdit: boolean;
}) {
    const wanted = selectedIdFromUrl();
    const evaluation = evaluations.find(e => e.id === wanted) ?? evaluations[0] ?? null;
    const categoryDef = category ? definition.categories.find(c => c.key === category) : undefined;
    const section = category ? 'eval-' + category : 'eval-summary';

    const [headerModal, setHeaderModal] = useState<'new' | 'edit' | null>(null);
    const [signMode, setSignMode] = useState<'approve' | 'return' | null>(null);

    return (
        <HubShell>
            {headerModal && (
                <HeaderModal project={project} definition={definition} contractors={contractors}
                    evaluation={headerModal === 'edit' ? evaluation : null} onClose={() => setHeaderModal(null)} />
            )}
            {signMode && evaluation && <SignModal evaluation={evaluation} mode={signMode} onClose={() => setSignMode(null)} />}

            {/* Evaluation picker */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '18px', padding: '10px 12px', border: '1px solid #e2e8f0', borderRadius: '10px', background: '#f8fafc' }}>
                <span style={{ fontSize: '11px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Evaluation</span>
                {evaluations.length > 0 ? (
                    <select value={evaluation?.id ?? ''} onChange={e => visit(section, project.id, Number(e.target.value))}
                        style={{ ...inputStyle, width: 'auto', minWidth: '320px', flex: '0 1 auto' }}>
                        {evaluations.map(e => (
                            <option key={e.id} value={e.id}>{e.control_no} — {e.period_label} — {e.contractor_name} ({e.status_label})</option>
                        ))}
                    </select>
                ) : <span style={{ fontSize: '12.5px', color: '#94a3b8' }}>None yet</span>}
                {evaluation && <StatusPill row={evaluation} />}
                <div style={{ flex: 1 }} />
                {canEdit && <button type="button" onClick={() => setHeaderModal('new')} style={btn(TONE, '#fff')}>+ New Evaluation</button>}
            </div>

            {!evaluation ? (
                <div style={{ padding: '48px 24px', border: '1px dashed #e2e8f0', borderRadius: '10px', textAlign: 'center' }}>
                    <div style={{ fontSize: '14px', fontWeight: 700, color: '#334155', marginBottom: '6px' }}>No contractor evaluations yet</div>
                    <div style={{ fontSize: '12.5px', color: '#94a3b8', maxWidth: '460px', margin: '0 auto' }}>
                        Evaluate the contractor for a period — monthly, quarterly or upon completion — across the eight categories of the PMC Contractor Performance Evaluation Framework.
                    </div>
                    {canEdit && <button type="button" onClick={() => setHeaderModal('new')} style={{ ...btn(TONE, '#fff'), marginTop: '16px' }}>+ New Evaluation</button>}
                </div>
            ) : categoryDef ? (
                <CategoryForm key={`${evaluation.id}-${categoryDef.key}`} project={project} evaluation={evaluation} category={categoryDef} definition={definition} canEdit={canEdit} />
            ) : (
                <SummaryView project={project} evaluation={evaluation} evaluations={evaluations} definition={definition}
                    canEdit={canEdit} onEdit={() => setHeaderModal('edit')} onSign={setSignMode} />
            )}
        </HubShell>
    );
}
