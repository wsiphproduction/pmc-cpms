import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface Attachment {
    id: number;
    filename: string;
    filepath: string;
    description: string | null;
    url: string;
}

interface User {
    id: number;
    name: string;
}

interface ProjectRequestData {
    id: number;
    title: string;
    job_type: string;
    description: string;
    job_location: string;
    costcode: string | null;
    opex: boolean;
    capex: boolean;
    for_budgeting: boolean;
    status: 'pending' | 'approved' | 'ongoing' | 'rejected' | 'completed';
    requester: User | null;
    attachments: Attachment[];
    created_at: string | null;
}

interface Props {
    projectRequest: ProjectRequestData;
}

// ── Helpers ────────────────────────────────────────────────────────────────
const formatDate = (dateStr: string | null): string => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'long', day: 'numeric', year: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

function InfoLabel({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '4px' }}>
            {children}
        </div>
    );
}

function InfoValue({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ fontSize: '14px', fontWeight: 500, color: '#1e293b', marginBottom: '20px' }}>
            {children}
        </div>
    );
}

function StatusBadge({ status }: { status: ProjectRequestData['status'] }) {
    const map: Record<ProjectRequestData['status'], { bg: string; color: string; label: string }> = {
        pending:   { bg: '#fef9c3', color: '#854d0e', label: '⏳ Pending Review' },
        approved:  { bg: '#dcfce7', color: '#166534', label: '✓ Approved' },
        ongoing:   { bg: '#dbeafe', color: '#1e40af', label: '⚡ Ongoing' },
        rejected:  { bg: '#fee2e2', color: '#991b1b', label: '✗ Rejected' },
        completed: { bg: '#f3f4f6', color: '#374151', label: '✔ Completed' },
    };
    const s = map[status] ?? { bg: '#f3f4f6', color: '#374151', label: status };
    return (
        <span style={{ padding: '6px 16px', borderRadius: '99px', fontSize: '12px', fontWeight: 700, background: s.bg, color: s.color }}>
            {s.label}
        </span>
    );
}

function FinanceBadge({ children }: { children: React.ReactNode }) {
    return (
        <span style={{ padding: '3px 10px', borderRadius: '99px', fontSize: '11px', fontWeight: 700, background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd', marginRight: '5px' }}>
            {children}
        </span>
    );
}

// ── Attachment Item ────────────────────────────────────────────────────────
function AttachmentItem({ att }: { att: Attachment }) {
    const ext = att.filename.split('.').pop()?.toLowerCase() ?? '';
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
    const isPdf   = ext === 'pdf';
    const iconBg    = isImage ? '#eff6ff' : isPdf ? '#fff1f2' : '#f8fafc';
    const iconColor = isImage ? '#2563eb' : isPdf ? '#dc2626' : '#6b7280';

    return (
        <a
            href={att.url}
            target="_blank"
            rel="noopener noreferrer"
            style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 12px', borderRadius: '8px', border: '1px solid #e5e7eb', background: '#fff', textDecoration: 'none', color: 'inherit', marginBottom: '8px', transition: 'all 0.13s' }}
            onMouseEnter={e => { (e.currentTarget as HTMLAnchorElement).style.background = '#f8fafc'; (e.currentTarget as HTMLAnchorElement).style.borderColor = '#2563eb'; }}
            onMouseLeave={e => { (e.currentTarget as HTMLAnchorElement).style.background = '#fff'; (e.currentTarget as HTMLAnchorElement).style.borderColor = '#e5e7eb'; }}
        >
            <div style={{ width: '38px', height: '38px', borderRadius: '8px', background: iconBg, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2">
                    {isImage ? (
                        <><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></>
                    ) : (
                        <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></>
                    )}
                </svg>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: '12.5px', fontWeight: 600, color: '#0f172a', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {att.filename}
                </div>
                {att.description && (
                    <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '1px' }}>{att.description}</div>
                )}
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" style={{ flexShrink: 0 }}>
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
        </a>
    );
}

// ── Feedback Modal ─────────────────────────────────────────────────────────
function FeedbackModal({ onClose }: { onClose: () => void }) {
    const [discOther,   setDiscOther]   = useState(false);
    const [permitOther, setPermitOther] = useState(false);
    const [priority,    setPriority]    = useState('');
    const [remarks,     setRemarks]     = useState('');

    const inputStyle: React.CSSProperties = {
        width: '100%', padding: '8px 12px', borderRadius: '8px',
        border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
        fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box',
    };

    const CheckGroup = ({ items, name, showOther, onToggleOther }: {
        items: string[]; name: string; showOther: boolean; onToggleOther: (v: boolean) => void;
    }) => (
        <div style={{ background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '12px 14px' }}>
            {items.map(item => (
                <label key={item} style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', color: '#374151', cursor: 'pointer', marginBottom: '6px' }}>
                    <input type="checkbox" name={name} value={item} style={{ accentColor: '#2563eb', width: '14px', height: '14px', cursor: 'pointer' }} />
                    {item}
                </label>
            ))}
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', color: '#374151', cursor: 'pointer', marginBottom: showOther ? '6px' : 0 }}>
                <input type="checkbox" onChange={e => onToggleOther(e.target.checked)} style={{ accentColor: '#2563eb', width: '14px', height: '14px', cursor: 'pointer' }} />
                Others
            </label>
            {showOther && (
                <input type="text" placeholder="Specify..." style={{ ...inputStyle, marginTop: '4px', borderColor: '#2563eb' }} />
            )}
        </div>
    );

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.4)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '620px', boxShadow: '0 24px 64px rgba(0,0,0,0.18)', zIndex: 201, overflow: 'hidden', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
                <div style={{ padding: '16px 22px', background: '#0891b2', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '9px' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        <span style={{ fontSize: '14px', fontWeight: 700, color: '#fff' }}>Technical Feedback Form</span>
                    </div>
                    <button onClick={onClose} style={{ width: '28px', height: '28px', borderRadius: '6px', border: '1px solid rgba(255,255,255,0.3)', background: 'rgba(255,255,255,0.1)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '22px', overflowY: 'auto', flex: 1 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '18px', marginBottom: '18px' }}>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '7px' }}>Involved Disciplines</label>
                            <CheckGroup items={['Civil', 'Architectural', 'Electrical/Automation', 'Mechanical', 'Fire Protection']} name="discipline" showOther={discOther} onToggleOther={setDiscOther} />
                        </div>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '7px' }}>Required Permits</label>
                            <CheckGroup items={['Building Permit', 'Safety Permit', 'Environmental Permit', 'No Permits Needed']} name="permit" showOther={permitOther} onToggleOther={setPermitOther} />
                        </div>
                    </div>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px' }}>Priority Level</label>
                        <select value={priority} onChange={e => setPriority(e.target.value)} style={{ ...inputStyle, borderColor: '#06b6d4' }}>
                            <option value="" disabled>Select priority…</option>
                            {['Critical', 'High', 'Medium', 'Low'].map(p => <option key={p}>{p}</option>)}
                        </select>
                    </div>
                    <div>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px' }}>Technical Remarks / Comments</label>
                        <textarea rows={4} value={remarks} onChange={e => setRemarks(e.target.value)} placeholder="Enter detailed feedback or technical requirements..." style={{ ...inputStyle, resize: 'vertical', lineHeight: 1.6 }} />
                    </div>
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f3f4f6', background: '#f8fafc', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '8px 20px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', cursor: 'pointer', color: '#374151' }}>Close</button>
                    <button style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: '#0891b2', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: 'pointer' }}>Submit Feedback</button>
                </div>
            </div>
        </div>
    );
}

// ── Show Page ──────────────────────────────────────────────────────────────
export default function Show({ projectRequest }: Props) {
    if (!projectRequest) return null;

    const [showFeedback, setShowFeedback] = useState(false);

    const attachments = projectRequest.attachments ?? [];
    const getExt = (filename: string) => filename.split('.').pop()?.toLowerCase() ?? '';

    const pictures = attachments.filter(a => ['jpg','jpeg','png','gif','webp'].includes(getExt(a.filename)));
    const drawings = attachments.filter(a => ['pdf','dwg'].includes(getExt(a.filename)));
    const reports  = attachments.filter(a => ['doc','docx'].includes(getExt(a.filename)));
    const others   = attachments.filter(a => !['jpg','jpeg','png','gif','webp','pdf','dwg','doc','docx'].includes(getExt(a.filename)));

    const handleApprove = () => router.patch(route('requests.update', projectRequest.id), { status: 'approved' });
    const handleReject  = () => router.patch(route('requests.update', projectRequest.id), { status: 'rejected' });
    const handleDelete  = () => {
        if (confirm('Are you sure you want to cancel this request?')) {
            router.delete(route('requests.destroy', projectRequest.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`View Request — ${projectRequest.title}`} />

            {showFeedback && <FeedbackModal onClose={() => setShowFeedback(false)} />}

            {/* Breadcrumb + actions */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px', flexWrap: 'wrap', gap: '10px' }}>
                <div style={{ fontSize: '13px', color: '#9ca3af', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <Link href={route('requests.index')} style={{ color: '#9ca3af', textDecoration: 'none' }}>Requests</Link>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span style={{ color: '#374151', fontWeight: 600 }}>View</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span style={{ color: '#0f172a', fontWeight: 700 }}>#{projectRequest.id}</span>
                </div>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <button onClick={() => window.print()} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', fontWeight: 500, color: '#374151', cursor: 'pointer' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print
                    </button>
                    <button onClick={() => setShowFeedback(true)} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#0891b2', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Add Feedback
                    </button>
                    <Link href={route('requests.edit', projectRequest.id)} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Request
                    </Link>
                </div>
            </div>

            {/* Main card */}
            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>

                {/* Card header */}
                <div style={{ background: '#fafbfc', borderBottom: '1px solid #e5e7eb', padding: '20px 28px', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '16px' }}>
                    <div>
                        <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#0f172a', margin: '0 0 5px', letterSpacing: '-0.3px' }}>
                            {projectRequest.title}
                        </h2>
                        <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>
                            Submitted on {formatDate(projectRequest.created_at)}
                            {projectRequest.requester?.name && (
                                <> by <strong style={{ color: '#374151' }}>{projectRequest.requester.name}</strong></>
                            )}
                        </p>
                    </div>
                    <StatusBadge status={projectRequest.status} />
                </div>

                {/* Card body */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px' }}>

                    {/* Left */}
                    <div style={{ padding: '28px', borderRight: '1px solid #f3f4f6' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0 24px' }}>
                            <div>
                                <InfoLabel>Job Type</InfoLabel>
                                <InfoValue>{projectRequest.job_type ?? '—'}</InfoValue>
                            </div>
                            <div>
                                <InfoLabel>Job Location</InfoLabel>
                                <InfoValue>{projectRequest.job_location ?? '—'}</InfoValue>
                            </div>
                            <div>
                                <InfoLabel>Cost Code</InfoLabel>
                                <InfoValue>
                                    {projectRequest.costcode
                                        ? <span style={{ fontFamily: 'monospace', fontSize: '13.5px' }}>{projectRequest.costcode}</span>
                                        : <span style={{ color: '#9ca3af', fontStyle: 'italic' }}>Not specified</span>
                                    }
                                </InfoValue>
                            </div>
                            <div>
                                <InfoLabel>Financial Allocation</InfoLabel>
                                <InfoValue>
                                    {projectRequest.opex          && <FinanceBadge>OPEX</FinanceBadge>}
                                    {projectRequest.capex         && <FinanceBadge>CAPEX</FinanceBadge>}
                                    {projectRequest.for_budgeting && <FinanceBadge>For Budgeting</FinanceBadge>}
                                    {!projectRequest.opex && !projectRequest.capex && !projectRequest.for_budgeting && (
                                        <span style={{ color: '#9ca3af', fontStyle: 'italic', fontSize: '13.5px' }}>None specified</span>
                                    )}
                                </InfoValue>
                            </div>
                        </div>
                        <div>
                            <InfoLabel>Project Description</InfoLabel>
                            <div style={{ background: '#f8fafc', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '14px 16px', fontSize: '13.5px', lineHeight: 1.7, color: '#334155' }}>
                                {projectRequest.description ?? '—'}
                            </div>
                        </div>
                    </div>

                    {/* Right — attachments */}
                    <div style={{ padding: '28px' }}>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '7px' }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                            Attached Files
                        </div>

                        {attachments.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: '32px 0' }}>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 8px' }}>
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                </svg>
                                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>No attachments</p>
                            </div>
                        ) : (
                            <>
                                {pictures.length > 0 && (
                                    <div style={{ marginBottom: '18px' }}>
                                        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '8px' }}>Pictures ({pictures.length})</div>
                                        {pictures.map(a => <AttachmentItem key={a.id} att={a} />)}
                                    </div>
                                )}
                                {drawings.length > 0 && (
                                    <div style={{ marginBottom: '18px' }}>
                                        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '8px' }}>Draft Drawings ({drawings.length})</div>
                                        {drawings.map(a => <AttachmentItem key={a.id} att={a} />)}
                                    </div>
                                )}
                                {reports.length > 0 && (
                                    <div style={{ marginBottom: '18px' }}>
                                        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '8px' }}>Reports ({reports.length})</div>
                                        {reports.map(a => <AttachmentItem key={a.id} att={a} />)}
                                    </div>
                                )}
                                {others.length > 0 && (
                                    <div style={{ marginBottom: '18px' }}>
                                        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '8px' }}>Other Files ({others.length})</div>
                                        {others.map(a => <AttachmentItem key={a.id} att={a} />)}
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {/* Footer */}
                <div style={{ borderTop: '1px solid #f3f4f6', padding: '16px 28px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: '#fafbfc' }}>
                    <button onClick={handleDelete} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 16px', borderRadius: '7px', border: '1px solid #fca5a5', background: '#fff', fontSize: '12.5px', fontWeight: 600, color: '#dc2626', cursor: 'pointer' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        Cancel Request
                    </button>

                    {projectRequest.status === 'pending' && (
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <button onClick={handleReject} style={{ padding: '8px 22px', borderRadius: '7px', border: '1.5px solid #fca5a5', background: '#fff', fontSize: '13px', fontWeight: 600, color: '#dc2626', cursor: 'pointer' }}>
                                Reject
                            </button>
                            <button onClick={handleApprove} style={{ padding: '8px 28px', borderRadius: '7px', border: 'none', background: '#16a34a', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: 'pointer', boxShadow: '0 2px 8px rgba(22,163,74,0.25)' }}>
                                Approve Request
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}