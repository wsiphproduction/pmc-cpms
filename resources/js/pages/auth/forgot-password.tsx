import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const [focused, setFocused] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Forgot password"
            description="Enter your email to receive a password reset link"
        >
            <Head title="Forgot password" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', fontFamily: "'Inter', sans-serif" }}>

                {/* Success status */}
                {status && (
                    <div style={{
                        padding: '11px 14px', borderRadius: '8px',
                        background: '#f0fdf4', border: '1px solid #bbf7d0',
                        fontSize: '12.5px', color: '#16a34a',
                        display: 'flex', alignItems: 'flex-start', gap: '8px',
                    }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        {status}
                    </div>
                )}

                {/* Info hint */}
                <div style={{
                    padding: '11px 14px', borderRadius: '8px',
                    background: '#eff6ff', border: '1px solid #bfdbfe',
                    fontSize: '12.5px', color: '#1d4ed8',
                    display: 'flex', alignItems: 'flex-start', gap: '8px',
                }}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    We'll send a secure reset link to your email. Check your spam folder if you don't see it.
                </div>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>

                    {/* Email field */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label
                            htmlFor="email"
                            style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}
                        >
                            Email Address
                        </label>
                        <div style={{ position: 'relative' }}>
                            <div style={{
                                position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
                                color: focused ? '#2563eb' : '#9ca3af',
                                pointerEvents: 'none', transition: 'color 0.15s',
                            }}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </div>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="off"
                                autoFocus
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                onFocus={() => setFocused(true)}
                                onBlur={() => setFocused(false)}
                                placeholder="you@example.com"
                                style={{
                                    width: '100%',
                                    padding: '10px 14px 10px 38px',
                                    borderRadius: '8px',
                                    border: `1.5px solid ${errors.email ? '#fca5a5' : focused ? '#2563eb' : '#e5e7eb'}`,
                                    fontSize: '13.5px', outline: 'none',
                                    fontFamily: "'Inter', sans-serif",
                                    color: '#111827',
                                    background: errors.email ? '#fff8f8' : '#fff',
                                    boxSizing: 'border-box',
                                    transition: 'border-color 0.15s, box-shadow 0.15s',
                                    boxShadow: focused && !errors.email ? '0 0 0 3px rgba(37,99,235,0.1)' : 'none',
                                }}
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    {/* Submit */}
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
                        {processing ? 'Sending link…' : 'Email password reset link'}
                    </button>
                </form>

                {/* Back to login */}
                <p style={{ textAlign: 'center', fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>
                    Remember your password?{' '}
                    <TextLink href={route('login')}>
                        <span style={{ color: '#2563eb', fontWeight: 600 }}>Back to sign in</span>
                    </TextLink>
                </p>
            </div>
        </AuthLayout>
    );
}