import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionBtns, Badge, Button, DataTable, Field, HubProject, HubShell, InfoStrip, SectionTitle, inputStyle } from './Common';

interface VofRow { id: number; vo_no: string; title: string; description: string | null; amount: number; status: string; submitted_date: string; approved_date: string }

const STATUS_TONE: Record<string, 'green' | 'slate' | 'red' | 'yellow'> = {
    Approved: 'green', Pending: 'slate', Rejected: 'red',
};

export default function VofHub({ project, vofs }: { project: HubProject; vofs: VofRow[] }) {
    const [title, setTitle]   = useState('');
    const [desc, setDesc]     = useState('');
    const [amount, setAmount] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const textCell = <textarea rows={3} style={{ ...inputStyle, border: 'none', borderRadius: 0, resize: 'vertical' }} />;

    const handleSubmit = () => {
        if (!title.trim()) { setError('Title / Subject is required.'); return; }
        if (!amount || Number(amount) === 0) { setError('Amount is required and must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.vof.store', project.id), { title, description: desc, amount }, {
            preserveScroll: true,
            onSuccess: () => { setTitle(''); setDesc(''); setAmount(''); },
            onFinish: () => setSaving(false),
        });
    };

    const handleDelete = (vof: VofRow) => {
        if (!confirm(`Delete variation order ${vof.vo_no}?`)) return;
        router.delete(route('hub.vof.destroy', [project.id, vof.id]), { preserveScroll: true });
    };

    return (
        <HubShell>
            <InfoStrip project={project} accent="#f59e0b" />

            {vofs.length > 0 && (
                <>
                    <SectionTitle color="#f59e0b">Variation Order History</SectionTitle>
                    <DataTable
                        headers={['VO No.', 'Title', 'Amount (PhP)', 'Status', 'Date', 'Actions']}
                        rows={vofs.map(vo => [
                            <strong style={{ color: '#f59e0b' }}>{vo.vo_no}</strong>,
                            vo.title,
                            <strong>{vo.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                            <Badge tone={STATUS_TONE[vo.status] ?? 'slate'}>{vo.status}</Badge>,
                            <span style={{ fontSize: '12px', color: '#94a3b8' }}>{vo.submitted_date}</span>,
                            <ActionBtns del onDelete={() => handleDelete(vo)} />,
                        ])}
                    />
                </>
            )}

            <SectionTitle color="#f59e0b">Variation Order Information</SectionTitle>
            <DataTable
                headers={['V.O. Number', 'Date of Request', 'Requestor']}
                rows={[[
                    <input style={inputStyle} placeholder="Auto-assigned on submit" readOnly />,
                    <input type="date" style={inputStyle} />,
                    <input style={inputStyle} placeholder="Enter full name" />
                ]]}
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
                    <Field label="Title / Subject">
                        <input style={inputStyle} placeholder="Brief title of this VO" value={title} onChange={e => setTitle(e.target.value)} />
                    </Field>
                    <Field label="Amount (PhP)">
                        <input type="number" style={inputStyle} placeholder="0.00" value={amount} onChange={e => setAmount(e.target.value)} />
                    </Field>
                </div>
                <div style={{ marginTop: '14px' }}>
                    <Field label="Description / Justification">
                        <textarea rows={3} style={inputStyle} placeholder="Describe the variation and why it is needed..." value={desc} onChange={e => setDesc(e.target.value)} />
                    </Field>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: '14px', marginTop: '14px' }}>
                    <Field label="Attachments"><input type="file" multiple style={inputStyle} /></Field>
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '8px' }}>
                        {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, width: '100%', textAlign: 'left' }}>{error}</div>}
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <Button variant="outline">Save as Draft</Button>
                            <Button variant="dark" onClick={handleSubmit}>{saving ? 'Submitting...' : 'Submit Variation Order'}</Button>
                        </div>
                    </div>
                </div>
            </div>
        </HubShell>
    );
}
