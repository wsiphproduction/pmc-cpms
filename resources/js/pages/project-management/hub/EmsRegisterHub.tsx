import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

interface EntryRow { id: number; description: string; filename: string; url: string; uploaded_by: string | null; created: string; sub_project_id: number | null; sub_project_no: string | null; versions?: FileVersion[] }

const TONE = '#16a34a';

export default function EmsRegisterHub({ project, entries, canEdit = true }: { project: HubProject; entries: EntryRow[]; canEdit?: boolean }) {
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
        router.post(route('hub.ems-register.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setDescription(''); if (fileRef.current) fileRef.current.value = ''; },
            onError: errors => setError(errors.file ?? errors.description ?? 'Upload failed.'),
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = entries.some(e => !!e.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (entry: EntryRow) => {
        showConfirm(`Delete "${entry.filename}" from the EMS Register?`, () => {
            router.delete(route('hub.ems-register.destroy', [project.id, entry.id]), { preserveScroll: true });
        }, { title: 'Delete File', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent={TONE} />
            {canEdit && (
                <div style={{ background: '#f0fdf4', border: '2px dashed #bbf7d0', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
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
            <DataTable
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Description', 'File', 'Uploaded By', 'Date', 'Actions']}
                rows={entries.map((entry, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={entry.sub_project_no} />] : []),
                    <span style={{ color: '#0f172a', whiteSpace: 'pre-wrap' }}>{entry.description}</span>,
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <a href={entry.url} target="_blank" rel="noreferrer" style={{ color: TONE, fontWeight: 600, textDecoration: 'none', fontSize: '12px' }}>{entry.filename}</a>
                            <VersionBadge versions={entry.versions} tone={TONE} />
                            {canEdit && !entry.sub_project_id && (
                                <ReplaceFileButton url={route('files.replace', [project.id, 'ems-register', entry.id])} tone={TONE} />
                            )}
                        </div>
                        <FileHistory
                            versions={entry.versions}
                            tone={TONE}
                            restoreUrl={canEdit && !entry.sub_project_id ? v => route('files.restore', [project.id, 'ems-register', entry.id, v]) : undefined}
                        />
                    </div>,
                    <span style={{ fontSize: '12px', color: '#475569' }}>{entry.uploaded_by ?? '—'}</span>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{entry.created}</span>,
                    <ActionBtns
                        download
                        del={canEdit && !entry.sub_project_id}
                        open={!!entry.sub_project_id}
                        onDownload={() => window.open(entry.url)}
                        onDelete={() => handleDelete(entry)}
                        onOpen={() => router.visit(route('projects.hub.ems-register', entry.sub_project_id!))}
                    />,
                ])}
            />
        </HubShell>
    );
}
