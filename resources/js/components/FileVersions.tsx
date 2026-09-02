import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * One upload of a file slot. `is_current` marks the version the record points
 * at now; everything below it is a superseded file kept so the history stays
 * openable.
 */
export interface FileVersion {
    id: number;
    version: number;
    label: string;
    filename: string;
    url: string;
    size: number | null;
    note: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
    is_current: boolean;
}

const fmtSize = (bytes: number | null): string => {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) { value /= 1024; unit++; }
    return `${value < 10 && unit > 0 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`;
};

/** "v3" — the version the file is on now. */
export function VersionBadge({ versions, tone = '#2563eb' }: { versions?: FileVersion[]; tone?: string }) {
    const current = versions?.find(v => v.is_current) ?? versions?.[0];
    if (!current) return null;

    return (
        <span
            title={`Version ${current.version}${current.uploaded_at ? ` · uploaded ${current.uploaded_at}` : ''}`}
            style={{
                display: 'inline-block', padding: '1px 6px', borderRadius: '999px',
                background: `${tone}14`, color: tone, border: `1px solid ${tone}33`,
                fontSize: '10px', fontWeight: 800, letterSpacing: '0.2px', flexShrink: 0,
            }}
        >
            {current.label}
        </span>
    );
}

/**
 * The upload history for one file, with every earlier version still openable.
 * Collapsed by default — a file that has only ever been uploaded once has no
 * history worth taking up room for.
 */
export function FileHistory({ versions, tone = '#2563eb' }: { versions?: FileVersion[]; tone?: string }) {
    const [open, setOpen] = useState(false);

    if (!versions || versions.length < 2) return null;

    return (
        <div style={{ marginTop: '4px' }}>
            <button
                type="button"
                onClick={() => setOpen(o => !o)}
                style={{
                    background: 'none', border: 'none', padding: 0, cursor: 'pointer',
                    fontSize: '11px', fontWeight: 700, color: tone,
                    display: 'inline-flex', alignItems: 'center', gap: '3px',
                }}
            >
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"
                     style={{ transform: open ? 'rotate(90deg)' : 'none', transition: 'transform 0.13s' }}>
                    <polyline points="9 18 15 12 9 6" />
                </svg>
                {open ? 'Hide' : `${versions.length} versions`}
            </button>

            {open && (
                <div style={{ marginTop: '6px', borderLeft: `2px solid ${tone}33`, paddingLeft: '9px', display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    {versions.map(v => (
                        <div key={v.id} style={{ display: 'flex', alignItems: 'baseline', gap: '7px', fontSize: '11px' }}>
                            <span style={{ fontWeight: 800, color: v.is_current ? tone : '#94a3b8', minWidth: '22px' }}>{v.label}</span>
                            <a
                                href={v.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                style={{ color: '#334155', textDecoration: 'none', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '190px' }}
                            >
                                {v.filename}
                            </a>
                            <span style={{ color: '#cbd5e1' }}>·</span>
                            <span style={{ color: '#94a3b8' }}>
                                {v.uploaded_at ?? '—'}
                                {v.uploaded_by ? ` by ${v.uploaded_by}` : ''}
                                {v.size ? ` · ${fmtSize(v.size)}` : ''}
                            </span>
                            {v.is_current && (
                                <span style={{ color: tone, fontWeight: 800, fontSize: '10px' }}>current</span>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Upload a newer file over an existing one. The record keeps its identity —
 * only the file moves on a version, and the one it replaces stays readable in
 * the history above.
 */
export function ReplaceFileButton({
    url,
    tone = '#2563eb',
    label = 'Replace',
    accept,
}: {
    url: string;
    tone?: string;
    label?: string;
    accept?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);

    const send = (file: File) => {
        const fd = new FormData();
        fd.append('file', file);

        setBusy(true);
        router.post(url, fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    return (
        <>
            <input
                type="file"
                ref={inputRef}
                accept={accept}
                style={{ display: 'none' }}
                onChange={e => { const f = e.target.files?.[0]; if (f) send(f); }}
            />
            <button
                type="button"
                disabled={busy}
                onClick={() => inputRef.current?.click()}
                title="Upload a newer file — the current one is kept as an earlier version"
                style={{
                    padding: '3px 8px', borderRadius: '6px', border: `1px solid ${tone}55`,
                    background: '#fff', color: tone, fontSize: '11px', fontWeight: 700,
                    cursor: busy ? 'wait' : 'pointer', flexShrink: 0, whiteSpace: 'nowrap',
                }}
            >
                {busy ? 'Uploading…' : label}
            </button>
        </>
    );
}
