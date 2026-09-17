import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

interface Manual {
    slug: string;
    title: string;
    audience: string;
    highlights: string[];
    sections: number;
    is_mine: boolean;
    available: boolean;
    url: string | null;
    size_kb: number | null;
    updated_at: string | null;
}

interface Props {
    manuals: Manual[];
    mine: string;
    version: string;
    docNo: string;
}

function BookIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
        </svg>
    );
}

export default function ManualIndex({ manuals, mine, version, docNo }: Props) {
    const [active, setActive] = useState<string>(
        manuals.find(m => m.is_mine && m.available)?.slug
            ?? manuals.find(m => m.available)?.slug
            ?? manuals[0]?.slug
            ?? '',
    );

    const selected = manuals.find(m => m.slug === active);

    return (
        <AuthenticatedLayout>
            <Head title="User Manual" />

            <div style={{ marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    User Manual
                </h1>
                <p style={{ fontSize: '12.5px', color: '#9ca3af', margin: '4px 0 0' }}>
                    How CPMS works, written for the way each role uses it. {docNo} · Version {version}
                </p>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(230px, 300px) 1fr', gap: '18px', alignItems: 'start' }}>

                {/* ── The booklets on offer ───────────────────────────────── */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    {manuals.map(manual => {
                        const isActive = manual.slug === active;

                        return (
                            <button
                                key={manual.slug}
                                type="button"
                                onClick={() => manual.available && setActive(manual.slug)}
                                disabled={!manual.available}
                                style={{
                                    textAlign: 'left',
                                    background: isActive ? '#fffdf5' : '#fff',
                                    border: `1.5px solid ${isActive ? '#d4a017' : '#e5e7eb'}`,
                                    borderRadius: '12px',
                                    padding: '13px 15px',
                                    cursor: manual.available ? 'pointer' : 'not-allowed',
                                    opacity: manual.available ? 1 : 0.55,
                                    fontFamily: 'inherit',
                                    width: '100%',
                                }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: isActive ? '#a16207' : '#64748b' }}>
                                    <BookIcon />
                                    <span style={{ fontSize: '13px', fontWeight: 800, color: '#0f172a', flex: 1 }}>
                                        {manual.title}
                                    </span>
                                </div>

                                {manual.is_mine && (
                                    <span style={{
                                        display: 'inline-block', marginTop: '7px', background: '#fef3c7', color: '#92400e',
                                        fontSize: '9.5px', fontWeight: 800, letterSpacing: '0.5px', textTransform: 'uppercase',
                                        padding: '2.5px 7px', borderRadius: '20px',
                                    }}>
                                        For your role
                                    </span>
                                )}

                                <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '7px' }}>
                                    {manual.available
                                        ? `${manual.sections} sections · ${manual.size_kb} KB · ${manual.updated_at}`
                                        : 'Not yet published'}
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* ── The reader ──────────────────────────────────────────── */}
                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden' }}>
                    {!selected?.available ? (
                        <div style={{ padding: '40px 26px', textAlign: 'center' }}>
                            <div style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a', marginBottom: '6px' }}>
                                This manual has not been published yet
                            </div>
                            <p style={{ fontSize: '12.5px', color: '#64748b', margin: 0, lineHeight: 1.6 }}>
                                Ask your administrator to run <code style={{ background: '#f1f5f9', padding: '1px 5px', borderRadius: '4px' }}>php artisan manuals:build</code>,
                                which renders the manuals into the public folder.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div style={{ padding: '16px 20px', borderBottom: '1px solid #e5e7eb', display: 'flex', gap: '14px', alignItems: 'flex-start', flexWrap: 'wrap' }}>
                                <div style={{ flex: '1 1 320px', minWidth: 0 }}>
                                    <div style={{ fontSize: '14.5px', fontWeight: 800, color: '#0f172a' }}>
                                        CPMS User Manual — {selected.title}
                                    </div>
                                    <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 0', lineHeight: 1.55 }}>
                                        {selected.audience}
                                    </p>
                                </div>

                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <a
                                        href={selected.url!}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style={{
                                            background: '#0f172a', color: '#fff', fontSize: '12px', fontWeight: 700,
                                            padding: '8px 14px', borderRadius: '8px', textDecoration: 'none', whiteSpace: 'nowrap',
                                        }}
                                    >
                                        Open in new tab
                                    </a>
                                    <a
                                        href={selected.url!}
                                        download
                                        style={{
                                            background: '#fff', color: '#334155', fontSize: '12px', fontWeight: 700,
                                            padding: '8px 14px', borderRadius: '8px', textDecoration: 'none',
                                            border: '1.5px solid #e5e7eb', whiteSpace: 'nowrap',
                                        }}
                                    >
                                        Download
                                    </a>
                                </div>
                            </div>

                            {selected.highlights.length > 0 && (
                                <ul style={{
                                    margin: 0, padding: '12px 20px 12px 38px', background: '#fafbfc',
                                    borderBottom: '1px solid #e5e7eb', fontSize: '12px', color: '#475569', lineHeight: 1.65,
                                }}>
                                    {selected.highlights.map((line, i) => <li key={i}>{line}</li>)}
                                </ul>
                            )}

                            {/* The PDF itself, read without leaving the system. */}
                            <iframe
                                key={selected.slug}
                                src={`${selected.url}#view=FitH`}
                                title={`CPMS User Manual — ${selected.title}`}
                                style={{ width: '100%', height: '78vh', minHeight: '520px', border: 'none', display: 'block' }}
                            />
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
