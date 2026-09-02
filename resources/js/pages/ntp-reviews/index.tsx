import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';
import NtpReviewCard, { NtpReview, NtpStatus, RejectModal } from '@/components/NtpReviewCard';

interface Props {
    ntps: NtpReview[];
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
            `Approve NTP ${ntp.ntp_no} for ${ntp.contractor}? It then goes to PMD for the remaining approvals before it can be issued.`,
            () => router.patch(route('ntp-reviews.approve', ntp.id), {}, { preserveScroll: true }),
            { title: 'Approve NTP', confirmLabel: 'Approve', variant: 'info' },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="NTP Reviews" />
            {confirmDialog}
            {rejectNtp && (
                <RejectModal
                    heading={`Reject NTP ${rejectNtp.ntp_no}`}
                    title="Reject NTP"
                    routeName="ntp-reviews.reject"
                    id={rejectNtp.id}
                    onClose={() => setRejectNtp(null)}
                />
            )}

            <div style={{ marginBottom: '16px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 4px' }}>NTP Reviews</h1>
                <p style={{ fontSize: '13px', color: '#64748b', margin: 0 }}>
                    Notices to Proceed and their review history. Yours is the first signature — after it, the NTP goes to the
                    PMD Assistant Manager, PMD Department Manager and Division Manager before it is issued.
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
                        <NtpReviewCard
                            key={ntp.id}
                            ntp={ntp}
                            canAct={ntp.can_act ?? false}
                            approveLabel="Approve"
                            onApprove={approve}
                            onReject={setRejectNtp}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
