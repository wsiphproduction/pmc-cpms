import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

interface Props {
    summary: {
        projects: number;
        reports: number;
        engineer_name: string;
    };
}

const inputStyle: React.CSSProperties = {
    padding: '8px 11px', borderRadius: '8px', border: '1.5px solid #e5e7eb',
    fontSize: '13px', outline: 'none', fontFamily: 'inherit', color: '#374151',
    boxSizing: 'border-box', width: '100%',
};

function Stat({ value, label }: { value: number; label: string }) {
    return (
        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '10px', padding: '12px 16px', minWidth: '120px' }}>
            <div style={{ fontSize: '24px', fontWeight: 900, color: '#0f172a', lineHeight: 1.1 }}>{value}</div>
            <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginTop: '2px' }}>{label}</div>
        </div>
    );
}

export default function ReportsIndex({ summary }: Props) {
    const [from, setFrom] = useState('');
    const [to, setTo]     = useState('');

    // Downloads are plain GETs so the browser handles the file itself.
    const href = (() => {
        const params = new URLSearchParams();
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        const query = params.toString();
        return route('reports.accomplishment') + (query ? `?${query}` : '');
    })();

    const rangeInvalid = !!from && !!to && to < from;
    const nothingToExport = summary.projects === 0;

    return (
        <AuthenticatedLayout>
            <Head title="Reports" />

            <div style={{ marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    Reports
                </h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>
                    Generate Excel reports from your project records.
                </p>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', maxWidth: '760px' }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '18px' }}>
                    My Accomplishment Report
                </div>

                <p style={{ fontSize: '13px', color: '#475569', lineHeight: 1.6, margin: '0 0 16px' }}>
                    An Excel workbook covering the projects you registered as{' '}
                    <strong style={{ color: '#0f172a' }}>{summary.engineer_name}</strong> and the weekly
                    progress reports filed against each — a project summary, every weekly report, and the
                    site checklist answers behind them.
                </p>

                <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', marginBottom: '18px' }}>
                    <Stat value={summary.projects} label="Projects" />
                    <Stat value={summary.reports} label="Weekly Reports" />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', maxWidth: '420px', marginBottom: '6px' }}>
                    <label style={{ display: 'block' }}>
                        <span style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>
                            Period From (optional)
                        </span>
                        <input type="date" value={from} max={to || undefined} onChange={e => setFrom(e.target.value)} style={inputStyle} />
                    </label>
                    <label style={{ display: 'block' }}>
                        <span style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>
                            Period To (optional)
                        </span>
                        <input type="date" value={to} min={from || undefined} onChange={e => setTo(e.target.value)} style={inputStyle} />
                    </label>
                </div>

                <div style={{ fontSize: '11.5px', color: '#64748b', marginBottom: '18px', lineHeight: 1.5 }}>
                    The period filters the weekly reports by submitted date. Leave both blank for all time —
                    your full project list is always included for context.
                </div>

                {rangeInvalid && (
                    <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>
                        “Period To” must be on or after “Period From”.
                    </div>
                )}

                {nothingToExport && (
                    <div style={{ padding: '10px 12px', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '7px', color: '#b45309', fontSize: '12.5px', fontWeight: 600, marginBottom: '14px' }}>
                        You haven’t registered any projects yet, so the report would come back empty.
                    </div>
                )}

                {rangeInvalid || nothingToExport ? (
                    <span
                        aria-disabled="true"
                        style={{
                            display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '9px 20px',
                            borderRadius: '8px', background: '#e2e8f0', color: '#94a3b8',
                            fontSize: '13px', fontWeight: 700, cursor: 'not-allowed',
                        }}
                    >
                        Download Excel
                    </span>
                ) : (
                    <a
                        href={href}
                        style={{
                            display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '9px 20px',
                            borderRadius: '8px', background: '#0f172a', color: '#fff',
                            fontSize: '13px', fontWeight: 700, textDecoration: 'none',
                        }}
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Download Excel
                    </a>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
