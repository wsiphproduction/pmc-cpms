import { ReactNode } from 'react';
import Topbar from './_Topbar';
import Sidebar from './_Sidebar';

interface Props {
    children: ReactNode;
}

export default function AuthenticatedLayout({ children }: Props) {
    return (
        <div style={{
            display: 'flex', minHeight: '100vh',
            background: '#f8fafc',
            fontFamily: "'Inter', sans-serif",
        }}>
            <Sidebar />
            <div style={{ marginLeft: '205px', flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                <Topbar />
                <main style={{ flex: 1, padding: '24px 28px', overflowY: 'auto' }}>
                    {children}
                </main>
            </div>
        </div>
    );
}