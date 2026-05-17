import { Badge, Button, DataTable, Field, HubProject, HubShell, inputStyle, money } from './Common';

export default function RfpHub({ project }: { project: HubProject }) {
    const paidPct = project.budget_total > 0 ? Math.round((project.budget_paid / project.budget_total) * 100) : 0;

    return (
        <HubShell>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '20px', marginBottom: '22px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '18px', alignItems: 'center' }}>
                <div>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Total Project Cost</div>
                    <div style={{ fontSize: '26px', fontWeight: 900, color: '#0f172a' }}>{money(project.budget_total)}</div>
                    <div style={{ marginTop: '12px', height: '8px', borderRadius: '999px', background: '#e2e8f0' }}>
                        <div style={{ width: `${paidPct}%`, height: '100%', borderRadius: '999px', background: '#2563eb' }} />
                    </div>
                    <div style={{ marginTop: '6px', fontSize: '12px', color: '#64748b' }}>Paid: <strong>{money(project.budget_paid)}</strong></div>
                </div>
                <div style={{ textAlign: 'center' }}>
                    <div style={{ width: '150px', height: '150px', borderRadius: '50%', margin: '0 auto', background: `conic-gradient(#2563eb ${paidPct}%, #e2e8f0 0)`, display: 'grid', placeItems: 'center' }}>
                        <div style={{ width: '102px', height: '102px', borderRadius: '50%', background: '#fff', display: 'grid', placeItems: 'center', fontWeight: 900, color: '#1e293b' }}>{paidPct}%</div>
                    </div>
                </div>
                <div>
                    <h4 style={{ margin: '0 0 12px', fontSize: '15px' }}>Quick Actions</h4>
                    <div style={{ display: 'grid', gap: '8px' }}>
                        <Button variant="dark">Add New Billing</Button>
                        <Button variant="outline">Statement of Account</Button>
                    </div>
                </div>
            </div>
            <DataTable
                headers={['Seq#', 'Control#', 'Billed Amount', 'Req. Date', 'Progress %', 'Status', 'Actions']}
                rows={[
                    [1, <strong>BLG-2026-001</strong>, 'PhP 450,000.00', 'Feb 10, 2026', '30%', <Badge tone="blue">Paid</Badge>, <Button variant="outline">View</Button>],
                    [2, <strong>BLG-2026-002</strong>, 'PhP 300,000.00', 'Mar 01, 2026', '50%', <Badge tone="green">Approved</Badge>, <Button variant="outline">Print</Button>],
                    [3, <strong>BLG-2026-003</strong>, 'PhP 150,000.00', 'Mar 11, 2026', '65%', <Badge tone="yellow">Pending</Badge>, <Button variant="outline">Edit</Button>],
                ]}
            />
            <div style={{ marginTop: '22px', border: '1px solid #000', borderRadius: '6px', overflow: 'hidden' }}>
                <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '7px', borderBottom: '1px solid #000' }}>Billing Details</div>
                <div style={{ padding: '14px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                    <Field label="Billing Statement No."><input style={inputStyle} /></Field>
                    <Field label="Billing Period / Date"><input type="date" style={inputStyle} /></Field>
                    <Field label="Billed Amount"><input type="number" style={inputStyle} placeholder="0.00" /></Field>
                    <Field label="Project Progress"><input style={inputStyle} placeholder="e.g. 50%" /></Field>
                    <Field label="Summary of Work Done"><textarea rows={3} style={inputStyle} /></Field>
                    <Field label="Recommendation"><select style={inputStyle}><option>For Payment</option><option>Withhold</option><option>Others</option></select></Field>
                </div>
            </div>
        </HubShell>
    );
}
