import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Field, Modal, downloadCsv, inputStyle } from '@/pages/project-management/hub/Common';
import {
    ChecklistEntry, ChecklistItem, CompletionBar, IssueEntry,
    ReadOnlyValue, ReportFormBody, ReportViewBody, answerable,
} from '@/pages/project-management/hub/PsrDocument';
import { useConfirm } from '@/components/useConfirm';

// ── Types ──────────────────────────────────────────────────────────────────
interface NtpOption { id: number; ntp_no: string; contractor: string | null }

interface ProjectOption {
    id: number;
    project_no: string;
    title: string;
    is_sub: boolean;
    status: string;
    completion_percent: number;
    ntps: NtpOption[];
}

interface ReportRow {
    id: number;
    project_id: number;
    project_no: string | null;
    project_title: string | null;
    week_code: string;
    completion_pct: number;
    identified_issues: string | null;
    progress_updates: string | null;
    checklist: ChecklistEntry[];
    issues: IssueEntry[];
    submitted_date: string;
    submitted_by: string | null;
    filename: string | null;
    url: string | null;
    ntp_id: number | null;
    ntp_no: string | null;
    ntp_contractor: string | null;
    /** Governs both the Edit and Delete row actions. */
    can_manage: boolean;
}

interface Props {
    projects: ProjectOption[];
    reports: ReportRow[];
    checklist: ChecklistItem[];
    issue_rows: number;
    statuses: string[];
    suggested_week_code: string;
}

// ── Small shared styles ────────────────────────────────────────────────────
const card: React.CSSProperties = {
    background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden',
};

const modalCloseBtn: React.CSSProperties = {
    padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb',
    background: '#fff', fontSize: '12.5px', cursor: 'pointer',
};

const modalPrimaryBtn: React.CSSProperties = {
    padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a',
    color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer',
};

function ErrorNote({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>
            {children}
        </div>
    );
}

function ProjectTag({ no, isSub }: { no: string | null; isSub?: boolean }) {
    if (!no) return <span style={{ color: '#cbd5e1' }}>—</span>;
    return (
        <span style={{
            fontSize: '11px', fontWeight: 700, whiteSpace: 'nowrap',
            color: isSub ? '#4338ca' : '#0f172a',
            background: isSub ? '#eef2ff' : '#f1f5f9',
            border: `1px solid ${isSub ? '#c7d2fe' : '#e2e8f0'}`,
            borderRadius: '5px', padding: '2px 7px',
        }}>
            {no}
        </span>
    );
}

// ── File or revise one report ──────────────────────────────────────────────
// One component for both, so a revision is filled in exactly where it was
// filed. Passing a `report` switches it into edit mode: the project it belongs
// to becomes fixed (moving a report between projects would strand both
// projects' completion figures) and the existing attachment can be replaced or
// removed — the one thing a bulk upload can't carry.
function ReportModal({ projects, report, checklist, issueRowCount, statuses, defaultWeekCode, onClose }: {
    projects: ProjectOption[];
    report: ReportRow | null;
    checklist: ChecklistItem[];
    issueRowCount: number;
    statuses: string[];
    defaultWeekCode: string;
    onClose: () => void;
}) {
    const isEdit = !!report;

    const [projectId, setProjectId] = useState(report ? String(report.project_id) : '');
    const [weekCode, setWeekCode]   = useState(report?.week_code ?? defaultWeekCode);
    const [pct, setPct]             = useState(report ? String(report.completion_pct) : '');
    const [updates, setUpdates]     = useState(report?.progress_updates ?? '');
    const [ntpId, setNtpId]         = useState(report?.ntp_id ? String(report.ntp_id) : '');
    const [file, setFile]           = useState<File | null>(null);
    const [removeFile, setRemoveFile] = useState(false);
    const [answers, setAnswers]     = useState<Record<string, ChecklistEntry>>(
        () => Object.fromEntries((report?.checklist ?? []).map(c => [c.seq, c]))
    );
    const [issueRows, setIssueRows] = useState<IssueEntry[]>(() => {
        const saved = report?.issues ?? [];
        // Always show at least the form's row count, but never hide a saved row.
        return Array.from({ length: Math.max(issueRowCount, saved.length) },
            (_, i) => saved[i] ?? { issue: null, action: null, commitment_date: null });
    });
    const [saving, setSaving]       = useState(false);
    const [error, setError]         = useState('');

    const project = projects.find(p => String(p.id) === projectId);

    const patchAnswer = (seq: string, patch: Partial<ChecklistEntry>) =>
        setAnswers(prev => {
            const current: ChecklistEntry = prev[seq] ?? { seq, status: null, remarks: null };
            return { ...prev, [seq]: { ...current, ...patch } };
        });

    const patchIssue = (index: number, patch: Partial<IssueEntry>) =>
        setIssueRows(prev => prev.map((row, i) => i === index ? { ...row, ...patch } : row));

    const handleSubmit = () => {
        if (!projectId) { setError('Pick the project this report is for.'); return; }
        if (!weekCode.trim()) { setError('Week Code is required (e.g. W1-OCT).'); return; }
        if (!pct || Number(pct) < 0 || Number(pct) > 100) { setError('Completion % must be between 0 and 100.'); return; }
        setError('');
        setSaving(true);

        // Only send rows that were actually answered. Empty strings rather than
        // nulls so the payload survives FormData when a PDF is attached.
        const filledChecklist = answerable(checklist)
            .map(c => answers[c.seq])
            .filter(c => c && (c.status || c.remarks?.trim()))
            .map(c => ({ seq: c.seq, status: c.status ?? '', remarks: c.remarks?.trim() ?? '' }));

        const filledIssues = issueRows
            .filter(r => r.issue?.trim() || r.action?.trim() || r.commitment_date)
            .map(r => ({ issue: r.issue?.trim() ?? '', action: r.action?.trim() ?? '', commitment_date: r.commitment_date ?? '' }));

        const payload = {
            project_ntp_id: ntpId || null,
            week_code: weekCode,
            completion_pct: pct,
            progress_updates: updates,
            checklist: filledChecklist,
            issues: filledIssues,
            file,
        };

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        };

        if (isEdit) {
            // FormData can't be sent over a real PUT, so spoof the method.
            router.post(route('weekly-status.update', report!.id), {
                ...payload,
                _method: 'put',
                remove_file: removeFile,
            }, options);
        } else {
            router.post(route('weekly-status.store'), { ...payload, project_id: projectId }, options);
        }
    };

    // Once a replacement is chosen the removal flag is moot.
    const chooseFile = (picked: File | null) => {
        setFile(picked);
        if (picked) setRemoveFile(false);
    };

    return (
        <Modal
            title={`${weekCode || 'New'} | Weekly Status ${isEdit ? 'Revision' : 'Submission'}`}
            onClose={onClose}
            size="960px"
            footer={<>
                {error && <ErrorNote>{error}</ErrorNote>}
                <button type="button" onClick={onClose} style={modalCloseBtn}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={modalPrimaryBtn}>
                    {saving
                        ? (isEdit ? 'Saving...' : 'Submitting...')
                        : (isEdit ? 'Save Changes' : 'Submit Weekly Status')}
                </button>
            </>}
        >
            {/* Project first — everything below is filed against it. */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '18px' }}>
                <Field label="Project">
                    {isEdit ? (
                        <ReadOnlyValue>{report!.project_no} — {report!.project_title}</ReadOnlyValue>
                    ) : (
                        <select
                            style={inputStyle}
                            value={projectId}
                            onChange={e => { setProjectId(e.target.value); setNtpId(''); }}
                        >
                            <option value="">— Select a project —</option>
                            {projects.map(p => (
                                <option key={p.id} value={String(p.id)}>
                                    {p.project_no}{p.is_sub ? ' (sub)' : ''} — {p.title}
                                </option>
                            ))}
                        </select>
                    )}
                </Field>
                <Field label="Notice to Proceed / Contractor (optional)">
                    <select style={inputStyle} value={ntpId} onChange={e => setNtpId(e.target.value)} disabled={!project?.ntps.length}>
                        <option value="">
                            {project?.ntps.length ? '— Whole project (no specific NTP) —' : 'No NTPs on this project'}
                        </option>
                        {project?.ntps.map(n => (
                            <option key={n.id} value={String(n.id)}>{n.ntp_no}{n.contractor ? ` — ${n.contractor}` : ''}</option>
                        ))}
                    </select>
                </Field>
            </div>

            <ReportFormBody
                checklist={checklist}
                statuses={statuses}
                answers={answers}
                onAnswer={patchAnswer}
                issueRows={issueRows}
                onIssue={patchIssue}
                updates={updates}
                onUpdates={setUpdates}
            />

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px' }}>
                <Field label="Week Code (e.g. W1-OCT)">
                    <input style={inputStyle} placeholder="e.g. W1-OCT" value={weekCode} onChange={e => setWeekCode(e.target.value)} />
                </Field>
                <Field label="Overall Project % Completion">
                    <input type="number" style={inputStyle} placeholder="e.g. 75" value={pct} onChange={e => setPct(e.target.value)} />
                </Field>
                <Field label={isEdit && report!.filename ? 'Replace Supporting PDF' : 'Attach Supporting PDF (e.g. signed copy)'}>
                    <input type="file" accept=".pdf" onChange={e => chooseFile(e.target.files?.[0] ?? null)} style={inputStyle} />
                </Field>
            </div>

            {/* What's attached today, and how to drop it. */}
            {isEdit && report!.filename && (
                <div style={{
                    marginTop: '10px', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap',
                    padding: '9px 12px', borderRadius: '8px', background: '#f8fafc', border: '1px solid #e2e8f0',
                }}>
                    <span style={{ fontSize: '11px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Currently attached</span>
                    <a
                        href={report!.url ?? '#'}
                        target="_blank"
                        rel="noreferrer"
                        style={{
                            fontSize: '12.5px', fontWeight: 600, color: '#2563eb', textDecoration: 'none',
                            textDecorationLine: removeFile ? 'line-through' : 'none', opacity: removeFile ? 0.5 : 1,
                        }}
                    >
                        {report!.filename}
                    </a>
                    <label style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 600, color: file ? '#94a3b8' : '#b91c1c', cursor: file ? 'not-allowed' : 'pointer' }}>
                        <input
                            type="checkbox"
                            checked={removeFile}
                            disabled={!!file}
                            onChange={e => setRemoveFile(e.target.checked)}
                            style={{ cursor: file ? 'not-allowed' : 'pointer', accentColor: '#dc2626' }}
                        />
                        Remove attachment
                    </label>
                </div>
            )}
            {isEdit && file && (
                <div style={{ marginTop: '8px', fontSize: '11.5px', color: '#64748b' }}>
                    {report!.filename ? `“${file.name}” will replace the current attachment.` : `“${file.name}” will be attached.`}
                </div>
            )}
        </Modal>
    );
}

// ── Read a filed report back ───────────────────────────────────────────────
function ViewModal({ report, checklist, issueRowCount, onEdit, onClose }: {
    report: ReportRow;
    checklist: ChecklistItem[];
    issueRowCount: number;
    onEdit?: () => void;
    onClose: () => void;
}) {
    return (
        <Modal title={`${report.project_no ?? ''} · ${report.week_code} | Weekly Status`} onClose={onClose} size="960px"
            footer={<>
                <button type="button" onClick={onClose} style={modalCloseBtn}>Close</button>
                {onEdit && <button type="button" onClick={onEdit} style={modalPrimaryBtn}>Edit</button>}
            </>}
        >
            <ReportViewBody report={report} checklist={checklist} issueRowCount={issueRowCount} />

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                <Field label="Project">
                    <ReadOnlyValue>{report.project_no} — {report.project_title}</ReadOnlyValue>
                </Field>
                <Field label="Notice to Proceed / Contractor">
                    <ReadOnlyValue muted={!report.ntp_no}>
                        {report.ntp_no
                            ? `${report.ntp_no}${report.ntp_contractor ? ` — ${report.ntp_contractor}` : ''}`
                            : 'Whole project'}
                    </ReadOnlyValue>
                </Field>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '14px' }}>
                <Field label="Week Code">
                    <ReadOnlyValue><strong style={{ color: '#2563eb' }}>{report.week_code}</strong></ReadOnlyValue>
                </Field>
                <Field label="Overall Project % Completion">
                    <ReadOnlyValue><CompletionBar value={report.completion_pct} /></ReadOnlyValue>
                </Field>
                <Field label="Submitted">
                    <ReadOnlyValue>{report.submitted_date}{report.submitted_by ? ` · ${report.submitted_by}` : ''}</ReadOnlyValue>
                </Field>
                <Field label="Supporting PDF">
                    <ReadOnlyValue muted={!report.url}>
                        {report.url
                            ? <a href={report.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontWeight: 600, textDecoration: 'none', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{report.filename}</a>
                            : 'No attachment'}
                    </ReadOnlyValue>
                </Field>
            </div>
        </Modal>
    );
}

// ── Bulk upload across projects ────────────────────────────────────────────
function BulkUploadModal({ projects, checklist, issueRowCount, statuses, defaultWeekCode, onClose }: {
    projects: ProjectOption[];
    checklist: ChecklistItem[];
    issueRowCount: number;
    statuses: string[];
    defaultWeekCode: string;
    onClose: () => void;
}) {
    const [file, setFile]     = useState<File | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const handleSubmit = () => {
        if (!file) { setError('Please choose a file first.'); return; }
        setError('');
        setSaving(true);
        router.post(route('weekly-status.import'), { file }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    // Sample rows use the engineer's real project numbers, so the basic template
    // is fillable straight away rather than needing them looked up first.
    const sampleNos = projects.slice(0, 2).map(p => p.project_no);
    const downloadBasicTemplate = () => downloadCsv('weekly-status-template.csv', [
        ['project_no', 'week_code', 'completion_pct', 'identified_issues', 'progress_updates', 'submitted_date', 'ntp_no'],
        [sampleNos[0] ?? 'PRJ-2026-0001', defaultWeekCode, '25', 'Delayed delivery of materials', 'Foundation works started', '', ''],
        [sampleNos[1] ?? sampleNos[0] ?? 'PRJ-2026-0002', defaultWeekCode, '40', '', 'Column rebars installed', '', ''],
    ]);

    const linkBtn: React.CSSProperties = {
        background: 'transparent', border: 'none', color: '#2563eb',
        fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', padding: 0, textAlign: 'left',
        textDecoration: 'none', display: 'inline-block',
    };

    return (
        <Modal title="Bulk Upload Weekly Status" onClose={onClose} size="640px"
            footer={<>
                {error && <ErrorNote>{error}</ErrorNote>}
                <button type="button" onClick={onClose} style={modalCloseBtn}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={modalPrimaryBtn}>
                    {saving ? 'Uploading...' : 'Upload Weekly Status'}
                </button>
            </>}
        >
            <div style={{ fontSize: '13px', color: '#475569', marginBottom: '14px', lineHeight: 1.5 }}>
                One file, one week, all the projects you handle — a row per report. Unlike the
                per-project upload in a project's operations hub, both templates below lead with a
                {' '}<strong>project_no</strong> column, so main projects, sub-projects and NTPs
                can be mixed freely in the same sheet. The header row is matched case-insensitively
                and unknown columns are ignored, so either template works. Only
                {' '}<strong>project_no</strong> and <strong>week_code</strong> are required.
            </div>

            <div style={{ display: 'grid', gap: '10px', marginBottom: '16px' }}>
                {/* Basic */}
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '10px', padding: '12px 14px' }}>
                    <div style={{ fontSize: '12.5px', fontWeight: 800, color: '#0f172a', marginBottom: '4px' }}>Basic — headline figures only (.csv)</div>
                    <ul style={{ fontSize: '12px', color: '#475569', margin: '0 0 8px', paddingLeft: '18px', lineHeight: 1.6 }}>
                        <li><strong>project_no</strong> — one of your project numbers (required)</li>
                        <li><strong>week_code</strong> — e.g. W1-OCT (required)</li>
                        <li><strong>completion_pct</strong> — 0–100</li>
                        <li><strong>identified_issues</strong>, <strong>progress_updates</strong> — free text</li>
                        <li><strong>submitted_date</strong> — e.g. 2026-10-07 (defaults to today)</li>
                        <li><strong>ntp_no</strong> — matches an NTP on that same project (optional)</li>
                    </ul>
                    <button type="button" onClick={downloadBasicTemplate} style={linkBtn}>↓ Download basic template</button>
                </div>

                {/* Detailed */}
                <div style={{ border: '1px solid #c7d2fe', background: '#f8faff', borderRadius: '10px', padding: '12px 14px' }}>
                    <div style={{ fontSize: '12.5px', fontWeight: 800, color: '#0f172a', marginBottom: '4px' }}>Detailed — everything the submission form captures (.xlsx)</div>
                    <div style={{ fontSize: '12px', color: '#475569', marginBottom: '6px', lineHeight: 1.5 }}>
                        All basic columns, plus the site checklist and the weekly issues / action plan:
                    </div>
                    <ul style={{ fontSize: '12px', color: '#475569', margin: '0 0 8px', paddingLeft: '18px', lineHeight: 1.6 }}>
                        <li>
                            <strong>chk_1_1_status</strong>, <strong>chk_1_1_remarks</strong> … one pair per
                            checklist item ({answerable(checklist).length} items). Each status cell is a
                            dropdown — pick {statuses.join(' / ')} or leave it blank.
                        </li>
                        <li>
                            <strong>issue_1</strong>, <strong>action_1</strong>, <strong>commitment_date_1</strong> …
                            up to {issueRowCount} rows. <strong>issue_1</strong> also fills Identified Issues.
                        </li>
                        <li>
                            A <strong>My Projects</strong> sheet backs <strong>project_no</strong> with a dropdown of
                            your {projects.length} project{projects.length === 1 ? '' : 's'} and lists each one's NTP
                            numbers; a <strong>Checklist Guide</strong> sheet says which item each
                            {' '}<strong>chk_*</strong> column belongs to.
                        </li>
                    </ul>
                    <a href={route('weekly-status.template')} style={linkBtn}>↓ Download detailed template (.xlsx)</a>
                </div>
            </div>

            <div style={{ fontSize: '11.5px', color: '#64748b', marginBottom: '12px', lineHeight: 1.5 }}>
                Blank checklist items and empty issue rows are skipped, so partially filled rows are fine.
                Rows naming a project you don't handle are reported back rather than filed.
                Attachments are the one thing a spreadsheet can't carry — add those per report.
            </div>

            <input
                type="file"
                accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                onChange={e => setFile(e.target.files?.[0] ?? null)}
                style={{ ...inputStyle, padding: '8px' }}
            />
        </Modal>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────
export default function WeeklyStatusIndex({
    projects, reports, checklist, issue_rows: issueRows, statuses, suggested_week_code: suggestedWeekCode,
}: Props) {
    const [showSubmit, setShowSubmit] = useState(false);
    const [showUpload, setShowUpload] = useState(false);
    const [viewing, setViewing]       = useState<ReportRow | null>(null);
    const [editing, setEditing]       = useState<ReportRow | null>(null);
    const [weekFilter, setWeekFilter]     = useState('');
    const [projectFilter, setProjectFilter] = useState('');

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const weekOptions = useMemo(
        () => Array.from(new Set(reports.map(r => r.week_code))).sort(),
        [reports],
    );

    const filtered = reports.filter(r =>
        (!weekFilter || r.week_code === weekFilter) &&
        (!projectFilter || String(r.project_id) === projectFilter));

    // Projects with nothing filed for the week being looked at — the reason to
    // open this page at all.
    const outstanding = useMemo(() => {
        if (!weekFilter) return [];
        const filed = new Set(reports.filter(r => r.week_code === weekFilter).map(r => r.project_id));
        return projects.filter(p => !filed.has(p.id));
    }, [weekFilter, reports, projects]);

    const handleDelete = (r: ReportRow) => {
        showConfirm(`Delete ${r.week_code} for ${r.project_no}?`, () => {
            router.delete(route('weekly-status.destroy', r.id), { preserveScroll: true });
        }, { title: 'Delete Weekly Status', confirmLabel: 'Delete', variant: 'danger' });
    };

    const th: React.CSSProperties = {
        padding: '10px 16px', textAlign: 'left', fontSize: '10.5px', fontWeight: 700,
        color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px',
        borderBottom: '1px solid #f3f4f6', whiteSpace: 'nowrap',
    };
    const td: React.CSSProperties = { padding: '12px 16px', fontSize: '12.5px', color: '#334155' };

    const filterSelect = (active: boolean): React.CSSProperties => ({
        padding: '7px 10px', borderRadius: '7px',
        border: `1px solid ${active ? '#2563eb' : '#e5e7eb'}`,
        background: active ? '#eff6ff' : '#fff',
        color: active ? '#2563eb' : '#374151',
        fontSize: '12.5px', fontFamily: 'inherit', cursor: 'pointer', outline: 'none',
    });

    return (
        <AuthenticatedLayout>
            <Head title="Weekly Status" />
            {confirmDialog}

            {/* Keyed so re-opening on a different report reseeds the form. */}
            {(showSubmit || editing) && (
                <ReportModal
                    key={editing?.id ?? 'new'}
                    projects={projects}
                    report={editing}
                    checklist={checklist}
                    issueRowCount={issueRows}
                    statuses={statuses}
                    defaultWeekCode={suggestedWeekCode}
                    onClose={() => { setShowSubmit(false); setEditing(null); }}
                />
            )}
            {showUpload && (
                <BulkUploadModal
                    projects={projects}
                    checklist={checklist}
                    issueRowCount={issueRows}
                    statuses={statuses}
                    defaultWeekCode={suggestedWeekCode}
                    onClose={() => setShowUpload(false)}
                />
            )}
            {viewing && !editing && (
                <ViewModal
                    report={viewing}
                    checklist={checklist}
                    issueRowCount={issueRows}
                    onEdit={viewing.can_manage ? () => { setEditing(viewing); setViewing(null); } : undefined}
                    onClose={() => setViewing(null)}
                />
            )}

            {/* Page title */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px', gap: '12px', flexWrap: 'wrap' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                        Weekly Status
                    </h1>
                    <p style={{ margin: '4px 0 0', fontSize: '12.5px', color: '#64748b' }}>
                        File this week's progress across the {projects.length} project{projects.length === 1 ? '' : 's'} you
                        handle. Reports land in each project's operations hub.
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    <button
                        type="button"
                        onClick={() => setShowUpload(true)}
                        disabled={projects.length === 0}
                        style={{
                            display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '8px 14px',
                            borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff',
                            fontSize: '12.5px', fontWeight: 600, color: '#374151',
                            cursor: projects.length === 0 ? 'not-allowed' : 'pointer',
                            opacity: projects.length === 0 ? 0.5 : 1,
                        }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Bulk Upload
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowSubmit(true)}
                        disabled={projects.length === 0}
                        style={{
                            display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '8px 14px',
                            borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff',
                            fontSize: '12.5px', fontWeight: 600,
                            cursor: projects.length === 0 ? 'not-allowed' : 'pointer',
                            opacity: projects.length === 0 ? 0.5 : 1,
                        }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Submit Weekly Status
                    </button>
                </div>
            </div>

            {projects.length === 0 && (
                <div style={{ ...card, padding: '32px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
                    You aren't handling any projects yet, so there's nothing to report on.
                </div>
            )}

            {projects.length > 0 && (
                <div style={card}>
                    {/* Toolbar */}
                    <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                        <select value={weekFilter} onChange={e => setWeekFilter(e.target.value)} style={filterSelect(!!weekFilter)} title="Filter by week">
                            <option value="">All Weeks</option>
                            {weekOptions.map(w => <option key={w} value={w}>{w}</option>)}
                        </select>
                        <select value={projectFilter} onChange={e => setProjectFilter(e.target.value)} style={filterSelect(!!projectFilter)} title="Filter by project">
                            <option value="">All Projects</option>
                            {projects.map(p => (
                                <option key={p.id} value={String(p.id)}>{p.project_no} — {p.title}</option>
                            ))}
                        </select>
                        {(weekFilter || projectFilter) && (
                            <button
                                type="button"
                                onClick={() => { setWeekFilter(''); setProjectFilter(''); }}
                                style={{ padding: '7px 12px', borderRadius: '7px', border: '1px solid #fca5a5', background: '#fff7f7', fontSize: '12px', fontWeight: 500, color: '#dc2626', cursor: 'pointer' }}
                            >
                                Clear filters
                            </button>
                        )}
                        <span style={{ marginLeft: 'auto', fontSize: '12.5px', color: '#9ca3af' }}>
                            Showing <strong style={{ color: '#374151' }}>{filtered.length}</strong> of {reports.length}
                        </span>
                    </div>

                    {/* Which projects still owe a report for the selected week. */}
                    {weekFilter && outstanding.length > 0 && (
                        <div style={{ padding: '11px 18px', background: '#fffbeb', borderBottom: '1px solid #fef3c7', fontSize: '12.5px', color: '#92400e' }}>
                            <strong>{outstanding.length}</strong> project{outstanding.length === 1 ? '' : 's'} with no {weekFilter} report yet:{' '}
                            {outstanding.map(p => p.project_no).join(', ')}
                        </div>
                    )}

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                            <thead>
                                <tr style={{ background: '#f8fafc' }}>
                                    {['Week#', 'Project', 'Project Title', 'NTP / Contractor', '% Completion', 'Identified Issues', 'Submitted', 'Attachment', 'Actions'].map((h, i, arr) => (
                                        <th key={h} style={{ ...th, textAlign: i === arr.length - 1 ? 'center' : 'left' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} style={{ padding: '44px', textAlign: 'center', fontSize: '13px', color: '#9ca3af' }}>
                                            No weekly status filed yet. Submit one, or bulk-upload a week's worth.
                                        </td>
                                    </tr>
                                ) : filtered.map(r => (
                                    <tr
                                        key={r.id}
                                        style={{ borderBottom: '1px solid #f8fafc' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td style={{ ...td, fontWeight: 700, color: '#2563eb', whiteSpace: 'nowrap' }}>{r.week_code}</td>
                                        <td style={td}>
                                            <ProjectTag no={r.project_no} isSub={projects.find(p => p.id === r.project_id)?.is_sub} />
                                        </td>
                                        <td style={{ ...td, maxWidth: '240px' }}>{r.project_title ?? '—'}</td>
                                        <td style={td}>
                                            {r.ntp_no
                                                ? <span style={{ fontWeight: 600, color: '#1e40af' }}>{r.ntp_no}{r.ntp_contractor ? ` — ${r.ntp_contractor}` : ''}</span>
                                                : <span style={{ color: '#cbd5e1' }}>Whole project</span>}
                                        </td>
                                        <td style={{ ...td, width: '150px' }}><CompletionBar value={r.completion_pct} /></td>
                                        <td style={{ ...td, maxWidth: '220px' }}>{r.identified_issues ?? '—'}</td>
                                        <td style={{ ...td, color: '#94a3b8', fontSize: '12px', whiteSpace: 'nowrap' }}>
                                            <div>{r.submitted_date}</div>
                                            {r.submitted_by && <div style={{ fontSize: '10.5px', marginTop: '2px' }}>{r.submitted_by}</div>}
                                        </td>
                                        <td style={td}>
                                            {r.filename
                                                ? <a href={r.url ?? '#'} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{r.filename}</a>
                                                : <span style={{ color: '#cbd5e1' }}>—</span>}
                                        </td>
                                        <td style={td}>
                                            <div style={{ display: 'flex', gap: '4px', justifyContent: 'center' }}>
                                                <button type="button" title="View" onClick={() => setViewing(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fff', border: '1px solid #e2e8f0', color: '#475569' }}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button type="button" title="Open project hub" onClick={() => router.visit(route('projects.hub.psr', r.project_id))} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#eef2ff', border: '1px solid #c7d2fe', color: '#4338ca' }}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M7 17L17 7M7 7h10v10"/></svg>
                                                </button>
                                                {r.can_manage && (
                                                    <button type="button" title="Edit" onClick={() => setEditing(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#eff6ff', border: '1px solid #bfdbfe', color: '#2563eb' }}>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                )}
                                                {r.can_manage && (
                                                    <button type="button" title="Delete" onClick={() => handleDelete(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626' }}>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
