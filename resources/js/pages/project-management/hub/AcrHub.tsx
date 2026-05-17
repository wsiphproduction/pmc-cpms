import { Button, DataTable, Field, HubProject, HubShell, inputStyle, money } from './Common';

const actualRows = [
    ['1', 'Initial Mobilization & Site Setup', 450000, 'NTP-MOB-01.pdf'],
    ['2', 'Material Test: Structural Steel Phase 1', 85000, 'Lab-Result.jpg'],
    ['3', 'Variation Order #1: Additional Piping', 1200000, 'VO-001-Signed.pdf'],
    ['4', 'Environmental Compliance Permit Fees', 12000, 'ECC-Permit.pdf'],
    ['5', 'Milestone 2: Foundation Works', 595000, 'Billing-M2.pdf'],
];

export default function AcrHub({ project }: { project: HubProject }) {
    const actual = actualRows.reduce((sum, row) => sum + Number(row[2]), 0);
    const variance = project.budget_total - actual;
    const pct = project.budget_total > 0 ? Math.round((actual / project.budget_total) * 100) : 0;

    return (
        <HubShell>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '16px', padding: '22px', marginBottom: '22px', display: 'grid', gridTemplateColumns: '1fr 80px 1fr 1fr', gap: '18px', alignItems: 'center' }}>
                <Metric label="Projected Budget" value={money(project.budget_total)} sub="Approved CAPEX" />
                <div style={{ textAlign: 'center', color: '#cbd5e1', fontWeight: 900, fontStyle: 'italic' }}>vs</div>
                <div>
                    <Metric label="Actual Cost to Date" value={money(actual)} sub={`${pct}% utilized`} color="#2563eb" />
                    <div style={{ height: '7px', background: '#f1f5f9', borderRadius: '999px', marginTop: '8px' }}>
                        <div style={{ width: `${Math.min(pct, 100)}%`, height: '100%', background: '#2563eb', borderRadius: '999px' }} />
                    </div>
                </div>
                <Metric label="Remaining Variance" value={money(variance)} sub={variance >= 0 ? 'Under Budget' : 'Over Budget'} color={variance >= 0 ? '#16a34a' : '#dc2626'} align="right" />
            </div>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                <h4 style={{ margin: '0 0 14px', fontSize: '15px' }}>Log New Actual Expenditure</h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 0.7fr 1fr 130px', gap: '12px', alignItems: 'end' }}>
                    <Field label="Cost Description"><input style={inputStyle} placeholder="e.g. Milestone 1 Progress Payment" /></Field>
                    <Field label="Amount (PhP)"><input type="number" style={inputStyle} placeholder="0.00" /></Field>
                    <Field label="Evidence Attachment"><input type="file" style={inputStyle} /></Field>
                    <Button>Add to Report</Button>
                </div>
            </div>
            <DataTable
                headers={['Seq#', 'Description', 'Actual Cost (PhP)', 'Attachment', 'Actions']}
                rows={actualRows.map(row => [row[0], row[1], <strong>{Number(row[2]).toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>, <a href="#" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{row[3]}</a>, <Button variant="outline">View</Button>])}
            />
        </HubShell>
    );
}

function Metric({ label, value, sub, color = '#0f172a', align = 'left' }: { label: string; value: string; sub: string; color?: string; align?: 'left' | 'right' }) {
    return (
        <div style={{ textAlign: align }}>
            <div style={{ fontSize: '10px', fontWeight: 900, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{label}</div>
            <div style={{ fontSize: '24px', fontWeight: 900, color, marginTop: '3px' }}>{value}</div>
            <div style={{ fontSize: '12px', color: '#64748b', marginTop: '3px', fontWeight: 700 }}>{sub}</div>
        </div>
    );
}
