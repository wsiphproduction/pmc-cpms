import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

interface MinutesRow { id: number; meeting_date: string; filename: string; url: string; uploaded_by: string; uploaded_at: string; sub_project_id: number | null; sub_project_no: string | null; versions?: FileVersion[] }

const ACCEPT = '.pdf,.doc,.docx,.odt,.rtf,.txt,.xls,.xlsx,.jpg,.jpeg,.png';
const TONE = '#0d9488';

export default function MomHub({ project, minutes, canEdit = true }: { project: HubProject; minutes: MinutesRow[]; canEdit?: boolean }) {
    const [meetingDate, setMeetingDate] = useState('');
    const [saving, setSaving]           = useState(false);
    const [error, setError]             = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleUpload = () => {
        const file = fileRef.current?.files?.[0];
        if (!meetingDate) { setError('Please select the meeting date.'); return; }
        if (!file) { setError('Please attach the minutes document.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('meeting_date', meetingDate);
        fd.append('file', file);
        router.post(route('hub.mom.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setMeetingDate(''); if (fileRef.current) fileRef.current.value = ''; },
            onError: errs => setError(Object.values(errs)[0] ?? 'Upload failed.'),
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = minutes.some(m => !!m.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (row: MinutesRow) => {
        showConfirm(`Delete the minutes of ${row.meeting_date}?`, () => {
            router.delete(route('hub.mom.destroy', [project.id, row.id]), { preserveScroll: true });
        }, { title: 'Delete Minutes', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent={TONE} />
            {canEdit && (
                <div style={{ background: '#f0fdfa', border: '2px dashed #99f6e4', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '200px 1fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Meeting Date">
                            <input type="date" style={inputStyle} value={meetingDate} onChange={e => setMeetingDate(e.target.value)} />
                        </Field>
                        <Field label="Minutes Document (PDF, Word, Excel or scan — up to 20 MB)">
                            <input type="file" ref={fileRef} accept={ACCEPT} style={inputStyle} />
                        </Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleUpload} disabled={saving} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: TONE, color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                                {saving ? 'Uploading...' : 'Add Minutes'}
                            </button>
                        </div>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}
            {minutes.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#94a3b8', fontSize: '13px', border: '1px dashed #e2e8f0', borderRadius: '8px' }}>
                    No minutes uploaded yet.
                </div>
            ) : (
                <DataTable
                    headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Meeting Date', 'File', 'Uploaded By', 'Date Uploaded', 'Actions']}
                    rows={minutes.map((doc, idx) => [
                        <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                        ...(hasSubRows ? [<SubTag no={doc.sub_project_no} />] : []),
                        <strong>{doc.meeting_date}</strong>,
                        <div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <a href={doc.url} target="_blank" rel="noreferrer" style={{ color: TONE, fontWeight: 600, textDecoration: 'none', fontSize: '12px' }}>{doc.filename}</a>
                                <VersionBadge versions={doc.versions} tone={TONE} />
                                {canEdit && !doc.sub_project_id && (
                                    <ReplaceFileButton url={route('files.replace', [project.id, 'mom', doc.id])} tone={TONE} />
                                )}
                            </div>
                            <FileHistory
                                versions={doc.versions}
                                tone={TONE}
                                restoreUrl={canEdit && !doc.sub_project_id ? v => route('files.restore', [project.id, 'mom', doc.id, v]) : undefined}
                            />
                        </div>,
                        <span style={{ fontSize: '12.5px' }}>{doc.uploaded_by}</span>,
                        <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.uploaded_at}</span>,
                        <ActionBtns
                            view
                            download
                            del={canEdit && !doc.sub_project_id}
                            open={!!doc.sub_project_id}
                            onView={() => window.open(doc.url, '_blank', 'noopener')}
                            onDownload={() => downloadFile(doc.url, doc.filename)}
                            onDelete={() => handleDelete(doc)}
                            onOpen={() => router.visit(route('projects.hub.mom', doc.sub_project_id!))}
                        />,
                    ])}
                />
            )}
        </HubShell>
    );
}

/** Save under the name it was uploaded with, rather than the stored hash name. */
function downloadFile(url: string, filename: string) {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
}
