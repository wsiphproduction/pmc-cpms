import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

// ── Reusable styled field wrapper ──────────────────────────────────────────
function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
            <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>
                {label}
            </label>
            {children}
            {error && (
                <span style={{ fontSize: '11.5px', color: '#dc2626', display: 'flex', alignItems: 'center', gap: '4px' }}>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    {error}
                </span>
            )}
        </div>
    );
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [focusedField, setFocusedField] = useState<string | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const inputStyle = (field: string, hasError?: boolean): React.CSSProperties => ({
        width: '100%',
        padding: '10px 14px',
        borderRadius: '8px',
        border: `1.5px solid ${hasError ? '#fca5a5' : focusedField === field ? '#2563eb' : '#e5e7eb'}`,
        fontSize: '13.5px',
        outline: 'none',
        fontFamily: "'Inter', sans-serif",
        color: '#111827',
        background: hasError ? '#fff8f8' : '#fff',
        boxSizing: 'border-box' as const,
        transition: 'border-color 0.15s, box-shadow 0.15s',
        boxShadow: focusedField === field && !hasError ? '0 0 0 3px rgba(37,99,235,0.1)' : 'none',
    });

    return (
        <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
            <Head title="Log in" />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
            `}</style>

            {/* Status message (e.g. password reset success) */}
            {status && (
                <div style={{
                    padding: '10px 14px', borderRadius: '8px', marginBottom: '4px',
                    background: '#f0fdf4', border: '1px solid #bbf7d0',
                    fontSize: '12.5px', color: '#16a34a',
                    display: 'flex', alignItems: 'center', gap: '8px',
                }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    {status}
                </div>
            )}

            <form
                onSubmit={submit}
                style={{ display: 'flex', flexDirection: 'column', gap: '18px', fontFamily: "'Inter', sans-serif" }}
            >
                {/* Email */}
                <Field label="Email address" error={errors.email}>
                    <div style={{ position: 'relative' }}>
                        <div style={{
                            position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
                            color: focusedField === 'email' ? '#2563eb' : '#9ca3af', pointerEvents: 'none',
                            transition: 'color 0.15s',
                        }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            onFocus={() => setFocusedField('email')}
                            onBlur={() => setFocusedField(null)}
                            placeholder="you@example.com"
                            style={{ ...inputStyle('email', !!errors.email), paddingLeft: '38px' }}
                        />
                    </div>
                </Field>

                {/* Password */}
                <Field label="Password" error={errors.password}>
                    <div style={{ position: 'relative' }}>
                        <div style={{
                            position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
                            color: focusedField === 'password' ? '#2563eb' : '#9ca3af', pointerEvents: 'none',
                            transition: 'color 0.15s',
                        }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            onFocus={() => setFocusedField('password')}
                            onBlur={() => setFocusedField(null)}
                            placeholder="Enter your password"
                            style={{ ...inputStyle('password', !!errors.password), paddingLeft: '38px', paddingRight: '42px' }}
                        />
                        {/* Show/hide toggle */}
                        <button
                            type="button"
                            onClick={() => setShowPassword(p => !p)}
                            tabIndex={-1}
                            style={{
                                position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
                                background: 'none', border: 'none', cursor: 'pointer', padding: '2px',
                                color: '#9ca3af', display: 'flex', alignItems: 'center',
                            }}
                        >
                            {showPassword ? (
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            ) : (
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            )}
                        </button>
                    </div>
                    {/* Forgot password link */}
                    {canResetPassword && (
                        <div style={{ textAlign: 'right', marginTop: '2px' }}>
                            <TextLink href={route('password.request')} tabIndex={5}>
                                <span style={{ fontSize: '12px', color: '#2563eb', fontWeight: 500 }}>
                                    Forgot password?
                                </span>
                            </TextLink>
                        </div>
                    )}
                </Field>

                {/* Remember me */}
                <label style={{
                    display: 'flex', alignItems: 'center', gap: '9px',
                    cursor: 'pointer', fontSize: '13px', color: '#374151', fontWeight: 500,
                }}>
                    <input
                        id="remember"
                        type="checkbox"
                        tabIndex={3}
                        checked={data.remember}
                        onChange={e => setData('remember', e.target.checked)}
                        style={{ width: '15px', height: '15px', accentColor: '#2563eb', cursor: 'pointer' }}
                    />
                    Keep me signed in
                </label>

                {/* Submit */}
                <button
                    type="submit"
                    tabIndex={4}
                    disabled={processing}
                    style={{
                        width: '100%',
                        padding: '11px',
                        borderRadius: '8px',
                        border: 'none',
                        background: processing ? '#93c5fd' : '#2563eb',
                        color: '#fff',
                        fontSize: '13.5px',
                        fontWeight: 700,
                        cursor: processing ? 'not-allowed' : 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '8px',
                        transition: 'background 0.15s, transform 0.1s',
                        marginTop: '4px',
                        fontFamily: "'Inter', sans-serif",
                        letterSpacing: '0.1px',
                    }}
                    onMouseEnter={e => { if (!processing) (e.currentTarget.style.background = '#1d4ed8'); }}
                    onMouseLeave={e => { if (!processing) (e.currentTarget.style.background = '#2563eb'); }}
                    onMouseDown={e => { if (!processing) (e.currentTarget.style.transform = 'scale(0.99)'); }}
                    onMouseUp={e => { (e.currentTarget.style.transform = 'scale(1)'); }}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>

                {/* Sign up link */}
                <p style={{ textAlign: 'center', fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>
                    Don't have an account?{' '}
                    <TextLink href={route('register')} tabIndex={5}>
                        <span style={{ color: '#2563eb', fontWeight: 600 }}>Sign up</span>
                    </TextLink>
                </p>
            </form>
        </AuthLayout>
    );
}