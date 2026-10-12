import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface NotificationRow {
    id: number;
    message: string;
    link: string | null;
    is_read: boolean;
    created_at: string | null;
    created_ago: string | null;
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

interface Props {
    items: Paginated<NotificationRow>;
    filter: 'all' | 'unread';
    total_count: number;
}

// ── Helpers ────────────────────────────────────────────────────────────────
const formatDate = (value?: string | null) => {
    if (!value) return '';
    const d = new Date(value);
    return isNaN(d.getTime()) ? value : d.toLocaleString('en-US', {
        month: 'short', day: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

// Day bucket the list is grouped under: Today, Yesterday, or the date itself.
const dayLabel = (value?: string | null) => {
    if (!value) return 'Earlier';
    const d = new Date(value);
    if (isNaN(d.getTime())) return 'Earlier';
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: '2-digit', year: 'numeric' });
};

function Pagination({ data }: { data: Paginated<unknown> }) {
    if (data.last_page <= 1) return null;
    return (
        <div style={{ padding: '14px 18px', borderTop: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
            <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                Showing <strong style={{ color: '#374151' }}>{data.from}–{data.to}</strong> of <strong style={{ color: '#374151' }}>{data.total}</strong> notifications
            </span>
            <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                {data.links.map((link, i) => {
                    const isDisabled = !link.url;
                    const isActive   = link.active;
                    const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›').replace(/&[^;]+;/g, '');
                    return (
                        <button key={i} type="button" disabled={isDisabled} onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
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
export default function NotificationsIndex({ items, filter, total_count }: Props) {
    const { props } = usePage<{ unread_notifications_count: number }>();
    const unreadCount = props.unread_notifications_count ?? 0;

    const setFilter = (next: 'all' | 'unread') => {
        if (next === filter) return;
        router.get(route('notifications.index'), next === 'unread' ? { filter: 'unread' } : {}, { preserveScroll: true });
    };

    const markRead = (n: NotificationRow) => router.patch(route('notifications.read', n.id), {}, { preserveScroll: true });
    const markAllRead = () => router.patch(route('notifications.read-all'), {}, { preserveScroll: true });

    // Group the current page by day, keeping the server's newest-first order.
    const groups: { label: string; rows: NotificationRow[] }[] = [];
    for (const n of items.data) {
        const label = dayLabel(n.created_at);
        const last = groups[groups.length - 1];
        if (last && last.label === label) last.rows.push(n);
        else groups.push({ label, rows: [n] });
    }

    const tabs: { key: 'all' | 'unread'; label: string; count: number }[] = [
        { key: 'all', label: 'All', count: total_count },
        { key: 'unread', label: 'Unread', count: unreadCount },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />

            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', marginBottom: '16px' }}>
                <div>
                    <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: '0 0 4px' }}>Notifications</h1>
                    <p style={{ fontSize: '13px', color: '#64748b', margin: 0 }}>
                        Everything the system has sent you. Opening one marks it read and takes you to the record.
                    </p>
                </div>
                {unreadCount > 0 && (
                    <button type="button" onClick={markAllRead}
                        style={{ padding: '8px 16px', borderRadius: '7px', border: '1px solid #bfdbfe', background: '#eff6ff', color: '#2563eb', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                        ✓ Mark all as read
                    </button>
                )}
            </div>

            <div style={{ display: 'flex', gap: '6px', marginBottom: '14px', flexWrap: 'wrap' }}>
                {tabs.map(t => {
                    const active = filter === t.key;
                    return (
                        <button key={t.key} type="button" onClick={() => setFilter(t.key)}
                            style={{
                                padding: '7px 14px', borderRadius: '8px', border: '1px solid', cursor: 'pointer',
                                borderColor: active ? '#2563eb' : '#e5e7eb', background: active ? '#2563eb' : '#fff',
                                color: active ? '#fff' : '#374151', fontSize: '12.5px', fontWeight: 700,
                                display: 'inline-flex', alignItems: 'center', gap: '8px',
                            }}>
                            {t.label}
                            <span style={{
                                minWidth: '20px', padding: '1px 6px', borderRadius: '999px', fontSize: '11px', fontWeight: 700,
                                background: active ? 'rgba(255,255,255,0.22)' : '#f1f5f9', color: active ? '#fff' : '#64748b',
                            }}>
                                {t.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)', overflow: 'hidden' }}>
                {items.data.length === 0 ? (
                    <div style={{ padding: '48px 16px', textAlign: 'center' }}>
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.8" style={{ marginBottom: '10px' }}><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <div style={{ fontSize: '13.5px', fontWeight: 700, color: '#334155' }}>
                            {filter === 'unread' ? "You're all caught up." : 'No notifications yet.'}
                        </div>
                        <div style={{ fontSize: '12.5px', color: '#94a3b8', marginTop: '4px' }}>
                            {filter === 'unread' ? 'There are no unread notifications.' : 'Updates on your requests, projects and approvals will appear here.'}
                        </div>
                    </div>
                ) : (
                    groups.map(g => (
                        <div key={g.label}>
                            <div style={{ padding: '8px 18px', background: '#f8fafc', borderBottom: '1px solid #f1f5f9', fontSize: '10.5px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                {g.label}
                            </div>
                            {g.rows.map(n => (
                                <div key={n.id} style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 18px', borderBottom: '1px solid #f1f5f9', background: n.is_read ? '#fff' : '#f8fbff' }}>
                                    <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: n.is_read ? 'transparent' : '#2563eb', flexShrink: 0 }} />
                                    <Link href={route('notifications.open', n.id)} style={{ flex: 1, minWidth: 0, textDecoration: 'none', color: 'inherit' }}>
                                        <div style={{ fontSize: '13px', fontWeight: n.is_read ? 500 : 700, color: n.is_read ? '#475569' : '#0f172a', wordBreak: 'break-word' }}>
                                            {n.message}
                                        </div>
                                        <div style={{ fontSize: '11.5px', color: '#94a3b8', marginTop: '3px' }} title={formatDate(n.created_at)}>
                                            {n.created_ago}{n.created_at ? ` · ${formatDate(n.created_at)}` : ''}
                                        </div>
                                    </Link>
                                    {!n.is_read && (
                                        <button type="button" onClick={() => markRead(n)} title="Mark as read"
                                            style={{ flexShrink: 0, padding: '5px 10px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', color: '#475569', fontSize: '11.5px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                                            Mark read
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    ))
                )}
                <Pagination data={items as Paginated<unknown>} />
            </div>
        </AuthenticatedLayout>
    );
}
