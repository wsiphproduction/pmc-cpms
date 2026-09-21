import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { useConfirm } from '@/components/useConfirm';

interface OicOption {
    id: number;
    name: string;
    email: string;
}

interface BreakRow {
    id: number;
    oic: { id: number; name: string } | null;
    starts_on: string;
    ends_on: string;
    span: string;
    days: number;
    notes: string | null;
    state: 'active' | 'upcoming' | 'ended';
}

interface Props {
    breaks: BreakRow[];
    oic_options: OicOption[];
    oic_role_label: string;
    today: string;
}

// A type alias rather than an interface so it satisfies Inertia's payload shape.
type FormState = {
    oic_user_id: string;
    starts_on: string;
    ends_on: string;
    notes: string;
};

const emptyForm = (today: string): FormState => ({ oic_user_id: '', starts_on: today, ends_on: today, notes: '' });

const STATE_META: Record<BreakRow['state'], { label: string; bg: string; color: string }> = {
    active:   { label: 'On break now', bg: '#dcfce7', color: '#166534' },
    upcoming: { label: 'Upcoming',     bg: '#dbeafe', color: '#1d4ed8' },
    ended:    { label: 'Ended',        bg: '#f1f5f9', color: '#64748b' },
};

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '9px 12px', borderRadius: '8px',
    border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
    fontFamily: 'inherit', color: '#374151', boxSizing: 'border-box', background: '#fff',
};

const labelStyle: React.CSSProperties = { fontSize: '12.5px', fontWeight: 600, color: '#374151', display: 'block', marginBottom: '6px' };

const errorStyle: React.CSSProperties = { fontSize: '11.5px', color: '#dc2626', margin: '6px 0 0' };

export default function RosterBreakIndex({ breaks, oic_options, oic_role_label, today }: Props) {
    const { props } = usePage<{ flash?: { success?: string; error?: string }; errors?: Record<string, string> }>();
    const flash  = props.flash;
    const errors = props.errors ?? {};
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const [form, setForm] = useState<FormState>(() => emptyForm(today));
    // Null while scheduling a new break; the row being changed otherwise.
    const [editing, setEditing] = useState<BreakRow | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const active   = breaks.find(b => b.state === 'active') ?? null;
    const upcoming = breaks.filter(b => b.state === 'upcoming');
    const ended    = breaks.filter(b => b.state === 'ended');

    const set = (key: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(f => ({ ...f, [key]: e.target.value }));

    const startEdit = (row: BreakRow) => {
        setEditing(row);
        setForm({
            oic_user_id: row.oic ? String(row.oic.id) : '',
            starts_on:   row.starts_on,
            ends_on:     row.ends_on,
            notes:       row.notes ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const cancelEdit = () => {
        setEditing(null);
        setForm(emptyForm(today));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        const options = {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
            onFinish: () => setSubmitting(false),
        };

        if (editing) {
            router.put(route('roster-break.update', editing.id), form, options);
        } else {
            router.post(route('roster-break.store'), form, options);
        }
    };

    const cancelBreak = (row: BreakRow) => {
        showConfirm(
            `Cancel the roster break ${row.span}? ${row.oic?.name ?? 'The OIC'} will stop covering your approvals${row.state === 'active' ? ' immediately' : ''}.`,
            () => router.delete(route('roster-break.destroy', row.id), {
                preserveScroll: true,
                onSuccess: () => { if (editing?.id === row.id) cancelEdit(); },
            }),
            { title: 'Cancel Roster Break', confirmLabel: 'Cancel Break', variant: 'danger' },
        );
    };

    const rowCard = (row: BreakRow) => {
        const meta = STATE_META[row.state];
        const editable = row.state !== 'ended';

        return (
            <div key={row.id} style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap',
                padding: '14px 16px', borderRadius: '10px', border: `1px solid ${editing?.id === row.id ? '#93c5fd' : '#e5e7eb'}`,
                background: editing?.id === row.id ? '#eff6ff' : '#fff',
            }}>
                <div style={{ minWidth: 0, flex: '1 1 260px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', marginBottom: '4px' }}>
                        <span style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a' }}>{row.span}</span>
                        <span style={{ padding: '2px 10px', borderRadius: '999px', background: meta.bg, color: meta.color, fontSize: '11px', fontWeight: 700 }}>{meta.label}</span>
                        <span style={{ fontSize: '11.5px', color: '#94a3b8' }}>{row.days} day{row.days === 1 ? '' : 's'}</span>
                    </div>
                    <div style={{ fontSize: '12.5px', color: '#475569' }}>
                        OIC: <strong style={{ color: '#1e293b' }}>{row.oic?.name ?? '—'}</strong>
                    </div>
                    {row.notes && (
                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px', whiteSpace: 'pre-wrap' }}>{row.notes}</div>
                    )}
                </div>
                {editable && (
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <button type="button" onClick={() => startEdit(row)} style={{ padding: '7px 14px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', color: '#334155', fontSize: '12px', fontWeight: 700, cursor: 'pointer' }}>
                            Edit
                        </button>
                        <button type="button" onClick={() => cancelBreak(row)} style={{ padding: '7px 14px', borderRadius: '7px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', fontSize: '12px', fontWeight: 700, cursor: 'pointer' }}>
                            Cancel
                        </button>
                    </div>
                )}
            </div>
        );
    };

    const sectionHeading = (text: string) => (
        <div style={{ fontSize: '10.5px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '1px', borderBottom: '1px solid #e5e7eb', paddingBottom: '10px', marginBottom: '14px' }}>
            {text}
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Roster Break" />
            {confirmDialog}

            {flash?.success && (
                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#15803d', fontSize: '13px', fontWeight: 500 }}>
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#b91c1c', fontSize: '13px', fontWeight: 500 }}>
                    {flash.error}
                </div>
            )}

            <div style={{ marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>Roster Break</h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0', maxWidth: '640px', lineHeight: 1.6 }}>
                    Schedule the dates you will be away and name a {oic_role_label} as officer-in-charge. For those dates the OIC
                    sees your project requests and NTPs in their For Approval queue and signs them on your behalf. You keep your own
                    access throughout, so anything you settle yourself still counts.
                </p>
            </div>

            {active && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap', background: '#ecfdf5', border: '1px solid #a7f3d0', borderRadius: '10px', padding: '12px 16px', marginBottom: '18px' }}>
                    <span style={{ fontSize: '20px' }}>🏖️</span>
                    <div style={{ fontSize: '13px', color: '#065f46' }}>
                        You are on roster break until <strong>{active.ends_on === today ? 'today' : active.span.split(' – ')[1]}</strong>.{' '}
                        <strong>{active.oic?.name ?? 'Your OIC'}</strong> is covering your approvals.
                    </div>
                </div>
            )}

            <div style={{ display: 'flex', gap: '18px', alignItems: 'flex-start', flexWrap: 'wrap' }}>
                {/* Form */}
                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px', flex: '1 1 340px', minWidth: '300px', maxWidth: '460px' }}>
                    {sectionHeading(editing ? `Edit break · ${editing.span}` : 'Schedule a break')}

                    {oic_options.length === 0 ? (
                        <p style={{ fontSize: '12.5px', color: '#b45309', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '8px', padding: '10px 12px', margin: 0 }}>
                            There is no {oic_role_label} account to name as OIC. Ask an admin to add one first.
                        </p>
                    ) : (
                        <form onSubmit={submit}>
                            <div style={{ marginBottom: '14px' }}>
                                <label style={labelStyle}>Officer-in-charge</label>
                                <select
                                    value={form.oic_user_id}
                                    onChange={set('oic_user_id')}
                                    required
                                    style={{ ...inputStyle, cursor: 'pointer', borderColor: errors.oic_user_id ? '#dc2626' : undefined }}
                                >
                                    <option value="">Select a {oic_role_label}…</option>
                                    {oic_options.map(o => (
                                        <option key={o.id} value={o.id}>{o.name} — {o.email}</option>
                                    ))}
                                </select>
                                {errors.oic_user_id && <p style={errorStyle}>{errors.oic_user_id}</p>}
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '14px' }}>
                                <div>
                                    <label style={labelStyle}>From</label>
                                    <input
                                        type="date"
                                        value={form.starts_on}
                                        min={editing ? undefined : today}
                                        onChange={e => {
                                            const v = e.target.value;
                                            // Keep the end from slipping before the start.
                                            setForm(f => ({ ...f, starts_on: v, ends_on: f.ends_on < v ? v : f.ends_on }));
                                        }}
                                        required
                                        style={{ ...inputStyle, borderColor: errors.starts_on ? '#dc2626' : undefined }}
                                    />
                                    {errors.starts_on && <p style={errorStyle}>{errors.starts_on}</p>}
                                </div>
                                <div>
                                    <label style={labelStyle}>To</label>
                                    <input
                                        type="date"
                                        value={form.ends_on}
                                        min={form.starts_on > today ? form.starts_on : today}
                                        onChange={set('ends_on')}
                                        required
                                        style={{ ...inputStyle, borderColor: errors.ends_on ? '#dc2626' : undefined }}
                                    />
                                    {errors.ends_on && <p style={errorStyle}>{errors.ends_on}</p>}
                                </div>
                            </div>

                            <div style={{ marginBottom: '6px' }}>
                                <label style={labelStyle}>Notes <span style={{ fontWeight: 400, color: '#9ca3af' }}>(optional)</span></label>
                                <textarea
                                    value={form.notes}
                                    onChange={set('notes')}
                                    rows={3}
                                    maxLength={500}
                                    placeholder="Anything the OIC should know while you are away…"
                                    style={{ ...inputStyle, resize: 'vertical', borderColor: errors.notes ? '#dc2626' : undefined }}
                                />
                                {errors.notes && <p style={errorStyle}>{errors.notes}</p>}
                            </div>

                            <div style={{ marginTop: '18px', borderTop: '1px solid #f3f4f6', paddingTop: '16px', display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    style={{ padding: '9px 22px', borderRadius: '8px', border: 'none', background: submitting ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: submitting ? 'not-allowed' : 'pointer' }}
                                >
                                    {submitting ? 'Saving…' : (editing ? 'Save Changes' : 'Schedule Break')}
                                </button>
                                {editing && (
                                    <button type="button" onClick={cancelEdit} style={{ padding: '9px 16px', borderRadius: '8px', border: '1px solid #e2e8f0', background: '#fff', color: '#475569', fontSize: '13px', fontWeight: 600, cursor: 'pointer' }}>
                                        Discard
                                    </button>
                                )}
                            </div>
                        </form>
                    )}
                </div>

                {/* Breaks */}
                <div style={{ flex: '2 1 420px', minWidth: '300px', display: 'flex', flexDirection: 'column', gap: '18px' }}>
                    <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px' }}>
                        {sectionHeading('Current & upcoming')}
                        {!active && upcoming.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: '28px 16px', border: '1px dashed #e2e8f0', borderRadius: '10px', color: '#94a3b8', fontSize: '12.5px' }}>
                                No break scheduled. Your approvals stay with you.
                            </div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                {active && rowCard(active)}
                                {upcoming.map(rowCard)}
                            </div>
                        )}
                    </div>

                    {ended.length > 0 && (
                        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '24px 26px' }}>
                            {sectionHeading('Past breaks')}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                {ended.map(rowCard)}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
