// The latest updates to reach the live site, as the login page shows them —
// on the blue brand panel on wide screens, under the form on phones.

export interface DeployedUpdate {
    sha: string;
    type: 'New' | 'Fix';
    text: string;
    date: string;
}

type Variant = 'dark' | 'light';

const THEME: Record<Variant, {
    card: React.CSSProperties;
    header: React.CSSProperties;
    heading: string;
    icon: string;
    text: string;
    date: string;
    tags: Record<DeployedUpdate['type'], { bg: string; color: string }>;
}> = {
    // On the brand panel's blue gradient.
    dark: {
        card: { background: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.12)' },
        header: { borderBottom: '1px solid rgba(255,255,255,0.1)' },
        heading: 'rgba(255,255,255,0.85)',
        icon: '#93c5fd',
        text: 'rgba(255,255,255,0.85)',
        date: 'rgba(255,255,255,0.45)',
        tags: {
            New: { bg: 'rgba(147,197,253,0.22)', color: '#dbeafe' },
            Fix: { bg: 'rgba(134,239,172,0.2)', color: '#dcfce7' },
        },
    },
    light: {
        card: { background: '#fff', border: '1px solid #e5e7eb' },
        header: { borderBottom: '1px solid #f1f5f9', background: '#f8fafc' },
        heading: '#334155',
        icon: '#2563eb',
        text: '#334155',
        date: '#9ca3af',
        tags: {
            New: { bg: '#dbeafe', color: '#1d4ed8' },
            Fix: { bg: '#dcfce7', color: '#15803d' },
        },
    },
};

export default function WhatsNew({ updates, variant = 'light', className, style }: {
    updates: DeployedUpdate[];
    variant?: Variant;
    className?: string;
    style?: React.CSSProperties;
}) {
    const theme = THEME[variant];

    if (updates.length === 0) {
        return null;
    }

    return (
        <section aria-label="Recent updates" className={className} style={{
            borderRadius: '10px', overflow: 'hidden', fontFamily: "'Inter', sans-serif",
            ...theme.card, ...style,
        }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '7px', padding: '10px 14px', ...theme.header }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke={theme.icon} strokeWidth="2.2">
                    <path d="M12 3l1.9 5.8H20l-4.9 3.6 1.9 5.8L12 14.6l-5 3.6 1.9-5.8L4 8.8h6.1z"/>
                </svg>
                <span style={{ fontSize: '11px', fontWeight: 800, color: theme.heading, textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                    What's new
                </span>
            </div>
            <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0' }}>
                {updates.map(u => (
                    <li key={u.sha} style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', padding: '8px 14px' }}>
                        <span style={{
                            // One width for every tag, so the update text lines up.
                            flexShrink: 0, marginTop: '1px', padding: '1px 0', borderRadius: '999px',
                            fontSize: '10px', fontWeight: 700, width: '36px', textAlign: 'center',
                            background: theme.tags[u.type].bg, color: theme.tags[u.type].color,
                        }}>
                            {u.type}
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: '12.5px', color: theme.text, lineHeight: 1.45 }}>{u.text}</div>
                            <div style={{ fontSize: '11px', color: theme.date, marginTop: '1px' }}>{u.date}</div>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
