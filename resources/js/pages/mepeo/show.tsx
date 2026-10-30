import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import ApprovalTimeline from '@/components/ApprovalTimeline';
import { useConfirm } from '@/components/useConfirm';
import {
    EMS_TONE, EmsHeaderGrid, EmsOption, EmsOptions, EmsReportRow, EmsReportSheet, EmsSignModal, EmsStatusBadge,
} from '@/components/EmsReport';

interface Props {
    report: EmsReportRow;
    options: EmsOptions;
    /** The representative it is with, while it is with them. */
    can_edit: boolean;
    /** The MEPEO Manager, while it waits on their approval. */
    can_sign: boolean;
}

interface Body {
    major_activities: string[];
    major_activities_other: string;
    other_scope: string;
    aspects: string[];
    aspects_other: string;
    impacts: string;
    mitigation: string;
    mepeo_status: string;
    remarks: string;
}

const band: React.CSSProperties = { background: '#fef08a', color: '#1f2937', fontSize: '11.5px', fontWeight: 800, textAlign: 'center', padding: '7px 10px', borderBottom: '1px solid #e5e7eb' };
const frame: React.CSSProperties = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', background: '#fff' };
const col: React.CSSProperties = { display: 'flex', flexDirection: 'column', borderRight: '1px solid #e5e7eb', minWidth: 0 };
const textarea: React.CSSProperties = { flex: 1, minHeight: '120px', width: '100%', boxSizing: 'border-box', border: 'none', outline: 'none', padding: '10px 12px', fontSize: '13px', fontFamily: 'inherit', resize: 'vertical', lineHeight: 1.5 };
const otherInput: React.CSSProperties = { flex: 1, minWidth: 0, border: 'none', borderBottom: '1.5px solid #cbd5e1', outline: 'none', padding: '3px 4px', fontSize: '12.5px', fontFamily: 'inherit', background: 'transparent' };

function CheckGroup({ options, value, onChange, other, onOther, error }: {
    options: EmsOption[];
    value: string[];
    onChange: (next: string[]) => void;
    other: string;
    onOther: (next: string) => void;
    error?: string;
}) {
    const toggle = (key: string) => onChange(value.includes(key) ? value.filter(k => k !== key) : [...value, key]);
    return (
        <div>
            {options.map(o => (
                <label key={o.key} style={{ display: 'flex', alignItems: 'center', gap: '9px', padding: '7px 12px', borderBottom: '1px solid #f1f5f9', fontSize: '12.5px', color: '#1e293b', cursor: 'pointer', background: value.includes(o.key) ? '#f0fdf4' : 'transparent' }}>
                    <input type="checkbox" checked={value.includes(o.key)} onChange={() => toggle(o.key)} style={{ accentColor: EMS_TONE, width: '15px', height: '15px' }} />
                    {o.label}
                </label>
            ))}
            <div style={{ display: 'flex', alignItems: 'center', gap: '9px', padding: '7px 12px', fontSize: '12.5px', color: '#1e293b' }}>
                <input type="checkbox" checked={other.trim() !== ''} readOnly tabIndex={-1} style={{ accentColor: EMS_TONE, width: '15px', height: '15px' }} />
                Others:
                <input type="text" value={other} onChange={e => onOther(e.target.value)} placeholder="Specify, if any" style={otherInput} />
            </div>
            {error && <div style={{ color: '#dc2626', fontSize: '12px', fontWeight: 600, padding: '0 12px 8px' }}>{error}</div>}
        </div>
    );
}

export default function MepeoShow({ report, options, can_edit, can_sign }: Props) {
    const [body, setBody] = useState<Body>({
        major_activities:       report.major_activities ?? [],
        major_activities_other: report.major_activities_other ?? '',
        other_scope:            report.other_scope ?? '',
        aspects:                report.aspects ?? [],
        aspects_other:          report.aspects_other ?? '',
        impacts:                report.impacts ?? '',
        mitigation:             report.mitigation ?? '',
        mepeo_status:           report.mepeo_status ?? '',
        remarks:                report.remarks ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState<'save' | 'submit' | null>(null);
    const [signMode, setSignMode] = useState<'sign' | 'return' | null>(null);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const set = <K extends keyof Body>(key: K) => (value: Body[K]) => {
        setBody(b => ({ ...b, [key]: value }));
        setErrors(e => ({ ...e, [key]: '' }));
    };

    const payload = () => ({ ...body, mepeo_status: body.mepeo_status || null });

    const save = () => {
        setBusy('save');
        router.patch(route('mepeo.reports.update', report.id), payload(), {
            preserveScroll: true,
            onError: errs => setErrors(errs),
            onFinish: () => setBusy(null),
        });
    };

    const submit = () => {
        showConfirm(
            `Submit ${report.control_no} to the MEPEO Manager for approval? You can no longer change it unless it is returned to you.`,
            () => {
                setBusy('submit');
                router.post(route('mepeo.reports.submit', report.id), payload(), {
                    onError: errs => setErrors(errs),
                    onFinish: () => setBusy(null),
                });
            },
            { title: 'Submit for Approval', confirmLabel: 'Submit', variant: 'info' },
        );
    };

    const errorList = Object.values(errors).filter(Boolean);

    return (
        <AuthenticatedLayout>
            <Head title={`EMS Survey Report ${report.control_no}`} />
            {confirmDialog}
            {signMode && <EmsSignModal report={report} mode={signMode} onClose={() => setSignMode(null)} />}

            <div style={{ marginBottom: '14px' }}>
                <Link href={route('mepeo.index')} style={{ fontSize: '12.5px', color: '#64748b', textDecoration: 'none', fontWeight: 600 }}>← EMS Survey Reports</Link>
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '16px', flexWrap: 'wrap', marginBottom: '16px' }}>
                <div style={{ minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                        <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0 }}>Environmental Impact Assessment</h1>
                        <EmsStatusBadge row={report} />
                    </div>
                    <div style={{ fontSize: '12.5px', color: '#64748b', marginTop: '4px' }}>
                        PMD-PRJ-FRM-19 · Prepared by {report.prepared_by ?? '—'}
                        {report.mepeo_rep ? ` · MEPEO Representative: ${report.mepeo_rep.name}` : ''}
                    </div>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    <a href={report.print_url} target="_blank" rel="noreferrer" style={{ padding: '8px 16px', borderRadius: '7px', border: '1px solid #e2e8f0', background: '#fff', color: '#374151', fontSize: '12.5px', fontWeight: 700, textDecoration: 'none' }}>
                        Print FRM-19
                    </a>
                    {can_sign && (
                        <>
                            <button type="button" onClick={() => setSignMode('return')} style={{ padding: '8px 16px', borderRadius: '7px', border: '1px solid #fecaca', background: '#fef2f2', color: '#dc2626', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                                Return for Revision
                            </button>
                            <button type="button" onClick={() => setSignMode('sign')} style={{ padding: '8px 18px', borderRadius: '7px', border: 'none', background: '#059669', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }}>
                                ✓ Approve
                            </button>
                        </>
                    )}
                </div>
            </div>

            {report.return_remarks && report.status === 'with_mepeo' && (
                <div style={{ fontSize: '13px', color: '#991b1b', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '10px', padding: '12px 16px', marginBottom: '14px' }}>
                    <strong>Returned for revision:</strong> {report.return_remarks}
                </div>
            )}

            <EmsHeaderGrid row={report} />

            {report.approvals.length > 0 && (
                <div style={{ margin: '14px 0 0' }}>
                    <ApprovalTimeline steps={report.approvals} />
                </div>
            )}

            <div style={{ marginTop: '16px' }}>
                {!can_edit ? (
                    <EmsReportSheet row={report} options={options} />
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                        <div style={frame}>
                            <div style={col}>
                                <div style={band}>Major Activities</div>
                                <CheckGroup
                                    options={options.major_activities}
                                    value={body.major_activities}
                                    onChange={set('major_activities')}
                                    other={body.major_activities_other}
                                    onOther={set('major_activities_other')}
                                    error={errors.major_activities}
                                />
                            </div>
                            <div style={{ ...col, borderRight: 'none' }}>
                                <div style={band}>Other Scope of Work</div>
                                <textarea value={body.other_scope} onChange={e => set('other_scope')(e.target.value)} style={textarea} placeholder="Describe any other scope of work…" />
                            </div>
                        </div>

                        <div style={frame}>
                            <div style={col}>
                                <div style={band}>Environmental Aspects</div>
                                <CheckGroup
                                    options={options.aspects}
                                    value={body.aspects}
                                    onChange={set('aspects')}
                                    other={body.aspects_other}
                                    onOther={set('aspects_other')}
                                    error={errors.aspects}
                                />
                            </div>
                            <div style={{ ...col, borderRight: 'none' }}>
                                <div style={band}>Environmental Impacts</div>
                                <textarea value={body.impacts} onChange={e => set('impacts')(e.target.value)} style={textarea} placeholder="The expected impacts on the environment…" />
                                <div style={{ ...band, borderTop: '1px solid #e5e7eb' }}>Mitigation</div>
                                <textarea value={body.mitigation} onChange={e => set('mitigation')(e.target.value)} style={textarea} placeholder="How each impact will be mitigated…" />
                            </div>
                        </div>

                        <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                            <div style={band}>Status</div>
                            {options.statuses.map(s => (
                                <label key={s.key} style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '9px 12px', borderBottom: '1px solid #f1f5f9', fontSize: '13px', cursor: 'pointer', background: body.mepeo_status === s.key ? '#f0fdf4' : 'transparent' }}>
                                    <input type="radio" name="mepeo_status" checked={body.mepeo_status === s.key} onChange={() => set('mepeo_status')(s.key)} style={{ accentColor: EMS_TONE, width: '15px', height: '15px' }} />
                                    {s.label}
                                </label>
                            ))}
                            {errors.mepeo_status && <div style={{ color: '#dc2626', fontSize: '12px', fontWeight: 600, padding: '6px 12px' }}>{errors.mepeo_status}</div>}
                            <div style={{ ...band, borderTop: '1px solid #e5e7eb' }}>Remarks/Comments</div>
                            <textarea value={body.remarks} onChange={e => set('remarks')(e.target.value)} style={{ ...textarea, minHeight: '90px' }} />
                        </div>

                        {errorList.length > 0 && (
                            <div style={{ fontSize: '12.5px', color: '#991b1b', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '10px 14px' }}>
                                {errorList.map((e, i) => <div key={i}>{e}</div>)}
                            </div>
                        )}

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', flexWrap: 'wrap', position: 'sticky', bottom: 0, background: 'linear-gradient(transparent, #f8fafc 30%)', padding: '14px 0 4px' }}>
                            <button type="button" onClick={save} disabled={busy !== null} style={{ padding: '9px 20px', borderRadius: '8px', border: '1px solid #e2e8f0', background: '#fff', color: '#334155', fontSize: '13px', fontWeight: 700, cursor: 'pointer' }}>
                                {busy === 'save' ? 'Saving…' : 'Save Draft'}
                            </button>
                            <button type="button" onClick={submit} disabled={busy !== null} style={{ padding: '9px 22px', borderRadius: '8px', border: 'none', background: EMS_TONE, color: '#fff', fontSize: '13px', fontWeight: 800, cursor: 'pointer' }}>
                                {busy === 'submit' ? 'Submitting…' : 'Submit to MEPEO Manager'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
