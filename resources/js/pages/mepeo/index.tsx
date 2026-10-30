import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import ApprovalTimeline from '@/components/ApprovalTimeline';
import { EMS_TONE, EmsReportRow, EmsStatusBadge, fmtDate } from '@/components/EmsReport';

type Row = EmsReportRow & { needs_me: boolean };

interface Props {
    reports: Row[];
    is_manager: boolean;
}

type TabKey = 'action' | 'signoff' | 'approved' | 'all';

const matches = (query: string, fields: (string | null | undefined)[]) =>
    fields.some(f => f?.toLowerCase().includes(query));

export default function MepeoIndex({ reports, is_manager }: Props) {
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;
    const [tab, setTab] = useState<TabKey>('action');
    const [search, setSearch] = useState('');

    const query = search.trim().toLowerCase();
    const searched = query === '' ? reports : reports.filter(r => matches(query, [
        r.control_no, r.project.project_no, r.project.title, r.location, r.contractor, r.prepared_by, r.mepeo_rep?.name,
    ]));

    const byTab: Record<TabKey, Row[]> = {
        action:   searched.filter(r => r.needs_me),
        signoff:  searched.filter(r => r.status === 'for_approval' && !r.needs_me),
        approved: searched.filter(r => r.status === 'approved'),
        all:      searched,
    };

    const tabs: { key: TabKey; label: string }[] = [
        { key: 'action',   label: is_manager ? 'Needs Your Action' : 'To Fill Out' },
        { key: 'signoff',  label: 'In Sign-off' },
        { key: 'approved', label: 'Completed' },
        { key: 'all',      label: 'All' },
    ];

    const rows = byTab[tab];

    return (
        <AuthenticatedLayout>
            <Head title="EMS Survey Reports" />

            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '16px', flexWrap: 'wrap', marginBottom: '18px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 4px' }}>EMS Survey Reports</h1>
                    <p style={{ fontSize: '13px', color: '#64748b', margin: 0, maxWidth: '680px', lineHeight: 1.55 }}>
                        Hi <strong style={{ color: '#334155' }}>{auth.user.name}</strong>.{' '}
                        {is_manager
                            ? 'Reports the MEPEO representatives submit come to you for approval; PMD then reviews and notes them.'
                            : 'Project engineers send you blank Environmental Impact Assessments to fill out. Submit each one to the MEPEO Manager when done.'}
                    </p>
                </div>
            </div>

            <div style={{ display: 'flex', gap: '10px', marginBottom: '16px', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    {tabs.map(t => {
                        const active = tab === t.key;
                        return (
                            <button key={t.key} type="button" onClick={() => setTab(t.key)}
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '7px', padding: '7px 14px', borderRadius: '8px', border: `1px solid ${active ? EMS_TONE : '#e2e8f0'}`, background: active ? EMS_TONE : '#fff', color: active ? '#fff' : '#475569', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                                {t.label}
                                <span style={{ padding: '1px 7px', borderRadius: '999px', background: active ? 'rgba(255,255,255,0.25)' : '#f1f5f9', color: active ? '#fff' : '#64748b', fontSize: '11px', fontWeight: 700 }}>{byTab[t.key].length}</span>
                            </button>
                        );
                    })}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#fff', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '7px 12px', flex: '1 1 220px', maxWidth: '340px' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search control no., project, contractor…"
                        style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }}
                    />
                </div>
            </div>

            {rows.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '56px 24px', border: '1px dashed #e2e8f0', borderRadius: '12px', color: '#94a3b8', background: '#fff' }}>
                    <div style={{ fontSize: '30px', marginBottom: '10px' }}>🌿</div>
                    <div style={{ fontWeight: 700, color: '#64748b', marginBottom: '4px' }}>
                        {query ? 'No matching reports' : tab === 'action' ? 'Nothing waiting on you' : 'No reports here yet'}
                    </div>
                    <div style={{ fontSize: '12.5px' }}>
                        {query ? `Nothing here matches “${search.trim()}”.` : 'You are notified, in the app and by email, when a report comes to you.'}
                    </div>
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {rows.map(r => (
                        <div key={r.id} style={{ background: '#fff', border: `1px solid ${r.needs_me ? '#bbf7d0' : '#e5e7eb'}`, borderLeft: `4px solid ${r.needs_me ? EMS_TONE : '#e5e7eb'}`, borderRadius: '12px', padding: '16px 20px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap' }}>
                                <div style={{ minWidth: 0, flex: '1 1 300px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '4px' }}>
                                        <span style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{r.control_no}</span>
                                        <EmsStatusBadge row={r} />
                                        {r.needs_me && <span style={{ padding: '2px 10px', borderRadius: '999px', background: '#fef3c7', color: '#92400e', fontSize: '11px', fontWeight: 700 }}>Awaiting You</span>}
                                    </div>
                                    <div style={{ fontSize: '13px', color: '#334155', fontWeight: 600 }}>{r.project.project_no} — {r.project.title}</div>
                                    <div style={{ fontSize: '12.5px', color: '#64748b', marginTop: '2px' }}>
                                        Requested {fmtDate(r.date_requested)}
                                        {r.prepared_by ? ` by ${r.prepared_by}` : ''}
                                        {r.location ? ` · ${r.location}` : ''}
                                        {is_manager && r.mepeo_rep ? ` · Representative: ${r.mepeo_rep.name}` : ''}
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                    <a href={r.print_url} target="_blank" rel="noreferrer" style={{ padding: '8px 14px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', color: '#374151', fontSize: '12.5px', fontWeight: 700, textDecoration: 'none' }}>
                                        Print
                                    </a>
                                    <Link href={route('mepeo.reports.show', r.id)} style={{ padding: '8px 18px', borderRadius: '7px', background: r.needs_me ? EMS_TONE : '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, textDecoration: 'none' }}>
                                        {r.needs_me ? (r.status === 'for_approval' ? 'Review & Sign' : 'Fill Out') : 'Open'}
                                    </Link>
                                </div>
                            </div>
                            {r.approvals.length > 0 && (
                                <div style={{ marginTop: '12px', paddingTop: '10px', borderTop: '1px solid #f1f5f9' }}>
                                    <ApprovalTimeline steps={r.approvals} compact />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
