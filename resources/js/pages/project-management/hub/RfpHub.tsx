import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, DataTable, HubProject, HubShell, Modal, inputStyle, money } from './Common';

interface BillingRow {
    id: number;
    stmt_no: string;
    billing_type: string;
    period_from: string;
    period_to: string;
    period_from_raw: string | null;
    period_to_raw: string | null;
    amount: number;
    progress_pct: number | null;
    summary: string | null;
    remarks: string | null;
    status: string;
    status_raw: string;
    filename: string | null;
    url: string | null;
}

const BILLING_TYPES = ['Down Payment', 'Retention', 'Milestone (Progress)', 'Variation', 'Final / Full Payment'];

const MAJOR_DOCS: Record<string, string[]> = {
    'Down Payment':        ['Billing Cover Letter', 'Billing Statement', 'Billing Summary', 'Testing Documents'],
    'Milestone/Retention': ['Approved NTP', 'Progress Report', 'Pictures', 'Signed Contract'],
    'Final/Variation':     ['Weather Chart', 'As-Built (for Final)', 'BLMC (for Variation)'],
};
const MINOR_DOCS: Record<string, string[]> = {
    'Partial/Final':    ['Billing Cover Letter', 'Billing Summary', 'Actual BLMC', 'Pictures'],
    'Retention/Others': ['Billing Reconciliation', 'Approved NTP'],
};

function CheckGroup({ label, items }: { label: string; items: string[] }) {
    return (
        <div>
            <div style={{ fontWeight: 800, fontSize: '11px', borderBottom: '1px solid #000', paddingBottom: '3px', marginBottom: '6px' }}>{label}</div>
            {items.map(item => (
                <label key={item} style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: '12px', marginBottom: '3px', cursor: 'pointer' }}>
                    <input type="checkbox" /> {item}
                </label>
            ))}
        </div>
    );
}

const rowStyle: React.CSSProperties = { borderBottom: '1px solid #000' };
const labelCell: React.CSSProperties = { background: '#f8fafc', fontWeight: 700, fontSize: '12.5px', padding: '8px 10px', width: '28%', borderRight: '1px solid #000', verticalAlign: 'top' };
const valCell: React.CSSProperties   = { padding: '8px 10px', verticalAlign: 'top', fontSize: '13px' };

// ── New Billing Modal ──────────────────────────────────────────────────────
function NewBillingModal({ project, onClose }: { project: HubProject; onClose: () => void }) {
    const [othersBilling, setOthersBilling] = useState(false);
    const [otherRec, setOtherRec]           = useState(false);
    const [billingType, setBillingType]     = useState('Milestone (Progress)');
    const [amount, setAmount]               = useState('');
    const [saving, setSaving]               = useState(false);
    const [error, setError]                 = useState('');

    const handleSubmit = () => {
        if (!amount || Number(amount) <= 0) { setError('Billed Amount is required and must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.rfp.store', project.id), { billing_type: billingType, amount }, {
            preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal title="New Payment Request Form" onClose={onClose} size="900px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#0f172a', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Submitting...' : 'Save & Submit Request'}
                </button>
            </>}
        >
            <BillingFormBody
                billingType={billingType} setBillingType={setBillingType}
                amount={amount} setAmount={setAmount}
                othersBilling={othersBilling} setOthersBilling={setOthersBilling}
                otherRec={otherRec} setOtherRec={setOtherRec}
                readOnly={false}
            />
        </Modal>
    );
}

// ── Edit Billing Modal ─────────────────────────────────────────────────────
function EditBillingModal({ project, billing, onClose }: { project: HubProject; billing: BillingRow; onClose: () => void }) {
    const [billingType, setBillingType] = useState(billing.billing_type);
    const [amount, setAmount]           = useState(String(billing.amount));
    const [periodFrom, setPeriodFrom]   = useState(billing.period_from_raw ?? '');
    const [periodTo, setPeriodTo]       = useState(billing.period_to_raw ?? '');
    const [progress, setProgress]       = useState(String(billing.progress_pct ?? ''));
    const [summary, setSummary]         = useState(billing.summary ?? '');
    const [remarks, setRemarks]         = useState(billing.remarks ?? '');
    const [status, setStatus]           = useState(billing.status_raw);
    const [saving, setSaving]           = useState(false);
    const [error, setError]             = useState('');

    const handleSubmit = () => {
        if (!amount || Number(amount) <= 0) { setError('Billed Amount is required.'); return; }
        setError('');
        setSaving(true);
        router.patch(route('hub.rfp.update', [project.id, billing.id]), {
            billing_type: billingType,
            amount,
            period_from: periodFrom || null,
            period_to:   periodTo   || null,
            progress_pct: progress  || null,
            summary:  summary  || null,
            remarks:  remarks  || null,
            status,
        }, { preserveScroll: true, onSuccess: onClose, onFinish: () => setSaving(false) });
    };

    return (
        <Modal title={`Edit Billing — ${billing.stmt_no}`} onClose={onClose} size="900px"
            footer={<>
                {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginRight: 'auto' }}>{error}</div>}
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleSubmit} disabled={saving} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                    {saving ? 'Saving...' : 'Save Changes'}
                </button>
            </>}
        >
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', marginBottom: 0, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Billing Details</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000', borderTop: 'none', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Type of Billing</td>
                        <td style={valCell}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 24px' }}>
                                {BILLING_TYPES.map(t => (
                                    <label key={t} style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>
                                        <input type="checkbox" checked={billingType === t} onChange={() => setBillingType(t)} /> {t}
                                    </label>
                                ))}
                            </div>
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billing Statement No.</td>
                        <td style={valCell}><span style={{ fontWeight: 700 }}>{billing.stmt_no}</span></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period From</td>
                        <td style={valCell}><input type="date" value={periodFrom} onChange={e => setPeriodFrom(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period To</td>
                        <td style={valCell}><input type="date" value={periodTo} onChange={e => setPeriodTo(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billed Amount</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex' }}>
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px 0 0 6px', fontSize: '12.5px', color: '#475569' }}>PhP</span>
                                <input type="number" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} style={{ ...inputStyle, borderRadius: '0 6px 6px 0', borderLeft: 'none', padding: '5px 8px' }} />
                            </div>
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Project Progress (%)</td>
                        <td style={valCell}><input value={progress} onChange={e => setProgress(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} placeholder="e.g. 50" /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Summary of Work Done</td>
                        <td style={valCell}><textarea rows={3} value={summary} onChange={e => setSummary(e.target.value)} style={{ ...inputStyle, resize: 'vertical' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Remarks</td>
                        <td style={valCell}><input value={remarks} onChange={e => setRemarks(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr>
                        <td style={labelCell}>Status</td>
                        <td style={valCell}>
                            <select value={status} onChange={e => setStatus(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }}>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </Modal>
    );
}

// ── View Billing Modal ─────────────────────────────────────────────────────
function ViewBillingModal({ billing, onClose, onEdit }: { billing: BillingRow; onClose: () => void; onEdit: () => void }) {
    const ro: React.CSSProperties = { padding: '8px 10px', verticalAlign: 'top', fontSize: '13px', color: '#0f172a' };
    return (
        <Modal title={`Billing — ${billing.stmt_no}`} onClose={onClose} size="860px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                {billing.status_raw === 'pending' && (
                    <button type="button" onClick={onEdit} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Edit</button>
                )}
            </>}
        >
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', marginBottom: 0, textTransform: 'uppercase' }}>Billing Details</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000', borderTop: 'none', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>
                    {([
                        ['Type of Billing',        billing.billing_type],
                        ['Billing Statement No.',  billing.stmt_no],
                        ['Period From',            billing.period_from],
                        ['Period To',              billing.period_to],
                        ['Billed Amount',          `PhP ${billing.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}`],
                        ['Project Progress (%)',   billing.progress_pct != null ? `${billing.progress_pct}%` : '—'],
                        ['Summary of Work Done',   billing.summary ?? '—'],
                        ['Remarks',                billing.remarks ?? '—'],
                        ['Status',                 billing.status],
                    ] as [string, string][]).map(([lbl, val]) => (
                        <tr key={lbl} style={rowStyle}>
                            <td style={labelCell}>{lbl}</td>
                            <td style={ro}>{val}</td>
                        </tr>
                    ))}
                    {billing.url && (
                        <tr>
                            <td style={labelCell}>Attached File</td>
                            <td style={ro}>
                                <a href={billing.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 600 }}>
                                    {billing.filename}
                                </a>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </Modal>
    );
}

// ── Shared form body used by NewBillingModal ───────────────────────────────
function BillingFormBody({
    billingType, setBillingType, amount, setAmount,
    othersBilling, setOthersBilling, otherRec, setOtherRec, readOnly,
}: {
    billingType: string; setBillingType: (v: string) => void;
    amount: string; setAmount: (v: string) => void;
    othersBilling: boolean; setOthersBilling: (v: boolean) => void;
    otherRec: boolean; setOtherRec: (v: boolean) => void;
    readOnly: boolean;
}) {
    return (
        <>
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', marginBottom: 0, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Billing Details</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000', borderTop: 'none', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Type of Billing</td>
                        <td style={valCell}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 24px' }}>
                                {BILLING_TYPES.map(t => (
                                    <label key={t} style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>
                                        <input type="checkbox" checked={billingType === t} onChange={() => setBillingType(t)} /> {t}
                                    </label>
                                ))}
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer' }}>
                                    <input type="checkbox" onChange={e => setOthersBilling(e.target.checked)} /> Others:
                                    {othersBilling && <input type="text" style={{ ...inputStyle, width: '80px', padding: '3px 6px', fontSize: '12px' }} />}
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billing Statement No.</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px' }} placeholder="Auto-generated on save" disabled /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billing Period / Date</td>
                        <td style={valCell}><input type="date" style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billed Amount</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '0' }}>
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px 0 0 6px', fontSize: '12.5px', color: '#475569' }}>PhP</span>
                                <input type="number" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} style={{ ...inputStyle, borderRadius: '0 6px 6px 0', borderLeft: 'none', padding: '5px 8px' }} />
                            </div>
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Project Progress (% Completed)</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px' }} placeholder="e.g. 50%" /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Summary of Work Done</td>
                        <td style={valCell}><textarea rows={3} style={{ ...inputStyle, resize: 'vertical' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Remarks</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr>
                        <td style={labelCell}>Recommendation</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '18px', alignItems: 'center', flexWrap: 'wrap' }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}><input type="radio" name="rec" defaultChecked /> For Payment</label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}><input type="radio" name="rec" /> Withhold (Pending Clarification)</label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec" onChange={e => setOtherRec(e.target.checked)} /> Others:
                                    {otherRec && <input type="text" style={{ ...inputStyle, width: '100px', padding: '3px 6px', fontSize: '12px' }} />}
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Attachments</div>
            <div style={{ display: 'flex', border: '1px solid #000', borderTop: 'none', fontSize: '12px' }}>
                <div style={{ flex: '1', borderRight: '1px solid #000' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Major Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr' }}>
                        {Object.entries(MAJOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i < 2 ? '1px solid #000' : 'none' }}>
                                <CheckGroup label={cat} items={items} />
                            </div>
                        ))}
                    </div>
                </div>
                <div style={{ width: '38%' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Minor Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr' }}>
                        {Object.entries(MINOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i === 0 ? '1px solid #000' : 'none' }}>
                                <CheckGroup label={cat} items={items} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

const STATUS_TONE: Record<string, 'blue' | 'green' | 'yellow' | 'slate'> = {
    Paid: 'blue', Approved: 'green', Pending: 'yellow',
};

// ── Main Component ─────────────────────────────────────────────────────────
export default function RfpHub({ project, billings }: { project: HubProject; billings: BillingRow[] }) {
    const [showNew, setShowNew]       = useState(false);
    const [viewing, setViewing]       = useState<BillingRow | null>(null);
    const [editing, setEditing]       = useState<BillingRow | null>(null);

    const budgetPaid = project.budget_paid ?? 0;
    const paidPct    = project.budget_total > 0 ? Math.round((budgetPaid / project.budget_total) * 100) : 0;

    const handleDelete = (b: BillingRow) => {
        if (!confirm(`Delete billing ${b.stmt_no}?`)) return;
        router.delete(route('hub.rfp.destroy', [project.id, b.id]), { preserveScroll: true });
    };

    const actionCell = (b: BillingRow) => {
        const isPending = b.status_raw === 'pending';
        return (
            <div style={{ display: 'flex', gap: '4px' }}>
                <button type="button" title="View" onClick={() => setViewing(b)}
                    style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fff', border: '1px solid #e2e8f0', color: '#475569' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                {isPending && (
                    <button type="button" title="Edit" onClick={() => setEditing(b)}
                        style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#eff6ff', border: '1px solid #bfdbfe', color: '#2563eb' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                )}
                {isPending && (
                    <button type="button" title="Delete" onClick={() => handleDelete(b)}
                        style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                )}
            </div>
        );
    };

    return (
        <HubShell>
            {showNew  && <NewBillingModal project={project} onClose={() => setShowNew(false)} />}
            {viewing  && !editing && (
                <ViewBillingModal
                    billing={viewing}
                    onClose={() => setViewing(null)}
                    onEdit={() => { setEditing(viewing); setViewing(null); }}
                />
            )}
            {editing  && (
                <EditBillingModal
                    project={project}
                    billing={editing}
                    onClose={() => setEditing(null)}
                />
            )}

            {/* Summary card */}
            <div style={{ border: '1px solid #e2e8f0', borderRadius: '12px', padding: '20px', marginBottom: '22px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '18px', alignItems: 'center' }}>
                <div>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase' }}>Total Project Cost</div>
                    <div style={{ fontSize: '26px', fontWeight: 900, color: '#0f172a' }}>{money(project.budget_total)}</div>
                    <div style={{ marginTop: '12px', height: '8px', borderRadius: '999px', background: '#e2e8f0' }}>
                        <div style={{ width: `${paidPct}%`, height: '100%', borderRadius: '999px', background: '#2563eb' }} />
                    </div>
                    <div style={{ marginTop: '6px', fontSize: '12px', color: '#64748b' }}>Paid: <strong>{money(budgetPaid)}</strong></div>
                </div>
                <div style={{ textAlign: 'center' }}>
                    <div style={{ width: '150px', height: '150px', borderRadius: '50%', margin: '0 auto', background: `conic-gradient(#2563eb ${paidPct}%, #e2e8f0 0)`, display: 'grid', placeItems: 'center' }}>
                        <div style={{ width: '102px', height: '102px', borderRadius: '50%', background: '#fff', display: 'grid', placeItems: 'center', fontWeight: 900, color: '#1e293b', fontSize: '20px' }}>{paidPct}%</div>
                    </div>
                </div>
                <div>
                    <h4 style={{ margin: '0 0 12px', fontSize: '15px' }}>Quick Actions</h4>
                    <div style={{ display: 'grid', gap: '8px' }}>
                        <Button variant="dark" onClick={() => setShowNew(true)}>Add New Billing</Button>
                        <Button variant="outline">Statement of Account</Button>
                    </div>
                </div>
            </div>

            <DataTable
                headers={['Seq#', 'Control#', 'Billing Type', 'Billed Amount', 'Progress %', 'Status', 'Actions']}
                rows={billings.map((b, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    <strong>{b.stmt_no}</strong>,
                    b.billing_type,
                    <strong>{b.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                    b.progress_pct != null ? `${b.progress_pct}%` : '—',
                    <Badge tone={STATUS_TONE[b.status] ?? 'slate'}>{b.status}</Badge>,
                    actionCell(b),
                ])}
            />
        </HubShell>
    );
}
