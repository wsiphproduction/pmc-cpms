import { usePage } from '@inertiajs/react';

interface PageProps {
    auth: { user: { name: string; email: string } };
    [key: string]: unknown;
}

export default function Topbar() {
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
            padding: '0 24px',
            position: 'sticky',
            top: 0,
            zIndex: 50,
            fontFamily: "'Inter', sans-serif",
        }}>
            {/* Left — app title */}
            <div style={{ fontSize: '14px', fontWeight: 600, color: '#374151' }}>
                Construction Project Management System
            </div>

            {/* Right — welcome + avatar */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <span style={{ fontSize: '13.5px', color: '#6b7280' }}>
                    Welcome, <span style={{ fontWeight: 600, color: '#111827' }}>
                        {auth?.user?.name?.split(' ')[0] ?? 'User'}
                    </span>
                </span>
                <div style={{
                    width: '32px', height: '32px', borderRadius: '8px',
                    background: '#2563eb',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '12px', fontWeight: 700, color: '#fff', flexShrink: 0,
                    cursor: 'pointer',
                }}>
                    {initials}
                </div>
            </div>
        </header>
    );
}