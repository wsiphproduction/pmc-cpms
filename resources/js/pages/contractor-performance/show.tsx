import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { FindingRow } from '../project-management/hub/NcrHub';
import { ContractorRow, FilterPanel, Filters, Options, RatingBadge, Stat, filterQuery, fmtScore, peso, withQuery } from './shared';

interface CategoryScore { key: string; letter: string; title: string; weight: number; score: number | null; corrective_actions: string | null }

interface Assignment {
    project_id: number;
    project_no: string;
    title: string;
    department: string | null;
    category: string | null;
    status: string;
    project_type: string | null;
    contract_value: number;
    evaluation: { id: number; control_no: string; period_label: string; as_of: string | null; score: number | null; classification: string | null } | null;
}

interface HistoryRow {
    id: number;
    control_no: string;
    project_id: number;
    project_no: string;
    project_title: string;
    period_type: string;
    period_label: string;
    as_of: string | null;
    evaluated_by: string | null;
    score: number | null;
    classification: string | null;
    categories: CategoryScore[];
}

interface Profile extends ContractorRow {
    ranked_of: number;
    assignments: Assignment[];
    history: HistoryRow[];
    findings: (FindingRow & { project_id: number; project_no: string })[];
}

interface Props {
    profile: Profile;
    filters: Filters;
    options: Options;
    definition: { key: string; letter: string; title: string; weight: number }[];
}

const BAR = '#2a78d6';
const DEFICIENCY = 60;

const th: React.CSSProperties = { padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', whiteSpace: 'nowrap', borderBottom: '1px solid #e2e8f0' };
const td: React.CSSProperties = { padding: '10px 12px', fontSize: '12.5px', color: '#334155', borderBottom: '1px solid #f1f5f9', verticalAlign: 'top' };

function Card({ title, children, right }: { title: string; children: React.ReactNode; right?: React.ReactNode }) {
    return (
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', overflow: 'hidden', marginBottom: '18px' }}>
            <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '10px' }}>
                <span style={{ fontSize: '12px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{title}</span>
                {right}
            </div>
            {children}
        </div>
    );
}

const hubLink = (projectId: number, evaluationId?: number) =>
    evaluationId ? route('projects.hub.eval-summary', { project: projectId, evaluation: evaluationId }) : route('projects.hub.eval-summary', projectId);

export default function ContractorPerformanceShow({ profile, filters, options, definition }: Props) {
    const query = filterQuery(filters);

    return (
        <AuthenticatedLayout>
            <Head title={`${profile.name} — Contractor Performance`} />

            <Link href={withQuery(route('contractor-performance.index'), query)} style={{ fontSize: '12.5px', color: '#6d28d9', fontWeight: 700, textDecoration: 'none' }}>
                ← Contractor Performance
            </Link>

            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', margin: '8px 0 18px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>{profile.name}</h1>
                    <p style={{ fontSize: '12.5px', color: '#64748b', margin: '4px 0 0' }}>
                        {profile.supplier
                            ? <>On Master Data{profile.supplier.accredited ? ' · accredited' : ''}{profile.supplier.active ? '' : ' · inactive'}{profile.supplier.email ? ` · ${profile.supplier.email}` : ''}</>
                            : <span style={{ color: '#b45309' }}>Not on Master Data — evaluations are matched by name only.</span>}
                    </p>
                </div>
                <a href={withQuery(route('contractor-performance.export'), { ...query, report: 'contractor', c: profile.key })}
                    style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '8px 13px', borderRadius: '8px', border: '1px solid #ddd6fe', background: '#fff', color: '#6d28d9', fontWeight: 700, fontSize: '12.5px', textDecoration: 'none' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Individual Contractor Report
                </a>
            </div>

            {/* Headline */}
            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(220px, 280px) 1fr', gap: '12px', marginBottom: '18px' }}>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', textAlign: 'center' }}>
                    <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Overall Score</div>
                    <div style={{ fontSize: '40px', fontWeight: 900, color: '#0f172a', lineHeight: 1.1, margin: '4px 0' }}>{fmtScore(profile.score)}</div>
                    <div style={{ fontSize: '11px', color: '#94a3b8', marginBottom: '6px' }}>
                        out of 100{filters.basis === 'value' ? ' · weighted by contract value' : ''}
                    </div>
                    <RatingBadge label={profile.classification} />
                    {profile.action && <div style={{ fontSize: '11.5px', color: '#475569', marginTop: '8px' }}>{profile.action}</div>}
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '12px' }}>
                    <Stat value={profile.rank ? `${profile.rank} of ${profile.ranked_of}` : '—'} label="Rank" />
                    <Stat value={profile.projects} label="Projects" />
                    <Stat value={profile.evaluated} label="Projects Evaluated" />
                    <Stat value={profile.pending} label="Not Yet Evaluated" />
                    <Stat value={profile.evaluations} label="Approved Evaluations" />
                    <Stat value={profile.last_evaluated ?? '—'} label="Last Evaluated" />
                </div>
            </div>

            {profile.flags.length > 0 && (
                <div style={{ padding: '12px 16px', marginBottom: '18px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '10px' }}>
                    <div style={{ fontSize: '12px', fontWeight: 800, color: '#991b1b', marginBottom: '6px' }}>⚑ Flagged for corrective action monitoring</div>
                    <ul style={{ margin: 0, paddingLeft: '18px', fontSize: '12.5px', color: '#7f1d1d', lineHeight: 1.6 }}>
                        {profile.flags.map((f, i) => <li key={i}>{f.message}</li>)}
                    </ul>
                </div>
            )}

            <FilterPanel filters={filters} options={options} routeName="contractor-performance.show" extraQuery={{ c: profile.key }} />

            {/* Category strengths and weaknesses: one hue, % of each category's weight */}
            <Card title="Performance by Category" right={<span style={{ fontSize: '11px', color: '#64748b' }}>Average % of each category’s weight · dashed line = {DEFICIENCY}% deficiency level</span>}>
                <div style={{ padding: '14px 16px', display: 'grid', gap: '10px' }}>
                    {definition.map(c => {
                        const v = profile.category_averages[c.key] ?? null;
                        return (
                            <div key={c.key} style={{ display: 'grid', gridTemplateColumns: 'minmax(180px, 300px) 1fr 56px', alignItems: 'center', gap: '12px' }}
                                title={`${c.letter}. ${c.title} (${c.weight}%): ${v === null ? 'not evaluated' : `${v}% of weight`}`}>
                                <span style={{ fontSize: '12.5px', color: '#0f172a', fontWeight: 600 }}>{c.letter}. {c.title} <span style={{ color: '#94a3b8', fontWeight: 500 }}>({c.weight}%)</span></span>
                                <div style={{ position: 'relative', height: '14px', background: '#f1f5f9', borderRadius: '4px' }}>
                                    {v !== null && <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: `${Math.min(100, v)}%`, background: BAR, borderRadius: '0 4px 4px 0' }} />}
                                    <div style={{ position: 'absolute', left: `${DEFICIENCY}%`, top: '-3px', bottom: '-3px', borderLeft: '2px dashed #64748b' }} />
                                </div>
                                <span style={{ fontSize: '12.5px', fontWeight: 800, color: v !== null && v < DEFICIENCY ? '#b91c1c' : '#0f172a', textAlign: 'right' }}>
                                    {v === null ? '—' : `${v}%`}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </Card>

            <Card title="Projects Handled">
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead style={{ background: '#f8fafc' }}>
                            <tr>{['Project', 'Department', 'Status', 'Contract Value', 'Latest Evaluation', 'Score', 'Rating'].map(h => <th key={h} style={th}>{h}</th>)}</tr>
                        </thead>
                        <tbody>
                            {profile.assignments.map(a => (
                                <tr key={a.project_id}>
                                    <td style={td}>
                                        <a href={hubLink(a.project_id, a.evaluation?.id)} style={{ color: '#6d28d9', fontWeight: 700, textDecoration: 'none' }}>{a.project_no}</a>
                                        <div style={{ color: '#0f172a' }}>{a.title}</div>
                                    </td>
                                    <td style={td}>{a.department ?? '—'}</td>
                                    <td style={td}>{a.status}</td>
                                    <td style={{ ...td, whiteSpace: 'nowrap' }}>{a.contract_value > 0 ? peso(a.contract_value) : '—'}</td>
                                    <td style={td}>{a.evaluation ? <>{a.evaluation.control_no}<div style={{ color: '#64748b', fontSize: '11.5px' }}>{a.evaluation.period_label}</div></> : <span style={{ color: '#94a3b8' }}>—</span>}</td>
                                    <td style={{ ...td, fontWeight: 800, color: '#0f172a' }}>{fmtScore(a.evaluation?.score)}</td>
                                    <td style={td}><RatingBadge label={a.evaluation?.classification ?? null} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card title="Performance History">
                {profile.history.length === 0 ? (
                    <div style={{ padding: '24px', textAlign: 'center', color: '#94a3b8', fontSize: '12.5px' }}>No approved evaluations in this period.</div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead style={{ background: '#f8fafc' }}>
                                <tr>
                                    {['Evaluation', 'Project', 'Period', 'As Of', 'Evaluated By', 'Score', 'Rating'].map(h => <th key={h} style={th}>{h}</th>)}
                                    {definition.map(c => <th key={c.key} style={{ ...th, textAlign: 'center' }} title={`${c.title} (out of ${c.weight})`}>{c.letter}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {profile.history.map(h => (
                                    <tr key={h.id} onClick={() => router.visit(hubLink(h.project_id, h.id))} style={{ cursor: 'pointer' }}>
                                        <td style={{ ...td, fontWeight: 700, color: '#6d28d9' }}>{h.control_no}</td>
                                        <td style={td}>{h.project_no}</td>
                                        <td style={td}>{h.period_label}</td>
                                        <td style={{ ...td, whiteSpace: 'nowrap' }}>{h.as_of ?? '—'}</td>
                                        <td style={td}>{h.evaluated_by ?? '—'}</td>
                                        <td style={{ ...td, fontWeight: 800, color: '#0f172a' }}>{fmtScore(h.score)}</td>
                                        <td style={td}><RatingBadge label={h.classification} /></td>
                                        {definition.map(c => {
                                            const s = h.categories.find(x => x.key === c.key)?.score ?? null;
                                            const short = s !== null && c.weight > 0 && (s / c.weight) * 100 < DEFICIENCY;
                                            return <td key={c.key} style={{ ...td, textAlign: 'center', fontWeight: short ? 800 : 500, color: short ? '#b91c1c' : '#334155' }}
                                                title={short ? 'Below the deficiency level' : undefined}>{s === null ? '—' : Number(s).toFixed(1)}</td>;
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card title="Non-Compliance and Corrective Actions"
                right={<span style={{ fontSize: '11px', color: '#64748b' }}>{profile.findings_open} not closed{profile.findings_overdue > 0 ? ` · ${profile.findings_overdue} overdue` : ''}</span>}>
                {profile.findings.length === 0 ? (
                    <div style={{ padding: '24px', textAlign: 'center', color: '#94a3b8', fontSize: '12.5px' }}>No non-compliance records in this period.</div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead style={{ background: '#f8fafc' }}>
                                <tr>{['NCR No.', 'Project', 'Deficiency', 'Corrective Action', 'Identified', 'Target', 'Status', 'Contractor Response'].map(h => <th key={h} style={th}>{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {profile.findings.map(f => (
                                    <tr key={f.id} onClick={() => router.visit(route('projects.hub.ncr', f.project_id))} style={{ cursor: 'pointer' }}>
                                        <td style={{ ...td, fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap' }}>{f.control_no}</td>
                                        <td style={td}>{f.project_no}</td>
                                        <td style={{ ...td, whiteSpace: 'pre-wrap', maxWidth: '260px' }}>{f.category_label && <div style={{ fontSize: '11px', color: '#64748b' }}>{f.category_label}</div>}{f.deficiency}</td>
                                        <td style={{ ...td, whiteSpace: 'pre-wrap', maxWidth: '260px', color: '#0f172a' }}>{f.corrective_action}</td>
                                        <td style={{ ...td, whiteSpace: 'nowrap' }}>{f.date_identified}</td>
                                        <td style={{ ...td, whiteSpace: 'nowrap', color: f.overdue ? '#b91c1c' : '#334155', fontWeight: f.overdue ? 700 : 400 }}>{f.target_date ?? '—'}</td>
                                        <td style={{ ...td, whiteSpace: 'nowrap', fontWeight: 700, color: f.overdue ? '#b91c1c' : '#0f172a' }}>{f.overdue ? 'Overdue' : f.status_label}{f.closure_date ? <div style={{ fontWeight: 400, color: '#64748b', fontSize: '11.5px' }}>{f.closure_date}</div> : null}</td>
                                        <td style={{ ...td, whiteSpace: 'pre-wrap', maxWidth: '260px' }}>{f.contractor_response ?? <span style={{ color: '#94a3b8' }}>—</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
