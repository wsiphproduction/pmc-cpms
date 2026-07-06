import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

interface Signatory {
    key:   string;
    label: string;
    name:  string;
}

interface Props {
    projectCompletionKpi: number;
    signatories: Signatory[];
}

export default function SystemSettingsIndex({ projectCompletionKpi, signatories = [] }: Props) {
    const { props } = usePage<{ flash?: { success?: string }; errors?: Record<string, string> }>();
    const flash  = props.flash;
    const errors = props.errors ?? {};

    const [kpi, setKpi] = useState(String(projectCompletionKpi));
    const [submitting, setSubmitting] = useState(false);

    const [sigNames, setSigNames] = useState<Record<string, string>>(
        () => Object.fromEntries(signatories.map(s => [s.key, s.name])),
    );
    const [sigSubmitting, setSigSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.patch(route('system-settings.update'), { project_completion_kpi: kpi }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    const handleSignatorySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSigSubmitting(true);
        router.patch(route('system-settings.update'), sigNames, {
            preserveScroll: true,
            onFinish: () => setSigSubmitting(false),
        });
    };

    const inputStyle: React.CSSProperties = {
        width: '120px', padding: '8px 12px', borderRadius: '8px',
        border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
        fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box',
    };

    return (
        <AuthenticatedLayout>
            <Head title="System Settings" />

            {flash?.success && (
                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#15803d', fontSize: '13px', fontWeight: 500 }}>
                    {flash.success}
                </div>
            )}

            <div style={{ marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    System Settings
                </h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>
                    Configure application-wide business rules and KPI thresholds.
                </p>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', maxWidth: '640px' }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '18px' }}>
                    Project Completion KPI
                </div>

                <form onSubmit={handleSubmit}>
                    <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151', display: 'block', marginBottom: '6px' }}>
                        On-Time Completion Threshold (%)
                    </label>
                    <p style={{ fontSize: '12px', color: '#6b7280', margin: '0 0 10px', lineHeight: 1.6, maxWidth: '480px' }}>
                        A project's Project Health badge shows <strong>On-Time</strong> when its actual completion is at
                        least this percentage of the completion expected from elapsed time, and <strong>Delayed</strong> otherwise.
                        For example, at 80% and the timeline half elapsed, a project needs at least 40% actual completion to stay On-Time.
                    </p>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <input
                            type="number"
                            min={1}
                            max={100}
                            value={kpi}
                            onChange={e => setKpi(e.target.value)}
                            style={{ ...inputStyle, borderColor: errors.project_completion_kpi ? '#dc2626' : undefined }}
                        />
                        <span style={{ fontSize: '13px', color: '#6b7280' }}>%</span>
                    </div>
                    {errors.project_completion_kpi && (
                        <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{errors.project_completion_kpi}</p>
                    )}

                    <div style={{ marginTop: '20px', borderTop: '1px solid #f3f4f6', paddingTop: '18px' }}>
                        <button
                            type="submit"
                            disabled={submitting}
                            style={{
                                padding: '9px 22px', borderRadius: '8px', border: 'none',
                                background: submitting ? '#93c5fd' : '#2563eb', color: '#fff',
                                fontSize: '13px', fontWeight: 700, cursor: submitting ? 'not-allowed' : 'pointer',
                            }}
                        >
                            {submitting ? 'Saving…' : 'Save Settings'}
                        </button>
                    </div>
                </form>
            </div>

            {/* Report Signatories */}
            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', maxWidth: '640px', marginTop: '18px' }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '18px' }}>
                    Report Signatories
                </div>

                <p style={{ fontSize: '12px', color: '#6b7280', margin: '0 0 18px', lineHeight: 1.6, maxWidth: '520px' }}>
                    Assign the person who signs off for each role. These names are printed as the
                    signatories on generated reports.
                </p>

                <form onSubmit={handleSignatorySubmit}>
                    {signatories.map(sig => (
                        <div key={sig.key} style={{ marginBottom: '16px' }}>
                            <label style={{ fontSize: '12.5px', fontWeight: 600, color: '#374151', display: 'block', marginBottom: '6px' }}>
                                {sig.label}
                            </label>
                            <input
                                type="text"
                                value={sigNames[sig.key] ?? ''}
                                onChange={e => setSigNames(prev => ({ ...prev, [sig.key]: e.target.value }))}
                                placeholder="Enter signatory name…"
                                style={{ ...inputStyle, width: '100%', maxWidth: '420px', borderColor: errors[sig.key] ? '#dc2626' : undefined }}
                            />
                            {errors[sig.key] && (
                                <p style={{ fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' }}>{errors[sig.key]}</p>
                            )}
                        </div>
                    ))}

                    <div style={{ marginTop: '20px', borderTop: '1px solid #f3f4f6', paddingTop: '18px' }}>
                        <button
                            type="submit"
                            disabled={sigSubmitting}
                            style={{
                                padding: '9px 22px', borderRadius: '8px', border: 'none',
                                background: sigSubmitting ? '#93c5fd' : '#2563eb', color: '#fff',
                                fontSize: '13px', fontWeight: 700, cursor: sigSubmitting ? 'not-allowed' : 'pointer',
                            }}
                        >
                            {sigSubmitting ? 'Saving…' : 'Save Signatories'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
