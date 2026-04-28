import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface MasterItem {
    id: number;
    name: string;
    description?: string | null;
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
    | 'structures';

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
    const [description, setDescription] = useState(item?.description ?? '');
    const [submitting,  setSubmitting]  = useState(false);

    const handleSubmit = () => {
        if (!name.trim()) return;
        setSubmitting(true);
        const base = ROUTE_MAP[tab];
        const data = { name: name.trim(), description: description.trim() || null };

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

// ── Delete Confirm ─────────────────────────────────────────────────────────
function DeleteModal({ tab, item, onClose }: { tab: TabKey; item: MasterItem; onClose: () => void }) {
    const [deleting, setDeleting] = useState(false);

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route(`${ROUTE_MAP[tab]}.destroy`, item.id), {
            onFinish: () => { setDeleting(false); onClose(); },
        });
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.4)', backdropFilter: 'blur(2px)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '14px', width: '100%', maxWidth: '380px', boxShadow: '0 24px 64px rgba(0,0,0,0.14)', zIndex: 201, padding: '24px 24px 20px' }}>
                <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: '#fef2f2', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '14px' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                </div>
                <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a', marginBottom: '6px' }}>Delete Entry</div>
                <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '20px', lineHeight: 1.5 }}>
                    Are you sure you want to delete <strong style={{ color: '#0f172a' }}>"{item.name}"</strong>? This action cannot be undone.
                </div>
                <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                    <button onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151', fontWeight: 500 }}>
                        Cancel
                    </button>
                    <button onClick={handleDelete} disabled={deleting} style={{ padding: '8px 18px', borderRadius: '7px', border: 'none', background: '#dc2626', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: deleting ? 'not-allowed' : 'pointer', opacity: deleting ? 0.7 : 1 }}>
                        {deleting ? 'Deleting…' : 'Delete'}
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

// ── Tab Table ──────────────────────────────────────────────────────────────
function TabTable({
    tab, items, onAdd, onEdit, onDelete,
}: {
    tab: TabKey;
    items: MasterItem[];
    onAdd: () => void;
    onEdit: (item: MasterItem) => void;
    onDelete: (item: MasterItem) => void;
}) {
    const config = TAB_CONFIG.find(t => t.key === tab)!;

    return (
        <div>
            {/* Sub-toolbar */}
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ fontSize: '13px', color: '#64748b' }}>
                    <strong style={{ color: '#0f172a' }}>{items.length}</strong> {items.length === 1 ? 'entry' : 'entries'}
                </div>
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

            {/* Table */}
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f8fafc' }}>
                            {['#', 'Name / Label', 'Description', 'Actions'].map((h, i) => (
                                <th key={h} style={{ padding: '10px 20px', textAlign: i === 3 ? 'right' : 'left', fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f1f5f9', whiteSpace: 'nowrap' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan={4} style={{ padding: '48px', textAlign: 'center' }}>
                                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '10px' }}>
                                        <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            {config.icon}
                                        </div>
                                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>No {config.label.toLowerCase()} yet. Add one to get started.</span>
                                    </div>
                                </td>
                            </tr>
                        ) : items.map((item, idx) => (
                            <tr key={item.id}
                                style={{ borderBottom: '1px solid #f8fafc', transition: 'background 0.1s' }}
                                onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                            >
                                <td style={{ padding: '12px 20px', color: '#cbd5e1', fontSize: '11.5px', width: '60px' }}>{idx + 1}</td>
                                <td style={{ padding: '12px 20px', fontWeight: 600, color: '#0f172a' }}>{item.name}</td>
                                <td style={{ padding: '12px 20px', color: '#94a3b8', fontSize: '12.5px' }}>
                                    {item.description ?? <span style={{ color: '#e2e8f0' }}>—</span>}
                                </td>
                                <td style={{ padding: '12px 20px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'flex-end' }}>
                                        <IconBtn title="Edit" onClick={() => onEdit(item)}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </IconBtn>
                                        <IconBtn title="Delete" color="#dc2626" onClick={() => onDelete(item)}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                        </IconBtn>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
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
}: Props) {
    const [activeTab,    setActiveTab]    = useState<TabKey>('job_types');
    const [modalTab,     setModalTab]     = useState<TabKey>('job_types');
    const [editTarget,   setEditTarget]   = useState<MasterItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<MasterItem | null>(null);
    const [showModal,    setShowModal]    = useState(false);

    const dataMap: Record<TabKey, MasterItem[]> = {
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
        setModalTab(tab);
        setEditTarget(null);
        setShowModal(true);
    };

    const openEdit = (tab: TabKey, item: MasterItem) => {
        setModalTab(tab);
        setEditTarget(item);
        setShowModal(true);
    };

    const totalEntries = Object.values(dataMap).reduce((sum, arr) => sum + arr.length, 0);

    return (
        <AuthenticatedLayout>
            <Head title="Master Data" />

            <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>

            {showModal && (
                <RecordModal
                    tab={modalTab}
                    item={editTarget}
                    onClose={() => setShowModal(false)}
                />
            )}

            {deleteTarget && (
                <DeleteModal
                    tab={activeTab}
                    item={deleteTarget}
                    onClose={() => setDeleteTarget(null)}
                />
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
                            const count = dataMap[tab.key].length;
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
                <TabTable
                    tab={activeTab}
                    items={dataMap[activeTab]}
                    onAdd={() => openAdd(activeTab)}
                    onEdit={item => openEdit(activeTab, item)}
                    onDelete={item => setDeleteTarget(item)}
                />
            </div>
        </AuthenticatedLayout>
    );
}