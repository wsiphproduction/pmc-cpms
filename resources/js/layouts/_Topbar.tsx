import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface NotificationRow {
    id: number;
    message: string;
    link: string | null;
    is_read: boolean;
    created_at: string;
}

interface PageProps {
    auth: { user: { name: string; email: string } };
    notifications: NotificationRow[];
    unread_notifications_count: number;
    [key: string]: unknown;
}

interface TopbarProps {
    isMobile: boolean;
    sidebarCollapsed: boolean;
    onToggleSidebar: () => void;
}

export default function Topbar({ isMobile, sidebarCollapsed, onToggleSidebar }: TopbarProps) {
    const { auth, notifications, unread_notifications_count } = usePage<PageProps>().props;
    const [notifOpen, setNotifOpen] = useState(false);
    const [menuOpen, setMenuOpen]   = useState(false);
    const notifRef = useRef<HTMLDivElement>(null);
    const menuRef  = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (notifRef.current && !notifRef.current.contains(e.target as Node)) setNotifOpen(false);
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const markAllRead = () => router.patch(route('notifications.read-all'), {}, { preserveScroll: true });

    const initials = auth?.user?.name
        ? auth.user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2)
        : 'U';

    return (
        <header className="print-hide" style={{
            height: '52px',
            background: '#fff',
            borderBottom: '1px solid #e5e7eb',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: isMobile ? '0 14px' : '0 24px',
            position: 'sticky',
            top: 0,
            zIndex: 50,
            fontFamily: "'Inter', sans-serif",
        }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', minWidth: 0 }}>
                <button
                    type="button"
                    onClick={onToggleSidebar}
                    aria-label={isMobile ? 'Open sidebar' : sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    title={isMobile ? 'Open sidebar' : sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    style={{
                        width: '32px',
                        height: '32px',
                        borderRadius: '7px',
                        border: '1px solid #e5e7eb',
                        background: '#fff',
                        color: '#475569',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                        flexShrink: 0,
                    }}
                >
                    {isMobile || sidebarCollapsed ? (
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                    ) : (
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
                    )}
                </button>
                <div style={{
                    fontSize: '14px',
                    fontWeight: 600,
                    color: '#374151',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                }}>
                    {isMobile ? 'CPMS' : 'Construction Project Management System'}
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <span style={{ display: isMobile ? 'none' : 'inline', fontSize: '13.5px', color: '#6b7280' }}>
                    Welcome, <span style={{ fontWeight: 600, color: '#111827' }}>
                        {auth?.user?.name?.split(' ')[0] ?? 'User'}
                    </span>
                </span>

                {/* Notifications */}
                <div ref={notifRef} style={{ position: 'relative' }}>
                    <button
                        type="button"
                        onClick={() => setNotifOpen(prev => !prev)}
                        aria-label="Notifications"
                        title="Notifications"
                        style={{
                            width: '32px', height: '32px', borderRadius: '7px', border: '1px solid #e5e7eb',
                            background: '#fff', color: '#475569', display: 'inline-flex', alignItems: 'center',
                            justifyContent: 'center', cursor: 'pointer', position: 'relative', flexShrink: 0,
                        }}
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        {unread_notifications_count > 0 && (
                            <span style={{
                                position: 'absolute', top: '-4px', right: '-4px', minWidth: '16px', height: '16px',
                                borderRadius: '999px', background: '#dc2626', color: '#fff', fontSize: '10px',
                                fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 3px',
                            }}>
                                {unread_notifications_count > 9 ? '9+' : unread_notifications_count}
                            </span>
                        )}
                    </button>
                    {notifOpen && (
                        <div style={{
                            position: 'absolute', top: '42px', right: 0, width: '340px', maxHeight: '420px',
                            overflowY: 'auto', background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                            boxShadow: '0 20px 40px rgba(15,23,42,0.15)', zIndex: 200,
                        }}>
                            <div style={{ padding: '12px 16px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a' }}>Notifications</span>
                                {notifications.some(n => !n.is_read) && (
                                    <button type="button" onClick={markAllRead} style={{ fontSize: '11.5px', fontWeight: 600, color: '#2563eb', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                                        Mark all read
                                    </button>
                                )}
                            </div>
                            {notifications.length === 0 ? (
                                <div style={{ padding: '24px 16px', textAlign: 'center', color: '#9ca3af', fontSize: '12.5px' }}>No notifications yet.</div>
                            ) : (
                                notifications.map(n => (
                                    <Link
                                        key={n.id}
                                        href={route('notifications.open', n.id)}
                                        onClick={() => setNotifOpen(false)}
                                        style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', padding: '11px 16px', borderBottom: '1px solid #f8fafc', textDecoration: 'none', color: 'inherit' }}
                                    >
                                        <span style={{ width: '7px', height: '7px', borderRadius: '50%', background: n.is_read ? 'transparent' : '#2563eb', flexShrink: 0, marginTop: '5px' }} />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontSize: '12px', fontWeight: n.is_read ? 500 : 700, color: n.is_read ? '#6b7280' : '#0f172a' }}>{n.message}</div>
                                            <div style={{ fontSize: '10.5px', color: '#9ca3af', marginTop: '2px' }}>{n.created_at}</div>
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    )}
                </div>

                {/* Account menu */}
                <div ref={menuRef} style={{ position: 'relative' }}>
                    <div
                        onClick={() => setMenuOpen(prev => !prev)}
                        style={{
                            width: '32px',
                            height: '32px',
                            borderRadius: '8px',
                            background: '#2563eb',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '12px',
                            fontWeight: 700,
                            color: '#fff',
                            flexShrink: 0,
                            cursor: 'pointer',
                        }}>
                        {initials}
                    </div>
                    {menuOpen && (
                        <div style={{
                            position: 'absolute', top: '42px', right: 0, width: '200px',
                            background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                            boxShadow: '0 20px 40px rgba(15,23,42,0.15)', zIndex: 200, overflow: 'hidden',
                        }}>
                            <div style={{ padding: '12px 16px', borderBottom: '1px solid #f3f4f6' }}>
                                <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{auth?.user?.name}</div>
                                <div style={{ fontSize: '11.5px', color: '#9ca3af', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{auth?.user?.email}</div>
                            </div>
                            <Link
                                href={route('account.edit')}
                                onClick={() => setMenuOpen(false)}
                                style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 16px', fontSize: '12.5px', color: '#374151', textDecoration: 'none' }}
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                My Account
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
