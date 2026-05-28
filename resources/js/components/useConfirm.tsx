import { useState } from 'react';

type ConfirmVariant = 'danger' | 'warning' | 'info';

interface ConfirmOptions {
    title?: string;
    confirmLabel?: string;
    variant?: ConfirmVariant;
}

interface ConfirmState extends ConfirmOptions {
    message: string;
    onConfirm: () => void;
}

const ICONS: Record<ConfirmVariant, React.ReactNode> = {
    danger: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
            <path d="M10 11v6M14 11v6"/>
            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
        </svg>
    ),
    warning: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
    ),
    info: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    ),
};

const VARIANT_COLORS: Record<ConfirmVariant, { iconBg: string; iconColor: string; btnBg: string }> = {
    danger:  { iconBg: '#fef2f2', iconColor: '#dc2626', btnBg: '#dc2626' },
    warning: { iconBg: '#fffbeb', iconColor: '#d97706', btnBg: '#d97706' },
    info:    { iconBg: '#eff6ff', iconColor: '#2563eb', btnBg: '#2563eb' },
};

function ConfirmDialog({
    title, message, confirmLabel = 'Confirm', variant = 'danger', onConfirm, onCancel,
}: ConfirmState & { onCancel: () => void }) {
    const c = VARIANT_COLORS[variant];
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div
                style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.5)', backdropFilter: 'blur(3px)' }}
                onClick={onCancel}
            />
            <div style={{
                position: 'relative', background: '#fff', borderRadius: '16px',
                padding: '36px 28px 28px', width: '100%', maxWidth: '400px', margin: '0 16px',
                boxShadow: '0 25px 80px rgba(0,0,0,0.22), 0 4px 16px rgba(0,0,0,0.08)',
                textAlign: 'center',
            }}>
                <div style={{
                    width: '56px', height: '56px', borderRadius: '50%',
                    background: c.iconBg, color: c.iconColor,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    margin: '0 auto 18px',
                }}>
                    {ICONS[variant]}
                </div>
                {title && (
                    <h3 style={{ margin: '0 0 8px', fontSize: '16px', fontWeight: 800, color: '#0f172a', letterSpacing: '-0.2px' }}>
                        {title}
                    </h3>
                )}
                <p style={{ margin: '0 0 28px', fontSize: '13.5px', color: '#64748b', lineHeight: 1.65 }}>
                    {message}
                </p>
                <div style={{ display: 'flex', gap: '10px', justifyContent: 'center' }}>
                    <button
                        type="button" onClick={onCancel}
                        style={{ padding: '9px 24px', borderRadius: '8px', border: '1.5px solid #e2e8f0', background: '#fff', fontSize: '13px', fontWeight: 600, color: '#475569', cursor: 'pointer', minWidth: '90px' }}
                    >
                        Cancel
                    </button>
                    <button
                        type="button" onClick={onConfirm}
                        style={{ padding: '9px 24px', borderRadius: '8px', border: 'none', background: c.btnBg, fontSize: '13px', fontWeight: 700, color: '#fff', cursor: 'pointer', minWidth: '90px' }}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

export function useConfirm() {
    const [state, setState] = useState<ConfirmState | null>(null);

    const confirm = (message: string, onConfirm: () => void, options?: ConfirmOptions) => {
        setState({ message, onConfirm, ...options });
    };

    const dialog = state ? (
        <ConfirmDialog
            {...state}
            onConfirm={() => { state.onConfirm(); setState(null); }}
            onCancel={() => setState(null)}
        />
    ) : null;

    return { confirm, dialog };
}
