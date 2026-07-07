import { router } from '@inertiajs/react';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────
export interface CompletionPhoto { path: string; url: string }

export interface CompletionData {
    reference_no: string | null;
    sub_project_title: string | null;
    classification: string | null;
    plan_baseline_start: string | null;
    plan_baseline_end: string | null;
    plan_actual_start: string | null;
    plan_actual_end: string | null;
    con_baseline_start: string | null;
    con_baseline_end: string | null;
    con_actual_start: string | null;
    con_actual_end: string | null;
    contractor: string | null;
    baseline_amount: number | null;
    actual_amount: number | null;
    payment_status: string | null;
    completion_status: string | null;
    request_date: string | null;
    date_prepared: string | null;
    issued_on: string | null;
    received_by: string | null;
    accepted_by: string | null;
    acknowledged_by: string | null;
    photos: CompletionPhoto[];
}

export interface Signatories {
    prepared_by: string;
    pmd_assistant_manager: string;
    pmd_manager: string;
    ecs_division_manager: string;
    operations_director: string;
}

interface ProjectLite {
    id: number;
    project_no: string;
    title: string;
    site: string;
    dept_owner: string;
    owner_name: string;
    cost_code: string;
    budget_total: number;
    budget_paid: number;
    project_type: 'major' | 'minor';
}

// ── Helpers ────────────────────────────────────────────────────────────────
const todayYmd = () => {
    const d = new Date();
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

const escapeHtml = (s: unknown) =>
    String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]!));

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "2026-03-13" -> "13-Mar-26" */
const fmtShort = (ymd?: string | null) => {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    if (!y || !m || !d) return ymd;
    return `${String(d).padStart(2, '0')}-${MONTHS[m - 1]}-${String(y).slice(-2)}`;
};

/** "2026-06-03" -> "June 3, 2026" */
const fmtLong = (ymd?: string | null) => {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    if (!y || !m || !d) return ymd;
    const full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return `${full[m - 1]} ${d}, ${y}`;
};

const money = (n?: number | null) =>
    n == null ? '' : `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const daysBetween = (aYmd?: string | null, bYmd?: string | null) => {
    if (!aYmd || !bYmd) return null;
    const a = new Date(aYmd).getTime(), b = new Date(bYmd).getTime();
    if (Number.isNaN(a) || Number.isNaN(b)) return null;
    return Math.round((a - b) / 86400000);
};

// ── Capture / Edit Modal ────────────────────────────────────────────────────
export function CompletionModal({ project, completion, signatories, onClose }: {
    project: ProjectLite;
    completion: CompletionData | null;
    signatories: Signatories;
    onClose: () => void;
}) {
    const paid = project.budget_paid >= project.budget_total && project.budget_total > 0;

    const [f, setF] = useState<Record<string, string>>({
        reference_no:        completion?.reference_no ?? '',
        sub_project_title:   completion?.sub_project_title ?? 'N/A',
        classification:      completion?.classification ?? (project.project_type === 'major' ? 'MAJOR CLASS' : 'MINOR CLASS'),
        plan_baseline_start: completion?.plan_baseline_start ?? '',
        plan_baseline_end:   completion?.plan_baseline_end ?? '',
        plan_actual_start:   completion?.plan_actual_start ?? '',
        plan_actual_end:     completion?.plan_actual_end ?? '',
        con_baseline_start:  completion?.con_baseline_start ?? '',
        con_baseline_end:    completion?.con_baseline_end ?? '',
        con_actual_start:    completion?.con_actual_start ?? '',
        con_actual_end:      completion?.con_actual_end ?? '',
        contractor:          completion?.contractor ?? '',
        baseline_amount:     completion?.baseline_amount != null ? String(completion.baseline_amount) : String(project.budget_total ?? ''),
        actual_amount:       completion?.actual_amount != null ? String(completion.actual_amount) : String(project.budget_paid || project.budget_total || ''),
        payment_status:      completion?.payment_status ?? (paid ? 'Full Payment' : 'Partial Payment'),
        completion_status:   completion?.completion_status ?? 'Finished',
        request_date:        completion?.request_date ?? '',
        date_prepared:       completion?.date_prepared ?? todayYmd(),
        issued_on:           completion?.issued_on ?? todayYmd(),
        received_by:         completion?.received_by ?? '',
        accepted_by:         completion?.accepted_by ?? '',
        acknowledged_by:     completion?.acknowledged_by ?? '',
    });

    const [keepPhotos, setKeepPhotos] = useState<string[]>((completion?.photos ?? []).map(p => p.path));
    const [newFiles, setNewFiles]     = useState<File[]>([]);
    const [saving, setSaving]         = useState(false);

    const existingPhotos = (completion?.photos ?? []).filter(p => keepPhotos.includes(p.path));

    const set = (k: string, v: string) => setF(prev => ({ ...prev, [k]: v }));

    const handleSave = () => {
        setSaving(true);
        router.post(route('projects.completion.save', project.id), {
            ...f,
            keep_photos: keepPhotos,
            photos: newFiles,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 400, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.45)' }} />
            <div style={{ position: 'relative', background: '#fff', borderRadius: '12px', zIndex: 401, width: '100%', maxWidth: '780px', maxHeight: '92vh', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}>
                {/* Header */}
                <div style={{ padding: '12px 20px', background: '#1e293b', borderRadius: '12px 12px 0 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexShrink: 0 }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>Project Completion Details</span>
                    <button onClick={onClose} style={{ width: '26px', height: '26px', borderRadius: '5px', border: '1px solid rgba(255,255,255,0.2)', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {/* Body */}
                <div style={{ overflowY: 'auto', padding: '22px', flex: 1 }}>
                    <p style={{ fontSize: '12px', color: '#64748b', margin: '0 0 18px', lineHeight: 1.6 }}>
                        These details are printed on the <strong>Completion &amp; Acceptance Certificate</strong> and the{' '}
                        <strong>Completion Summary</strong>. Prepared-by, Reviewed/Checked-by, Noted-by and Endorsed-by are filled
                        automatically from the project creator and Signatory Settings.
                    </p>

                    <Section title="Overview">
                        <Grid>
                            <Field label="Reference No."><Inp v={f.reference_no} on={v => set('reference_no', v)} ph="Optional" /></Field>
                            <Field label="Sub-Project Title"><Inp v={f.sub_project_title} on={v => set('sub_project_title', v)} /></Field>
                            <Field label="Classification"><Inp v={f.classification} on={v => set('classification', v)} /></Field>
                            <Field label="Service Contractor"><Inp v={f.contractor} on={v => set('contractor', v)} ph="e.g. Austral Construction Services" /></Field>
                        </Grid>
                    </Section>

                    <Section title="Planning Schedule">
                        <Grid>
                            <Field label="Baseline Start"><Inp type="date" v={f.plan_baseline_start} on={v => set('plan_baseline_start', v)} /></Field>
                            <Field label="Baseline End"><Inp type="date" v={f.plan_baseline_end} on={v => set('plan_baseline_end', v)} /></Field>
                            <Field label="Actual Start"><Inp type="date" v={f.plan_actual_start} on={v => set('plan_actual_start', v)} /></Field>
                            <Field label="Actual End"><Inp type="date" v={f.plan_actual_end} on={v => set('plan_actual_end', v)} /></Field>
                        </Grid>
                    </Section>

                    <Section title="Construction Schedule">
                        <Grid>
                            <Field label="Baseline Start"><Inp type="date" v={f.con_baseline_start} on={v => set('con_baseline_start', v)} /></Field>
                            <Field label="Baseline End"><Inp type="date" v={f.con_baseline_end} on={v => set('con_baseline_end', v)} /></Field>
                            <Field label="Actual Start"><Inp type="date" v={f.con_actual_start} on={v => set('con_actual_start', v)} /></Field>
                            <Field label="Actual End"><Inp type="date" v={f.con_actual_end} on={v => set('con_actual_end', v)} /></Field>
                        </Grid>
                        <p style={{ fontSize: '11px', color: '#94a3b8', margin: '6px 0 0' }}>
                            The certificate’s Actual Start/End dates use the Construction schedule.
                        </p>
                    </Section>

                    <Section title="Project Cost">
                        <Grid>
                            <Field label="Baseline Amount (PhP)"><Inp type="number" v={f.baseline_amount} on={v => set('baseline_amount', v)} /></Field>
                            <Field label="Actual Amount (PhP)"><Inp type="number" v={f.actual_amount} on={v => set('actual_amount', v)} /></Field>
                            <Field label="Payment Status"><Inp v={f.payment_status} on={v => set('payment_status', v)} /></Field>
                            <Field label="Completion Status"><Inp v={f.completion_status} on={v => set('completion_status', v)} /></Field>
                        </Grid>
                    </Section>

                    <Section title="Certificate Dates">
                        <Grid>
                            <Field label="Request Date"><Inp type="date" v={f.request_date} on={v => set('request_date', v)} /></Field>
                            <Field label="Date Prepared"><Inp type="date" v={f.date_prepared} on={v => set('date_prepared', v)} /></Field>
                            <Field label="Issued On"><Inp type="date" v={f.issued_on} on={v => set('issued_on', v)} /></Field>
                        </Grid>
                    </Section>

                    <Section title="Project Owner Signatories">
                        <Grid>
                            <Field label="Received by (Owner Representative)"><Inp v={f.received_by} on={v => set('received_by', v)} /></Field>
                            <Field label="Accepted by (Owner Division Manager)"><Inp v={f.accepted_by} on={v => set('accepted_by', v)} /></Field>
                            <Field label="Acknowledged by (Owner Dept. Manager)"><Inp v={f.acknowledged_by} on={v => set('acknowledged_by', v)} /></Field>
                        </Grid>
                    </Section>

                    <Section title="Documentation Photos">
                        {existingPhotos.length > 0 && (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', marginBottom: '10px' }}>
                                {existingPhotos.map(p => (
                                    <div key={p.path} style={{ position: 'relative' }}>
                                        <img src={p.url} alt="" style={{ width: '96px', height: '72px', objectFit: 'cover', borderRadius: '7px', border: '1px solid #e2e8f0' }} />
                                        <button type="button" title="Remove" onClick={() => setKeepPhotos(prev => prev.filter(x => x !== p.path))}
                                            style={{ position: 'absolute', top: '-7px', right: '-7px', width: '20px', height: '20px', borderRadius: '50%', border: 'none', background: '#dc2626', color: '#fff', fontSize: '11px', cursor: 'pointer', lineHeight: 1 }}>×</button>
                                    </div>
                                ))}
                            </div>
                        )}
                        {newFiles.length > 0 && (
                            <div style={{ fontSize: '11.5px', color: '#2563eb', marginBottom: '8px' }}>
                                {newFiles.length} new photo{newFiles.length > 1 ? 's' : ''} selected
                            </div>
                        )}
                        <input type="file" accept="image/*" multiple
                            onChange={e => setNewFiles(Array.from(e.target.files ?? []))}
                            style={{ fontSize: '12px' }} />
                    </Section>
                </div>

                {/* Footer */}
                <div style={{ padding: '12px 20px', borderTop: '1px solid #e5e7eb', background: '#f8fafc', borderRadius: '0 0 12px 12px', display: 'flex', justifyContent: 'flex-end', gap: '8px', flexShrink: 0 }}>
                    <button onClick={onClose} style={{ padding: '7px 18px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', cursor: 'pointer', color: '#374151' }}>Cancel</button>
                    <button onClick={handleSave} disabled={saving}
                        style={{ padding: '7px 22px', borderRadius: '7px', border: 'none', background: saving ? '#93c5fd' : '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: saving ? 'not-allowed' : 'pointer' }}>
                        {saving ? 'Saving…' : 'Save Details'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// small styled sub-parts for the modal
function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div style={{ marginBottom: '18px' }}>
            <div style={{ fontSize: '11px', fontWeight: 700, color: '#2563eb', borderBottom: '1px solid #e5e7eb', paddingBottom: '6px', marginBottom: '12px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>{title}</div>
            {children}
        </div>
    );
}
function Grid({ children }: { children: React.ReactNode }) {
    return <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>{children}</div>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label style={{ fontSize: '11px', fontWeight: 600, color: '#475569', display: 'block', marginBottom: '4px' }}>{label}</label>
            {children}
        </div>
    );
}
function Inp({ v, on, type = 'text', ph }: { v: string; on: (v: string) => void; type?: string; ph?: string }) {
    return (
        <input type={type} value={v} placeholder={ph} onChange={e => on(e.target.value)}
            style={{ width: '100%', padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '12.5px', outline: 'none', fontFamily: 'inherit', boxSizing: 'border-box', color: '#0f172a' }} />
    );
}

// ── Printable HTML ──────────────────────────────────────────────────────────
const printCss = `
    * { box-sizing: border-box; }
    body { font-family: 'Times New Roman', Georgia, serif; margin: 34px 40px; color: #000; font-size: 13px; }
    .hdr { display: grid; grid-template-columns: 64px 1fr 210px; border: 1.5px solid #000; }
    .hdr .logo { display: flex; align-items: center; justify-content: center; border-right: 1px solid #000; font-weight: 800; font-size: 11px; }
    .hdr .mid { text-align: center; padding: 6px 8px; border-right: 1px solid #000; }
    .hdr .mid .c1 { font-weight: 800; font-size: 14px; letter-spacing: .3px; }
    .hdr .mid .c2 { font-weight: 700; font-size: 9.5px; }
    .hdr .mid .c3 { font-size: 11px; margin-top: 2px; }
    .hdr .doc { font-size: 10px; }
    .hdr .doc div { padding: 4px 8px; border-bottom: 1px solid #000; }
    .hdr .doc div:last-child { border-bottom: none; }
    .titlebar { border: 1.5px solid #000; border-top: none; text-align: center; font-weight: 800; font-size: 14px; padding: 6px; letter-spacing: .5px; }
    h3.sec { font-size: 12.5px; margin: 16px 0 6px; }
    table.kv { border-collapse: collapse; width: 100%; }
    table.kv td { padding: 3px 6px; vertical-align: top; }
    table.kv td.k { width: 165px; }
    table.box { border-collapse: collapse; width: 100%; }
    table.box td, table.box th { border: 1px solid #000; padding: 5px 8px; font-size: 12px; }
    table.box th.hd { background: #e5e7eb; text-align: center; font-weight: 800; }
    p.para { text-align: justify; line-height: 1.5; margin: 10px 0; }
    .sig { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; margin-top: 26px; }
    .sig.two { grid-template-columns: 1fr 1fr; }
    .sig .cell { border: 1px solid #000; padding: 8px 10px 10px; min-height: 78px; }
    .sig .role { font-size: 11px; }
    .sig .name { text-align: center; font-weight: 800; margin-top: 26px; text-transform: uppercase; font-size: 12px; }
    .sig .line { border-top: 1px solid #000; margin-top: 2px; padding-top: 2px; text-align: center; font-size: 10.5px; }
    .docimgs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .docimgs img { width: 100%; height: 150px; object-fit: cover; border: 1px solid #000; }
    @media print { body { margin: 12mm; } .sig { page-break-inside: avoid; } }
`;

function headerBlock(docNo: string, rev: string, eff: string, title: string) {
    return `
    <div class="hdr">
        <div class="logo">PMC</div>
        <div class="mid">
            <div class="c1">PHILSAGA MINING CORPORATION</div>
            <div class="c2">MINDANAO MINERAL PROCESSING AND REFINING CORPORATION</div>
            <div class="c3">PROJECT MANAGEMENT DEPARTMENT</div>
        </div>
        <div class="doc">
            <div>Doc No.: ${escapeHtml(docNo)}</div>
            <div>Rev No.: ${escapeHtml(rev)}</div>
            <div>Effective: ${escapeHtml(eff)}</div>
        </div>
    </div>
    <div class="titlebar">${escapeHtml(title)}</div>`;
}

function sigCell(role: string, name: string, title: string) {
    return `<div class="cell">
        <div class="role">${escapeHtml(role)}</div>
        <div class="name">${escapeHtml(name || ' ')}</div>
        <div class="line">${escapeHtml(title)}</div>
    </div>`;
}

function openPrint(title: string, inner: string) {
    const win = window.open('', '_blank', 'width=980,height=800');
    if (!win) return;
    win.document.write(`<!DOCTYPE html><html><head><title>${escapeHtml(title)}</title><style>${printCss}</style></head><body>${inner}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); }, 350);
}

function acceptanceHtml(project: ProjectLite, c: CompletionData | null, s: Signatories) {
    const row = (k: string, v: string) => `<tr><td class="k">${escapeHtml(k)}</td><td>:</td><td>${escapeHtml(v || '')}</td></tr>`;
    return headerBlock('PMD-PRJ-FRM-06', '00', 'October 05, 2025', 'PROJECT COMPLETION AND ACCEPTANCE CERTIFICATE') + `
    <h3 class="sec">PROJECT DETAILS <span style="float:right;font-weight:400;">Reference No.: ${escapeHtml(c?.reference_no || '')}</span></h3>
    <table class="kv">
        ${row('Project Number', project.project_no)}
        ${row('Project Title', project.title)}
        ${row('Sub-Project Title', c?.sub_project_title || 'N/A')}
        ${row('Job Site/Location', project.site)}
        ${row('Project Owner', project.owner_name)}
        ${row('Cost Code', project.cost_code)}
        ${row('Service Contractor', c?.contractor || '')}
        ${row('Actual Start Date', fmtShort(c?.con_actual_start))}
        ${row('Actual End Date', fmtShort(c?.con_actual_end))}
    </table>

    <p class="para">This is to formally certify that the project referenced above has been successfully completed in full compliance with the approved design specifications, standard engineering practices, and all relevant codes, safety regulations, and contractual obligations.</p>
    <p class="para">The completion of this project reflects the diligent efforts, coordination, and technical competence demonstrated throughout all phases of execution, from planning and mobilization to implementation, testing, and final inspection.</p>
    <p class="para">We further certify that the quality standards, performance criteria, and full scope of work, as outlined in the original Work Request, Quotation, Inspection and Test Plan, and/or Construction/Service Agreement, have been entirely fulfilled. All deliverables have been reviewed, verified, and accepted by the Project Management Department (PMD) and the designated end-user representative.</p>
    <p class="para">Comprehensive inspections, quality assurance procedures, and functional testing have been conducted where applicable, and the completed work meets or exceeds the expectations and requirements set forth at the outset of the project.</p>
    <p class="para">This certificate is issued as a formal recognition of the project's successful completion, and it signifies the transfer of responsibility from the executing party to the end-user.</p>

    <p style="margin-top:14px;"><strong>Issued on:</strong> ${escapeHtml(fmtLong(c?.issued_on))}</p>

    <div class="sig">
        ${sigCell('Prepared by:', s.prepared_by, 'Project Management Engineer')}
        ${sigCell('Reviewed by:', s.pmd_assistant_manager, 'Project Management Dept. Asst. Manager')}
        ${sigCell('Received by:', c?.received_by || '', 'Project Owner Representative')}
        ${sigCell('Noted by:', s.pmd_manager, 'Project Management Dept. Manager')}
        ${sigCell('Endorsed by:', s.ecs_division_manager, 'ECS Division Manager')}
        ${sigCell('Accepted by:', c?.accepted_by || '', 'Project Owner Division Manager')}
    </div>`;
}

function summaryHtml(project: ProjectLite, c: CompletionData | null, s: Signatories) {
    const planSlip = daysBetween(c?.plan_actual_end, c?.plan_baseline_end);
    const conSlip  = daysBetween(c?.con_actual_end, c?.con_baseline_end);
    const baseline = c?.baseline_amount ?? null;
    const actual   = c?.actual_amount ?? null;
    let variance = '';
    if (baseline != null && actual != null && baseline !== 0) {
        const diff = baseline - actual;
        const pct  = (diff / baseline) * 100;
        variance = `${pct.toFixed(1)}% (₱${diff.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })})`;
    }
    const timeline = conSlip == null ? '' : conSlip > 0 ? 'Delayed' : conSlip < 0 ? 'Advanced' : 'On-Time';

    const boxRow = (k: string, v: string) => `<tr><td>${escapeHtml(k)}</td><td>${escapeHtml(v || '')}</td></tr>`;

    const photos = (c?.photos ?? []).slice(0, 4);
    const photoHtml = photos.length
        ? `<div class="docimgs">${photos.map(p => `<img src="${escapeHtml(p.url)}" />`).join('')}</div>`
        : `<div style="border:1px solid #000;padding:40px;text-align:center;color:#666;">No documentation photos attached.</div>`;

    return headerBlock('PMD-PRJ-FRM-12', '00', 'November 01, 2025', 'PROJECT COMPLETION SUMMARY') + `
    <p style="margin:10px 0;"><strong>Date Prepared:</strong> ${escapeHtml(fmtLong(c?.date_prepared))}
        <span style="float:right;">Reference Number: ${escapeHtml(c?.reference_no || '')}</span></p>

    <table class="box" style="margin-bottom:12px;">
        <tr><td class="hd" style="width:140px;">Project Number</td><td>${escapeHtml(project.project_no)}</td><td class="hd" style="width:130px;">Job Site/Location</td><td>${escapeHtml(project.site)}</td></tr>
        <tr><td class="hd">Project Title</td><td>${escapeHtml(project.title)}</td><td class="hd">Project Owner</td><td>${escapeHtml(project.owner_name)}</td></tr>
        <tr><td class="hd">Sub-Project Title</td><td>${escapeHtml(c?.sub_project_title || 'N/A')}</td><td class="hd">Contractor</td><td>${escapeHtml(c?.contractor || '')}</td></tr>
        <tr><td class="hd">Project Classification</td><td>${escapeHtml(c?.classification || '')}</td><td class="hd">Request Date</td><td>${escapeHtml(fmtShort(c?.request_date))}</td></tr>
    </table>

    <table style="width:100%;border-collapse:collapse;"><tr>
    <td style="width:49%;vertical-align:top;">
        <table class="box"><tr><th class="hd" colspan="2">PLANNING STATUS</th></tr>
            ${boxRow('Baseline Start Date', fmtShort(c?.plan_baseline_start))}
            ${boxRow('Baseline End Date', fmtShort(c?.plan_baseline_end))}
            ${boxRow('Actual Start Date', fmtShort(c?.plan_actual_start))}
            ${boxRow('Actual End Date', fmtShort(c?.plan_actual_end))}
            ${boxRow('Slippage (days)', planSlip == null ? '' : String(planSlip))}
        </table>
        <table class="box" style="margin-top:10px;"><tr><th class="hd" colspan="2">PROJECT COST</th></tr>
            ${boxRow('Cost Code', project.cost_code)}
            ${boxRow('Baseline Amount', money(baseline))}
            ${boxRow('Actual Amount', money(actual))}
            ${boxRow('Variance', variance)}
            ${boxRow('Payment Status', c?.payment_status || '')}
        </table>
        <table class="box" style="margin-top:10px;"><tr><th class="hd" colspan="2">CONSTRUCTION STATUS</th></tr>
            ${boxRow('Baseline Start Date', fmtShort(c?.con_baseline_start))}
            ${boxRow('Baseline End Date', fmtShort(c?.con_baseline_end))}
            ${boxRow('Actual Start Date', fmtShort(c?.con_actual_start))}
            ${boxRow('Actual End Date', fmtShort(c?.con_actual_end))}
            ${boxRow('Slippage (days)', conSlip == null ? '' : String(conSlip))}
            ${boxRow('Timeline Status', timeline)}
            ${boxRow('Completion Status', c?.completion_status || '')}
        </table>
    </td>
    <td style="width:2%;"></td>
    <td style="vertical-align:top;">
        <table class="box"><tr><th class="hd">DOCUMENTATION</th></tr><tr><td>${photoHtml}</td></tr></table>
    </td>
    </tr></table>

    <div class="sig">
        ${sigCell('Prepared by:', s.prepared_by, 'Project Management Engineer')}
        ${sigCell('Checked by:', s.pmd_assistant_manager, 'PMD Assistant Manager')}
        ${sigCell('Noted by:', s.pmd_manager, 'PMD Manager')}
    </div>
    <div class="sig two">
        ${sigCell('Endorsed by:', s.ecs_division_manager, 'ECS Division Manager')}
        ${sigCell('Acknowledged by:', c?.acknowledged_by || '', 'Project Owner – Department Manager')}
    </div>`;
}

export function printAcceptanceCertificate(project: ProjectLite, c: CompletionData | null, s: Signatories) {
    openPrint(`Acceptance Certificate — ${project.project_no}`, acceptanceHtml(project, c, s));
}
export function printCompletionSummary(project: ProjectLite, c: CompletionData | null, s: Signatories) {
    openPrint(`Completion Summary — ${project.project_no}`, summaryHtml(project, c, s));
}

// ── Panel shown on the project page when status is COMPLETED ─────────────────
export function CompletionPanel({ project, completion, signatories, canEdit }: {
    project: ProjectLite;
    completion: CompletionData | null;
    signatories: Signatories;
    canEdit: boolean;
}) {
    const [showModal, setShowModal] = useState(false);
    const filled = !!completion;

    const btn = (bg: string, color: string, border: string): React.CSSProperties => ({
        padding: '8px 16px', borderRadius: '8px', border: `1px solid ${border}`, background: bg, color,
        fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '6px',
    });

    return (
        <div style={{ margin: '0 30px 20px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '12px', padding: '16px 20px' }}>
            {showModal && (
                <CompletionModal project={project} completion={completion} signatories={signatories} onClose={() => setShowModal(false)} />
            )}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px', flexWrap: 'wrap' }}>
                <div>
                    <div style={{ fontSize: '13.5px', fontWeight: 800, color: '#166534', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Project Completed — Completion Documents
                    </div>
                    <div style={{ fontSize: '12px', color: '#15803d', marginTop: '3px' }}>
                        {filled
                            ? 'Completion details saved. You can print the certificates or edit the details.'
                            : 'Fill in the completion details to generate the printable certificates.'}
                    </div>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    {canEdit && (
                        <button onClick={() => setShowModal(true)} style={btn('#fff', '#166534', '#86efac')}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            {filled ? 'Edit Details' : 'Fill Details'}
                        </button>
                    )}
                    <button onClick={() => printAcceptanceCertificate(project, completion, signatories)} disabled={!filled}
                        title={filled ? '' : 'Fill in the completion details first'}
                        style={{ ...btn(filled ? '#16a34a' : '#f1f5f9', filled ? '#fff' : '#94a3b8', filled ? '#16a34a' : '#e2e8f0'), cursor: filled ? 'pointer' : 'not-allowed' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Acceptance Certificate
                    </button>
                    <button onClick={() => printCompletionSummary(project, completion, signatories)} disabled={!filled}
                        title={filled ? '' : 'Fill in the completion details first'}
                        style={{ ...btn(filled ? '#0f766e' : '#f1f5f9', filled ? '#fff' : '#94a3b8', filled ? '#0f766e' : '#e2e8f0'), cursor: filled ? 'pointer' : 'not-allowed' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Completion Summary
                    </button>
                </div>
            </div>
        </div>
    );
}
