import { router } from '@inertiajs/react';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────

export interface Filters {
    period: 'all' | 'month' | 'quarter' | 'year' | 'custom';
    from: string | null;
    to: string | null;
    period_type: string | null;
    contractor: string;
    project: string;
    department: string | null;
    category: string | null;
    status: string | null;
    project_type: string | null;
    rating: string | null;
    evaluation: 'evaluated' | 'pending' | null;
    basis: 'simple' | 'value';
    window_from: string | null;
    window_to: string | null;
}

export interface Options {
    departments: string[];
    categories: string[];
    statuses: { value: string; label: string }[];
    period_types: { value: string; label: string }[];
    ratings: string[];
}

export interface Flag { type: 'low_rating' | 'recurring' | 'overdue'; message: string }

export interface ContractorRow {
    key: string;
    name: string;
    supplier: { id: number; company: string; email: string | null; accredited: boolean; active: boolean } | null;
    projects: number;
    evaluated: number;
    pending: number;
    evaluations: number;
    contract_value: number;
    score: number | null;
    simple_score: number | null;
    weighted_score: number | null;
    classification: string | null;
    action: string | null;
    category_averages: Record<string, number | null>;
    flags: Flag[];
    last_evaluated: string | null;
    findings_open: number;
    findings_overdue: number;
    findings_total: number;
    rank: number | null;
}

/** The query string a filter set maps to — blanks and defaults left out. */
export function filterQuery(filters: Partial<Filters>): Record<string, string> {
    const query: Record<string, string> = {};
    const keys: (keyof Filters)[] = ['period', 'from', 'to', 'period_type', 'contractor', 'project', 'department', 'category', 'status', 'project_type', 'rating', 'evaluation', 'basis'];
    for (const key of keys) {
        const value = filters[key];
        if (value === null || value === undefined || value === '') continue;
        if ((key === 'period' && value === 'all') || (key === 'basis' && value === 'simple')) continue;
        if ((key === 'from' || key === 'to') && filters.period !== 'custom') continue;
        query[key] = String(value);
    }
    return query;
}

export function withQuery(url: string, query: Record<string, string>): string {
    const qs = new URLSearchParams(query).toString();
    return qs ? `${url}?${qs}` : url;
}

// ── Display helpers ────────────────────────────────────────────────────────

export const fmtScore = (n: number | null | undefined) => (n === null || n === undefined ? '—' : Number(n).toFixed(2));

export const peso = (n: number) => `PhP ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Rating bands as status: a coloured dot beside the label, never colour alone. */
const RATING_DOT: Record<string, string> = {
    'Excellent': '#0ca30c',
    'Very Good': '#0ca30c',
    'Satisfactory': '#94a3b8',
    'Needs Improvement': '#ec835a',
    'Unsatisfactory': '#d03b3b',
};

export function RatingBadge({ label }: { label: string | null }) {
    if (!label) return <span style={{ fontSize: '12px', color: '#94a3b8' }}>Not Yet Evaluated</span>;
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap' }}>
            <span aria-hidden style={{ width: '8px', height: '8px', borderRadius: '999px', background: RATING_DOT[label] ?? '#94a3b8' }} />
            {label}
        </span>
    );
}

/**
 * A category's average as a share of its weight, on one sequential blue ramp
 * (lighter = lower). The number is always printed, in ink, so the shade only
 * reinforces it.
 */
export function shareCell(value: number | null): { background: string; color: string } {
    if (value === null) return { background: '#f8fafc', color: '#94a3b8' };
    if (value >= 90) return { background: '#184f95', color: '#ffffff' };
    if (value >= 80) return { background: '#2a78d6', color: '#ffffff' };
    if (value >= 70) return { background: '#6da7ec', color: '#0f172a' };
    if (value >= 60) return { background: '#9ec5f4', color: '#0f172a' };
    return { background: '#cde2fb', color: '#0f172a' };
}

export function Stat({ value, label, hint }: { value: number | string; label: string; hint?: string }) {
    return (
        <div title={hint} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '10px', padding: '14px 16px' }}>
            <div style={{ fontSize: '26px', fontWeight: 900, color: '#0f172a', lineHeight: 1.1 }}>{value}</div>
            <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginTop: '3px' }}>{label}</div>
        </div>
    );
}

/** Switches between the ranking and the corrective-action monitoring, keeping the filters. */
export function SectionTabs({ active, filters }: { active: 'ranking' | 'findings'; filters: Filters }) {
    const query = filterQuery(filters);
    const tabs = [
        { key: 'ranking',  label: 'Ranking and Comparison',            href: withQuery(route('contractor-performance.index'), query) },
        { key: 'findings', label: 'Non-Compliance and Corrective Actions', href: withQuery(route('contractor-performance.findings'), query) },
    ] as const;

    return (
        <div role="tablist" style={{ display: 'flex', gap: '4px', borderBottom: '1px solid #e2e8f0', marginBottom: '18px' }}>
            {tabs.map(t => {
                const on = t.key === active;
                return (
                    <button key={t.key} type="button" role="tab" aria-selected={on} onClick={() => router.visit(t.href)}
                        style={{ padding: '10px 14px', border: 'none', background: 'none', borderBottom: `2px solid ${on ? '#7c3aed' : 'transparent'}`, marginBottom: '-1px', fontSize: '13px', fontWeight: on ? 800 : 600, color: on ? '#6d28d9' : '#64748b', cursor: 'pointer', fontFamily: 'inherit' }}>
                        {t.label}
                    </button>
                );
            })}
        </div>
    );
}

const input: React.CSSProperties = {
    padding: '7px 10px', borderRadius: '8px', border: '1.5px solid #e5e7eb', fontSize: '12.5px',
    outline: 'none', fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box', width: '100%', background: '#fff',
};

function L({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label style={{ display: 'block', minWidth: 0 }}>
            <span style={{ display: 'block', fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.3px', marginBottom: '4px' }}>{label}</span>
            {children}
        </label>
    );
}

/**
 * The framework §8 filters, in one panel. Applying reloads the page with the
 * filters in the URL, so a filtered view can be bookmarked or shared.
 */
export function FilterPanel({ filters, options, routeName, extraQuery = {} }: {
    filters: Filters;
    options: Options;
    routeName: string;
    extraQuery?: Record<string, string>;
}) {
    const [f, setF] = useState<Filters>(filters);
    const set = <K extends keyof Filters>(key: K) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setF(prev => ({ ...prev, [key]: e.target.value === '' ? null : e.target.value } as Filters));

    const apply = (next: Filters) => router.get(route(routeName), { ...extraQuery, ...filterQuery(next) }, { preserveScroll: true });

    return (
        <form onSubmit={e => { e.preventDefault(); apply(f); }}
            style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '16px', marginBottom: '18px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: '12px' }}>
                <L label="Evaluation Period">
                    <select style={input} value={f.period} onChange={set('period')}>
                        <option value="all">All time</option>
                        <option value="month">This month</option>
                        <option value="quarter">This quarter</option>
                        <option value="year">This year</option>
                        <option value="custom">Custom range…</option>
                    </select>
                </L>
                {f.period === 'custom' && <>
                    <L label="From"><input type="date" style={input} value={f.from ?? ''} onChange={set('from')} /></L>
                    <L label="To"><input type="date" style={input} value={f.to ?? ''} onChange={set('to')} /></L>
                </>}
                <L label="Evaluation Type">
                    <select style={input} value={f.period_type ?? ''} onChange={set('period_type')}>
                        <option value="">Any</option>
                        {options.period_types.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                    </select>
                </L>
                <L label="Contractor"><input style={input} value={f.contractor ?? ''} onChange={set('contractor')} placeholder="Name contains…" /></L>
                <L label="Project"><input style={input} value={f.project ?? ''} onChange={set('project')} placeholder="Title or project no." /></L>
                <L label="Project Owner / Department">
                    <select style={input} value={f.department ?? ''} onChange={set('department')}>
                        <option value="">All</option>
                        {options.departments.map(d => <option key={d} value={d}>{d}</option>)}
                    </select>
                </L>
                <L label="Project Category">
                    <select style={input} value={f.category ?? ''} onChange={set('category')}>
                        <option value="">All</option>
                        {options.categories.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </L>
                <L label="Project Status">
                    <select style={input} value={f.status ?? ''} onChange={set('status')}>
                        <option value="">All</option>
                        {options.statuses.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </L>
                <L label="Project Type">
                    <select style={input} value={f.project_type ?? ''} onChange={set('project_type')}>
                        <option value="">Major and Minor</option>
                        <option value="major">Major</option>
                        <option value="minor">Minor</option>
                    </select>
                </L>
                <L label="Performance Rating">
                    <select style={input} value={f.rating ?? ''} onChange={set('rating')}>
                        <option value="">All</option>
                        {options.ratings.map(r => <option key={r} value={r}>{r}</option>)}
                        <option value="unrated">Not Yet Evaluated</option>
                    </select>
                </L>
                <L label="Evaluation Status">
                    <select style={input} value={f.evaluation ?? ''} onChange={set('evaluation')}>
                        <option value="">All projects</option>
                        <option value="evaluated">Evaluated projects</option>
                        <option value="pending">Not yet evaluated</option>
                    </select>
                </L>
                <L label="Overall Score">
                    <select style={input} value={f.basis} onChange={set('basis')}>
                        <option value="simple">Average of projects</option>
                        <option value="value">Weighted by contract value</option>
                    </select>
                </L>
            </div>
            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '14px' }}>
                <button type="button"
                    onClick={() => { const reset = { ...filters, period: 'all', from: null, to: null, period_type: null, contractor: '', project: '', department: null, category: null, status: null, project_type: null, rating: null, evaluation: null, basis: 'simple' } as Filters; setF(reset); apply(reset); }}
                    style={{ padding: '8px 14px', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#fff', color: '#475569', fontWeight: 700, fontSize: '12.5px', cursor: 'pointer', fontFamily: 'inherit' }}>
                    Reset
                </button>
                <button type="submit"
                    style={{ padding: '8px 16px', borderRadius: '8px', border: 'none', background: '#7c3aed', color: '#fff', fontWeight: 700, fontSize: '12.5px', cursor: 'pointer', fontFamily: 'inherit' }}>
                    Apply Filters
                </button>
            </div>
        </form>
    );
}
