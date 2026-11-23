import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

interface DocumentRow { id: number; description: string; filename: string; url: string; uploaded_by: string | null; created: string; sub_project_id: number | null; sub_project_no: string | null; versions?: FileVersion[] }

const TONE = '#2563eb';

/**
 * One hub section of the shared document register — SOW, drawings, BOQ,
 * as-built drawings and the rest. Each section is the same shape: a file and a
 * description, filed under the section's slug.
 */
export default function DocumentsHub({ project, section, label, documents, canEdit = true }: {
    project: HubProject;
    /** The section's slug, which is also its hub URL segment. */
    section: string;
    label: string;
    documents: DocumentRow[];
    canEdit?: boolean;
}) {
    const [description, setDescription] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleUpload = () => {
        const file = fileRef.current?.files?.[0];
        if (!description.trim()) { setError('Description is required.'); return; }
        if (!file) { setError('Please attach a file before uploading.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('description', description);
        fd.append('file', file);
        router.post(route('hub.docs.store', [project.id, section]), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setDescription(''); if (fileRef.current) fileRef.current.value = ''; },
            onError: errors => setError(errors.file ?? errors.description ?? 'Upload failed.'),
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = documents.some(d => !!d.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (doc: DocumentRow) => {
        showConfirm(`Delete "${doc.filename}" from ${label}?`, () => {
            router.delete(route('hub.docs.destroy', [project.id, doc.id]), { preserveScroll: true });
        }, { title: 'Delete File', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent={TONE} />
            {canEdit && (
                <div style={{ background: '#eff6ff', border: '2px dashed #bfdbfe', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Description">
                            <input style={inputStyle} placeholder="What is this file?" value={description} onChange={e => setDescription(e.target.value)} />
                        </Field>
                        <Field label="Upload File"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleUpload} disabled={saving} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: TONE, color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                                {saving ? 'Uploading...' : 'Upload File'}
                            </button>
                        </div>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}
            {documents.length === 0 ? (
                <div style={{ padding: '40px 24px', border: '1px dashed #e2e8f0', borderRadius: '8px', textAlign: 'center', color: '#94a3b8' }}>
                    <div style={{ fontSize: '13.5px', fontWeight: 600, color: '#64748b', marginBottom: '4px' }}>No files yet</div>
                    <div style={{ fontSize: '12.5px' }}>
                        {canEdit ? `Files uploaded to ${label} will be listed here.` : `Nothing has been filed under ${label} yet.`}
                    </div>
                </div>
            ) : <DataTable
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Description', 'File', 'Uploaded By', 'Date', 'Actions']}
                rows={documents.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={doc.sub_project_no} />] : []),
                    <span style={{ color: '#0f172a', whiteSpace: 'pre-wrap' }}>{doc.description}</span>,
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <a href={doc.url} target="_blank" rel="noreferrer" style={{ color: TONE, fontWeight: 600, textDecoration: 'none', fontSize: '12px' }}>{doc.filename}</a>
                            <VersionBadge versions={doc.versions} tone={TONE} />
                            {canEdit && !doc.sub_project_id && (
                                <ReplaceFileButton url={route('files.replace', [project.id, 'doc', doc.id])} tone={TONE} />
                            )}
                        </div>
                        <FileHistory
                            versions={doc.versions}
                            tone={TONE}
                            restoreUrl={canEdit && !doc.sub_project_id ? v => route('files.restore', [project.id, 'doc', doc.id, v]) : undefined}
                        />
                    </div>,
                    <span style={{ fontSize: '12px', color: '#475569' }}>{doc.uploaded_by ?? '—'}</span>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.created}</span>,
                    <ActionBtns
                        download
                        del={canEdit && !doc.sub_project_id}
                        open={!!doc.sub_project_id}
                        onDownload={() => window.open(doc.url)}
                        onDelete={() => handleDelete(doc)}
                        onOpen={() => router.visit(route('projects.hub.' + section, doc.sub_project_id!))}
                    />,
                ])}
            />}
        </HubShell>
    );
}
