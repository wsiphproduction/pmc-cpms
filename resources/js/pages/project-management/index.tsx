import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface Project {
    id: number;
    project_no: string;
    title: string;
    type: 'Major' | 'Minor';
    progress: number;
    project_manager: string;
    dept_owner: string;
    status: string;
    budget_total: number;
    budget_paid: number;
    deadline: string | null;
    days_remaining: number | null;
    /** Children of this project; listed under its number, never as own rows. */
    sub_projects: { id: number; project_no: string; title: string; status: string }[];
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface AdvancedFilters {
    project_no?: string;
    project_manager?: string;
    title?: string;
    project_type?: string;
    site?: string;
    asset_id?: string;
    cls?: string;
    priority_no?: string;
    status?: string;
    wr_no?: string;
    wr_date_received?: string;
    dept_owner?: string;
    cost_code?: string;
    category?: string;
    service_type?: string;
    work_force?: string;
    structure_type?: string;
    jip?: boolean;
    need_civil?: boolean;
    need_electrical?: boolean;
    need_mechanical?: boolean;
}

interface Filters extends AdvancedFilters {
    search?: string;
}

interface SelectOption {
    value: string;
    label: string;
    displayLabel?: string;
}

interface MasterData {
    managers: SelectOption[];
    sites: SelectOption[];
    assets: SelectOption[];
    departments: SelectOption[];
    classes: SelectOption[];
    priorities: SelectOption[];
    statuses: SelectOption[];
    workForces: SelectOption[];
    categories: SelectOption[];
    serviceTypes: SelectOption[];
    structures: SelectOption[];
}

interface Props extends MasterData {
    projects: Paginated<Project>;
    filters: Filters;
    canCreate: boolean;
}

// ── Status Badge ───────────────────────────────────────────────────────────
function StatusBadge({ status }: { status: string }) {
    const map: Record<string, { bg: string; color: string; border: string }> = {
        'Ongoing':                 { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' },
        'For Planning':            { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' },
        'Proposal Under Review':   { bg: '#fefce8', color: '#854d0e', border: '#fef08a' },
        'On Hold':                 { bg: '#fff7ed', color: '#9a3412', border: '#ffedd5' },
    };
    const s = map[status] ?? { bg: '#f3f4f6', color: '#374151', border: '#e5e7eb' };
    return (
        <span style={{
            padding: '4px 12px',
            borderRadius: '50px',
            fontWeight: 700,
            fontSize: '10.5px',
            display: 'inline-block',
            background: s.bg,
            color: s.color,
            border: `1px solid ${s.border}`,
            whiteSpace: 'nowrap',
        }}>
            {status}
        </span>
    );
}

// ── Type Label ─────────────────────────────────────────────────────────────
function TypeLabel({ type }: { type: Project['type'] }) {
    const isMajor = type === 'Major';
    return (
        <span style={{
            fontSize: '10px',
            fontWeight: 700,
            padding: '2px 6px',
            borderRadius: '4px',
            display: 'inline-block',
            marginTop: '4px',
            background: isMajor ? '#e0e7ff' : '#f1f5f9',
            color: isMajor ? '#3730a3' : '#475569',
        }}>
            {type}
        </span>
    );
}

// ── Mini Progress Bar ──────────────────────────────────────────────────────
function MiniProgress({ progress }: { progress: number }) {
    const color = progress < 30 ? '#ef4444' : progress < 70 ? '#f59e0b' : '#22c55e';
    return (
        <div style={{ width: '100px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 800, color: '#334155' }}>{progress}%</span>
            <div style={{ height: '6px', borderRadius: '10px', background: '#e2e8f0', overflow: 'hidden', marginTop: '4px' }}>
                <div style={{ height: '100%', borderRadius: '10px', width: `${progress}%`, background: color, transition: 'width 0.6s ease' }} />
            </div>
        </div>
    );
}

// ── Payment Status (budget paid vs total) ──────────────────────────────────
function MiniPayment({ paid, total }: { paid: number; total: number }) {
    const pct = total > 0 ? Math.round((paid / total) * 100) : 0;
    return (
        <div style={{ width: '110px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 800, color: '#334155' }}>{pct}%</span>
            <div style={{ height: '6px', borderRadius: '10px', background: '#e2e8f0', overflow: 'hidden', marginTop: '4px' }}>
                <div style={{ height: '100%', borderRadius: '10px', width: `${pct}%`, background: '#2563eb', transition: 'width 0.6s ease' }} />
            </div>
        </div>
    );
}

// ── Icon Button ────────────────────────────────────────────────────────────
function IconBtn({ onClick, title, children }: {
    onClick?: () => void; title?: string; children: React.ReactNode;
}) {
    return (
        <button
            onClick={onClick}
            title={title}
            style={{
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                width: '30px', height: '30px', borderRadius: '6px',
                border: '1px solid #e5e7eb', background: '#fff',
                color: '#374151', cursor: 'pointer', transition: 'all 0.12s', flexShrink: 0,
            }}
            onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
            onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
        >
            {children}
        </button>
    );
}

// ── Advanced Filter Modal ──────────────────────────────────────────────────
function AdvancedFilterModal({
    filters, onClose,
    managers, sites, assets, departments, classes, priorities, statuses, workForces, categories, serviceTypes, structures,
}: MasterData & { filters: Filters; onClose: () => void }) {
    const [form, setForm] = useState<AdvancedFilters>({
        project_no:        filters.project_no ?? '',
        project_manager:   filters.project_manager ?? '',
        title:             filters.title ?? '',
        project_type:      filters.project_type ?? '',
        site:              filters.site ?? '',
        asset_id:          filters.asset_id ?? '',
        cls:               filters.cls ?? '',
        priority_no:       filters.priority_no ?? '',
        status:            filters.status ?? '',
        wr_no:             filters.wr_no ?? '',
        wr_date_received:  filters.wr_date_received ?? '',
        dept_owner:        filters.dept_owner ?? '',
        cost_code:         filters.cost_code ?? '',
        category:          filters.category ?? '',
        service_type:      filters.service_type ?? '',
        work_force:        filters.work_force ?? '',
        structure_type:    filters.structure_type ?? '',
        jip:               filters.jip ?? false,
        need_civil:        filters.need_civil ?? false,
        need_electrical:   filters.need_electrical ?? false,
        need_mechanical:   filters.need_mechanical ?? false,
    });

    const set = (key: keyof AdvancedFilters, value: string | boolean) =>
        setForm(prev => ({ ...prev, [key]: value }));

    const apply = () => {
        const params: Record<string, string | boolean> = {};
        Object.entries(form).forEach(([k, v]) => {
            if (v !== '' && v !== false) params[k] = v as string | boolean;
        });
        router.get(route('projects.index'), params, { preserveState: true });
        onClose();
    };

    const reset = () => setForm({
        project_no: '', project_manager: '', title: '', project_type: '',
        site: '', asset_id: '', cls: '', priority_no: '', status: '', wr_no: '',
        wr_date_received: '', dept_owner: '', cost_code: '', category: '',
        service_type: '', work_force: '', structure_type: '',
        jip: false, need_civil: false, need_electrical: false, need_mechanical: false,
    });

    const inputStyle: React.CSSProperties = {
        width: '100%', padding: '6px 10px', borderRadius: '6px',
        border: '1.5px solid #e5e7eb', fontSize: '12.5px',
        outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box',
    };
    const labelStyle: React.CSSProperties = {
        fontSize: '10.5px', fontWeight: 700, color: '#475569',
        display: 'block', marginBottom: '4px', textTransform: 'uppercase', letterSpacing: '0.3px',
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)' }} />
            <div style={{
                position: 'relative', background: '#fff', borderRadius: '12px',
                width: '100%', maxWidth: '860px', zIndex: 201,
                boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
                display: 'flex', flexDirection: 'column', maxHeight: '90vh',
            }}>
                {/* Header */}
                <div style={{ padding: '14px 20px', background: '#1e293b', borderRadius: '12px 12px 0 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>Advanced Filter Search</span>
                    <button onClick={onClose} style={{ width: '26px', height: '26px', borderRadius: '5px', border: '1px solid rgba(255,255,255,0.2)', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Body */}
                <div style={{ overflowY: 'auto', padding: '20px', background: '#f8fafc' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '14px' }}>
                        <div>
                            <label style={labelStyle}>Project No</label>
                            <input type="text" value={form.project_no} onChange={e => set('project_no', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={labelStyle}>Project Manager</label>
                            <select value={form.project_manager} onChange={e => set('project_manager', e.target.value)} style={inputStyle}>
                                <option value="">All Managers</option>
                                {managers.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div style={{ gridColumn: 'span 2' }}>
                            <label style={labelStyle}>Project Title</label>
                            <input type="text" value={form.title} onChange={e => set('title', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={labelStyle}>Project Type</label>
                            <select value={form.project_type} onChange={e => set('project_type', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                <option value="major">Major</option>
                                <option value="minor">Minor</option>
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Site</label>
                            <select value={form.site} onChange={e => set('site', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {sites.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Asset ID</label>
                            <select value={form.asset_id} onChange={e => set('asset_id', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {assets.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Class</label>
                            <select value={form.cls} onChange={e => set('cls', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {classes.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Priority No.</label>
                            <select value={form.priority_no} onChange={e => set('priority_no', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {priorities.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Project Status</label>
                            <select value={form.status} onChange={e => set('status', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {statuses.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>WR No.</label>
                            <input type="number" value={form.wr_no} onChange={e => set('wr_no', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={labelStyle}>WR Date Received</label>
                            <input type="date" value={form.wr_date_received} onChange={e => set('wr_date_received', e.target.value)} style={inputStyle} />
                        </div>
                        <div style={{ gridColumn: 'span 2' }}>
                            <label style={labelStyle}>Department Owner</label>
                            <select value={form.dept_owner} onChange={e => set('dept_owner', e.target.value)} style={inputStyle}>
                                <option value="">All Departments</option>
                                {departments.map(opt => <option key={opt.value} value={opt.value}>{opt.displayLabel ?? opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Cost Code</label>
                            <input type="text" value={form.cost_code} onChange={e => set('cost_code', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={labelStyle}>Category</label>
                            <select value={form.category} onChange={e => set('category', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {categories.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Service Type</label>
                            <select value={form.service_type} onChange={e => set('service_type', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {serviceTypes.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Work Force</label>
                            <select value={form.work_force} onChange={e => set('work_force', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {workForces.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Structure Type</label>
                            <select value={form.structure_type} onChange={e => set('structure_type', e.target.value)} style={inputStyle}>
                                <option value="">All</option>
                                {structures.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                            </select>
                        </div>
                    </div>

                    {/* Checkboxes */}
                    <div style={{ marginTop: '16px', display: 'flex', gap: '24px', flexWrap: 'wrap' }}>
                        {([
                            ['jip',              'JIP'],
                            ['need_civil',       'Need Civil Works Plans'],
                            ['need_electrical',  'Need Electrical Plans'],
                            ['need_mechanical',  'Need Mechanical Plans'],
                        ] as [keyof AdvancedFilters, string][]).map(([key, label]) => (
                            <label key={key} style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12.5px', fontWeight: 600, color: '#374151', cursor: 'pointer' }}>
                                <input
                                    type="checkbox"
                                    checked={!!form[key]}
                                    onChange={e => set(key, e.target.checked)}
                                    style={{ cursor: 'pointer', accentColor: '#2563eb' }}
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <div style={{ padding: '14px 20px', borderTop: '1px solid #e5e7eb', display: 'flex', justifyContent: 'flex-end', gap: '8px', background: '#fff', borderRadius: '0 0 12px 12px' }}>
                    <button onClick={reset} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151' }}>
                        Reset
                    </button>
                    <button onClick={apply} style={{ padding: '7px 18px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Pagination ─────────────────────────────────────────────────────────────
function Pagination({ data }: { data: Paginated<unknown> }) {
    if (data.last_page <= 1) return null;
    return (
        <div style={{ padding: '14px 18px', borderTop: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
            <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                Showing <strong style={{ color: '#374151' }}>{data.from}–{data.to}</strong> of <strong style={{ color: '#374151' }}>{data.total}</strong> entries
            </span>
            <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                {data.links.map((link, i) => {
                    const isDisabled = !link.url;
                    const isActive   = link.active;
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›')
                        .replace(/&[^;]+;/g, '');
                    return (
                        <button
                            key={i}
                            disabled={isDisabled}
                            onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                            style={{
                                minWidth: '32px', height: '32px', padding: '0 8px',
                                borderRadius: '7px', border: '1px solid',
                                borderColor: isActive ? '#2563eb' : '#e5e7eb',
                                background: isActive ? '#2563eb' : '#fff',
                                color: isActive ? '#fff' : isDisabled ? '#d1d5db' : '#374151',
                                fontSize: '12.5px', fontWeight: isActive ? 700 : 400,
                                cursor: isDisabled ? 'not-allowed' : 'pointer', transition: 'all 0.12s',
                            }}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function ProjectsIndex({
    projects, filters, canCreate,
    managers, sites, assets, departments, classes, priorities, statuses, workForces, categories, serviceTypes, structures,
}: Props) {
    const [search,         setSearch]         = useState(filters.search ?? '');
    const [showAdvFilter,  setShowAdvFilter]   = useState(false);
    // Display-only: sub-projects ship with their parent row, so revealing them
    // costs nothing and needs no round trip.
    const [showSubs,       setShowSubs]        = useState(false);

    const subCount = projects.data.reduce((n, p) => n + p.sub_projects.length, 0);

    // Keep whatever is already applied when one control changes, so the quick
    // status filter composes with the search box and the advanced filters.
    const applyFilters = (patch: Partial<Filters>) => {
        const params: Record<string, string | boolean> = {};
        Object.entries({ ...filters, search, ...patch }).forEach(([k, v]) => {
            if (v !== '' && v !== false && v != null) params[k] = v as string | boolean;
        });
        router.get(route('projects.index'), params, { preserveState: true });
    };

    const doSearch     = () => applyFilters({});
    const clearFilters = () => router.get(route('projects.index'));
    const hasFilters   = Object.values(filters).some(v => v && (Array.isArray(v) ? v.length : true));

    return (
        <AuthenticatedLayout>
            <Head title="Project Management" />

            {showAdvFilter && (
                <AdvancedFilterModal
                    filters={filters}
                    onClose={() => setShowAdvFilter(false)}
                    managers={managers} sites={sites} assets={assets} departments={departments}
                    classes={classes} priorities={priorities} statuses={statuses} workForces={workForces}
                    categories={categories} serviceTypes={serviceTypes} structures={structures}
                />
            )}

            {/* Page Title */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    Project Management
                </h1>
                <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                    {projects.total} total record{projects.total !== 1 ? 's' : ''}
                </span>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden' }}>

                {/* Toolbar */}
                <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flex: 1, maxWidth: '620px', flexWrap: 'wrap' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '7px 12px', flex: 1 }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <input
                                type="text"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && doSearch()}
                                placeholder="Search projects..."
                                style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }}
                            />
                            {search && (
                                <button onClick={() => { setSearch(''); clearFilters(); }} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', display: 'flex', padding: 0 }}>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            )}
                        </div>
                        <button
                            onClick={() => setShowAdvFilter(true)}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '6px',
                                padding: '7px 14px', borderRadius: '7px',
                                border: `1px solid ${hasFilters ? '#2563eb' : '#e5e7eb'}`,
                                background: hasFilters ? '#eff6ff' : '#fff',
                                fontSize: '12.5px', fontWeight: 500,
                                color: hasFilters ? '#2563eb' : '#374151',
                                cursor: 'pointer', whiteSpace: 'nowrap',
                            }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                            Advanced Filter
                        </button>
                        {/* Status is the filter people reach for most, so it gets
                            a control of its own rather than living only in the
                            advanced modal. */}
                        <select
                            value={filters.status ?? ''}
                            onChange={e => applyFilters({ status: e.target.value })}
                            title="Filter by status"
                            style={{
                                padding: '7px 10px', borderRadius: '7px',
                                border: `1px solid ${filters.status ? '#2563eb' : '#e5e7eb'}`,
                                background: filters.status ? '#eff6ff' : '#fff',
                                color: filters.status ? '#2563eb' : '#374151',
                                fontSize: '12.5px', fontFamily: 'inherit',
                                cursor: 'pointer', outline: 'none', whiteSpace: 'nowrap',
                            }}
                        >
                            <option value="">All Statuses</option>
                            {statuses.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                        </select>
                    </div>

                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        <label
                            title={subCount > 0 ? 'List sub-project numbers under their parent' : 'None of the projects on this page have sub-projects'}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '7px', padding: '7px 12px', borderRadius: '7px', border: `1px solid ${showSubs ? '#2563eb' : '#e5e7eb'}`, background: showSubs ? '#eff6ff' : '#fff', fontSize: '12px', fontWeight: 500, color: showSubs ? '#2563eb' : '#374151', cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap' }}>
                            <input
                                type="checkbox"
                                checked={showSubs}
                                onChange={e => setShowSubs(e.target.checked)}
                                style={{ margin: 0, accentColor: '#2563eb', cursor: 'pointer' }}
                            />
                            Show sub-projects
                            <span style={{ padding: '1px 6px', borderRadius: '999px', background: showSubs ? 'rgba(37,99,235,0.12)' : '#f1f5f9', color: showSubs ? '#2563eb' : '#94a3b8', fontSize: '11px', fontWeight: 700 }}>{subCount}</span>
                        </label>
                        {hasFilters && (
                            <button onClick={clearFilters} style={{ display: 'flex', alignItems: 'center', gap: '5px', padding: '7px 12px', borderRadius: '7px', border: '1px solid #fca5a5', background: '#fff7f7', fontSize: '12px', fontWeight: 500, color: '#dc2626', cursor: 'pointer' }}>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Clear filters
                            </button>
                        )}
                        {canCreate && (
                            <Link
                                href={route('projects.create')}
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add New Project
                            </Link>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['Project #', 'Project Title', 'Engr', 'Completion (%)', 'Payment Status', 'Dept Owner', 'Target Completion', 'Status', 'Actions'].map((h, i, arr) => (
                                    <th key={h} style={{
                                        padding: '10px 16px',
                                        textAlign: i === arr.length - 1 ? 'center' : 'left',
                                        fontSize: '10.5px', fontWeight: 700,
                                        color: '#9ca3af', textTransform: 'uppercase',
                                        letterSpacing: '0.5px',
                                        borderBottom: '1px solid #f3f4f6',
                                        whiteSpace: 'nowrap',
                                    }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {projects.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} style={{ padding: '48px', textAlign: 'center' }}>
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 10px' }}>
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span style={{ fontSize: '13px', color: '#9ca3af' }}>No projects found.</span>
                                    </td>
                                </tr>
                            ) : projects.data.map(proj => (
                                <tr
                                    key={proj.id}
                                    style={{ borderBottom: '1px solid #f8fafc', transition: 'background 0.1s' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <td style={{ padding: '12px 16px', fontSize: '11.5px' }}>
                                        <Link
                                            href={route('projects.show', proj.id)}
                                            style={{ fontWeight: 700, color: '#2563eb', textDecoration: 'none', whiteSpace: 'nowrap' }}
                                            onMouseEnter={e => (e.currentTarget.style.textDecoration = 'underline')}
                                            onMouseLeave={e => (e.currentTarget.style.textDecoration = 'none')}
                                        >
                                            {proj.project_no}
                                        </Link>
                                        {showSubs && proj.sub_projects.length > 0 && (
                                            <div style={{ marginTop: '6px', paddingLeft: '9px', borderLeft: '2px solid #e5e7eb', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                                {proj.sub_projects.map(sub => (
                                                    <Link
                                                        key={sub.id}
                                                        href={route('projects.show', sub.id)}
                                                        title={`${sub.title} · ${sub.status}`}
                                                        style={{ color: '#64748b', textDecoration: 'none', fontWeight: 600, whiteSpace: 'nowrap' }}
                                                        onMouseEnter={e => { e.currentTarget.style.textDecoration = 'underline'; e.currentTarget.style.color = '#2563eb'; }}
                                                        onMouseLeave={e => { e.currentTarget.style.textDecoration = 'none'; e.currentTarget.style.color = '#64748b'; }}
                                                    >
                                                        ↳ {sub.project_no}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px 16px', maxWidth: '220px' }}>
                                        <div style={{ fontWeight: 700, color: '#0f172a', fontSize: '12.5px' }}>{proj.title}</div>
                                        <TypeLabel type={proj.type} />
                                    </td>
                                    <td style={{ padding: '12px 16px', color: '#374151', fontSize: '12.5px' }}>
                                        {proj.project_manager}
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <MiniProgress progress={proj.progress} />
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <MiniPayment paid={proj.budget_paid} total={proj.budget_total} />
                                    </td>
                                    <td style={{ padding: '12px 16px', color: '#6b7280', fontSize: '12.5px' }}>
                                        {proj.dept_owner}
                                    </td>
                                    <td style={{ padding: '12px 16px', color: '#64748b', fontSize: '12px', whiteSpace: 'nowrap' }}>
                                        <div>{proj.deadline ?? '—'}</div>
                                        {proj.days_remaining !== null && (
                                            <div style={{ fontSize: '10.5px', marginTop: '3px', fontWeight: 600, color: proj.days_remaining < 0 ? '#dc2626' : proj.days_remaining === 0 ? '#d97706' : '#94a3b8' }}>
                                                {/* Past the target completion is "Delayed"; only a target
                                                    falling on today itself is "Due today". */}
                                                {proj.days_remaining < 0
                                                    ? 'Delayed'
                                                    : proj.days_remaining === 0
                                                        ? 'Due today'
                                                        : `${proj.days_remaining} day${proj.days_remaining === 1 ? '' : 's'} left`}
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <StatusBadge status={proj.status} />
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'center' }}>
                                            <IconBtn title="View" onClick={() => router.visit(route('projects.show', proj.id))}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </IconBtn>
                                            {proj.can.update && (
                                                <IconBtn title="Edit" onClick={() => router.visit(route('projects.edit', proj.id))}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </IconBtn>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination data={projects as Paginated<unknown>} />
            </div>
        </AuthenticatedLayout>
    );
}
