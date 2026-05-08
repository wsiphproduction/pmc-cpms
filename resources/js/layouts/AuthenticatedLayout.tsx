import { ReactNode, useEffect, useState } from 'react';
import Topbar from './_Topbar';
import Sidebar from './_Sidebar';

interface Props {
    children: ReactNode;
}

export default function AuthenticatedLayout({ children }: Props) {
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const syncViewport = () => {
            const mobile = window.innerWidth < 768;
            setIsMobile(mobile);
            if (!mobile) {
                setMobileSidebarOpen(false);
            }
        };

        syncViewport();
        window.addEventListener('resize', syncViewport);

        return () => window.removeEventListener('resize', syncViewport);
    }, []);

    const sidebarWidth = sidebarCollapsed ? 72 : 205;

    return (
        <div style={{
            display: 'flex', minHeight: '100vh',
            background: '#f8fafc',
            fontFamily: "'Inter', sans-serif",
        }}>
            {isMobile && mobileSidebarOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    onClick={() => setMobileSidebarOpen(false)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 90,
                        border: 'none',
                        background: 'rgba(15,23,42,0.42)',
                        cursor: 'pointer',
                    }}
                />
            )}

            <Sidebar
                collapsed={!isMobile && sidebarCollapsed}
                mobileOpen={mobileSidebarOpen}
                isMobile={isMobile}
                onNavigate={() => isMobile && setMobileSidebarOpen(false)}
            />

            <div style={{
                marginLeft: isMobile ? 0 : `${sidebarWidth}px`,
                flex: 1,
                display: 'flex',
                flexDirection: 'column',
                minWidth: 0,
                transition: 'margin-left 0.18s ease',
            }}>
                <Topbar
                    isMobile={isMobile}
                    sidebarCollapsed={sidebarCollapsed}
                    onToggleSidebar={() => {
                        if (isMobile) {
                            setMobileSidebarOpen(true);
                        } else {
                            setSidebarCollapsed(prev => !prev);
                        }
                    }}
                />
                <main style={{ flex: 1, padding: isMobile ? '18px 14px' : '24px 28px', overflowY: 'auto' }}>
                    {children}
                </main>
            </div>
        </div>
    );
}
