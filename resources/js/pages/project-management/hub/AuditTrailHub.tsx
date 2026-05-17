import { Badge, Button, DataTable, Field, HubProject, HubShell, inputStyle } from './Common';

const logs = [
    ['Mar 12, 2026 08:45 AM', 'Engr. Alex Rivera', 'Project Manager', 'Approved Payment Request BLG-2026-003', '192.168.1.45', 'finance'],
    ['Mar 11, 2026 03:20 PM', 'Maria Santos', 'Document Controller', 'Uploaded Material Test Report: Steel_Tensile_V2.pdf', '192.168.1.102', 'upload'],
    ['Mar 10, 2026 10:15 AM', 'Engr. Alex Rivera', 'Project Manager', 'Updated Weekly Status Report (Week 4 - SEP)', '192.168.1.45', 'update'],
    ['Mar 09, 2026 04:55 PM', 'Support Team', 'Logistics Admin', 'Added new Other Cost: Site Mobilization (45,000.00)', '172.16.254.12', 'finance'],
    ['Mar 08, 2026 11:02 AM', 'Admin User', 'System Admin', 'Deleted Draft Variation Order: VO-TEMP-099', '10.0.0.15', 'delete'],
];

export default function AuditTrailHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <h3 style={{ margin: '0 0 18px', fontSize: '18px', color: '#334155' }}>Project System Logs & Audit Trail</h3>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', marginBottom: '20px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 0.8fr 0.8fr 1fr 130px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Search User"><input style={inputStyle} placeholder="Name or role..." /></Field>
                    <Field label="From Date"><input type="date" style={inputStyle} /></Field>
                    <Field label="To Date"><input type="date" style={inputStyle} /></Field>
                    <Field label="Action Type">
                        <select style={inputStyle}>
                            <option>All Activities</option>
                            <option>File Upload</option>
                            <option>Record Deletion</option>
                            <option>Financial Approval</option>
                            <option>Status Update</option>
                        </select>
                    </Field>
                    <Button variant="dark">Apply Filter</Button>
                </div>
            </div>
            <DataTable
                headers={['Date & Time', 'User Account', 'Activity Description', 'IP Address', 'Reference']}
                rows={logs.map(row => [
                    <strong>{row[0]}</strong>,
                    <><strong>{row[1]}</strong><div style={{ fontSize: '11px', color: '#94a3b8' }}>{row[2]}</div></>,
                    <span style={{ color: row[5] === 'delete' ? '#dc2626' : '#334155' }}>{row[3]}</span>,
                    row[4],
                    row[5] === 'delete' ? <span style={{ color: '#94a3b8' }}>-</span> : <Badge tone={row[5] === 'upload' ? 'blue' : row[5] === 'update' ? 'green' : 'yellow'}>{project.project_no}</Badge>,
                ])}
            />
        </HubShell>
    );
}
