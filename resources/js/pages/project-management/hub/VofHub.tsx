import { Button, DataTable, Field, HubProject, HubShell, InfoStrip, SectionTitle, inputStyle } from './Common';

const textCell = <textarea rows={3} style={{ ...inputStyle, border: 'none', borderRadius: 0, resize: 'vertical' }} />;

export default function VofHub({ project }: { project: HubProject }) {
    return (
        <HubShell>
            <InfoStrip project={project} accent="#f59e0b" />
            <SectionTitle color="#f59e0b">Variation Order Information</SectionTitle>
            <DataTable
                headers={['V.O. Number', 'Date of Request', 'Requestor']}
                rows={[[<input style={inputStyle} placeholder="VO-001" />, <input type="date" style={inputStyle} />, <input style={inputStyle} placeholder="Enter full name" />]]}
            />
            <SectionTitle color="#f59e0b">Variation Order Details</SectionTitle>
            <DataTable
                headers={['Aspect', 'Original Details', 'Proposed Change', 'Reason / Remark']}
                rows={['Scope', 'Schedule', 'Cost'].map(label => [<strong>{label}</strong>, textCell, textCell, textCell])}
            />
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '16px', marginTop: '18px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '14px' }}>
                    <Field label="Priority">
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', fontSize: '12px', color: '#334155' }}>
                            {['Immediate', 'High', 'Essential', 'Medium', 'Urgent', 'Low'].map(item => <label key={item}><input type="checkbox" /> {item}</label>)}
                        </div>
                    </Field>
                    <Field label="Intended Outcome"><textarea rows={4} style={inputStyle} /></Field>
                    <Field label="Expected Benefits"><textarea rows={4} style={inputStyle} /></Field>
                </div>
                <div style={{ marginTop: '14px' }}>
                    <Field label="Impacts"><textarea rows={3} style={inputStyle} /></Field>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: '14px', marginTop: '14px' }}>
                    <Field label="Attachments"><input type="file" multiple style={inputStyle} /></Field>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <Button variant="outline">Save as Draft</Button>
                        <Button variant="dark">Submit Variation Order</Button>
                    </div>
                </div>
            </div>
        </HubShell>
    );
}
