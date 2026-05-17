import { Badge, Button, DataTable, Field, HubProject, HubShell, InfoStrip, inputStyle } from './Common';

export default function QppHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <InfoStrip project={project} accent="#0ea5e9" />
            <div style={{ background: '#f0f9ff', border: '2px dashed #bae6fd', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Document Label"><input style={inputStyle} placeholder="e.g. Concrete Pouring ITP" /></Field>
                    <Field label="Type of Document">
                        <select style={inputStyle}>
                            <option>Inspection & Test Plan (ITP)</option>
                            <option>Method Statement</option>
                            <option>Quality Control Procedure</option>
                            <option>Material Approval Request</option>
                            <option>Site Inspection Report</option>
                        </select>
                    </Field>
                    <Field label="Upload File"><input type="file" style={inputStyle} /></Field>
                    <Button>Upload File</Button>
                </div>
            </div>
            <DataTable
                headers={['Seq#', 'Label', 'Type of Document', 'Actions']}
                rows={[[1, <strong>Main Electrical Method Statement</strong>, <Badge tone="blue">Method Statement</Badge>, <Button variant="outline">Download</Button>]]}
            />
        </HubShell>
    );
}
