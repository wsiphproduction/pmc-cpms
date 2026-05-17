import { Badge, Button, DataTable, Field, HubProject, HubShell, inputStyle } from './Common';

export default function PermitsHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <h3 style={{ marginTop: 0 }}>Project Permits & Compliance</h3>
            <div style={{ background: '#f8fafc', border: '2px dashed #cbd5e1', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1.4fr 150px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Document Label"><input style={inputStyle} placeholder="e.g. Site Clearance" /></Field>
                    <Field label="Type of Document"><select style={inputStyle}><option>Building Permit</option><option>Safety Permit</option><option>Environmental Permit</option><option>Others</option></select></Field>
                    <Field label="Upload Files"><input type="file" multiple style={inputStyle} /></Field>
                    <Button>Add Record</Button>
                </div>
            </div>
            <DataTable headers={['Seq#', 'Label & Attached Files', 'Type', 'Actions']} rows={[[1, <><b>Excavation Clearance Package</b><div style={{ marginTop: 4 }}><Badge>drawings.pdf</Badge> <Badge tone="blue">site_photo.jpg</Badge></div></>, <Badge>Safety Permit</Badge>, <Button variant="outline">Download</Button>]]} />
        </HubShell>
    );
}
