import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';
import ApprovalTimeline, { ApprovalStep } from '@/components/ApprovalTimeline';
import NtpReviewCard, { NtpReview, RejectModal } from '@/components/NtpReviewCard';

interface RequestRow {
    id: number;
    request_no: string;
    title: string;
    job_type: string;
    job_location: string;
    description: string;
    costcode: string | null;
    opex: boolean;
    capex: boolean;
    for_budgeting: boolean;
    status: string;
    created_at: string | null;
    attachments: number;
    requester: { name: string; department: string | null };
    approvals: ApprovalStep[];
    /** The final signature, where a project engineer may be assigned. */
    assigns_engineer: boolean;
    /** Already named ahead of the final signature, if anyone. */
    assigned_engineer_id: number | null;
}

interface SelectOption {
    value: string;
    label: string;
}

interface HistoryRow {
    id: number;
    type: 'NTP' | 'Request';
    label: string;
    status: string;
    remarks: string | null;
    /** The manager this decision was given for, when made as their OIC. */
    on_behalf_of: string | null;
    acted_at: string | null;
    /** Project id for an NTP, request id for a request; null when the record is gone. */
    link_id: number | null;
}

interface Props {
    role: string | null;
    role_label: string;
    requests: RequestRow[];
    ntps: NtpReview[];
    history: HistoryRow[];
    shows_requests: boolean;
    engineers: SelectOption[];
    /** Set while the signed-in user is covering a manager's roster break. */
    oic_for: { manager: string | null; role_label: string; ends_on: string; notes: string | null } | null;
    /** Set while the signed-in user is away on a roster break themselves. */
    on_break: { oic: string | null; ends_on: string } | null;
    /** The search the history was fetched with. */
    search: string;
}

// The queues come down whole, so they are filtered here; the history is
// searched on the server, which reaches past the latest decisions.
const matches = (query: string, fields: (string | null | undefined)[]) =>
    fields.some(f => f?.toLowerCase().includes(query));

const sectionLabel: React.CSSProperties = { fontSize: '10px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '3px' };

// ── Final approval, with an optional engineer assignment ─────────────────────
// The PMD Department Manager's signature completes the request, so this is
// where they may name who should run it. The project form then opens with
// that engineer as Project Manager; left blank, it defaults as before.
function AssignEngineerApproveModal({ row, engineers, onClose }: {
    row: RequestRow;
    engineers: SelectOption[];
    onClose: () => void;
}) {
    const [engineerId, setEngineerId] = useState(row.assigned_engineer_id ? String(row.assigned_engineer_id) : '');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.patch(route('approvals.requests.approve', row.id), { engineer_id: engineerId || null }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 100, padding: '20px' }}>
            <div style={{ background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '460px', overflow: 'hidden', boxShadow: '0 20px 50px rgba(0,0,0,0.25)' }}>
                <div style={{ padding: '16px 22px', borderBottom: '1px solid #f1f5f9', background: '#ecfdf5' }}>
                    <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 800, color: '#047857' }}>Approve Request {row.request_no}</h3>
                </div>
                <div style={{ padding: '22px' }}>
                    <p style={{ margin: '0 0 16px', fontSize: '13px', color: '#475569', lineHeight: 1.55 }}>
                        Yours is the final signature. The request will be fully approved and ready to register as a project.
                    </p>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                        Project Engineer (optional)
                    </label>
                    <select
                        value={engineerId}
                        onChange={e => setEngineerId(e.target.value)}
                        style={{ width: '100%', boxSizing: 'border-box', padding: '9px 12px', border: '1.5px solid #e2e8f0', borderRadius: '8px', fontSize: '13px', fontFamily: 'inherit', outline: 'none', background: '#fff' }}
                    >
                        <option value="">— Not assigned —</option>
                        {engineers.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                    </select>
                    <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '6px' }}>
                        Pre-selected as Project Manager when the project is registered.
                    </div>
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button type="button" onClick={onClose} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                    <button type="button" onClick={submit} disabled={saving} style={{ padding: '8px 22px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: saving ? 'not-allowed' : 'pointer' }}>
                        {saving ? 'Approving…' : 'Approve'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Request card ─────────────────────────────────────────────────────────────
function RequestCard({ row, onApprove, onReject }: {
    row: RequestRow;
    onApprove: (row: RequestRow) => void;
    onReject: (row: RequestRow) => void;
}) {
    const [open, setOpen] = useState(true);

    const funding = [
        row.opex ? 'OPEX' : null,
        row.capex ? 'CAPEX' : null,
        row.for_budgeting ? 'For Budgeting' : null,
    ].filter(Boolean).join(', ') || '—';

    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '18px 22px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
                <div
                    role="button"
                    tabIndex={0}
                    aria-expanded={open}
                    onClick={() => setOpen(o => !o)}
                    onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(o => !o); } }}
                    style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', flex: '1 1 260px', minWidth: 0, cursor: 'pointer', outline: 'none' }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2.5"
                        style={{ marginTop: '3px', flexShrink: 0, transform: open ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s' }}>
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                    <div style={{ minWidth: 0 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '4px', flexWrap: 'wrap' }}>
                            <span style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>{row.request_no}</span>
                            <span style={{ padding: '2px 10px', borderRadius: '999px', background: '#fef3c7', color: '#92400e', fontSize: '11px', fontWeight: 700 }}>Awaiting You</span>
                        </div>
                        <div style={{ fontSize: '13px', color: '#334155', fontWeight: 600 }}>{row.title}</div>
                        <div style={{ fontSize: '12.5px', color: '#64748b', marginTop: '2px' }}>
                            {row.requester.name}{row.requester.department ? ` · ${row.requester.department}` : ''}
                            {row.created_at ? ` · ${row.created_at}` : ''}
                        </div>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <button type="button" onClick={() => onApprove(row)} style={{ padding: '8px 18px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                        ✓ Approve
                    </button>
                    <button type="button" onClick={() => onReject(row)} style={{ padding: '8px 18px', borderRadius: '7px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                        Reject
                    </button>
                </div>
            </div>

            <div style={{ marginTop: '14px', paddingTop: '12px', borderTop: '1px solid #f1f5f9' }}>
                <div style={sectionLabel}>Approval Chain</div>
                <ApprovalTimeline steps={row.approvals} />
            </div>

            {open && (
                <>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '14px', marginTop: '16px', paddingTop: '14px', borderTop: '1px solid #f1f5f9' }}>
                        {[
                            ['Job Type', row.job_type],
                            ['Job Location', row.job_location],
                            ['Cost Code', row.costcode ?? '—'],
                            ['Funding', funding],
                            ['Attachments', String(row.attachments)],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <div style={sectionLabel}>{label}</div>
                                <div style={{ fontSize: '13px', fontWeight: 600, color: '#1e293b' }}>{value}</div>
                            </div>
                        ))}
                    </div>

                    <div style={{ marginTop: '14px' }}>
                        <div style={sectionLabel}>Description</div>
                        <div style={{ fontSize: '12.5px', color: '#475569', lineHeight: 1.5, whiteSpace: 'pre-wrap' }}>{row.description}</div>
                    </div>

                    <div style={{ marginTop: '14px' }}>
                        <Link href={route('requests.show', row.id)} style={{ fontSize: '12.5px', color: '#2563eb', fontWeight: 700, textDecoration: 'none' }}>
                            Open full request, attachments and technical feedback →
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}

// ── Portal ───────────────────────────────────────────────────────────────────
type TabKey = 'requests' | 'ntps' | 'history';

export default function ApprovalsIndex({ role, role_label, requests: allRequests, ntps: allNtps, history, shows_requests, engineers, oic_for, on_break, search: searchedFor }: Props) {
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();
    const [rejectRequest, setRejectRequest] = useState<RequestRow | null>(null);
    const [finalApprove, setFinalApprove] = useState<RequestRow | null>(null);
    const [rejectNtp, setRejectNtp] = useState<NtpReview | null>(null);
    const [tab, setTab] = useState<TabKey>(shows_requests && allRequests.length > 0 ? 'requests' : 'ntps');
    const [search, setSearch] = useState(searchedFor ?? '');

    const query = search.trim().toLowerCase();

    const requests = query === '' ? allRequests : allRequests.filter(r => matches(query, [
        r.request_no, r.title, r.job_type, r.job_location, r.costcode, r.requester.name, r.requester.department,
    ]));

    const ntps = query === '' ? allNtps : allNtps.filter(n => matches(query, [
        n.ntp_no, n.contractor, n.prepared_by, n.project.project_no, n.project.title,
        n.project.site, n.project.end_user, n.project.requester, n.project.cost_code,
    ]));

    // Refetch the history once typing pauses; the queues above have already
    // filtered as each key went in.
    useEffect(() => {
        const term = search.trim();

        if (term === (searchedFor ?? '')) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(route('approvals.index'), term ? { search: term } : {}, {
                only: ['history', 'search'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [search, searchedFor]);

    const tabs: { key: TabKey; label: string; count: number }[] = [
        ...(shows_requests ? [{ key: 'requests' as TabKey, label: 'Requests', count: requests.length }] : []),
        { key: 'ntps', label: 'NTPs', count: ntps.length },
        { key: 'history', label: 'My Decisions', count: history.length },
    ];

    const approveRequest = (row: RequestRow) => {
        if (row.assigns_engineer) {
            setFinalApprove(row);
            return;
        }

        showConfirm(
            `Approve request ${row.request_no}? It moves to the next approver in the chain.`,
            () => router.patch(route('approvals.requests.approve', row.id), {}, { preserveScroll: true }),
            { title: 'Approve Request', confirmLabel: 'Approve', variant: 'info' },
        );
    };

    const approveNtp = (ntp: NtpReview) => {
        showConfirm(
            `Approve NTP ${ntp.ntp_no} for ${ntp.contractor}? Once the last approver signs, the NTP is issued and its RFQ awarded.`,
            () => router.patch(route('approvals.ntps.approve', ntp.id), {}, { preserveScroll: true }),
            { title: 'Approve NTP', confirmLabel: 'Approve', variant: 'info' },
        );
    };

    const emptyState = (icon: string, title: string, detail: string) => (
        <div style={{ textAlign: 'center', padding: '56px 24px', border: '1px dashed #e2e8f0', borderRadius: '12px', color: '#94a3b8', background: '#fff' }}>
            <div style={{ fontSize: '30px', marginBottom: '10px' }}>{icon}</div>
            <div style={{ fontWeight: 700, color: '#64748b', marginBottom: '4px' }}>{title}</div>
            <div style={{ fontSize: '12.5px' }}>{detail}</div>
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="For Approval" />
            {confirmDialog}
            {finalApprove && (
                <AssignEngineerApproveModal row={finalApprove} engineers={engineers} onClose={() => setFinalApprove(null)} />
            )}
            {rejectRequest && (
                <RejectModal
                    heading={`Reject Request ${rejectRequest.request_no}`}
                    title="Reject Request"
                    routeName="approvals.requests.reject"
                    id={rejectRequest.id}
                    onClose={() => setRejectRequest(null)}
                />
            )}
            {rejectNtp && (
                <RejectModal
                    heading={`Reject NTP ${rejectNtp.ntp_no}`}
                    title="Reject NTP"
                    routeName="approvals.ntps.reject"
                    id={rejectNtp.id}
                    onClose={() => setRejectNtp(null)}
                />
            )}

            <div style={{ marginBottom: '16px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 4px' }}>For Approval</h1>
                <p style={{ fontSize: '13px', color: '#64748b', margin: 0 }}>
                    {role
                        ? <>Items waiting on the <strong style={{ color: '#334155' }}>{role_label}</strong>{oic_for && oic_for.role_label !== role_label ? <> and, as OIC, on the <strong style={{ color: '#334155' }}>{oic_for.role_label}</strong></> : null}. Approving passes each one to the next approver; rejecting ends its chain.</>
                        : <>You hold no approval role, so nothing is queued to you. Admins settle steps from the record's own screen.</>}
                </p>
            </div>

            {oic_for && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '10px', padding: '12px 16px', marginBottom: '16px' }}>
                    <span style={{ fontSize: '20px' }}>🛡️</span>
                    <div style={{ fontSize: '13px', color: '#1e3a8a' }}>
                        You are <strong>OIC for {oic_for.manager ?? `the ${oic_for.role_label}`}</strong> until <strong>{oic_for.ends_on}</strong>.
                        {oic_for.role_label === role_label
                            // A fellow Assistant Manager already shares this queue and signs as themselves.
                            ? <>You already share their {oic_for.role_label} queue below, so please keep it moving while they are away.</>
                            : <>Their {oic_for.role_label} approvals appear in your queue below, and anything you settle is recorded as signed on their behalf.</>}
                        {oic_for.notes && (
                            <div style={{ marginTop: '8px', padding: '8px 12px', borderRadius: '8px', background: '#fff', border: '1px solid #bfdbfe', whiteSpace: 'pre-wrap' }}>
                                <span style={{ fontSize: '10px', fontWeight: 800, color: '#60a5fa', textTransform: 'uppercase', letterSpacing: '0.5px', display: 'block', marginBottom: '2px' }}>Notes from {oic_for.manager ?? 'the manager'}</span>
                                {oic_for.notes}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {on_break && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap', background: '#ecfdf5', border: '1px solid #a7f3d0', borderRadius: '10px', padding: '12px 16px', marginBottom: '16px' }}>
                    <span style={{ fontSize: '20px' }}>🏖️</span>
                    <div style={{ fontSize: '13px', color: '#065f46' }}>
                        You are on roster break until <strong>{on_break.ends_on}</strong>. <strong>{on_break.oic ?? 'Your OIC'}</strong> is covering these approvals — you can still sign them yourself.
                    </div>
                </div>
            )}

            <div style={{ display: 'flex', gap: '10px', marginBottom: '18px', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    {tabs.map(t => {
                        const active = tab === t.key;
                        return (
                            <button key={t.key} type="button" onClick={() => setTab(t.key)}
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '7px', padding: '7px 14px', borderRadius: '8px', border: `1px solid ${active ? '#2563eb' : '#e2e8f0'}`, background: active ? '#2563eb' : '#fff', color: active ? '#fff' : '#475569', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                                {t.label}
                                <span style={{ padding: '1px 7px', borderRadius: '999px', background: active ? 'rgba(255,255,255,0.25)' : '#f1f5f9', color: active ? '#fff' : '#64748b', fontSize: '11px', fontWeight: 700 }}>{t.count}</span>
                            </button>
                        );
                    })}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#fff', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '7px 12px', flex: '1 1 220px', maxWidth: '340px' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search NTP, request, project, contractor…"
                        style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }}
                    />
                    {search && (
                        <button type="button" title="Clear search" onClick={() => setSearch('')} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', display: 'flex', padding: 0 }}>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    )}
                </div>
            </div>

            {tab === 'requests' && (
                requests.length === 0
                    ? (query
                        ? emptyState('🔍', 'No matching requests', `Nothing in your request queue matches “${search.trim()}”.`)
                        : emptyState('📥', 'Nothing awaiting your approval', 'Project requests endorsed by the project engineer will appear here.'))
                    : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                            {requests.map(row => (
                                <RequestCard key={row.id} row={row} onApprove={approveRequest} onReject={setRejectRequest} />
                            ))}
                        </div>
                    )
            )}

            {tab === 'ntps' && (
                ntps.length === 0
                    ? (query
                        ? emptyState('🔍', 'No matching NTPs', `Nothing in your NTP queue matches “${search.trim()}”.`)
                        : emptyState('📄', 'No NTPs awaiting your approval', 'Notices to Proceed reach you once the earlier approvers have signed.'))
                    : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                            {ntps.map(ntp => (
                                <NtpReviewCard
                                    key={ntp.id}
                                    ntp={ntp}
                                    canAct
                                    approveLabel="Approve"
                                    onApprove={approveNtp}
                                    onReject={setRejectNtp}
                                />
                            ))}
                        </div>
                    )
            )}

            {tab === 'history' && (
                history.length === 0
                    ? (searchedFor
                        ? emptyState('🔍', 'No matching decisions', `None of your past decisions match “${searchedFor}”.`)
                        : emptyState('🗂️', 'No decisions yet', 'Everything you approve or reject is recorded here.'))
                    : (
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px', minWidth: '620px' }}>
                                <thead>
                                    <tr style={{ background: '#f8fafc' }}>
                                        {['Type', 'Record', 'Decision', 'Remarks', 'Date'].map(h => (
                                            <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map(row => (
                                        <tr key={row.id} style={{ borderTop: '1px solid #f1f5f9' }}>
                                            <td style={{ padding: '11px 14px', color: '#64748b' }}>{row.type}</td>
                                            <td style={{ padding: '11px 14px', color: '#1e293b', fontWeight: 600 }}>
                                                {row.link_id
                                                    ? <Link href={route(row.type === 'NTP' ? 'projects.hub.ntp' : 'requests.show', row.link_id)} style={{ color: '#2563eb', textDecoration: 'none' }}>{row.label}</Link>
                                                    : row.label}
                                            </td>
                                            <td style={{ padding: '11px 14px' }}>
                                                <span style={{
                                                    padding: '2px 10px', borderRadius: '999px', fontSize: '11px', fontWeight: 700,
                                                    background: row.status === 'approved' ? '#dcfce7' : '#fee2e2',
                                                    color: row.status === 'approved' ? '#166534' : '#b91c1c',
                                                }}>
                                                    {row.status === 'approved' ? 'Approved' : 'Rejected'}
                                                </span>
                                                {row.on_behalf_of && (
                                                    <span style={{ display: 'block', fontSize: '10.5px', color: '#64748b', marginTop: '4px' }}>as OIC for {row.on_behalf_of}</span>
                                                )}
                                            </td>
                                            <td style={{ padding: '11px 14px', color: '#64748b' }}>{row.remarks || '—'}</td>
                                            <td style={{ padding: '11px 14px', color: '#64748b', whiteSpace: 'nowrap' }}>{row.acted_at ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )
            )}
        </AuthenticatedLayout>
    );
}
