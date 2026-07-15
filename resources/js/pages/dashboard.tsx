import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

// ── Types ──────────────────────────────────────────────────────────────────
interface Stats {
    active_projects?: number;
    unread_messages?: number;
    my_requests?: number;
    active_project?: number;
    delayed?: number;
    about_to_lapse?: number;
    pending_request?: number;
}

interface Kpi {
    target: number;
    actual: number;
}

interface NotificationRow {
    id: number;
    message: string;
    link: string | null;
    is_read: boolean;
    created_at: string;
}

interface ProjectRow {
    id: number;
    project_no: string;
    title: string;
    status: string;
    health: 'Advanced' | 'On-Time' | 'Delayed';
    progress: number;
}

interface RequestRow {
    id: number;
    request_no: string;
    title: string;
    requester: string;
    status: string;
    created_at: string;
}

interface AuditRow {
    id: number;
    date: string;
    user: string;
    action: string;
    module: string;
}

interface DashboardProps {
    stats: Stats;
    kpi: Kpi | null;
    tables: {
        notifications: NotificationRow[];
        projects: ProjectRow[];
        requests: RequestRow[];
        audit_trail: AuditRow[];
    };
}

interface PageProps {
    auth: { user: { role?: string } };
    [key: string]: unknown;
}

function formatNumber(value: number) {
    return new Intl.NumberFormat().format(value || 0);
}

// ── Stat Card ──────────────────────────────────────────────────────────────
interface StatCardProps {
    label: string;
    value: string;
    sub: string;
    subColor?: string;
    icon: React.ReactNode;
    onClick?: () => void;
}

function StatCard({ label, value, sub, subColor = '#6b7280', icon, onClick }: StatCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                textAlign: 'left', background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                padding: '18px 20px', position: 'relative', overflow: 'hidden', flex: 1,
                transition: 'transform 0.18s, box-shadow 0.18s', cursor: onClick ? 'pointer' : 'default', fontFamily: 'inherit',
            }}
            onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 6px 20px rgba(0,0,0,0.07)'; }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = 'none'; }}
        >
            <div style={{ position: 'absolute', right: '14px', top: '14px', opacity: 0.08, fontSize: '2rem', color: '#374151' }}>{icon}</div>
            <div style={{ fontSize: '10px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '6px' }}>{label}</div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: '#0f172a', lineHeight: 1.1, marginBottom: '4px' }}>{value}</div>
            <div style={{ fontSize: '11.5px', fontWeight: 600, color: subColor }}>{sub}</div>
        </button>
    );
}

// ── Quick Link (launchpad action tile) ──────────────────────────────────────
interface QuickLinkProps {
    label: string;
    href?: string;
    icon: React.ReactNode;
    onClick?: () => void;
}

function QuickLink({ label, href, icon, onClick }: QuickLinkProps) {
    const sharedStyle: React.CSSProperties = {
        display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '10px',
        minHeight: '94px', padding: '18px 12px', borderRadius: '8px', border: '1.5px dashed #cbd5e1',
        background: '#fff', textDecoration: 'none', color: '#475569', fontSize: '12.5px', fontWeight: 600,
        transition: 'all 0.15s', cursor: 'pointer', fontFamily: 'inherit',
    };

    const hoverIn = (target: HTMLElement) => { target.style.borderColor = '#2563eb'; target.style.color = '#2563eb'; target.style.background = '#eff6ff'; };
    const hoverOut = (target: HTMLElement) => { target.style.borderColor = '#cbd5e1'; target.style.color = '#475569'; target.style.background = '#fff'; };

    const content = (<><span style={{ color: 'currentColor' }}>{icon}</span>{label}</>);

    if (href) {
        return (
            <Link href={href} style={sharedStyle} onMouseEnter={e => hoverIn(e.currentTarget)} onMouseLeave={e => hoverOut(e.currentTarget)}>
                {content}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} style={sharedStyle} onMouseEnter={e => hoverIn(e.currentTarget)} onMouseLeave={e => hoverOut(e.currentTarget)}>
            {content}
        </button>
    );
}

function EmptyState({ text }: { text: string }) {
    return <div style={{ padding: '28px 18px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>{text}</div>;
}

function StatusBadge({ label, tone }: { label: string; tone: 'green' | 'blue' | 'red' | 'amber' | 'slate' }) {
    const map: Record<string, { bg: string; color: string }> = {
        green: { bg: '#dcfce7', color: '#166534' },
        blue: { bg: '#dbeafe', color: '#1e40af' },
        red: { bg: '#fee2e2', color: '#991b1b' },
        amber: { bg: '#fef9c3', color: '#854d0e' },
        slate: { bg: '#f1f5f9', color: '#475569' },
    };
    const s = map[tone];
    return (
        <span style={{ padding: '2px 9px', borderRadius: '99px', fontSize: '10.5px', fontWeight: 700, background: s.bg, color: s.color, whiteSpace: 'nowrap' }}>
            {label}
        </span>
    );
}

function healthTone(health: ProjectRow['health']): 'green' | 'blue' | 'red' {
    if (health === 'Advanced') return 'green';
    if (health === 'Delayed') return 'red';
    return 'blue';
}

function requestStatusTone(status: string): 'green' | 'blue' | 'red' | 'amber' | 'slate' {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'ongoing') return 'blue';
    if (status === 'completed') return 'slate';
    return 'amber';
}

// ── Generic table widget ─────────────────────────────────────────────────
function TableCard({ title, viewAllHref, headers, rows, emptyText }: {
    title: string;
    viewAllHref?: string;
    headers: string[];
    rows: React.ReactNode[][];
    emptyText: string;
}) {
    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
            <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>{title}</span>
                {viewAllHref && <Link href={viewAllHref} style={{ fontSize: '12.5px', fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}>View All</Link>}
            </div>
            {rows.length === 0 ? <EmptyState text={emptyText} /> : (
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12.5px' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {headers.map(h => (
                                    <th key={h} style={{ padding: '9px 16px', textAlign: 'left', fontSize: '10.5px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #f3f4f6', whiteSpace: 'nowrap' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((cells, i) => (
                                <tr key={i} style={{ borderBottom: '1px solid #f8fafc' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    {cells.map((cell, j) => (
                                        <td key={j} style={{ padding: '10px 16px', color: '#374151' }}>{cell}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// ── Notifications widget ─────────────────────────────────────────────────
function NotificationsCard({ notifications }: { notifications: NotificationRow[] }) {
    const markAllRead = () => router.patch(route('notifications.read-all'), {}, { preserveScroll: true });

    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
            <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>Notifications</span>
                {notifications.some(n => !n.is_read) && (
                    <button type="button" onClick={markAllRead} style={{ fontSize: '12px', fontWeight: 600, color: '#2563eb', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                        Mark all read
                    </button>
                )}
            </div>
            {notifications.length === 0 ? <EmptyState text="No notifications yet." /> : (
                <div>
                    {notifications.map(n => (
                        <a
                            key={n.id}
                            href={route('notifications.open', n.id)}
                            style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', padding: '11px 16px', borderBottom: '1px solid #f8fafc', textDecoration: 'none', color: 'inherit' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <span style={{ width: '7px', height: '7px', borderRadius: '50%', background: n.is_read ? 'transparent' : '#2563eb', flexShrink: 0, marginTop: '5px' }} />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontSize: '12.5px', fontWeight: n.is_read ? 500 : 700, color: n.is_read ? '#6b7280' : '#0f172a' }}>{n.message}</div>
                                <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '2px' }}>{n.created_at}</div>
                            </div>
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function Dashboard({ stats, kpi, tables }: DashboardProps) {
    const { props } = usePage<PageProps>();
    const role = props.auth?.user?.role;
    const isDeptUser = role !== 'approver' && role !== 'assistant_manager' && role !== 'admin';
    const isAdmin = role === 'admin';

    const kpiTileStyle: React.CSSProperties = {
        textAlign: 'left', background: '#f59e0b', border: 'none', borderRadius: '8px', padding: '16px 20px',
        color: '#fff', position: 'relative', overflow: 'hidden', cursor: isAdmin ? 'pointer' : 'default',
        fontFamily: 'inherit', textDecoration: 'none', display: 'block',
    };

    const kpiTileContent = kpi && (
        <>
            <div style={{ fontSize: '10px', fontWeight: 700, color: 'rgba(0,0,0,0.45)', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '4px' }}>
                Project Completion KPI
            </div>
            <div style={{ fontSize: '2.2rem', fontWeight: 800, lineHeight: 1, marginBottom: '2px' }}>{kpi.target}%</div>
            <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.85)' }}>
                Actual: <span style={{ color: kpi.actual >= kpi.target ? '#14532d' : '#dc2626', fontWeight: 800 }}>{kpi.actual}%</span>
            </div>
            <div style={{ position: 'absolute', right: '-8px', top: '-8px', opacity: 0.08, fontSize: '5.6rem' }}>
                <svg width="86" height="86" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M8 7V5h8v2"/></svg>
            </div>
        </>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div style={{ fontFamily: "'Inter', sans-serif" }}>
                <h1 style={{ fontSize: '22px', fontWeight: 800, color: '#0f172a', margin: '0 0 18px', letterSpacing: '-0.4px' }}>
                    Dashboard
                </h1>

                {isDeptUser ? (
                    <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
                        <QuickLink href={route('requests.create')} label="New Request" icon={
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                        } />
                        <StatCard label="Active Projects" value={formatNumber(stats.active_projects ?? 0)} sub="Your projects" subColor="#16a34a" onClick={() => router.visit(route('projects.index'))}
                            icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>} />
                        <StatCard label="Message" value={formatNumber(stats.unread_messages ?? 0)} sub="Unread comments" subColor="#ef4444" onClick={() => router.visit(route('requests.index'))}
                            icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>} />
                        <StatCard label="Requests" value={formatNumber(stats.my_requests ?? 0)} sub="Submitted by you" subColor="#2563eb" onClick={() => router.visit(route('requests.index'))}
                            icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>} />
                    </div>
                ) : (
                    <>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(120px, 1fr)) 2fr', gap: '10px', marginBottom: '20px' }}>
                            <QuickLink href={route('requests.index')} label="View Request" icon={
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            } />
                            <QuickLink href={route('projects.create')} label="Create Project" icon={
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                    <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                                </svg>
                            } />
                            <QuickLink href={route('projects.index')} label="View Projects" icon={
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            } />

                            {isAdmin ? (
                                <Link href={route('system-settings.index')} style={kpiTileStyle}>{kpiTileContent}</Link>
                            ) : (
                                <div style={kpiTileStyle}>{kpiTileContent}</div>
                            )}
                        </div>

                        <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
                            <StatCard label="Active Project" value={formatNumber(stats.active_project ?? 0)} sub="Open project records" subColor="#16a34a" onClick={() => router.visit(route('projects.index'))}
                                icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>} />
                            <StatCard label="Delayed" value={formatNumber(stats.delayed ?? 0)} sub="Behind expected progress" subColor="#dc2626" onClick={() => router.visit(route('projects.index'))}
                                icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>} />
                            <StatCard label="About to Lapse" value={formatNumber(stats.about_to_lapse ?? 0)} sub="Due within 7 days" subColor="#d97706" onClick={() => router.visit(route('projects.index'))}
                                icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>} />
                            <StatCard label="Pending Request" value={formatNumber(stats.pending_request ?? 0)} sub="In review pipeline" subColor="#2563eb" onClick={() => router.visit(route('requests.index'), { data: { status: ['pending'] } })}
                                icon={<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>} />
                        </div>
                    </>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                    <NotificationsCard notifications={tables.notifications} />

                    <TableCard
                        title="Projects"
                        viewAllHref={route('projects.index')}
                        headers={['Project No', 'Title', 'Status', 'Health', 'Progress']}
                        emptyText="No projects yet."
                        rows={tables.projects.map(p => [
                            <span style={{ fontWeight: 700, color: '#0f172a' }}>{p.project_no}</span>,
                            p.title,
                            p.status,
                            <StatusBadge label={p.health} tone={healthTone(p.health)} />,
                            `${p.progress}%`,
                        ])}
                    />

                    <TableCard
                        title="Requests"
                        viewAllHref={route('requests.index')}
                        headers={['Ref #', 'Title', 'Requester', 'Status', 'Date']}
                        emptyText="No requests yet."
                        rows={tables.requests.map(r => [
                            <span style={{ fontWeight: 700, color: '#0f172a' }}>{r.request_no}</span>,
                            r.title,
                            r.requester,
                            <StatusBadge label={r.status} tone={requestStatusTone(r.status)} />,
                            r.created_at,
                        ])}
                    />

                    <TableCard
                        title="Audit Trail"
                        headers={['Date', 'User', 'Module', 'Action']}
                        emptyText="No recent activity."
                        rows={tables.audit_trail.map(a => [
                            <span style={{ whiteSpace: 'nowrap' }}>{a.date}</span>,
                            a.user,
                            <StatusBadge label={a.module} tone="slate" />,
                            a.action,
                        ])}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
