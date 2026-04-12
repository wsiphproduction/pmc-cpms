import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface NavChild {
    label: string;
    href: string;
}

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    children?: NavChild[];
}

const navItems: NavItem[] = [
    {
        label: 'Dashboard',
        href: route('dashboard'),
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
        ),
    },
    {
        label: 'Projects',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
        ),
        children: [
            { label: 'Requests', href: route('requests.index') },
            { label: 'View All', href: '#' },
            { label: 'Add New', href: '#' },
        ],
    },
    {
        label: 'Master Data',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
        ),
    },
    {
        label: 'Man-hours Form',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        ),
    },
    {
        label: 'Documents',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
        ),
    },
    {
        label: 'Reports',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6" y1="20" x2="6" y2="14"/>
            </svg>
        ),
    },
    {
        label: 'Users',
        href: '#',
        icon: (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        ),
    },
];

export default function Sidebar() {
    const { url } = usePage();

    // Auto-open Projects submenu if on a child route
    const defaultOpen = navItems
        .filter(item => item.children && item.children.some(c => url.startsWith(c.href)))
        .map(item => item.label);

    const [openMenus, setOpenMenus] = useState<string[]>(
        defaultOpen.length ? defaultOpen : ['Projects']
    );

    const toggle = (label: string) =>
        setOpenMenus(prev =>
            prev.includes(label) ? prev.filter(l => l !== label) : [...prev, label]
        );

    const isActive = (href: string) => url === href || url.startsWith(href + '/');

    const navLinkStyle = (active: boolean): React.CSSProperties => ({
        display: 'flex', alignItems: 'center', gap: '9px',
        padding: '8px 12px', borderRadius: '7px', marginBottom: '1px',
        fontSize: '13px', fontWeight: active ? 600 : 500, textDecoration: 'none',
        color: active ? '#2563eb' : '#374151',
        background: active ? '#eff6ff' : 'transparent',
        transition: 'all 0.12s',
    });

    return (
        <aside style={{
            width: '205px', height: '100vh', position: 'fixed', top: 0, left: 0,
            background: '#fff', borderRight: '1px solid #e5e7eb',
            display: 'flex', flexDirection: 'column', zIndex: 100,
            fontFamily: "'Inter', sans-serif",
        }}>
            {/* Logo */}
            <div style={{ padding: '16px 14px 14px', borderBottom: '1px solid #f3f4f6' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
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
                    <div style={{ fontSize: '13px', fontWeight: 800, color: '#111827', letterSpacing: '-0.2px' }}>
                        CPMS - PMD
                    </div>
                </div>
            </div>

            {/* Nav */}
            <nav style={{ flex: 1, overflowY: 'auto', padding: '8px 8px' }}>
                {navItems.map(item => (
                    <div key={item.label}>
                        {item.children ? (
                            <>
                                <button
                                    onClick={() => toggle(item.label)}
                                    style={{
                                        ...navLinkStyle(isActive(item.href)) as React.CSSProperties,
                                        border: 'none', width: '100%', textAlign: 'left',
                                        cursor: 'pointer', fontFamily: 'inherit',
                                    }}
                                >
                                    <span style={{ color: isActive(item.href) ? '#2563eb' : '#9ca3af', flexShrink: 0 }}>
                                        {item.icon}
                                    </span>
                                    <span style={{ flex: 1 }}>{item.label}</span>
                                    <svg
                                        width="12" height="12" viewBox="0 0 24 24" fill="none"
                                        stroke="#9ca3af" strokeWidth="2"
                                        style={{
                                            transform: openMenus.includes(item.label) ? 'rotate(180deg)' : 'none',
                                            transition: 'transform 0.2s',
                                        }}
                                    >
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>

                                {openMenus.includes(item.label) && (
                                    <div style={{ paddingBottom: '4px' }}>
                                        {item.children.map(child => (
                                            <Link
                                                key={child.href}
                                                href={child.href}
                                                style={{
                                                    display: 'block',
                                                    padding: '7px 12px 7px 37px',
                                                    fontSize: '12.5px',
                                                    fontWeight: url === child.href ? 600 : 400,
                                                    color: url === child.href ? '#2563eb' : '#6b7280',
                                                    background: url === child.href ? '#eff6ff' : 'transparent',
                                                    borderRadius: '6px', textDecoration: 'none',
                                                    marginBottom: '1px', transition: 'all 0.12s',
                                                }}
                                            >
                                                {child.label}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </>
                        ) : (
                            <Link href={item.href} style={navLinkStyle(isActive(item.href))}>
                                <span style={{ color: isActive(item.href) ? '#2563eb' : '#9ca3af', flexShrink: 0 }}>
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        )}
                    </div>
                ))}
            </nav>

            {/* Logout */}
            <div style={{ padding: '8px', borderTop: '1px solid #f3f4f6' }}>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    style={{
                        display: 'flex', alignItems: 'center', gap: '9px',
                        padding: '8px 12px', borderRadius: '7px', width: '100%',
                        fontSize: '13px', fontWeight: 500, color: '#dc2626',
                        background: 'none', border: 'none', cursor: 'pointer',
                        textAlign: 'left', fontFamily: 'inherit',
                    }}
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Logout
                </Link>
            </div>
        </aside>
    );
}