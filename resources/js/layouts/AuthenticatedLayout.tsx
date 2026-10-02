import { router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import TourProvider from '@/components/tour/TourProvider';
import Topbar from './_Topbar';

interface Props {
    children: ReactNode;
}

interface ImpersonationProps {
    auth?: {
        user?: { name: string; email: string } | null;
        impersonator?: { name: string; email: string } | null;
    };
    [key: string]: unknown;
}

// Shown above the topbar on every page while the IT account is signed in as
// someone else, so it is never mistaken for a normal session.
function ImpersonationBanner() {
    const { auth } = usePage<ImpersonationProps>().props;
    const [leaving, setLeaving] = useState(false);

    if (!auth?.impersonator || !auth.user) return null;

    const leave = () => {
        setLeaving(true);
        router.post(route('impersonate.leave'), {}, { onFinish: () => setLeaving(false) });
    };

    return (
        <div className="print-hide" style={{
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '12px', flexWrap: 'wrap',
            padding: '8px 16px', background: '#7c3aed', color: '#fff', fontSize: '12.5px',
        }}>
            <span>
                You are impersonating <strong>{auth.user.name}</strong> ({auth.user.email}).
                Everything you do is done as this user.
            </span>
            <button
                type="button"
                onClick={leave}
                disabled={leaving}
                style={{
                    padding: '4px 12px', borderRadius: '6px', border: '1px solid rgba(255,255,255,0.6)',
                    background: '#fff', color: '#5b21b6', fontSize: '12px', fontWeight: 700,
                    cursor: leaving ? 'default' : 'pointer', opacity: leaving ? 0.7 : 1,
                }}
            >
                {leaving ? 'Exiting…' : 'Exit impersonation'}
            </button>
        </div>
    );
}

export default function AuthenticatedLayout({ children }: Props) {
    const [isMobile, setIsMobile] = useState(false);
    // The horizontal nav needs more room than the page padding does, so it
    // folds into a toggle earlier than the mobile breakpoint.
    const [navCollapsed, setNavCollapsed] = useState(false);

    useEffect(() => {
        const syncViewport = () => {
            setIsMobile(window.innerWidth < 768);
            setNavCollapsed(window.innerWidth < 1280);
        };

        syncViewport();
        window.addEventListener('resize', syncViewport);

        return () => window.removeEventListener('resize', syncViewport);
    }, []);

    return (
        <div style={{
            display: 'flex', flexDirection: 'column', minHeight: '100vh',
            background: '#f8fafc',
            fontFamily: "'Inter', sans-serif",
        }}>
            <ImpersonationBanner />
            <Topbar isMobile={isMobile} navCollapsed={navCollapsed} />

            <main className="print-full-width" style={{
                flex: 1,
                minWidth: 0,
                padding: isMobile ? '18px 14px' : '24px 28px',
            }}>
                {/* Guided tours for the department-side roles; a no-op for everyone else. */}
                <TourProvider>{children}</TourProvider>
            </main>
        </div>
    );
}
