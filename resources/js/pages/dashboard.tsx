import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useEffect, useRef } from 'react';

// ── Stat Card ──────────────────────────────────────────────────────────────
interface StatCardProps {
    label: string;
    value: string;
    sub: string;
    subColor?: string;
    icon: React.ReactNode;
}

function StatCard({ label, value, sub, subColor = '#6b7280', icon }: StatCardProps) {
    return (
        <div style={{
            background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
            padding: '18px 20px', position: 'relative', overflow: 'hidden',
            flex: 1, transition: 'transform 0.18s, box-shadow 0.18s',
        }}
            onMouseEnter={e => {
                (e.currentTarget as HTMLDivElement).style.transform = 'translateY(-2px)';
                (e.currentTarget as HTMLDivElement).style.boxShadow = '0 6px 20px rgba(0,0,0,0.07)';
            }}
            onMouseLeave={e => {
                (e.currentTarget as HTMLDivElement).style.transform = 'none';
                (e.currentTarget as HTMLDivElement).style.boxShadow = 'none';
            }}
        >
            {/* ghost icon top-right */}
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
        </div>
    );
}

// ── Quick Link ─────────────────────────────────────────────────────────────
interface QuickLinkProps {
    label: string;
    href: string;
    icon: React.ReactNode;
}

function QuickLink({ label, href, icon }: QuickLinkProps) {
    return (
        <a href={href} style={{
            display: 'flex', flexDirection: 'column', alignItems: 'center',
            justifyContent: 'center', gap: '10px', padding: '20px 12px',
            borderRadius: '8px', border: '1.5px dashed #cbd5e1',
            background: '#fff', textDecoration: 'none', color: '#475569',
            fontSize: '12.5px', fontWeight: 600, transition: 'all 0.15s',
        }}
            onMouseEnter={e => {
                (e.currentTarget as HTMLAnchorElement).style.borderColor = '#2563eb';
                (e.currentTarget as HTMLAnchorElement).style.color = '#2563eb';
                (e.currentTarget as HTMLAnchorElement).style.background = '#eff6ff';
            }}
            onMouseLeave={e => {
                (e.currentTarget as HTMLAnchorElement).style.borderColor = '#cbd5e1';
                (e.currentTarget as HTMLAnchorElement).style.color = '#475569';
                (e.currentTarget as HTMLAnchorElement).style.background = '#fff';
            }}
        >
            <span style={{ color: '#64748b' }}>{icon}</span>
            {label}
        </a>
    );
}

// ── Mini Progress ──────────────────────────────────────────────────────────
function MiniProgress({ pct, color }: { pct: number; color: string }) {
    return (
        <div style={{ height: '5px', borderRadius: '99px', background: '#f1f5f9', marginTop: '6px' }}>
            <div style={{ height: '100%', width: `${pct}%`, borderRadius: '99px', background: color }} />
        </div>
    );
}

// ── Bar Chart (canvas) ─────────────────────────────────────────────────────
function BarChart() {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        // Dynamically load Chart.js from CDN if not available
        const draw = () => {
            const Chart = (window as unknown as { Chart: new (...args: unknown[]) => unknown }).Chart;
            if (!Chart) return;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Planning', 'Review', 'Approval', 'NTP', 'Ongoing', 'Hold'],
                    datasets: [{
                        label: 'Projects',
                        data: [12, 19, 8, 5, 24, 7],
                        backgroundColor: ['#e2e8f0', '#fef9c3', '#dbeafe', '#bae6fd', '#d1fae5', '#ffedd5'],
                        borderColor:     ['#94a3b8', '#fde047', '#93c5fd', '#7dd3fc', '#6ee7b7', '#fdba74'],
                        borderWidth: 1,
                        borderRadius: 5,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 11 } } },
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
    }, []);

    return <canvas ref={canvasRef} style={{ width: '100%', height: '100%' }} />;
}

// ── Dashboard ──────────────────────────────────────────────────────────────
export default function Dashboard() {
    return (
        <AuthenticatedLayout>
            <div style={{ fontFamily: "'Inter', sans-serif" }}>

                {/* Page title */}
                <h1 style={{ fontSize: '22px', fontWeight: 800, color: '#0f172a', margin: '0 0 18px', letterSpacing: '-0.4px' }}>
                    Dashboard
                </h1>

                {/* Quick Launchpad */}
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ fontSize: '13px', fontWeight: 600, color: '#374151', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <span style={{ color: '#f59e0b' }}>⚡</span> Quick Launchpad
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr) 2fr', gap: '10px' }}>
                        <QuickLink href="/requests/create" label="New Request" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                        } />
                        <QuickLink href="/projects/create" label="Create Project" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                            </svg>
                        } />
                        <QuickLink href="/reports/export" label="Export Report" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        } />
                        <QuickLink href="/users/assign" label="Assign PM" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        } />

                        {/* KPI Card */}
                        <div style={{
                            background: '#f59e0b', borderRadius: '8px', padding: '16px 20px',
                            color: '#fff', position: 'relative', overflow: 'hidden',
                        }}>
                            <div style={{ fontSize: '10px', fontWeight: 700, color: 'rgba(0,0,0,0.45)', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '4px' }}>
                                Project Completion KPI
                            </div>
                            <div style={{ fontSize: '2.2rem', fontWeight: 800, lineHeight: 1, marginBottom: '2px' }}>90%</div>
                            <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.8)' }}>
                                Actual: <span style={{ color: '#dc2626', fontWeight: 700 }}>68%</span>
                            </div>
                            <div style={{ position: 'absolute', right: '-8px', top: '-8px', opacity: 0.06, fontSize: '6rem' }}>💼</div>
                        </div>
                    </div>
                </div>

                {/* Stat Cards */}
                <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
                    <StatCard label="Active Projects" value="24" sub="↑ 12% vs last month" subColor="#16a34a"
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>} />
                    <StatCard label="Critical / Delayed" value="07" sub="Requires Attention" subColor="#dc2626"
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>} />
                    <StatCard label="About to Lapse" value="03" sub="Due within 7 days" subColor="#d97706"
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>} />
                    <StatCard label="Pending Requests" value="12" sub="In Review Pipeline" subColor="#2563eb"
                        icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>} />
                </div>

                {/* Main grid */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: '16px' }}>

                    {/* Left */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>

                        {/* Chart */}
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>Project Status Distribution</span>
                                <button style={{ padding: '4px 10px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', cursor: 'pointer', color: '#6b7280' }}>···</button>
                            </div>
                            <div style={{ padding: '16px 18px', height: '260px' }}>
                                <BarChart />
                            </div>
                        </div>

                        {/* Requests table */}
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>Latest Project Requests</span>
                                <a href="/requests" style={{ fontSize: '12.5px', fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}>View All</a>
                            </div>
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                                <thead>
                                    <tr style={{ background: '#f8fafc' }}>
                                        {['Ref #', 'Project Title', 'Requested By', 'Date', 'Action'].map(h => (
                                            <th key={h} style={{
                                                padding: '9px 16px', textAlign: 'left', fontSize: '11px',
                                                fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase',
                                                letterSpacing: '0.5px', borderBottom: '1px solid #f3f4f6',
                                            }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {[
                                        { ref: 'REQ-4421', title: 'Warehouse Cold Storage Extension', by: 'Logistics Dept', date: 'Mar 10, 2026' },
                                        { ref: 'REQ-4419', title: 'Server Room Fire Suppressor', by: 'IT Operations', date: 'Mar 08, 2026' },
                                    ].map((row, i) => (
                                        <tr key={i} style={{ borderBottom: '1px solid #f8fafc' }}
                                            onMouseEnter={e => (e.currentTarget as HTMLTableRowElement).style.background = '#f8fafc'}
                                            onMouseLeave={e => (e.currentTarget as HTMLTableRowElement).style.background = 'transparent'}
                                        >
                                            <td style={{ padding: '11px 16px', fontWeight: 700, color: '#0f172a' }}>{row.ref}</td>
                                            <td style={{ padding: '11px 16px', color: '#374151' }}>{row.title}</td>
                                            <td style={{ padding: '11px 16px', color: '#6b7280' }}>{row.by}</td>
                                            <td style={{ padding: '11px 16px', color: '#6b7280', whiteSpace: 'nowrap' }}>{row.date}</td>
                                            <td style={{ padding: '11px 16px' }}>
                                                <button style={{
                                                    padding: '4px 14px', borderRadius: '6px', fontSize: '12px', fontWeight: 600,
                                                    border: '1px solid #bfdbfe', background: '#fff', color: '#2563eb', cursor: 'pointer',
                                                }}>Review</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Right */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>

                        {/* Critical projects */}
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                            <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6' }}>
                                <span style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a' }}>Critical Projects (Delay Alerts)</span>
                            </div>
                            {[
                                { name: 'Main Gate Automation', note: 'Delayed by 14 days - Material Shortage', badge: 'Critical', badgeBg: '#fef2f2', badgeColor: '#dc2626', pct: 85, barColor: '#ef4444' },
                                { name: 'Site Fence Repair', note: 'Awaiting Contractor Response', badge: 'Delayed', badgeBg: '#fffbeb', badgeColor: '#d97706', pct: 40, barColor: '#f59e0b' },
                            ].map((p, i) => (
                                <div key={i} style={{ padding: '12px 16px', borderBottom: '1px solid #f8fafc' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '3px' }}>
                                        <span style={{ fontSize: '12.5px', fontWeight: 600, color: '#0f172a' }}>{p.name}</span>
                                        <span style={{
                                            padding: '2px 8px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700,
                                            background: p.badgeBg, color: p.badgeColor,
                                        }}>{p.badge}</span>
                                    </div>
                                    <div style={{ fontSize: '11.5px', color: '#9ca3af', marginBottom: '2px' }}>{p.note}</div>
                                    <MiniProgress pct={p.pct} color={p.barColor} />
                                </div>
                            ))}
                        </div>

                        {/* Success rate */}
                        <div style={{
                            background: '#0f172a', borderRadius: '10px',
                            padding: '22px 20px', textAlign: 'center', color: '#fff',
                        }}>
                            <div style={{ fontSize: '2.4rem', fontWeight: 800, letterSpacing: '-1px', lineHeight: 1 }}>94%</div>
                            <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.45)', marginBottom: '16px', marginTop: '4px' }}>Project Success Rate</div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px', fontSize: '12px', color: 'rgba(255,255,255,0.55)' }}>
                                <span>Annual Goal</span><span>₱ 10M</span>
                            </div>
                            <div style={{ height: '4px', borderRadius: '99px', background: 'rgba(255,255,255,0.1)' }}>
                                <div style={{ height: '100%', width: '62%', borderRadius: '99px', background: '#2563eb' }} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}