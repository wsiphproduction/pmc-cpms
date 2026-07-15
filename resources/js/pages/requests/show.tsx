import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';

// ── Types ──────────────────────────────────────────────────────────────────
interface Attachment {
    id: number;
    filename: string;
    filepath: string;
    type: 'picture' | 'drawing' | 'report' | 'other' | null;
    description: string | null;
    url: string;
}

interface Comment {
    id: number;
    content: string;
    author: string;
    date: string;
}

interface User {
    id: number;
    name: string;
}

interface ProjectRequestData {
    id: number;
    request_no: string | null;
    title: string;
    job_type: string;
    description: string;
    job_location: string;
    costcode: string | null;
    opex: boolean;
    capex: boolean;
    for_budgeting: boolean;
    status: 'pending' | 'approved' | 'hold' | 'ongoing' | 'rejected' | 'completed';
    requester: User | null;
    project: { id: number; project_no: string } | null;
    attachments: Attachment[];
    created_at: string | null;
    can: {
        update: boolean;
        delete: boolean;
        decide: boolean;
        canCreateProject: boolean;
    };
}

interface FeedbackEntry {
    id: number;
    can_edit: boolean;
    author: string;
    date: string;
    priority: string | null;
    disciplines: string[];
    permits: string[];
    remarks: string | null;
}

const DISCIPLINE_OPTIONS = ['Civil', 'Architectural', 'Electrical/Automation', 'Mechanical', 'Fire Protection'];
const PERMIT_OPTIONS = ['Building Permit', 'Safety Permit', 'Environmental Permit', 'No Permits Needed'];

interface Props {
    projectRequest: ProjectRequestData;
    feedbacks: FeedbackEntry[];
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
        pending:   { bg: '#fef9c3', color: '#854d0e', label: '⏳ For Approval' },
        approved:  { bg: '#dcfce7', color: '#166534', label: '✓ Approved' },
        hold:      { bg: '#ffedd5', color: '#9a3412', label: '⏸ On Hold' },
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
const feedbackInputStyle: React.CSSProperties = {
    width: '100%', padding: '8px 12px', borderRadius: '8px',
    border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
    fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box',
};

// Defined at module scope (not inside FeedbackModal) so it keeps a stable
// component identity across re-renders — otherwise changing the priority select
// remounted this subtree and wiped the checkbox selections.
function CheckGroup({ items, selected, onToggle, showOther, onToggleOther, otherValue, onOtherChange }: {
    items: string[];
    selected: string[];
    onToggle: (item: string) => void;
    showOther: boolean;
    onToggleOther: (v: boolean) => void;
    otherValue: string;
    onOtherChange: (v: string) => void;
}) {
    return (
        <div style={{ background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '12px 14px' }}>
            {items.map(item => (
                <label key={item} style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', color: '#374151', cursor: 'pointer', marginBottom: '6px' }}>
                    <input type="checkbox" checked={selected.includes(item)} onChange={() => onToggle(item)} style={{ accentColor: '#2563eb', width: '14px', height: '14px', cursor: 'pointer' }} />
                    {item}
                </label>
            ))}
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', color: '#374151', cursor: 'pointer', marginBottom: showOther ? '6px' : 0 }}>
                <input type="checkbox" checked={showOther} onChange={e => onToggleOther(e.target.checked)} style={{ accentColor: '#2563eb', width: '14px', height: '14px', cursor: 'pointer' }} />
                Others
            </label>
            {showOther && (
                <input type="text" value={otherValue} onChange={e => onOtherChange(e.target.value)} placeholder="Specify..." style={{ ...feedbackInputStyle, marginTop: '4px', borderColor: '#2563eb' }} />
            )}
        </div>
    );
}

// Split a saved list into the known checkbox items and any leftover free-text
// "other" values (joined back into the single "Others" field).
function splitKnown(values: string[], known: string[]): { selected: string[]; other: string } {
    const selected = values.filter(v => known.includes(v));
    const other = values.filter(v => !known.includes(v)).join(', ');
    return { selected, other };
}

function FeedbackModal({ projectRequestId, feedback, onClose }: {
    projectRequestId: number; feedback?: FeedbackEntry | null; onClose: () => void;
}) {
    const isEdit = !!feedback;
    const initialDisc   = splitKnown(feedback?.disciplines ?? [], DISCIPLINE_OPTIONS);
    const initialPermit = splitKnown(feedback?.permits ?? [], PERMIT_OPTIONS);

    const [disciplines,     setDisciplines]     = useState<string[]>(initialDisc.selected);
    const [permits,         setPermits]         = useState<string[]>(initialPermit.selected);
    const [discOther,       setDiscOther]       = useState(!!initialDisc.other);
    const [discOtherText,   setDiscOtherText]   = useState(initialDisc.other);
    const [permitOther,     setPermitOther]     = useState(!!initialPermit.other);
    const [permitOtherText, setPermitOtherText] = useState(initialPermit.other);
    const [priority,        setPriority]        = useState(feedback?.priority ?? '');
    const [remarks,         setRemarks]         = useState(feedback?.remarks ?? '');
    const [submitting,      setSubmitting]      = useState(false);
    const [error,           setError]           = useState('');

    const toggle = (setter: React.Dispatch<React.SetStateAction<string[]>>) => (item: string) =>
        setter(prev => prev.includes(item) ? prev.filter(x => x !== item) : [...prev, item]);

    const submit = () => {
        if (!remarks.trim()) { setError('Technical remarks are required.'); return; }
        setError('');
        setSubmitting(true);

        const allDisciplines = [...disciplines, ...(discOther && discOtherText.trim() ? [discOtherText.trim()] : [])];
        const allPermits     = [...permits,     ...(permitOther && permitOtherText.trim() ? [permitOtherText.trim()] : [])];

        // Submit via Inertia's router (axios + fresh XSRF-TOKEN cookie) so it is
        // never bitten by a stale meta CSRF token on a long-open page. The
        // controller redirects back to the show page, refreshing the feedback list.
        const payload = {
            disciplines: allDisciplines,
            permits:     allPermits,
            priority:    priority || null,
            remarks:     remarks.trim(),
        };
        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError:   (errors: Record<string, string>) => setError(errors.remarks ?? errors.priority ?? 'Failed to submit feedback. Please try again.'),
            onFinish:  () => setSubmitting(false),
        };

        if (isEdit) {
            router.patch(route('requests.feedback.update', feedback!.id), payload, opts);
        } else {
            router.post(route('requests.feedback.store', projectRequestId), payload, opts);
        }
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.4)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '620px', boxShadow: '0 24px 64px rgba(0,0,0,0.18)', zIndex: 201, overflow: 'hidden', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
                <div style={{ padding: '16px 22px', background: '#0891b2', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '9px' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        <span style={{ fontSize: '14px', fontWeight: 700, color: '#fff' }}>{isEdit ? 'Edit Technical Feedback' : 'Technical Feedback Form'}</span>
                    </div>
                    <button onClick={onClose} style={{ width: '28px', height: '28px', borderRadius: '6px', border: '1px solid rgba(255,255,255,0.3)', background: 'rgba(255,255,255,0.1)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '22px', overflowY: 'auto', flex: 1 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '18px', marginBottom: '18px' }}>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '7px' }}>Involved Disciplines</label>
                            <CheckGroup
                                items={DISCIPLINE_OPTIONS}
                                selected={disciplines} onToggle={toggle(setDisciplines)}
                                showOther={discOther} onToggleOther={setDiscOther}
                                otherValue={discOtherText} onOtherChange={setDiscOtherText}
                            />
                        </div>
                        <div>
                            <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '7px' }}>Required Permits</label>
                            <CheckGroup
                                items={PERMIT_OPTIONS}
                                selected={permits} onToggle={toggle(setPermits)}
                                showOther={permitOther} onToggleOther={setPermitOther}
                                otherValue={permitOtherText} onOtherChange={setPermitOtherText}
                            />
                        </div>
                    </div>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px' }}>Priority Level</label>
                        <select value={priority} onChange={e => setPriority(e.target.value)} style={{ ...feedbackInputStyle, borderColor: '#06b6d4' }}>
                            <option value="">Select priority…</option>
                            {['Critical', 'High', 'Medium', 'Low'].map(p => <option key={p}>{p}</option>)}
                        </select>
                    </div>
                    <div>
                        <label style={{ fontSize: '11.5px', fontWeight: 700, color: '#374151', display: 'block', marginBottom: '6px' }}>Technical Remarks / Comments <span style={{ color: '#ef4444' }}>*</span></label>
                        <textarea rows={4} value={remarks} onChange={e => { setRemarks(e.target.value); if (error) setError(''); }} placeholder="Enter detailed feedback or technical requirements..." style={{ ...feedbackInputStyle, resize: 'vertical', lineHeight: 1.6 }} />
                    </div>
                    {error && (
                        <p style={{ fontSize: '12px', color: '#dc2626', margin: '12px 0 0', display: 'flex', alignItems: 'center', gap: '5px' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {error}
                        </p>
                    )}
                </div>
                <div style={{ padding: '14px 22px', borderTop: '1px solid #f3f4f6', background: '#f8fafc', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <button onClick={onClose} disabled={submitting} style={{ padding: '8px 20px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', cursor: submitting ? 'not-allowed' : 'pointer', color: '#374151' }}>Close</button>
                    <button onClick={submit} disabled={submitting} style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: submitting ? '#67c8db' : '#0891b2', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: submitting ? 'not-allowed' : 'pointer' }}>
                        {submitting ? 'Saving…' : isEdit ? 'Update Feedback' : 'Submit Feedback'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Priority Badge ─────────────────────────────────────────────────────────
function PriorityBadge({ priority }: { priority: string | null }) {
    if (!priority) return null;
    const map: Record<string, { bg: string; color: string }> = {
        Critical: { bg: '#fee2e2', color: '#991b1b' },
        High:     { bg: '#ffedd5', color: '#9a3412' },
        Medium:   { bg: '#fef9c3', color: '#854d0e' },
        Low:      { bg: '#dcfce7', color: '#166534' },
    };
    const s = map[priority] ?? { bg: '#f3f4f6', color: '#374151' };
    return (
        <span style={{ padding: '3px 10px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.3px', background: s.bg, color: s.color }}>
            {priority}
        </span>
    );
}

// ── Feedback Section ───────────────────────────────────────────────────────
function FeedbackSection({ feedbacks, onEdit, onDelete }: {
    feedbacks: FeedbackEntry[];
    onEdit: (f: FeedbackEntry) => void;
    onDelete: (f: FeedbackEntry) => void;
}) {
    if (feedbacks.length === 0) return null;

    const Chips = ({ label, items }: { label: string; items: string[] }) => (
        items.length === 0 ? null : (
            <div style={{ marginTop: '8px' }}>
                <span style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginRight: '6px' }}>{label}:</span>
                {items.map(i => (
                    <span key={i} style={{ display: 'inline-block', padding: '2px 9px', borderRadius: '99px', fontSize: '11px', fontWeight: 600, background: '#ecfeff', color: '#0e7490', border: '1px solid #a5f3fc', marginRight: '5px', marginTop: '4px' }}>{i}</span>
                ))}
            </div>
        )
    );

    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.04)', marginTop: '20px' }}>
            <div style={{ padding: '18px 28px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '7px' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0891b2" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>Technical Feedback ({feedbacks.length})</span>
            </div>
            <div style={{ padding: '20px 28px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                {feedbacks.map(f => (
                    <div key={f.id} style={{ background: '#f8fafc', borderRadius: '8px', padding: '14px 16px', border: '1px solid #f0f2f5' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px', gap: '8px', flexWrap: 'wrap' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <div style={{ width: '26px', height: '26px', borderRadius: '7px', background: '#0891b2', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 700, color: '#fff', flexShrink: 0 }}>
                                    {f.author?.slice(0, 2).toUpperCase()}
                                </div>
                                <span style={{ fontSize: '13px', fontWeight: 700, color: '#374151' }}>{f.author}</span>
                                <span style={{ fontSize: '11.5px', color: '#9ca3af' }}>{f.date}</span>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <PriorityBadge priority={f.priority} />
                                {f.can_edit && (
                                    <div style={{ display: 'flex', gap: '4px' }}>
                                        <button
                                            onClick={() => onEdit(f)}
                                            title="Edit feedback"
                                            style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', color: '#0891b2', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
                                        >
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button
                                            onClick={() => onDelete(f)}
                                            title="Delete feedback"
                                            style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1px solid #fca5a5', background: '#fff7f7', color: '#dc2626', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
                                        >
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                        {f.remarks && <p style={{ fontSize: '13px', color: '#334155', margin: '4px 0 0', lineHeight: 1.6 }}>{f.remarks}</p>}
                        <Chips label="Disciplines" items={f.disciplines} />
                        <Chips label="Permits" items={f.permits} />
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Comments Section ───────────────────────────────────────────────────────
function CommentsSection({ projectRequestId, canComment }: { projectRequestId: number; canComment: boolean }) {
    const [comments, setComments] = useState<Comment[]>([]);
    const [loading, setLoading] = useState(true);
    const [newComment, setNewComment] = useState('');
    const [posting, setPosting] = useState(false);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    // Read Laravel's XSRF-TOKEN cookie (refreshed on every response) rather than
    // the <meta> tag, which is baked in at initial load and goes stale after
    // Inertia reloads / session-token rotation — the cause of CSRF mismatches.
    const xsrfToken = () =>
        decodeURIComponent(
            document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? ''
        );

    const loadComments = () =>
        fetch(route('comments.index', projectRequestId), {
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        })
            .then(r => r.json())
            .then(data => setComments(data))
            .catch(console.error);

    useEffect(() => {
        setLoading(true);
        loadComments().finally(() => setLoading(false));
    }, [projectRequestId]);

    const postComment = async () => {
        if (!newComment.trim()) return;
        setPosting(true);
        try {
            const res = await fetch(route('comments.store', projectRequestId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ content: newComment.trim() }),
            });
            if (res.ok) {
                const comment = await res.json();
                setComments(prev => [...prev, comment]); // instant feedback
                setNewComment('');
                // The comment may have flipped the request to HOLD server-side.
                // Reload the projectRequest prop for the status badge, then re-sync
                // the comment list from the server once the reload settles (so the
                // re-render can't clobber the freshly-posted comment).
                // reload() keeps scroll + component state automatically.
                router.reload({
                    only: ['projectRequest'],
                    onFinish: () => loadComments(),
                });
            }
        } catch (err) {
            console.error(err);
        } finally {
            setPosting(false);
        }
    };

    const deleteComment = (id: number) => {
        showConfirm('Delete this comment?', async () => {
            try {
                const res = await fetch(route('comments.destroy', id), {
                    method: 'DELETE',
                    headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
                });
                if (res.ok) setComments(prev => prev.filter(c => c.id !== id));
            } catch (err) {
                console.error(err);
            }
        }, { title: 'Delete Comment', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.04)', marginTop: '20px' }}>
            {confirmDialog}
            <div style={{ padding: '18px 28px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '7px' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>
                    Comments{comments.length > 0 && ` (${comments.length})`}
                </span>
            </div>

            <div style={{ padding: '20px 28px' }}>
                {loading ? (
                    <div style={{ textAlign: 'center', padding: '20px 0', color: '#9ca3af', fontSize: '13px' }}>Loading…</div>
                ) : comments.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '20px 0' }}>
                        <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>No comments yet. Be the first!</p>
                    </div>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '18px' }}>
                        {comments.map(c => (
                            <div key={c.id} style={{ background: '#f8fafc', borderRadius: '8px', padding: '12px 14px', border: '1px solid #f0f2f5' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '6px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <div style={{ width: '26px', height: '26px', borderRadius: '7px', background: '#2563eb', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 700, color: '#fff', flexShrink: 0 }}>
                                            {c.author?.slice(0, 2).toUpperCase()}
                                        </div>
                                        <span style={{ fontSize: '13px', fontWeight: 700, color: '#374151' }}>{c.author}</span>
                                        <span style={{ fontSize: '11.5px', color: '#9ca3af' }}>{c.date}</span>
                                    </div>
                                    {canComment && (
                                        <button
                                            onClick={() => deleteComment(c.id)}
                                            title="Delete comment"
                                            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#d1d5db', display: 'flex', padding: '2px', borderRadius: '4px' }}
                                            onMouseEnter={e => (e.currentTarget.style.color = '#ef4444')}
                                            onMouseLeave={e => (e.currentTarget.style.color = '#d1d5db')}
                                        >
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                        </button>
                                    )}
                                </div>
                                <p style={{ fontSize: '13px', color: '#334155', margin: 0, lineHeight: 1.6, paddingLeft: '34px' }}>{c.content}</p>
                            </div>
                        ))}
                    </div>
                )}

                {canComment && (
                    <div>
                        <textarea
                            rows={3}
                            value={newComment}
                            onChange={e => setNewComment(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) postComment(); }}
                            placeholder="Write a comment… (Ctrl+Enter to submit)"
                            style={{ width: '100%', padding: '10px 12px', borderRadius: '8px', border: '1.5px solid #e5e7eb', fontSize: '13px', resize: 'vertical', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', transition: 'border-color 0.15s' }}
                            onFocus={e => (e.target.style.borderColor = '#2563eb')}
                            onBlur={e => (e.target.style.borderColor = '#e5e7eb')}
                        />
                        <button
                            onClick={postComment}
                            disabled={posting || !newComment.trim()}
                            style={{ marginTop: '8px', padding: '9px 20px', borderRadius: '8px', background: posting || !newComment.trim() ? '#93c5fd' : '#2563eb', color: '#fff', border: 'none', fontSize: '13px', fontWeight: 600, cursor: posting || !newComment.trim() ? 'not-allowed' : 'pointer', transition: 'background 0.15s', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            {posting ? 'Posting…' : 'Post Comment'}
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Show Page ──────────────────────────────────────────────────────────────
export default function Show({ projectRequest, feedbacks = [] }: Props) {
    if (!projectRequest) return null;

    const { auth } = usePage().props as unknown as { auth: { user: { id: number; role: string | null } | null } };
    const role = auth?.user?.role ?? null;
    // Technical feedback is an engineer/approver responsibility — department
    // users (requestors) submit and edit requests, they don't review them.
    const canGiveFeedback = role === 'approver' || role === 'assistant_manager' || role === 'admin';

    const [showFeedback, setShowFeedback] = useState(false);
    const [editingFeedback, setEditingFeedback] = useState<FeedbackEntry | null>(null);

    const attachments = projectRequest.attachments ?? [];
    const getExt = (filename: string) => filename.split('.').pop()?.toLowerCase() ?? '';

    // Categorize by the type chosen at upload. Fall back to extension for legacy
    // rows that predate the `type` column (a PDF report used to be mis-bucketed
    // as a drawing because both share the .pdf extension).
    const bucketOf = (a: Attachment): 'picture' | 'drawing' | 'report' | 'other' => {
        if (a.type === 'picture' || a.type === 'drawing' || a.type === 'report' || a.type === 'other') return a.type;
        const ext = getExt(a.filename);
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'picture';
        if (['pdf','dwg'].includes(ext)) return 'drawing';
        if (['doc','docx'].includes(ext)) return 'report';
        return 'other';
    };

    const pictures = attachments.filter(a => bucketOf(a) === 'picture');
    const drawings = attachments.filter(a => bucketOf(a) === 'drawing');
    const reports  = attachments.filter(a => bucketOf(a) === 'report');
    const others   = attachments.filter(a => bucketOf(a) === 'other');

    const handleApprove = () => router.patch(route('requests.update', projectRequest.id), { status: 'approved' });
    const handleReject  = () => router.patch(route('requests.update', projectRequest.id), { status: 'rejected' });
    const handleResume  = () => router.patch(route('requests.update', projectRequest.id), { status: 'resume' });
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const handleDelete  = () => {
        showConfirm('Are you sure you want to cancel this request?', () => {
            router.delete(route('requests.destroy', projectRequest.id));
        }, { title: 'Cancel Request', confirmLabel: 'Yes, Cancel', variant: 'warning' });
    };

    const openNewFeedback  = () => { setEditingFeedback(null); setShowFeedback(true); };
    const openEditFeedback = (f: FeedbackEntry) => { setEditingFeedback(f); setShowFeedback(true); };
    const closeFeedback    = () => { setShowFeedback(false); setEditingFeedback(null); };
    const deleteFeedback   = (f: FeedbackEntry) => {
        showConfirm('Delete this technical feedback?', () => {
            router.delete(route('requests.feedback.destroy', f.id), { preserveScroll: true });
        }, { title: 'Delete Feedback', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`View Request — ${projectRequest.title}`} />

            {confirmDialog}
            {showFeedback && (
                <FeedbackModal
                    projectRequestId={projectRequest.id}
                    feedback={editingFeedback}
                    onClose={closeFeedback}
                />
            )}

            {/* Breadcrumb + actions */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px', flexWrap: 'wrap', gap: '10px' }}>
                <div style={{ fontSize: '13px', color: '#9ca3af', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <Link href={route('requests.index')} style={{ color: '#9ca3af', textDecoration: 'none' }}>Requests</Link>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span style={{ color: '#374151', fontWeight: 600 }}>View</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span style={{ color: '#0f172a', fontWeight: 700 }}>{projectRequest.request_no ?? `#${projectRequest.id}`}</span>
                </div>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <button onClick={() => window.print()} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', fontWeight: 500, color: '#374151', cursor: 'pointer' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print
                    </button>
                    {canGiveFeedback && (
                        <button onClick={openNewFeedback} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#0891b2', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Add Feedback
                        </button>
                    )}
                    {projectRequest.status === 'hold' && projectRequest.can.decide && (
                        <button onClick={handleResume} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#f59e0b', color: '#fff', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Resume (Take off Hold)
                        </button>
                    )}
                    {projectRequest.status === 'approved' && !projectRequest.project && projectRequest.can.canCreateProject && (
                        <Link href={`${route('projects.create')}?request_id=${projectRequest.id}`} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#16a34a', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Create Project
                        </Link>
                    )}
                    {projectRequest.can.update && (
                        <Link href={route('requests.edit', projectRequest.id)} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', textDecoration: 'none', fontSize: '12.5px', fontWeight: 600 }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit Request
                        </Link>
                    )}
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
                                <InfoLabel>Project No</InfoLabel>
                                <InfoValue>
                                    {projectRequest.project?.project_no
                                        ? <Link href={route('projects.show', projectRequest.project.id)} style={{ fontFamily: 'monospace', fontSize: '13.5px', color: '#2563eb', textDecoration: 'none', fontWeight: 600 }}>{projectRequest.project.project_no}</Link>
                                        : <span style={{ color: '#9ca3af', fontStyle: 'italic' }}>Not created yet</span>
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
                    {projectRequest.can.delete ? (
                        <button onClick={handleDelete} style={{ display: 'flex', alignItems: 'center', gap: '6px', padding: '7px 16px', borderRadius: '7px', border: '1px solid #fca5a5', background: '#fff', fontSize: '12.5px', fontWeight: 600, color: '#dc2626', cursor: 'pointer' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                            Cancel Request
                        </button>
                    ) : <span />}

                    {projectRequest.can.decide && (
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

            <FeedbackSection feedbacks={feedbacks} onEdit={openEditFeedback} onDelete={deleteFeedback} />

            <CommentsSection projectRequestId={projectRequest.id} canComment={role === 'approver' || role === 'admin'} />
        </AuthenticatedLayout>
    );
}
