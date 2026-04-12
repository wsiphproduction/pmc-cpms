import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface Comment {
    id: number;
    author: string;
    date: string;
    content: string;
}

interface ProjectRequest {
    id: number;
    title: string;
    job_type: string;
    job_location: string;
    status: 'approved' | 'pending' | 'ongoing' | 'rejected' | 'completed';
    costcode: string | null;
    requester?: { name: string };
    unread_comments?: number;
    comments?: Comment[];
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
}

// ── Status Badge ───────────────────────────────────────────────────────────
function StatusBadge({ status }: { status: ProjectRequest['status'] }) {
    const map: Record<ProjectRequest['status'], { bg: string; color: string }> = {
        approved:  { bg: '#dcfce7', color: '#166534' },
        pending:   { bg: '#fef9c3', color: '#854d0e' },
        ongoing:   { bg: '#dbeafe', color: '#1e40af' },
        rejected:  { bg: '#fee2e2', color: '#991b1b' },
        completed: { bg: '#f3f4f6', color: '#374151' },
    };
    const s = map[status] ?? { bg: '#f3f4f6', color: '#374151' };
    return (
        <span style={{ padding: '3px 10px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.3px', background: s.bg, color: s.color }}>
            {status}
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

    const csrfToken = () =>
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    // Load comments when modal opens
    useEffect(() => {
        if (!request) return;
        setLoading(true);
        fetch(route('comments.index', request.id), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        })
            .then(r => r.json())
            .then(data => setComments(data))
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [request?.id]);

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
                    'X-CSRF-TOKEN':  csrfToken(),
                },
                body: JSON.stringify({ content: newComment.trim() }),
            });
            if (res.ok) {
                const comment = await res.json();
                setComments(prev => [...prev, comment]);
                setNewComment('');
            }
        } catch (err) {
            console.error(err);
        } finally {
            setPosting(false);
        }
    };

    const deleteComment = async (id: number) => {
        if (!confirm('Delete this comment?')) return;
        try {
            await fetch(route('comments.destroy', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            });
            setComments(prev => prev.filter(c => c.id !== id));
        } catch (err) {
            console.error(err);
        }
    };

    if (!request) return null;

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
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
                            {['approved','pending','ongoing','rejected','completed'].map(s => (
                                <label key={s} style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', color: '#374151', cursor: 'pointer', textTransform: 'capitalize' }}>
                                    <input type="checkbox" checked={statuses.includes(s)} onChange={() => toggleStatus(s)} style={{ cursor: 'pointer', accentColor: '#2563eb' }} />
                                    {s}
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
export default function RequestsIndex({ requests, filters }: Props) {
    const [search,        setSearch]        = useState(filters.search ?? '');
    const [commentTarget, setCommentTarget] = useState<ProjectRequest | null>(null);
    const [showAdvSearch, setShowAdvSearch] = useState(false);

    const doSearch    = () => router.get(route('requests.index'), { search }, { preserveState: true });
    const clearFilters = () => router.get(route('requests.index'));
    const hasFilters  = Object.values(filters).some(v => v && (Array.isArray(v) ? v.length : true));

    return (
        <AuthenticatedLayout>
            <Head title="Project Requests" />

            <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>

            {commentTarget && <CommentModal request={commentTarget} onClose={() => setCommentTarget(null)} />}
            {showAdvSearch && <AdvancedSearchModal filters={filters} onClose={() => setShowAdvSearch(false)} />}

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
                        <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && doSearch()} placeholder="Search title, job type, location…" style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }} />
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
                        <Link href={route('requests.create')} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', background: '#2563eb', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Request
                        </Link>
                    </div>
                </div>

                {/* Table */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['#', 'Title', 'Job Type', 'Location', 'Requester', 'Cost Code', 'Status', 'Actions'].map((h, i) => (
                                    <th key={h} style={{ padding: '10px 16px', textAlign: i === 7 ? 'right' : 'left', fontSize: '10.5px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f3f4f6', whiteSpace: 'nowrap' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} style={{ padding: '48px', textAlign: 'center' }}>
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
                                    <td style={{ padding: '12px 16px', color: '#9ca3af', fontSize: '11.5px' }}>#{req.id}</td>
                                    <td style={{ padding: '12px 16px', fontWeight: 600, color: '#0f172a', maxWidth: '220px' }}>{req.title}</td>
                                    <td style={{ padding: '12px 16px', color: '#6b7280' }}>{req.job_type}</td>
                                    <td style={{ padding: '12px 16px', color: '#6b7280', maxWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{req.job_location}</td>
                                    <td style={{ padding: '12px 16px', color: '#6b7280' }}>{req.requester?.name ?? '—'}</td>
                                    <td style={{ padding: '12px 16px' }}>
                                        {req.costcode
                                            ? <span style={{ fontFamily: 'monospace', fontSize: '12px', color: '#374151' }}>{req.costcode}</span>
                                            : <span style={{ color: '#d1d5db', fontSize: '12px' }}>—</span>
                                        }
                                    </td>
                                    <td style={{ padding: '12px 16px' }}><StatusBadge status={req.status} /></td>
                                    <td style={{ padding: '12px 16px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'flex-end' }}>

                                            <IconBtn title="View" onClick={() => router.visit(route('requests.show', req.id))}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </IconBtn>

                                            <IconBtn title="Edit" onClick={() => router.visit(route('requests.edit', req.id))}>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </IconBtn>

                                            {req.status === 'pending' && (
                                                <>
                                                    <IconBtn title="Approve" color="#16a34a" onClick={() => router.patch(route('requests.update', req.id), { status: 'approved' })}>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </IconBtn>
                                                    <IconBtn title="Reject" color="#dc2626" onClick={() => router.patch(route('requests.update', req.id), { status: 'rejected' })}>
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