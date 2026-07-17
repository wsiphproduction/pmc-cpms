import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, DataTable, Field, HubProject, HubShell, Modal, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface PsrRow { id: number; week_code: string; completion_pct: number; identified_issues: string | null; progress_updates: string | null; submitted_date: string; filename: string | null; url: string | null; ntp_id: number | null; ntp_no: string | null; ntp_contractor: string | null; sub_project_id: number | null; sub_project_no: string | null }
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

function SectionRow({ seq, label }: { seq: string; label: string }) {
    return (
        <tr style={{ background: '#dbeafe' }}>
            <td style={{ border: '1px solid #000', padding: '5px 8px', fontWeight: 800, fontSize: '12px', color: '#1e40af', textAlign: 'center' }}>{seq}</td>
            <td colSpan={3} style={{ border: '1px solid #000', padding: '5px 8px', fontWeight: 800, fontSize: '12px', color: '#1e40af' }}>{label}</td>
        </tr>
    );
}

function ChecklistRow({ seq, description }: { seq: string; description: string }) {
    const cellStyle: React.CSSProperties = { border: '1px solid #000', padding: '5px 8px', verticalAlign: 'middle' };
    return (
        <tr>
            <td style={{ ...cellStyle, textAlign: 'center', fontSize: '12px', color: '#64748b', fontWeight: 600, width: '50px' }}>{seq}</td>
            <td style={{ ...cellStyle, fontSize: '12.5px' }}>{description}</td>
            <td style={{ ...cellStyle, textAlign: 'center', width: '90px' }}>
                <select style={{ border: 'none', background: 'transparent', fontSize: '14px', fontWeight: 800, cursor: 'pointer', textAlign: 'center', width: '100%' }}>
                    <option>—</option><option>√</option><option>✕</option><option>Ø</option>
                </select>
            </td>
            <td style={{ ...cellStyle }}>
                <input type="text" style={{ border: 'none', background: 'transparent', width: '100%', fontSize: '12.5px', fontFamily: 'inherit' }} />
            </td>
        </tr>
    );
}

// ── View Report Modal ──────────────────────────────────────────────────────
function ViewReportModal({ report, onClose }: { report: PsrRow; onClose: () => void }) {
    const lc: React.CSSProperties = { background: '#f8fafc', fontWeight: 700, fontSize: '12.5px', padding: '10px 14px', width: '30%', borderRight: '1px solid #e5e7eb', color: '#374151', verticalAlign: 'top' };
    const vc: React.CSSProperties = { padding: '10px 14px', fontSize: '13px', color: '#0f172a', verticalAlign: 'top' };
    const rw: React.CSSProperties = { borderBottom: '1px solid #e5e7eb' };
    return (
        <Modal title={`PSR — ${report.week_code}`} onClose={onClose} size="600px"
            footer={
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
            }
        >
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden' }}>
                <tbody>
                    <tr style={rw}>
                        <td style={lc}>Week Code</td>
                        <td style={{ ...vc, fontWeight: 700, color: '#2563eb' }}>{report.week_code}</td>
                    </tr>
                    <tr style={rw}>
                        <td style={lc}>Overall Completion</td>
                        <td style={vc}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                <strong style={{ fontSize: '16px' }}>{report.completion_pct}%</strong>
                                <div style={{ flex: 1, height: '8px', borderRadius: '999px', background: '#e2e8f0' }}>
                                    <div style={{ width: `${report.completion_pct}%`, height: '100%', borderRadius: '999px', background: '#16a34a' }} />
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr style={rw}>
                        <td style={lc}>Submitted Date</td>
                        <td style={vc}>{report.submitted_date}</td>
                    </tr>
                    <tr style={rw}>
                        <td style={lc}>Identified Issues</td>
                        <td style={vc}>{report.identified_issues ?? <span style={{ color: '#94a3b8' }}>—</span>}</td>
                    </tr>
                    <tr style={rw}>
                        <td style={lc}>Progress Updates</td>
                        <td style={vc}>{report.progress_updates ?? <span style={{ color: '#94a3b8' }}>—</span>}</td>
                    </tr>
                    <tr>
                        <td style={lc}>Attachment</td>
                        <td style={vc}>
                            {report.url
                                ? <a href={report.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontWeight: 600, textDecoration: 'none' }}>{report.filename}</a>
                                : <span style={{ color: '#94a3b8' }}>No attachment</span>
                            }
                        </td>
                    </tr>
                </tbody>
            </table>
        </Modal>
    );
}

// ── Weekly Report Modal ────────────────────────────────────────────────────
function ReportModal({ project, ntps, onClose }: { project: HubProject; ntps: NtpOption[]; onClose: () => void }) {
    const [weekCode, setWeekCode]   = useState('');
    const [pct, setPct]             = useState('');
    const [issues, setIssues]       = useState('');
    const [updates, setUpdates]     = useState('');
    const [ntpId, setNtpId]         = useState('');
    const [saving, setSaving]       = useState(false);
    const [error, setError]         = useState('');

    const thStyle: React.CSSProperties = { border: '1px solid #000', padding: '6px 8px', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', textAlign: 'center', background: '#f8fafc' };

    const handleSubmit = () => {
        if (!weekCode.trim()) { setError('Week Code is required (e.g. W1-OCT).'); return; }
        if (!pct || Number(pct) < 0 || Number(pct) > 100) { setError('Completion % must be between 0 and 100.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.psr.store', project.id), {
            project_ntp_id: ntpId || null,
            week_code: weekCode,
            completion_pct: pct,
            identified_issues: issues,
            progress_updates: updates,
        }, {
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
            {/* Checklist */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '5px', border: '1px solid #000', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Checklist</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>
                    <tr style={{ background: '#fefce8' }}>
                        <td colSpan={2} style={{ border: '1px solid #000', padding: '5px 8px', fontWeight: 600, fontSize: '12px' }}>Legend:</td>
                        <td style={{ border: '1px solid #000', padding: '5px 8px', textAlign: 'center', fontSize: '12px', fontWeight: 700, color: '#15803d' }}>√ – Completed</td>
                        <td style={{ border: '1px solid #000', padding: '5px 8px', textAlign: 'center', fontSize: '12px', fontWeight: 700, color: '#b91c1c' }}>✕ – Not Completed</td>
                    </tr>
                    <tr>
                        <th style={thStyle}>Seq#</th>
                        <th style={{ ...thStyle, textAlign: 'left', width: '50%' }}>Item / Requirement Description</th>
                        <th style={{ ...thStyle, width: '90px' }}>Status</th>
                        <th style={{ ...thStyle, textAlign: 'left' }}>Remarks / Comments</th>
                    </tr>
                    <SectionRow seq="1.0" label="General Site Conditions" />
                    <ChecklistRow seq="1.1" description="Site access is clear, secure, and signposted" />
                    <ChecklistRow seq="1.2" description="Appropriate signage (directional, hazard, information) is posted" />
                    <SectionRow seq="2.0" label="Quality Assurance and Control" />
                    <ChecklistRow seq="2.1" description="Approved updated drawings and specifications available on-site" />
                    <ChecklistRow seq="2.2" description="Workmanship (Civil, Electrical, Mechanical) meets standards" />
                </tbody>
            </table>

            {/* Issues table */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '5px', border: '1px solid #000', borderBottom: 'none', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Weekly Issues and Action Plan</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '20px', fontSize: '12.5px' }}>
                <thead>
                    <tr>
                        <th style={{ ...thStyle, width: '50px' }}>No.</th>
                        <th style={{ ...thStyle, textAlign: 'left' }}>Key Issues Identified This Week</th>
                        <th style={{ ...thStyle, textAlign: 'left' }}>Corrective Actions</th>
                        <th style={{ ...thStyle, width: '150px' }}>Commitment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style={{ border: '1px solid #000', padding: '4px 8px', textAlign: 'center', color: '#64748b' }}>1</td>
                        <td style={{ border: '1px solid #000', padding: '4px' }}><textarea rows={2} value={issues} onChange={e => setIssues(e.target.value)} style={{ border: 'none', background: 'transparent', width: '100%', resize: 'vertical', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                        <td style={{ border: '1px solid #000', padding: '4px' }}><textarea rows={2} style={{ border: 'none', background: 'transparent', width: '100%', resize: 'vertical', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                        <td style={{ border: '1px solid #000', padding: '4px' }}><input type="date" style={{ border: 'none', background: 'transparent', width: '100%', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                    </tr>
                    {[2, 3].map(n => (
                        <tr key={n}>
                            <td style={{ border: '1px solid #000', padding: '4px 8px', textAlign: 'center', color: '#64748b' }}>{n}</td>
                            <td style={{ border: '1px solid #000', padding: '4px' }}><textarea rows={2} style={{ border: 'none', background: 'transparent', width: '100%', resize: 'vertical', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                            <td style={{ border: '1px solid #000', padding: '4px' }}><textarea rows={2} style={{ border: 'none', background: 'transparent', width: '100%', resize: 'vertical', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                            <td style={{ border: '1px solid #000', padding: '4px' }}><input type="date" style={{ border: 'none', background: 'transparent', width: '100%', fontFamily: 'inherit', fontSize: '12.5px' }} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {/* Progress Updates */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '5px', border: '1px solid #000', borderBottom: 'none', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Project Progress Updates</div>
            <textarea
                rows={5}
                placeholder="Provide general updates on overall project progress, accomplished milestones, and next week's outlook..."
                value={updates}
                onChange={e => setUpdates(e.target.value)}
                style={{ ...inputStyle, borderRadius: '0 0 0 0', border: '1px solid #000', marginBottom: '16px', resize: 'vertical' }}
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
                    <input type="file" accept=".pdf" style={inputStyle} />
                </Field>
            </div>
        </Modal>
    );
}

// ── CSV Bulk Import Modal ──────────────────────────────────────────────────
function ImportModal({ project, onClose }: { project: HubProject; onClose: () => void }) {
    const [file, setFile]     = useState<File | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const handleSubmit = () => {
        if (!file) { setError('Please choose a CSV file first.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.psr.import', project.id), { file }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    const downloadTemplate = () => {
        const csv = 'week_code,completion_pct,identified_issues,progress_updates,submitted_date,ntp_no\n'
            + 'W1-OCT,25,Delayed delivery of materials,Foundation works started,2026-10-07,\n'
            + 'W2-OCT,40,,Column rebars installed,2026-10-14,\n';
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = 'psr-template.csv';
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <Modal title="Bulk Upload Weekly Reports (CSV)" onClose={onClose} size="560px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Importing...' : 'Import Reports'}
                </button>
            </>}
        >
            <div style={{ fontSize: '13px', color: '#475569', marginBottom: '14px', lineHeight: 1.5 }}>
                Upload a CSV to add many weekly reports at once. The header row is matched
                case-insensitively. Only <strong>week_code</strong> is required per row.
            </div>
            <ul style={{ fontSize: '12.5px', color: '#475569', margin: '0 0 16px', paddingLeft: '18px', lineHeight: 1.6 }}>
                <li><strong>week_code</strong> — e.g. W1-OCT (required)</li>
                <li><strong>completion_pct</strong> — 0–100</li>
                <li><strong>identified_issues</strong>, <strong>progress_updates</strong> — free text</li>
                <li><strong>submitted_date</strong> — e.g. 2026-10-07 (defaults to today)</li>
                <li><strong>ntp_no</strong> — matches an NTP on this project (optional)</li>
            </ul>
            <input
                type="file"
                accept=".csv,text/csv"
                onChange={e => setFile(e.target.files?.[0] ?? null)}
                style={{ ...inputStyle, padding: '8px' }}
            />
            <button type="button" onClick={downloadTemplate}
                style={{ marginTop: '12px', background: 'transparent', border: 'none', color: '#2563eb', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', padding: 0 }}>
                ↓ Download CSV template
            </button>
        </Modal>
    );
}

// ── Main Component ─────────────────────────────────────────────────────────
export default function PsrHub({ project, reports, ntps = [], canEdit = true }: { project: HubProject; reports: PsrRow[]; ntps?: NtpOption[]; canEdit?: boolean }) {
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
            {showModal  && <ReportModal project={project} ntps={ntps} onClose={() => setShowModal(false)} />}
            {showImport && <ImportModal project={project} onClose={() => setShowImport(false)} />}
            {viewing    && <ViewReportModal report={viewing} onClose={() => setViewing(null)} />}

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
                                <Button variant="outline" onClick={() => setShowImport(true)}>Bulk Upload CSV</Button>
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
