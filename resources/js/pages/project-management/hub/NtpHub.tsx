import { router } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable, HubProject, HubShell, InfoStrip, SectionTitle, SubTag } from './Common';
import { useConfirm } from '@/components/useConfirm';
import { SendConfirmModal } from './SendConfirmModal';

interface ScopeItem {
    seq: number;
    description: string;
    qty: string | null;
    unit: string | null;
    unit_cost: number | null;
    total_cost: number | null;
}

interface ApprovalStep {
    sequence: number;
    role: string;
    role_label: string;
    status: 'pending' | 'approved' | 'rejected';
    is_current: boolean;
    actor: string | null;
    acted_at: string | null;
    remarks: string | null;
}

const peso = (n: number) => `Php ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

interface NtpData {
    id: number;
    ntp_no: string;
    contractor: string;
    baseline_start: string;
    baseline_end: string;
    approved_cost: number;
    status: string;
    issued_date: string;
    reviewed_by: string | null;
    review_remarks: string | null;
    // The sub-project spawned FROM this issued NTP (own rows only).
    spawned_sub_id: number | null;
    spawned_sub_no: string | null;
    // The sub-project this NTP itself belongs to (roll-up tag; null = own).
    sub_project_id: number | null;
    sub_project_no: string | null;
    duration_days: number | null;
    scope_items: ScopeItem[];
    prepared_by: string | null;
    /** The sign-off chain, in signing order — stamped onto the printout. */
    approvals: ApprovalStep[];
    /** Contractor address carried over from the source RFQ. */
    vendor_email: string | null;
    vendor_notified_at: string | null;
}

const NTP_STATUS_META: Record<string, { label: string; bg: string; border: string; color: string }> = {
    pending_review: { label: '⏳ Pending Review', bg: '#fef3c7', border: '#fde68a', color: '#92400e' },
    issued:         { label: '✓ Issued',          bg: '#f0fdf4', border: '#bbf7d0', color: '#15803d' },
    rejected:       { label: '✕ Rejected',        bg: '#fef2f2', border: '#fecaca', color: '#dc2626' },
};

function NtpStatusBadge({ status }: { status: string }) {
    const m = NTP_STATUS_META[status] ?? { label: status, bg: '#f1f5f9', border: '#e2e8f0', color: '#475569' };
    return (
        <span style={{ padding: '3px 10px', borderRadius: '999px', background: m.bg, border: `1px solid ${m.border}`, color: m.color, fontSize: '11px', fontWeight: 700, whiteSpace: 'nowrap' }}>{m.label}</span>
    );
}

export default function NtpHub({ project, ntps, canEdit = true }: { project: HubProject; ntps: NtpData[]; canEdit?: boolean }) {
    const [selected, setSelected] = useState<NtpData | null>(null);
    const [sendNtp, setSendNtp] = useState<NtpData | null>(null);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();
    const hasSubRows = ntps.some(n => !!n.sub_project_id);

    // Only rejected NTPs may be deleted — issued/pending ones are protected.
    const handleDelete = (ntp: NtpData) => {
        showConfirm(`Delete rejected NTP ${ntp.ntp_no}? This cannot be undone.`, () => {
            router.delete(route('hub.ntp.destroy', [project.id, ntp.id]), { preserveScroll: true });
        }, { title: 'Delete NTP', confirmLabel: 'Delete', variant: 'danger' });
    };

    const handleSendToVendor = (recipientEmail: string, additionalRecipients: string[], ccSelf: boolean) => {
        const ntp = sendNtp;
        setSendNtp(null);
        if (!ntp) return;
        router.post(route('hub.ntp.send', [project.id, ntp.id]), {
            recipient_email: recipientEmail,
            additional_recipients: additionalRecipients,
            cc_self: ccSelf,
        }, { preserveScroll: true });
    };

    /**
     * Notice to Proceed — PMD-PRJ-FRM-04, rendered to PDF server-side and
     * previewed in a new tab. The approval stamps on the form come from the
     * chain itself, which is why the server builds it rather than the browser.
     */
    const handlePrint = () => {
        if (!selected) return;
        window.open(route('print.ntp', [selected.sub_project_id ?? project.id, selected.id]), '_blank');
    };


    // ── List view ─────────────────────────────────────────────────────────────
    if (!selected) {
        return (
            <HubShell>
                {confirmDialog}
                <div style={{ borderBottom: '2px solid #059669', paddingBottom: '14px', marginBottom: '24px' }}>
                    <h3 style={{ color: '#059669', margin: '0 0 4px' }}>NOTICE TO PROCEED</h3>
                    <span style={{ color: '#64748b', fontSize: '12px' }}>Official Authorization for Project Commencement</span>
                </div>

                {ntps.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '60px 24px', color: '#94a3b8' }}>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.5" style={{ marginBottom: '12px' }}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <div style={{ fontWeight: 700, fontSize: '15px', color: '#64748b', marginBottom: '6px' }}>No NTP Issued Yet</div>
                        <div style={{ fontSize: '13px' }}>Issue an NTP from the RFQ section after awarding a contractor.</div>
                    </div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['NTP No.', ...(hasSubRows ? ['Project'] : []), 'Contractor', 'Baseline Start', 'Baseline End', 'Approved Cost', 'Status', 'Issued Date', ''].map((h, hi) => (
                                    <th key={`${h}-${hi}`} style={{ padding: '9px 14px', fontSize: '11px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e5e7eb', textAlign: 'left' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {ntps.map((ntp, i) => (
                                <tr key={ntp.id} style={{ background: i % 2 === 0 ? '#fff' : '#f8fafc' }}>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', fontWeight: 700, color: '#059669', borderBottom: '1px solid #f1f5f9' }}>{ntp.ntp_no}</td>
                                    {hasSubRows && <td style={{ padding: '10px 14px', borderBottom: '1px solid #f1f5f9' }}><SubTag no={ntp.sub_project_no} /></td>}
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#0f172a', borderBottom: '1px solid #f1f5f9' }}>{ntp.contractor}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.baseline_start}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.baseline_end}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#0f172a', fontWeight: 600, borderBottom: '1px solid #f1f5f9' }}>
                                        Php {ntp.approved_cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </td>
                                    <td style={{ padding: '10px 14px', borderBottom: '1px solid #f1f5f9' }}>
                                        <NtpStatusBadge status={ntp.status} />
                                        {ntp.status === 'rejected' && ntp.review_remarks && (
                                            <div title={ntp.review_remarks} style={{ fontSize: '11px', color: '#94a3b8', marginTop: '3px', maxWidth: '180px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{ntp.review_remarks}</div>
                                        )}
                                    </td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.status === 'issued' ? ntp.issued_date : '—'}</td>
                                    <td style={{ padding: '10px 14px', borderBottom: '1px solid #f1f5f9' }}>
                                        <div style={{ display: 'flex', gap: '6px' }}>
                                            <button
                                                type="button"
                                                onClick={() => setSelected(ntp)}
                                                style={{ padding: '5px 12px', borderRadius: '6px', border: '1px solid #bbf7d0', background: '#f0fdf4', color: '#166534', cursor: 'pointer', fontSize: '12px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                                            >
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                View
                                            </button>
                                            {ntp.sub_project_id ? (
                                                /* This NTP belongs to a sub-project — read-only; jump to that sub-project's NTP hub. */
                                                <button
                                                    type="button"
                                                    title={`Open ${ntp.sub_project_no ?? 'sub-project'}`}
                                                    onClick={() => router.visit(route('projects.hub.ntp', ntp.sub_project_id!))}
                                                    style={{ padding: '5px 12px', borderRadius: '6px', border: '1px solid #c7d2fe', background: '#eef2ff', color: '#4338ca', cursor: 'pointer', fontSize: '12px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M7 17L17 7M7 7h10v10"/></svg>
                                                    Open Sub-Project
                                                </button>
                                            ) : (
                                                <>
                                                    {/* Sub-projects are raised in the Sub-Projects section now; this
                                                        only links to one spawned from this NTP before that change. */}
                                                    {ntp.spawned_sub_id && (
                                                        <button
                                                            type="button"
                                                            title={`Open sub-project ${ntp.spawned_sub_no ?? ''}`}
                                                            onClick={() => router.visit(route('projects.show', ntp.spawned_sub_id!))}
                                                            style={{ padding: '5px 12px', borderRadius: '6px', border: '1px solid #c7d2fe', background: '#eef2ff', color: '#4338ca', cursor: 'pointer', fontSize: '12px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                                                        >
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 3 3 5-5"/></svg>
                                                            View Sub-Project
                                                        </button>
                                                    )}
                                                    {canEdit && ntp.status === 'rejected' && (
                                                        <button
                                                            type="button"
                                                            title="Delete rejected NTP"
                                                            onClick={() => handleDelete(ntp)}
                                                            style={{ padding: '5px 10px', borderRadius: '6px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', cursor: 'pointer', fontSize: '12px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                                                        >
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                                            Delete
                                                        </button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </HubShell>
        );
    }

    // ── Detail / print view ───────────────────────────────────────────────────
    return (
        <HubShell>
            {confirmDialog}
            {sendNtp && (
                <SendConfirmModal
                    contractor={sendNtp.contractor}
                    dueDate={sendNtp.baseline_start}
                    dueLabel="Baseline Start"
                    email={sendNtp.vendor_email ?? ''}
                    title={`Send NTP ${sendNtp.ntp_no} to Vendor`}
                    sendLabel="Send NTP"
                    note={`${sendNtp.contractor} will be told their Notice to Proceed is approved and issued, with the baseline dates and approved cost.`}
                    onClose={() => setSendNtp(null)}
                    onSend={handleSendToVendor}
                />
            )}
            <div style={{ borderBottom: '2px solid #059669', paddingBottom: '14px', marginBottom: '24px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <button
                        type="button"
                        onClick={() => setSelected(null)}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', fontSize: '12px', color: '#64748b', background: 'none', border: 'none', cursor: 'pointer', padding: '0 0 8px', fontWeight: 500 }}
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to list
                    </button>
                    <h3 style={{ color: '#059669', margin: '0 0 4px' }}>NOTICE TO PROCEED</h3>
                    <span style={{ color: '#64748b', fontSize: '12px' }}>Official Authorization for Project Commencement</span>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontSize: '16px', fontWeight: 800, color: '#0f172a' }}>NTP#: {selected.ntp_no}</div>
                    <div style={{ marginTop: '6px', display: 'flex', gap: '6px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
                        <button
                            type="button"
                            onClick={handlePrint}
                            style={{ padding: '5px 12px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', cursor: 'pointer', fontSize: '12px', display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print Document
                        </button>
                        {canEdit && selected.status === 'issued' && !selected.sub_project_id && (
                            <button
                                type="button"
                                onClick={() => setSendNtp(selected)}
                                title={selected.vendor_notified_at ? `Already sent ${selected.vendor_notified_at} — sending again re-notifies the contractor` : 'Email this NTP to the contractor'}
                                style={{ padding: '5px 12px', borderRadius: '6px', border: 'none', background: '#059669', color: '#fff', cursor: 'pointer', fontSize: '12px', fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                {selected.vendor_notified_at ? 'Re-send to Vendor' : 'Send to Vendor'}
                            </button>
                        )}
                    </div>
                    {selected.vendor_notified_at && (
                        <div style={{ marginTop: '5px', fontSize: '11px', color: '#15803d', fontWeight: 600 }}>
                            ✓ Sent to contractor {selected.vendor_notified_at}
                        </div>
                    )}
                </div>
            </div>

            <div>
                {/* Summary strip */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '16px', marginBottom: '22px' }}>
                    {([
                        ['Contractor', selected.contractor],
                        ['Baseline Start', selected.baseline_start],
                        ['Baseline End', selected.baseline_end],
                        ['Approved Cost', `Php ${selected.approved_cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}`],
                    ] as [string, string][]).map(([label, value]) => (
                        <div key={label}>
                            <div style={{ fontSize: '10px', fontWeight: 800, color: '#166534', textTransform: 'uppercase', marginBottom: '3px' }}>{label}</div>
                            <div style={{ fontSize: '13px', fontWeight: 700, color: '#14532d' }}>{value}</div>
                        </div>
                    ))}
                </div>

                <SectionTitle color="#059669">I. Project Information</SectionTitle>
                <InfoStrip project={project} accent="#059669" />

                <SectionTitle color="#059669">II. Scope of Work</SectionTitle>
                {selected.scope_items.length > 0 ? (
                    <DataTable
                        headers={['Seq No.', 'Scope of Work / Description', 'Quantity', 'Unit', 'Unit Cost', 'Total Cost']}
                        rows={[
                            ...selected.scope_items.map(item => [
                                <span style={{ fontWeight: 600 }}>{item.seq}</span>,
                                item.description,
                                item.qty ?? '—',
                                item.unit ?? '—',
                                item.unit_cost != null ? peso(item.unit_cost) : '—',
                                item.total_cost != null ? peso(item.total_cost) : '—',
                            ]),
                            [
                                '',
                                <strong>Total Cost</strong>,
                                '',
                                '',
                                '',
                                <strong style={{ color: '#059669' }}>
                                    {peso(selected.scope_items.reduce((s, i) => s + Number(i.total_cost ?? 0), 0))}
                                </strong>,
                            ],
                        ]}
                    />
                ) : (
                    <div style={{ padding: '16px', background: '#f8fafc', border: '1px solid #e5e7eb', borderRadius: '8px', fontSize: '13px', color: '#94a3b8', marginBottom: '16px' }}>
                        No scope items linked. Add items to the associated RFQ.
                    </div>
                )}

                <SectionTitle color="#059669">III. Terms and Conditions</SectionTitle>
                <DataTable
                    headers={['Provision Category', 'Details & Obligations']}
                    rows={[
                        [<strong>1. Project Commencement</strong>, 'The contractor is formally authorized to mobilize and begin work on the specified Baseline Start Date.'],
                        [<strong>2. Compliance & Safety</strong>, 'All work must adhere to the site-specific safety protocols and ISO 45001 standards. Personnel must wear appropriate PPE at all times.'],
                        [<strong>3. Payment Milestone</strong>, 'Payments shall be released based on the approved progress billing cycle as stipulated in the primary contract.'],
                        [<strong>4. Delays & Penalties</strong>, 'Any unexcused delay beyond the Baseline End Date may subject the contractor to liquidated damages of 0.1% per day.'],
                    ]}
                />

                {/* Signature block */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '48px', marginTop: '64px' }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', marginBottom: '2px', minHeight: '18px' }}>
                            {project.project_manager || ' '}
                        </div>
                        <div style={{ borderTop: '1px solid #334155', paddingTop: '8px', fontSize: '12px', fontWeight: 700, color: '#334155' }}>
                            Prepared By: Project Manager
                        </div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', marginBottom: '2px', minHeight: '18px' }}>
                            {project.signatories?.operations_director || ' '}
                        </div>
                        <div style={{ borderTop: '1px solid #334155', paddingTop: '8px', fontSize: '12px', fontWeight: 700, color: '#334155' }}>
                            Approved By: Operations Director
                        </div>
                    </div>
                </div>
            </div>
        </HubShell>
    );
}
