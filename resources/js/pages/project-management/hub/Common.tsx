export interface HubSignatories {
    prepared_by: string;
    pmd_assistant_manager: string;
    pmd_manager: string;
    ecs_division_manager: string;
    operations_director: string;
}

export interface HubProject {
    id: number;
    project_no: string;
    title: string;
    site: string;
    project_manager: string;
    budget_total: number;
    budget_paid?: number;
    completion_percent: number;
    deadline?: string;
    cost_code?: string;
    owner_email?: string;
    signatories?: HubSignatories;
}

export function money(value: number) {
    return `PhP ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function HubShell({ children }: { children: React.ReactNode }) {
    return <div style={{ padding: '24px', background: '#fff', minHeight: '550px', boxSizing: 'border-box' }}>{children}</div>;
}

export function SectionTitle({ children, color = '#2563eb' }: { children: React.ReactNode; color?: string }) {
    return (
        <div style={{ borderLeft: `4px solid ${color}`, background: '#f8fafc', padding: '9px 14px', marginBottom: '18px', fontSize: '12px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
            {children}
        </div>
    );
}

export function InfoStrip({ project, accent = '#2563eb' }: { project: HubProject; accent?: string }) {
    const items = [
        ['Project Number', project.project_no],
        ['Project Title', project.title],
        ['Job Site / Location', project.site],
        ['Project Owner', project.project_manager],
        ['Cost Code', project.cost_code],
        ['Date Needed', project.deadline],
    ];
    return (
        <div style={{ border: '1px solid #e2e8f0', borderLeft: `4px solid ${accent}`, borderRadius: '8px', background: '#f8fafc', padding: '16px', marginBottom: '22px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '14px 18px' }}>
                {items.map(([label, value]) => (
                    <div key={label}>
                        <div style={{ fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', marginBottom: '2px' }}>{label}</div>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#1e293b' }}>{value || '-'}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label style={{ display: 'block' }}>
            <span style={{ display: 'block', fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>{label}</span>
            {children}
        </label>
    );
}

export const inputStyle: React.CSSProperties = {
    width: '100%',
    boxSizing: 'border-box',
    border: '1.5px solid #e2e8f0',
    borderRadius: '7px',
    padding: '8px 10px',
    fontSize: '13px',
    fontFamily: 'inherit',
    outline: 'none',
};

export function Button({ children, variant = 'primary', onClick }: { children: React.ReactNode; variant?: 'primary' | 'dark' | 'outline' | 'success'; onClick?: () => void }) {
    const styles = {
        primary: { background: '#2563eb', color: '#fff', border: 'none' },
        dark:    { background: '#0f172a', color: '#fff', border: 'none' },
        success: { background: '#059669', color: '#fff', border: 'none' },
        outline: { background: '#fff',    color: '#374151', border: '1px solid #e2e8f0' },
    }[variant];
    return (
        <button type="button" onClick={onClick} style={{ ...styles, borderRadius: '7px', padding: '8px 13px', fontSize: '12.5px', fontWeight: 800, cursor: 'pointer' }}>
            {children}
        </button>
    );
}

export function DataTable({ headers, rows, footer }: { headers: string[]; rows: React.ReactNode[][]; footer?: React.ReactNode }) {
    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f1f5f9' }}>
                            {headers.map(h => (
                                <th key={h} style={{ padding: '10px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e2e8f0', whiteSpace: 'nowrap' }}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, ri) => (
                            <tr key={ri} style={{ borderBottom: ri === rows.length - 1 ? 'none' : '1px solid #f1f5f9' }}>
                                {row.map((cell, ci) => <td key={ci} style={{ padding: '11px 12px', color: '#334155', verticalAlign: 'middle' }}>{cell}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {footer}
        </div>
    );
}

export function Badge({ children, tone = 'slate' }: { children: React.ReactNode; tone?: 'slate' | 'green' | 'yellow' | 'red' | 'blue' }) {
    const map = {
        slate:  ['#f1f5f9', '#475569', '#cbd5e1'],
        green:  ['#dcfce7', '#166534', '#bbf7d0'],
        yellow: ['#fef9c3', '#854d0e', '#fef08a'],
        red:    ['#fee2e2', '#991b1b', '#fecaca'],
        blue:   ['#dbeafe', '#1e40af', '#bfdbfe'],
    };
    const [bg, color, border] = map[tone];
    return (
        <span style={{ background: bg, color, border: `1px solid ${border}`, borderRadius: '999px', padding: '4px 9px', fontSize: '11px', fontWeight: 800 }}>
            {children}
        </span>
    );
}

// ── Shared icon SVGs ───────────────────────────────────────────────────────
const EyeIcon    = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>;
const PencilIcon = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>;
const TrashIcon  = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>;
const DlIcon     = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>;
const PrintIcon  = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>;
const TrophyIcon = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 9H2V3h4"/><path d="M22 3h-4v6"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M2 3h20"/><path d="M6 9a6 6 0 0 0 12 0"/></svg>;
const RefreshIcon = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>;
const OpenIcon    = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M7 17L17 7M7 7h10v10"/></svg>;

// A pill that tags a hub row as belonging to a sub-project (blank for the
// project's own rows). Used by the parent's read-only roll-up views.
export function SubTag({ no }: { no: string | null | undefined }) {
    return no
        ? <span style={{ fontSize: '11px', fontWeight: 700, color: '#4338ca', background: '#eef2ff', border: '1px solid #c7d2fe', borderRadius: '5px', padding: '2px 7px', whiteSpace: 'nowrap' }}>{no}</span>
        : <span style={{ fontSize: '11.5px', color: '#94a3b8' }}>This project</span>;
}

function mkBtn(bg: string, border: string, color: string, title: string, icon: React.ReactNode, onClick?: () => void) {
    return (
        <button type="button" title={title} onClick={onClick} style={{ width: '30px', height: '30px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: '6px', cursor: 'pointer', background: bg, border: `1px solid ${border}`, color }}>
            {icon}
        </button>
    );
}

export function ActionBtns({
    view, edit, del, download, print, trophy, refresh, open,
    onView, onEdit, onDelete, onDownload, onPrint, onTrophy, onRefresh, onOpen,
}: {
    view?: boolean; edit?: boolean; del?: boolean; download?: boolean; print?: boolean; trophy?: boolean; refresh?: boolean; open?: boolean;
    onView?: () => void; onEdit?: () => void; onDelete?: () => void; onDownload?: () => void; onPrint?: () => void; onTrophy?: () => void; onRefresh?: () => void; onOpen?: () => void;
}) {
    return (
        <div style={{ display: 'flex', gap: '4px' }}>
            {view     && mkBtn('#fff',    '#e2e8f0', '#475569', 'View',     <EyeIcon />,     onView)}
            {download && mkBtn('#eff6ff', '#bfdbfe', '#2563eb', 'Download', <DlIcon />,      onDownload)}
            {print    && mkBtn('#fff',    '#e2e8f0', '#475569', 'Print',    <PrintIcon />,   onPrint)}
            {edit     && mkBtn('#eff6ff', '#bfdbfe', '#2563eb', 'Edit',     <PencilIcon />,  onEdit)}
            {trophy   && mkBtn('#f0fdf4', '#bbf7d0', '#15803d', 'Award',    <TrophyIcon />,  onTrophy)}
            {refresh  && mkBtn('#fef2f2', '#fecaca', '#dc2626', 'Resend',   <RefreshIcon />, onRefresh)}
            {open     && mkBtn('#eef2ff', '#c7d2fe', '#4338ca', 'Open sub-project', <OpenIcon />, onOpen)}
            {del      && mkBtn('#fef2f2', '#fecaca', '#dc2626', 'Delete',   <TrashIcon />,   onDelete)}
        </div>
    );
}

// ── Reusable Modal ─────────────────────────────────────────────────────────
export function Modal({
    title, onClose, children, footer, headerBg = '#1e293b', size = '720px',
}: {
    title: string; onClose: () => void; children: React.ReactNode; footer?: React.ReactNode; headerBg?: string; size?: string;
}) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 500, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', zIndex: 501, width: '100%', maxWidth: size, maxHeight: '90vh', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}>
                <div style={{ padding: '12px 20px', background: headerBg, borderRadius: '12px 12px 0 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexShrink: 0 }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>{title}</span>
                    <button onClick={onClose} type="button" style={{ width: '26px', height: '26px', borderRadius: '5px', border: '1px solid rgba(255,255,255,0.2)', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ overflowY: 'auto', padding: '24px', flex: 1 }}>{children}</div>
                {footer && (
                    <div style={{ padding: '12px 20px', borderTop: '1px solid #e5e7eb', background: '#f8fafc', borderRadius: '0 0 12px 12px', display: 'flex', justifyContent: 'flex-end', gap: '8px', flexShrink: 0 }}>
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Shared modal section header ────────────────────────────────────────────
export function ModalSection({ children, color = '#2563eb' }: { children: React.ReactNode; color?: string }) {
    return (
        <div style={{ fontSize: '12px', fontWeight: 700, color, borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>
            {children}
        </div>
    );
}
