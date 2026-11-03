import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

interface ContractRow { id: number; label: string; filename: string; url: string; uploaded_by: string | null; created: string; sub_project_id: number | null; sub_project_no: string | null; versions?: FileVersion[] }

const TONE = '#7c3aed';

export default function ContractHub({ project, contracts, canEdit = true }: { project: HubProject; contracts: ContractRow[]; canEdit?: boolean }) {
    const [label, setLabel]   = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleUpload = () => {
        const file = fileRef.current?.files?.[0];
        if (!label.trim()) { setError('Label is required.'); return; }
        if (!file) { setError('Please attach the contract PDF before uploading.'); return; }
        if (!/\.pdf$/i.test(file.name)) { setError('The contract must be a PDF file.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('file', file);
        router.post(route('hub.contract.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setLabel(''); if (fileRef.current) fileRef.current.value = ''; },
            onError: errors => setError(errors.file ?? errors.label ?? 'Upload failed.'),
            onFinish: () => setSaving(false),
        });
    };

    const hasSubRows = contracts.some(c => !!c.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (contract: ContractRow) => {
        showConfirm(`Delete "${contract.label}"?`, () => {
            router.delete(route('hub.contract.destroy', [project.id, contract.id]), { preserveScroll: true });
        }, { title: 'Delete Contract', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent={TONE} />
            {canEdit && (
                <div style={{ background: '#f5f3ff', border: '2px dashed #ddd6fe', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Label">
                            <input style={inputStyle} placeholder="e.g. Approved Project Contract" value={label} onChange={e => setLabel(e.target.value)} />
                        </Field>
                        <Field label="Upload File (PDF)"><input type="file" accept="application/pdf,.pdf" ref={fileRef} style={inputStyle} /></Field>
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
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Label', 'File', 'Uploaded By', 'Date', 'Actions']}
                rows={contracts.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={doc.sub_project_no} />] : []),
                    <strong>{doc.label}</strong>,
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <a href={doc.url} target="_blank" rel="noreferrer" style={{ color: TONE, fontWeight: 600, textDecoration: 'none', fontSize: '12px' }}>{doc.filename}</a>
                            <VersionBadge versions={doc.versions} tone={TONE} />
                            {canEdit && !doc.sub_project_id && (
                                <ReplaceFileButton url={route('files.replace', [project.id, 'contract', doc.id])} tone={TONE} />
                            )}
                        </div>
                        <FileHistory
                            versions={doc.versions}
                            tone={TONE}
                            restoreUrl={canEdit && !doc.sub_project_id ? v => route('files.restore', [project.id, 'contract', doc.id, v]) : undefined}
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
                        onOpen={() => router.visit(route('projects.hub.contract', doc.sub_project_id!))}
                    />,
                ])}
            />
        </HubShell>
    );
}
