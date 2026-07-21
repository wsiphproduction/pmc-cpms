import { useState } from 'react';
import { DataTable, Field, HubProject, HubShell, inputStyle, SubTag } from './Common';

interface FieldChange { field: string; old: string; new: string }
interface LogRow { date: string; time: string; user: string; action: string; module: string; ip: string; type: string; fields?: FieldChange[]; sub_project_no: string | null }

// Renders a "field: old → new" list for audit entries that carry field-level
// change detail (currently RFQ updates). Short single-line values render inline;
// long or multi-line values (e.g. line items) render as stacked blocks.
function ChangeDetail({ fields }: { fields?: FieldChange[] }) {
    if (!fields || fields.length === 0) return null;

    const oldPill: React.CSSProperties = { padding: '2px 7px', borderRadius: '4px', background: '#fef2f2', color: '#b91c1c', whiteSpace: 'pre-wrap', wordBreak: 'break-word' };
    const newPill: React.CSSProperties = { padding: '2px 7px', borderRadius: '4px', background: '#f0fdf4', color: '#15803d', whiteSpace: 'pre-wrap', wordBreak: 'break-word' };
    const empty = (v: string) => v.trim() === '';

    return (
        <div style={{ marginTop: '6px', display: 'flex', flexDirection: 'column', gap: '6px' }}>
            {fields.map((f, i) => {
                const isBlock = f.old.length > 55 || f.new.length > 55 || f.old.includes('\n') || f.new.includes('\n');
                return (
                    <div key={i} style={{ fontSize: '11.5px', color: '#64748b' }}>
                        <span style={{ fontWeight: 700, color: '#475569' }}>{f.field}:</span>
                        {isBlock ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '3px', marginTop: '3px' }}>
                                <div><span style={{ color: '#94a3b8', fontWeight: 600 }}>Old </span><span style={{ ...oldPill, textDecoration: empty(f.old) ? 'none' : 'line-through' }}>{empty(f.old) ? '—' : f.old}</span></div>
                                <div><span style={{ color: '#94a3b8', fontWeight: 600 }}>New </span><span style={newPill}>{empty(f.new) ? '—' : f.new}</span></div>
                            </div>
                        ) : (
                            <span style={{ display: 'inline-flex', flexWrap: 'wrap', alignItems: 'center', gap: '6px', marginLeft: '6px' }}>
                                <span style={{ ...oldPill, textDecoration: empty(f.old) ? 'none' : 'line-through' }}>{empty(f.old) ? '—' : f.old}</span>
                                <span style={{ color: '#94a3b8' }}>→</span>
                                <span style={newPill}>{empty(f.new) ? '—' : f.new}</span>
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

const MODULE_COLORS: Record<string, [string, string]> = {
    'RFQ':     ['#eff6ff', '#2563eb'],
    'NTP':     ['#f0fdf4', '#16a34a'],
    'Permit':  ['#faf5ff', '#7c3aed'],
    'VOF':     ['#fff7ed', '#c2410c'],
    'QPP':     ['#ecfdf5', '#059669'],
    'MTR':     ['#f0f9ff', '#0284c7'],
    'RFP':     ['#fffbeb', '#d97706'],
    'IOC':     ['#fdf4ff', '#a21caf'],
    'PSR':     ['#f0fdf4', '#15803d'],
    'Project': ['#f8fafc', '#475569'],
};

const TYPE_ICON: Record<string, [string, string, React.ReactNode]> = {
    create:  ['#f0fdf4', '#16a34a', <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>],
    finance: ['#fffbeb', '#d97706', <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>],
    upload:  ['#eff6ff', '#2563eb', <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>],
    update:  ['#f0fdf4', '#16a34a', <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>],
    delete:  ['#fef2f2', '#dc2626', <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>],
};

function TypeBadge({ type }: { type: string }) {
    const [bg, color, icon] = TYPE_ICON[type] ?? TYPE_ICON.update;
    return (
        <div style={{ width: '30px', height: '30px', borderRadius: '8px', background: bg, color, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
            {icon}
        </div>
    );
}

function ModuleBadge({ module }: { module: string }) {
    const [bg, color] = MODULE_COLORS[module] ?? ['#f1f5f9', '#475569'];
    return (
        <span style={{ padding: '2px 8px', borderRadius: '4px', background: bg, color, fontSize: '11px', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.4px' }}>
            {module}
        </span>
    );
}

export default function AuditTrailHub({ project: _, logs }: { project: HubProject; logs: LogRow[] }) {
    const [search, setSearch]             = useState('');
    const [fromDate, setFromDate]         = useState('');
    const [toDate, setToDate]             = useState('');
    const [typeFilter, setTypeFilter]     = useState('all');
    const [moduleFilter, setModuleFilter] = useState('all');
    const [page, setPage]                 = useState(1);

    const PER_PAGE = 15;
    const modules  = ['all', ...Array.from(new Set(logs.map(l => l.module))).sort()];
    const hasSubRows = logs.some(l => !!l.sub_project_no);

    const resetPage = () => setPage(1);

    const filtered = logs.filter(log => {
        if (search && !log.user.toLowerCase().includes(search.toLowerCase()) && !log.action.toLowerCase().includes(search.toLowerCase())) return false;
        if (typeFilter !== 'all' && log.type !== typeFilter) return false;
        if (moduleFilter !== 'all' && log.module !== moduleFilter) return false;
        if (fromDate && log.date < new Date(fromDate).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })) return false;
        if (toDate   && log.date > new Date(toDate).toLocaleDateString('en-US',   { month: 'short', day: '2-digit', year: 'numeric' })) return false;
        return true;
    });

    const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
    const safePage   = Math.min(page, totalPages);
    const paginated  = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

    const pageNums = (): (number | '…')[] => {
        if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1);
        if (safePage <= 4)   return [1, 2, 3, 4, 5, '…', totalPages];
        if (safePage >= totalPages - 3) return [1, '…', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        return [1, '…', safePage - 1, safePage, safePage + 1, '…', totalPages];
    };

    return (
        <HubShell>
            <h3 style={{ margin: '0 0 18px', fontSize: '16px', fontWeight: 800, color: '#0f172a' }}>Project System Logs & Audit Trail</h3>

            {/* Filters */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '16px', marginBottom: '20px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 0.8fr 0.8fr 1fr 1fr', gap: '12px', alignItems: 'end' }}>
                    <Field label="Search User / Action">
                        <input style={inputStyle} placeholder="Type to search..." value={search} onChange={e => { setSearch(e.target.value); resetPage(); }} />
                    </Field>
                    <Field label="From Date">
                        <input type="date" style={inputStyle} value={fromDate} onChange={e => { setFromDate(e.target.value); resetPage(); }} />
                    </Field>
                    <Field label="To Date">
                        <input type="date" style={inputStyle} value={toDate} onChange={e => { setToDate(e.target.value); resetPage(); }} />
                    </Field>
                    <Field label="Module">
                        <select style={inputStyle} value={moduleFilter} onChange={e => { setModuleFilter(e.target.value); resetPage(); }}>
                            {modules.map(m => <option key={m} value={m}>{m === 'all' ? 'All Modules' : m}</option>)}
                        </select>
                    </Field>
                    <Field label="Action Type">
                        <select style={inputStyle} value={typeFilter} onChange={e => { setTypeFilter(e.target.value); resetPage(); }}>
                            <option value="all">All Types</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="upload">Upload</option>
                            <option value="finance">Finance</option>
                            <option value="delete">Delete</option>
                        </select>
                    </Field>
                </div>
                {(search || fromDate || toDate || typeFilter !== 'all' || moduleFilter !== 'all') && (
                    <div style={{ marginTop: '10px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span style={{ fontSize: '12px', color: '#64748b' }}>Filters active</span>
                        <button type="button" onClick={() => { setSearch(''); setFromDate(''); setToDate(''); setTypeFilter('all'); setModuleFilter('all'); resetPage(); }}
                            style={{ fontSize: '11.5px', color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', fontWeight: 600 }}>
                            Clear all
                        </button>
                    </div>
                )}
            </div>

            {logs.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '60px 24px', color: '#94a3b8' }}>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.5" style={{ marginBottom: '12px' }}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <div style={{ fontWeight: 700, fontSize: '15px', color: '#64748b', marginBottom: '6px' }}>No activity recorded yet</div>
                    <div style={{ fontSize: '13px' }}>Actions taken inside this project will appear here automatically.</div>
                </div>
            ) : (
                <DataTable
                    headers={['Date & Time', 'User', ...(hasSubRows ? ['Project'] : []), 'Module', 'Activity', 'IP Address']}
                    rows={paginated.map(log => [
                        <div>
                            <div style={{ fontWeight: 700, fontSize: '12px', color: '#0f172a' }}>{log.date}</div>
                            <div style={{ fontSize: '11px', color: '#94a3b8' }}>{log.time}</div>
                        </div>,
                        <div style={{ fontWeight: 600, fontSize: '13px', color: '#334155' }}>{log.user}</div>,
                        ...(hasSubRows ? [<SubTag no={log.sub_project_no} />] : []),
                        <ModuleBadge module={log.module} />,
                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '10px' }}>
                            <TypeBadge type={log.type} />
                            <div>
                                <span style={{ fontSize: '13px', color: log.type === 'delete' ? '#dc2626' : '#334155' }}>{log.action}</span>
                                <ChangeDetail fields={log.fields} />
                            </div>
                        </div>,
                        <span style={{ fontSize: '12px', color: '#94a3b8', fontFamily: 'monospace' }}>{log.ip}</span>,
                    ])}
                    footer={
                        <div style={{ padding: '10px 14px', background: '#f8fafc', borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '12px', color: '#64748b' }}>
                            <span>
                                Showing <strong>{((safePage - 1) * PER_PAGE) + 1}–{Math.min(safePage * PER_PAGE, filtered.length)}</strong> of <strong>{filtered.length}</strong> activities
                                {filtered.length !== logs.length && <span style={{ color: '#94a3b8' }}> (filtered from {logs.length})</span>}
                            </span>
                            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                                <button type="button" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={safePage === 1}
                                    style={{ padding: '4px 10px', borderRadius: '5px', border: '1px solid #e2e8f0', background: '#fff', color: safePage === 1 ? '#cbd5e1' : '#374151', cursor: safePage === 1 ? 'default' : 'pointer', fontSize: '12px' }}>
                                    ‹ Prev
                                </button>
                                {pageNums().map((n, i) =>
                                    n === '…'
                                        ? <span key={`ellipsis-${i}`} style={{ padding: '4px 6px', color: '#94a3b8', fontSize: '12px' }}>…</span>
                                        : <button key={n} type="button" onClick={() => setPage(n as number)}
                                            style={{ padding: '4px 10px', borderRadius: '5px', border: '1px solid #e2e8f0', background: n === safePage ? '#2563eb' : '#fff', color: n === safePage ? '#fff' : '#374151', cursor: 'pointer', fontSize: '12px', fontWeight: n === safePage ? 700 : 400 }}>
                                            {n}
                                          </button>
                                )}
                                <button type="button" onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={safePage === totalPages}
                                    style={{ padding: '4px 10px', borderRadius: '5px', border: '1px solid #e2e8f0', background: '#fff', color: safePage === totalPages ? '#cbd5e1' : '#374151', cursor: safePage === totalPages ? 'default' : 'pointer', fontSize: '12px' }}>
                                    Next ›
                                </button>
                            </div>
                        </div>
                    }
                />
            )}
        </HubShell>
    );
}
