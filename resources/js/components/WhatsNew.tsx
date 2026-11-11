// The latest updates to reach the live site, as a card on the dashboard.

export interface DeployedUpdate {
    sha: string;
    type: 'New' | 'Fix';
    text: string;
    date: string;
}

const TAGS: Record<DeployedUpdate['type'], { bg: string; color: string }> = {
    New: { bg: '#dbeafe', color: '#1d4ed8' },
    Fix: { bg: '#dcfce7', color: '#15803d' },
};

export default function WhatsNew({ updates, style }: {
    updates: DeployedUpdate[];
    style?: React.CSSProperties;
}) {
    return (
        <section data-tour="dash-whats-new" aria-label="Recent updates" style={{
            background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden', ...style,
        }}>
            <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.2">
                    <path d="M12 3l1.9 5.8H20l-4.9 3.6 1.9 5.8L12 14.6l-5 3.6 1.9-5.8L4 8.8h6.1z"/>
                </svg>
                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>What's New</span>
                {updates.length > 0 && (
                    <span style={{ fontSize: '11.5px', color: '#9ca3af' }}>· {updates.length} update{updates.length === 1 ? '' : 's'}</span>
                )}
            </div>
            {updates.length === 0 ? (
                <div style={{ padding: '28px 18px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>No updates yet.</div>
            ) : (
                // Every deploy is listed, newest first; the list scrolls past about seven.
                <ul style={{
                    listStyle: 'none', margin: 0, padding: '4px 0',
                    maxHeight: '320px', overflowY: 'auto', scrollbarWidth: 'thin', scrollbarColor: '#cbd5e1 transparent',
                }}>
                    {updates.map(u => (
                        <li key={u.sha} style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', padding: '9px 16px', borderBottom: '1px solid #f8fafc' }}>
                            <span style={{
                                // One width for every tag, so the update text lines up.
                                flexShrink: 0, marginTop: '1px', padding: '1px 0', borderRadius: '999px',
                                fontSize: '10px', fontWeight: 700, width: '36px', textAlign: 'center',
                                background: TAGS[u.type].bg, color: TAGS[u.type].color,
                            }}>
                                {u.type}
                            </span>
                            <div style={{ flex: 1, minWidth: 0, fontSize: '12.5px', color: '#334155', lineHeight: 1.45 }}>{u.text}</div>
                            <div style={{ flexShrink: 0, fontSize: '11px', color: '#9ca3af', whiteSpace: 'nowrap', marginTop: '1px' }}>{u.date}</div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
