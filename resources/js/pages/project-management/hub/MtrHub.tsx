import { Badge, Button, DataTable, Field, HubProject, HubShell, InfoStrip, inputStyle } from './Common';

export default function MtrHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <InfoStrip project={project} accent="#eab308" />
            <div style={{ background: '#fefce8', border: '2px dashed #fef08a', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 150px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Report Name"><input style={inputStyle} placeholder="e.g. 28-Day Concrete Compressive Strength" /></Field>
                    <Field label="Type of Report">
                        <select style={inputStyle}>
                            <option>Concrete Compressive Test</option>
                            <option>Soil Compaction / Sieve Analysis</option>
                            <option>Steel Tensile Strength</option>
                            <option>Welding Inspection (NDT)</option>
                            <option>Chemical Analysis</option>
                            <option>Others</option>
                        </select>
                    </Field>
                    <Field label="Upload Document"><input type="file" style={inputStyle} /></Field>
                    <Button variant="outline">Log Report</Button>
                </div>
            </div>
            <DataTable
                headers={['Seq#', 'Report Name', 'Type of Report', 'Date Logged', 'Actions']}
                rows={[[1, <strong>Foundation Piling Integrity Report</strong>, <Badge tone="yellow">Concrete Compressive Test</Badge>, 'Mar 10, 2026', <Button variant="outline">Download</Button>]]}
            />
        </HubShell>
    );
}
