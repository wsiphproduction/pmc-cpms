import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface MasterItem {
    id: number;
    name: string;
    sequence_no?: number | null;
    description?: string | null;
    created_at?: string;
    // Cost-code detail columns (populated by the GL-code CSV import).
    division?: string | null;
    cost_center?: string | null;
    activity?: string | null;
    expense_description?: string | null;
    agu_per_class?: string | null;
    agu_per_stat?: string | null;
    is_active?: boolean;
}

interface Supplier {
    id: number;
    company: string;
    accredited?: boolean;
    email?: string | null;
    telephone_no?: string | null;
    mobile_no?: string | null;
    is_active?: boolean;
    created_at?: string;
}

interface Props {
    jobTypes:      MasterItem[];
    jobLocations:  MasterItem[];
    costCodes:     MasterItem[];
    sites?:        MasterItem[];
    classes?:      MasterItem[];
    priorities?:   MasterItem[];
    statuses?:     MasterItem[];
    departments?:  MasterItem[];
    categories?:   MasterItem[];
    serviceTypes?: MasterItem[];
    workForces?:   MasterItem[];
    structures?:   MasterItem[];
    suppliers?:    Supplier[];
}

type TabKey =
    | 'job_types'
    | 'job_locations'
    | 'cost_codes'
    | 'sites'
    | 'classes'
    | 'priorities'
    | 'statuses'
    | 'departments'
    | 'categories'
    | 'service_types'
    | 'work_forces'
    | 'structures'
    | 'suppliers';

const formatDateTime = (value?: string | null) => {
    if (!value) return 'â€”';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const TAB_CONFIG: { key: TabKey; label: string; icon: React.ReactNode; addLabel: string }[] = [
    {
        key: 'job_types',
        label: 'Job Types',
        addLabel: 'Add Job Type',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
        ),
    },
    {
        key: 'job_locations',
        label: 'Job Locations',
        addLabel: 'Add Location',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
        ),
    },
    {
        key: 'cost_codes',
        label: 'Cost Codes',
        addLabel: 'Add Cost Code',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        ),
    },
    {
        key: 'sites',
        label: 'Sites',
        addLabel: 'Add Site',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        ),
    },
    {
        key: 'classes',
        label: 'Class',
        addLabel: 'Add Class',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        ),
    },
    {
        key: 'priorities',
        label: 'Priority',
        addLabel: 'Add Priority',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        ),
    },
    {
        key: 'statuses',
        label: 'Status',
        addLabel: 'Add Status',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        ),
    },
    {
        key: 'departments',
        label: 'Departments',
        addLabel: 'Add Department',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
        ),
    },
    {
        key: 'categories',
        label: 'Categories',
        addLabel: 'Add Category',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
        ),
    },
    {
        key: 'service_types',
        label: 'Service Types',
        addLabel: 'Add Service Type',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
            </svg>
        ),
    },
    {
        key: 'work_forces',
        label: 'Work Forces',
        addLabel: 'Add Work Force',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        ),
    },
    {
        key: 'structures',
        label: 'Structures',
        addLabel: 'Add Structure',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
        ),
    },
    {
        key: 'suppliers',
        label: 'Suppliers',
        addLabel: 'Add Supplier',
        icon: (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M3 9l1-5h16l1 5"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21V12h6v9"/>
            </svg>
        ),
    },
];

// ── Route Map ──────────────────────────────────────────────────────────────
const ROUTE_MAP: Record<TabKey, string> = {
    job_types:     'master.job-types',
    job_locations: 'master.job-locations',
    cost_codes:    'master.cost-codes',
    sites:         'master.sites',
    classes:       'master.classes',
    priorities:    'master.priorities',
    statuses:      'master.statuses',
    departments:   'master.departments',
    categories:    'master.categories',
    service_types: 'master.service-types',
    work_forces:   'master.work-forces',
    structures:    'master.structures',
    suppliers:     'master.suppliers',
};

// ── Modal ──────────────────────────────────────────────────────────────────
function RecordModal({
    tab, item, onClose,
}: {
    tab: TabKey;
    item: MasterItem | null;
    onClose: () => void;
}) {
    const isEdit = !!item;
    const [name,        setName]        = useState(item?.name ?? '');
    const [sequenceNo,  setSequenceNo]  = useState(item?.sequence_no?.toString() ?? '');
    const [description, setDescription] = useState(item?.description ?? '');
    const [submitting,  setSubmitting]  = useState(false);
    const isPriority = tab === 'priorities';

    const handleSubmit = () => {
        if (!name.trim()) return;
        setSubmitting(true);
        const base = ROUTE_MAP[tab];
        const data = {
            name: name.trim(),
            description: description.trim() || null,
            ...(isPriority ? { sequence_no: sequenceNo.trim() ? Number(sequenceNo) : null } : {}),
        };

        if (isEdit) {
            router.put(route(`${base}.update`, item!.id), data, {
                onFinish: () => { setSubmitting(false); onClose(); },
            });
        } else {
            router.post(route(`${base}.store`), data, {
                onFinish: () => { setSubmitting(false); onClose(); },
            });
        }
    };

    const tabLabel = TAB_CONFIG.find(t => t.key === tab)?.label ?? '';

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.4)', backdropFilter: 'blur(2px)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '14px', width: '100%', maxWidth: '440px', boxShadow: '0 24px 64px rgba(0,0,0,0.14)', zIndex: 201, overflow: 'hidden' }}>

                {/* Header */}
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>
                            {isEdit ? `Edit ${tabLabel.slice(0, -1)}` : `Add New ${tabLabel.slice(0, -1)}`}
                        </div>
                        <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '2px' }}>
                            {tabLabel} Master List
                        </div>
                    </div>
                    <button onClick={onClose} style={{ width: '30px', height: '30px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#f8fafc', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Body */}
                <div style={{ padding: '20px 22px' }}>
                    <div style={{ marginBottom: '14px' }}>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                            Name / Label <span style={{ color: '#ef4444' }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={name}
                            onChange={e => setName(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && handleSubmit()}
                            placeholder={`Enter ${tabLabel.toLowerCase()} name…`}
                            autoFocus
                            style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', transition: 'border-color 0.15s', color: '#0f172a' }}
                            onFocus={e => (e.target.style.borderColor = '#2563eb')}
                            onBlur={e => (e.target.style.borderColor = '#e2e8f0')}
                        />
                    </div>
                    {isPriority && (
                        <div style={{ marginBottom: '14px' }}>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                                Sequence No.
                            </label>
                            <input
                                type="number"
                                min="1"
                                value={sequenceNo}
                                onChange={e => setSequenceNo(e.target.value)}
                                placeholder="e.g. 1"
                                style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', transition: 'border-color 0.15s', color: '#0f172a' }}
                                onFocus={e => (e.target.style.borderColor = '#2563eb')}
                                onBlur={e => (e.target.style.borderColor = '#e2e8f0')}
                            />
                        </div>
                    )}
                    <div>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                            Description <span style={{ color: '#94a3b8', fontWeight: 400, textTransform: 'none' }}>(optional)</span>
                        </label>
                        <textarea
                            rows={3}
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            placeholder="Short description or notes…"
                            style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', resize: 'vertical', boxSizing: 'border-box', transition: 'border-color 0.15s', color: '#0f172a' }}
                            onFocus={e => (e.target.style.borderColor = '#2563eb')}
                            onBlur={e => (e.target.style.borderColor = '#e2e8f0')}
                        />
                    </div>
                </div>

                {/* Footer */}
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151', fontWeight: 500 }}>
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={submitting || !name.trim()}
                        style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: submitting || !name.trim() ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: submitting || !name.trim() ? 'not-allowed' : 'pointer', display: 'flex', alignItems: 'center', gap: '6px', transition: 'background 0.15s' }}>
                        {submitting ? (
                            <>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                Saving…
                            </>
                        ) : (
                            <>{isEdit ? 'Save Changes' : 'Add Entry'}</>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Icon Button ────────────────────────────────────────────────────────────
function IconBtn({ onClick, title, color = '#374151', children }: {
    onClick?: () => void; title?: string; color?: string; children: React.ReactNode;
}) {
    return (
        <button onClick={onClick} title={title}
            style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', color, cursor: 'pointer', transition: 'all 0.12s', flexShrink: 0 }}
            onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
            onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
        >
            {children}
        </button>
    );
}

// ── Active / Inactive Toggle Switch ──────────────────────────────────────────
function ToggleSwitch({ active, onClick, title }: { active: boolean; onClick: () => void; title?: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={title ?? (active ? 'Active — click to deactivate' : 'Inactive — click to activate')}
            aria-label={active ? 'Deactivate' : 'Activate'}
            style={{ position: 'relative', width: '38px', height: '22px', borderRadius: '99px', border: 'none', cursor: 'pointer', background: active ? '#16a34a' : '#cbd5e1', transition: 'background 0.15s', flexShrink: 0, padding: 0 }}
        >
            <span style={{ position: 'absolute', top: '2px', left: active ? '18px' : '2px', width: '18px', height: '18px', borderRadius: '50%', background: '#fff', transition: 'left 0.15s', boxShadow: '0 1px 2px rgba(0,0,0,0.25)' }} />
        </button>
    );
}

// ── Pagination ───────────────────────────────────────────────────────────────
const PER_PAGE = 10;

const pageNumbers = (page: number, totalPages: number): (number | '…')[] => {
    if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1);
    if (page <= 4) return [1, 2, 3, 4, 5, '…', totalPages];
    if (page >= totalPages - 3) return [1, '…', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
    return [1, '…', page - 1, page, page + 1, '…', totalPages];
};

function TablePagination({ page, totalPages, count, onPage }: { page: number; totalPages: number; count: number; onPage: (p: number) => void }) {
    if (count === 0) return null;
    const from = (page - 1) * PER_PAGE + 1;
    const to = Math.min(page * PER_PAGE, count);
    const btn = (disabled: boolean): React.CSSProperties => ({ padding: '4px 10px', borderRadius: '5px', border: '1px solid #e2e8f0', background: '#fff', color: disabled ? '#cbd5e1' : '#374151', cursor: disabled ? 'default' : 'pointer', fontSize: '12px' });
    return (
        <div style={{ padding: '10px 20px', background: '#f8fafc', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '12px', color: '#64748b' }}>
            <span>Showing <strong>{from}–{to}</strong> of <strong>{count}</strong></span>
            {totalPages > 1 && (
                <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                    <button type="button" onClick={() => onPage(page - 1)} disabled={page === 1} style={btn(page === 1)}>‹ Prev</button>
                    {pageNumbers(page, totalPages).map((n, i) =>
                        n === '…'
                            ? <span key={`e${i}`} style={{ padding: '4px 6px', color: '#94a3b8' }}>…</span>
                            : <button key={n} type="button" onClick={() => onPage(n as number)}
                                style={{ padding: '4px 10px', borderRadius: '5px', border: '1px solid #e2e8f0', background: n === page ? '#2563eb' : '#fff', color: n === page ? '#fff' : '#374151', cursor: 'pointer', fontSize: '12px', fontWeight: n === page ? 700 : 400 }}>
                                {n}
                              </button>
                    )}
                    <button type="button" onClick={() => onPage(page + 1)} disabled={page === totalPages} style={btn(page === totalPages)}>Next ›</button>
                </div>
            )}
        </div>
    );
}

// ── Tab Table ──────────────────────────────────────────────────────────────
function TabTable({
    tab, items, onAdd, onEdit, onToggle,
}: {
    tab: TabKey;
    items: MasterItem[];
    onAdd: () => void;
    onEdit: (item: MasterItem) => void;
    onToggle: (item: MasterItem) => void;
}) {
    const config = TAB_CONFIG.find(t => t.key === tab)!;
    const isPriority = tab === 'priorities';
    const isCostCode = tab === 'cost_codes';
    const headers = isPriority
        ? ['#', 'Sequence', 'Name / Label', 'Description', 'Created At', 'Actions']
        : isCostCode
            ? ['#', 'GL Code', 'Division', 'Cost Center', 'Activity', 'Expense Description', 'Class', 'Status', 'Active', 'Actions']
            : ['#', 'Name / Label', 'Description', 'Created At', 'Actions'];

    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(items.length / PER_PAGE));
    const safePage = Math.min(page, totalPages);
    const paginated = items.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImporting(true);
        router.post(route('master.cost-codes.import'), { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setImporting(false);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    return (
        <div>
            {/* Sub-toolbar */}
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ fontSize: '13px', color: '#64748b' }}>
                    <strong style={{ color: '#0f172a' }}>{items.length}</strong> {items.length === 1 ? 'entry' : 'entries'}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    {isCostCode && (
                        <>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".csv,text/csv"
                                onChange={handleImport}
                                style={{ display: 'none' }}
                            />
                            <button
                                onClick={() => fileInputRef.current?.click()}
                                disabled={importing}
                                title="Import cost codes from a CSV file (columns: Full_GL_Codes, Division, Cost_Center, Activity, Expense_Description, AGU_PER_CLASS, AGU_PER_STAT, isActive)"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#fff', color: '#0f766e', border: '1px solid #99f6e4', fontSize: '12.5px', fontWeight: 600, cursor: importing ? 'wait' : 'pointer' }}
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                {importing ? 'Uploading…' : 'Upload CSV'}
                            </button>
                        </>
                    )}
                    <button
                        onClick={onAdd}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer', transition: 'background 0.15s' }}
                        onMouseEnter={e => (e.currentTarget.style.background = '#1d4ed8')}
                        onMouseLeave={e => (e.currentTarget.style.background = '#2563eb')}
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {config.addLabel}
                    </button>
                </div>
            </div>

            {/* Table */}
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f8fafc' }}>
                            {headers.map((h, i) => (
                                <th key={h} style={{ padding: '10px 20px', textAlign: i === headers.length - 1 ? 'right' : 'left', fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f1f5f9', whiteSpace: 'nowrap' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan={headers.length} style={{ padding: '48px', textAlign: 'center' }}>
                                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '10px' }}>
                                        <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            {config.icon}
                                        </div>
                                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>No {config.label.toLowerCase()} yet. Add one to get started.</span>
                                    </div>
                                </td>
                            </tr>
                        ) : paginated.map((item, idx) => (
                            <tr key={item.id}
                                style={{ borderBottom: '1px solid #f8fafc', transition: 'background 0.1s', opacity: item.is_active === false ? 0.5 : 1 }}
                                onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                            >
                                <td style={{ padding: '12px 20px', color: '#cbd5e1', fontSize: '11.5px', width: '60px' }}>{(safePage - 1) * PER_PAGE + idx + 1}</td>
                                {isCostCode ? (
                                    <>
                                        <td style={{ padding: '12px 20px', fontWeight: 600, color: '#0f172a', whiteSpace: 'nowrap' }}>{item.name}</td>
                                        <td style={{ padding: '12px 20px', color: '#475569', fontSize: '12.5px' }}>{item.division || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px', color: '#475569', fontSize: '12.5px' }}>{item.cost_center || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px', color: '#475569', fontSize: '12.5px' }}>{item.activity || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px', color: '#0f172a', fontSize: '12.5px' }}>{item.expense_description || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12.5px' }}>{item.agu_per_class || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12.5px' }}>{item.agu_per_stat || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                        <td style={{ padding: '12px 20px' }}>
                                            <span style={{ display: 'inline-block', padding: '2px 8px', borderRadius: '99px', fontSize: '11px', fontWeight: 600, background: item.is_active ? '#f0fdf4' : '#fef2f2', color: item.is_active ? '#15803d' : '#dc2626' }}>
                                                {item.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                    </>
                                ) : (
                                    <>
                                        {isPriority && (
                                            <td style={{ padding: '12px 20px', color: '#334155', fontSize: '12px', fontWeight: 700, width: '90px' }}>
                                                {item.sequence_no ?? <span style={{ color: '#e2e8f0' }}>-</span>}
                                            </td>
                                        )}
                                        <td style={{ padding: '12px 20px', fontWeight: 600, color: '#0f172a' }}>{item.name}</td>
                                        <td style={{ padding: '12px 20px', color: '#94a3b8', fontSize: '12.5px' }}>
                                            {item.description ?? <span style={{ color: '#e2e8f0' }}>—</span>}
                                        </td>
                                        <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12px', whiteSpace: 'nowrap' }}>
                                            {formatDateTime(item.created_at)}
                                        </td>
                                    </>
                                )}
                                <td style={{ padding: '12px 20px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', justifyContent: 'flex-end' }}>
                                        <IconBtn title="Edit" onClick={() => onEdit(item)}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </IconBtn>
                                        <ToggleSwitch active={item.is_active !== false} onClick={() => onToggle(item)} />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <TablePagination page={safePage} totalPages={totalPages} count={items.length} onPage={setPage} />
        </div>
    );
}

// ── Supplier Modal (add / edit) ─────────────────────────────────────────────
function SupplierModal({ supplier, suppliers, onClose }: { supplier: Supplier | null; suppliers: Supplier[]; onClose: () => void }) {
    const isEdit = !!supplier;
    const [company,  setCompany]  = useState(supplier?.company ?? '');
    const [accredited, setAccredited] = useState(supplier?.accredited ?? true);
    const [email,    setEmail]    = useState(supplier?.email ?? '');
    const [tel,      setTel]      = useState(supplier?.telephone_no ?? '');
    const [mobile,   setMobile]   = useState(supplier?.mobile_no ?? '');
    const [submitting, setSubmitting] = useState(false);
    const [showSuggest, setShowSuggest] = useState(false);

    const field: React.CSSProperties = { width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', color: '#0f172a' };
    const lbl: React.CSSProperties = { fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' };

    // Suggestions: accredited suppliers whose company matches what's being typed
    // (excluding the record currently being edited). Prevents duplicates and lets
    // the user pick an existing accredited supplier.
    const needle = company.trim().toLowerCase();
    const suggestions = needle
        ? suppliers
            .filter(s => s.accredited !== false && s.id !== supplier?.id && s.company.toLowerCase().includes(needle) && s.company.toLowerCase() !== needle)
            .slice(0, 6)
        : [];

    const pickSuggestion = (s: Supplier) => {
        setCompany(s.company);
        setAccredited(s.accredited ?? true);
        setEmail(s.email ?? '');
        setTel(s.telephone_no ?? '');
        setMobile(s.mobile_no ?? '');
        setShowSuggest(false);
    };

    const handleSubmit = () => {
        if (!company.trim()) return;
        setSubmitting(true);
        const data = { company: company.trim(), accredited, email: email.trim() || null, telephone_no: tel.trim() || null, mobile_no: mobile.trim() || null };
        if (isEdit) {
            router.put(route('master.suppliers.update', supplier!.id), data, { onFinish: () => { setSubmitting(false); onClose(); } });
        } else {
            router.post(route('master.suppliers.store'), data, { onFinish: () => { setSubmitting(false); onClose(); } });
        }
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.4)', backdropFilter: 'blur(2px)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '14px', width: '100%', maxWidth: '460px', boxShadow: '0 24px 64px rgba(0,0,0,0.14)', zIndex: 201, overflow: 'hidden' }}>
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>{isEdit ? 'Edit Supplier' : 'Add New Supplier'}</div>
                        <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '2px' }}>Suppliers Master List</div>
                    </div>
                    <button onClick={onClose} style={{ width: '30px', height: '30px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#f8fafc', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px 22px', display: 'grid', gap: '14px' }}>
                    <div style={{ position: 'relative' }}>
                        <label style={lbl}>Company <span style={{ color: '#ef4444' }}>*</span></label>
                        <input
                            type="text"
                            value={company}
                            onChange={e => { setCompany(e.target.value); setShowSuggest(true); }}
                            onFocus={() => setShowSuggest(true)}
                            onBlur={() => setTimeout(() => setShowSuggest(false), 150)}
                            autoFocus
                            autoComplete="off"
                            placeholder="Company name…"
                            style={field}
                        />
                        {showSuggest && suggestions.length > 0 && (
                            <div style={{ position: 'absolute', top: '100%', left: 0, right: 0, marginTop: '4px', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', boxShadow: '0 10px 30px rgba(0,0,0,0.10)', zIndex: 10, overflow: 'hidden' }}>
                                {suggestions.map(s => (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onMouseDown={e => { e.preventDefault(); pickSuggestion(s); }}
                                        style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%', textAlign: 'left', padding: '9px 12px', border: 'none', background: '#fff', cursor: 'pointer', fontSize: '13px', color: '#0f172a' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                        onMouseLeave={e => (e.currentTarget.style.background = '#fff')}>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span style={{ fontWeight: 600 }}>{s.company}</span>
                                        <span style={{ marginLeft: 'auto', fontSize: '10.5px', fontWeight: 700, color: '#16a34a', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Accredited</span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '9px', cursor: 'pointer', userSelect: 'none' }}>
                        <ToggleSwitch active={accredited} onClick={() => setAccredited(v => !v)} />
                        <span style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>Accredited supplier</span>
                    </label>
                    <div>
                        <label style={lbl}>Email</label>
                        <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="name@company.com" style={field} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                        <div>
                            <label style={lbl}>Telephone No.</label>
                            <input type="text" value={tel} onChange={e => setTel(e.target.value)} placeholder="Landline" style={field} />
                        </div>
                        <div>
                            <label style={lbl}>Mobile No.</label>
                            <input type="text" value={mobile} onChange={e => setMobile(e.target.value)} placeholder="Mobile" style={field} />
                        </div>
                    </div>
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151', fontWeight: 500 }}>Cancel</button>
                    <button onClick={handleSubmit} disabled={submitting || !company.trim()}
                        style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: submitting || !company.trim() ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: submitting || !company.trim() ? 'not-allowed' : 'pointer' }}>
                        {submitting ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Supplier'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Supplier Table ───────────────────────────────────────────────────────────
function SupplierTable({ suppliers, onAdd, onEdit, onToggle }: {
    suppliers: Supplier[];
    onAdd: () => void;
    onEdit: (s: Supplier) => void;
    onToggle: (s: Supplier) => void;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);
    const [page, setPage] = useState(1);

    const totalPages = Math.max(1, Math.ceil(suppliers.length / PER_PAGE));
    const safePage = Math.min(page, totalPages);
    const paginated = suppliers.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImporting(true);
        router.post(route('master.suppliers.import'), { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { setImporting(false); if (fileInputRef.current) fileInputRef.current.value = ''; },
        });
    };

    const headers = ['#', 'Company', 'Accredited', 'Email', 'Telephone', 'Mobile', 'Created At', 'Actions'];

    return (
        <div>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ fontSize: '13px', color: '#64748b' }}>
                    <strong style={{ color: '#0f172a' }}>{suppliers.length}</strong> {suppliers.length === 1 ? 'entry' : 'entries'}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <input ref={fileInputRef} type="file" accept=".csv,text/csv" onChange={handleImport} style={{ display: 'none' }} />
                    <button onClick={() => fileInputRef.current?.click()} disabled={importing}
                        title="Import suppliers from a CSV file (columns: company, email, telephone_no, mobile_no)"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#fff', color: '#0f766e', border: '1px solid #99f6e4', fontSize: '12.5px', fontWeight: 600, cursor: importing ? 'wait' : 'pointer' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        {importing ? 'Uploading…' : 'Upload CSV'}
                    </button>
                    <button onClick={onAdd}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Supplier
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
                        {suppliers.length === 0 ? (
                            <tr>
                                <td colSpan={headers.length} style={{ padding: '48px', textAlign: 'center', fontSize: '13px', color: '#94a3b8' }}>
                                    No suppliers yet. Add one or upload a CSV to get started.
                                </td>
                            </tr>
                        ) : paginated.map((s, idx) => (
                            <tr key={s.id} style={{ borderBottom: '1px solid #f8fafc', opacity: s.is_active === false ? 0.5 : 1 }}
                                onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                <td style={{ padding: '12px 20px', color: '#cbd5e1', fontSize: '11.5px', width: '60px' }}>{(safePage - 1) * PER_PAGE + idx + 1}</td>
                                <td style={{ padding: '12px 20px', fontWeight: 600, color: '#0f172a' }}>{s.company}</td>
                                <td style={{ padding: '12px 20px' }}>
                                    {s.accredited !== false
                                        ? <span style={{ display: 'inline-block', padding: '2px 9px', borderRadius: '999px', background: '#dcfce7', color: '#166534', fontSize: '10.5px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.4px' }}>Accredited</span>
                                        : <span style={{ display: 'inline-block', padding: '2px 9px', borderRadius: '999px', background: '#f1f5f9', color: '#94a3b8', fontSize: '10.5px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.4px' }}>—</span>}
                                </td>
                                <td style={{ padding: '12px 20px', color: '#475569', fontSize: '12.5px' }}>{s.email || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12.5px' }}>{s.telephone_no || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12.5px' }}>{s.mobile_no || <span style={{ color: '#e2e8f0' }}>—</span>}</td>
                                <td style={{ padding: '12px 20px', color: '#64748b', fontSize: '12px', whiteSpace: 'nowrap' }}>{formatDateTime(s.created_at)}</td>
                                <td style={{ padding: '12px 20px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', justifyContent: 'flex-end' }}>
                                        <IconBtn title="Edit" onClick={() => onEdit(s)}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </IconBtn>
                                        <ToggleSwitch active={s.is_active !== false} onClick={() => onToggle(s)} />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <TablePagination page={safePage} totalPages={totalPages} count={suppliers.length} onPage={setPage} />
        </div>
    );
}

// ── Cost Code Modal (add / edit — all GL-code fields) ────────────────────────
function CostCodeModal({ costCode, onClose }: { costCode: MasterItem | null; onClose: () => void }) {
    const isEdit = !!costCode;
    const [name,       setName]       = useState(costCode?.name ?? '');
    const [division,   setDivision]   = useState(costCode?.division ?? '');
    const [costCenter, setCostCenter] = useState(costCode?.cost_center ?? '');
    const [activity,   setActivity]   = useState(costCode?.activity ?? '');
    const [expense,    setExpense]    = useState(costCode?.expense_description ?? '');
    const [aguClass,   setAguClass]   = useState(costCode?.agu_per_class ?? '');
    const [aguStat,    setAguStat]    = useState(costCode?.agu_per_stat ?? '');
    const [isActive,   setIsActive]   = useState(costCode?.is_active ?? true);
    const [submitting, setSubmitting] = useState(false);

    const field: React.CSSProperties = { width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1.5px solid #e2e8f0', fontSize: '13px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', color: '#0f172a' };
    const lbl: React.CSSProperties = { fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.4px' };

    const handleSubmit = () => {
        if (!name.trim()) return;
        setSubmitting(true);
        const data = {
            name: name.trim(),
            division: division.trim() || null,
            cost_center: costCenter.trim() || null,
            activity: activity.trim() || null,
            expense_description: expense.trim() || null,
            agu_per_class: aguClass.trim() || null,
            agu_per_stat: aguStat.trim() || null,
            is_active: isActive,
        };
        if (isEdit) {
            router.put(route('master.cost-codes.update', costCode!.id), data, { onFinish: () => { setSubmitting(false); onClose(); } });
        } else {
            router.post(route('master.cost-codes.store'), data, { onFinish: () => { setSubmitting(false); onClose(); } });
        }
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.4)', backdropFilter: 'blur(2px)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '14px', width: '100%', maxWidth: '520px', boxShadow: '0 24px 64px rgba(0,0,0,0.14)', zIndex: 201, overflow: 'hidden', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>{isEdit ? 'Edit Cost Code' : 'Add New Cost Code'}</div>
                        <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '2px' }}>Cost Codes Master List</div>
                    </div>
                    <button onClick={onClose} style={{ width: '30px', height: '30px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#f8fafc', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px 22px', display: 'grid', gap: '14px', overflowY: 'auto' }}>
                    <div>
                        <label style={lbl}>GL Code <span style={{ color: '#ef4444' }}>*</span></label>
                        <input type="text" value={name} onChange={e => setName(e.target.value)} autoFocus placeholder="e.g. 1.01.01.003.000" style={field} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                        <div>
                            <label style={lbl}>Division</label>
                            <input type="text" value={division} onChange={e => setDivision(e.target.value)} placeholder="Division" style={field} />
                        </div>
                        <div>
                            <label style={lbl}>Cost Center</label>
                            <input type="text" value={costCenter} onChange={e => setCostCenter(e.target.value)} placeholder="Cost Center" style={field} />
                        </div>
                    </div>
                    <div>
                        <label style={lbl}>Activity</label>
                        <input type="text" value={activity} onChange={e => setActivity(e.target.value)} placeholder="Activity" style={field} />
                    </div>
                    <div>
                        <label style={lbl}>Expense Description</label>
                        <input type="text" value={expense} onChange={e => setExpense(e.target.value)} placeholder="Readable account name" style={field} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                        <div>
                            <label style={lbl}>Class (AGU_PER_CLASS)</label>
                            <input type="text" value={aguClass} onChange={e => setAguClass(e.target.value)} placeholder="e.g. Non Ledger" style={field} />
                        </div>
                        <div>
                            <label style={lbl}>Status (AGU_PER_STAT)</label>
                            <input type="text" value={aguStat} onChange={e => setAguStat(e.target.value)} placeholder="e.g. Open" style={field} />
                        </div>
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '13px', color: '#374151', fontWeight: 500 }}>
                        <input type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} style={{ width: '16px', height: '16px', cursor: 'pointer', accentColor: '#2563eb' }} />
                        Active
                    </label>
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151', fontWeight: 500 }}>Cancel</button>
                    <button onClick={handleSubmit} disabled={submitting || !name.trim()}
                        style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: submitting || !name.trim() ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: submitting || !name.trim() ? 'not-allowed' : 'pointer' }}>
                        {submitting ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Cost Code'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function MasterData({
    jobTypes      = [],
    jobLocations  = [],
    costCodes     = [],
    sites         = [],
    classes       = [],
    priorities    = [],
    statuses      = [],
    departments   = [],
    categories    = [],
    serviceTypes  = [],
    workForces    = [],
    structures    = [],
    suppliers     = [],
}: Props) {
    const [activeTab,    setActiveTab]    = useState<TabKey>('job_types');
    const [modalTab,     setModalTab]     = useState<TabKey>('job_types');
    const [editTarget,   setEditTarget]   = useState<MasterItem | null>(null);
    const [showModal,    setShowModal]    = useState(false);

    // Suppliers have their own record shape, so they get a dedicated modal.
    const [showSupplierModal, setShowSupplierModal] = useState(false);
    const [editSupplier,      setEditSupplier]      = useState<Supplier | null>(null);

    // Cost codes carry many extra columns, so they get a dedicated modal too.
    const [showCostCodeModal, setShowCostCodeModal] = useState(false);
    const [editCostCode,      setEditCostCode]      = useState<MasterItem | null>(null);

    const { props: pageProps } = usePage<{ flash?: { success?: string; error?: string }; errors?: Record<string, string> }>();
    const flash = pageProps.flash;
    const fileError = pageProps.errors?.file;

    const dataMap: Record<Exclude<TabKey, 'suppliers'>, MasterItem[]> = {
        job_types:     jobTypes,
        job_locations: jobLocations,
        cost_codes:    costCodes,
        sites,
        classes,
        priorities,
        statuses,
        departments,
        categories,
        service_types: serviceTypes,
        work_forces:   workForces,
        structures,
    };

    const openAdd = (tab: TabKey) => {
        if (tab === 'cost_codes') {
            setEditCostCode(null);
            setShowCostCodeModal(true);
            return;
        }
        setModalTab(tab);
        setEditTarget(null);
        setShowModal(true);
    };

    const openEdit = (tab: TabKey, item: MasterItem) => {
        if (tab === 'cost_codes') {
            setEditCostCode(item);
            setShowCostCodeModal(true);
            return;
        }
        setModalTab(tab);
        setEditTarget(item);
        setShowModal(true);
    };

    // Flip active/inactive for any master record. `slug` is the route segment
    // (e.g. job-types, suppliers); the page reloads with the fresh flag.
    const toggleActive = (slug: string, id: number) => {
        router.patch(route('master.toggle', [slug, id]), {}, { preserveScroll: true });
    };

    const totalEntries = Object.values(dataMap).reduce((sum, arr) => sum + arr.length, 0) + suppliers.length;

    return (
        <AuthenticatedLayout>
            <Head title="Master Data" />

            <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>

            {(flash?.success || flash?.error || fileError) && (
                <div style={{
                    background: flash?.success ? '#f0fdf4' : '#fef2f2',
                    border: `1px solid ${flash?.success ? '#bbf7d0' : '#fecaca'}`,
                    color: flash?.success ? '#15803d' : '#dc2626',
                    borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', fontSize: '13px', fontWeight: 500,
                }}>
                    {flash?.success || flash?.error || fileError}
                </div>
            )}

            {showModal && (
                <RecordModal
                    tab={modalTab}
                    item={editTarget}
                    onClose={() => setShowModal(false)}
                />
            )}

            {showSupplierModal && (
                <SupplierModal supplier={editSupplier} suppliers={suppliers} onClose={() => setShowSupplierModal(false)} />
            )}

            {showCostCodeModal && (
                <CostCodeModal costCode={editCostCode} onClose={() => setShowCostCodeModal(false)} />
            )}

            {/* Page Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                        Dropdown Master Lists
                    </h1>
                    <p style={{ fontSize: '12.5px', color: '#94a3b8', margin: '3px 0 0' }}>
                        Manage global options used across Project Management System forms.
                    </p>
                </div>
                <span style={{ fontSize: '12.5px', color: '#94a3b8' }}>
                    {totalEntries} total {totalEntries !== 1 ? 'entries' : 'entry'}
                </span>
            </div>

            {/* Card */}
            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden' }}>

                {/* Tabs — scrollable row */}
                <div style={{ overflowX: 'auto', borderBottom: '1px solid #f1f5f9' }}>
                    <div style={{ display: 'flex', padding: '0 4px', gap: '2px', minWidth: 'max-content' }}>
                        {TAB_CONFIG.map(tab => {
                            const isActive = activeTab === tab.key;
                            const count = tab.key === 'suppliers' ? suppliers.length : dataMap[tab.key].length;
                            return (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key)}
                                    style={{ display: 'inline-flex', alignItems: 'center', gap: '7px', padding: '12px 16px', border: 'none', background: 'transparent', cursor: 'pointer', fontSize: '13px', fontWeight: isActive ? 700 : 500, color: isActive ? '#2563eb' : '#64748b', borderBottom: isActive ? '2px solid #2563eb' : '2px solid transparent', transition: 'all 0.15s', fontFamily: 'inherit', marginBottom: '-1px', whiteSpace: 'nowrap' }}
                                    onMouseEnter={e => { if (!isActive) e.currentTarget.style.color = '#334155'; }}
                                    onMouseLeave={e => { if (!isActive) e.currentTarget.style.color = '#64748b'; }}
                                >
                                    <span style={{ color: isActive ? '#2563eb' : '#94a3b8', display: 'flex' }}>{tab.icon}</span>
                                    {tab.label}
                                    <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', minWidth: '20px', height: '20px', borderRadius: '99px', background: isActive ? '#eff6ff' : '#f1f5f9', color: isActive ? '#2563eb' : '#94a3b8', fontSize: '10.5px', fontWeight: 700, padding: '0 5px' }}>
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Tab content */}
                {activeTab === 'suppliers' ? (
                    <SupplierTable
                        suppliers={suppliers}
                        onAdd={() => { setEditSupplier(null); setShowSupplierModal(true); }}
                        onEdit={s => { setEditSupplier(s); setShowSupplierModal(true); }}
                        onToggle={s => toggleActive('suppliers', s.id)}
                    />
                ) : (
                    <TabTable
                        key={activeTab}
                        tab={activeTab}
                        items={dataMap[activeTab]}
                        onAdd={() => openAdd(activeTab)}
                        onEdit={item => openEdit(activeTab, item)}
                        onToggle={item => toggleActive(ROUTE_MAP[activeTab].replace('master.', ''), item.id)}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
