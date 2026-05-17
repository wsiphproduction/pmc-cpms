export interface HubProject {
    project_no: string;
    title: string;
    site: string;
    project_manager: string;
    deadline: string;
    budget_total: number;
    budget_paid: number;
    completion_percent: number;
    cost_code: string;
    owner_email: string;
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

export function Button({ children, variant = 'primary' }: { children: React.ReactNode; variant?: 'primary' | 'dark' | 'outline' | 'success' }) {
    const styles = {
        primary: { background: '#2563eb', color: '#fff', border: 'none' },
        dark: { background: '#0f172a', color: '#fff', border: 'none' },
        success: { background: '#059669', color: '#fff', border: 'none' },
        outline: { background: '#fff', color: '#374151', border: '1px solid #e2e8f0' },
    }[variant];

    return <button type="button" style={{ ...styles, borderRadius: '7px', padding: '8px 13px', fontSize: '12.5px', fontWeight: 800, cursor: 'pointer' }}>{children}</button>;
}

export function DataTable({ headers, rows }: { headers: string[]; rows: React.ReactNode[][] }) {
    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                <thead>
                    <tr style={{ background: '#f1f5f9' }}>
                        {headers.map(header => (
                            <th key={header} style={{ padding: '10px 12px', textAlign: 'left', fontSize: '10px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e2e8f0' }}>{header}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => (
                        <tr key={rowIndex} style={{ borderBottom: rowIndex === rows.length - 1 ? 'none' : '1px solid #f1f5f9' }}>
                            {row.map((cell, cellIndex) => <td key={cellIndex} style={{ padding: '11px 12px', color: '#334155', verticalAlign: 'middle' }}>{cell}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function Badge({ children, tone = 'slate' }: { children: React.ReactNode; tone?: 'slate' | 'green' | 'yellow' | 'red' | 'blue' }) {
    const map = {
        slate: ['#f1f5f9', '#475569', '#cbd5e1'],
        green: ['#dcfce7', '#166534', '#bbf7d0'],
        yellow: ['#fef9c3', '#854d0e', '#fef08a'],
        red: ['#fee2e2', '#991b1b', '#fecaca'],
        blue: ['#dbeafe', '#1e40af', '#bfdbfe'],
    };
    const [bg, color, border] = map[tone];
    return <span style={{ background: bg, color, border: `1px solid ${border}`, borderRadius: '999px', padding: '4px 9px', fontSize: '11px', fontWeight: 800 }}>{children}</span>;
}
