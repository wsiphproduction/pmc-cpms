import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { type SharedData } from '@/types';

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '9px 12px', borderRadius: '8px',
    border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
    fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box',
};

const labelStyle: React.CSSProperties = {
    fontSize: '12.5px', fontWeight: 600, color: '#374151', display: 'block', marginBottom: '6px',
};

const buttonStyle = (disabled: boolean): React.CSSProperties => ({
    padding: '9px 22px', borderRadius: '8px', border: 'none',
    background: disabled ? '#93c5fd' : '#2563eb', color: '#fff',
    fontSize: '13px', fontWeight: 700, cursor: disabled ? 'not-allowed' : 'pointer',
});

export default function AccountIndex({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;

    const profileForm = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profileForm.patch(route('account.update-profile'));
    };

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        passwordForm.put(route('account.update-password'), {
            onSuccess: () => passwordForm.reset(),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="My Account" />

            <div style={{ marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    My Account
                </h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>
                    Update your account details and password.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#15803d', fontSize: '13px', fontWeight: 500, maxWidth: '640px' }}>
                    A new verification link has been sent to your email address.
                </div>
            )}

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', maxWidth: '640px', marginBottom: '20px' }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '18px' }}>
                    Account Details
                </div>

                <form onSubmit={submitProfile}>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={labelStyle} htmlFor="name">Name</label>
                        <input
                            id="name"
                            style={{ ...inputStyle, borderColor: profileForm.errors.name ? '#dc2626' : undefined }}
                            value={profileForm.data.name}
                            onChange={e => profileForm.setData('name', e.target.value)}
                            autoComplete="name"
                            required
                        />
                        {profileForm.errors.name && <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{profileForm.errors.name}</p>}
                    </div>

                    <div style={{ marginBottom: '16px' }}>
                        <label style={labelStyle} htmlFor="email">Email Address</label>
                        <input
                            id="email"
                            type="email"
                            style={{ ...inputStyle, borderColor: profileForm.errors.email ? '#dc2626' : undefined }}
                            value={profileForm.data.email}
                            onChange={e => profileForm.setData('email', e.target.value)}
                            autoComplete="username"
                            required
                        />
                        {profileForm.errors.email && <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{profileForm.errors.email}</p>}

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <p style={{ fontSize: '11.5px', color: '#6b7280', margin: '8px 0 0' }}>
                                Your email address is unverified.{' '}
                                <Link href={route('verification.send')} method="post" as="button" style={{ color: '#2563eb', fontWeight: 600, background: 'none', border: 'none', cursor: 'pointer', padding: 0, fontSize: '11.5px' }}>
                                    Click here to re-send the verification email.
                                </Link>
                            </p>
                        )}
                    </div>

                    <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: '18px', display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <button type="submit" disabled={profileForm.processing} style={buttonStyle(profileForm.processing)}>
                            {profileForm.processing ? 'Saving…' : 'Save Changes'}
                        </button>
                        {profileForm.recentlySuccessful && <span style={{ fontSize: '12.5px', color: '#15803d', fontWeight: 600 }}>Saved</span>}
                    </div>
                </form>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', maxWidth: '640px' }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '18px' }}>
                    Change Password
                </div>

                <form onSubmit={submitPassword}>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={labelStyle} htmlFor="current_password">Current Password</label>
                        <input
                            id="current_password"
                            type="password"
                            style={{ ...inputStyle, borderColor: passwordForm.errors.current_password ? '#dc2626' : undefined }}
                            value={passwordForm.data.current_password}
                            onChange={e => passwordForm.setData('current_password', e.target.value)}
                            autoComplete="current-password"
                            required
                        />
                        {passwordForm.errors.current_password && <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{passwordForm.errors.current_password}</p>}
                    </div>

                    <div style={{ marginBottom: '16px' }}>
                        <label style={labelStyle} htmlFor="password">New Password</label>
                        <input
                            id="password"
                            type="password"
                            style={{ ...inputStyle, borderColor: passwordForm.errors.password ? '#dc2626' : undefined }}
                            value={passwordForm.data.password}
                            onChange={e => passwordForm.setData('password', e.target.value)}
                            autoComplete="new-password"
                            required
                        />
                        {passwordForm.errors.password && <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{passwordForm.errors.password}</p>}
                    </div>

                    <div style={{ marginBottom: '16px' }}>
                        <label style={labelStyle} htmlFor="password_confirmation">Confirm New Password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            style={inputStyle}
                            value={passwordForm.data.password_confirmation}
                            onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                            required
                        />
                    </div>

                    <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: '18px', display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <button type="submit" disabled={passwordForm.processing} style={buttonStyle(passwordForm.processing)}>
                            {passwordForm.processing ? 'Saving…' : 'Update Password'}
                        </button>
                        {passwordForm.recentlySuccessful && <span style={{ fontSize: '12.5px', color: '#15803d', fontWeight: 600 }}>Saved</span>}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
