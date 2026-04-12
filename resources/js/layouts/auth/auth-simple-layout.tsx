import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div style={{
            minHeight: '100vh',
            display: 'flex',
            fontFamily: "'Inter', sans-serif",
            background: '#f8fafc',
        }}>
            {/* ── Left panel — branding ── */}
            <div style={{
                width: '420px',
                flexShrink: 0,
                background: 'linear-gradient(160deg, #1d4ed8 0%, #1e40af 50%, #1e3a8a 100%)',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
                padding: '48px 44px',
                position: 'relative',
                overflow: 'hidden',
            }}>
                {/* Decorative circles */}
                <div style={{
                    position: 'absolute', width: '320px', height: '320px',
                    borderRadius: '50%', border: '1px solid rgba(255,255,255,0.08)',
                    top: '-80px', right: '-80px',
                }} />
                <div style={{
                    position: 'absolute', width: '220px', height: '220px',
                    borderRadius: '50%', border: '1px solid rgba(255,255,255,0.06)',
                    bottom: '120px', left: '-60px',
                }} />
                <div style={{
                    position: 'absolute', width: '140px', height: '140px',
                    borderRadius: '50%', background: 'rgba(255,255,255,0.04)',
                    bottom: '-40px', right: '40px',
                }} />

                {/* Logo */}
                <div style={{ position: 'relative', zIndex: 1 }}>
                    <Link href={route('home')} style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{
                            width: '40px', height: '40px', borderRadius: '10px',
                            background: 'rgba(255,255,255,0.15)',
                            backdropFilter: 'blur(8px)',
                            border: '1px solid rgba(255,255,255,0.2)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                            </svg>
                        </div>
                        <div>
                            <div style={{ fontSize: '15px', fontWeight: 800, color: '#fff', letterSpacing: '-0.3px', lineHeight: 1.1 }}>CPMS</div>
                            <div style={{ fontSize: '10px', color: 'rgba(255,255,255,0.55)', fontWeight: 500, letterSpacing: '1px' }}>PROJECT MGMT</div>
                        </div>
                    </Link>
                </div>

                {/* Center copy */}
                <div style={{ position: 'relative', zIndex: 1 }}>
                    <div style={{
                        display: 'inline-block', padding: '5px 12px', borderRadius: '99px',
                        background: 'rgba(255,255,255,0.12)', border: '1px solid rgba(255,255,255,0.18)',
                        fontSize: '11px', fontWeight: 600, color: 'rgba(255,255,255,0.8)',
                        letterSpacing: '0.5px', marginBottom: '18px',
                    }}>
                        Construction Project Management
                    </div>
                    <h2 style={{
                        fontSize: '28px', fontWeight: 800, color: '#fff',
                        lineHeight: 1.25, margin: '0 0 14px', letterSpacing: '-0.5px',
                    }}>
                        Manage projects<br />with confidence.
                    </h2>
                    <p style={{ fontSize: '13.5px', color: 'rgba(255,255,255,0.6)', lineHeight: 1.7, margin: 0 }}>
                        Track requests, budgets, timelines, and<br />teams — all in one place.
                    </p>

                    {/* Feature pills */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '28px' }}>
                        {[
                            { icon: '📋', text: 'Project Request Tracking' },
                            { icon: '📊', text: 'Real-time Status Dashboard' },
                            { icon: '👥', text: 'Team & PM Assignment' },
                        ].map(f => (
                            <div key={f.text} style={{
                                display: 'flex', alignItems: 'center', gap: '10px',
                                padding: '9px 14px', borderRadius: '8px',
                                background: 'rgba(255,255,255,0.08)',
                                border: '1px solid rgba(255,255,255,0.1)',
                            }}>
                                <span style={{ fontSize: '15px' }}>{f.icon}</span>
                                <span style={{ fontSize: '12.5px', color: 'rgba(255,255,255,0.8)', fontWeight: 500 }}>{f.text}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Footer note */}
                <div style={{ position: 'relative', zIndex: 1, fontSize: '11px', color: 'rgba(255,255,255,0.35)' }}>
                    © {new Date().getFullYear()} CPMS · All rights reserved
                </div>
            </div>

            {/* ── Right panel — form ── */}
            <div style={{
                flex: 1,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '40px 24px',
            }}>
                <div style={{ width: '100%', maxWidth: '380px' }}>

                    {/* Header */}
                    <div style={{ marginBottom: '32px' }}>
                        <h1 style={{
                            fontSize: '22px', fontWeight: 800, color: '#0f172a',
                            margin: '0 0 6px', letterSpacing: '-0.4px',
                        }}>
                            {title}
                        </h1>
                        <p style={{ fontSize: '13px', color: '#9ca3af', margin: 0 }}>
                            {description}
                        </p>
                    </div>

                    {/* Slot for login form */}
                    {children}

                    {/* Bottom note */}
                    <p style={{ marginTop: '32px', fontSize: '11.5px', color: '#cbd5e1', textAlign: 'center' }}>
                        By signing in you agree to our{' '}
                        <a href="#" style={{ color: '#93c5fd', textDecoration: 'none' }}>Terms</a>
                        {' '}and{' '}
                        <a href="#" style={{ color: '#93c5fd', textDecoration: 'none' }}>Privacy Policy</a>.
                    </p>
                </div>
            </div>
        </div>
    );
}