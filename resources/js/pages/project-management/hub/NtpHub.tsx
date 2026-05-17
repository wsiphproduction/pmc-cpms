import { DataTable, HubProject, HubShell, InfoStrip, SectionTitle } from './Common';

export default function NtpHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <div style={{ borderBottom: '2px solid #059669', paddingBottom: '14px', marginBottom: '24px', display: 'flex', justifyContent: 'space-between' }}>
                <div>
                    <h3 style={{ color: '#059669', margin: 0 }}>NOTICE TO PROCEED</h3>
                    <span style={{ color: '#64748b', fontSize: '12px' }}>Official Authorization for Project Commencement</span>
                </div>
                <strong>NTP#: PMC-NTP-001</strong>
            </div>
            <InfoStrip project={project} accent="#059669" />
            <SectionTitle color="#059669">II. Scope of Work</SectionTitle>
            <DataTable headers={['Seq No.', 'Scope of Work / Description', 'Quantity', 'Unit of Measurement']} rows={Array.from({ length: 10 }, (_, i) => [i + 1, '', '', ''])} />
            <SectionTitle color="#059669">III. Terms and Conditions</SectionTitle>
            <DataTable
                headers={['Provision Category', 'Details & Obligations']}
                rows={[
                    ['1. Project Commencement', 'The contractor is formally authorized to mobilize and begin work on the specified baseline start date.'],
                    ['2. Compliance & Safety', 'All work must adhere to site-specific safety protocols and ISO 45001 standards.'],
                    ['3. Payment Milestone', 'Payments shall be released based on the approved progress billing cycle.'],
                    ['4. Delays & Penalties', 'Unexcused delays beyond the baseline end date may be subject to liquidated damages.'],
                ]}
            />
        </HubShell>
    );
}
