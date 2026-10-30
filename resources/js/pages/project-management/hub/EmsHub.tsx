import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, Modal, ModalSection, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';
import ApprovalTimeline from '@/components/ApprovalTimeline';
import { EMS_TONE, EmsHeaderGrid, EmsOptions, EmsReportRow, EmsReportSheet, EmsStatusBadge, fmtDate } from '@/components/EmsReport';

interface SelectOption { value: string; label: string }

interface Header {
    date_requested: string;
    location: string;
    contractor: string;
    target_start: string;
    target_end: string;
}

interface Props {
    project: HubProject;
    reports: EmsReportRow[];
    options: EmsOptions;
    mepeoReps: SelectOption[];
    defaults: Partial<Record<keyof Header, string | null>>;
    /** The project's assigned engineer (or someone covering for them). */
    canManage: boolean;
}

const btn = (bg: string, color: string, border = 'none'): React.CSSProperties => ({
    padding: '8px 16px', borderRadius: '7px', border, background: bg, color, fontSize: '12.5px', fontWeight: 800, cursor: 'pointer',
});

// ── Header form: create a blank report, or change its header before MEPEO submits
function HeaderModal({ project, report, defaults, onClose }: {
    project: HubProject;
    report: EmsReportRow | null;
    defaults: Props['defaults'];
    onClose: () => void;
}) {
    const [form, setForm] = useState<Header>({
        date_requested: report?.date_requested ?? defaults.date_requested ?? '',
        location:       report?.location ?? defaults.location ?? '',
        contractor:     report?.contractor ?? defaults.contractor ?? '',
        target_start:   report?.target_start ?? defaults.target_start ?? '',
        target_end:     report?.target_end ?? defaults.target_end ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const set = (key: keyof Header) => (e: React.ChangeEvent<HTMLInputElement>) => setForm(f => ({ ...f, [key]: e.target.value }));

    const submit = () => {
        setSaving(true);
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errs: Record<string, string>) => setErrors(errs),
            onFinish: () => setSaving(false),
        };
        report
            ? router.patch(route('hub.ems.update', [project.id, report.id]), { ...form }, options)
            : router.post(route('hub.ems.store', project.id), { ...form }, options);
    };

    const err = (key: string) => errors[key] && <div style={{ color: '#dc2626', fontSize: '11.5px', marginTop: '4px' }}>{errors[key]}</div>;

    return (
        <Modal
            title={report ? `Edit ${report.control_no}` : 'New EMS Survey Report'}
            onClose={onClose}
            headerBg={EMS_TONE}
            size="620px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#374151', '1px solid #e2e8f0')}>Cancel</button>
                <button type="button" onClick={submit} disabled={saving} style={btn(EMS_TONE, '#fff')}>
                    {saving ? 'Saving…' : report ? 'Save' : 'Create Blank Report'}
                </button>
            </>}
        >
            {!report && (
                <p style={{ margin: '0 0 16px', fontSize: '12.5px', color: '#475569', lineHeight: 1.55 }}>
                    This creates a blank Environmental Impact Assessment (PMD-PRJ-FRM-19) for <strong>{project.project_no}</strong>.
                    Send it to a MEPEO representative afterwards; they fill out the assessment.
                </p>
            )}
            <ModalSection color={EMS_TONE}>Report Header</ModalSection>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                <Field label="Date Requested">
                    <input type="date" style={inputStyle} value={form.date_requested} onChange={set('date_requested')} />
                    {err('date_requested')}
                </Field>
                <Field label="Project Location">
                    <input type="text" style={inputStyle} value={form.location} onChange={set('location')} />
                    {err('location')}
                </Field>
                <div style={{ gridColumn: '1 / -1' }}>
                    <Field label="Contractor">
                        <input type="text" style={inputStyle} value={form.contractor} onChange={set('contractor')} placeholder="Leave blank if not yet known" />
                        {err('contractor')}
                    </Field>
                </div>
                <Field label="Target Start Date">
                    <input type="date" style={inputStyle} value={form.target_start} onChange={set('target_start')} />
                    {err('target_start')}
                </Field>
                <Field label="Target End Date">
                    <input type="date" style={inputStyle} value={form.target_end} onChange={set('target_end')} />
                    {err('target_end')}
                </Field>
            </div>
        </Modal>
    );
}

// ── Send to a MEPEO representative ──────────────────────────────────────────
function SendModal({ project, report, reps, onClose }: {
    project: HubProject;
    report: EmsReportRow;
    reps: SelectOption[];
    onClose: () => void;
}) {
    const [repId, setRepId] = useState(report.mepeo_rep ? String(report.mepeo_rep.id) : '');
    const [sendEmail, setSendEmail] = useState(true);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        if (!repId) { setError('Choose the MEPEO representative to send it to.'); return; }
        setSaving(true);
        router.post(route('hub.ems.send', [project.id, report.id]), { mepeo_rep_id: Number(repId), send_email: sendEmail }, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: errs => setError(Object.values(errs)[0] ?? 'Could not send.'),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            title={`Send ${report.control_no} to MEPEO`}
            onClose={onClose}
            headerBg={EMS_TONE}
            size="480px"
            footer={<>
                <button type="button" onClick={onClose} style={btn('#fff', '#374151', '1px solid #e2e8f0')}>Cancel</button>
                <button type="button" onClick={submit} disabled={saving || reps.length === 0} style={{ ...btn(EMS_TONE, '#fff'), opacity: reps.length === 0 ? 0.5 : 1 }}>
                    {saving ? 'Sending…' : 'Send'}
                </button>
            </>}
        >
            {reps.length === 0 ? (
                <div style={{ fontSize: '13px', color: '#92400e', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '8px', padding: '12px 14px' }}>
                    No user holds the MEPEO Representative role yet. Ask an administrator to add one under Users.
                </div>
            ) : (
                <>
                    <p style={{ margin: '0 0 14px', fontSize: '12.5px', color: '#475569', lineHeight: 1.55 }}>
                        The representative fills out the assessment, then submits it to the MEPEO Manager for approval.
                        {report.mepeo_rep && <> It is with <strong>{report.mepeo_rep.name}</strong> now; choosing someone else hands it over.</>}
                    </p>
                    <Field label="MEPEO Representative">
                        <select value={repId} onChange={e => { setRepId(e.target.value); setError(''); }} style={{ ...inputStyle, background: '#fff' }}>
                            <option value="">— Choose —</option>
                            {reps.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                    </Field>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '14px', fontSize: '12.5px', color: '#334155', cursor: 'pointer' }}>
                        <input type="checkbox" checked={sendEmail} onChange={e => setSendEmail(e.target.checked)} />
                        Email them as well (they always get an in-app notification)
                    </label>
                    {error && <div style={{ color: '#dc2626', fontSize: '12px', marginTop: '10px', fontWeight: 600 }}>{error}</div>}
                </>
            )}
        </Modal>
    );
}

// ── Read-only view of the whole report ─────────────────────────────────────
function ViewModal({ report, options, onClose }: { report: EmsReportRow; options: EmsOptions; onClose: () => void }) {
    return (
        <Modal
            title={`EMS Survey Report ${report.control_no}`}
            onClose={onClose}
            headerBg={EMS_TONE}
            size="900px"
            footer={<>
                <button type="button" onClick={() => window.open(report.print_url, '_blank', 'noopener')} style={btn('#fff', '#374151', '1px solid #e2e8f0')}>Print FRM-19</button>
                <button type="button" onClick={onClose} style={btn(EMS_TONE, '#fff')}>Close</button>
            </>}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '14px', flexWrap: 'wrap' }}>
                <EmsStatusBadge row={report} />
                {report.mepeo_rep && <span style={{ fontSize: '12.5px', color: '#64748b' }}>MEPEO Representative: <strong style={{ color: '#334155' }}>{report.mepeo_rep.name}</strong></span>}
            </div>
            {report.return_remarks && report.status === 'with_mepeo' && (
                <div style={{ fontSize: '12.5px', color: '#991b1b', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '10px 12px', marginBottom: '14px' }}>
                    <strong>Returned for revision:</strong> {report.return_remarks}
                </div>
            )}
            <EmsHeaderGrid row={report} />
            {report.approvals.length > 0 && (
                <div style={{ margin: '14px 0' }}>
                    <ApprovalTimeline steps={report.approvals} />
                </div>
            )}
            <div style={{ marginTop: '14px' }}>
                <EmsReportSheet row={report} options={options} />
            </div>
        </Modal>
    );
}

export default function EmsHub({ project, reports, options, mepeoReps, defaults, canManage }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<EmsReportRow | null>(null);
    const [sending, setSending] = useState<EmsReportRow | null>(null);
    const [viewing, setViewing] = useState<EmsReportRow | null>(null);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const hasSubRows = reports.some(r => !!r.sub_project_id);
    // Only this project's own reports, and only until MEPEO submits them.
    const isOpen = (r: EmsReportRow) => canManage && !r.sub_project_id && (r.status === 'draft' || r.status === 'with_mepeo');

    const handleDelete = (row: EmsReportRow) => {
        showConfirm(`Delete EMS Survey Report ${row.control_no}?`, () => {
            router.delete(route('hub.ems.destroy', [project.id, row.id]), { preserveScroll: true });
        }, { title: 'Delete EMS Survey Report', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            {creating && <HeaderModal project={project} report={null} defaults={defaults} onClose={() => setCreating(false)} />}
            {editing && <HeaderModal project={project} report={editing} defaults={defaults} onClose={() => setEditing(null)} />}
            {sending && <SendModal project={project} report={sending} reps={mepeoReps} onClose={() => setSending(null)} />}
            {viewing && <ViewModal report={viewing} options={options} onClose={() => setViewing(null)} />}

            <InfoStrip project={project} accent={EMS_TONE} />

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', marginBottom: '16px' }}>
                <div style={{ fontSize: '12.5px', color: '#64748b', maxWidth: '620px', lineHeight: 1.5 }}>
                    Environmental Impact Assessment (PMD-PRJ-FRM-19). Create a blank report, send it to MEPEO to fill out;
                    it is then approved by the MEPEO Manager, reviewed by the PMD Assistant Manager and noted by the PMD Manager.
                </div>
                {canManage && (
                    <button type="button" onClick={() => setCreating(true)} style={btn(EMS_TONE, '#fff')}>+ New EMS Survey Report</button>
                )}
            </div>

            {reports.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#94a3b8', fontSize: '13px', border: '1px dashed #e2e8f0', borderRadius: '8px' }}>
                    No EMS Survey Reports yet.
                    {!canManage && <div style={{ marginTop: '6px', fontSize: '12px' }}>Only the project's assigned engineer creates them.</div>}
                </div>
            ) : (
                <DataTable
                    headers={['Control No.', ...(hasSubRows ? ['Project'] : []), 'Date Requested', 'MEPEO Representative', 'Status', 'Sign-off', 'Actions']}
                    rows={reports.map(row => [
                        <strong style={{ whiteSpace: 'nowrap' }}>{row.control_no}</strong>,
                        ...(hasSubRows ? [<SubTag no={row.sub_project_no} />] : []),
                        <span style={{ whiteSpace: 'nowrap' }}>{fmtDate(row.date_requested)}</span>,
                        <span style={{ fontSize: '12.5px' }}>{row.mepeo_rep?.name ?? <span style={{ color: '#94a3b8' }}>Not sent yet</span>}</span>,
                        <EmsStatusBadge row={row} />,
                        row.approvals.length > 0 ? <ApprovalTimeline steps={row.approvals} compact /> : <span style={{ color: '#94a3b8', fontSize: '12px' }}>—</span>,
                        <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                            {isOpen(row) && (
                                <button type="button" onClick={() => setSending(row)} style={{ ...btn(row.status === 'draft' ? EMS_TONE : '#fff', row.status === 'draft' ? '#fff' : EMS_TONE, row.status === 'draft' ? 'none' : `1px solid ${EMS_TONE}`), padding: '6px 11px', fontSize: '12px', whiteSpace: 'nowrap' }}>
                                    {row.status === 'draft' ? 'Send to MEPEO' : 'Reassign'}
                                </button>
                            )}
                            <ActionBtns
                                view
                                print
                                edit={isOpen(row)}
                                del={isOpen(row)}
                                open={!!row.sub_project_id}
                                onView={() => setViewing(row)}
                                onPrint={() => window.open(row.print_url, '_blank', 'noopener')}
                                onEdit={() => setEditing(row)}
                                onDelete={() => handleDelete(row)}
                                onOpen={() => router.visit(route('projects.hub.ems', row.sub_project_id!))}
                            />
                        </div>,
                    ])}
                />
            )}
        </HubShell>
    );
}
