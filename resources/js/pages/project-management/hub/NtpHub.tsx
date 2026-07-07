import { useRef, useState } from 'react';
import { DataTable, HubProject, HubShell, InfoStrip, SectionTitle } from './Common';

interface ScopeItem {
    seq: number;
    description: string;
    qty: string | null;
    unit: string | null;
    unit_cost: number | null;
    total_cost: number | null;
}

const peso = (n: number) => `Php ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

interface NtpData {
    id: number;
    ntp_no: string;
    contractor: string;
    baseline_start: string;
    baseline_end: string;
    approved_cost: number;
    issued_date: string;
    scope_items: ScopeItem[];
}

export default function NtpHub({ project, ntps }: { project: HubProject; ntps: NtpData[]; canEdit?: boolean }) {
    const [selected, setSelected] = useState<NtpData | null>(null);
    const printRef = useRef<HTMLDivElement>(null);

    const handlePrint = () => {
        const content = printRef.current;
        if (!content || !selected) return;
        const win = window.open('', '_blank', 'width=900,height=700');
        if (!win) return;
        win.document.write(`<!DOCTYPE html><html><head>
            <title>Notice to Proceed — ${selected.ntp_no}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #0f172a; }
                * { box-sizing: border-box; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #e2e8f0; padding: 8px 12px; font-size: 13px; text-align: left; }
                th { background: #f8fafc; font-weight: 700; }
                @media print { body { margin: 20px; } }
            </style>
        </head><body>${content.innerHTML}</body></html>`);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 250);
    };

    // ── List view ─────────────────────────────────────────────────────────────
    if (!selected) {
        return (
            <HubShell>
                <div style={{ borderBottom: '2px solid #059669', paddingBottom: '14px', marginBottom: '24px' }}>
                    <h3 style={{ color: '#059669', margin: '0 0 4px' }}>NOTICE TO PROCEED</h3>
                    <span style={{ color: '#64748b', fontSize: '12px' }}>Official Authorization for Project Commencement</span>
                </div>

                {ntps.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '60px 24px', color: '#94a3b8' }}>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.5" style={{ marginBottom: '12px' }}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <div style={{ fontWeight: 700, fontSize: '15px', color: '#64748b', marginBottom: '6px' }}>No NTP Issued Yet</div>
                        <div style={{ fontSize: '13px' }}>Issue an NTP from the RFQ section after awarding a contractor.</div>
                    </div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['NTP No.', 'Contractor', 'Baseline Start', 'Baseline End', 'Approved Cost', 'Issued Date', ''].map(h => (
                                    <th key={h} style={{ padding: '9px 14px', fontSize: '11px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.5px', borderBottom: '1px solid #e5e7eb', textAlign: 'left' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {ntps.map((ntp, i) => (
                                <tr key={ntp.id} style={{ background: i % 2 === 0 ? '#fff' : '#f8fafc' }}>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', fontWeight: 700, color: '#059669', borderBottom: '1px solid #f1f5f9' }}>{ntp.ntp_no}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#0f172a', borderBottom: '1px solid #f1f5f9' }}>{ntp.contractor}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.baseline_start}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.baseline_end}</td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#0f172a', fontWeight: 600, borderBottom: '1px solid #f1f5f9' }}>
                                        Php {ntp.approved_cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </td>
                                    <td style={{ padding: '10px 14px', fontSize: '13px', color: '#475569', borderBottom: '1px solid #f1f5f9' }}>{ntp.issued_date}</td>
                                    <td style={{ padding: '10px 14px', borderBottom: '1px solid #f1f5f9' }}>
                                        <button
                                            type="button"
                                            onClick={() => setSelected(ntp)}
                                            style={{ padding: '5px 12px', borderRadius: '6px', border: '1px solid #bbf7d0', background: '#f0fdf4', color: '#166534', cursor: 'pointer', fontSize: '12px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                                        >
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            View
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </HubShell>
        );
    }

    // ── Detail / print view ───────────────────────────────────────────────────
    return (
        <HubShell>
            <div style={{ borderBottom: '2px solid #059669', paddingBottom: '14px', marginBottom: '24px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <button
                        type="button"
                        onClick={() => setSelected(null)}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '5px', fontSize: '12px', color: '#64748b', background: 'none', border: 'none', cursor: 'pointer', padding: '0 0 8px', fontWeight: 500 }}
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to list
                    </button>
                    <h3 style={{ color: '#059669', margin: '0 0 4px' }}>NOTICE TO PROCEED</h3>
                    <span style={{ color: '#64748b', fontSize: '12px' }}>Official Authorization for Project Commencement</span>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontSize: '16px', fontWeight: 800, color: '#0f172a' }}>NTP#: {selected.ntp_no}</div>
                    <button
                        type="button"
                        onClick={handlePrint}
                        style={{ marginTop: '6px', padding: '5px 12px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', cursor: 'pointer', fontSize: '12px', display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print Document
                    </button>
                </div>
            </div>

            <div ref={printRef}>
                {/* Summary strip */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '16px', marginBottom: '22px' }}>
                    {([
                        ['Contractor', selected.contractor],
                        ['Baseline Start', selected.baseline_start],
                        ['Baseline End', selected.baseline_end],
                        ['Approved Cost', `Php ${selected.approved_cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}`],
                    ] as [string, string][]).map(([label, value]) => (
                        <div key={label}>
                            <div style={{ fontSize: '10px', fontWeight: 800, color: '#166534', textTransform: 'uppercase', marginBottom: '3px' }}>{label}</div>
                            <div style={{ fontSize: '13px', fontWeight: 700, color: '#14532d' }}>{value}</div>
                        </div>
                    ))}
                </div>

                <SectionTitle color="#059669">I. Project Information</SectionTitle>
                <InfoStrip project={project} accent="#059669" />

                <SectionTitle color="#059669">II. Scope of Work</SectionTitle>
                {selected.scope_items.length > 0 ? (
                    <DataTable
                        headers={['Seq No.', 'Scope of Work / Description', 'Quantity', 'Unit', 'Unit Cost', 'Total Cost']}
                        rows={[
                            ...selected.scope_items.map(item => [
                                <span style={{ fontWeight: 600 }}>{item.seq}</span>,
                                item.description,
                                item.qty ?? '—',
                                item.unit ?? '—',
                                item.unit_cost != null ? peso(item.unit_cost) : '—',
                                item.total_cost != null ? peso(item.total_cost) : '—',
                            ]),
                            [
                                '',
                                <strong>Total Cost</strong>,
                                '',
                                '',
                                '',
                                <strong style={{ color: '#059669' }}>
                                    {peso(selected.scope_items.reduce((s, i) => s + Number(i.total_cost ?? 0), 0))}
                                </strong>,
                            ],
                        ]}
                    />
                ) : (
                    <div style={{ padding: '16px', background: '#f8fafc', border: '1px solid #e5e7eb', borderRadius: '8px', fontSize: '13px', color: '#94a3b8', marginBottom: '16px' }}>
                        No scope items linked. Add items to the associated RFQ.
                    </div>
                )}

                <SectionTitle color="#059669">III. Terms and Conditions</SectionTitle>
                <DataTable
                    headers={['Provision Category', 'Details & Obligations']}
                    rows={[
                        [<strong>1. Project Commencement</strong>, 'The contractor is formally authorized to mobilize and begin work on the specified Baseline Start Date.'],
                        [<strong>2. Compliance & Safety</strong>, 'All work must adhere to the site-specific safety protocols and ISO 45001 standards. Personnel must wear appropriate PPE at all times.'],
                        [<strong>3. Payment Milestone</strong>, 'Payments shall be released based on the approved progress billing cycle as stipulated in the primary contract.'],
                        [<strong>4. Delays & Penalties</strong>, 'Any unexcused delay beyond the Baseline End Date may subject the contractor to liquidated damages of 0.1% per day.'],
                    ]}
                />

                {/* Signature block */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '48px', marginTop: '64px' }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', marginBottom: '2px', minHeight: '18px' }}>
                            {project.project_manager || ' '}
                        </div>
                        <div style={{ borderTop: '1px solid #334155', paddingTop: '8px', fontSize: '12px', fontWeight: 700, color: '#334155' }}>
                            Prepared By: Project Manager
                        </div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '13px', fontWeight: 700, color: '#0f172a', marginBottom: '2px', minHeight: '18px' }}>
                            {project.signatories?.operations_director || ' '}
                        </div>
                        <div style={{ borderTop: '1px solid #334155', paddingTop: '8px', fontSize: '12px', fontWeight: 700, color: '#334155' }}>
                            Approved By: Operations Director
                        </div>
                    </div>
                </div>
            </div>
        </HubShell>
    );
}
