import { inputStyle } from './Common';

// The weekly progress report document: checklist, weekly issues / action plan
// and progress updates. Shared by the operations hub's PSR tab and the Weekly
// Status module so a report is filled in and read back in the same layout
// wherever it was filed from. The checklist, issue-row count and allowed
// statuses all come from config/psr.php.

export interface ChecklistEntry { seq: string; status: string | null; remarks: string | null }
export interface IssueEntry { issue: string | null; action: string | null; commitment_date: string | null }
export interface ChecklistItem { seq: string; label: string; section?: boolean }

/** Checklist rows that take an answer (section headings don't). */
export const answerable = (checklist: ChecklistItem[]) => checklist.filter(c => !c.section);

const docCell: React.CSSProperties = { border: '1px solid #000', padding: '5px 8px', verticalAlign: 'middle' };
const docTh: React.CSSProperties = { border: '1px solid #000', padding: '6px 8px', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', textAlign: 'center', background: '#f8fafc' };

const statusColor = (status?: string | null) => status === '√' ? '#15803d' : status === '✕' ? '#b91c1c' : '#94a3b8';

/** Bare input inside a bordered document cell. */
const docCellInput: React.CSSProperties = { border: 'none', background: 'transparent', width: '100%', fontFamily: 'inherit', fontSize: '12.5px' };

const docTable: React.CSSProperties = { width: '100%', borderCollapse: 'collapse', marginBottom: '20px', fontSize: '12.5px' };

export function Banner({ children, attached = false }: { children: React.ReactNode; attached?: boolean }) {
    return (
        <div style={{
            background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '5px',
            border: '1px solid #000', borderBottom: attached ? 'none' : '1px solid #000',
            textTransform: 'uppercase', letterSpacing: '0.5px',
        }}>
            {children}
        </div>
    );
}

function SectionRow({ seq, label }: { seq: string; label: string }) {
    return (
        <tr style={{ background: '#dbeafe' }}>
            <td style={{ ...docCell, fontWeight: 800, fontSize: '12px', color: '#1e40af', textAlign: 'center' }}>{seq}</td>
            <td colSpan={3} style={{ ...docCell, fontWeight: 800, fontSize: '12px', color: '#1e40af' }}>{label}</td>
        </tr>
    );
}

/** Editable when `onChange` is given; otherwise renders the saved answer. */
function ChecklistRow({ seq, description, value, statuses = [], onChange }: {
    seq: string;
    description: string;
    value: ChecklistEntry | null;
    statuses?: string[];
    onChange?: (patch: Partial<ChecklistEntry>) => void;
}) {
    return (
        <tr>
            <td style={{ ...docCell, textAlign: 'center', fontSize: '12px', color: '#64748b', fontWeight: 600, width: '50px' }}>{seq}</td>
            <td style={{ ...docCell, fontSize: '12.5px' }}>{description}</td>
            <td style={{ ...docCell, textAlign: 'center', width: '90px' }}>
                {onChange ? (
                    <select
                        value={value?.status ?? ''}
                        onChange={e => onChange({ status: e.target.value || null })}
                        style={{ ...docCellInput, fontSize: '14px', fontWeight: 800, cursor: 'pointer', textAlign: 'center', color: statusColor(value?.status) }}
                    >
                        <option value="">—</option>
                        {statuses.map(s => <option key={s} value={s}>{s}</option>)}
                    </select>
                ) : (
                    <span style={{ fontSize: '14px', fontWeight: 800, color: statusColor(value?.status) }}>{value?.status ?? '—'}</span>
                )}
            </td>
            <td style={docCell}>
                {onChange ? (
                    <input type="text" value={value?.remarks ?? ''} onChange={e => onChange({ remarks: e.target.value })} style={docCellInput} />
                ) : (
                    <span style={{ fontSize: '12.5px', color: value?.remarks ? '#0f172a' : '#94a3b8' }}>{value?.remarks || '—'}</span>
                )}
            </td>
        </tr>
    );
}

function ChecklistLegend() {
    return (
        <tr style={{ background: '#fefce8' }}>
            <td colSpan={2} style={{ ...docCell, fontWeight: 600, fontSize: '12px' }}>Legend:</td>
            <td style={{ ...docCell, textAlign: 'center', fontSize: '12px', fontWeight: 700, color: '#15803d' }}>√ – Completed</td>
            <td style={{ ...docCell, textAlign: 'center', fontSize: '12px', fontWeight: 700, color: '#b91c1c' }}>✕ – Not Completed</td>
        </tr>
    );
}

function ChecklistHeader() {
    return (
        <tr>
            <th style={docTh}>Seq#</th>
            <th style={{ ...docTh, textAlign: 'left', width: '50%' }}>Item / Requirement Description</th>
            <th style={{ ...docTh, width: '90px' }}>Status</th>
            <th style={{ ...docTh, textAlign: 'left' }}>Remarks / Comments</th>
        </tr>
    );
}

/** Read-only counterpart to an input in the field grid at the foot of the form. */
export function ReadOnlyValue({ children, muted = false }: { children: React.ReactNode; muted?: boolean }) {
    return (
        <div style={{
            ...inputStyle, background: '#f8fafc', color: muted ? '#94a3b8' : '#0f172a',
            minHeight: '35px', display: 'flex', alignItems: 'center',
        }}>
            {children}
        </div>
    );
}

function IssuesHeader() {
    return (
        <tr>
            <th style={{ ...docTh, width: '50px' }}>No.</th>
            <th style={{ ...docTh, textAlign: 'left' }}>Key Issues Identified This Week</th>
            <th style={{ ...docTh, textAlign: 'left' }}>Corrective Actions</th>
            <th style={{ ...docTh, width: '150px' }}>Commitment Date</th>
        </tr>
    );
}

// ── Editable document ──────────────────────────────────────────────────────

/**
 * The fillable part of the report. The surrounding modal owns the week code,
 * completion % and attachment fields, which differ between the two modules.
 */
export function ReportFormBody({
    checklist, statuses, answers, onAnswer, issueRows, onIssue, updates, onUpdates,
}: {
    checklist: ChecklistItem[];
    statuses: string[];
    answers: Record<string, ChecklistEntry>;
    onAnswer: (seq: string, patch: Partial<ChecklistEntry>) => void;
    issueRows: IssueEntry[];
    onIssue: (index: number, patch: Partial<IssueEntry>) => void;
    updates: string;
    onUpdates: (value: string) => void;
}) {
    return (
        <>
            <Banner>Checklist</Banner>
            <table style={docTable}>
                <tbody>
                    <ChecklistLegend />
                    <ChecklistHeader />
                    {checklist.map(c => c.section
                        ? <SectionRow key={c.seq} seq={c.seq} label={c.label} />
                        : <ChecklistRow
                            key={c.seq}
                            seq={c.seq}
                            description={c.label}
                            value={answers[c.seq] ?? null}
                            statuses={statuses}
                            onChange={patch => onAnswer(c.seq, patch)}
                        />
                    )}
                </tbody>
            </table>

            <Banner attached>Weekly Issues and Action Plan</Banner>
            <table style={docTable}>
                <thead><IssuesHeader /></thead>
                <tbody>
                    {issueRows.map((row, i) => (
                        <tr key={i}>
                            <td style={{ ...docCell, padding: '4px 8px', textAlign: 'center', color: '#64748b' }}>{i + 1}</td>
                            <td style={{ ...docCell, padding: '4px' }}>
                                <textarea rows={2} value={row.issue ?? ''} onChange={e => onIssue(i, { issue: e.target.value })} style={{ ...docCellInput, resize: 'vertical' }} />
                            </td>
                            <td style={{ ...docCell, padding: '4px' }}>
                                <textarea rows={2} value={row.action ?? ''} onChange={e => onIssue(i, { action: e.target.value })} style={{ ...docCellInput, resize: 'vertical' }} />
                            </td>
                            <td style={{ ...docCell, padding: '4px' }}>
                                <input type="date" value={row.commitment_date ?? ''} onChange={e => onIssue(i, { commitment_date: e.target.value })} style={docCellInput} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <Banner attached>Project Progress Updates</Banner>
            <textarea
                rows={5}
                placeholder="Provide general updates on overall project progress, accomplished milestones, and next week's outlook..."
                value={updates}
                onChange={e => onUpdates(e.target.value)}
                style={{ ...inputStyle, borderRadius: 0, border: '1px solid #000', marginBottom: '16px', resize: 'vertical' }}
            />
        </>
    );
}

// ── Read-only document ─────────────────────────────────────────────────────

export interface SavedReport {
    checklist: ChecklistEntry[];
    issues: IssueEntry[];
    identified_issues: string | null;
    progress_updates: string | null;
}

/** Read-only mirror of ReportFormBody, so a saved report reads back unchanged. */
export function ReportViewBody({ report, checklist, issueRowCount }: {
    report: SavedReport;
    checklist: ChecklistItem[];
    issueRowCount: number;
}) {
    const answers = new Map(report.checklist.map(c => [c.seq, c]));
    const known = new Set(checklist.map(c => c.seq));

    // Pad to the form's row count so the grid keeps its shape; reports created
    // before the detailed fields existed fall back to the headline issue text.
    const issueRows: IssueEntry[] = report.issues.length > 0
        ? report.issues
        : report.identified_issues
            ? [{ issue: report.identified_issues, action: null, commitment_date: null }]
            : [];
    const paddedIssues = Array.from({ length: Math.max(issueRowCount, issueRows.length) },
        (_, i) => issueRows[i] ?? { issue: null, action: null, commitment_date: null });

    const dash = <span style={{ color: '#94a3b8' }}>—</span>;

    return (
        <>
            <Banner>Checklist</Banner>
            <table style={docTable}>
                <tbody>
                    <ChecklistLegend />
                    <ChecklistHeader />
                    {checklist.map(c => c.section
                        ? <SectionRow key={c.seq} seq={c.seq} label={c.label} />
                        : <ChecklistRow key={c.seq} seq={c.seq} description={c.label} value={answers.get(c.seq) ?? null} />
                    )}
                    {/* Imported under a seq the form doesn't define — shown so nothing is hidden. */}
                    {report.checklist.filter(c => !known.has(c.seq)).map(c => (
                        <ChecklistRow key={c.seq} seq={c.seq} description="Imported checklist item" value={c} />
                    ))}
                </tbody>
            </table>

            <Banner attached>Weekly Issues and Action Plan</Banner>
            <table style={docTable}>
                <thead><IssuesHeader /></thead>
                <tbody>
                    {paddedIssues.map((i, n) => (
                        <tr key={n}>
                            <td style={{ ...docCell, textAlign: 'center', color: '#64748b' }}>{n + 1}</td>
                            <td style={{ ...docCell, verticalAlign: 'top' }}>{i.issue || dash}</td>
                            <td style={{ ...docCell, verticalAlign: 'top' }}>{i.action || dash}</td>
                            <td style={{ ...docCell, textAlign: 'center' }}>{i.commitment_date || dash}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <Banner attached>Project Progress Updates</Banner>
            <div style={{
                border: '1px solid #000', padding: '10px 12px', marginBottom: '16px', minHeight: '84px',
                fontSize: '12.5px', lineHeight: 1.55, whiteSpace: 'pre-wrap',
                color: report.progress_updates ? '#0f172a' : '#94a3b8',
            }}>
                {report.progress_updates || 'No progress updates recorded.'}
            </div>
        </>
    );
}

/** Completion figure rendered the way both modules' summary rows show it. */
export function CompletionBar({ value }: { value: number }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '9px', width: '100%' }}>
            <strong>{value}%</strong>
            <div style={{ flex: 1, height: '7px', borderRadius: '999px', background: '#e2e8f0' }}>
                <div style={{ width: `${value}%`, height: '100%', borderRadius: '999px', background: '#16a34a' }} />
            </div>
        </div>
    );
}
