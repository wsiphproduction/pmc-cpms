import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, Badge, DataTable, Field, HubProject, HubShell, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface PermitFile { id: number; filename: string; url: string; mime: string }
interface PermitRow  { id: number; label: string; doc_type: string; files: PermitFile[]; sub_project_id: number | null; sub_project_no: string | null }

export default function PermitsHub({ project, permits, canEdit = true }: { project: HubProject; permits: PermitRow[]; canEdit?: boolean }) {
    const [label, setLabel]     = useState('');
    const [docType, setDocType] = useState('');
    const [customType, setCustomType] = useState('');
    const [saving, setSaving]   = useState(false);
    const [error, setError]     = useState('');
    const filesRef = useRef<HTMLInputElement>(null);

    const handleAdd = () => {
        const files = filesRef.current?.files;
        if (!label.trim()) { setError('Document Label is required.'); return; }
        if (!docType) { setError('Please select a document type.'); return; }
        if (docType === 'OTHERS' && !customType.trim()) { setError('Please specify the custom document type.'); return; }
        if (!files?.length) { setError('Please attach at least one file.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('doc_type', docType === 'OTHERS' ? customType : docType);
        Array.from(files).forEach(f => fd.append('files[]', f));
        router.post(route('hub.permits.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setLabel(''); setDocType(''); setCustomType(''); if (filesRef.current) filesRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = permits.some(p => !!p.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (permit: PermitRow) => {
        showConfirm(`Delete "${permit.label}"?`, () => {
            router.delete(route('hub.permits.destroy', [project.id, permit.id]), { preserveScroll: true });
        }, { title: 'Delete Permit', confirmLabel: 'Delete', variant: 'danger' });
    };

    const handleDownload = (permit: PermitRow) => {
        permit.files.forEach((f, i) => {
            setTimeout(() => {
                const a = document.createElement('a');
                a.href = f.url;
                a.download = f.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }, i * 400);
        });
    };

    return (
        <HubShell>
            {confirmDialog}
            <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Project Permits & Compliance
            </h3>

            {canEdit && (
                <div style={{ background: '#f8fafc', border: '2px dashed #cbd5e1', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1.4fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Document Label">
                            <input style={inputStyle} placeholder="e.g. Site Clearance" value={label} onChange={e => setLabel(e.target.value)} />
                        </Field>
                        <Field label="Type of Document">
                            <select style={inputStyle} value={docType} onChange={e => setDocType(e.target.value)}>
                                <option value="" disabled>Select type...</option>
                                <option value="Building Permit">Building Permit</option>
                                <option value="Safety Permit">Safety Permit</option>
                                <option value="Environmental Permit">Environmental Permit</option>
                                <option value="OTHERS">Others (Specify...)</option>
                            </select>
                            {docType === 'OTHERS' && (
                                <input
                                    type="text"
                                    placeholder="Enter custom document type"
                                    value={customType}
                                    onChange={e => setCustomType(e.target.value)}
                                    style={{ ...inputStyle, marginTop: '6px', borderColor: '#2563eb' }}
                                />
                            )}
                        </Field>
                        <Field label="Upload Files (select one or more)">
                            <input type="file" multiple ref={filesRef} style={inputStyle} />
                            <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>You can select multiple PDFs or images.</div>
                        </Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleAdd} disabled={saving} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                                {saving ? 'Saving...' : 'Add Record'}
                            </button>
                        </div>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}

            <DataTable
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Label & Attached Files', 'Type', 'Actions']}
                rows={permits.map((permit, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={permit.sub_project_no} />] : []),
                    <div>
                        <strong>{permit.label}</strong>
                        <div style={{ marginTop: '5px', display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                            {permit.files.map(f => (
                                <a key={f.id} href={f.url} target="_blank" rel="noreferrer" style={{ fontSize: '12px', background: f.mime?.includes('image') ? '#eff6ff' : '#fee2e2', color: f.mime?.includes('image') ? '#1e40af' : '#991b1b', padding: '2px 8px', borderRadius: '4px', display: 'inline-flex', alignItems: 'center', gap: '4px', textDecoration: 'none' }}>
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    {f.filename}
                                </a>
                            ))}
                        </div>
                    </div>,
                    <Badge>{permit.doc_type}</Badge>,
                    <ActionBtns
                        download
                        del={canEdit && !permit.sub_project_id}
                        open={!!permit.sub_project_id}
                        onDownload={() => handleDownload(permit)}
                        onDelete={() => handleDelete(permit)}
                        onOpen={() => router.visit(route('projects.hub.permits', permit.sub_project_id!))}
                    />,
                ])}
            />
        </HubShell>
    );
}
