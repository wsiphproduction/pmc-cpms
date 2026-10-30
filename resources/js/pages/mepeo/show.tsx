import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import ApprovalTimeline from '@/components/ApprovalTimeline';
import { useConfirm } from '@/components/useConfirm';
import {
    EMS_TONE, EmsAttachmentsField, EmsBody, EmsBodyForm, EmsFileChanges, EmsHeaderGrid, EmsOptions, EmsReportRow, EmsReportSheet,
    EmsSignModal, EmsStatusBadge, emsBodyOf, emsPayload,
} from '@/components/EmsReport';

interface Props {
    report: EmsReportRow;
    options: EmsOptions;
    /** The representative it is with, while it is with them. */
    can_edit: boolean;
    /** The MEPEO Manager, while it waits on their approval. */
    can_sign: boolean;
}

const sectionTitle: React.CSSProperties = { fontSize: '11.5px', fontWeight: 800, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.4px', margin: '0 0 6px' };

export default function MepeoShow({ report, options, can_edit, can_sign }: Props) {
    const [body, setBody] = useState<EmsBody>(() => emsBodyOf(report));
    const [fileChanges, setFileChanges] = useState<EmsFileChanges>({ files: [], removed: [] });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState<'save' | 'submit' | null>(null);
    const [signMode, setSignMode] = useState<'sign' | 'return' | null>(null);
    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const set = <K extends keyof EmsBody>(key: K, value: EmsBody[K]) => {
        setBody(b => ({ ...b, [key]: value }));
        setErrors(e => ({ ...e, [key]: '' }));
    };

    const save = () => {
        setBusy('save');
        router.post(route('mepeo.reports.update', report.id), emsPayload(body, fileChanges, { _method: 'patch' }), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { setFileChanges({ files: [], removed: [] }); setErrors({}); },
            onError: errs => setErrors(errs),
            onFinish: () => setBusy(null),
        });
    };

    const submit = () => {
        showConfirm(
            `Submit ${report.control_no} to the MEPEO Manager for approval? You can no longer change it unless it is returned to you.`,
            () => {
                setBusy('submit');
                router.post(route('mepeo.reports.submit', report.id), emsPayload(body, fileChanges), {
                    forceFormData: true,
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
                        <EmsBodyForm body={body} onChange={set} options={options} errors={errors} />

                        <div>
                            <div style={sectionTitle}>Supporting Documents</div>
                            <EmsAttachmentsField existing={report.attachments} changes={fileChanges} onChange={setFileChanges} errors={errors} />
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
