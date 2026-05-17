import { Badge, Button, DataTable, Field, HubProject, HubShell, inputStyle } from './Common';

export default function RfqHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <h3 style={{ margin: '0 0 18px', color: '#2563eb', fontSize: '18px' }}>Dispatch New RFQ</h3>
            <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '18px', marginBottom: '22px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 180px', gap: '14px', alignItems: 'end' }}>
                    <Field label="Select Contractor">
                        <select style={inputStyle} defaultValue="">
                            <option value="" disabled>Choose from registered contractors...</option>
                            {['BuildRight Solutions', 'Apex Engineering Group', 'Global Infra Systems', 'Precision Mechanical Ltd', 'Titan Gel Construction', 'Discaya Construction'].map(name => <option key={name}>{name}</option>)}
                        </select>
                    </Field>
                    <Field label="Due Date">
                        <input type="date" style={inputStyle} />
                    </Field>
                    <Button>Send RFQ</Button>
                </div>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                <strong style={{ color: '#475569' }}>RFQ Dispatch & Quotation Tracking</strong>
                <Badge>Total Records: 4</Badge>
            </div>
            <DataTable
                headers={['Contractor Name', 'Sent Date', 'Due Date', 'Status', 'Actions']}
                rows={[
                    ['BuildRight Solutions', 'Mar 01, 2026', 'Mar 05, 2026', <Badge tone="yellow">Awarded</Badge>, <Button variant="success">Create NTP</Button>],
                    ['Apex Engineering Group', 'Mar 02, 2026', 'Mar 12, 2026', <Badge tone="green">Submitted</Badge>, <Button variant="outline">Award Project</Button>],
                    ['Global Infra Systems', 'Mar 08, 2026', 'Mar 15, 2026', <Badge>Pending</Badge>, <Button variant="outline">Quotation</Button>],
                    ['Precision Mechanical Ltd', 'Feb 15, 2026', 'Mar 01, 2026', <Badge tone="red">Expired</Badge>, <Button variant="outline">Resend RFQ</Button>],
                ]}
            />
            <div style={{ marginTop: '20px', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '16px' }}>
                <strong style={{ display: 'block', marginBottom: '10px' }}>RFQ & Quotation Details</strong>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '12px', fontSize: '13px' }}>
                    <div><b>Project:</b> {project.project_no}</div>
                    <div><b>Title:</b> {project.title}</div>
                    <div><b>Owner:</b> {project.project_manager}</div>
                </div>
            </div>
        </HubShell>
    );
}
