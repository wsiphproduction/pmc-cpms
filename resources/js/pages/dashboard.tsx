import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface Stats {
    active_projects: number;
    critical_projects: number;
    about_to_lapse: number;
    pending_requests: number;
    completion_target: number;
    completion_actual: number;
    success_rate: number;
    budget_total: number;
    budget_paid: number;
}

interface StatusPoint {
    label: string;
    count: number;
}

interface LatestRequest {
    id: number;
    ref: string;
    title: string;
    requested_by: string;
    date: string;
    status: string;
}

interface CriticalProject {
    id: number;
    name: string;
    note: string;
    badge: string;
    progress: number;
}

interface DashboardProps {
    stats: Stats;
    status_distribution: StatusPoint[];
    latest_requests: LatestRequest[];
    critical_projects: CriticalProject[];
}

interface StatCardProps {
    label: string;
    value: string;
    sub: string;
    subColor?: string;
    icon: React.ReactNode;
    onClick?: () => void;
}

function clampPct(value: number) {
    return Math.max(0, Math.min(100, value || 0));
}

function formatNumber(value: number) {
    return new Intl.NumberFormat().format(value || 0);
}

function formatCurrency(value: number) {
    if (!value) return 'PHP 0';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
}

function StatCard({ label, value, sub, subColor = '#6b7280', icon, onClick }: StatCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                textAlign: 'left',
                background: '#fff',
                border: '1px solid #e5e7eb',
                borderRadius: '10px',
                padding: '18px 20px',
                position: 'relative',
                overflow: 'hidden',
                flex: 1,
                transition: 'transform 0.18s, box-shadow 0.18s',
                cursor: onClick ? 'pointer' : 'default',
                fontFamily: 'inherit',
            }}
            onMouseEnter={e => {
                e.currentTarget.style.transform = 'translateY(-2px)';
                e.currentTarget.style.boxShadow = '0 6px 20px rgba(0,0,0,0.07)';
            }}
            onMouseLeave={e => {
                e.currentTarget.style.transform = 'none';
                e.currentTarget.style.boxShadow = 'none';
            }}
        >
            <div style={{ position: 'absolute', right: '14px', top: '14px', opacity: 0.08, fontSize: '2rem', color: '#374151' }}>
                {icon}
            </div>
            <div style={{ fontSize: '10px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '6px' }}>
                {label}
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: '#0f172a', lineHeight: 1.1, marginBottom: '4px' }}>
                {value}
            </div>
            <div style={{ fontSize: '11.5px', fontWeight: 600, color: subColor }}>
                {sub}
            </div>
        </button>
    );
}

interface QuickLinkProps {
    label: string;
    href?: string;
    icon: React.ReactNode;
    onClick?: () => void;
}

function QuickLink({ label, href, icon, onClick }: QuickLinkProps) {
    const sharedStyle: React.CSSProperties = {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '10px',
        minHeight: '94px',
        padding: '18px 12px',
        borderRadius: '8px',
        border: '1.5px dashed #cbd5e1',
        background: '#fff',
        textDecoration: 'none',
        color: '#475569',
        fontSize: '12.5px',
        fontWeight: 600,
        transition: 'all 0.15s',
        cursor: 'pointer',
        fontFamily: 'inherit',
    };

    const hoverIn = (target: HTMLElement) => {
        target.style.borderColor = '#2563eb';
        target.style.color = '#2563eb';
        target.style.background = '#eff6ff';
    };
    const hoverOut = (target: HTMLElement) => {
        target.style.borderColor = '#cbd5e1';
        target.style.color = '#475569';
        target.style.background = '#fff';
    };

    const content = (
        <>
            <span style={{ color: 'currentColor' }}>{icon}</span>
            {label}
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                style={sharedStyle}
                onMouseEnter={e => hoverIn(e.currentTarget)}
                onMouseLeave={e => hoverOut(e.currentTarget)}
            >
                {content}
            </Link>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            style={sharedStyle}
            onMouseEnter={e => hoverIn(e.currentTarget)}
            onMouseLeave={e => hoverOut(e.currentTarget)}
        >
            {content}
        </button>
    );
}

function MiniProgress({ pct, color }: { pct: number; color: string }) {
    return (
        <div style={{ height: '5px', borderRadius: '99px', background: '#f1f5f9', marginTop: '6px', overflow: 'hidden' }}>
            <div style={{ height: '100%', width: `${clampPct(pct)}%`, borderRadius: '99px', background: color }} />
        </div>
    );
}

function WorkInProgressModal({ feature, onClose }: { feature: string; onClose: () => void }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '18px' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.42)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: '380px', background: '#fff', borderRadius: '12px', boxShadow: '0 24px 70px rgba(15,23,42,0.25)', overflow: 'hidden' }}>
                <div style={{ padding: '18px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a' }}>Currently Working</div>
                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '2px' }}>{feature}</div>
                    </div>
                    <button type="button" onClick={onClose} style={{ width: '30px', height: '30px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px' }}>
                    <p style={{ margin: '0 0 16px', fontSize: '13px', lineHeight: 1.6, color: '#475569' }}>
                        This feature is currently being worked on and is not available in the system yet.
                    </p>
                    <button type="button" onClick={onClose} style={{ width: '100%', padding: '9px 14px', border: 'none', borderRadius: '8px', background: '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: 'pointer' }}>
                        Got it
                    </button>
                </div>
            </div>
        </div>
    );
}

function BarChart({ points }: { points: StatusPoint[] }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return undefined;

        let chart: { destroy?: () => void } | null = null;
        let mounted = true;

        const draw = () => {
            const Chart = (window as unknown as { Chart?: new (...args: unknown[]) => { destroy?: () => void } }).Chart;
            const ctx = canvas.getContext('2d');
            if (!Chart || !ctx || !mounted) return;

            chart?.destroy?.();
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: points.map(point => point.label),
                    datasets: [{
                        label: 'Projects',
                        data: points.map(point => point.count),
                        backgroundColor: ['#e2e8f0', '#fef9c3', '#dbeafe', '#bae6fd', '#d1fae5', '#ffedd5', '#fee2e2', '#e0e7ff', '#f1f5f9', '#dcfce7', '#ede9fe', '#fae8ff'],
                        borderColor: ['#94a3b8', '#fde047', '#93c5fd', '#7dd3fc', '#6ee7b7', '#fdba74', '#fca5a5', '#a5b4fc', '#cbd5e1', '#86efac', '#c4b5fd', '#f0abfc'],
                        borderWidth: 1,
                        borderRadius: 5,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 11 }, precision: 0 } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    },
                },
            } as never);
        };

        if ((window as unknown as { Chart?: unknown }).Chart) {
            draw();
        } else {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = draw;
            document.head.appendChild(script);
        }

        return () => {
            mounted = false;
            chart?.destroy?.();
        };
    }, [points]);

    return <canvas ref={canvasRef} style={{ width: '100%', height: '100%' }} />;
}

function EmptyState({ text }: { text: string }) {
    return (
        <div style={{ padding: '28px 18px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
            {text}
        </div>
    );
}

export default function Dashboard({
    stats,
    status_distribution,
    latest_requests,
    critical_projects,
}: DashboardProps) {
    const [wipFeature, setWipFeature] = useState<string | null>(null);
    const budgetPct = stats.budget_total > 0 ? Math.round((stats.budget_paid / stats.budget_total) * 100) : 0;
    const statusPoints = useMemo(
        () => status_distribution.filter(point => point.count > 0),
        [status_distribution],
    );
    const chartPoints = statusPoints.length ? statusPoints : [{ label: 'No projects', count: 0 }];

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            {wipFeature && <WorkInProgressModal feature={wipFeature} onClose={() => setWipFeature(null)} />}

            <div style={{ fontFamily: "'Inter', sans-serif" }}>
                <h1 style={{ fontSize: '22px', fontWeight: 800, color: '#0f172a', margin: '0 0 18px', letterSpacing: '-0.4px' }}>
                    Dashboard
                </h1>

                <div style={{ marginBottom: '20px' }}>
                    <div style={{ fontSize: '13px', fontWeight: 600, color: '#374151', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <span style={{ color: '#f59e0b' }}>⚡</span> Quick Launchpad
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(120px, 1fr)) 2fr', gap: '10px' }}>
                        <QuickLink href={route('requests.create')} label="New Request" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                        } />
                        <QuickLink href={route('projects.create')} label="Create Project" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                            </svg>
                        } />
                        <QuickLink onClick={() => setWipFeature('Export Report')} label="Export Report" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        } />
                        <QuickLink onClick={() => setWipFeature('Assign PM')} label="Assign PM" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        } />

                        <button
                            type="button"
                            onClick={() => router.visit(route('projects.index'))}
                            style={{
                                textAlign: 'left',
                                background: '#f59e0b',
                                border: 'none',
                                borderRadius: '8px',
                                padding: '16px 20px',
                                color: '#fff',
                                position: 'relative',
                                overflow: 'hidden',
                                cursor: 'pointer',
                                fontFamily: 'inherit',
                            }}
                        >
                            <div style={{ fontSize: '10px', fontWeight: 700, color: 'rgba(0,0,0,0.45)', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '4px' }}>
                                Project Completion KPI
                            </div>
                            <div style={{ fontSize: '2.2rem', fontWeight: 800, lineHeight: 1, marginBottom: '2px' }}>{stats.completion_target}%</div>
                            <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.85)' }}>
                                Actual: <span style={{ color: stats.completion_actual >= stats.completion_target ? '#14532d' : '#dc2626', fontWeight: 800 }}>{stats.completion_actual}%</span>
                            </div>
                            <div style={{ position: 'absolute', right: '-8px', top: '-8px', opacity: 0.08, fontSize: '5.6rem' }}>
                                <svg width="86" height="86" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M8 7V5h8v2"/></svg>
                            </div>
                        </button>
                    </div>
                </div>

                <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
                    <StatCard label="Active Projects" value={formatNumber(stats.active_projects)} sub="Open project records" subColor="#16a34a" onClick={() => router.visit(route('projects.index'))}
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>} />
                    <StatCard label="Critical / Delayed" value={formatNumber(stats.critical_projects)} sub="Needs attention" subColor="#dc2626" onClick={() => router.visit(route('projects.index'), { data: { status: 'ON_HOLD' } })}
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>} />
                    <StatCard label="About to Lapse" value={formatNumber(stats.about_to_lapse)} sub="Due within 7 days" subColor="#d97706" onClick={() => router.visit(route('projects.index'))}
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>} />
                    <StatCard label="Pending Requests" value={formatNumber(stats.pending_requests)} sub="In review pipeline" subColor="#2563eb" onClick={() => router.visit(route('requests.index'), { data: { status: ['pending'] } })}
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>} />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: '16px' }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>Project Status Distribution</span>
                                <button type="button" onClick={() => setWipFeature('Chart Options')} style={{ padding: '4px 10px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', cursor: 'pointer', color: '#6b7280' }}>...</button>
                            </div>
                            <div style={{ padding: '16px 18px', height: '260px' }}>
                                <BarChart points={chartPoints} />
                            </div>
                        </div>

                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>Latest Project Requests</span>
                                <Link href={route('requests.index')} style={{ fontSize: '12.5px', fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}>View All</Link>
                            </div>
                            {latest_requests.length === 0 ? (
                                <EmptyState text="No project requests yet." />
                            ) : (
                                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                                    <thead>
                                        <tr style={{ background: '#f8fafc' }}>
                                            {['Ref #', 'Project Title', 'Requested By', 'Date', 'Action'].map(h => (
                                                <th key={h} style={{
                                                    padding: '9px 16px',
                                                    textAlign: 'left',
                                                    fontSize: '11px',
                                                    fontWeight: 700,
                                                    color: '#9ca3af',
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '0.5px',
                                                    borderBottom: '1px solid #f3f4f6',
                                                }}>{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {latest_requests.map(row => (
                                            <tr key={row.id} style={{ borderBottom: '1px solid #f8fafc' }}
                                                onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                            >
                                                <td style={{ padding: '11px 16px', fontWeight: 700, color: '#0f172a' }}>{row.ref}</td>
                                                <td style={{ padding: '11px 16px', color: '#374151' }}>{row.title}</td>
                                                <td style={{ padding: '11px 16px', color: '#6b7280' }}>{row.requested_by}</td>
                                                <td style={{ padding: '11px 16px', color: '#6b7280', whiteSpace: 'nowrap' }}>{row.date}</td>
                                                <td style={{ padding: '11px 16px' }}>
                                                    <button type="button" onClick={() => router.visit(route('requests.show', row.id))} style={{
                                                        padding: '4px 14px',
                                                        borderRadius: '6px',
                                                        fontSize: '12px',
                                                        fontWeight: 600,
                                                        border: '1px solid #bfdbfe',
                                                        background: '#fff',
                                                        color: '#2563eb',
                                                        cursor: 'pointer',
                                                    }}>Review</button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6' }}>
                                <span style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a' }}>Critical Projects</span>
                            </div>
                            {critical_projects.length === 0 ? (
                                <EmptyState text="No critical or soon-to-lapse projects." />
                            ) : critical_projects.map(project => {
                                const isCritical = project.badge === 'Critical';
                                const isHold = project.badge === 'On Hold';
                                const badgeBg = isCritical ? '#fef2f2' : isHold ? '#fffbeb' : '#eff6ff';
                                const badgeColor = isCritical ? '#dc2626' : isHold ? '#d97706' : '#2563eb';
                                const barColor = isCritical ? '#ef4444' : isHold ? '#f59e0b' : '#2563eb';

                                return (
                                    <button
                                        type="button"
                                        key={project.id}
                                        onClick={() => router.visit(route('projects.show', project.id))}
                                        style={{ display: 'block', width: '100%', textAlign: 'left', padding: '12px 16px', border: 'none', borderBottom: '1px solid #f8fafc', background: '#fff', cursor: 'pointer', fontFamily: 'inherit' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                        onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
                                    >
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '10px', marginBottom: '3px' }}>
                                            <span style={{ fontSize: '12.5px', fontWeight: 600, color: '#0f172a' }}>{project.name}</span>
                                            <span style={{ padding: '2px 8px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700, background: badgeBg, color: badgeColor, whiteSpace: 'nowrap' }}>{project.badge}</span>
                                        </div>
                                        <div style={{ fontSize: '11.5px', color: '#9ca3af', marginBottom: '2px' }}>{project.note}</div>
                                        <MiniProgress pct={project.progress} color={barColor} />
                                    </button>
                                );
                            })}
                        </div>

                        <div style={{ background: '#0f172a', borderRadius: '10px', padding: '22px 20px', textAlign: 'center', color: '#fff' }}>
                            <div style={{ fontSize: '2.4rem', fontWeight: 800, letterSpacing: '-1px', lineHeight: 1 }}>{stats.success_rate}%</div>
                            <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.45)', marginBottom: '16px', marginTop: '4px' }}>Project Success Rate</div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px', fontSize: '12px', color: 'rgba(255,255,255,0.55)' }}>
                                <span>Budget Paid</span><span>{formatCurrency(stats.budget_paid)}</span>
                            </div>
                            <div style={{ height: '4px', borderRadius: '99px', background: 'rgba(255,255,255,0.1)', overflow: 'hidden', marginBottom: '8px' }}>
                                <div style={{ height: '100%', width: `${clampPct(budgetPct)}%`, borderRadius: '99px', background: '#2563eb' }} />
                            </div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '11px', color: 'rgba(255,255,255,0.38)' }}>
                                <span>{clampPct(budgetPct)}% utilized</span>
                                <span>Total {formatCurrency(stats.budget_total)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
