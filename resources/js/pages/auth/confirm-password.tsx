import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });
    const [focused, setFocused] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Confirm your password"
            description="This is a secure area. Please confirm your password before continuing."
        >
            <Head title="Confirm password" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', fontFamily: "'Inter', sans-serif" }}>

                {/* Security notice */}
                <div style={{
                    padding: '12px 14px', borderRadius: '8px',
                    background: '#fffbeb', border: '1px solid #fde68a',
                    fontSize: '12.5px', color: '#92400e',
                    display: 'flex', alignItems: 'flex-start', gap: '9px',
                }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <span>
                        For your security, please re-enter your password to access this area.
                    </span>
                </div>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>

                    {/* Password field */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label
                            htmlFor="password"
                            style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}
                        >
                            Password
                        </label>
                        <div style={{ position: 'relative' }}>
                            {/* Lock icon */}
                            <div style={{
                                position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
                                color: focused ? '#2563eb' : '#9ca3af',
                                pointerEvents: 'none', transition: 'color 0.15s',
                            }}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>

                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                autoComplete="current-password"
                                autoFocus
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                onFocus={() => setFocused(true)}
                                onBlur={() => setFocused(false)}
                                placeholder="Enter your password"
                                style={{
                                    width: '100%',
                                    padding: '10px 42px 10px 38px',
                                    borderRadius: '8px',
                                    border: `1.5px solid ${errors.password ? '#fca5a5' : focused ? '#2563eb' : '#e5e7eb'}`,
                                    fontSize: '13.5px', outline: 'none',
                                    fontFamily: "'Inter', sans-serif",
                                    color: '#111827',
                                    background: errors.password ? '#fff8f8' : '#fff',
                                    boxSizing: 'border-box' as const,
                                    transition: 'border-color 0.15s, box-shadow 0.15s',
                                    boxShadow: focused && !errors.password ? '0 0 0 3px rgba(37,99,235,0.1)' : 'none',
                                }}
                            />

                            {/* Show / hide toggle */}
                            <button
                                type="button"
                                tabIndex={-1}
                                onClick={() => setShowPassword(p => !p)}
                                style={{
                                    position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
                                    background: 'none', border: 'none', cursor: 'pointer',
                                    color: '#9ca3af', display: 'flex', alignItems: 'center', padding: '2px',
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
                        <InputError message={errors.password} />
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
                        {processing ? 'Confirming…' : 'Confirm password'}
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}