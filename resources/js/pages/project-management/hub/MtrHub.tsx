import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, Badge, DataTable, Field, HubProject, HubShell, InfoStrip, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface MtrRow { id: number; label: string; material_type: string; test_date: string; filename: string; url: string }

export default function MtrHub({ project, mtrs, canEdit = true }: { project: HubProject; mtrs: MtrRow[]; canEdit?: boolean }) {
    const [label, setLabel]           = useState('');
    const [materialType, setMatType]  = useState('');
    const [customType, setCustomType] = useState('');
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const handleLog = () => {
        const file = fileRef.current?.files?.[0];
        const resolvedType = materialType === 'Others' ? customType.trim() : materialType;
        if (!label.trim()) { setError('Report Name is required.'); return; }
        if (!materialType) { setError('Please select a type of report.'); return; }
        if (materialType === 'Others' && !resolvedType) { setError('Please specify the report type.'); return; }
        if (!file) { setError('Please attach a document before logging.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('label', label);
        fd.append('material_type', resolvedType);
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
            {canEdit && (
                <div style={{ background: '#fefce8', border: '2px dashed #fef08a', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Report Name">
                            <input style={inputStyle} placeholder="e.g. 28-Day Concrete Compressive Strength" value={label} onChange={e => setLabel(e.target.value)} />
                        </Field>
                        <Field label="Type of Report">
                            <select style={inputStyle} value={materialType} onChange={e => setMatType(e.target.value)}>
                                <option value="">Select type of report…</option>
                                <option>Concrete Compressive Test</option>
                                <option>Soil Compaction / Sieve Analysis</option>
                                <option>Steel Tensile Strength</option>
                                <option>Welding Inspection (NDT)</option>
                                <option>Chemical Analysis</option>
                                <option>Others</option>
                            </select>
                            {materialType === 'Others' && (
                                <input style={{ ...inputStyle, marginTop: '8px' }} placeholder="Specify report type…" value={customType} onChange={e => setCustomType(e.target.value)} />
                            )}
                        </Field>
                        <Field label="Upload Document (PDF)"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleLog} disabled={saving} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: '#eab308', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                                {saving ? 'Logging...' : 'Log Report'}
                            </button>
                        </div>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}
            <DataTable
                headers={['Seq#', 'Report Name', 'Type of Report', 'Date Logged', 'Actions']}
                rows={mtrs.map((doc, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    <strong>{doc.label}</strong>,
                    <Badge tone="yellow">{doc.material_type}</Badge>,
                    <span style={{ fontSize: '12px', color: '#94a3b8' }}>{doc.test_date}</span>,
                    <ActionBtns download del={canEdit} onDownload={() => window.open(doc.url)} onDelete={() => handleDelete(doc)} />,
                ])}
            />
        </HubShell>
    );
}
