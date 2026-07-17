import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, Modal, SubTag, inputStyle, money } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface IocRow { id: number; description: string; amount: number; filename: string | null; url: string | null; created: string; sub_project_id: number | null; sub_project_no: string | null; }

// ── Small helpers ──────────────────────────────────────────────────────────
function Metric({ label, value, sub, color = '#0f172a', align = 'left' }: {
    label: string; value: string; sub: string; color?: string; align?: 'left' | 'right' | 'center';
}) {
    return (
        <div style={{ textAlign: align }}>
            <div style={{ fontSize: '10px', fontWeight: 900, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{label}</div>
            <div style={{ fontSize: '22px', fontWeight: 900, color, marginTop: '3px' }}>{value}</div>
            <div style={{ fontSize: '12px', color: '#64748b', marginTop: '3px', fontWeight: 700 }}>{sub}</div>
        </div>
    );
}

function Bar({ pct, color }: { pct: number; color: string }) {
    return (
        <div style={{ height: '7px', background: '#f1f5f9', borderRadius: '999px', marginTop: '8px' }}>
            <div style={{ width: `${Math.min(pct, 100)}%`, height: '100%', background: color, borderRadius: '999px', transition: 'width 0.3s' }} />
        </div>
    );
}

// ── View Modal ─────────────────────────────────────────────────────────────
function ViewModal({ item, onClose, onEdit, canEdit = true }: { item: IocRow; onClose: () => void; onEdit: () => void; canEdit?: boolean }) {
    const lc: React.CSSProperties = { background: '#f8fafc', fontWeight: 700, fontSize: '12.5px', padding: '10px 14px', width: '32%', borderRight: '1px solid #e5e7eb', color: '#374151' };
    const vc: React.CSSProperties = { padding: '10px 14px', fontSize: '13px', color: '#0f172a' };
    const rw: React.CSSProperties = { borderBottom: '1px solid #e5e7eb' };
    return (
        <Modal title="Cost Entry — Details" onClose={onClose} size="540px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                {canEdit && <button type="button" onClick={onEdit} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Edit</button>}
            </>}
        >
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden' }}>
                <tbody>
                    <tr style={rw}><td style={lc}>Description</td><td style={vc}>{item.description}</td></tr>
                    <tr style={rw}><td style={lc}>Amount (PhP)</td><td style={{ ...vc, fontWeight: 700 }}>{money(item.amount)}</td></tr>
                    <tr style={rw}><td style={lc}>Date Logged</td><td style={vc}>{item.created}</td></tr>
                    <tr>
                        <td style={lc}>Attachment</td>
                        <td style={vc}>
                            {item.url
                                ? <a href={item.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontWeight: 600, textDecoration: 'none' }}>{item.filename}</a>
                                : <span style={{ color: '#94a3b8' }}>No attachment</span>
                            }
                        </td>
                    </tr>
                </tbody>
            </table>
        </Modal>
    );
}

// ── Edit Modal ─────────────────────────────────────────────────────────────
function EditModal({ project, item, onClose }: { project: HubProject; item: IocRow; onClose: () => void }) {
    const [desc, setDesc]     = useState(item.description);
    const [amount, setAmount] = useState(String(item.amount));
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const handleSave = () => {
        if (!desc.trim()) { setError('Description is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Amount must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.patch(route('hub.ioc.update', [project.id, item.id]), { description: desc, amount }, {
            preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title="Edit Cost Entry" onClose={onClose} size="500px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleSave} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Saving...' : 'Save Changes'}
                </button>
            </>}
        >
            <div style={{ display: 'grid', gap: '14px' }}>
                <Field label="Description">
                    <input style={inputStyle} value={desc} onChange={e => setDesc(e.target.value)} />
                </Field>
                <Field label="Amount (PhP)">
                    <div style={{ display: 'flex' }}>
                        <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px 0 0 6px', fontSize: '12.5px', color: '#475569' }}>PhP</span>
                        <input type="number" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} style={{ ...inputStyle, borderRadius: '0 6px 6px 0', borderLeft: 'none', padding: '5px 8px' }} />
                    </div>
                </Field>
            </div>
        </Modal>
    );
}

// ── Main Component ─────────────────────────────────────────────────────────
export default function AcrHub({ project, iocs, canEdit = true }: { project: HubProject; iocs: IocRow[]; canEdit?: boolean }) {
    const [desc, setDesc]       = useState('');
    const [amount, setAmount]   = useState('');
    const [saving, setSaving]   = useState(false);
    const [error, setError]     = useState('');
    const [viewing, setViewing] = useState<IocRow | null>(null);
    const [editing, setEditing] = useState<IocRow | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const hasSubRows  = iocs.some(r => !!r.sub_project_id);
    const otherCosts  = iocs.filter(r => !r.sub_project_id).reduce((s, r) => s + r.amount, 0);
    const paidBilling = project.budget_paid ?? 0;
    const totalActual = otherCosts + paidBilling;
    const variance    = project.budget_total - totalActual;
    const pctOther    = project.budget_total > 0 ? Math.round((otherCosts  / project.budget_total) * 100) : 0;
    const pctBilling  = project.budget_total > 0 ? Math.round((paidBilling / project.budget_total) * 100) : 0;
    const pctTotal    = project.budget_total > 0 ? Math.round((totalActual / project.budget_total) * 100) : 0;

    const handleAdd = () => {
        if (!desc.trim()) { setError('Description is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Amount must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('description', desc);
        fd.append('amount', amount);
        const file = fileRef.current?.files?.[0];
        if (file) fd.append('file', file);
        router.post(route('hub.ioc.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setDesc(''); setAmount(''); if (fileRef.current) fileRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (item: IocRow) => {
        showConfirm(`Delete "${item.description}"?`, () => {
            router.delete(route('hub.ioc.destroy', [project.id, item.id]), { preserveScroll: true });
        }, { title: 'Delete Record', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            {viewing && !editing && (
                <ViewModal item={viewing} onClose={() => setViewing(null)} onEdit={() => { setEditing(viewing); setViewing(null); }} canEdit={canEdit && !viewing.sub_project_id} />
            )}
            {editing && (
                <EditModal project={project} item={editing} onClose={() => setEditing(null)} />
            )}

            {/* ── Cost Overview ── */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '16px', padding: '22px', marginBottom: '18px' }}>
                <div style={{ fontSize: '11px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '18px' }}>Cost Overview</div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr 1fr', gap: '18px', alignItems: 'start' }}>
                    <Metric label="Approved Budget"    value={money(project.budget_total)} sub="Total CAPEX" />
                    <div>
                        <Metric label="Paid Billings" value={money(paidBilling)} sub={`${pctBilling}% of budget`} color="#7c3aed" />
                        <Bar pct={pctBilling} color="#7c3aed" />
                    </div>
                    <div>
                        <Metric label="Other Costs (IOC)" value={money(otherCosts)} sub={`${pctOther}% of budget`} color="#2563eb" />
                        <Bar pct={pctOther} color="#2563eb" />
                    </div>
                    <div>
                        <Metric label="Total Actual Cost" value={money(totalActual)} sub={`${pctTotal}% utilized`} color="#0f172a" />
                        <Bar pct={pctTotal} color="#0f172a" />
                    </div>
                    <Metric
                        label="Variance"
                        value={money(Math.abs(variance))}
                        sub={variance >= 0 ? 'Under Budget' : 'Over Budget'}
                        color={variance >= 0 ? '#16a34a' : '#dc2626'}
                        align="right"
                    />
                </div>
            </div>

            {/* ── Log New Expenditure ── */}
            {canEdit && (
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <h4 style={{ margin: '0 0 14px', fontSize: '15px' }}>Log New Actual Expenditure</h4>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 0.7fr 1fr 130px', gap: '12px', alignItems: 'end' }}>
                        <Field label="Cost Description">
                            <input style={inputStyle} placeholder="e.g. Milestone 1 Progress Payment" value={desc} onChange={e => setDesc(e.target.value)} />
                        </Field>
                        <Field label="Amount (PhP)">
                            <input type="number" step="0.01" style={inputStyle} placeholder="0.00" value={amount} onChange={e => setAmount(e.target.value)} />
                        </Field>
                        <Field label="Evidence Attachment">
                            <input type="file" ref={fileRef} style={inputStyle} />
                        </Field>
                        <button type="button" onClick={handleAdd} disabled={saving} style={{ padding: '8px 13px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                            {saving ? 'Saving...' : 'Add to Report'}
                        </button>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}

            {/* ── Expenditure Table ── */}
            <DataTable
                headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Description', 'Actual Cost (PhP)', 'Date Logged', 'Attachment', 'Actions']}
                rows={iocs.map((item, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    ...(hasSubRows ? [<SubTag no={item.sub_project_no} />] : []),
                    item.description,
                    <strong>{item.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                    <span style={{ color: '#64748b', fontSize: '12px' }}>{item.created}</span>,
                    item.filename
                        ? <a href={item.url ?? '#'} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{item.filename}</a>
                        : <span style={{ color: '#94a3b8' }}>—</span>,
                    <ActionBtns view edit={canEdit && !item.sub_project_id} del={canEdit && !item.sub_project_id} open={!!item.sub_project_id}
                        onView={() => setViewing(item)}
                        onEdit={() => setEditing(item)}
                        onDelete={() => handleDelete(item)}
                        onOpen={() => router.visit(route('projects.hub.acr', item.sub_project_id!))}
                    />,
                ])}
            />

            {/* ── Total row ── */}
            {iocs.length > 0 && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '10px', padding: '10px 16px', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                    <span style={{ fontSize: '13px', fontWeight: 700, color: '#374151' }}>
                        Total Other Costs:&nbsp;&nbsp;
                        <span style={{ color: '#2563eb', fontSize: '15px' }}>{money(otherCosts)}</span>
                    </span>
                </div>
            )}
        </HubShell>
    );
}
