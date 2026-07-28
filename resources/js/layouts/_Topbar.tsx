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
    auth: { user: { name: string; email: string; role?: string } };
    notifications: NotificationRow[];
    unread_notifications_count: number;
    ntp_reviews_count?: number;
    [key: string]: unknown;
}

interface NavChild {
    label: string;
    href: string;
}

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    children?: NavChild[];
    badge?: number;
}

interface TopbarProps {
    isMobile: boolean;
    navCollapsed: boolean;
}

export default function Topbar({ isMobile, navCollapsed }: TopbarProps) {
    const { url, props } = usePage<PageProps>();
    const { auth, notifications, unread_notifications_count } = props;
    const [notifOpen, setNotifOpen] = useState(false);
    const [menuOpen, setMenuOpen]   = useState(false);
    const [navOpen, setNavOpen]     = useState<string | null>(null);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const notifRef = useRef<HTMLDivElement>(null);
    const menuRef  = useRef<HTMLDivElement>(null);
    const navRef   = useRef<HTMLElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (notifRef.current && !notifRef.current.contains(e.target as Node)) setNotifOpen(false);
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
            if (navRef.current && !navRef.current.contains(e.target as Node)) setNavOpen(null);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // The horizontal nav is always visible on desktop, so collapse it back once
    // the viewport grows past the breakpoint.
    useEffect(() => {
        if (!navCollapsed) setMobileNavOpen(false);
    }, [navCollapsed]);

    const markAllRead = () => router.patch(route('notifications.read-all'), {}, { preserveScroll: true });

    const initials = auth?.user?.name
        ? auth.user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2)
        : 'U';

    const role = auth?.user?.role;
    const ntpReviewsCount = props.ntp_reviews_count ?? 0;
    const isRequestor = role === 'requestor';
    const isAdmin = role === 'admin';
    // User management is admin-only.
    const canManageUsers = isAdmin;

    const navItems: NavItem[] = [
        {
            label: 'Dashboard',
            href: route('dashboard'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            ),
        },
        {
            label: 'Project Requests',
            href: route('requests.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="8" y1="13" x2="16" y2="13"/>
                    <line x1="8" y1="17" x2="13" y2="17"/>
                </svg>
            ),
        },
        {
            label: 'Project Management',
            href: route('projects.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
            ),
        },
        // Department users review NTPs submitted by engineers.
        ...(isRequestor ? [{
            label: 'NTP Reviews',
            href: route('ntp-reviews.index'),
            badge: ntpReviewsCount,
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            ),
        }] : []),
        ...(isRequestor ? [] : [{
            label: 'Master Data',
            href: route('master.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
            ),
        }]),
        {
            label: 'Reports',
            href: route('reports.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
            ),
        },
        ...(canManageUsers ? [{
            label: 'Users',
            href: route('users.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            ),
        }] : []),
        ...(isAdmin ? [{
            label: 'Settings',
            href: route('system-settings.index'),
            icon: (
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
            ),
        }] : []),
    ];

    const isActive = (href: string) => {
        if (href === '#') return false;
        try {
            const path = new URL(href).pathname;
            return url === path || url.startsWith(path + '/');
        } catch {
            return url === href || url.startsWith(href + '/');
        }
    };

    // A parent menu counts as active when any of its children is.
    const isItemActive = (item: NavItem) =>
        isActive(item.href) || !!item.children?.some(c => isActive(c.href));

    const closeAll = () => {
        setNavOpen(null);
        setMobileNavOpen(false);
    };

    const navButtonStyle = (active: boolean): React.CSSProperties => ({
        display: 'inline-flex', alignItems: 'center', gap: '7px',
        padding: '7px 10px', borderRadius: '7px',
        fontSize: '13px', fontWeight: active ? 600 : 500,
        color: active ? '#2563eb' : '#374151',
        background: active ? '#eff6ff' : 'transparent',
        border: 'none', cursor: 'pointer', textDecoration: 'none',
        fontFamily: 'inherit', whiteSpace: 'nowrap',
        transition: 'all 0.12s',
    });

    return (
        <header className="print-hide" style={{
            background: '#fff',
            borderBottom: '1px solid #e5e7eb',
            position: 'sticky',
            top: 0,
            zIndex: 100,
            fontFamily: "'Inter', sans-serif",
        }}>
            <div style={{
                height: '56px',
                display: 'flex',
                alignItems: 'center',
                gap: '14px',
                padding: isMobile ? '0 14px' : '0 24px',
            }}>
                {/* Brand */}
                <Link href={route('dashboard')} onClick={closeAll} style={{
                    display: 'flex', alignItems: 'center', gap: '9px',
                    textDecoration: 'none', flexShrink: 0,
                }}>
                    <div style={{
                        width: '32px', height: '32px', borderRadius: '8px',
                        background: '#2563eb', display: 'flex', alignItems: 'center',
                        justifyContent: 'center', flexShrink: 0,
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <div style={{ fontSize: '13px', fontWeight: 800, color: '#111827', letterSpacing: '-0.2px', whiteSpace: 'nowrap' }}>
                        CPMS - PMD
                    </div>
                </Link>

                {/* Desktop nav */}
                {!navCollapsed && (
                    <nav ref={navRef} style={{ display: 'flex', alignItems: 'center', gap: '2px', flex: 1, minWidth: 0 }}>
                        {navItems.map(item => (
                            <div key={item.label} style={{ position: 'relative' }}>
                                {item.children ? (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => setNavOpen(prev => prev === item.label ? null : item.label)}
                                            style={navButtonStyle(isItemActive(item))}
                                        >
                                            <span style={{ color: isItemActive(item) ? '#2563eb' : '#9ca3af', display: 'inline-flex' }}>
                                                {item.icon}
                                            </span>
                                            {item.label}
                                            <svg
                                                width="11" height="11" viewBox="0 0 24 24" fill="none"
                                                stroke="#9ca3af" strokeWidth="2"
                                                style={{
                                                    transform: navOpen === item.label ? 'rotate(180deg)' : 'none',
                                                    transition: 'transform 0.2s',
                                                }}
                                            >
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        </button>
                                        {navOpen === item.label && (
                                            <div style={{
                                                position: 'absolute', top: '40px', left: 0, minWidth: '180px',
                                                background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                                                boxShadow: '0 20px 40px rgba(15,23,42,0.15)', zIndex: 200,
                                                padding: '6px', overflow: 'hidden',
                                            }}>
                                                {item.children.map(child => (
                                                    <Link
                                                        key={child.href}
                                                        href={child.href}
                                                        onClick={closeAll}
                                                        style={{
                                                            display: 'block', padding: '8px 10px', borderRadius: '6px',
                                                            fontSize: '12.5px',
                                                            fontWeight: isActive(child.href) ? 600 : 500,
                                                            color: isActive(child.href) ? '#2563eb' : '#374151',
                                                            background: isActive(child.href) ? '#eff6ff' : 'transparent',
                                                            textDecoration: 'none',
                                                        }}
                                                    >
                                                        {child.label}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <Link
                                        href={item.href}
                                        onClick={closeAll}
                                        style={{
                                            ...navButtonStyle(isActive(item.href)),
                                            // Highlight when there are items needing attention.
                                            ...(item.badge && item.badge > 0 && !isActive(item.href)
                                                ? { background: '#fffbeb', color: '#b45309', fontWeight: 600 }
                                                : {}),
                                        }}
                                    >
                                        <span style={{
                                            color: item.badge && item.badge > 0 && !isActive(item.href)
                                                ? '#d97706'
                                                : (isActive(item.href) ? '#2563eb' : '#9ca3af'),
                                            display: 'inline-flex',
                                        }}>
                                            {item.icon}
                                        </span>
                                        {item.label}
                                        {item.badge && item.badge > 0 ? (
                                            <span style={{
                                                minWidth: '18px', height: '18px', padding: '0 5px', borderRadius: '999px',
                                                background: '#dc2626', color: '#fff', fontSize: '10.5px', fontWeight: 700,
                                                display: 'flex', alignItems: 'center', justifyContent: 'center', boxSizing: 'border-box',
                                            }}>
                                                {item.badge}
                                            </span>
                                        ) : null}
                                    </Link>
                                )}
                            </div>
                        ))}
                    </nav>
                )}

                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginLeft: 'auto' }}>
                    <span style={{ display: isMobile ? 'none' : 'inline', fontSize: '13.5px', color: '#6b7280', whiteSpace: 'nowrap' }}>
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
                                {/* Logout lived in the sidebar before the nav moved up here. */}
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    style={{
                                        display: 'flex', alignItems: 'center', gap: '8px', width: '100%',
                                        padding: '10px 16px', fontSize: '12.5px', color: '#dc2626',
                                        background: 'none', border: 'none', borderTop: '1px solid #f3f4f6',
                                        cursor: 'pointer', textAlign: 'left', fontFamily: 'inherit',
                                    }}
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16 17 21 12 16 7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    Logout
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Nav toggle for narrow viewports */}
                    {navCollapsed && (
                        <button
                            type="button"
                            onClick={() => setMobileNavOpen(prev => !prev)}
                            aria-label={mobileNavOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={mobileNavOpen}
                            title={mobileNavOpen ? 'Close menu' : 'Open menu'}
                            style={{
                                width: '32px', height: '32px', borderRadius: '7px', border: '1px solid #e5e7eb',
                                background: '#fff', color: '#475569', display: 'inline-flex', alignItems: 'center',
                                justifyContent: 'center', cursor: 'pointer', flexShrink: 0,
                            }}
                        >
                            {mobileNavOpen ? (
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            ) : (
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                            )}
                        </button>
                    )}
                </div>
            </div>

            {/* Collapsed nav panel */}
            {navCollapsed && mobileNavOpen && (
                <nav style={{
                    borderTop: '1px solid #f3f4f6',
                    padding: '8px',
                    maxHeight: 'calc(100vh - 56px)',
                    overflowY: 'auto',
                }}>
                    {navItems.map(item => (
                        <div key={item.label}>
                            {item.children ? (
                                <>
                                    <div style={{
                                        display: 'flex', alignItems: 'center', gap: '9px',
                                        padding: '9px 12px', fontSize: '13px', fontWeight: 600, color: '#6b7280',
                                    }}>
                                        <span style={{ color: '#9ca3af', display: 'inline-flex' }}>{item.icon}</span>
                                        {item.label}
                                    </div>
                                    {item.children.map(child => (
                                        <Link
                                            key={child.href}
                                            href={child.href}
                                            onClick={closeAll}
                                            style={{
                                                display: 'block', padding: '8px 12px 8px 36px', borderRadius: '7px',
                                                fontSize: '12.5px',
                                                fontWeight: isActive(child.href) ? 600 : 500,
                                                color: isActive(child.href) ? '#2563eb' : '#374151',
                                                background: isActive(child.href) ? '#eff6ff' : 'transparent',
                                                textDecoration: 'none', marginBottom: '1px',
                                            }}
                                        >
                                            {child.label}
                                        </Link>
                                    ))}
                                </>
                            ) : (
                                <Link
                                    href={item.href}
                                    onClick={closeAll}
                                    style={{
                                        display: 'flex', alignItems: 'center', gap: '9px',
                                        padding: '9px 12px', borderRadius: '7px', marginBottom: '1px',
                                        fontSize: '13px',
                                        fontWeight: isActive(item.href) ? 600 : 500,
                                        color: isActive(item.href) ? '#2563eb' : '#374151',
                                        background: isActive(item.href) ? '#eff6ff' : 'transparent',
                                        textDecoration: 'none',
                                        ...(item.badge && item.badge > 0 && !isActive(item.href)
                                            ? { background: '#fffbeb', color: '#b45309', fontWeight: 600 }
                                            : {}),
                                    }}
                                >
                                    <span style={{
                                        color: item.badge && item.badge > 0 && !isActive(item.href)
                                            ? '#d97706'
                                            : (isActive(item.href) ? '#2563eb' : '#9ca3af'),
                                        display: 'inline-flex',
                                    }}>
                                        {item.icon}
                                    </span>
                                    <span style={{ flex: 1 }}>{item.label}</span>
                                    {item.badge && item.badge > 0 ? (
                                        <span style={{
                                            minWidth: '18px', height: '18px', padding: '0 5px', borderRadius: '999px',
                                            background: '#dc2626', color: '#fff', fontSize: '10.5px', fontWeight: 700,
                                            display: 'flex', alignItems: 'center', justifyContent: 'center', boxSizing: 'border-box',
                                        }}>
                                            {item.badge}
                                        </span>
                                    ) : null}
                                </Link>
                            )}
                        </div>
                    ))}
                </nav>
            )}
        </header>
    );
}
