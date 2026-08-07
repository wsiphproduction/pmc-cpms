import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, DataTable, Field, HubProject, HubShell, Modal, downloadCsv, inputStyle } from './Common';
import {
    ChecklistEntry, ChecklistItem, CompletionBar, IssueEntry,
    ReadOnlyValue, ReportFormBody, ReportViewBody, answerable,
} from './PsrDocument';
import { useConfirm } from '@/components/useConfirm';

interface PsrRow { id: number; week_code: string; completion_pct: number; identified_issues: string | null; progress_updates: string | null; checklist: ChecklistEntry[]; issues: IssueEntry[]; submitted_date: string; filename: string | null; url: string | null; ntp_id: number | null; ntp_no: string | null; ntp_contractor: string | null; sub_project_id: number | null; sub_project_no: string | null }
interface NtpOption { id: number; ntp_no: string; contractor: string }

function ProgressBar({ value }: { value: number }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <strong>{value}%</strong>
            <div style={{ width: '70px', height: '7px', borderRadius: '999px', background: '#e2e8f0' }}>
                <div style={{ width: `${value}%`, height: '100%', borderRadius: '999px', background: '#16a34a' }} />
            </div>
        </div>
    );
}

// ── View Report Modal ──────────────────────────────────────────────────────
function ViewReportModal({ report, checklist, issueRowCount, onClose }: {
    report: PsrRow;
    checklist: ChecklistItem[];
    issueRowCount: number;
    onClose: () => void;
}) {
    return (
        <Modal title={`${report.week_code} | Project Progress Report`} onClose={onClose} size="960px"
            footer={
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
            }
        >
            <ReportViewBody report={report} checklist={checklist} issueRowCount={issueRowCount} />

            {report.ntp_no && (
                <div style={{ marginBottom: '14px' }}>
                    <Field label="Notice to Proceed / Contractor">
                        <ReadOnlyValue>{report.ntp_no}{report.ntp_contractor ? ` — ${report.ntp_contractor}` : ''}</ReadOnlyValue>
                    </Field>
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '14px' }}>
                <Field label="Week Code">
                    <ReadOnlyValue><strong style={{ color: '#2563eb' }}>{report.week_code}</strong></ReadOnlyValue>
                </Field>
                <Field label="Overall Project % Completion">
                    <ReadOnlyValue><CompletionBar value={report.completion_pct} /></ReadOnlyValue>
                </Field>
                <Field label="Submitted Date">
                    <ReadOnlyValue>{report.submitted_date}</ReadOnlyValue>
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

// ── Weekly Report Modal ────────────────────────────────────────────────────
function ReportModal({ project, ntps, checklist, issueRowCount, statuses, onClose }: {
    project: HubProject;
    ntps: NtpOption[];
    checklist: ChecklistItem[];
    issueRowCount: number;
    statuses: string[];
    onClose: () => void;
}) {
    const [weekCode, setWeekCode]   = useState('');
    const [pct, setPct]             = useState('');
    const [updates, setUpdates]     = useState('');
    const [ntpId, setNtpId]         = useState('');
    const [file, setFile]           = useState<File | null>(null);
    const [answers, setAnswers]     = useState<Record<string, ChecklistEntry>>({});
    const [issueRows, setIssueRows] = useState<IssueEntry[]>(
        () => Array.from({ length: issueRowCount }, () => ({ issue: null, action: null, commitment_date: null }))
    );
    const [saving, setSaving]       = useState(false);
    const [error, setError]         = useState('');

    const patchAnswer = (seq: string, patch: Partial<ChecklistEntry>) =>
        setAnswers(prev => {
            const current: ChecklistEntry = prev[seq] ?? { seq, status: null, remarks: null };
            return { ...prev, [seq]: { ...current, ...patch } };
        });

    const patchIssue = (index: number, patch: Partial<IssueEntry>) =>
        setIssueRows(prev => prev.map((row, i) => i === index ? { ...row, ...patch } : row));

    const handleSubmit = () => {
        if (!weekCode.trim()) { setError('Week Code is required (e.g. W1-OCT).'); return; }
        if (!pct || Number(pct) < 0 || Number(pct) > 100) { setError('Completion % must be between 0 and 100.'); return; }
        setError('');
        setSaving(true);

        // Only send rows the submitter actually answered. Empty strings rather
        // than nulls so the payload survives FormData when a PDF is attached.
        const filledChecklist = answerable(checklist)
            .map(c => answers[c.seq])
            .filter(c => c && (c.status || c.remarks?.trim()))
            .map(c => ({ seq: c.seq, status: c.status ?? '', remarks: c.remarks?.trim() ?? '' }));

        const filledIssues = issueRows
            .filter(r => r.issue?.trim() || r.action?.trim() || r.commitment_date)
            .map(r => ({ issue: r.issue?.trim() ?? '', action: r.action?.trim() ?? '', commitment_date: r.commitment_date ?? '' }));

        router.post(route('hub.psr.store', project.id), {
            project_ntp_id: ntpId || null,
            week_code: weekCode,
            completion_pct: pct,
            progress_updates: updates,
            checklist: filledChecklist,
            issues: filledIssues,
            file,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title={`${weekCode || 'New'} | Project Progress Report Submission`} onClose={onClose} size="960px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Submitting...' : 'Submit Weekly Report'}
                </button>
            </>}
        >
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

            {ntps.length > 0 && (
                <div style={{ marginBottom: '14px' }}>
                    <Field label="Notice to Proceed / Contractor (optional)">
                        <select style={inputStyle} value={ntpId} onChange={e => setNtpId(e.target.value)}>
                            <option value="">— Whole project (no specific NTP) —</option>
                            {ntps.map(n => <option key={n.id} value={String(n.id)}>{n.ntp_no} — {n.contractor}</option>)}
                        </select>
                    </Field>
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px' }}>
                <Field label="Week Code (e.g. W1-OCT)">
                    <input style={inputStyle} placeholder="e.g. W1-OCT" value={weekCode} onChange={e => setWeekCode(e.target.value)} />
                </Field>
                <Field label="Overall Project % Completion">
                    <input type="number" style={inputStyle} placeholder="e.g. 75" value={pct} onChange={e => setPct(e.target.value)} />
                </Field>
                <Field label="Attach Supporting PDF (e.g. signed copy)">
                    <input type="file" accept=".pdf" onChange={e => setFile(e.target.files?.[0] ?? null)} style={inputStyle} />
                </Field>
            </div>
        </Modal>
    );
}

// ── CSV Bulk Import Modal ──────────────────────────────────────────────────
function ImportModal({ project, checklist, issueRowCount, statuses, onClose }: {
    project: HubProject;
    checklist: ChecklistItem[];
    issueRowCount: number;
    statuses: string[];
    onClose: () => void;
}) {
    const [file, setFile]     = useState<File | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const handleSubmit = () => {
        if (!file) { setError('Please choose a file first.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.psr.import', project.id), { file }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    const downloadBasicTemplate = () => downloadCsv('psr-template.csv', [
        ['week_code', 'completion_pct', 'identified_issues', 'progress_updates', 'submitted_date', 'ntp_no'],
        ['W1-OCT', '25', 'Delayed delivery of materials', 'Foundation works started', '2026-10-07', ''],
        ['W2-OCT', '40', '', 'Column rebars installed', '2026-10-14', ''],
    ]);

    const linkBtn: React.CSSProperties = {
        background: 'transparent', border: 'none', color: '#2563eb',
        fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', padding: 0, textAlign: 'left',
        textDecoration: 'none', display: 'inline-block',
    };

    return (
        <Modal title="Bulk Upload Weekly Reports" onClose={onClose} size="620px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Importing...' : 'Import Reports'}
                </button>
            </>}
        >
            <div style={{ fontSize: '13px', color: '#475569', marginBottom: '14px', lineHeight: 1.5 }}>
                Upload a <strong>.xlsx</strong> or <strong>.csv</strong> to add many weekly reports
                at once — one report per row. The header row is matched case-insensitively and
                unknown columns are ignored, so either template below works. Only
                {' '}<strong>week_code</strong> is required.
            </div>

            <div style={{ display: 'grid', gap: '10px', marginBottom: '16px' }}>
                {/* Basic */}
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '10px', padding: '12px 14px' }}>
                    <div style={{ fontSize: '12.5px', fontWeight: 800, color: '#0f172a', marginBottom: '4px' }}>Basic — headline figures only (.csv)</div>
                    <ul style={{ fontSize: '12px', color: '#475569', margin: '0 0 8px', paddingLeft: '18px', lineHeight: 1.6 }}>
                        <li><strong>week_code</strong> — e.g. W1-OCT (required)</li>
                        <li><strong>completion_pct</strong> — 0–100</li>
                        <li><strong>identified_issues</strong>, <strong>progress_updates</strong> — free text</li>
                        <li><strong>submitted_date</strong> — e.g. 2026-10-07 (defaults to today)</li>
                        <li><strong>ntp_no</strong> — matches an NTP on this project (optional)</li>
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
                            up to {issueRowCount} rows. <strong>issue_1</strong> also fills Identified Issues in the table.
                        </li>
                        <li>
                            A <strong>Checklist Guide</strong> sheet lists which item each
                            {' '}<strong>chk_*</strong> column belongs to.
                        </li>
                    </ul>
                    <div style={{ fontSize: '11.5px', color: '#64748b', marginBottom: '8px' }}>
                        Blank checklist items and empty issue rows are skipped, so partially filled rows are fine.
                        The attachment is the one thing a spreadsheet can't carry — upload PDFs per report.
                    </div>
                    <a href={route('hub.psr.template', project.id)} style={linkBtn}>↓ Download detailed template (.xlsx)</a>
                </div>
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

// ── Main Component ─────────────────────────────────────────────────────────
export default function PsrHub({ project, reports, ntps = [], checklist = [], issueRows = 3, statuses = [], canEdit = true }: {
    project: HubProject;
    reports: PsrRow[];
    ntps?: NtpOption[];
    checklist?: ChecklistItem[];
    issueRows?: number;
    statuses?: string[];
    canEdit?: boolean;
}) {
    const [showModal, setShowModal]   = useState(false);
    const [showImport, setShowImport] = useState(false);
    const [viewing, setViewing]       = useState<PsrRow | null>(null);
    const [ntpFilter, setNtpFilter]   = useState('');
    // Use the project's effective (blended) completion for the headline.
    const progress = project.completion_percent ?? 0;

    // When a parent project's reports include sub-project rows, show a Project column.
    const hasSubRows = reports.some(r => !!r.sub_project_id);

    const ntpOptions = Array.from(new Set(reports.map(r => r.ntp_no).filter((v): v is string => !!v))).sort();
    const filteredReports = reports.filter(r => !ntpFilter || r.ntp_no === ntpFilter);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (r: PsrRow) => {
        showConfirm(`Delete report ${r.week_code}?`, () => {
            router.delete(route('hub.psr.destroy', [project.id, r.id]), { preserveScroll: true });
        }, { title: 'Delete Report', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            {showModal  && <ReportModal project={project} ntps={ntps} checklist={checklist} issueRowCount={issueRows} statuses={statuses} onClose={() => setShowModal(false)} />}
            {showImport && <ImportModal project={project} checklist={checklist} issueRowCount={issueRows} statuses={statuses} onClose={() => setShowImport(false)} />}
            {viewing    && <ViewReportModal report={viewing} checklist={checklist} issueRowCount={issueRows} onClose={() => setViewing(null)} />}

            {/* Summary card */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '16px', padding: '22px', marginBottom: '22px', display: 'grid', gridTemplateColumns: '180px 190px 1fr', gap: '22px', alignItems: 'center' }}>
                <div style={{ textAlign: 'center', borderRight: '1px solid #e2e8f0' }}>
                    <div style={{ fontSize: '10px', fontWeight: 900, color: '#64748b', textTransform: 'uppercase' }}>Latest Overall Progress</div>
                    <div style={{ fontSize: '44px', fontWeight: 900, color: '#1e293b' }}>{progress}%</div>
                    <div style={{ fontSize: '12px', fontWeight: 800, color: '#16a34a' }}>On Track</div>
                </div>
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <div style={{ width: '150px', height: '150px', borderRadius: '50%', background: `conic-gradient(#16a34a ${progress}%, #e2e8f0 0)`, display: 'grid', placeItems: 'center' }}>
                        <div style={{ width: '100px', height: '100px', borderRadius: '50%', background: '#fff', display: 'grid', placeItems: 'center', fontWeight: 900, fontSize: '18px', color: '#1e293b' }}>{progress}%</div>
                    </div>
                </div>
                <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', marginBottom: '12px' }}>
                        <h4 style={{ margin: 0, color: '#2563eb', fontSize: '14px' }}>Weekly Execution Phase</h4>
                        {canEdit && (
                            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                                <Button variant="outline" onClick={() => setShowImport(true)}>Bulk Upload</Button>
                                <Button variant="dark" onClick={() => setShowModal(true)}>Add New Weekly Report</Button>
                            </div>
                        )}
                    </div>
                    <div style={{ background: '#e0f2fe', color: '#075985', borderRadius: '8px', padding: '10px 12px', fontSize: '12.5px', fontWeight: 600 }}>
                        A total of <strong>27 critical site checklists</strong> must be verified weekly by the site supervisor and approved by the QA/QC manager.
                    </div>
                </div>
            </div>

            {/* NTP filter */}
            {ntpOptions.length > 0 && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', alignItems: 'flex-end', marginBottom: '12px' }}>
                    <div>
                        <label style={{ display: 'block', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '4px' }}>NTP / Contractor</label>
                        <select value={ntpFilter} onChange={e => setNtpFilter(e.target.value)}
                            style={{ ...inputStyle, padding: '6px 8px', minWidth: '200px' }}>
                            <option value="">All NTPs</option>
                            {ntpOptions.map(n => <option key={n} value={n}>{n}</option>)}
                        </select>
                    </div>
                    {ntpFilter && (
                        <button type="button" onClick={() => setNtpFilter('')}
                            style={{ padding: '7px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12px', fontWeight: 600, color: '#475569', cursor: 'pointer' }}>
                            Clear filter
                        </button>
                    )}
                    <div style={{ marginLeft: 'auto', fontSize: '12px', color: '#64748b', paddingBottom: '7px' }}>
                        Showing <strong>{filteredReports.length}</strong> of {reports.length}
                    </div>
                </div>
            )}

            <DataTable
                headers={['Week#', ...(hasSubRows ? ['Project'] : []), 'NTP / Contractor', '% Completion', 'Identified Issues', 'Submitted Date', 'Attachment', 'Actions']}
                rows={filteredReports.map(r => [
                    <strong style={{ color: '#2563eb' }}>{r.week_code}</strong>,
                    ...(hasSubRows ? [
                        r.sub_project_no
                            ? <span style={{ fontSize: '11px', fontWeight: 700, color: '#4338ca', background: '#eef2ff', border: '1px solid #c7d2fe', borderRadius: '5px', padding: '2px 7px', whiteSpace: 'nowrap' }}>{r.sub_project_no}</span>
                            : <span style={{ fontSize: '11.5px', color: '#94a3b8' }}>This project</span>,
                    ] : []),
                    r.ntp_no
                        ? <span style={{ fontSize: '12px', fontWeight: 600, color: '#1e40af' }}>{r.ntp_no}{r.ntp_contractor ? ` — ${r.ntp_contractor}` : ''}</span>
                        : <span style={{ color: '#cbd5e1', fontSize: '12px' }}>Whole project</span>,
                    <ProgressBar value={r.completion_pct} />,
                    <span style={{ fontSize: '12.5px' }}>{r.identified_issues ?? '—'}</span>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{r.submitted_date}</span>,
                    r.filename
                        ? <a href={r.url ?? '#'} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700, fontSize: '12.5px' }}>{r.filename}</a>
                        : <span style={{ color: '#94a3b8' }}>—</span>,
                    r.sub_project_id ? (
                        <div style={{ display: 'flex', gap: '4px' }}>
                            <button type="button" title="View" onClick={() => setViewing(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fff', border: '1px solid #e2e8f0', color: '#475569' }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                            <button type="button" title={`Open ${r.sub_project_no}`} onClick={() => router.visit(route('projects.hub.psr', r.sub_project_id!))} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#eef2ff', border: '1px solid #c7d2fe', color: '#4338ca' }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M7 17L17 7M7 7h10v10"/></svg></button>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', gap: '4px' }}>
                            <button type="button" title="View" onClick={() => setViewing(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fff', border: '1px solid #e2e8f0', color: '#475569' }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                            {canEdit && <button type="button" title="Delete" onClick={() => handleDelete(r)} style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626' }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>}
                        </div>
                    ),
                ])}
            />
        </HubShell>
    );
}
