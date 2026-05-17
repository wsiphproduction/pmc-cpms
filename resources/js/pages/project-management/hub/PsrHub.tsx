import { Button, DataTable, Field, HubProject, HubShell, inputStyle } from './Common';

const reportRows = [
    ['W4-SEP', '72%', 'No major issues. Handover preparation began for Phase 1.', 'Sep 28, 2026', 'Report_W4_Sep.pdf'],
    ['W3-SEP', '65%', 'Minor material delivery delay (Steel beams). Recovered.', 'Sep 21, 2026', 'Report_W3_Sep.pdf'],
    ['W2-SEP', '58%', '2 site laborers noted with improper PPE during safety audit.', 'Sep 14, 2026', 'Report_W2_Sep.pdf'],
    ['W1-SEP', '51%', 'Variation order VO-001 approved, increasing scope slightly.', 'Sep 07, 2026', 'Report_W1_Sep.pdf'],
    ['W4-AUG', '44%', 'Piping installation milestone accomplished on time.', 'Aug 31, 2026', 'Report_W4_Aug.pdf'],
];

export default function PsrHub({ project }: { project: HubProject }) {
    const progress = project.completion_percent || 72;

    return (
        <HubShell>
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '16px', padding: '22px', marginBottom: '22px', display: 'grid', gridTemplateColumns: '180px 180px 1fr', gap: '22px', alignItems: 'center' }}>
                <div style={{ textAlign: 'center', borderRight: '1px solid #e2e8f0' }}>
                    <div style={{ fontSize: '10px', fontWeight: 900, color: '#64748b', textTransform: 'uppercase' }}>Latest Overall Progress</div>
                    <div style={{ fontSize: '44px', fontWeight: 900, color: '#1e293b' }}>{progress}%</div>
                    <div style={{ fontSize: '12px', fontWeight: 800, color: '#16a34a' }}>On Track</div>
                </div>
                <div style={{ width: '150px', height: '150px', borderRadius: '50%', background: `conic-gradient(#16a34a ${progress}%, #e2e8f0 0)`, display: 'grid', placeItems: 'center' }}>
                    <div style={{ width: '100px', height: '100px', borderRadius: '50%', background: '#fff', display: 'grid', placeItems: 'center', fontWeight: 900 }}>{progress}%</div>
                </div>
                <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '12px', alignItems: 'center', marginBottom: '12px' }}>
                        <h4 style={{ margin: 0, color: '#2563eb' }}>Monthly Execution Phase</h4>
                        <Button variant="dark">Add New Weekly Report</Button>
                    </div>
                    <div style={{ background: '#e0f2fe', color: '#075985', borderRadius: '8px', padding: '10px 12px', fontSize: '12.5px', fontWeight: 600 }}>
                        A total of 27 critical site checklists must be verified weekly by the site supervisor and approved by QA/QC.
                    </div>
                </div>
            </div>
            <DataTable
                headers={['Week#', '% Completion', 'Identified Issues', 'Submitted Date', 'Attachment', 'Actions']}
                rows={reportRows.map(row => [<strong style={{ color: '#2563eb' }}>{row[0]}</strong>, <Progress value={row[1]} />, row[2], row[3], <a href="#" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{row[4]}</a>, <Button variant="outline">View</Button>])}
            />
            <div style={{ border: '1px solid #000', borderRadius: '6px', overflow: 'hidden', marginTop: '22px' }}>
                <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '7px', borderBottom: '1px solid #000' }}>Weekly Issues and Action Plan</div>
                <div style={{ padding: '14px', display: 'grid', gridTemplateColumns: '1fr 1fr 170px', gap: '12px' }}>
                    <Field label="Key Issues"><textarea rows={3} style={inputStyle} /></Field>
                    <Field label="Corrective Actions"><textarea rows={3} style={inputStyle} /></Field>
                    <Field label="Commitment Date"><input type="date" style={inputStyle} /></Field>
                </div>
            </div>
        </HubShell>
    );
}

function Progress({ value }: { value: string }) {
    const numeric = Number(value.replace('%', ''));
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <strong>{value}</strong>
            <div style={{ width: '70px', height: '7px', borderRadius: '999px', background: '#e2e8f0' }}>
                <div style={{ width: `${numeric}%`, height: '100%', borderRadius: '999px', background: '#16a34a' }} />
            </div>
        </div>
    );
}
