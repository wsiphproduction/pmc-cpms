import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface ExistingAttachment {
    id: number;
    filename: string;
    filepath: string;
    description: string | null;
    url: string;
}

interface UploadRow {
    id: number;
    file: File | null;
    description: string;
    type: 'picture' | 'drawing' | 'report';
}

interface ProjectRequestData {
    id: number;
    title: string;
    job_type: string;
    description: string;
    job_location: string;
    costcode: string | null;
    opex: boolean;
    capex: boolean;
    for_budgeting: boolean;
    status: string;
    attachments: ExistingAttachment[];
}

interface Props {
    projectRequest: ProjectRequestData;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function SectionTitle({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '6px', marginBottom: '20px' }}>
            {children}
        </div>
    );
}

function FormLabel({ required, children }: { required?: boolean; children: React.ReactNode }) {
    return (
        <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151', display: 'block', marginBottom: '6px' }}>
            {children}{required && <span style={{ color: '#ef4444', marginLeft: '3px' }}>*</span>}
        </label>
    );
}

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '8px 12px', borderRadius: '8px',
    border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
    fontFamily: 'inherit', color: '#374151', background: '#fff',
    boxSizing: 'border-box', transition: 'border-color 0.15s',
};

const focus = (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => (e.target.style.borderColor = '#2563eb');
const blur  = (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => (e.target.style.borderColor = '#e5e7eb');

// ── Upload Section ─────────────────────────────────────────────────────────
function UploadSection({ label, icon, accept, placeholder, rows, onAdd, onRemove, onFileChange, onDescChange }: {
    label: string; icon: React.ReactNode; accept: string; placeholder: string;
    rows: UploadRow[]; onAdd: () => void; onRemove: (id: number) => void;
    onFileChange: (id: number, file: File | null) => void;
    onDescChange: (id: number, val: string) => void;
}) {
    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }}>
                <FormLabel>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '7px' }}>{icon} {label}</span>
                </FormLabel>
                <button type="button" onClick={onAdd} style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1.5px solid #2563eb', background: '#fff', color: '#2563eb', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', flexShrink: 0 }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {rows.map((row, index) => (
                    <div key={row.id} style={{ background: '#fcfcfd', border: '1.5px dashed #cbd5e1', borderRadius: '8px', padding: '12px', position: 'relative', animation: 'fadeIn 0.3s ease' }}>
                        {index > 0 && (
                            <button type="button" onClick={() => onRemove(row.id)} style={{ position: 'absolute', top: '-9px', right: '-9px', width: '22px', height: '22px', borderRadius: '50%', background: '#ef4444', border: '2px solid #fff', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}>
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        )}
                        <input
                            type="file"
                            accept={accept}
                            onChange={e => onFileChange(row.id, e.target.files?.[0] ?? null)}
                            style={{ ...inputStyle, padding: '5px 8px', marginBottom: '8px', fontSize: '12px' }}
                        />
                        <input
                            type="text"
                            value={row.description}
                            onChange={e => onDescChange(row.id, e.target.value)}
                            placeholder={placeholder}
                            style={{ ...inputStyle, fontSize: '12px' }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Existing Attachment Row ────────────────────────────────────────────────
function ExistingAttachmentRow({ att, onDelete }: { att: ExistingAttachment; onDelete: (id: number) => void }) {
    const [deleted, setDeleted] = useState(false);
    if (deleted) return null;

    const ext = att.filename.split('.').pop()?.toLowerCase() ?? '';
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 10px', background: '#f8fafc', borderRadius: '7px', border: '1px solid #e5e7eb' }}>
            <div style={{ width: '32px', height: '32px', borderRadius: '6px', background: isImage ? '#dbeafe' : '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                {isImage ? (
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                ) : (
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                )}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <a href={att.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: '12.5px', fontWeight: 600, color: '#2563eb', textDecoration: 'none', display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {att.filename}
                </a>
                {att.description && <span style={{ fontSize: '11px', color: '#9ca3af' }}>{att.description}</span>}
            </div>
            <button
                type="button"
                onClick={() => { setDeleted(true); onDelete(att.id); }}
                title="Remove attachment"
                style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1px solid #fca5a5', background: '#fff7f7', color: '#dc2626', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', flexShrink: 0 }}
            >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    );
}

// ── Edit Page ──────────────────────────────────────────────────────────────
export default function Edit({ projectRequest }: Props) {
    const [title,        setTitle]        = useState(projectRequest.title);
    const [jobType,      setJobType]      = useState(projectRequest.job_type);
    const [jobTypeOther, setJobTypeOther] = useState('');
    const [description,  setDesc]         = useState(projectRequest.description);
    const [jobLocation,  setLocation]     = useState(projectRequest.job_location);
    const [costcode,     setCostcode]     = useState(projectRequest.costcode ?? '');
    const [opex,         setOpex]         = useState(projectRequest.opex);
    const [capex,        setCapex]        = useState(projectRequest.capex);
    const [forBudgeting, setForBudgeting] = useState(projectRequest.for_budgeting);
    const [processing,   setProcessing]   = useState(false);
    const [deletedAttachments, setDeletedAttachments] = useState<number[]>([]);

    let _nextId = 1;
    const makeRow = (type: UploadRow['type']): UploadRow => ({ id: _nextId++, file: null, description: '', type });

    const [pictureRows, setPictureRows] = useState<UploadRow[]>([makeRow('picture')]);
    const [drawingRows, setDrawingRows] = useState<UploadRow[]>([makeRow('drawing')]);
    const [reportRows,  setReportRows]  = useState<UploadRow[]>([makeRow('report')]);

    const addRow     = (s: React.Dispatch<React.SetStateAction<UploadRow[]>>, t: UploadRow['type']) => s(p => [...p, makeRow(t)]);
    const removeRow  = (s: React.Dispatch<React.SetStateAction<UploadRow[]>>, id: number) => s(p => p.filter(r => r.id !== id));
    const updateFile = (s: React.Dispatch<React.SetStateAction<UploadRow[]>>, id: number, file: File | null) => s(p => p.map(r => r.id === id ? { ...r, file } : r));
    const updateDesc = (s: React.Dispatch<React.SetStateAction<UploadRow[]>>, id: number, desc: string) => s(p => p.map(r => r.id === id ? { ...r, description: desc } : r));

    const markDeleted = (id: number) => setDeletedAttachments(p => [...p, id]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        const fd = new FormData();
        fd.append('_method',      'PUT');
        fd.append('title',        title);
        fd.append('job_type',     jobType === 'OTHERS' ? (jobTypeOther || 'Others') : jobType);
        fd.append('description',  description);
        fd.append('job_location', jobLocation);
        fd.append('costcode',     costcode);
        fd.append('opex',         opex ? '1' : '0');
        fd.append('capex',        capex ? '1' : '0');
        fd.append('for_budgeting',forBudgeting ? '1' : '0');

        // Deleted attachment IDs
        deletedAttachments.forEach((id, i) =>
            fd.append(`deleted_attachments[${i}]`, String(id))
        );

        // ── New attachments — always append all three fields per row ──
        let idx = 0;
        [...pictureRows, ...drawingRows, ...reportRows].forEach(row => {
            if (!row.file) return;
            fd.append(`attachments[${idx}][file]`,        row.file);
            fd.append(`attachments[${idx}][type]`,        row.type);
            fd.append(`attachments[${idx}][description]`, row.description ?? '');
            idx++;
        });

        try {
            const res = await fetch(route('requests.update', projectRequest.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':      (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Inertia':         'true',
                    'X-Inertia-Version': (document.querySelector('meta[name="inertia-version"]') as HTMLMetaElement)?.content ?? '',
                    'Accept':            'text/html, application/xhtml+xml',
                },
                body: fd,
            });
            if (res.redirected) window.location.href = res.url;
            else window.location.href = route('requests.index');
        } catch (err) {
            console.error(err);
        } finally {
            setProcessing(false);
        }
    };

    const JOB_TYPES = ['Construction', 'Design', 'Installation', 'Study/Report', 'Modification', 'Estimate', 'Demolition/Removal', 'Retrofitting', 'Others'];

    return (
        <AuthenticatedLayout>
            <Head title={`Edit Request — ${projectRequest.title}`} />
            <style>{`@keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }`}</style>

            <div style={{ marginBottom: '20px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 3px', letterSpacing: '-0.3px' }}>Edit Project Request</h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>
                    Editing: <strong style={{ color: '#374151' }}>{projectRequest.title}</strong>
                    {' · '}
                    <span style={{ textTransform: 'capitalize', color: '#6b7280' }}>{projectRequest.status}</span>
                </p>
            </div>

            <form onSubmit={handleSubmit} style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '28px 30px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>

                <SectionTitle>General Information</SectionTitle>

                <div style={{ marginBottom: '18px' }}>
                    <FormLabel required>Project Title</FormLabel>
                    <input type="text" value={title} onChange={e => setTitle(e.target.value)} onFocus={focus} onBlur={blur} required style={inputStyle} />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '18px', marginBottom: '18px' }}>
                    <div>
                        <FormLabel required>Job Type</FormLabel>
                        <select value={jobType} onChange={e => setJobType(e.target.value)} onFocus={focus} onBlur={blur} required style={{ ...inputStyle, cursor: 'pointer' }}>
                            <option value="" disabled>Select Job Type…</option>
                            {JOB_TYPES.map(t => <option key={t} value={t === 'Others' ? 'OTHERS' : t}>{t}</option>)}
                        </select>
                        {jobType === 'OTHERS' && (
                            <input type="text" value={jobTypeOther} onChange={e => setJobTypeOther(e.target.value)} onFocus={focus} onBlur={blur} placeholder="Please specify…" style={{ ...inputStyle, marginTop: '8px', borderColor: '#2563eb' }} />
                        )}
                    </div>
                    <div>
                        <FormLabel required>Job Location</FormLabel>
                        <input type="text" value={jobLocation} onChange={e => setLocation(e.target.value)} onFocus={focus} onBlur={blur} required style={inputStyle} />
                    </div>
                </div>

                <div style={{ marginBottom: '28px' }}>
                    <FormLabel required>Project Description</FormLabel>
                    <textarea value={description} onChange={e => setDesc(e.target.value)} onFocus={focus as never} onBlur={blur as never} rows={4} required style={{ ...inputStyle, resize: 'vertical', lineHeight: 1.6 }} />
                </div>

                <SectionTitle>Financials &amp; Budgeting</SectionTitle>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '18px', marginBottom: '28px' }}>
                    <div>
                        <FormLabel>Cost Code</FormLabel>
                        <input type="text" value={costcode} onChange={e => setCostcode(e.target.value)} onFocus={focus} onBlur={blur} placeholder="Enter assigned cost code" style={inputStyle} />
                    </div>
                    <div>
                        <FormLabel>Funding Classification</FormLabel>
                        <div style={{ background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '14px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-around', height: 'calc(100% - 22px)' }}>
                            {[
                                ['opex',         'OPEX',          opex,         setOpex]         as const,
                                ['capex',        'CAPEX',         capex,        setCapex]        as const,
                                ['for_budgeting','For Budgeting', forBudgeting, setForBudgeting] as const,
                            ].map(([key, lbl, val, setter]) => (
                                <label key={key} style={{ display: 'flex', alignItems: 'center', gap: '7px', fontSize: '13px', fontWeight: 600, color: '#374151', cursor: 'pointer' }}>
                                    <input type="checkbox" checked={val} onChange={e => setter(e.target.checked)} style={{ width: '15px', height: '15px', accentColor: '#2563eb', cursor: 'pointer' }} />
                                    {lbl}
                                </label>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Existing attachments */}
                {projectRequest.attachments.length > 0 && (
                    <>
                        <SectionTitle>Current Attachments</SectionTitle>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '28px' }}>
                            {projectRequest.attachments.map(att => (
                                <ExistingAttachmentRow key={att.id} att={att} onDelete={markDeleted} />
                            ))}
                            {deletedAttachments.length > 0 && (
                                <p style={{ fontSize: '11.5px', color: '#f59e0b', margin: '6px 0 0' }}>
                                    ⚠ {deletedAttachments.length} attachment{deletedAttachments.length > 1 ? 's' : ''} will be removed on save.
                                </p>
                            )}
                        </div>
                    </>
                )}

                {/* New attachments */}
                <SectionTitle>Add New Attachments</SectionTitle>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '20px', marginBottom: '32px' }}>
                    <UploadSection
                        label="Picture Attachments" accept="image/*" placeholder="Image description" rows={pictureRows}
                        onAdd={() => addRow(setPictureRows, 'picture')} onRemove={id => removeRow(setPictureRows, id)}
                        onFileChange={(id, f) => updateFile(setPictureRows, id, f)} onDescChange={(id, v) => updateDesc(setPictureRows, id, v)}
                        icon={<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>}
                    />
                    <UploadSection
                        label="Draft Drawings" accept=".pdf,.dwg,.jpg" placeholder="Drawing reference" rows={drawingRows}
                        onAdd={() => addRow(setDrawingRows, 'drawing')} onRemove={id => removeRow(setDrawingRows, id)}
                        onFileChange={(id, f) => updateFile(setDrawingRows, id, f)} onDescChange={(id, v) => updateDesc(setDrawingRows, id, v)}
                        icon={<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><path d="M18 8l-6-6-6 6"/><path d="M6 20V14"/></svg>}
                    />
                    <UploadSection
                        label="Reports Attachments" accept=".pdf,.doc,.docx" placeholder="Report summary" rows={reportRows}
                        onAdd={() => addRow(setReportRows, 'report')} onRemove={id => removeRow(setReportRows, id)}
                        onFileChange={(id, f) => updateFile(setReportRows, id, f)} onDescChange={(id, v) => updateDesc(setReportRows, id, v)}
                        icon={<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>}
                    />
                </div>

                <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <a href={route('requests.index')} style={{ fontSize: '13px', color: '#9ca3af', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '5px' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to list
                    </a>
                    <button type="submit" disabled={processing} style={{ padding: '9px 28px', borderRadius: '8px', border: 'none', background: processing ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: processing ? 'not-allowed' : 'pointer', transition: 'background 0.15s' }}>
                        {processing ? 'Saving…' : 'Save Changes'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}