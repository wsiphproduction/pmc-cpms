import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, Badge, DataTable, Field, HubProject, HubShell, InfoStrip, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface MtrRow { id: number; label: string; material_type: string; test_date: string; filename: string; url: string }

export default function MtrHub({ project, mtrs }: { project: HubProject; mtrs: MtrRow[] }) {
    const [label, setLabel]           = useState('');
    const [materialType, setMatType]  = useState('Concrete Compressive Test');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleLog = () => {
        const file = fileRef.current?.files?.[0];
        if (!label.trim()) { setError('Report Name is required.'); return; }
        if (!file) { setError('Please attach a document before logging.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('material_type', materialType);
        fd.append('file', file);
        router.post(route('hub.mtr.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setLabel(''); if (fileRef.current) fileRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (mtr: MtrRow) => {
        showConfirm(`Delete "${mtr.label}"?`, () => {
            router.delete(route('hub.mtr.destroy', [project.id, mtr.id]), { preserveScroll: true });
        }, { title: 'Delete Test Report', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent="#eab308" />
            <div style={{ background: '#fefce8', border: '2px dashed #fef08a', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Report Name">
                        <input style={inputStyle} placeholder="e.g. 28-Day Concrete Compressive Strength" value={label} onChange={e => setLabel(e.target.value)} />
                    </Field>
                    <Field label="Type of Report">
                        <select style={inputStyle} value={materialType} onChange={e => setMatType(e.target.value)}>
                            <option>Concrete Compressive Test</option>
                            <option>Soil Compaction / Sieve Analysis</option>
                            <option>Steel Tensile Strength</option>
                            <option>Welding Inspection (NDT)</option>
                            <option>Chemical Analysis</option>
                            <option>Others</option>
                        </select>
                    </Field>
                    <Field label="Upload Document (PDF)"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                    <button type="button" onClick={handleLog} disabled={saving} style={{ padding: '8px 13px', borderRadius: '7px', background: '#eab308', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                        {saving ? 'Logging...' : 'Log Report'}
                    </button>
                </div>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
            </div>
            <DataTable
                headers={['Seq#', 'Report Name', 'Type of Report', 'Date Logged', 'Actions']}
                rows={mtrs.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    <strong>{doc.label}</strong>,
                    <Badge tone="yellow">{doc.material_type}</Badge>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.test_date}</span>,
                    <ActionBtns download del onDownload={() => window.open(doc.url)} onDelete={() => handleDelete(doc)} />,
                ])}
            />
        </HubShell>
    );
}
