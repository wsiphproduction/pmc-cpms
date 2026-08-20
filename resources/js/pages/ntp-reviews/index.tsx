import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';

interface QuotationItem {
    seq: number;
    description: string | null;
    qty: number | null;
    unit: string | null;
    unit_cost: number | null;
    total_cost: number | null;
}

interface RfqDetail {
    due_date: string | null;
    sent_date: string | null;
    duration_days: number | null;
    terms: string | null;
    inclusions: string | null;
    exclusions: string | null;
    quotation_file: string | null;
    grand_total: number;
    items: QuotationItem[];
}

type NtpStatus = 'pending_review' | 'issued' | 'rejected';

interface NtpReview {
    id: number;
    ntp_no: string;
    contractor: string;
    status: NtpStatus;
    baseline_start: string | null;
    baseline_end: string | null;
    approved_cost: number;
    submitted_at: string | null;
    prepared_by: string;
    issued_date: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_remarks: string | null;
    scope_of_work: string | null;
    project: { id: number; project_no: string; title: string };
    rfq: RfqDetail | null;
}

const peso = (n: number) => `PhP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// Status → display label + badge colors. "pending_review" is shown as "For Review".
const STATUS_META: Record<NtpStatus, { label: string; bg: string; color: string }> = {
    pending_review: { label: 'For Review', bg: '#fef3c7', color: '#92400e' },
    issued:         { label: 'Issued',     bg: '#dcfce7', color: '#166534' },
    rejected:       { label: 'Rejected',   bg: '#fee2e2', color: '#b91c1c' },
};

const sectionLabel: React.CSSProperties = { fontSize: '10px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '3px' };
const thStyle: React.CSSProperties = { padding: '8px 10px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', background: '#f1f5f9' };
const tdStyle: React.CSSProperties = { padding: '7px 10px', color: '#334155', verticalAlign: 'top' };

interface Props {
    ntps: NtpReview[];
}

// ── Reject modal ─────────────────────────────────────────────────────────────
function RejectModal({ ntp, onClose }: { ntp: NtpReview; onClose: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.patch(route('ntp-reviews.reject', ntp.id), { remarks }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 100, padding: '20px' }}>
            <div style={{ background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '460px', overflow: 'hidden', boxShadow: '0 20px 50px rgba(0,0,0,0.25)' }}>
                <div style={{ padding: '16px 22px', borderBottom: '1px solid #f1f5f9', background: '#fef2f2' }}>
                    <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 800, color: '#b91c1c' }}>Reject NTP {ntp.ntp_no}</h3>
                </div>
                <div style={{ padding: '22px' }}>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                        Reason / Remarks (optional)
                    </label>
                    <textarea
                        rows={4}
                        value={remarks}
                        onChange={e => setRemarks(e.target.value)}
                        placeholder="Explain why this NTP is being sent back…"
                        style={{ width: '100%', boxSizing: 'border-box', padding: '10px 12px', border: '1.5px solid #e2e8f0', borderRadius: '8px', fontSize: '13px', fontFamily: 'inherit', resize: 'vertical', outline: 'none' }}
                    />
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button type="button" onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                    <button type="button" onClick={submit} disabled={saving} style={{ padding: '8px 22px', borderRadius: '7px', border: 'none', background: '#dc2626', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: saving ? 'not-allowed' : 'pointer' }}>
                        {saving ? 'Rejecting…' : 'Reject NTP'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── NTP card ─────────────────────────────────────────────────────────────────
// An NTP that has been reviewed either way is settled business, and its
// quotation table is long, so issued and rejected cards start collapsed. Only
// the records still awaiting a decision open on their own; every card folds
// either way once on screen.
function NtpCard({ ntp, onApprove, onReject }: {
    ntp: NtpReview;
    onApprove: (ntp: NtpReview) => void;
    onReject: (ntp: NtpReview) => void;
}) {
    const pending = ntp.status === 'pending_review';
    const [open, setOpen] = useState(pending);

    // One-line stand-in for the detail grid while the card is folded.
    const summary = [
        ntp.contractor,
        peso(ntp.approved_cost),
        ntp.status === 'issued'
            ? (ntp.issued_date ? `Issued ${ntp.issued_date}` : 'Issued')
            : ntp.status === 'rejected'
                ? (ntp.reviewed_at ? `Rejected ${ntp.reviewed_at}` : 'Rejected')
                : (ntp.submitted_at ? `Submitted ${ntp.submitted_at}` : null),
    ].filter(Boolean).join('  ·  ');

    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '18px 22px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
                {/* Only the heading toggles, so the approve/reject buttons keep
                    their own click target instead of bubbling into the fold. */}
                <div
                    role="button"
                    tabIndex={0}
                    aria-expanded={open}
                    title={open ? 'Hide details' : 'Show details'}
                    onClick={() => setOpen(o => !o)}
                    onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(o => !o); } }}
                    style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', flex: '1 1 260px', minWidth: 0, cursor: 'pointer', outline: 'none' }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2.5"
                        style={{ marginTop: '3px', flexShrink: 0, transform: open ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s' }}>
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                    <div style={{ minWidth: 0 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '4px', flexWrap: 'wrap' }}>
                            <span style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{ntp.ntp_no}</span>
                            <span style={{ padding: '2px 10px', borderRadius: '999px', background: STATUS_META[ntp.status].bg, color: STATUS_META[ntp.status].color, fontSize: '11px', fontWeight: 700 }}>{STATUS_META[ntp.status].label}</span>
                        </div>
                        <div style={{ fontSize: '12.5px', color: '#64748b' }}>
                            Project <strong style={{ color: '#334155' }}>{ntp.project.project_no}</strong> — {ntp.project.title}
                        </div>
                        {!open && (
                            <div style={{ fontSize: '12px', color: '#94a3b8', marginTop: '5px' }}>{summary}</div>
                        )}
                    </div>
                </div>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    {pending ? (
                        <>
                            <button type="button" onClick={() => onApprove(ntp)} style={{ padding: '8px 18px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                                ✓ Approve &amp; Issue
                            </button>
                            <button type="button" onClick={() => onReject(ntp)} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                                Reject
                            </button>
                        </>
                    ) : (
                        <button type="button" onClick={() => setOpen(o => !o)} style={{ padding: '6px 12px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', color: '#475569', fontSize: '12px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                            {open ? 'Hide details' : 'Show details'}
                        </button>
                    )}
                </div>
            </div>

            {open && (
                <>
                    {/* Review outcome for records that have been acted on */}
                    {ntp.status !== 'pending_review' && (
                        <div style={{ marginTop: '12px', padding: '10px 14px', borderRadius: '8px', background: ntp.status === 'issued' ? '#f0fdf4' : '#fef2f2', border: `1px solid ${ntp.status === 'issued' ? '#bbf7d0' : '#fecaca'}`, fontSize: '12.5px', color: '#475569' }}>
                            {ntp.status === 'issued'
                                ? <span><strong style={{ color: '#166534' }}>Issued</strong>{ntp.issued_date ? ` on ${ntp.issued_date}` : ''}{ntp.reviewed_by ? ` · reviewed by ${ntp.reviewed_by}` : ''}{ntp.reviewed_at ? ` (${ntp.reviewed_at})` : ''}</span>
                                : <span><strong style={{ color: '#b91c1c' }}>Rejected</strong>{ntp.reviewed_by ? ` by ${ntp.reviewed_by}` : ''}{ntp.reviewed_at ? ` (${ntp.reviewed_at})` : ''}{ntp.review_remarks ? ` — ${ntp.review_remarks}` : ''}</span>}
                        </div>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '14px', marginTop: '16px', paddingTop: '14px', borderTop: '1px solid #f1f5f9' }}>
                        {[
                            ['Contractor', ntp.contractor],
                            ['Approved Cost', `PhP ${ntp.approved_cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}`],
                            ['Baseline Start', ntp.baseline_start ?? '—'],
                            ['Baseline End', ntp.baseline_end ?? '—'],
                            ['Prepared By', ntp.prepared_by],
                            ['Submitted', ntp.submitted_at ?? '—'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <div style={{ fontSize: '10px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '3px' }}>{label}</div>
                                <div style={{ fontSize: '13px', fontWeight: 600, color: '#1e293b' }}>{value}</div>
                            </div>
                        ))}
                    </div>

                    {ntp.scope_of_work && (
                        <div style={{ marginTop: '14px' }}>
                            <div style={sectionLabel}>Scope of Work</div>
                            <div style={{ fontSize: '12.5px', color: '#475569', lineHeight: 1.5, whiteSpace: 'pre-wrap' }}>{ntp.scope_of_work}</div>
                        </div>
                    )}

                    {ntp.rfq && (
                        <>
                            {/* Itemized quotation */}
                            <div style={{ marginTop: '18px' }}>
                                <div style={sectionLabel}>Itemized Quotation</div>
                                {ntp.rfq.items.length > 0 ? (
                                    <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', overflowX: 'auto' }}>
                                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px', minWidth: '520px' }}>
                                            <thead>
                                                <tr>
                                                    {['Seq', 'Description', 'Qty', 'Unit', 'Unit Cost', 'Total Cost'].map(h => (
                                                        <th key={h} style={thStyle}>{h}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {ntp.rfq.items.map((it, i) => (
                                                    <tr key={i} style={{ borderTop: '1px solid #f1f5f9' }}>
                                                        <td style={{ ...tdStyle, textAlign: 'center', color: '#94a3b8' }}>{it.seq}</td>
                                                        <td style={tdStyle}>{it.description ?? '—'}</td>
                                                        <td style={{ ...tdStyle, textAlign: 'right' }}>{it.qty ?? '—'}</td>
                                                        <td style={tdStyle}>{it.unit ?? '—'}</td>
                                                        <td style={{ ...tdStyle, textAlign: 'right' }}>{it.unit_cost != null ? peso(it.unit_cost) : '—'}</td>
                                                        <td style={{ ...tdStyle, textAlign: 'right', fontWeight: 700, color: '#2563eb' }}>{it.total_cost != null ? peso(it.total_cost) : '—'}</td>
                                                    </tr>
                                                ))}
                                                <tr style={{ background: '#fefce8', borderTop: '1px solid #e5e7eb' }}>
                                                    <td colSpan={5} style={{ ...tdStyle, textAlign: 'right', fontWeight: 800 }}>Grand Total</td>
                                                    <td style={{ ...tdStyle, textAlign: 'right', fontWeight: 900, color: '#2563eb' }}>{peso(ntp.rfq.grand_total)}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div style={{ fontSize: '12.5px', color: '#94a3b8' }}>No itemized quotation recorded.</div>
                                )}
                            </div>

                            {/* Duration + dates + file */}
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '14px', marginTop: '16px' }}>
                                {[
                                    ['Target Duration', ntp.rfq.duration_days != null ? `${ntp.rfq.duration_days} calendar day${ntp.rfq.duration_days === 1 ? '' : 's'}` : '—'],
                                    ['RFQ Sent', ntp.rfq.sent_date ?? '—'],
                                    ['RFQ Due', ntp.rfq.due_date ?? '—'],
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <div style={sectionLabel}>{label}</div>
                                        <div style={{ fontSize: '13px', fontWeight: 600, color: '#1e293b' }}>{value}</div>
                                    </div>
                                ))}
                                {ntp.rfq.quotation_file && (
                                    <div>
                                        <div style={sectionLabel}>Quotation File</div>
                                        <a href={ntp.rfq.quotation_file} target="_blank" rel="noreferrer" style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', fontSize: '12.5px', color: '#2563eb', fontWeight: 700, textDecoration: 'none' }}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            View File
                                        </a>
                                    </div>
                                )}
                            </div>

                            {/* Terms / inclusions / exclusions */}
                            {(ntp.rfq.terms || ntp.rfq.inclusions || ntp.rfq.exclusions) && (
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px', marginTop: '16px' }}>
                                    {([['Terms & Conditions', ntp.rfq.terms], ['Inclusions', ntp.rfq.inclusions], ['Exclusions', ntp.rfq.exclusions]] as [string, string | null][]).map(([label, value]) => (
                                        <div key={label}>
                                            <div style={sectionLabel}>{label}</div>
                                            <div style={{ fontSize: '12.5px', color: '#475569', lineHeight: 1.5, whiteSpace: 'pre-wrap' }}>{value || '—'}</div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </>
            )}
        </div>
    );
}

type TabKey = 'all' | NtpStatus;

export default function NtpReviewsIndex({ ntps }: Props) {
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();
    const [rejectNtp, setRejectNtp] = useState<NtpReview | null>(null);
    const [tab, setTab] = useState<TabKey>('all');

    const counts = {
        all: ntps.length,
        pending_review: ntps.filter(n => n.status === 'pending_review').length,
        issued: ntps.filter(n => n.status === 'issued').length,
        rejected: ntps.filter(n => n.status === 'rejected').length,
    };
    const tabs: { key: TabKey; label: string }[] = [
        { key: 'all', label: 'All' },
        { key: 'pending_review', label: 'For Review' },
        { key: 'issued', label: 'Issued' },
        { key: 'rejected', label: 'Rejected' },
    ];
    const filtered = tab === 'all' ? ntps : ntps.filter(n => n.status === tab);

    // Records still awaiting a decision come first, whatever tab is showing. The
    // server orders them that way too; this makes it hold no matter what arrives.
    // Sort is stable, so the server's recency order survives within each group.
    const ordered = [...filtered].sort(
        (a, b) => Number(b.status === 'pending_review') - Number(a.status === 'pending_review'),
    );

    const approve = (ntp: NtpReview) => {
        showConfirm(
            `Approve and issue NTP ${ntp.ntp_no} for ${ntp.contractor}? This commits the approved cost to the project budget.`,
            () => router.patch(route('ntp-reviews.approve', ntp.id), {}, { preserveScroll: true }),
            { title: 'Approve NTP', confirmLabel: 'Approve & Issue', variant: 'info' },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="NTP Reviews" />
            {confirmDialog}
            {rejectNtp && <RejectModal ntp={rejectNtp} onClose={() => setRejectNtp(null)} />}

            <div style={{ marginBottom: '16px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 4px' }}>NTP Reviews</h1>
                <p style={{ fontSize: '13px', color: '#64748b', margin: 0 }}>
                    Notices to Proceed and their review history. Approving one for review issues it and commits its cost to the project budget.
                </p>
            </div>

            {/* Status tabs */}
            <div style={{ display: 'flex', gap: '6px', marginBottom: '18px', flexWrap: 'wrap' }}>
                {tabs.map(t => {
                    const active = tab === t.key;
                    const count = counts[t.key];
                    return (
                        <button key={t.key} type="button" onClick={() => setTab(t.key)}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '7px', padding: '7px 14px', borderRadius: '8px', border: `1px solid ${active ? '#2563eb' : '#e2e8f0'}`, background: active ? '#2563eb' : '#fff', color: active ? '#fff' : '#475569', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                            {t.label}
                            <span style={{ padding: '1px 7px', borderRadius: '999px', background: active ? 'rgba(255,255,255,0.25)' : '#f1f5f9', color: active ? '#fff' : '#64748b', fontSize: '11px', fontWeight: 700 }}>{count}</span>
                        </button>
                    );
                })}
            </div>

            {filtered.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '56px 24px', border: '1px dashed #e2e8f0', borderRadius: '12px', color: '#94a3b8', background: '#fff' }}>
                    <div style={{ fontSize: '30px', marginBottom: '10px' }}>✅</div>
                    <div style={{ fontWeight: 700, color: '#64748b', marginBottom: '4px' }}>No NTP records{tab !== 'all' ? ` in "${tabs.find(t => t.key === tab)?.label}"` : ''}</div>
                    <div style={{ fontSize: '12.5px' }}>New Notices to Proceed will appear here when an engineer submits one.</div>
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                    {ordered.map(ntp => (
                        <NtpCard key={ntp.id} ntp={ntp} onApprove={approve} onReject={setRejectNtp} />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
