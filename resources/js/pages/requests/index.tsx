import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';

// ── Types ──────────────────────────────────────────────────────────────────
interface Comment {
    id: number;
    author: string;
    date: string;
    content: string;
}

interface ProjectRequest {
    id: number;
    request_no: string | null;
    title: string;
    job_type: string;
    job_location: string;
    status: 'approved' | 'pending' | 'in_approval' | 'hold' | 'ongoing' | 'rejected' | 'completed';
    costcode: string | null;
    created_at: string | null;
    requester?: { name: string; department?: string | null };
    project?: { id: number; project_no: string } | null;
    // Who the sign-off chain is currently waiting on, once it has left the engineer.
    awaiting_role_label?: string | null;
    unread_comments?: number;
    comments?: Comment[];
    can: {
        update: boolean;
        delete: boolean;
        decide: boolean;
        canCreateProject: boolean;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    job_type?: string;
    job_location?: string;
    costcode?: string;
    status?: string[];
}

interface Props {
    requests: Paginated<ProjectRequest>;
    filters: Filters;
    // The PMD/division approval roles review requests but never raise them.
    canCreate: boolean;
}

type RequestDecision = 'approved' | 'rejected';

// ── Status Badge ───────────────────────────────────────────────────────────
function StatusBadge({ status }: { status: ProjectRequest['status'] }) {
    const map: Record<ProjectRequest['status'], { bg: string; color: string; label: string }> = {
        approved:  { bg: '#dcfce7', color: '#166534', label: 'Approved' },
        pending:   { bg: '#fef9c3', color: '#854d0e', label: 'For Approval' },
        in_approval: { bg: '#e0e7ff', color: '#3730a3', label: 'In Approval' },
        hold:      { bg: '#ffedd5', color: '#9a3412', label: 'Hold' },
        ongoing:   { bg: '#dbeafe', color: '#1e40af', label: 'Ongoing' },
        rejected:  { bg: '#fee2e2', color: '#991b1b', label: 'Rejected' },
        completed: { bg: '#f3f4f6', color: '#374151', label: 'Completed' },
    };
    const s = map[status] ?? { bg: '#f3f4f6', color: '#374151', label: status };
    return (
        <span style={{ padding: '3px 10px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.3px', background: s.bg, color: s.color }}>
            {s.label}
        </span>
    );
}

// ── Icon Button ────────────────────────────────────────────────────────────
function IconBtn({ onClick, title, color = '#374151', children }: {
    onClick?: () => void; title?: string; color?: string; children: React.ReactNode;
}) {
    return (
        <button onClick={onClick} title={title} style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', color, cursor: 'pointer', transition: 'all 0.12s', flexShrink: 0 }}
            onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
            onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
        >
            {children}
        </button>
    );
}

// ── Comment Modal ──────────────────────────────────────────────────────────
function CommentModal({ request, onClose }: { request: ProjectRequest | null; onClose: () => void }) {
    const [comments, setComments]     = useState<Comment[]>([]);
    const [newComment, setNewComment] = useState('');
    const [loading, setLoading]       = useState(false);
    const [posting, setPosting]       = useState(false);
    const bottomRef                   = useRef<HTMLDivElement>(null);

    // Read Laravel's XSRF-TOKEN cookie (refreshed on every response) rather than
    // the <meta> tag, which goes stale after Inertia reloads / session rotation.
    const xsrfToken = () =>
        decodeURIComponent(
            document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? ''
        );

    // Load comments when modal opens
    useEffect(() => {
        if (!request) return;
        setLoading(true);
        fetch(route('comments.index', request.id), {
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        })
            .then(r => r.json())
            .then(data => setComments(data))
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [request]);

    // Scroll to bottom when comments load
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [comments]);

    const postComment = async () => {
        if (!request || !newComment.trim()) return;
        setPosting(true);
        try {
            const res = await fetch(route('comments.store', request.id), {
                method: 'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                    'X-XSRF-TOKEN':  xsrfToken(),
                },
                body: JSON.stringify({ content: newComment.trim() }),
            });
            if (res.ok) {
                const comment = await res.json();
                setComments(prev => [...prev, comment]);
                setNewComment('');
                // An approver's comment may have flipped the request to HOLD —
                // refresh the list so the status column reflects it. reload() keeps
                // scroll + component state automatically, so the modal stays open.
                router.reload({ only: ['requests'] });
            }
        } catch (err) {
            console.error(err);
        } finally {
            setPosting(false);
        }
    };

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const deleteComment = (id: number) => {
        showConfirm('Delete this comment?', async () => {
            try {
                await fetch(route('comments.destroy', id), {
                    method: 'DELETE',
                    headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
                });
                setComments(prev => prev.filter(c => c.id !== id));
            } catch (err) {
                console.error(err);
            }
        }, { title: 'Delete Comment', confirmLabel: 'Delete', variant: 'danger' });
    };

    if (!request) return null;

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            {confirmDialog}
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.35)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '480px', boxShadow: '0 20px 60px rgba(0,0,0,0.15)', zIndex: 201, overflow: 'hidden', display: 'flex', flexDirection: 'column', maxHeight: '85vh' }}>

                {/* Header */}
                <div style={{ padding: '16px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #f3f4f6', flexShrink: 0 }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>Comments</div>
                        <div style={{ fontSize: '11.5px', color: '#9ca3af', marginTop: '1px' }}>{request.title}</div>
                    </div>
                    <button onClick={onClose} style={{ width: '28px', height: '28px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Comment list */}
                <div style={{ flex: 1, overflowY: 'auto', padding: '14px 20px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    {loading ? (
                        <div style={{ textAlign: 'center', padding: '24px 0', color: '#9ca3af', fontSize: '13px' }}>Loading…</div>
                    ) : comments.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: '24px 0' }}>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 8px' }}>
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>No comments yet. Be the first!</p>
                        </div>
                    ) : (
                        comments.map(c => (
                            <div key={c.id} style={{ background: '#f8fafc', borderRadius: '8px', padding: '10px 12px', border: '1px solid #f0f2f5' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '4px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '7px' }}>
                                        <div style={{ width: '24px', height: '24px', borderRadius: '6px', background: '#2563eb', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '9px', fontWeight: 700, color: '#fff', flexShrink: 0 }}>
                                            {c.author?.slice(0, 2).toUpperCase()}
                                        </div>
                                        <span style={{ fontSize: '12px', fontWeight: 700, color: '#374151' }}>{c.author}</span>
                                        <span style={{ fontSize: '11px', color: '#9ca3af' }}>{c.date}</span>
                                    </div>
                                    <button
                                        onClick={() => deleteComment(c.id)}
                                        title="Delete comment"
                                        style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#d1d5db', display: 'flex', padding: '2px', borderRadius: '4px' }}
                                        onMouseEnter={e => (e.currentTarget.style.color = '#ef4444')}
                                        onMouseLeave={e => (e.currentTarget.style.color = '#d1d5db')}
                                    >
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                    </button>
                                </div>
                                <p style={{ fontSize: '12.5px', color: '#334155', margin: 0, lineHeight: 1.6, paddingLeft: '31px' }}>{c.content}</p>
                            </div>
                        ))
                    )}
                    <div ref={bottomRef} />
                </div>

                {/* Add comment */}
                <div style={{ padding: '12px 20px 16px', borderTop: '1px solid #f3f4f6', flexShrink: 0 }}>
                    <label style={{ fontSize: '12px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px' }}>Add Comment</label>
                    <textarea
                        rows={3}
                        value={newComment}
                        onChange={e => setNewComment(e.target.value)}
                        onKeyDown={e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) postComment(); }}
                        placeholder="Write a note… (Ctrl+Enter to submit)"
                        style={{ width: '100%', padding: '8px 12px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', resize: 'vertical', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', transition: 'border-color 0.15s' }}
                        onFocus={e => (e.target.style.borderColor = '#2563eb')}
                        onBlur={e => (e.target.style.borderColor = '#e5e7eb')}
                    />
                    <button
                        onClick={postComment}
                        disabled={posting || !newComment.trim()}
                        style={{ width: '100%', marginTop: '8px', padding: '9px', borderRadius: '7px', background: posting || !newComment.trim() ? '#93c5fd' : '#2563eb', color: '#fff', border: 'none', fontSize: '13px', fontWeight: 600, cursor: posting || !newComment.trim() ? 'not-allowed' : 'pointer', transition: 'background 0.15s', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
                    >
                        {posting ? (
                            <>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                Posting…
                            </>
                        ) : (
                            <>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                Post Comment
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Advanced Search Modal ──────────────────────────────────────────────────
function AdvancedSearchModal({ filters, onClose }: { filters: Filters; onClose: () => void }) {
    const [jobType,     setJobType]     = useState(filters.job_type ?? '');
    const [jobLocation, setJobLocation] = useState(filters.job_location ?? '');
    const [costcode,    setCostcode]    = useState(filters.costcode ?? '');
    const [statuses,    setStatuses]    = useState<string[]>(filters.status ?? []);

    const toggleStatus = (s: string) =>
        setStatuses(prev => prev.includes(s) ? prev.filter(x => x !== s) : [...prev, s]);

    const apply = () => {
        router.get(route('requests.index'), {
            ...(jobType     && { job_type: jobType }),
            ...(jobLocation && { job_location: jobLocation }),
            ...(costcode    && { costcode }),
            ...(statuses.length && { status: statuses }),
        }, { preserveState: true });
        onClose();
    };

    const selectStyle: React.CSSProperties = { width: '100%', padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none', fontFamily: 'inherit' };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.35)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '560px', boxShadow: '0 20px 60px rgba(0,0,0,0.15)', zIndex: 201 }}>
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>Advanced Search</span>
                    <button onClick={onClose} style={{ width: '28px', height: '28px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px 22px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Job Type</label>
                            <select value={jobType} onChange={e => setJobType(e.target.value)} style={selectStyle}>
                                <option value="">All Types</option>
                                {['Construction','Design','Installation','Study/Report','Modification','Estimate','Demolition/Removal','Retrofitting','Others'].map(t => <option key={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Job Location</label>
                            <input type="text" value={jobLocation} onChange={e => setJobLocation(e.target.value)} placeholder="Filter by location" style={{ ...selectStyle, boxSizing: 'border-box' as const }} />
                        </div>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '5px' }}>Cost Code</label>
                            <input type="text" value={costcode} onChange={e => setCostcode(e.target.value)} placeholder="Enter code" style={{ ...selectStyle, boxSizing: 'border-box' as const }} />
                        </div>
                    </div>
                    <div>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '7px' }}>Status</label>
                        <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
                            {[
                                { value: 'approved',  label: 'Approved' },
                                { value: 'pending',   label: 'For Approval' },
                                { value: 'in_approval', label: 'In Approval' },
                                { value: 'hold',      label: 'Hold' },
                                { value: 'ongoing',   label: 'Ongoing' },
                                { value: 'rejected',  label: 'Rejected' },
                                { value: 'completed', label: 'Completed' },
                            ].map(({ value, label }) => (
                                <label key={value} style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', color: '#374151', cursor: 'pointer' }}>
                                    <input type="checkbox" checked={statuses.includes(value)} onChange={() => toggleStatus(value)} style={{ cursor: 'pointer', accentColor: '#2563eb' }} />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f3f4f6', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} style={{ padding: '7px 20px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', cursor: 'pointer', color: '#374151' }}>Close</button>
                    <button onClick={apply} style={{ padding: '7px 20px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 600, cursor: 'pointer' }}>Apply Filters</button>
                </div>
            </div>
        </div>
    );
}

// ── Pagination ─────────────────────────────────────────────────────────────
function DecisionModal({
    request,
    decision,
    processing,
    onClose,
    onConfirm,
}: {
    request: ProjectRequest;
    decision: RequestDecision;
    processing: boolean;
    onClose: () => void;
    onConfirm: () => void;
}) {
    const isApprove = decision === 'approved';

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 220, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '18px' }}>
            <div onClick={processing ? undefined : onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.42)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: '420px', background: '#fff', borderRadius: '12px', boxShadow: '0 24px 70px rgba(15,23,42,0.25)', overflow: 'hidden', zIndex: 221 }}>
                <div style={{ padding: '18px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a' }}>
                            {isApprove ? 'Approve Request' : 'Reject Request'}
                        </div>
                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '2px' }}>
                            {request.request_no ?? `#${request.id}`} · {request.title}
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        style={{ width: '30px', height: '30px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', cursor: processing ? 'not-allowed' : 'pointer', opacity: processing ? 0.6 : 1 }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px' }}>
                    <p style={{ margin: '0 0 16px', fontSize: '13px', lineHeight: 1.6, color: '#475569' }}>
                        Are you sure you want to {isApprove ? 'approve' : 'reject'} this project request?
                    </p>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={processing}
                            style={{ padding: '8px 16px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', color: '#374151', fontSize: '12.5px', fontWeight: 600, cursor: processing ? 'not-allowed' : 'pointer' }}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={onConfirm}
                            disabled={processing}
                            style={{ padding: '8px 16px', borderRadius: '7px', border: 'none', background: isApprove ? '#16a34a' : '#dc2626', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: processing ? 'not-allowed' : 'pointer', opacity: processing ? 0.75 : 1 }}
                        >
                            {processing ? 'Saving...' : isApprove ? 'Approve' : 'Reject'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Pagination({ data }: { data: Paginated<unknown> }) {
    if (data.last_page <= 1) return null;
    return (
        <div style={{ padding: '14px 18px', borderTop: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
            <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                Showing <strong style={{ color: '#374151' }}>{data.from}–{data.to}</strong> of <strong style={{ color: '#374151' }}>{data.total}</strong> results
            </span>
            <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                {data.links.map((link, i) => {
                    const isDisabled = !link.url;
                    const isActive   = link.active;
                    const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›').replace(/&[^;]+;/g, '');
                    return (
                        <button key={i} disabled={isDisabled} onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                            style={{ minWidth: '32px', height: '32px', padding: '0 8px', borderRadius: '7px', border: '1px solid', borderColor: isActive ? '#2563eb' : '#e5e7eb', background: isActive ? '#2563eb' : '#fff', color: isActive ? '#fff' : isDisabled ? '#d1d5db' : '#374151', fontSize: '12.5px', fontWeight: isActive ? 700 : 400, cursor: isDisabled ? 'not-allowed' : 'pointer', transition: 'all 0.12s' }}>
                            {label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function RequestsIndex({ requests, filters, canCreate }: Props) {
    const [search,        setSearch]        = useState(filters.search ?? '');
    const [commentTarget, setCommentTarget] = useState<ProjectRequest | null>(null);
    const [showAdvSearch, setShowAdvSearch] = useState(false);
    const [decisionTarget, setDecisionTarget] = useState<{ request: ProjectRequest; decision: RequestDecision } | null>(null);
    const [decisionProcessing, setDecisionProcessing] = useState(false);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const deleteRequest = (req: ProjectRequest) => {
        showConfirm(
            `Delete request "${req.title}"? Its attachments will be permanently removed. This cannot be undone.`,
            () => router.delete(route('requests.destroy', req.id), { preserveScroll: true }),
            { title: 'Delete Request', confirmLabel: 'Delete', variant: 'danger' },
        );
    };

    const doSearch    = () => router.get(route('requests.index'), { search }, { preserveState: true });
    const clearFilters = () => router.get(route('requests.index'));
    const hasFilters  = Object.values(filters).some(v => v && (Array.isArray(v) ? v.length : true));
    const confirmDecision = () => {
        if (!decisionTarget) return;

        setDecisionProcessing(true);
        router.patch(
            route('requests.update', decisionTarget.request.id),
            { status: decisionTarget.decision },
            {
                preserveScroll: true,
                onSuccess: () => setDecisionTarget(null),
                onFinish: () => setDecisionProcessing(false),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Project Requests" />

            <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>

            {confirmDialog}
            {commentTarget && <CommentModal request={commentTarget} onClose={() => setCommentTarget(null)} />}
            {showAdvSearch && <AdvancedSearchModal filters={filters} onClose={() => setShowAdvSearch(false)} />}
            {decisionTarget && (
                <DecisionModal
                    request={decisionTarget.request}
                    decision={decisionTarget.decision}
                    processing={decisionProcessing}
                    onClose={() => !decisionProcessing && setDecisionTarget(null)}
                    onConfirm={confirmDecision}
                />
            )}

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    Project Requests Registry
                </h1>
                <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                    {requests.total} total record{requests.total !== 1 ? 's' : ''}
                </span>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden' }}>

                {/* Toolbar */}
                <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '7px 12px', flex: '1', maxWidth: '320px' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && doSearch()} placeholder="Search request, project, title…" style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }} />
                        {search && (
                            <button onClick={() => { setSearch(''); clearFilters(); }} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', display: 'flex', padding: 0 }}>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        )}
                    </div>
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        {hasFilters && (
                            <button onClick={clearFilters} style={{ display: 'flex', alignItems: 'center', gap: '5px', padding: '7px 12px', borderRadius: '7px', border: '1px solid #fca5a5', background: '#fff7f7', fontSize: '12px', fontWeight: 500, color: '#dc2626', cursor: 'pointer' }}>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Clear filters
                            </button>
                        )}
                        <button onClick={() => setShowAdvSearch(true)} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: `1px solid ${hasFilters ? '#2563eb' : '#e5e7eb'}`, background: hasFilters ? '#eff6ff' : '#fff', fontSize: '12.5px', fontWeight: 500, color: hasFilters ? '#2563eb' : '#374151', cursor: 'pointer' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                            Advanced Search
                        </button>
                        {canCreate && (
                            <Link href={route('requests.create')} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Request
                            </Link>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['Request ID', 'Title', 'Requester', 'Created At', 'Status', 'Project No', 'Actions'].map((h, i, arr) => (
                                    <th key={h} style={{ padding: '10px 16px', textAlign: i === arr.length - 1 ? 'right' : 'left', fontSize: '10.5px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f3f4f6', whiteSpace: 'nowrap' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} style={{ padding: '48px', textAlign: 'center' }}>
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 10px' }}>
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span style={{ fontSize: '13px', color: '#9ca3af' }}>No requests found.</span>
                                    </td>
                                </tr>
                            ) : requests.data.map(req => (
                                <tr key={req.id} style={{ borderBottom: '1px solid #f8fafc', transition: 'background 0.1s' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <td style={{ padding: '12px 16px', color: '#9ca3af', fontSize: '11.5px', fontFamily: 'monospace', whiteSpace: 'nowrap' }}>{req.request_no ?? `#${req.id}`}</td>
                                    <td style={{ padding: '12px 16px', fontWeight: 600, color: '#0f172a', minWidth: '300px' }}>{req.title}</td>
                                    <td style={{ padding: '12px 16px', color: '#6b7280' }}>
                                        <div>{req.requester?.name ?? '—'}</div>
                                        {req.requester?.department && (
                                            <div style={{ fontSize: '11.5px', color: '#9ca3af', marginTop: '2px' }}>{req.requester.department}</div>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px 16px', color: '#64748b', fontSize: '12px', whiteSpace: 'nowrap' }}>{req.created_at ?? '—'}</td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <StatusBadge status={req.status} />
                                        {req.awaiting_role_label && ['pending', 'in_approval', 'hold'].includes(req.status) && (
                                            <div style={{ fontSize: '10.5px', color: '#9ca3af', marginTop: '4px', whiteSpace: 'nowrap' }}>
                                                with {req.awaiting_role_label}
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        {req.project?.project_no
                                            ? <span style={{ fontFamily: 'monospace', fontSize: '12px', color: '#374151' }}>{req.project.project_no}</span>
                                            : <span style={{ color: '#d1d5db', fontSize: '12px' }}>—</span>
                                        }
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'flex-end' }}>
                                            {req.status === 'approved' && !req.project && req.can.canCreateProject && (
                                                <IconBtn title="Create Project" color="#2563eb" onClick={() => router.visit(`${route('projects.create')}?request_id=${req.id}`)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                </IconBtn>
                                            )}

                                            <IconBtn title="View" onClick={() => router.visit(route('requests.show', req.id))}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </IconBtn>

                                            {req.can.update && (
                                                <IconBtn title="Edit" onClick={() => router.visit(route('requests.edit', req.id))}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </IconBtn>
                                            )}

                                            {req.can.delete && (
                                                <IconBtn title="Delete" color="#dc2626" onClick={() => deleteRequest(req)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </IconBtn>
                                            )}

                                            {req.can.decide && (
                                                <>
                                                    <IconBtn title="Approve" color="#16a34a" onClick={() => setDecisionTarget({ request: req, decision: 'approved' })}>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </IconBtn>
                                                    <IconBtn title="Reject" color="#dc2626" onClick={() => setDecisionTarget({ request: req, decision: 'rejected' })}>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </IconBtn>
                                                </>
                                            )}

                                            <div style={{ position: 'relative', display: 'inline-flex' }}>
                                                {(req.unread_comments ?? 0) > 0 && (
                                                    <span style={{ position: 'absolute', top: '-6px', right: '-6px', background: '#ef4444', color: '#fff', fontSize: '9px', fontWeight: 700, minWidth: '16px', height: '16px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', border: '2px solid #fff', zIndex: 1 }}>
                                                        {req.unread_comments}
                                                    </span>
                                                )}
                                                <IconBtn title="Comments" color={(req.unread_comments ?? 0) > 0 ? '#ef4444' : '#374151'} onClick={() => setCommentTarget(req)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                                </IconBtn>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination data={requests as Paginated<unknown>} />
            </div>
        </AuthenticatedLayout>
    );
}
