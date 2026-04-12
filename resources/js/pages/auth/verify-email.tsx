import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    const linkSent = status === 'verification-link-sent';

    return (
        <AuthLayout
            title="Verify your email"
            description="Please verify your email address to continue."
        >
            <Head title="Email verification" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', fontFamily: "'Inter', sans-serif" }}>

                {/* Email illustration */}
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px', padding: '24px 0 8px' }}>
                    <div style={{
                        width: '64px', height: '64px', borderRadius: '16px',
                        background: 'linear-gradient(135deg, #eff6ff, #dbeafe)',
                        border: '1px solid #bfdbfe',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="1.5">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                        <p style={{ fontSize: '13px', color: '#6b7280', margin: 0, lineHeight: 1.6, maxWidth: '300px' }}>
                            We sent a verification link to your email address. Click the link in the email to verify your account.
                        </p>
                    </div>
                </div>

                {/* Success banner */}
                {linkSent && (
                    <div style={{
                        padding: '11px 14px', borderRadius: '8px',
                        background: '#f0fdf4', border: '1px solid #bbf7d0',
                        fontSize: '12.5px', color: '#16a34a',
                        display: 'flex', alignItems: 'flex-start', gap: '8px',
                    }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        A new verification link has been sent to your email address.
                    </div>
                )}

                {/* Tips */}
                <div style={{
                    padding: '12px 14px', borderRadius: '8px',
                    background: '#fffbeb', border: '1px solid #fde68a',
                    fontSize: '12px', color: '#92400e',
                    display: 'flex', alignItems: 'flex-start', gap: '8px',
                }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Didn't receive it? Check your spam or junk folder, or resend below.
                </div>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>

                    {/* Resend button */}
                    <button
                        type="submit"
                        disabled={processing}
                        style={{
                            width: '100%', padding: '11px', borderRadius: '8px', border: 'none',
                            background: processing ? '#93c5fd' : '#2563eb', color: '#fff',
                            fontSize: '13.5px', fontWeight: 700,
                            cursor: processing ? 'not-allowed' : 'pointer',
                            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
                            transition: 'background 0.15s, transform 0.1s',
                            fontFamily: "'Inter', sans-serif", letterSpacing: '0.1px',
                        }}
                        onMouseEnter={e => { if (!processing) (e.currentTarget.style.background = '#1d4ed8'); }}
                        onMouseLeave={e => { if (!processing) (e.currentTarget.style.background = '#2563eb'); }}
                        onMouseDown={e => { (e.currentTarget.style.transform = 'scale(0.99)'); }}
                        onMouseUp={e => { (e.currentTarget.style.transform = 'scale(1)'); }}
                    >
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {processing ? 'Sending…' : 'Resend verification email'}
                    </button>

                    {/* Logout */}
                    <div style={{ textAlign: 'center' }}>
                        <TextLink href={route('logout')} method="post">
                            <span style={{ fontSize: '12.5px', color: '#9ca3af', fontWeight: 500 }}>
                                Not your account?{' '}
                                <span style={{ color: '#dc2626', fontWeight: 600 }}>Log out</span>
                            </span>
                        </TextLink>
                    </div>
                </form>
            </div>
        </AuthLayout>
    );
}