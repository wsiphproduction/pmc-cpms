import { ReactNode, useEffect, useState } from 'react';
import Topbar from './_Topbar';

interface Props {
    children: ReactNode;
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
            <Topbar isMobile={isMobile} navCollapsed={navCollapsed} />

            <main className="print-full-width" style={{
                flex: 1,
                minWidth: 0,
                padding: isMobile ? '18px 14px' : '24px 28px',
            }}>
                {children}
            </main>
        </div>
    );
}
