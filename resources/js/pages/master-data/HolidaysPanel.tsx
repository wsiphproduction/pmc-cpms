import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useConfirm } from '@/components/useConfirm';

// ── Holidays (Master Data tab) ───────────────────────────────────────────────
// The days a working-days project skips besides Sundays — in its durations,
// its NTP baseline end and its timeline. Starts with the Philippine regular
// holidays; special and local holidays are added here, and any day can be
// moved, renamed, switched off or deleted.

export interface Holiday {
    id: number;
    /** YYYY-MM-DD */
    date: string;
    name: string;
    type: string;
    is_active?: boolean;
}

const TYPE_TONE: Record<string, { bg: string; color: string }> = {
    regular: { bg: '#dbeafe', color: '#1d4ed8' },
    special: { bg: '#fef3c7', color: '#92400e' },
    local:   { bg: '#dcfce7', color: '#166534' },
};

const field: React.CSSProperties = { width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', color: '#0f172a', background: '#fff' };
const lbl: React.CSSProperties = { fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' };
const ghostBtn: React.CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#fff', color: '#0f766e', border: '1px solid #99f6e4', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' };
const primaryBtn: React.CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' };
const iconBtn: React.CSSProperties = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', flexShrink: 0 };

/** "Thu, Jan 01, 2026" from YYYY-MM-DD, read as a local date (no timezone shift). */
const longDate = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit', year: 'numeric' });
};

const isSunday = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).getDay() === 0;
};

function HolidayModal({ holiday, types, onClose }: { holiday: Holiday | null; types: Record<string, string>; onClose: () => void }) {
    const isEdit = !!holiday;
    const [date, setDate] = useState(holiday?.date ?? '');
    const [name, setName] = useState(holiday?.name ?? '');
    const [type, setType] = useState(holiday?.type ?? 'local');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const ready = !!date && !!name.trim();

    const submit = () => {
        if (!ready) return;
        setSubmitting(true);
        setErrors({});
        const data = { date, name: name.trim(), type };
        const opts = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: (e: Record<string, string>) => setErrors(e),
            onFinish: () => setSubmitting(false),
        };
        if (isEdit) router.put(route('master.holidays.update', holiday!.id), data, opts);
        else router.post(route('master.holidays.store'), data, opts);
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '16px' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.4)', backdropFilter: 'blur(2px)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '14px', width: '100%', maxWidth: '440px', boxShadow: '0 24px 64px rgba(0,0,0,0.14)', zIndex: 201, overflow: 'hidden' }}>
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f1f5f9' }}>
                    <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>{isEdit ? 'Edit Holiday' : 'Add Holiday'}</div>
                    <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '2px' }}>Skipped by projects counted in working days</div>
                </div>
                <div style={{ padding: '20px 22px', display: 'grid', gap: '14px' }}>
                    <div>
                        <label style={lbl}>Date <span style={{ color: '#ef4444' }}>*</span></label>
                        <input type="date" value={date} onChange={e => setDate(e.target.value)} style={{ ...field, borderColor: errors.date ? '#fca5a5' : '#e2e8f0' }} />
                        {date && isSunday(date) && (
                            <div style={{ fontSize: '11.5px', color: '#b45309', marginTop: '5px' }}>This falls on a Sunday, which working days already skip.</div>
                        )}
                    </div>
                    <div>
                        <label style={lbl}>Holiday <span style={{ color: '#ef4444' }}>*</span></label>
                        <input type="text" value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Araw ng Surigao del Sur" maxLength={191} autoFocus={!isEdit} style={{ ...field, borderColor: errors.name ? '#fca5a5' : '#e2e8f0' }} />
                    </div>
                    <div>
                        <label style={lbl}>Type</label>
                        <select value={type} onChange={e => setType(e.target.value)} style={{ ...field, cursor: 'pointer' }}>
                            {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </div>
                </div>
                {Object.values(errors).filter(Boolean).length > 0 && (
                    <div style={{ margin: '0 22px 14px', padding: '9px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, lineHeight: 1.5 }}>
                        {Object.values(errors).filter(Boolean).map((message, i) => <div key={i}>{message}</div>)}
                    </div>
                )}
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151', fontWeight: 500 }}>Cancel</button>
                    <button onClick={submit} disabled={submitting || !ready}
                        style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: submitting || !ready ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: submitting || !ready ? 'not-allowed' : 'pointer' }}>
                        {submitting ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Holiday'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function HolidaysPanel({ holidays, types }: { holidays: Holiday[]; types: Record<string, string> }) {
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();
    const thisYear = new Date().getFullYear();

    const years = useMemo(() => {
        const set = new Set(holidays.map(h => Number(h.date.slice(0, 4))));
        set.add(thisYear);
        return [...set].sort((a, b) => b - a);
    }, [holidays, thisYear]);

    const [year, setYear] = useState<number | 'all'>(thisYear);
    const [search, setSearch] = useState('');
    const [modal, setModal] = useState<{ holiday: Holiday | null } | null>(null);
    const [loadYear, setLoadYear] = useState(String(thisYear + 1));
    const [loading, setLoading] = useState(false);

    const needle = search.trim().toLowerCase();
    const shown = holidays.filter(h =>
        (year === 'all' || h.date.startsWith(String(year)))
        && (!needle || h.name.toLowerCase().includes(needle) || (types[h.type] ?? h.type).toLowerCase().includes(needle)));

    const toggle = (h: Holiday) => router.patch(route('master.toggle', ['holidays', h.id]), {}, { preserveState: true, preserveScroll: true });

    const remove = (h: Holiday) => showConfirm(
        `Delete ${h.name} (${longDate(h.date)})? Working-days projects will count that day again.`,
        () => router.delete(route('master.holidays.destroy', h.id), { preserveState: true, preserveScroll: true }),
        { title: 'Delete Holiday', confirmLabel: 'Delete', variant: 'danger' },
    );

    const loadRegular = () => {
        const y = Number(loadYear);
        if (!y) return;
        setLoading(true);
        router.post(route('master.holidays.load-regular'), { year: y }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setYear(y),
            onFinish: () => setLoading(false),
        });
    };

    const headers = ['Date', 'Holiday', 'Type', 'Actions'];

    return (
        <div>
            {confirmDialog}
            {modal && <HolidayModal holiday={modal.holiday} types={types} onClose={() => setModal(null)} />}

            <div style={{ padding: '14px 20px', background: '#f8fafc', borderBottom: '1px solid #f1f5f9', fontSize: '12.5px', color: '#475569', lineHeight: 1.6 }}>
                Projects counted in <strong>working days</strong> skip these dates as well as Sundays — in their durations, the NTP baseline end and the timeline.
                Switch a day off to stop skipping it. Eid'l Fitr and Eid'l Adha are proclaimed each year, so add them once announced.
            </div>

            <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                    <select value={year} onChange={e => setYear(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                        aria-label="Year" style={{ padding: '7px 10px', borderRadius: '7px', border: '1px solid #e2e8f0', fontSize: '12.5px', color: '#0f172a', fontFamily: 'inherit', cursor: 'pointer' }}>
                        {years.map(y => <option key={y} value={y}>{y}</option>)}
                        <option value="all">All years</option>
                    </select>
                    <span style={{ fontSize: '13px', color: '#64748b' }}>
                        <strong style={{ color: '#0f172a' }}>{shown.length}</strong> {shown.length === 1 ? 'holiday' : 'holidays'}
                    </span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                    <input type="search" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search holidays…" aria-label="Search holidays"
                        style={{ width: '180px', padding: '7px 10px', borderRadius: '7px', border: '1px solid #e2e8f0', fontSize: '12.5px', color: '#0f172a', outline: 'none', fontFamily: 'inherit' }} />
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                        <input type="number" value={loadYear} onChange={e => setLoadYear(e.target.value)} min={2000} max={2100} aria-label="Year to load"
                            style={{ width: '76px', padding: '7px 8px', borderRadius: '7px', border: '1px solid #e2e8f0', fontSize: '12.5px', fontFamily: 'inherit' }} />
                        <button onClick={loadRegular} disabled={loading} title="Add that year's Philippine regular holidays; dates already listed are left as they are"
                            style={{ ...ghostBtn, cursor: loading ? 'wait' : 'pointer' }}>
                            {loading ? 'Loading…' : 'Load PH Regular Holidays'}
                        </button>
                    </div>
                    <button onClick={() => setModal({ holiday: null })} style={primaryBtn}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Holiday
                    </button>
                </div>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f8fafc' }}>
                            {headers.map((h, i) => (
                                <th key={h} style={{ padding: '10px 20px', textAlign: i === headers.length - 1 ? 'right' : 'left', fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f1f5f9', whiteSpace: 'nowrap' }}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {shown.length === 0 ? (
                            <tr>
                                <td colSpan={headers.length} style={{ padding: '48px', textAlign: 'center', fontSize: '13px', color: '#94a3b8' }}>
                                    {needle ? `No holidays match “${search.trim()}”.` : `No holidays for ${year === 'all' ? 'any year' : year} yet. Load the Philippine regular holidays or add one.`}
                                </td>
                            </tr>
                        ) : shown.map(h => {
                            const tone = TYPE_TONE[h.type] ?? { bg: '#f1f5f9', color: '#475569' };
                            const active = h.is_active !== false;

                            return (
                                <tr key={h.id} style={{ borderBottom: '1px solid #f8fafc', opacity: active ? 1 : 0.5 }}>
                                    <td style={{ padding: '12px 20px', color: '#334155', whiteSpace: 'nowrap', fontVariantNumeric: 'tabular-nums' }}>
                                        {longDate(h.date)}
                                        {isSunday(h.date) && <span style={{ marginLeft: '6px', fontSize: '11px', color: '#94a3b8' }}>(Sunday)</span>}
                                    </td>
                                    <td style={{ padding: '12px 20px', fontWeight: 600, color: '#0f172a' }}>{h.name}</td>
                                    <td style={{ padding: '12px 20px' }}>
                                        <span style={{ padding: '2px 10px', borderRadius: '999px', background: tone.bg, color: tone.color, fontSize: '11px', fontWeight: 700, whiteSpace: 'nowrap' }}>{types[h.type] ?? h.type}</span>
                                    </td>
                                    <td style={{ padding: '12px 20px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', justifyContent: 'flex-end' }}>
                                            <button type="button" title="Edit" onClick={() => setModal({ holiday: h })} style={{ ...iconBtn, color: '#374151' }}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <button type="button" title="Delete" onClick={() => remove(h)} style={{ ...iconBtn, color: '#dc2626' }}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            </button>
                                            <button type="button" onClick={() => toggle(h)}
                                                title={active ? 'Skipped by working days — click to stop skipping it' : 'Not skipped — click to skip it again'}
                                                aria-label={active ? 'Deactivate' : 'Activate'}
                                                style={{ position: 'relative', width: '38px', height: '22px', borderRadius: '99px', border: 'none', cursor: 'pointer', background: active ? '#16a34a' : '#cbd5e1', flexShrink: 0, padding: 0 }}>
                                                <span style={{ position: 'absolute', top: '2px', left: active ? '18px' : '2px', width: '18px', height: '18px', borderRadius: '50%', background: '#fff', transition: 'left 0.15s', boxShadow: '0 1px 2px rgba(0,0,0,0.25)' }} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
