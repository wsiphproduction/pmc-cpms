import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { ContractorRow, FilterPanel, Filters, Options, RatingBadge, SectionTabs, Stat, filterQuery, fmtScore, shareCell, withQuery } from './shared';

interface Props {
    stats: { contractors: number; projects: number; projects_evaluated: number; pending_evaluation: number; in_signoff: number };
    contractors: ContractorRow[];
    filters: Filters;
    options: Options;
}

const CATEGORY_COLUMNS = [
    { key: 'schedule',      letter: 'A', title: 'Project Schedule and Timeliness' },
    { key: 'quality',       letter: 'B', title: 'Quality of Workmanship and Technical Compliance' },
    { key: 'manpower',      letter: 'C', title: 'Manpower Management and Availability' },
    { key: 'equipment',     letter: 'D', title: 'Equipment and Resource Management' },
    { key: 'hse',           letter: 'E', title: 'Health, Safety and Environmental Compliance' },
    { key: 'contractual',   letter: 'F', title: 'Contractual and Administrative Compliance' },
    { key: 'documentation', letter: 'G', title: 'Project Documentation and Reporting' },
    { key: 'coordination',  letter: 'H', title: 'Coordination and Responsiveness' },
];

const th: React.CSSProperties = { padding: '9px 10px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', whiteSpace: 'nowrap', borderBottom: '1px solid #e2e8f0' };
const td: React.CSSProperties = { padding: '10px', fontSize: '12.5px', color: '#334155', borderBottom: '1px solid #f1f5f9', verticalAlign: 'middle' };

function downloadLink(label: string, href: string) {
    return (
        <a href={href} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '8px 13px', borderRadius: '8px', border: '1px solid #ddd6fe', background: '#fff', color: '#6d28d9', fontWeight: 700, fontSize: '12.5px', textDecoration: 'none', whiteSpace: 'nowrap' }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {label}
        </a>
    );
}

export default function ContractorPerformanceIndex({ stats, contractors, filters, options }: Props) {
    const query = filterQuery(filters);
    const open = (row: ContractorRow) => router.visit(route('contractor-performance.show', { ...query, c: row.key }));

    return (
        <AuthenticatedLayout>
            <Head title="Contractor Performance" />

            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', marginBottom: '18px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>Contractor Performance</h1>
                    <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>
                        Each contractor’s approved project evaluations, consolidated across the projects they handled.
                        {filters.window_from || filters.window_to ? ` Evaluations ${filters.window_from ?? '…'} to ${filters.window_to ?? '…'}.` : ''}
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    {downloadLink('Ranking Report', withQuery(route('contractor-performance.export'), query))}
                    {downloadLink('Project-Level Report', withQuery(route('contractor-performance.export'), { ...query, report: 'evaluations' }))}
                </div>
            </div>

            <SectionTabs active="ranking" filters={filters} />

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '12px', marginBottom: '18px' }}>
                <Stat value={stats.contractors} label="Total Contractors" hint="Contractors with a live NTP or an approved evaluation in this view." />
                <Stat value={stats.projects} label="Total Projects" hint="Projects assigned to those contractors." />
                <Stat value={stats.projects_evaluated} label="Projects Evaluated" hint="Projects with an approved evaluation in the period." />
                <Stat value={stats.pending_evaluation} label="Pending Evaluation" hint="Projects with no approved evaluation in the period — Not Yet Evaluated." />
                <Stat value={stats.in_signoff} label="Evaluations in Sign-off" hint="Submitted evaluations awaiting PMD review or approval." />
            </div>

            <FilterPanel filters={filters} options={options} routeName="contractor-performance.index" />

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', overflow: 'hidden' }}>
                <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
                    <span style={{ fontSize: '12px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Contractor Ranking and Comparison</span>
                    {/* Legend for the category cells */}
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontSize: '11px', color: '#64748b' }}>
                        Category score, % of its weight:
                        {[['<60', 50], ['60', 65], ['70', 75], ['80', 85], ['90+', 95]].map(([label, v]) => {
                            const s = shareCell(v as number);
                            return <span key={label as string} style={{ padding: '1px 6px', borderRadius: '4px', background: s.background, color: s.color, fontWeight: 700 }}>{label}</span>;
                        })}
                    </span>
                </div>
                {contractors.length === 0 ? (
                    <div style={{ padding: '48px 24px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
                        No contractors match these filters. Contractors appear once they hold a live NTP or have an approved evaluation.
                    </div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead style={{ background: '#f8fafc' }}>
                                <tr>
                                    <th style={th}>Rank</th>
                                    <th style={th}>Contractor</th>
                                    <th style={{ ...th, textAlign: 'right' }}>Projects</th>
                                    <th style={{ ...th, textAlign: 'right' }} title="Projects with an approved evaluation / not yet evaluated">Evaluated</th>
                                    <th style={{ ...th, textAlign: 'right' }}>Score</th>
                                    <th style={th}>Rating</th>
                                    {CATEGORY_COLUMNS.map(c => <th key={c.key} style={{ ...th, textAlign: 'center' }} title={c.title}>{c.letter}</th>)}
                                    <th style={{ ...th, textAlign: 'right' }} title="Non-compliance records not yet closed (overdue)">Open NCR</th>
                                    <th style={th}>Flags</th>
                                </tr>
                            </thead>
                            <tbody>
                                {contractors.map(row => (
                                    <tr key={row.key} onClick={() => open(row)} style={{ cursor: 'pointer' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#faf5ff')}
                                        onMouseLeave={e => (e.currentTarget.style.background = '')}>
                                        <td style={{ ...td, fontWeight: 800, color: '#0f172a' }}>{row.rank ?? '—'}</td>
                                        <td style={td}>
                                            <div style={{ fontWeight: 700, color: '#0f172a' }}>{row.name}</div>
                                            <div style={{ fontSize: '11px', color: row.supplier ? '#64748b' : '#b45309' }}>
                                                {row.supplier ? (row.supplier.accredited ? 'Master Data · accredited' : 'Master Data') : 'Not on Master Data'}
                                            </div>
                                        </td>
                                        <td style={{ ...td, textAlign: 'right' }}>{row.projects}</td>
                                        <td style={{ ...td, textAlign: 'right', whiteSpace: 'nowrap' }}>{row.evaluated}{row.pending > 0 && <span style={{ color: '#94a3b8' }}> / {row.pending} pending</span>}</td>
                                        <td style={{ ...td, textAlign: 'right', fontWeight: 900, color: '#0f172a', fontSize: '14px' }}>{fmtScore(row.score)}</td>
                                        <td style={td}><RatingBadge label={row.classification} /></td>
                                        {CATEGORY_COLUMNS.map(c => {
                                            const v = row.category_averages[c.key] ?? null;
                                            const s = shareCell(v);
                                            return (
                                                <td key={c.key} style={{ ...td, padding: '6px 4px', textAlign: 'center' }} title={`${c.letter}. ${c.title}: ${v === null ? 'not evaluated' : `${v}% of weight`}`}>
                                                    <span style={{ display: 'inline-block', minWidth: '38px', padding: '4px 4px', borderRadius: '4px', background: s.background, color: s.color, fontSize: '11.5px', fontWeight: 700 }}>
                                                        {v === null ? '—' : Math.round(v)}
                                                    </span>
                                                </td>
                                            );
                                        })}
                                        <td style={{ ...td, textAlign: 'right', whiteSpace: 'nowrap' }}>
                                            {row.findings_open > 0 ? row.findings_open : <span style={{ color: '#cbd5e1' }}>—</span>}
                                            {row.findings_overdue > 0 && <span style={{ color: '#b91c1c', fontWeight: 700 }}> ({row.findings_overdue} overdue)</span>}
                                        </td>
                                        <td style={td}>
                                            {row.flags.length > 0
                                                ? <span title={row.flags.map(f => f.message).join('\n')} style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '11.5px', fontWeight: 700, color: '#b91c1c' }}>⚑ {row.flags.length}</span>
                                                : <span style={{ color: '#cbd5e1' }}>—</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
            <p style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '10px', lineHeight: 1.6 }}>
                Overall score = sum of project scores ÷ number of evaluated projects, each project counted by its latest approved evaluation in the period
                {filters.basis === 'value' ? ', weighted by the contract value of the contractor’s NTPs on it' : ''}. Projects not yet evaluated are left out, not scored as zero.
                The rating bands are the framework’s proposed thresholds, pending PMC Management approval.
            </p>
        </AuthenticatedLayout>
    );
}
