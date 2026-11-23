import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * A contractor's view of one non-compliance record, reached from the emailed
 * link — no account, no login. It shows what was found and what is required,
 * and takes the contractor's explanation and supporting documents.
 */

interface PageProps {
    finding: {
        control_no: string;
        contractor_name: string;
        category: string | null;
        deficiency: string;
        corrective_action: string;
        date_identified: string;
        target_date: string | null;
        status: 'open' | 'responded' | 'closed';
        status_label: string;
        contractor_response: string | null;
        responded_at: string | null;
        response_files: { id: number; filename: string; url: string }[];
        closure_date: string | null;
        closure_remarks: string | null;
        token: string;
    };
    project: { project_no: string; title: string; site: string };
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
    [key: string]: unknown;
}

function Card({ children }: { children: React.ReactNode }) {
    return <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '22px 24px', boxShadow: '0 2px 10px rgba(15,23,42,0.04)' }}>{children}</div>;
}

function Label({ children }: { children: React.ReactNode }) {
    return <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '5px' }}>{children}</div>;
}

export default function ContractorFindingPortal() {
    const { finding, project, flash, errors } = usePage<PageProps>().props;
    const [response, setResponse] = useState(finding.contractor_response ?? '');
    const [saving, setSaving] = useState(false);
    const filesRef = useRef<HTMLInputElement>(null);
    const closed = finding.status === 'closed';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const fd = new FormData();
        fd.append('contractor_response', response);
        Array.from(filesRef.current?.files ?? []).forEach(f => fd.append('files[]', f));
        router.post(route('contractor-finding.respond', finding.token), fd as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { if (filesRef.current) filesRef.current.value = ''; },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <>
            <Head title={`Non-Compliance Record ${finding.control_no}`} />

            <div style={{ minHeight: '100vh', background: '#f1f5f9', padding: '0 0 60px', fontFamily: "'Segoe UI', system-ui, -apple-system, sans-serif", color: '#0f172a' }}>
                <div style={{ background: '#7f1d1d', padding: '30px 24px 34px' }}>
                    <div style={{ maxWidth: '820px', margin: '0 auto' }}>
                        <div style={{ fontSize: '11px', fontWeight: 800, color: '#fecaca', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '6px' }}>
                            Non-Compliance Record {finding.control_no}
                        </div>
                        <h1 style={{ margin: 0, fontSize: '24px', fontWeight: 800, color: '#fff', lineHeight: 1.25 }}>{project.title}</h1>
                        <div style={{ marginTop: '8px', fontSize: '13px', color: '#fecaca' }}>
                            {project.project_no} · For <strong style={{ color: '#fff' }}>{finding.contractor_name}</strong>
                        </div>
                    </div>
                </div>

                <div style={{ maxWidth: '820px', margin: '-18px auto 0', padding: '0 16px', display: 'flex', flexDirection: 'column', gap: '18px' }}>
                    {flash?.success && (
                        <div style={{ padding: '13px 17px', background: '#dcfce7', border: '1px solid #bbf7d0', borderRadius: '10px', color: '#166534', fontSize: '13px', fontWeight: 700 }}>
                            {flash.success}
                        </div>
                    )}

                    <Card>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '16px', marginBottom: '18px' }}>
                            <div><Label>Status</Label><div style={{ fontSize: '14px', fontWeight: 700 }}>{finding.status_label}</div></div>
                            <div><Label>Date Identified</Label><div style={{ fontSize: '14px', fontWeight: 600 }}>{finding.date_identified}</div></div>
                            <div><Label>Target Date</Label><div style={{ fontSize: '14px', fontWeight: 600 }}>{finding.target_date ?? 'To be advised'}</div></div>
                            <div><Label>Job Site</Label><div style={{ fontSize: '14px', fontWeight: 600 }}>{project.site}</div></div>
                        </div>
                        {finding.category && <div style={{ marginBottom: '14px' }}><Label>Category</Label><div style={{ fontSize: '13.5px' }}>{finding.category}</div></div>}
                        <div style={{ marginBottom: '14px' }}>
                            <Label>Deficiency</Label>
                            <div style={{ fontSize: '13.5px', lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{finding.deficiency}</div>
                        </div>
                        <div>
                            <Label>Required Corrective Action</Label>
                            <div style={{ fontSize: '13.5px', lineHeight: 1.6, whiteSpace: 'pre-wrap', padding: '12px 14px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px' }}>{finding.corrective_action}</div>
                        </div>
                    </Card>

                    {closed ? (
                        <Card>
                            <Label>Closed {finding.closure_date}</Label>
                            <div style={{ fontSize: '13.5px', lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{finding.closure_remarks}</div>
                            {finding.contractor_response && (
                                <div style={{ marginTop: '14px' }}>
                                    <Label>Your Response</Label>
                                    <div style={{ fontSize: '13.5px', lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{finding.contractor_response}</div>
                                </div>
                            )}
                            <p style={{ fontSize: '12.5px', color: '#64748b', margin: '14px 0 0' }}>This record has been closed by the Project Management Department and no longer takes responses.</p>
                        </Card>
                    ) : (
                        <Card>
                            <form onSubmit={submit}>
                                <div style={{ fontSize: '15px', fontWeight: 800, marginBottom: '4px' }}>{finding.contractor_response ? 'Update Your Response' : 'Your Response'}</div>
                                <p style={{ fontSize: '12.5px', color: '#64748b', margin: '0 0 14px', lineHeight: 1.6 }}>
                                    Explain the deficiency, the corrective action you have taken or will take, and when. Attach any supporting documents — photos, reports, schedules.
                                    {finding.responded_at && ` Last sent ${finding.responded_at}.`}
                                </p>
                                <textarea value={response} onChange={e => setResponse(e.target.value)} rows={7}
                                    style={{ width: '100%', boxSizing: 'border-box', padding: '10px 12px', borderRadius: '8px', border: '1.5px solid #e5e7eb', fontSize: '13.5px', fontFamily: 'inherit', resize: 'vertical' }} />
                                {errors.contractor_response && <div style={{ color: '#dc2626', fontSize: '12px', marginTop: '4px' }}>{errors.contractor_response}</div>}
                                {finding.response_files.length > 0 && (
                                    <div style={{ marginTop: '10px', fontSize: '12.5px' }}>
                                        <span style={{ color: '#64748b', fontWeight: 700 }}>Already attached: </span>
                                        {finding.response_files.map((f, i) => <span key={f.id}>{i > 0 && ', '}<a href={f.url} target="_blank" rel="noreferrer" style={{ color: '#2563eb' }}>{f.filename}</a></span>)}
                                    </div>
                                )}
                                <div style={{ marginTop: '12px' }}>
                                    <Label>Attach documents (optional, up to 20 MB each)</Label>
                                    <input type="file" multiple ref={filesRef} style={{ fontSize: '13px' }} />
                                    {Object.entries(errors).filter(([k]) => k.startsWith('files')).map(([k, v]) => <div key={k} style={{ color: '#dc2626', fontSize: '12px', marginTop: '4px' }}>{v}</div>)}
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
                                    <button type="submit" disabled={saving}
                                        style={{ padding: '10px 22px', borderRadius: '8px', border: 'none', background: '#b91c1c', color: '#fff', fontWeight: 800, fontSize: '13.5px', cursor: 'pointer', fontFamily: 'inherit' }}>
                                        {saving ? 'Sending…' : 'Send Response'}
                                    </button>
                                </div>
                            </form>
                        </Card>
                    )}

                    <p style={{ fontSize: '11.5px', color: '#94a3b8', textAlign: 'center', margin: 0 }}>
                        This page is private to your company. Please do not forward the link.
                    </p>
                </div>
            </div>
        </>
    );
}
