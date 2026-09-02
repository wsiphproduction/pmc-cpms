export interface ApprovalStep {
    sequence: number;
    role: string;
    role_label: string;
    status: 'pending' | 'approved' | 'rejected';
    is_current: boolean;
    actor: string | null;
    acted_at: string | null;
    remarks: string | null;
}

const STEP_META: Record<ApprovalStep['status'], { bg: string; border: string; color: string; mark: string }> = {
    approved: { bg: '#dcfce7', border: '#86efac', color: '#166534', mark: '✓' },
    rejected: { bg: '#fee2e2', border: '#fca5a5', color: '#b91c1c', mark: '✕' },
    pending:  { bg: '#f8fafc', border: '#e2e8f0', color: '#94a3b8', mark: '' },
};

/**
 * The sign-off chain for a request or NTP, in signing order. The step the chain
 * is currently waiting on is outlined so it reads at a glance who is holding it.
 */
export default function ApprovalTimeline({ steps, compact = false }: { steps: ApprovalStep[]; compact?: boolean }) {
    if (!steps || steps.length === 0) return null;

    return (
        <div style={{ display: 'flex', alignItems: 'stretch', gap: '6px', flexWrap: 'wrap' }}>
            {steps.map((step, i) => {
                const meta = STEP_META[step.status];
                const waiting = step.is_current;

                return (
                    <div key={step.sequence} style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <div
                            title={[
                                step.role_label,
                                step.actor ? `by ${step.actor}` : null,
                                step.acted_at,
                                step.remarks ? `“${step.remarks}”` : null,
                            ].filter(Boolean).join(' · ')}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '7px',
                                padding: compact ? '4px 9px' : '7px 12px',
                                borderRadius: '999px',
                                background: waiting ? '#eff6ff' : meta.bg,
                                border: `1.5px solid ${waiting ? '#93c5fd' : meta.border}`,
                                minWidth: 0,
                            }}
                        >
                            <span style={{
                                width: '17px', height: '17px', borderRadius: '999px', flexShrink: 0,
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                background: waiting ? '#2563eb' : (step.status === 'pending' ? '#e2e8f0' : meta.color),
                                color: '#fff', fontSize: '10px', fontWeight: 900,
                            }}>
                                {meta.mark || step.sequence}
                            </span>
                            <span style={{ minWidth: 0 }}>
                                <span style={{
                                    display: 'block', fontSize: compact ? '11px' : '12px', fontWeight: 700,
                                    color: waiting ? '#1d4ed8' : (step.status === 'pending' ? '#94a3b8' : meta.color),
                                    whiteSpace: 'nowrap',
                                }}>
                                    {step.role_label}
                                </span>
                                {!compact && (step.actor || waiting) && (
                                    <span style={{ display: 'block', fontSize: '10.5px', color: '#94a3b8', whiteSpace: 'nowrap' }}>
                                        {waiting ? 'Awaiting decision' : `${step.actor}${step.acted_at ? ` · ${step.acted_at}` : ''}`}
                                    </span>
                                )}
                            </span>
                        </div>
                        {i < steps.length - 1 && (
                            <span style={{ width: '14px', height: '2px', background: '#e2e8f0', flexShrink: 0 }} />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** The remarks left on any settled step, so a rejection reason is never buried in a tooltip. */
export function ApprovalRemarks({ steps }: { steps: ApprovalStep[] }) {
    const withRemarks = (steps ?? []).filter(s => s.remarks);

    if (withRemarks.length === 0) return null;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginTop: '10px' }}>
            {withRemarks.map(step => (
                <div key={step.sequence} style={{
                    padding: '8px 12px', borderRadius: '8px', fontSize: '12.5px', color: '#475569',
                    background: step.status === 'rejected' ? '#fef2f2' : '#f8fafc',
                    border: `1px solid ${step.status === 'rejected' ? '#fecaca' : '#e2e8f0'}`,
                }}>
                    <strong style={{ color: step.status === 'rejected' ? '#b91c1c' : '#334155' }}>
                        {step.role_label}
                    </strong>
                    {step.actor ? ` (${step.actor})` : ''} — {step.remarks}
                </div>
            ))}
        </div>
    );
}
