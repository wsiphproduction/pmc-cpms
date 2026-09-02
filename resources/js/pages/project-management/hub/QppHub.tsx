import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, Badge, DataTable, Field, HubProject, HubShell, InfoStrip, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

interface QppRow { id: number; label: string; doc_type: string; filename: string; url: string; created: string; sub_project_id: number | null; sub_project_no: string | null; versions?: FileVersion[] }

export default function QppHub({ project, qpps, canEdit = true }: { project: HubProject; qpps: QppRow[]; canEdit?: boolean }) {
    const [label, setLabel]   = useState('');
    const [docType, setDocType] = useState('');
    const [customDocType, setCustomDocType] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleUpload = () => {
        const file = fileRef.current?.files?.[0];
        const resolvedType = docType === 'Others' ? customDocType.trim() : docType;
        if (!label.trim()) { setError('Document Label is required.'); return; }
        if (!docType) { setError('Please select a type of document.'); return; }
        if (docType === 'Others' && !resolvedType) { setError('Please specify the document type.'); return; }
        if (!file) { setError('Please attach a file before uploading.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('doc_type', resolvedType);
        fd.append('file', file);
        router.post(route('hub.qpp.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setLabel(''); if (fileRef.current) fileRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = qpps.some(q => !!q.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (qpp: QppRow) => {
        showConfirm(`Delete "${qpp.label}"?`, () => {
            router.delete(route('hub.qpp.destroy', [project.id, qpp.id]), { preserveScroll: true });
        }, { title: 'Delete Document', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent="#0ea5e9" />
            {canEdit && (
                <div style={{ background: '#f0f9ff', border: '2px dashed #bae6fd', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Document Label">
                            <input style={inputStyle} placeholder="e.g. Concrete Pouring ITP" value={label} onChange={e => setLabel(e.target.value)} />
                        </Field>
                        <Field label="Type of Document">
                            <select style={inputStyle} value={docType} onChange={e => setDocType(e.target.value)}>
                                <option value="">Select type of document…</option>
                                <option>Inspection & Test Plan (ITP)</option>
                                <option>Method Statement</option>
                                <option>Quality Control Procedure</option>
                                <option>Material Approval Request</option>
                                <option>Site Inspection Report</option>
                                <option>Others</option>
                            </select>
                            {docType === 'Others' && (
                                <input style={{ ...inputStyle, marginTop: '8px' }} placeholder="Specify document type…" value={customDocType} onChange={e => setCustomDocType(e.target.value)} />
                            )}
                        </Field>
                        <Field label="Upload File"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleUpload} disabled={saving} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: '#0ea5e9', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                                {saving ? 'Uploading...' : 'Upload File'}
                            </button>
                        </div>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}
            <DataTable
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Label', 'Type of Document', 'File', 'Date', 'Actions']}
                rows={qpps.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={doc.sub_project_no} />] : []),
                    <strong>{doc.label}</strong>,
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <a href={doc.url} target="_blank" rel="noreferrer" style={{ color: '#0ea5e9', fontWeight: 600, textDecoration: 'none', fontSize: '12px' }}>{doc.filename}</a>
                            <VersionBadge versions={doc.versions} tone="#0ea5e9" />
                            {canEdit && !doc.sub_project_id && (
                                <ReplaceFileButton url={route('files.replace', [project.id, 'qpp', doc.id])} tone="#0ea5e9" />
                            )}
                        </div>
                        <FileHistory versions={doc.versions} tone="#0ea5e9" />
                    </div>,
                    <Badge tone="blue">{doc.doc_type}</Badge>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.created}</span>,
                    <ActionBtns
                        download
                        del={canEdit && !doc.sub_project_id}
                        open={!!doc.sub_project_id}
                        onDownload={() => window.open(doc.url)}
                        onDelete={() => handleDelete(doc)}
                        onOpen={() => router.visit(route('projects.hub.qpp', doc.sub_project_id!))}
                    />,
                ])}
            />
        </HubShell>
    );
}
