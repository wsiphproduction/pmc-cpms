import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

// ── Field wrapper ──────────────────────────────────────────────────────────
function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
            <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151' }}>
                {label}
            </label>
            {children}
            {error && <InputError message={error} />}
        </div>
    );
}

// ── Icon ───────────────────────────────────────────────────────────────────
function FieldIcon({ focused, children }: { focused: boolean; children: React.ReactNode }) {
    return (
        <div style={{
            position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
            color: focused ? '#2563eb' : '#9ca3af', pointerEvents: 'none', transition: 'color 0.15s',
        }}>
            {children}
        </div>
    );
}

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const [focused, setFocused] = useState<string | null>(null);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const inputStyle = (field: string, hasError?: boolean): React.CSSProperties => ({
        width: '100%',
        padding: '10px 14px 10px 38px',
        borderRadius: '8px',
        border: `1.5px solid ${hasError ? '#fca5a5' : focused === field ? '#2563eb' : '#e5e7eb'}`,
        fontSize: '13.5px',
        outline: 'none',
        fontFamily: "'Inter', sans-serif",
        color: '#111827',
        background: hasError ? '#fff8f8' : '#fff',
        boxSizing: 'border-box' as const,
        transition: 'border-color 0.15s, box-shadow 0.15s',
        boxShadow: focused === field && !hasError ? '0 0 0 3px rgba(37,99,235,0.1)' : 'none',
    });

    const EyeToggle = ({ show, onToggle }: { show: boolean; onToggle: () => void }) => (
        <button
            type="button"
            onClick={onToggle}
            tabIndex={-1}
            style={{
                position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
                background: 'none', border: 'none', cursor: 'pointer', padding: '2px',
                color: '#9ca3af', display: 'flex', alignItems: 'center',
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

    return (
        <AuthLayout title="Create an account" description="Enter your details below to create your account">
            <Head title="Register" />

            <form
                onSubmit={submit}
                style={{ display: 'flex', flexDirection: 'column', gap: '16px', fontFamily: "'Inter', sans-serif" }}
            >
                {/* Name */}
                <Field label="Full Name" error={errors.name}>
                    <div style={{ position: 'relative' }}>
                        <FieldIcon focused={focused === 'name'}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </FieldIcon>
                        <input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            onFocus={() => setFocused('name')}
                            onBlur={() => setFocused(null)}
                            disabled={processing}
                            placeholder="Your full name"
                            style={inputStyle('name', !!errors.name)}
                        />
                    </div>
                </Field>

                {/* Email */}
                <Field label="Email Address" error={errors.email}>
                    <div style={{ position: 'relative' }}>
                        <FieldIcon focused={focused === 'email'}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </FieldIcon>
                        <input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            onFocus={() => setFocused('email')}
                            onBlur={() => setFocused(null)}
                            disabled={processing}
                            placeholder="you@example.com"
                            style={inputStyle('email', !!errors.email)}
                        />
                    </div>
                </Field>

                {/* Password */}
                <Field label="Password" error={errors.password}>
                    <div style={{ position: 'relative' }}>
                        <FieldIcon focused={focused === 'password'}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </FieldIcon>
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            onFocus={() => setFocused('password')}
                            onBlur={() => setFocused(null)}
                            disabled={processing}
                            placeholder="Create a strong password"
                            style={{ ...inputStyle('password', !!errors.password), paddingRight: '42px' }}
                        />
                        <EyeToggle show={showPassword} onToggle={() => setShowPassword(p => !p)} />
                    </div>
                </Field>

                {/* Confirm Password */}
                <Field label="Confirm Password" error={errors.password_confirmation}>
                    <div style={{ position: 'relative' }}>
                        <FieldIcon focused={focused === 'confirm'}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </FieldIcon>
                        <input
                            id="password_confirmation"
                            type={showConfirm ? 'text' : 'password'}
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={e => setData('password_confirmation', e.target.value)}
                            onFocus={() => setFocused('confirm')}
                            onBlur={() => setFocused(null)}
                            disabled={processing}
                            placeholder="Re-enter your password"
                            style={{ ...inputStyle('confirm', !!errors.password_confirmation), paddingRight: '42px' }}
                        />
                        <EyeToggle show={showConfirm} onToggle={() => setShowConfirm(p => !p)} />
                    </div>
                </Field>

                {/* Submit */}
                <button
                    type="submit"
                    tabIndex={5}
                    disabled={processing}
                    style={{
                        width: '100%', padding: '11px', borderRadius: '8px', border: 'none',
                        background: processing ? '#93c5fd' : '#2563eb', color: '#fff',
                        fontSize: '13.5px', fontWeight: 700, cursor: processing ? 'not-allowed' : 'pointer',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
                        transition: 'background 0.15s, transform 0.1s', marginTop: '4px',
                        fontFamily: "'Inter', sans-serif", letterSpacing: '0.1px',
                    }}
                    onMouseEnter={e => { if (!processing) (e.currentTarget.style.background = '#1d4ed8'); }}
                    onMouseLeave={e => { if (!processing) (e.currentTarget.style.background = '#2563eb'); }}
                    onMouseDown={e => { (e.currentTarget.style.transform = 'scale(0.99)'); }}
                    onMouseUp={e => { (e.currentTarget.style.transform = 'scale(1)'); }}
                >
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {processing ? 'Creating account…' : 'Create account'}
                </button>

                {/* Login link */}
                <p style={{ textAlign: 'center', fontSize: '12.5px', color: '#9ca3af', margin: 0 }}>
                    Already have an account?{' '}
                    <TextLink href={route('login')} tabIndex={6}>
                        <span style={{ color: '#2563eb', fontWeight: 600 }}>Sign in</span>
                    </TextLink>
                </p>
            </form>
        </AuthLayout>
    );
}