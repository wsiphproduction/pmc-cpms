import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { HubProject, HubShell } from './Common';

interface Candidate {
    id: number;
    label: string;
}

interface Step {
    sequence: number;
    role: string;
    role_label: string;
    /** Who holds the step when the project names nobody. */
    default_names: string[];
    /** Who the project names instead, or null for the default. */
    user_id: number | null;
    candidates: Candidate[];
}

const ACCENT = '#0d9488';

type Picks = Record<string, string>;

const picksFrom = (steps: Step[]): Picks =>
    Object.fromEntries(steps.map(s => [s.role, s.user_id ? String(s.user_id) : '']));

export default function NtpSignatoriesHub({ project, steps, canEdit = true }: { project: HubProject; steps: Step[]; canEdit?: boolean }) {
    const errors = (usePage().props as { errors?: Record<string, string> }).errors ?? {};
    const [picks, setPicks] = useState<Picks>(() => picksFrom(steps));
    const [saving, setSaving] = useState(false);

    // Back in step with the server after a save or a reload.
    useEffect(() => setPicks(picksFrom(steps)), [steps]);

    const saved = picksFrom(steps);
    const dirty = steps.some(s => picks[s.role] !== saved[s.role]);
    const anyNamed = steps.some(s => picks[s.role] !== '');

    const save = (next: Picks) => {
        setSaving(true);
        router.put(
            route('hub.signatories.update', project.id),
            { signatories: Object.fromEntries(Object.entries(next).map(([role, id]) => [role, id ? Number(id) : null])) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const resetAll = () => {
        if (!confirm('Put every step back to its default signatory?')) return;
        const cleared = Object.fromEntries(steps.map(s => [s.role, '']));
        setPicks(cleared);
        save(cleared);
    };

    const select: React.CSSProperties = {
        width: '100%', padding: '7px 10px', border: '1.5px solid #e5e7eb', borderRadius: '7px',
        fontSize: '12.5px', background: '#fff', color: '#0f172a', outline: 'none',
    };
    const th: React.CSSProperties = {
        padding: '10px 14px', fontSize: '10.5px', fontWeight: 800, color: '#64748b',
        textTransform: 'uppercase', letterSpacing: '0.5px', textAlign: 'left', background: '#f8fafc',
        borderBottom: '1px solid #e5e7eb',
    };
    const td: React.CSSProperties = { padding: '12px 14px', borderBottom: '1px solid #f1f5f9', fontSize: '13px', verticalAlign: 'top' };

    return (
        <HubShell>
            <div style={{ borderBottom: `2px solid ${ACCENT}`, paddingBottom: '14px', marginBottom: '20px' }}>
                <h3 style={{ color: ACCENT, margin: '0 0 4px', fontSize: '15px', fontWeight: 800 }}>NTP SIGNATORIES</h3>
                <span style={{ color: '#64748b', fontSize: '12px' }}>
                    Who signs each step of this project's Notices to Proceed, in order. Each step starts with its default signatory; pick someone else to hand them that step on this project.
                </span>
            </div>

            <div style={{ border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr>
                            <th style={{ ...th, width: '44px' }}>#</th>
                            <th style={{ ...th, width: '190px' }}>Step</th>
                            <th style={th}>Default signatory</th>
                            <th style={{ ...th, width: '40%' }}>Signs on this project</th>
                        </tr>
                    </thead>
                    <tbody>
                        {steps.map(step => {
                            const pick = picks[step.role] ?? '';
                            const named = step.candidates.find(c => String(c.id) === pick);
                            const error = errors[`signatories.${step.role}`];

                            return (
                                <tr key={step.role}>
                                    <td style={{ ...td, fontWeight: 800, color: ACCENT }}>{step.sequence}</td>
                                    <td style={{ ...td, fontWeight: 700, color: '#0f172a' }}>{step.role_label}</td>
                                    <td style={{ ...td, color: '#475569' }}>
                                        {step.default_names.length
                                            ? step.default_names.join(', ')
                                            : (
                                                <span style={{ color: '#b45309' }}>
                                                    {step.role === 'requestor'
                                                        ? `None — no Department User account for ${project.dept_owner || 'this project\'s department'}.`
                                                        : `None — no user account has the ${step.role_label} role yet.`}
                                                    <span style={{ display: 'block', fontSize: '11px', color: '#94a3b8', marginTop: '2px' }}>
                                                        Pick someone here, or the NTP will wait at this step.
                                                    </span>
                                                </span>
                                            )}
                                        {step.default_names.length > 1 && (
                                            <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '2px' }}>Any of them can sign.</div>
                                        )}
                                    </td>
                                    <td style={td}>
                                        {canEdit ? (
                                            <select
                                                aria-label={`Signatory for ${step.role_label}`}
                                                value={pick}
                                                onChange={e => setPicks(p => ({ ...p, [step.role]: e.target.value }))}
                                                style={{ ...select, borderColor: pick ? ACCENT : '#e5e7eb' }}
                                            >
                                                <option value="">Default{step.default_names.length ? ` — ${step.default_names.join(', ')}` : ''}</option>
                                                {step.candidates.map(c => <option key={c.id} value={c.id}>{c.label}</option>)}
                                            </select>
                                        ) : (
                                            <span style={{ fontWeight: 600, color: '#0f172a' }}>
                                                {named?.label ?? (step.default_names.join(', ') || '—')}
                                            </span>
                                        )}
                                        {pick && (
                                            <span style={{ display: 'inline-block', marginTop: '6px', fontSize: '10.5px', fontWeight: 700, padding: '2px 8px', borderRadius: '4px', background: '#ccfbf1', color: '#115e59', textTransform: 'uppercase', letterSpacing: '0.3px' }}>
                                                Adjusted
                                            </span>
                                        )}
                                        {error && <div style={{ marginTop: '4px', color: '#dc2626', fontSize: '12px' }}>{error}</div>}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <p style={{ margin: '12px 0 0', fontSize: '12px', color: '#64748b' }}>
                The person picked is the one notified and the only one who can approve that step on this project, and their name prints on the NTP. Changes apply to every step not yet signed, including NTPs already in progress.
            </p>

            {canEdit && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '16px' }}>
                    <button
                        type="button"
                        onClick={resetAll}
                        disabled={saving || !anyNamed}
                        style={{ padding: '8px 16px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', color: '#374151', fontSize: '13px', fontWeight: 600, cursor: saving || !anyNamed ? 'not-allowed' : 'pointer', opacity: anyNamed ? 1 : 0.5 }}
                    >
                        Reset to defaults
                    </button>
                    <button
                        type="button"
                        onClick={() => save(picks)}
                        disabled={saving || !dirty}
                        style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: ACCENT, color: '#fff', fontSize: '13px', fontWeight: 700, cursor: saving || !dirty ? 'not-allowed' : 'pointer', opacity: dirty ? 1 : 0.5 }}
                    >
                        {saving ? 'Saving…' : 'Save Signatories'}
                    </button>
                </div>
            )}
        </HubShell>
    );
}
