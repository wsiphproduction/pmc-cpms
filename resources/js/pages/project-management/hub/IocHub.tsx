import { Button, DataTable, Field, HubProject, HubShell, inputStyle } from './Common';

const otherCosts = [
    ['1', 'Site Mobilization Equipment Rental', '45,000.00', 'INV-088.pdf'],
    ['2', 'Local Barangay Clearance Fees', '7,500.00', 'clearance.jpg'],
    ['3', 'Security Guard OT - Night Shift (Week 1)', '12,000.00', 'timesheet.xlsx'],
    ['4', 'Emergency Piping Repair Materials', '28,000.00', 'OR_5592.pdf'],
    ['5', 'Consultancy Fees - Structural Audit', '50,000.00', 'SOA_V3.pdf'],
];

export default function IocHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <div style={{ background: 'linear-gradient(135deg, #1e293b 0%, #334155 100%)', color: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '22px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <div style={{ fontSize: '11px', fontWeight: 800, textTransform: 'uppercase', opacity: 0.78 }}>Aggregate Project Miscellaneous Expenses</div>
                    <div style={{ fontSize: '28px', fontWeight: 900, marginTop: '4px' }}>PhP 142,500.00</div>
                </div>
                <div style={{ textAlign: 'right', opacity: 0.75 }}>
                    <div style={{ fontSize: '11px', fontWeight: 800 }}>Project</div>
                    <div style={{ fontSize: '14px', fontWeight: 700 }}>{project.project_no}</div>
                </div>
            </div>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <h4 style={{ margin: '0 0 14px', fontSize: '15px' }}>Add New Miscellaneous Cost</h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 0.7fr 1fr 130px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Description of Expense"><input style={inputStyle} placeholder="e.g. Hauling services for site debris" /></Field>
                    <Field label="Cost (PhP)"><input type="number" step="0.01" style={inputStyle} placeholder="0.00" /></Field>
                    <Field label="Receipt / Attachment"><input type="file" style={inputStyle} /></Field>
                    <Button>Save Record</Button>
                </div>
            </div>
            <DataTable
                headers={['Seq#', 'Description', 'Cost (PhP)', 'Attachment', 'Actions']}
                rows={otherCosts.map(row => [row[0], row[1], <strong>{row[2]}</strong>, <a href="#" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{row[3]}</a>, <Button variant="outline">View</Button>])}
            />
        </HubShell>
    );
}
