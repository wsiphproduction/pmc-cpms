import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import AuthLayout from '@/layouts/auth-layout';

interface ResetPasswordProps {
    token: string;
    email: string;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

// ── Eye toggle ─────────────────────────────────────────────────────────────
function EyeToggle({ show, onToggle }: { show: boolean; onToggle: () => void }) {
    return (
        <button
            type="button"
            tabIndex={-1}
            onClick={onToggle}
            style={{
                position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
                background: 'none', border: 'none', cursor: 'pointer',
                color: '#9ca3af', display: 'flex', alignItems: 'center', padding: '2px',
            }}
        >
            {show ? (
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
    );
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const [focused, setFocused] = useState<string | null>(null);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const inputStyle = (field: string, hasError?: boolean, extraPadding?: boolean): React.CSSProperties => ({
        width: '100%',
        padding: `10px ${extraPadding ? '42px' : '14px'} 10px 38px`,
        borderRadius: '8px',
        border: `1.5px solid ${hasError ? '#fca5a5' : focused === field ? '#2563eb' : '#e5e7eb'}`,
        fontSize: '13.5px', outline: 'none',
        fontFamily: "'Inter', sans-serif",
        color: '#111827',
        background: hasError ? '#fff8f8' : '#fff',
        boxSizing: 'border-box' as const,
        transition: 'border-color 0.15s, box-shadow 0.15s',
        boxShadow: focused === field && !hasError ? '0 0 0 3px rgba(37,99,235,0.1)' : 'none',
    });

    const FieldIcon = ({ field, children }: { field: string; children: React.ReactNode }) => (
        <div style={{
            position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
            color: focused === field ? '#2563eb' : '#9ca3af',
            pointerEvents: 'none', transition: 'color 0.15s',
        }}>
            {children}
        </div>
    );

    return (
        <AuthLayout title="Reset password" description="Please enter your new password below">
            <Head title="Reset password" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', fontFamily: "'Inter', sans-serif" }}>

                {/* Info banner */}
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
                    Choose a strong password with at least 8 characters, including numbers and symbols.
                </div>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>

                    {/* Email — read only */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>
                            Email Address
                        </label>
                        <div style={{ position: 'relative' }}>
                            <div style={{
                                position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
                                color: '#cbd5e1', pointerEvents: 'none',
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
                                autoComplete="email"
                                value={data.email}
                                readOnly
                                onChange={e => setData('email', e.target.value)}
                                style={{
                                    width: '100%', padding: '10px 14px 10px 38px',
                                    borderRadius: '8px', border: '1.5px solid #e5e7eb',
                                    fontSize: '13.5px', outline: 'none',
                                    fontFamily: "'Inter', sans-serif",
                                    color: '#6b7280', background: '#f8fafc',
                                    boxSizing: 'border-box' as const,
                                    cursor: 'not-allowed',
                                }}
                            />
                            {/* Lock badge */}
                            <div style={{
                                position: 'absolute', right: '10px', top: '50%', transform: 'translateY(-50%)',
                                display: 'flex', alignItems: 'center', gap: '4px',
                                fontSize: '10.5px', fontWeight: 600, color: '#9ca3af',
                            }}>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                locked
                            </div>
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    {/* New Password */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>
                            New Password
                        </label>
                        <div style={{ position: 'relative' }}>
                            <FieldIcon field="password">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </FieldIcon>
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                onFocus={() => setFocused('password')}
                                onBlur={() => setFocused(null)}
                                placeholder="Enter new password"
                                style={inputStyle('password', !!errors.password, true)}
                            />
                            <EyeToggle show={showPassword} onToggle={() => setShowPassword(p => !p)} />
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    {/* Confirm Password */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                        <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>
                            Confirm Password
                        </label>
                        <div style={{ position: 'relative' }}>
                            <FieldIcon field="confirm">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                            </FieldIcon>
                            <input
                                id="password_confirmation"
                                type={showConfirm ? 'text' : 'password'}
                                name="password_confirmation"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                onFocus={() => setFocused('confirm')}
                                onBlur={() => setFocused(null)}
                                placeholder="Re-enter new password"
                                style={inputStyle('confirm', !!errors.password_confirmation, true)}
                            />
                            <EyeToggle show={showConfirm} onToggle={() => setShowConfirm(p => !p)} />
                        </div>
                        <InputError message={errors.password_confirmation} />
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
                            marginTop: '4px',
                        }}
                        onMouseEnter={e => { if (!processing) (e.currentTarget.style.background = '#1d4ed8'); }}
                        onMouseLeave={e => { if (!processing) (e.currentTarget.style.background = '#2563eb'); }}
                        onMouseDown={e => { (e.currentTarget.style.transform = 'scale(0.99)'); }}
                        onMouseUp={e => { (e.currentTarget.style.transform = 'scale(1)'); }}
                    >
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {processing ? 'Resetting password…' : 'Reset password'}
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}