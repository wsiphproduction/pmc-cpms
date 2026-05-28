import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, Badge, DataTable, Field, HubProject, HubShell, InfoStrip, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface QppRow { id: number; label: string; doc_type: string; filename: string; url: string; created: string }

export default function QppHub({ project, qpps }: { project: HubProject; qpps: QppRow[] }) {
    const [label, setLabel]   = useState('');
    const [docType, setDocType] = useState('Inspection & Test Plan (ITP)');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleUpload = () => {
        const file = fileRef.current?.files?.[0];
        if (!label.trim()) { setError('Document Label is required.'); return; }
        if (!file) { setError('Please attach a file before uploading.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('doc_type', docType);
        fd.append('file', file);
        router.post(route('hub.qpp.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setLabel(''); if (fileRef.current) fileRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

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
            <div style={{ background: '#f0f9ff', border: '2px dashed #bae6fd', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Document Label">
                        <input style={inputStyle} placeholder="e.g. Concrete Pouring ITP" value={label} onChange={e => setLabel(e.target.value)} />
                    </Field>
                    <Field label="Type of Document">
                        <select style={inputStyle} value={docType} onChange={e => setDocType(e.target.value)}>
                            <option>Inspection & Test Plan (ITP)</option>
                            <option>Method Statement</option>
                            <option>Quality Control Procedure</option>
                            <option>Material Approval Request</option>
                            <option>Site Inspection Report</option>
                        </select>
                    </Field>
                    <Field label="Upload File"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                    <button type="button" onClick={handleUpload} disabled={saving} style={{ padding: '8px 13px', borderRadius: '7px', background: '#0ea5e9', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                        {saving ? 'Uploading...' : 'Upload File'}
                    </button>
                </div>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
            </div>
            <DataTable
                headers={['Seq#', 'Label', 'Type of Document', 'Date', 'Actions']}
                rows={qpps.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    <strong>{doc.label}</strong>,
                    <Badge tone="blue">{doc.doc_type}</Badge>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.created}</span>,
                    <ActionBtns download del onDownload={() => window.open(doc.url)} onDelete={() => handleDelete(doc)} />,
                ])}
            />
        </HubShell>
    );
}
