import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, Modal, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface CostCodeOption { value: string; label: string }
interface IocRow { id: number; description: string; cost_code: string | null; amount: number; filename: string | null; url: string | null; created: string }

function CostCodeSelect({ value, onChange, options, id }: {
    value: string;
    onChange: (value: string) => void;
    options: CostCodeOption[];
    id: string;
}) {
    const [open, setOpen] = useState(false);
    const selectedOption = options.find(option => option.value === value);
    const [inputValue, setInputValue] = useState(selectedOption?.label ?? value);

    useEffect(() => {
        const selected = options.find(option => option.value === value);
        setInputValue(selected?.label ?? value);
    }, [options, value]);

    const filtered = useMemo(() => {
        const needle = inputValue.trim().toLowerCase();
        return needle
            ? options.filter(option => option.label.toLowerCase().includes(needle) || option.value.toLowerCase().includes(needle)).slice(0, 8)
            : options.slice(0, 8);
    }, [inputValue, options]);

    return (
        <div style={{ position: 'relative' }}>
            <input
                value={inputValue}
                onChange={e => { setInputValue(e.target.value); onChange(e.target.value); setOpen(true); }}
                placeholder="Select or type cost code"
                role="combobox"
                aria-expanded={open}
                aria-controls={id}
                autoComplete="off"
                style={{ ...inputStyle, cursor: 'text', paddingRight: '34px' }}
                onFocus={e => { e.currentTarget.style.borderColor = '#2563eb'; e.currentTarget.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.08)'; setOpen(true); }}
                onBlur={e => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.boxShadow = 'none'; window.setTimeout(() => setOpen(false), 120); }}
            />
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2" style={{ position: 'absolute', right: '11px', top: '13px', pointerEvents: 'none' }}>
                <polyline points="6 9 12 15 18 9" />
            </svg>
            {open && (
                <div id={id} role="listbox" style={{ position: 'absolute', top: 'calc(100% + 5px)', left: 0, right: 0, zIndex: 60, background: '#fff', border: '1px solid #dbe3ef', borderRadius: '8px', boxShadow: '0 14px 32px rgba(15,23,42,0.14)', overflow: 'hidden', maxHeight: '220px', overflowY: 'auto' }}>
                    {filtered.length ? filtered.map(option => (
                        <button
                            key={option.value}
                            type="button"
                            onMouseDown={e => e.preventDefault()}
                            onClick={() => { onChange(option.value); setInputValue(option.label); setOpen(false); }}
                            style={{ width: '100%', border: 'none', background: option.value === value ? '#eff6ff' : '#fff', padding: '9px 12px', textAlign: 'left', fontSize: '13px', color: '#334155', cursor: 'pointer' }}
                        >
                            {option.label}
                        </button>
                    )) : (
                        <div style={{ padding: '10px 12px', fontSize: '12.5px', color: '#94a3b8' }}>No results found</div>
                    )}
                </div>
            )}
        </div>
    );
}

// ── View Modal ─────────────────────────────────────────────────────────────
function ViewIocModal({ item, onClose, onEdit, canEdit = true }: { item: IocRow; onClose: () => void; onEdit: () => void; canEdit?: boolean }) {
    const labelCell: React.CSSProperties = { background: '#f8fafc', fontWeight: 700, fontSize: '12.5px', padding: '10px 14px', width: '32%', borderRight: '1px solid #e5e7eb', color: '#374151' };
    const valCell: React.CSSProperties   = { padding: '10px 14px', fontSize: '13px', color: '#0f172a' };
    const row: React.CSSProperties       = { borderBottom: '1px solid #e5e7eb' };
    return (
        <Modal title="Other Cost — Details" onClose={onClose} size="560px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                {canEdit && <button type="button" onClick={onEdit} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Edit</button>}
            </>}
        >
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', fontSize: '13px' }}>
                <tbody>
                    <tr style={row}>
                        <td style={labelCell}>Description</td>
                        <td style={valCell}>{item.description}</td>
                    </tr>
                    <tr style={row}>
                        <td style={labelCell}>Cost Code</td>
                        <td style={valCell}>{item.cost_code ?? <span style={{ color: '#94a3b8' }}>Not set</span>}</td>
                    </tr>
                    <tr style={row}>
                        <td style={labelCell}>Cost (PhP)</td>
                        <td style={{ ...valCell, fontWeight: 700 }}>PhP {item.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    </tr>
                    <tr style={row}>
                        <td style={labelCell}>Date Added</td>
                        <td style={valCell}>{item.created}</td>
                    </tr>
                    <tr>
                        <td style={labelCell}>Attachment</td>
                        <td style={valCell}>
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
function EditIocModal({ project, item, costCodes, onClose }: { project: HubProject; item: IocRow; costCodes: CostCodeOption[]; onClose: () => void }) {
    const [desc, setDesc]     = useState(item.description);
    const [costCode, setCostCode] = useState(item.cost_code ?? '');
    const [amount, setAmount] = useState(String(item.amount));
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');

    const handleSave = () => {
        if (!desc.trim()) { setError('Description is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Cost must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.patch(route('hub.ioc.update', [project.id, item.id]), { description: desc, cost_code: costCode || null, amount }, {
            preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title="Edit Other Cost" onClose={onClose} size="520px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleSave} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Saving...' : 'Save Changes'}
                </button>
            </>}
        >
            <div style={{ display: 'grid', gap: '14px' }}>
                <Field label="Description of Expense">
                    <input style={inputStyle} value={desc} onChange={e => setDesc(e.target.value)} placeholder="e.g. Hauling services for site debris" />
                </Field>
                <Field label="Cost Code">
                    <CostCodeSelect id="edit-ioc-cost-code-options" value={costCode} onChange={setCostCode} options={costCodes} />
                </Field>
                <Field label="Cost (PhP)">
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
export default function IocHub({ project, iocs, costCodes = [], canEdit = true }: { project: HubProject; iocs: IocRow[]; costCodes?: CostCodeOption[]; canEdit?: boolean }) {
    const [desc, setDesc]     = useState('');
    const [costCode, setCostCode] = useState(project.cost_code ?? '');
    const [amount, setAmount] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError]   = useState('');
    const [viewing, setViewing] = useState<IocRow | null>(null);
    const [editing, setEditing] = useState<IocRow | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const total = iocs.reduce((s, r) => s + r.amount, 0);

    const handleSave = () => {
        if (!desc.trim()) { setError('Description of expense is required.'); return; }
        if (!amount || Number(amount) <= 0) { setError('Cost must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        const fd = new FormData();
        fd.append('description', desc);
        if (costCode) fd.append('cost_code', costCode);
        fd.append('amount', amount);
        const file = fileRef.current?.files?.[0];
        if (file) fd.append('file', file);
        router.post(route('hub.ioc.store', project.id), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setDesc(''); setCostCode(project.cost_code ?? ''); setAmount(''); if (fileRef.current) fileRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (ioc: IocRow) => {
        showConfirm(`Delete "${ioc.description}"?`, () => {
            router.delete(route('hub.ioc.destroy', [project.id, ioc.id]), { preserveScroll: true });
        }, { title: 'Delete Record', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            {viewing && !editing && (
                <ViewIocModal
                    item={viewing}
                    onClose={() => setViewing(null)}
                    onEdit={() => { setEditing(viewing); setViewing(null); }}
                    canEdit={canEdit}
                />
            )}
            {editing && (
                <EditIocModal
                    project={project}
                    item={editing}
                    costCodes={costCodes}
                    onClose={() => setEditing(null)}
                />
            )}

            <div style={{ background: 'linear-gradient(135deg, #1e293b 0%, #334155 100%)', color: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '22px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <div style={{ fontSize: '11px', fontWeight: 800, textTransform: 'uppercase', opacity: 0.78 }}>Aggregate Project Miscellaneous Expenses</div>
                    <div style={{ fontSize: '28px', fontWeight: 900, marginTop: '4px' }}>
                        PhP {total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </div>
                </div>
                <div style={{ textAlign: 'right', opacity: 0.75 }}>
                    <div style={{ fontSize: '11px', fontWeight: 800 }}>Project</div>
                    <div style={{ fontSize: '14px', fontWeight: 700 }}>{project.project_no}</div>
                </div>
            </div>

            {canEdit && (
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '18px', marginBottom: '22px' }}>
                    <h4 style={{ margin: '0 0 14px', fontSize: '15px' }}>Add New Miscellaneous Cost</h4>
                    <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 0.9fr 0.7fr 1fr 130px', gap: '12px', alignItems: 'end' }}>
                        <Field label="Description of Expense">
                            <input style={inputStyle} placeholder="e.g. Hauling services for site debris" value={desc} onChange={e => setDesc(e.target.value)} />
                        </Field>
                        <Field label="Cost Code">
                            <CostCodeSelect id="ioc-cost-code-options" value={costCode} onChange={setCostCode} options={costCodes} />
                        </Field>
                        <Field label="Cost (PhP)">
                            <input type="number" step="0.01" style={inputStyle} placeholder="0.00" value={amount} onChange={e => setAmount(e.target.value)} />
                        </Field>
                        <Field label="Receipt / Attachment"><input type="file" ref={fileRef} style={inputStyle} /></Field>
                        <button type="button" onClick={handleSave} disabled={saving} style={{ padding: '8px 13px', borderRadius: '7px', background: '#2563eb', color: '#fff', border: 'none', fontWeight: 800, cursor: 'pointer', fontSize: '12.5px' }}>
                            {saving ? 'Saving...' : 'Save Record'}
                        </button>
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}

            <DataTable
                headers={['Seq#', 'Description', 'Cost Code', 'Cost (PhP)', 'Attachment', 'Actions']}
                rows={iocs.map((item, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    item.description,
                    item.cost_code ?? <span style={{ color: '#94a3b8' }}>-</span>,
                    <strong>{item.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                    item.filename
                        ? <a href={item.url ?? '#'} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 700 }}>{item.filename}</a>
                        : <span style={{ color: '#94a3b8' }}>—</span>,
                    <ActionBtns view edit={canEdit} del={canEdit}
                        onView={() => setViewing(item)}
                        onEdit={() => setEditing(item)}
                        onDelete={() => handleDelete(item)}
                    />,
                ])}
            />
        </HubShell>
    );
}
