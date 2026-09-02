import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Modal } from './Common';

interface SendPageProps {
    auth: { user: { email: string } };
    [key: string]: unknown;
}

/**
 * The "who are we mailing this to" dialog, shared by every hub action that
 * sends a document out to a contractor — dispatching an RFQ, re-sending one,
 * and issuing an NTP. The caller supplies the wording and does the posting.
 */
export function SendConfirmModal({ contractor, dueDate, dueLabel = 'Due Date', email, additional = '', onClose, onSend, title = 'Confirm & Send RFQ', sendLabel = 'Send RFQ', note }: {
    contractor: string;
    dueDate: string;
    /** What the date beside the contractor means for this document. */
    dueLabel?: string;
    email: string;
    /** Pre-filled CC list — the supplier's remaining mailboxes. */
    additional?: string;
    onClose: () => void;
    onSend: (email: string, additionalRecipients: string[], ccSelf: boolean) => void;
    /** Overridden when the same dialog is reused to re-send an existing RFQ. */
    title?: string;
    sendLabel?: string;
    note?: string;
}) {
    const { auth } = usePage<SendPageProps>().props;
    const [recipientEmail, setRecipientEmail]   = useState(email);
    const [additionalInput, setAdditionalInput] = useState(additional);
    const [ccSelf, setCcSelf]                   = useState(false);
    const [emailError, setEmailError]           = useState('');

    const parseAdditional = () =>
        additionalInput.split(',').map(e => e.trim()).filter(Boolean);

    const handleConfirm = () => {
        if (!recipientEmail.trim()) { setEmailError('Email address is required.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipientEmail.trim())) { setEmailError('Please enter a valid email address.'); return; }
        const additional = parseAdditional();
        const invalid = additional.find(e => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e));
        if (invalid) { setEmailError(`"${invalid}" is not a valid email address.`); return; }
        setEmailError('');
        onSend(recipientEmail.trim(), additional, ccSelf);
    };

    return (
        <Modal title={title} onClose={onClose} size="460px" headerBg="#2563eb"
            footer={<>
                <button type="button" onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer' }}>Cancel</button>
                <button type="button" onClick={handleConfirm} style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    {sendLabel}
                </button>
            </>}
        >
            {note && (
                <div style={{ background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '8px', padding: '10px 14px', marginBottom: '16px', fontSize: '12.5px', color: '#92400e', lineHeight: 1.6 }}>
                    {note}
                </div>
            )}
            {/* Contractor info strip */}
            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '8px', padding: '12px 16px', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <div>
                    <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Service Contractor</div>
                    <div style={{ fontSize: '14px', fontWeight: 700, color: '#1e3a8a' }}>{contractor}</div>
                </div>
                {dueDate && (
                    <div style={{ marginLeft: 'auto', textAlign: 'right' }}>
                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#3b82f6', textTransform: 'uppercase', letterSpacing: '0.4px' }}>{dueLabel}</div>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e3a8a' }}>{dueDate}</div>
                    </div>
                )}
            </div>

            {/* Email input */}
            <div style={{ marginBottom: '6px' }}>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                    Recipient Email Address
                </label>
                <div style={{ position: 'relative' }}>
                    <span style={{ position: 'absolute', left: '11px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input
                        type="email"
                        value={recipientEmail}
                        onChange={e => { setRecipientEmail(e.target.value); setEmailError(''); }}
                        placeholder="contractor@example.com"
                        autoFocus
                        style={{
                            width: '100%', boxSizing: 'border-box', padding: '9px 11px 9px 34px',
                            border: `1.5px solid ${emailError ? '#fca5a5' : '#e2e8f0'}`,
                            borderRadius: '7px', fontSize: '13px', fontFamily: 'inherit', outline: 'none',
                            background: emailError ? '#fff7f7' : '#fff',
                        }}
                    />
                </div>
                {emailError && <div style={{ color: '#dc2626', fontSize: '11.5px', fontWeight: 600, marginTop: '5px' }}>{emailError}</div>}
                <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '5px' }}>You can edit the email before sending.</div>
            </div>

            {/* Additional recipients */}
            <div style={{ marginBottom: '14px' }}>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: '6px' }}>
                    Additional Recipients (Optional)
                </label>
                <input
                    type="text"
                    value={additionalInput}
                    onChange={e => { setAdditionalInput(e.target.value); setEmailError(''); }}
                    placeholder="another@example.com, another2@example.com"
                    style={{
                        width: '100%', boxSizing: 'border-box', padding: '9px 11px',
                        border: '1.5px solid #e2e8f0', borderRadius: '7px', fontSize: '13px', fontFamily: 'inherit', outline: 'none',
                    }}
                />
                <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '5px' }}>Separate multiple email addresses with commas.</div>
            </div>

            {/* Send me a copy */}
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12.5px', color: '#334155', cursor: 'pointer' }}>
                <input type="checkbox" checked={ccSelf} onChange={e => setCcSelf(e.target.checked)} />
                Send me a copy of this email ({auth.user.email})
            </label>
        </Modal>
    );
}
