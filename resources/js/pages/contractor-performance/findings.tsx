import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { FindingRow } from '../project-management/hub/NcrHub';
import { FilterPanel, Filters, Options, SectionTabs, Stat, filterQuery, withQuery } from './shared';

interface Props {
    stats: { total: number; open: number; responded: number; overdue: number; closed: number; avg_days_to_close: number | null };
    findings: (FindingRow & { project_id: number; project_no: string; project_title: string; department: string | null })[];
    ncr_status: string | null;
    filters: Filters;
    options: Options;
}

const STATUS_TABS = [
    { key: null,        label: 'All' },
    { key: 'unclosed',  label: 'Not Closed' },
    { key: 'open',      label: 'Open' },
    { key: 'responded', label: 'Responded' },
    { key: 'overdue',   label: 'Overdue' },
    { key: 'closed',    label: 'Closed' },
] as const;

const th: React.CSSProperties = { padding: '9px 10px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', whiteSpace: 'nowrap', borderBottom: '1px solid #e2e8f0' };
const td: React.CSSProperties = { padding: '10px', fontSize: '12.5px', color: '#334155', borderBottom: '1px solid #f1f5f9', verticalAlign: 'top' };

const STATUS_DOT: Record<string, string> = { Open: '#f59e0b', Responded: '#2563eb', Overdue: '#d03b3b', Closed: '#0ca30c' };

function clip(text: string, n = 140) {
    return text.length > n ? text.slice(0, n - 1) + '…' : text;
}

export default function ContractorFindings({ stats, findings, ncr_status, filters, options }: Props) {
    const query = filterQuery(filters);
    const statusQuery: Record<string, string> = ncr_status ? { ncr_status } : {};
    const download = (report: string, label: string) => (
        <a href={withQuery(route('contractor-performance.export'), { ...query, ...statusQuery, report })}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '8px 13px', borderRadius: '8px', border: '1px solid #ddd6fe', background: '#fff', color: '#6d28d9', fontWeight: 700, fontSize: '12.5px', textDecoration: 'none', whiteSpace: 'nowrap' }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {label}
        </a>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Corrective Actions — Contractor Performance" />

            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', marginBottom: '14px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>Contractor Performance</h1>
                    <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>Deficiencies recorded against contractors, and the corrective actions tracked to closure.</p>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    {download('noncompliance', 'Non-Compliance Report')}
                    {download('corrective', 'Corrective Action Monitoring Report')}
                </div>
            </div>

            <SectionTabs active="findings" filters={filters} />

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '12px', marginBottom: '18px' }}>
                <Stat value={stats.total} label="Records" />
                <Stat value={stats.open} label="Open" hint="Awaiting the contractor’s response." />
                <Stat value={stats.responded} label="Responded" hint="The contractor answered; awaiting PMD verification and closure." />
                <Stat value={stats.overdue} label="Overdue" hint="Not closed by the target date." />
                <Stat value={stats.closed} label="Closed" />
                <Stat value={stats.avg_days_to_close ?? '—'} label="Avg Days to Close" />
            </div>

            <FilterPanel filters={filters} options={options} routeName="contractor-performance.findings" extraQuery={statusQuery} />

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', overflow: 'hidden' }}>
                <div style={{ padding: '10px 12px', borderBottom: '1px solid #e2e8f0', display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    {STATUS_TABS.map(t => {
                        const on = (ncr_status ?? null) === t.key;
                        return (
                            <button key={t.label} type="button"
                                onClick={() => router.get(route('contractor-performance.findings'), { ...query, ...(t.key ? { ncr_status: t.key } : {}) }, { preserveScroll: true })}
                                style={{ padding: '6px 12px', borderRadius: '999px', border: `1px solid ${on ? '#0f172a' : '#e2e8f0'}`, background: on ? '#0f172a' : '#fff', color: on ? '#fff' : '#475569', fontSize: '12px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit' }}>
                                {t.label}
                            </button>
                        );
                    })}
                </div>
                {findings.length === 0 ? (
                    <div style={{ padding: '44px 24px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>No non-compliance records match.</div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead style={{ background: '#f8fafc' }}>
                                <tr>{['NCR No.', 'Contractor', 'Project', 'Deficiency', 'Corrective Action', 'Identified', 'Target', 'Status', 'Days'].map(h => <th key={h} style={th}>{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {findings.map(f => {
                                    const label = f.overdue ? 'Overdue' : f.status_label;
                                    return (
                                        <tr key={f.id} onClick={() => router.visit(route('projects.hub.ncr', f.project_id))} style={{ cursor: 'pointer' }}
                                            onMouseEnter={e => (e.currentTarget.style.background = '#faf5ff')} onMouseLeave={e => (e.currentTarget.style.background = '')}>
                                            <td style={{ ...td, fontWeight: 800, color: '#0f172a', whiteSpace: 'nowrap' }}>{f.control_no}</td>
                                            <td style={td}>{f.contractor_name}</td>
                                            <td style={td}><div style={{ fontWeight: 700 }}>{f.project_no}</div><div style={{ color: '#64748b', fontSize: '11.5px' }}>{f.project_title}</div></td>
                                            <td style={{ ...td, maxWidth: '260px' }} title={f.deficiency}>{f.category_label && <div style={{ fontSize: '11px', color: '#64748b' }}>{f.category_label}</div>}{clip(f.deficiency)}</td>
                                            <td style={{ ...td, maxWidth: '260px' }} title={f.corrective_action}>{clip(f.corrective_action)}</td>
                                            <td style={{ ...td, whiteSpace: 'nowrap' }}>{f.date_identified}</td>
                                            <td style={{ ...td, whiteSpace: 'nowrap', color: f.overdue ? '#b91c1c' : '#334155', fontWeight: f.overdue ? 700 : 400 }}>{f.target_date ?? '—'}</td>
                                            <td style={{ ...td, whiteSpace: 'nowrap' }}>
                                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontWeight: 700, color: '#0f172a' }}>
                                                    <span aria-hidden style={{ width: '8px', height: '8px', borderRadius: '999px', background: STATUS_DOT[label] ?? '#94a3b8' }} />{label}
                                                </span>
                                            </td>
                                            <td style={{ ...td, textAlign: 'right' }}>{f.days_open}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
