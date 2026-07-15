import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge, Button, DataTable, HubProject, HubShell, Modal, inputStyle, money } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface NtpOption {
    id: number;
    ntp_no: string;
    contractor: string;
    approved_cost: number;
}

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
    attachments: string[];
    recommendation: string | null;
    status: string;
    status_raw: string;
    filename: string | null;
    url: string | null;
    ntp_id: number | null;
    ntp_no: string | null;
    ntp_date: string | null;
    ntp_contractor: string | null;
    ntp_approved_cost: number | null;
    status_logs?: BillingStatusLog[];
}

interface BillingStatusLog {
    id: number;
    date: string;
    time: string;
    user: string;
    status: string;
    remarks: string;
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

function CheckGroup({ category, label, items, checked, onToggle }: {
    category: string;
    label: string;
    items: string[];
    checked: string[];
    onToggle: (item: string) => void;
}) {
    return (
        <div>
            <div style={{ fontWeight: 800, fontSize: '11px', borderBottom: '1px solid #000', paddingBottom: '3px', marginBottom: '6px' }}>{label}</div>
            {items.map(item => {
                // Prefixed with category since the same label (e.g. "Billing Summary") appears
                // under multiple categories and would otherwise share checked state between them.
                const key = `${category}: ${item}`;
                return (
                    <label key={item} style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: '12px', marginBottom: '3px', cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            checked={checked.includes(key)}
                            onChange={() => onToggle(key)}
                        /> {item}
                    </label>
                );
            })}
        </div>
    );
}

function formatBytes(bytes: number) {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const idx = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / Math.pow(1024, idx)).toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
}

function FileAttachmentField({
    file,
    onChange,
    existingFilename,
    existingUrl,
}: {
    file: File | null;
    onChange: (file: File | null) => void;
    existingFilename?: string | null;
    existingUrl?: string | null;
}) {
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    return (
        <div style={{ display: 'grid', gap: '8px' }}>
            <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                onChange={e => onChange(e.target.files?.[0] ?? null)}
                style={{ ...inputStyle, padding: '6px 8px' }}
            />
            {file ? (
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px', padding: '8px 10px', border: '1px solid #bfdbfe', borderRadius: '7px', background: '#eff6ff', color: '#1e40af' }}>
                    <div style={{ minWidth: 0 }}>
                        <div style={{ fontWeight: 700, fontSize: '12.5px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{file.name}</div>
                        <div style={{ fontSize: '11px', color: '#64748b' }}>{formatBytes(file.size)}</div>
                    </div>
                    <div style={{ display: 'flex', gap: '8px', flexShrink: 0 }}>
                        {previewUrl && (
                            <a href={previewUrl} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontSize: '12px', fontWeight: 700, textDecoration: 'none' }}>
                                Preview
                            </a>
                        )}
                        <button type="button" onClick={() => onChange(null)} style={{ border: 'none', background: 'transparent', color: '#dc2626', fontSize: '12px', fontWeight: 700, cursor: 'pointer' }}>
                            Remove
                        </button>
                    </div>
                </div>
            ) : existingUrl ? (
                <div style={{ fontSize: '12.5px', color: '#475569' }}>
                    Current file:{' '}
                    <a href={existingUrl} target="_blank" rel="noreferrer" style={{ color: '#2563eb', fontWeight: 700, textDecoration: 'none' }}>
                        {existingFilename ?? 'View attachment'}
                    </a>
                </div>
            ) : (
                <div style={{ fontSize: '11px', color: '#64748b' }}>Optional supporting file for review before saving.</div>
            )}
        </div>
    );
}

const rowStyle: React.CSSProperties = { borderBottom: '1px solid #000' };
const labelCell: React.CSSProperties = { background: '#f8fafc', fontWeight: 700, fontSize: '12.5px', padding: '8px 10px', width: '28%', borderRight: '1px solid #000', verticalAlign: 'top' };
const valCell: React.CSSProperties   = { padding: '8px 10px', verticalAlign: 'top', fontSize: '13px' };

// ── New Billing Modal ──────────────────────────────────────────────────────
function NewBillingModal({ project, ntps, onClose }: { project: HubProject; ntps: NtpOption[]; onClose: () => void }) {
    const [ntpId,        setNtpId]        = useState('');
    const [billingType,  setBillingType]  = useState('Milestone (Progress)');
    const [amount,       setAmount]       = useState('');
    const [progress,     setProgress]     = useState('');
    const [periodFrom,   setPeriodFrom]   = useState('');
    const [periodTo,     setPeriodTo]     = useState('');
    const [summary,      setSummary]      = useState('');
    const [remarks,      setRemarks]      = useState('');
    const [attachments,  setAttachments]  = useState<string[]>([]);
    const [file,         setFile]         = useState<File | null>(null);
    const [othersBilling,setOthersBilling]= useState(false);
    const [recommendation, setRecommendation] = useState('For Payment');
    const [otherRec,     setOtherRec]     = useState(false);
    const [otherRecText, setOtherRecText] = useState('');
    const [saving,       setSaving]       = useState(false);
    const [error,        setError]        = useState('');

    const selectedNtp  = ntps.find(n => String(n.id) === ntpId) ?? null;

    const toggleAttachment = (item: string) =>
        setAttachments(prev => prev.includes(item) ? prev.filter(x => x !== item) : [...prev, item]);
    const base         = selectedNtp?.approved_cost || project.budget_total || 0;

    const handleAmountChange = (val: string) => {
        setAmount(val);
        const num = parseFloat(val);
        if (!isNaN(num) && num >= 0 && base > 0) {
            setProgress(((num / base) * 100).toFixed(2));
        } else if (val === '') {
            setProgress('');
        }
    };

    const handleProgressChange = (val: string) => {
        setProgress(val);
        const num = parseFloat(val);
        if (!isNaN(num) && num >= 0 && base > 0) {
            setAmount(((num / 100) * base).toFixed(2));
        } else if (val === '') {
            setAmount('');
        }
    };

    const handleSubmit = () => {
        if (!amount || Number(amount) <= 0) { setError('Billed Amount is required and must be greater than zero.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.rfp.store', project.id), {
            project_ntp_id: ntpId || null,
            billing_type:   billingType,
            amount,
            progress_pct:   progress   || null,
            period_from:    periodFrom || null,
            period_to:      periodTo   || null,
            summary:        summary    || null,
            remarks:        remarks    || null,
            attachments,
            recommendation: recommendation === 'Others' && otherRecText.trim() ? otherRecText.trim() : recommendation,
            file,
        }, { preserveScroll: true, forceFormData: true, onSuccess: onClose, onFinish: () => setSaving(false) });
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
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', marginBottom: 0, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Billing Details</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000', borderTop: 'none', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>

                    {/* NTP Selector */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Notice to Proceed</td>
                        <td style={valCell}>
                            {ntps.length === 0 ? (
                                <span style={{ color: '#94a3b8', fontSize: '12px' }}>No NTP issued for this project yet.</span>
                            ) : (
                                <>
                                    <select
                                        style={{ ...inputStyle, padding: '5px 8px', marginBottom: selectedNtp ? '10px' : '0' }}
                                        value={ntpId}
                                        onChange={e => { setNtpId(e.target.value); setAmount(''); setProgress(''); }}
                                    >
                                        <option value="">— Select NTP / Awardee (optional) —</option>
                                        {ntps.map(n => (
                                            <option key={n.id} value={String(n.id)}>
                                                {n.ntp_no} — {n.contractor}
                                            </option>
                                        ))}
                                    </select>
                                    {selectedNtp && (
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', padding: '10px 12px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '7px' }}>
                                            <div>
                                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>NTP No.</div>
                                                <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{selectedNtp.ntp_no}</div>
                                            </div>
                                            <div>
                                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contractor</div>
                                                <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{selectedNtp.contractor}</div>
                                            </div>
                                            <div>
                                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contract Value</div>
                                                <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{money(selectedNtp.approved_cost)}</div>
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </td>
                    </tr>

                    {/* Type of Billing */}
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

                    {/* Statement No */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billing Statement No.</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px', color: '#94a3b8' }} placeholder="Auto-generated on save" disabled /></td>
                    </tr>

                    {/* Period */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period From</td>
                        <td style={valCell}><input type="date" style={{ ...inputStyle, padding: '5px 8px' }} value={periodFrom} onChange={e => setPeriodFrom(e.target.value)} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period To</td>
                        <td style={valCell}><input type="date" style={{ ...inputStyle, padding: '5px 8px' }} value={periodTo} onChange={e => setPeriodTo(e.target.value)} /></td>
                    </tr>

                    {/* Billed Amount ↔ Progress % two-way */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billed Amount</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '0', marginBottom: '6px' }}>
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px 0 0 6px', fontSize: '12.5px', color: '#475569' }}>PhP</span>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={amount}
                                    onChange={e => handleAmountChange(e.target.value)}
                                    style={{ ...inputStyle, borderRadius: '0 6px 6px 0', borderLeft: 'none', padding: '5px 8px' }}
                                    placeholder="0.00"
                                />
                            </div>
                            {base > 0 ? (
                                <div style={{ fontSize: '11px', color: '#64748b' }}>
                                    Base for calculation: <strong>{money(base)}</strong>
                                    {selectedNtp ? ' (NTP contract value)' : ' (project budget)'}
                                </div>
                            ) : (
                                <div style={{ fontSize: '11px', color: '#f59e0b' }}>
                                    No base amount set — select an NTP or set a project budget to enable auto-calculation.
                                </div>
                            )}
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Percent of Project Cost</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '0' }}>
                                <input
                                    type="number" step="0.01" min="0" max="100"
                                    value={progress}
                                    onChange={e => handleProgressChange(e.target.value)}
                                    style={{ ...inputStyle, borderRadius: '6px 0 0 6px', padding: '5px 8px' }}
                                    placeholder="0.00"
                                />
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderLeft: 'none', borderRadius: '0 6px 6px 0', fontSize: '12.5px', color: '#475569' }}>%</span>
                            </div>
                        </td>
                    </tr>

                    {/* Summary & Remarks */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Summary of Work Done</td>
                        <td style={valCell}><textarea rows={3} style={{ ...inputStyle, resize: 'vertical' }} value={summary} onChange={e => setSummary(e.target.value)} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Remarks</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px' }} value={remarks} onChange={e => setRemarks(e.target.value)} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Attached File</td>
                        <td style={valCell}><FileAttachmentField file={file} onChange={setFile} /></td>
                    </tr>

                    {/* Recommendation */}
                    <tr>
                        <td style={labelCell}>Recommendation</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '18px', alignItems: 'center', flexWrap: 'wrap' }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec" checked={recommendation === 'For Payment'} onChange={() => { setRecommendation('For Payment'); setOtherRec(false); }} /> For Payment
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec" checked={recommendation === 'Withhold (Pending Clarification)'} onChange={() => { setRecommendation('Withhold (Pending Clarification)'); setOtherRec(false); }} /> Withhold (Pending Clarification)
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec" checked={recommendation === 'Others'} onChange={() => { setRecommendation('Others'); setOtherRec(true); }} /> Others:
                                    {otherRec && <input type="text" style={{ ...inputStyle, width: '100px', padding: '3px 6px', fontSize: '12px' }} value={otherRecText} onChange={e => setOtherRecText(e.target.value)} />}
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            {/* Attachments checklist */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Attachments</div>
            <div style={{ display: 'flex', border: '1px solid #000', borderTop: 'none', fontSize: '12px' }}>
                <div style={{ flex: '1', borderRight: '1px solid #000' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Major Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr' }}>
                        {Object.entries(MAJOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i < 2 ? '1px solid #000' : 'none' }}>
                                <CheckGroup category={cat} label={cat} items={items} checked={attachments} onToggle={toggleAttachment} />
                            </div>
                        ))}
                    </div>
                </div>
                <div style={{ width: '38%' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Minor Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr' }}>
                        {Object.entries(MINOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i === 0 ? '1px solid #000' : 'none' }}>
                                <CheckGroup category={cat} label={cat} items={items} checked={attachments} onToggle={toggleAttachment} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}

// ── Edit Billing Modal ─────────────────────────────────────────────────────
function EditBillingModal({ project, billing, onClose }: { project: HubProject; billing: BillingRow; onClose: () => void }) {
    const [billingType,   setBillingType]   = useState(billing.billing_type);
    const [amount,        setAmount]        = useState(String(billing.amount));
    const [progress,      setProgress]      = useState(billing.progress_pct != null ? String(billing.progress_pct) : '');
    const [periodFrom,    setPeriodFrom]    = useState(billing.period_from_raw ?? '');
    const [periodTo,      setPeriodTo]      = useState(billing.period_to_raw   ?? '');
    const [summary,       setSummary]       = useState(billing.summary  ?? '');
    const [remarks,       setRemarks]       = useState(billing.remarks  ?? '');
    const [attachments,   setAttachments]   = useState<string[]>(billing.attachments ?? []);
    const [file,          setFile]          = useState<File | null>(null);
    const [othersBilling, setOthersBilling] = useState(false);
    const KNOWN_RECS = ['For Payment', 'Withhold (Pending Clarification)'];
    const [recommendation, setRecommendation] = useState(
        billing.recommendation && KNOWN_RECS.includes(billing.recommendation) ? billing.recommendation : (billing.recommendation ? 'Others' : 'For Payment')
    );
    const [otherRec,      setOtherRec]      = useState(!!billing.recommendation && !KNOWN_RECS.includes(billing.recommendation));
    const [otherRecText,  setOtherRecText]  = useState(!!billing.recommendation && !KNOWN_RECS.includes(billing.recommendation) ? billing.recommendation : '');
    const [saving,        setSaving]        = useState(false);
    const [error,         setError]         = useState('');

    const toggleAttachment = (item: string) =>
        setAttachments(prev => prev.includes(item) ? prev.filter(x => x !== item) : [...prev, item]);

    const base = billing.ntp_approved_cost || project.budget_total || 0;
    const baseLabel = billing.ntp_approved_cost ? 'NTP contract value' : 'project budget';

    const handleAmountChange = (val: string) => {
        setAmount(val);
        const num = parseFloat(val);
        if (!isNaN(num) && num >= 0 && base > 0) {
            setProgress(((num / base) * 100).toFixed(2));
        } else if (val === '') {
            setProgress('');
        }
    };

    const handleProgressChange = (val: string) => {
        setProgress(val);
        const num = parseFloat(val);
        if (!isNaN(num) && num >= 0 && base > 0) {
            setAmount(((num / 100) * base).toFixed(2));
        } else if (val === '') {
            setAmount('');
        }
    };

    const handleSubmit = () => {
        if (!amount || Number(amount) <= 0) { setError('Billed Amount is required.'); return; }
        setError('');
        setSaving(true);
        router.post(route('hub.rfp.update', [project.id, billing.id]), {
            _method: 'patch',
            billing_type:  billingType,
            amount,
            period_from:   periodFrom  || null,
            period_to:     periodTo    || null,
            progress_pct:  progress    || null,
            summary:       summary     || null,
            remarks:       remarks     || null,
            attachments,
            recommendation: recommendation === 'Others' && otherRecText.trim() ? otherRecText.trim() : recommendation,
            file,
        }, { preserveScroll: true, forceFormData: true, onSuccess: onClose, onFinish: () => setSaving(false) });
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

                    {/* NTP — read-only */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Notice to Proceed</td>
                        <td style={valCell}>
                            {billing.ntp_contractor ? (
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', padding: '10px 12px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '7px' }}>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>NTP No.</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{billing.ntp_no}</div>
                                    </div>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contractor</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{billing.ntp_contractor}</div>
                                    </div>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contract Value</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>
                                            {billing.ntp_approved_cost != null ? money(billing.ntp_approved_cost) : '—'}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <span style={{ color: '#94a3b8', fontSize: '12px' }}>No NTP linked to this billing.</span>
                            )}
                        </td>
                    </tr>

                    {/* Type of Billing */}
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

                    {/* Statement No — read-only */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billing Statement No.</td>
                        <td style={valCell}><span style={{ fontWeight: 700 }}>{billing.stmt_no}</span></td>
                    </tr>

                    {/* Period */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period From</td>
                        <td style={valCell}><input type="date" value={periodFrom} onChange={e => setPeriodFrom(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Period To</td>
                        <td style={valCell}><input type="date" value={periodTo} onChange={e => setPeriodTo(e.target.value)} style={{ ...inputStyle, padding: '5px 8px' }} /></td>
                    </tr>

                    {/* Billed Amount ↔ Progress % two-way */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Billed Amount</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '0', marginBottom: '6px' }}>
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderRadius: '6px 0 0 6px', fontSize: '12.5px', color: '#475569' }}>PhP</span>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={amount}
                                    onChange={e => handleAmountChange(e.target.value)}
                                    style={{ ...inputStyle, borderRadius: '0 6px 6px 0', borderLeft: 'none', padding: '5px 8px' }}
                                    placeholder="0.00"
                                />
                            </div>
                            {base > 0 ? (
                                <div style={{ fontSize: '11px', color: '#64748b' }}>
                                    Base for calculation: <strong>{money(base)}</strong> ({baseLabel})
                                </div>
                            ) : (
                                <div style={{ fontSize: '11px', color: '#f59e0b' }}>
                                    No base amount set — enter % manually.
                                </div>
                            )}
                        </td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Percent of Project Cost</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '0' }}>
                                <input
                                    type="number" step="0.01" min="0" max="100"
                                    value={progress}
                                    onChange={e => handleProgressChange(e.target.value)}
                                    style={{ ...inputStyle, borderRadius: '6px 0 0 6px', padding: '5px 8px' }}
                                    placeholder="0.00"
                                />
                                <span style={{ padding: '6px 10px', background: '#f1f5f9', border: '1px solid #e2e8f0', borderLeft: 'none', borderRadius: '0 6px 6px 0', fontSize: '12.5px', color: '#475569' }}>%</span>
                            </div>
                        </td>
                    </tr>

                    {/* Summary & Remarks */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Summary of Work Done</td>
                        <td style={valCell}><textarea rows={3} style={{ ...inputStyle, resize: 'vertical' }} value={summary} onChange={e => setSummary(e.target.value)} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Remarks</td>
                        <td style={valCell}><input style={{ ...inputStyle, padding: '5px 8px' }} value={remarks} onChange={e => setRemarks(e.target.value)} /></td>
                    </tr>
                    <tr style={rowStyle}>
                        <td style={labelCell}>Attached File</td>
                        <td style={valCell}>
                            <FileAttachmentField
                                file={file}
                                onChange={setFile}
                                existingFilename={billing.filename}
                                existingUrl={billing.url}
                            />
                        </td>
                    </tr>

                    {/* Recommendation */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Recommendation</td>
                        <td style={valCell}>
                            <div style={{ display: 'flex', gap: '18px', alignItems: 'center', flexWrap: 'wrap' }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec_edit" checked={recommendation === 'For Payment'} onChange={() => { setRecommendation('For Payment'); setOtherRec(false); }} /> For Payment
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec_edit" checked={recommendation === 'Withhold (Pending Clarification)'} onChange={() => { setRecommendation('Withhold (Pending Clarification)'); setOtherRec(false); }} /> Withhold (Pending Clarification)
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontWeight: 600 }}>
                                    <input type="radio" name="rec_edit" checked={recommendation === 'Others'} onChange={() => { setRecommendation('Others'); setOtherRec(true); }} /> Others:
                                    {otherRec && <input type="text" style={{ ...inputStyle, width: '100px', padding: '3px 6px', fontSize: '12px' }} value={otherRecText} onChange={e => setOtherRecText(e.target.value)} />}
                                </label>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>

            {/* Attachments checklist */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Attachments</div>
            <div style={{ display: 'flex', border: '1px solid #000', borderTop: 'none', fontSize: '12px' }}>
                <div style={{ flex: '1', borderRight: '1px solid #000' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Major Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr' }}>
                        {Object.entries(MAJOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i < 2 ? '1px solid #000' : 'none' }}>
                                <CheckGroup category={cat} label={cat} items={items} checked={attachments} onToggle={toggleAttachment} />
                            </div>
                        ))}
                    </div>
                </div>
                <div style={{ width: '38%' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Minor Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr' }}>
                        {Object.entries(MINOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i === 0 ? '1px solid #000' : 'none' }}>
                                <CheckGroup category={cat} label={cat} items={items} checked={attachments} onToggle={toggleAttachment} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}

// ── Read-only attachments checklist (View modal) ───────────────────────────
function ReadOnlyCheckGroup({ category, label, items, checked }: {
    category: string;
    label: string;
    items: string[];
    checked: string[];
}) {
    return (
        <div>
            <div style={{ fontWeight: 800, fontSize: '11px', borderBottom: '1px solid #000', paddingBottom: '3px', marginBottom: '6px' }}>{label}</div>
            {items.map(item => {
                const key = `${category}: ${item}`;
                const isChecked = checked.includes(key);
                return (
                    <div key={item} style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: '12px', marginBottom: '3px', color: isChecked ? '#0f172a' : '#94a3b8' }}>
                        <span>{isChecked ? '☑' : '☐'}</span> {item}
                    </div>
                );
            })}
        </div>
    );
}

// ── View Billing Modal ─────────────────────────────────────────────────────
function ViewBillingModal({ billing, onClose, onEdit, canEdit = true }: { billing: BillingRow; onClose: () => void; onEdit: () => void; canEdit?: boolean }) {
    const ro: React.CSSProperties = { padding: '8px 10px', verticalAlign: 'top', fontSize: '13px', color: '#0f172a' };
    return (
        <Modal title={`Billing — ${billing.stmt_no}`} onClose={onClose} size="900px"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Close</button>
                {canEdit && (
                    <button type="button" onClick={onEdit} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>Edit</button>
                )}
            </>}
        >
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', marginBottom: 0, textTransform: 'uppercase' }}>Billing Details</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000', borderTop: 'none', marginBottom: '20px', fontSize: '12.5px' }}>
                <tbody>
                    {/* NTP card */}
                    <tr style={rowStyle}>
                        <td style={labelCell}>Notice to Proceed</td>
                        <td style={ro}>
                            {billing.ntp_contractor ? (
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', padding: '10px 12px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '7px' }}>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>NTP No.</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{billing.ntp_no}</div>
                                    </div>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contractor</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>{billing.ntp_contractor}</div>
                                    </div>
                                    <div>
                                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', marginBottom: '2px' }}>Contract Value</div>
                                        <div style={{ fontWeight: 700, color: '#1e3a8a' }}>
                                            {billing.ntp_approved_cost != null ? money(billing.ntp_approved_cost) : '—'}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <span style={{ color: '#94a3b8', fontSize: '12px' }}>No NTP linked to this billing.</span>
                            )}
                        </td>
                    </tr>

                    {([
                        ['Type of Billing',       billing.billing_type],
                        ['Billing Statement No.', billing.stmt_no],
                        ['Period From',           billing.period_from],
                        ['Period To',             billing.period_to],
                        ['Billed Amount',         `PhP ${billing.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}`],
                        ['Percent of Project Cost',  billing.progress_pct != null ? `${billing.progress_pct}%` : '—'],
                        ['Summary of Work Done',  billing.summary ?? '—'],
                        ['Remarks',               billing.remarks ?? '—'],
                        ['Recommendation',        billing.recommendation ?? '—'],
                        ['Status',                billing.status],
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

            {/* Attachments checklist */}
            <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 900, padding: '6px', border: '1px solid #000', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Attachments</div>
            <div style={{ display: 'flex', border: '1px solid #000', borderTop: 'none', fontSize: '12px' }}>
                <div style={{ flex: '1', borderRight: '1px solid #000' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Major Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr' }}>
                        {Object.entries(MAJOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i < 2 ? '1px solid #000' : 'none' }}>
                                <ReadOnlyCheckGroup category={cat} label={cat} items={items} checked={billing.attachments ?? []} />
                            </div>
                        ))}
                    </div>
                </div>
                <div style={{ width: '38%' }}>
                    <div style={{ background: '#ffff00', textAlign: 'center', fontWeight: 700, padding: '4px', borderBottom: '1px solid #000' }}>Minor Projects</div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr' }}>
                        {Object.entries(MINOR_DOCS).map(([cat, items], i) => (
                            <div key={cat} style={{ padding: '8px', borderRight: i === 0 ? '1px solid #000' : 'none' }}>
                                <ReadOnlyCheckGroup category={cat} label={cat} items={items} checked={billing.attachments ?? []} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}

const STATUS_TONE: Record<string, 'blue' | 'green' | 'yellow' | 'slate'> = {
    Approved: 'green', Pending: 'yellow',
};

// ── Billing Status Meta ────────────────────────────────────────────────────
const BILLING_STATUS_META: Record<string, { label: string; bg: string; color: string; border: string; hint: string }> = {
    pending:  { label: 'Pending',  bg: '#fffbeb', color: '#b45309', border: '#fde68a', hint: 'Awaiting review. The billing has been submitted but not yet approved for payment.' },
    approved: { label: 'Approved', bg: '#f0fdf4', color: '#15803d', border: '#bbf7d0', hint: 'Reviewed and cleared for payment. This amount is added to the project’s total paid.' },
};

// ── Billing Status Change Modal ────────────────────────────────────────────
function StatusChangeModal({ project, billing, onClose }: { project: HubProject; billing: BillingRow; onClose: () => void }) {
    const [selectedKey, setSelectedKey] = useState(billing.status_raw);
    const [remarks, setRemarks]         = useState('');
    const [posting, setPosting]         = useState(false);

    const selectedMeta = BILLING_STATUS_META[selectedKey];
    const changed      = selectedKey !== billing.status_raw;
    const logs         = billing.status_logs ?? [];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedKey) return;
        setPosting(true);
        router.patch(route('hub.rfp.update-status', [project.id, billing.id]), {
            status: selectedKey,
            remarks: remarks || null,
        }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setPosting(false),
        });
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 400, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', zIndex: 401, width: '100%', maxWidth: '560px', maxHeight: '90vh', boxShadow: '0 20px 60px rgba(0,0,0,0.2)', display: 'flex', flexDirection: 'column' }}>
                {/* Header */}
                <div style={{ padding: '12px 20px', background: '#1e293b', borderRadius: '12px 12px 0 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>Update Billing Status</span>
                    <button onClick={onClose} style={{ width: '26px', height: '26px', borderRadius: '5px', border: '1px solid rgba(255,255,255,0.2)', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Body */}
                <div style={{ padding: '22px', overflowY: 'auto', flex: 1 }}>
                    <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '16px' }}>
                        Billing <strong style={{ color: '#0f172a' }}>{billing.stmt_no}</strong>
                        {billing.ntp_contractor && <> — {billing.ntp_contractor}</>}
                    </div>

                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#2563eb', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                        Change Status Form
                    </div>

                    {(
                        <form id="billingStatusForm" onSubmit={handleSubmit}>
                            <div style={{ marginBottom: '14px' }}>
                                <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Select New Status</label>
                                <select
                                    value={selectedKey}
                                    onChange={e => setSelectedKey(e.target.value)}
                                    required
                                    style={{ width: '100%', padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none', fontFamily: 'inherit' }}
                                >
                                    {Object.entries(BILLING_STATUS_META).map(([k, v]) => (
                                        <option key={k} value={k}>{v.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div style={{ marginBottom: '14px' }}>
                                <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Modification Remarks</label>
                                <textarea
                                    rows={2}
                                    value={remarks}
                                    onChange={e => setRemarks(e.target.value)}
                                    placeholder="Explain the status change..."
                                    style={{ width: '100%', padding: '8px 12px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', resize: 'vertical', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box' }}
                                />
                            </div>

                            {selectedMeta && (
                                <div style={{ padding: '14px', borderRadius: '8px', background: selectedMeta.bg, border: `1px solid ${selectedMeta.border}`, color: selectedMeta.color, fontSize: '12.5px' }}>
                                    <div style={{ fontWeight: 700, textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.3px', marginBottom: '4px' }}>
                                        Status: {selectedMeta.label}
                                    </div>
                                    <div>{selectedMeta.hint}</div>
                                </div>
                            )}
                        </form>
                    )}

                    {/* Status Log & Audit Trail */}
                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#64748b', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '12px', textTransform: 'uppercase', letterSpacing: '0.4px', marginTop: '26px' }}>
                        Status Log & Audit Trail
                    </div>
                    <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', maxHeight: '220px', overflowY: 'auto' }}>
                        {logs.length === 0 ? (
                            <div style={{ padding: '22px', textAlign: 'center', fontSize: '12.5px', color: '#94a3b8' }}>
                                No status changes recorded yet.
                            </div>
                        ) : (
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px' }}>
                                <thead>
                                    <tr style={{ background: '#f8fafc' }}>
                                        {['Date & Time', 'User', 'Status', 'Remarks'].map(h => (
                                            <th key={h} style={{ padding: '9px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e5e7eb' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.map((log, i) => {
                                        const meta = BILLING_STATUS_META[log.status.toLowerCase()];
                                        return (
                                            <tr key={log.id ?? i} style={{ borderBottom: '1px solid #f3f4f6' }}>
                                                <td style={{ padding: '9px 12px' }}>
                                                    <div style={{ fontWeight: 700, color: '#475569' }}>{log.date}</div>
                                                    <div style={{ fontSize: '11px', color: '#9ca3af' }}>{log.time}</div>
                                                </td>
                                                <td style={{ padding: '9px 12px', fontWeight: 600, color: '#1e293b' }}>{log.user}</td>
                                                <td style={{ padding: '9px 12px' }}>
                                                    <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: meta?.bg ?? '#f1f5f9', color: meta?.color ?? '#475569' }}>
                                                        {log.status}
                                                    </span>
                                                </td>
                                                <td style={{ padding: '9px 12px', color: '#6b7280', fontSize: '12px' }}>{log.remarks}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                {/* Footer */}
                <div style={{ padding: '12px 20px', borderTop: '1px solid #e5e7eb', background: '#f8fafc', borderRadius: '0 0 12px 12px', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151' }}>Close</button>
                    <button
                        type="submit"
                        form="billingStatusForm"
                        disabled={posting || !changed}
                        style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: posting || !changed ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: posting || !changed ? 'not-allowed' : 'pointer' }}
                    >
                        {posting ? 'Updating…' : 'Update Status'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Main Component ─────────────────────────────────────────────────────────
export default function RfpHub({ project, billings, ntps, canEdit = true, canManageStatus = false }: { project: HubProject; billings: BillingRow[]; ntps: NtpOption[]; canEdit?: boolean; canManageStatus?: boolean }) {
    const [showNew, setShowNew] = useState(false);
    const [viewing, setViewing] = useState<BillingRow | null>(null);
    const [editing, setEditing] = useState<BillingRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<BillingRow | null>(null);
    const [ntpFilter, setNtpFilter] = useState('');
    const [contractorFilter, setContractorFilter] = useState('');

    // Distinct NTP numbers / contractors present on this project's billings.
    const ntpOptions        = Array.from(new Set(billings.map(b => b.ntp_no).filter((v): v is string => !!v))).sort();
    const contractorOptions = Array.from(new Set(billings.map(b => b.ntp_contractor).filter((v): v is string => !!v))).sort();

    const filteredBillings = billings.filter(b =>
        (!ntpFilter        || b.ntp_no === ntpFilter) &&
        (!contractorFilter || b.ntp_contractor === contractorFilter)
    );

    const budgetPaid = project.budget_paid ?? 0;
    const paidPct    = project.budget_total > 0 ? Math.round((budgetPaid / project.budget_total) * 100) : 0;

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = (b: BillingRow) => {
        showConfirm(`Delete billing ${b.stmt_no}?`, () => {
            router.delete(route('hub.rfp.destroy', [project.id, b.id]), { preserveScroll: true });
        }, { title: 'Delete Billing', confirmLabel: 'Delete', variant: 'danger' });
    };

    const actionCell = (b: BillingRow) => {
        return (
            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                <button type="button" title="View" onClick={() => setViewing(b)}
                    style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#fff', border: '1px solid #e2e8f0', color: '#475569' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                {canEdit && (
                    <button type="button" title="Edit" onClick={() => setEditing(b)}
                        style={{ width: '28px', height: '28px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: '#eff6ff', border: '1px solid #bfdbfe', color: '#2563eb' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                )}
                {canEdit && (
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
            {confirmDialog}
            {showNew && <NewBillingModal project={project} ntps={ntps} onClose={() => setShowNew(false)} />}
            {viewing && !editing && (
                <ViewBillingModal
                    billing={viewing}
                    onClose={() => setViewing(null)}
                    onEdit={() => { setEditing(viewing); setViewing(null); }}
                    canEdit={canEdit}
                />
            )}
            {editing && (
                <EditBillingModal project={project} billing={editing} onClose={() => setEditing(null)} />
            )}
            {statusTarget && (
                <StatusChangeModal project={project} billing={statusTarget} onClose={() => setStatusTarget(null)} />
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
                        {canEdit && <Button variant="dark" onClick={() => setShowNew(true)}>Add New Billing</Button>}
                        <Button variant="outline">Statement of Account</Button>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', alignItems: 'flex-end', marginBottom: '12px' }}>
                <div>
                    <label style={{ display: 'block', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '4px' }}>NTP No.</label>
                    <select value={ntpFilter} onChange={e => setNtpFilter(e.target.value)}
                        style={{ ...inputStyle, padding: '6px 8px', minWidth: '180px' }}>
                        <option value="">All NTPs</option>
                        {ntpOptions.map(n => <option key={n} value={n}>{n}</option>)}
                    </select>
                </div>
                <div>
                    <label style={{ display: 'block', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '4px' }}>Contractor</label>
                    <select value={contractorFilter} onChange={e => setContractorFilter(e.target.value)}
                        style={{ ...inputStyle, padding: '6px 8px', minWidth: '200px' }}>
                        <option value="">All Contractors</option>
                        {contractorOptions.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                {(ntpFilter || contractorFilter) && (
                    <button type="button" onClick={() => { setNtpFilter(''); setContractorFilter(''); }}
                        style={{ padding: '7px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12px', fontWeight: 600, color: '#475569', cursor: 'pointer' }}>
                        Clear filters
                    </button>
                )}
                <div style={{ marginLeft: 'auto', fontSize: '12px', color: '#64748b', paddingBottom: '7px' }}>
                    Showing <strong>{filteredBillings.length}</strong> of {billings.length}
                </div>
            </div>

            <DataTable
                headers={['Seq#', 'Control#', 'NTP No.', 'NTP Date', 'Contractor (NTP)', 'Billing Type', 'Billed Amount', 'Progress %', 'Status', 'Actions']}
                rows={filteredBillings.map((b, idx) => [
                    <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                    <strong>{b.stmt_no}</strong>,
                    b.ntp_no
                        ? <span style={{ fontSize: '12px', fontWeight: 600, color: '#1e40af' }}>{b.ntp_no}</span>
                        : <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>,
                    <span style={{ fontSize: '12px', color: b.ntp_date ? '#475569' : '#cbd5e1' }}>{b.ntp_date ?? '—'}</span>,
                    b.ntp_contractor
                        ? <span style={{ fontSize: '12px', fontWeight: 600, color: '#1e40af' }}>{b.ntp_contractor}</span>
                        : <span style={{ color: '#cbd5e1', fontSize: '12px' }}>—</span>,
                    b.billing_type,
                    <strong>PhP {b.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong>,
                    b.progress_pct != null ? `${b.progress_pct}%` : '—',
                    canManageStatus ? (
                        <button type="button" title="Click to change status" onClick={() => setStatusTarget(b)}
                            style={{ padding: 0, border: 'none', background: 'transparent', cursor: 'pointer' }}>
                            <Badge tone={STATUS_TONE[b.status] ?? 'slate'}>{b.status} ✎</Badge>
                        </button>
                    ) : (
                        <Badge tone={STATUS_TONE[b.status] ?? 'slate'}>{b.status}</Badge>
                    ),
                    actionCell(b),
                ])}
            />
        </HubShell>
    );
}
