import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    next_project_no: string;
    managers: SelectOption[];
    sites: SelectOption[];
    assets: SelectOption[];
    departments: SelectOption[];
    classes: SelectOption[];
    priorities: SelectOption[];
    statuses: SelectOption[];
    workForces: SelectOption[];
    costCodes: SelectOption[];
    categories: SelectOption[];
    serviceTypes: SelectOption[];
    structures: SelectOption[];
    project?: Partial<ProjectFormData> & { id?: number; proposal_document_url?: string | null };
}

// ── Form Data ──────────────────────────────────────────────────────────────
interface ProjectFormData {
    title: string;
    project_manager: string;
    site: string;
    asset_id: string;
    cls: string;
    priority: string;
    status: string;
    work_force: string;
    wr_no: string;
    wr_date: string;
    dept_owner: string;
    cost_code: string;
    category: string;
    service_type: string;
    deadline: string;
    owner_email: string;
    structure_type: string;
    jip: boolean;
    need_civil: boolean;
    need_electrical: boolean;
    need_mechanical: boolean;
    notes: string;
    project_request_id: string;
    project_type: string;
    [key: string]: string | boolean | File | null;
}

// ── Sub-components ─────────────────────────────────────────────────────────
function SectionLabel({ num, children }: { num: string; children: React.ReactNode }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: '20px' }}>
            <span style={{ fontSize: '10px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', whiteSpace: 'nowrap' }}>
                {num}. {children}
            </span>
            <div style={{ flex: 1, height: '1px', background: '#e2e8f0', marginLeft: '14px' }} />
        </div>
    );
}

function FormLabel({ children, required }: { children: React.ReactNode; required?: boolean }) {
    return (
        <label style={{ fontWeight: 600, fontSize: '13px', marginBottom: '6px', display: 'block', color: '#374151' }}>
            {children}
            {required && <span style={{ color: '#ef4444', marginLeft: '3px' }}>*</span>}
        </label>
    );
}

function InputField({
    type = 'text', value, onChange, placeholder, required, readOnly, error,
}: {
    type?: string; value: string; onChange?: (v: string) => void;
    placeholder?: string; required?: boolean; readOnly?: boolean; error?: string;
}) {
    return (
        <div>
            <input
                type={type}
                value={value}
                onChange={e => onChange?.(e.target.value)}
                placeholder={placeholder}
                required={required}
                readOnly={readOnly}
                style={{
                    width: '100%', padding: '9px 13px', borderRadius: '8px', fontSize: '13.5px',
                    border: `1px solid ${error ? '#fca5a5' : '#e2e8f0'}`,
                    background: readOnly ? '#f8fafc' : '#fff',
                    color: readOnly ? '#475569' : '#1e293b',
                    fontWeight: readOnly ? 600 : 400,
                    outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box',
                    transition: 'border-color 0.15s',
                }}
                onFocus={e => { if (!readOnly) e.target.style.borderColor = '#2563eb'; e.target.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.08)'; }}
                onBlur={e => { e.target.style.borderColor = error ? '#fca5a5' : '#e2e8f0'; e.target.style.boxShadow = 'none'; }}
            />
            {error && <div style={{ fontSize: '11.5px', color: '#ef4444', marginTop: '4px' }}>{error}</div>}
        </div>
    );
}

function SelectField({
    value, onChange, options, placeholder, required, error, id,
}: {
    value: string; onChange: (v: string) => void;
    options: SelectOption[]; placeholder?: string; required?: boolean; error?: string; id: string;
}) {
    const [open, setOpen] = useState(false);
    const selectedOption = options.find(option => option.value === value);
    const [inputValue, setInputValue] = useState(selectedOption?.label ?? value);

    useEffect(() => {
        const selected = options.find(option => option.value === value);
        setInputValue(selected?.label ?? value);
    }, [options, value]);

    const filtered = useMemo(() => {
        const needle = inputValue.trim().toLowerCase();
        return needle
            ? options.filter(option => option.label.toLowerCase().includes(needle) || option.value.toLowerCase().includes(needle)).slice(0, 8)
            : options.slice(0, 8);
    }, [inputValue, options]);

    return (
        <div style={{ position: 'relative' }}>
            <input
                value={inputValue}
                onChange={e => { setInputValue(e.target.value); onChange(e.target.value); setOpen(true); }}
                required={required}
                placeholder={placeholder}
                role="combobox"
                aria-expanded={open}
                aria-controls={id}
                autoComplete="off"
                style={{
                    width: '100%', padding: '9px 13px', borderRadius: '8px', fontSize: '13.5px',
                    border: `1px solid ${error ? '#fca5a5' : '#e2e8f0'}`,
                    background: '#fff', color: inputValue ? '#1e293b' : '#9ca3af',
                    outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box',
                    transition: 'border-color 0.15s', cursor: 'text', paddingRight: '34px',
                }}
                onFocus={e => { e.target.style.borderColor = '#2563eb'; e.target.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.08)'; setOpen(true); }}
                onBlur={e => { e.target.style.borderColor = error ? '#fca5a5' : '#e2e8f0'; e.target.style.boxShadow = 'none'; window.setTimeout(() => setOpen(false), 120); }}
            />
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2" style={{ position: 'absolute', right: '11px', top: '18px', pointerEvents: 'none' }}>
                <polyline points="6 9 12 15 18 9" />
            </svg>
            {open && (
                <div id={id} role="listbox" style={{ position: 'absolute', top: 'calc(100% + 5px)', left: 0, right: 0, zIndex: 60, background: '#fff', border: '1px solid #dbe3ef', borderRadius: '8px', boxShadow: '0 14px 32px rgba(15,23,42,0.14)', overflow: 'hidden', maxHeight: '220px', overflowY: 'auto' }}>
                    {filtered.length ? filtered.map(option => (
                        <button
                            key={option.value}
                            type="button"
                            onMouseDown={e => e.preventDefault()}
                            onClick={() => { onChange(option.value); setInputValue(option.label); setOpen(false); }}
                            style={{ width: '100%', border: 'none', background: option.value === value ? '#eff6ff' : '#fff', padding: '9px 12px', textAlign: 'left', fontSize: '13px', color: '#334155', cursor: 'pointer' }}
                        >
                            {option.label}
                        </button>
                    )) : (
                        <div style={{ padding: '10px 12px', fontSize: '12.5px', color: '#94a3b8' }}>No results found</div>
                    )}
                </div>
            )}
            {error && <div style={{ fontSize: '11.5px', color: '#ef4444', marginTop: '4px' }}>{error}</div>}
        </div>
    );
}

function CheckToggle({ id, label, checked, onChange, isSwitch }: {
    id: string; label: string; checked: boolean; onChange: (v: boolean) => void; isSwitch?: boolean;
}) {
    return (
        <label htmlFor={id} style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', userSelect: 'none' }}>
            {isSwitch ? (
                <div
                    onClick={() => onChange(!checked)}
                    style={{
                        width: '38px', height: '22px', borderRadius: '11px', position: 'relative',
                        background: checked ? '#2563eb' : '#e2e8f0', transition: 'background 0.2s', flexShrink: 0,
                    }}
                >
                    <div style={{
                        position: 'absolute', top: '3px', left: checked ? '19px' : '3px',
                        width: '16px', height: '16px', borderRadius: '50%', background: '#fff',
                        transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)',
                    }} />
                </div>
            ) : (
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    onChange={e => onChange(e.target.checked)}
                    style={{ width: '15px', height: '15px', cursor: 'pointer', accentColor: '#2563eb', flexShrink: 0 }}
                />
            )}
            <span style={{ fontSize: '13px', fontWeight: isSwitch ? 700 : 400, color: '#374151' }}>{label}</span>
        </label>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function ProjectCreate({
    next_project_no,
    managers,
    sites,
    assets,
    departments,
    classes,
    priorities,
    statuses,
    workForces,
    costCodes,
    categories,
    serviceTypes,
    structures,
    project,
}: Props) {
    const isEditing = Boolean(project?.id);
    const { data, setData, processing, errors, setError, clearErrors } = useForm<ProjectFormData>({
        project_request_id: project?.project_request_id ?? '',
        title:           project?.title ?? '',
        project_manager: project?.project_manager ?? '',
        site:            project?.site ?? '',
        asset_id:        project?.asset_id ?? '',
        cls:             project?.cls ?? '',
        priority:        project?.priority ?? '',
        status:          project?.status ?? '',
        work_force:      project?.work_force ?? '',
        wr_no:           project?.wr_no ?? '',
        wr_date:         project?.wr_date ?? '',
        dept_owner:      project?.dept_owner ?? '',
        cost_code:       project?.cost_code ?? '',
        category:        project?.category ?? '',
        service_type:    project?.service_type ?? '',
        deadline:        project?.deadline ?? '',
        owner_email:     project?.owner_email ?? '',
        structure_type:  project?.structure_type ?? '',
        jip:             project?.jip ?? false,
        need_civil:      project?.need_civil ?? false,
        need_electrical: project?.need_electrical ?? false,
        need_mechanical: project?.need_mechanical ?? false,
        notes:           project?.notes ?? '',
        project_type:    project?.project_type ?? 'minor',
    });

    const [proposalFile, setProposalFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const set = (key: keyof ProjectFormData) => (v: string | boolean) => setData(key, v);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors('proposal_document' as any);

        // Client-side guard: major projects need a proposal document
        if (data.project_type === 'major' && !proposalFile && !project?.proposal_document_url) {
            setError('proposal_document' as any, 'Approved proposal document is required for major projects.');
            return;
        }

        const payload: Record<string, string | boolean | File | null> = { ...data };
        if (proposalFile) payload.proposal_document = proposalFile;

        if (isEditing) {
            router.post(route('projects.update', project!.id!), { ...payload, _method: 'put' }, { forceFormData: true });
            return;
        }
        router.post(route('projects.store'), payload, { forceFormData: true });
    };

    const col2: React.CSSProperties = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' };
    const col3: React.CSSProperties = { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '20px' };
    const col4: React.CSSProperties = { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '20px' };

    return (
        <AuthenticatedLayout>
            <Head title={isEditing ? 'Edit Project' : 'Create New Project'} />

            {/* Page Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    {isEditing ? 'Edit Project' : 'Create New Project'}
                </h1>
                <nav style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12.5px', color: '#9ca3af' }}>
                    <Link href={route('projects.index')} style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 500 }}>Projects</Link>
                    <span>/</span>
                    <span style={{ color: '#374151' }}>{isEditing ? 'Edit' : 'Add New'}</span>
                </nav>
            </div>

            <form onSubmit={handleSubmit}>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '30px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>

                    {/* ── Section 01 ──────────────────────────────────── */}
                    <SectionLabel num="01">Project Identification</SectionLabel>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 3fr 1fr', gap: '20px', marginBottom: '20px', alignItems: 'start' }}>
                        <div>
                            <FormLabel>Project No</FormLabel>
                            <InputField value={next_project_no} readOnly />
                        </div>
                        <div>
                            <FormLabel required>Project Title</FormLabel>
                            <InputField
                                value={data.title}
                                onChange={set('title')}
                                placeholder="Enter complete project title"
                                required
                                error={errors.title}
                            />
                        </div>
                        {/* Project Type */}
                        <div>
                            <FormLabel required>Project Type</FormLabel>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                {(['minor', 'major'] as const).map(type => (
                                    <button
                                        key={type}
                                        type="button"
                                        onClick={() => { setData('project_type', type); if (type === 'minor') { setProposalFile(null); if (fileInputRef.current) fileInputRef.current.value = ''; clearErrors('proposal_document' as any); } }}
                                        style={{
                                            flex: 1, padding: '9px 0', borderRadius: '8px', fontSize: '13px', fontWeight: 700,
                                            cursor: 'pointer', textTransform: 'capitalize',
                                            border: data.project_type === type ? 'none' : '1.5px solid #e2e8f0',
                                            background: data.project_type === type
                                                ? (type === 'major' ? '#1e3a8a' : '#0f172a')
                                                : '#fff',
                                            color: data.project_type === type ? '#fff' : '#64748b',
                                            transition: 'all 0.15s',
                                        }}
                                    >
                                        {type === 'major' ? '★ Major' : 'Minor'}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Proposal document upload — Major only */}
                    {data.project_type === 'major' && (
                        <div style={{
                            background: '#fff7ed', border: `1.5px solid ${(errors as any).proposal_document ? '#fca5a5' : '#fed7aa'}`,
                            borderRadius: '10px', padding: '18px 20px', marginBottom: '20px',
                            display: 'grid', gridTemplateColumns: '1fr auto', gap: '16px', alignItems: 'start',
                        }}>
                            <div>
                                <div style={{ fontSize: '11px', fontWeight: 800, color: '#c2410c', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '6px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    Approved Proposal Document <span style={{ color: '#ef4444' }}>*</span>
                                </div>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".pdf,.doc,.docx"
                                    onChange={e => { setProposalFile(e.target.files?.[0] ?? null); clearErrors('proposal_document' as any); }}
                                    style={{ width: '100%', boxSizing: 'border-box', padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #fdba74', background: '#fff', fontSize: '12.5px', cursor: 'pointer', fontFamily: 'inherit' }}
                                />
                                {proposalFile && (
                                    <div style={{ marginTop: '5px', fontSize: '12px', color: '#ea580c', fontWeight: 600 }}>
                                        Selected: {proposalFile.name}
                                    </div>
                                )}
                                {(errors as any).proposal_document && (
                                    <div style={{ marginTop: '5px', fontSize: '11.5px', color: '#ef4444', fontWeight: 600 }}>
                                        {(errors as any).proposal_document}
                                    </div>
                                )}
                                <div style={{ marginTop: '4px', fontSize: '11px', color: '#9a3412' }}>
                                    PDF, Word only · Max 20 MB · Required for major projects
                                </div>
                            </div>
                            {/* Existing file link (edit mode) */}
                            {project?.proposal_document_url && !proposalFile && (
                                <div style={{ paddingTop: '20px' }}>
                                    <a
                                        href={project.proposal_document_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', padding: '7px 13px', borderRadius: '7px', border: '1px solid #fdba74', background: '#fff7ed', color: '#c2410c', fontSize: '12px', fontWeight: 700, textDecoration: 'none', whiteSpace: 'nowrap' }}
                                    >
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        View Current
                                    </a>
                                    <div style={{ fontSize: '10.5px', color: '#9a3412', marginTop: '3px', textAlign: 'center' }}>Upload to replace</div>
                                </div>
                            )}
                        </div>
                    )}

                    <div style={{ ...col3, marginBottom: '32px' }}>
                        <div>
                            <FormLabel required>Project Manager</FormLabel>
                            <SelectField
                                id="project-manager-options"
                                value={data.project_manager}
                                onChange={set('project_manager')}
                                options={managers}
                                placeholder="Select Manager"
                                required
                                error={errors.project_manager}
                            />
                        </div>
                        <div>
                            <FormLabel required>Site</FormLabel>
                            <SelectField
                                id="site-options"
                                value={data.site}
                                onChange={set('site')}
                                options={sites}
                                placeholder="Select Site Location"
                                required
                                error={errors.site}
                            />
                        </div>
                        <div>
                            <FormLabel required>Asset ID</FormLabel>
                            <SelectField
                                id="asset-options"
                                value={data.asset_id}
                                onChange={set('asset_id')}
                                options={assets}
                                placeholder="Select Asset"
                                required
                                error={errors.asset_id}
                            />
                        </div>
                    </div>

                    {/* ── Section 02 ──────────────────────────────────── */}
                    <SectionLabel num="02">Work Request & Classification</SectionLabel>
                    <div style={{ ...col4, marginBottom: '20px' }}>
                        <div>
                            <FormLabel required>Class</FormLabel>
                            <SelectField
                                id="class-options"
                                value={data.cls}
                                onChange={set('cls')}
                                options={classes}
                                placeholder="Select Class"
                                required
                                error={errors.cls}
                            />
                        </div>
                        <div>
                            <FormLabel required>Priority No.</FormLabel>
                            <SelectField
                                id="priority-options"
                                value={data.priority}
                                onChange={set('priority')}
                                options={priorities}
                                placeholder="Select Priority"
                                required
                                error={errors.priority}
                            />
                        </div>
                        <div>
                            <FormLabel required>Project Status</FormLabel>
                            <SelectField
                                id="status-options"
                                value={data.status}
                                onChange={set('status')}
                                options={statuses}
                                placeholder="Select Status"
                                required
                                error={errors.status}
                            />
                        </div>
                        <div>
                            <FormLabel required>Work Force</FormLabel>
                            <SelectField
                                id="work-force-options"
                                value={data.work_force}
                                onChange={set('work_force')}
                                options={workForces}
                                placeholder="Select Work Force"
                                required
                                error={errors.work_force}
                            />
                        </div>
                    </div>
                    <div style={{ ...col4, marginBottom: '32px' }}>
                        <div>
                            <FormLabel required>WR No.</FormLabel>
                            <InputField
                                type="text"
                                value={data.wr_no}
                                onChange={set('wr_no')}
                                placeholder="0000"
                                required
                                error={errors.wr_no}
                            />
                        </div>
                        <div>
                            <FormLabel required>WR Date Received</FormLabel>
                            <InputField
                                type="date"
                                value={data.wr_date}
                                onChange={set('wr_date')}
                                required
                                error={errors.wr_date}
                            />
                        </div>
                        <div>
                            <FormLabel required>Department Owner</FormLabel>
                            <SelectField
                                id="department-options"
                                value={data.dept_owner}
                                onChange={set('dept_owner')}
                                options={departments}
                                placeholder="Select Department"
                                required
                                error={errors.dept_owner}
                            />
                        </div>
                        <div>
                            <FormLabel required>Cost Code</FormLabel>
                            <SelectField
                                id="cost-code-options"
                                value={data.cost_code}
                                onChange={set('cost_code')}
                                options={costCodes}
                                placeholder="Select Cost Code"
                                required
                                error={errors.cost_code}
                            />
                        </div>
                    </div>

                    {/* ── Section 03 ──────────────────────────────────── */}
                    <SectionLabel num="03">Timeline & Specifications</SectionLabel>
                    <div style={{ ...col3, marginBottom: '20px' }}>
                        <div>
                            <FormLabel required>Category</FormLabel>
                            <SelectField
                                id="category-options"
                                value={data.category}
                                onChange={set('category')}
                                options={categories}
                                placeholder="Select Category"
                                required
                                error={errors.category}
                            />
                        </div>
                        <div>
                            <FormLabel required>Service Type</FormLabel>
                            <SelectField
                                id="service-type-options"
                                value={data.service_type}
                                onChange={set('service_type')}
                                options={serviceTypes}
                                placeholder="Select Service Type"
                                required
                                error={errors.service_type}
                            />
                        </div>
                        <div>
                            <FormLabel required>Deadline</FormLabel>
                            <InputField
                                type="date"
                                value={data.deadline}
                                onChange={set('deadline')}
                                required
                                error={errors.deadline}
                            />
                        </div>
                    </div>
                    <div style={{ ...col2, marginBottom: '20px' }}>
                        <div>
                            <FormLabel>Project Owner's Email</FormLabel>
                            <InputField
                                type="email"
                                value={data.owner_email}
                                onChange={set('owner_email')}
                                placeholder="email@construction.com"
                                error={errors.owner_email}
                            />
                        </div>
                        <div>
                            <FormLabel>Structure Type</FormLabel>
                            <SelectField
                                id="structure-options"
                                value={data.structure_type}
                                onChange={set('structure_type')}
                                options={structures}
                                placeholder="Select Structure Type"
                                error={errors.structure_type}
                            />
                        </div>
                    </div>

                    {/* Spec Box */}
                    <div style={{ background: '#f1f5f9', borderRadius: '10px', padding: '18px 22px', marginBottom: '20px', display: 'grid', gridTemplateColumns: '1fr 3fr', gap: '0', alignItems: 'center' }}>
                        <div style={{ borderRight: '1px solid #e2e8f0', paddingRight: '20px' }}>
                            <CheckToggle
                                id="jip"
                                label="JIP"
                                checked={data.jip}
                                onChange={v => setData('jip', v)}
                                isSwitch
                            />
                        </div>
                        <div style={{ paddingLeft: '24px', display: 'flex', gap: '32px', flexWrap: 'wrap' }}>
                            <CheckToggle id="civil"    label="Civil Works Plans"  checked={data.need_civil}      onChange={v => setData('need_civil', v)} />
                            <CheckToggle id="elec"     label="Electrical Plans"   checked={data.need_electrical} onChange={v => setData('need_electrical', v)} />
                            <CheckToggle id="mech"     label="Mechanical Plans"   checked={data.need_mechanical} onChange={v => setData('need_mechanical', v)} />
                        </div>
                    </div>

                    {/* Notes */}
                    <div style={{ marginBottom: '8px' }}>
                        <FormLabel>Notes</FormLabel>
                        <textarea
                            rows={3}
                            value={data.notes}
                            onChange={e => setData('notes', e.target.value)}
                            placeholder="Additional details..."
                            style={{
                                width: '100%', padding: '9px 13px', borderRadius: '8px', fontSize: '13.5px',
                                border: '1px solid #e2e8f0', resize: 'vertical', outline: 'none',
                                fontFamily: 'inherit', boxSizing: 'border-box', transition: 'border-color 0.15s',
                            }}
                            onFocus={e => { e.target.style.borderColor = '#2563eb'; e.target.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.08)'; }}
                            onBlur={e => { e.target.style.borderColor = '#e2e8f0'; e.target.style.boxShadow = 'none'; }}
                        />
                    </div>

                    {/* Footer Buttons */}
                    <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '20px', marginTop: '16px', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                        <Link
                            href={route('projects.index')}
                            style={{ padding: '10px 22px', borderRadius: '8px', border: '1px solid #e2e8f0', background: '#fff', fontSize: '13.5px', fontWeight: 500, color: '#374151', textDecoration: 'none', display: 'inline-flex', alignItems: 'center' }}
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            style={{
                                padding: '10px 28px', borderRadius: '8px', border: 'none',
                                background: processing ? '#93c5fd' : '#2563eb',
                                color: '#fff', fontSize: '13.5px', fontWeight: 600,
                                cursor: processing ? 'not-allowed' : 'pointer',
                                display: 'inline-flex', alignItems: 'center', gap: '7px',
                                boxShadow: '0 1px 3px rgba(37,99,235,0.3)',
                                transition: 'background 0.15s',
                            }}
                        >
                            {processing ? (
                                <>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    Saving…
                                </>
                            ) : (
                                <>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    {isEditing ? 'Update Project' : 'Register Project'}
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </form>

            <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
        </AuthenticatedLayout>
    );
}
