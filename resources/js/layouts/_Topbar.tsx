import { usePage } from '@inertiajs/react';

interface PageProps {
    auth: { user: { name: string; email: string } };
    [key: string]: unknown;
}

interface TopbarProps {
    isMobile: boolean;
    sidebarCollapsed: boolean;
    onToggleSidebar: () => void;
}

export default function Topbar({ isMobile, sidebarCollapsed, onToggleSidebar }: TopbarProps) {
    const { auth } = usePage<PageProps>().props;

    const initials = auth?.user?.name
        ? auth.user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2)
        : 'U';

    return (
        <header style={{
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
                <div style={{
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
            </div>
        </header>
    );
}
