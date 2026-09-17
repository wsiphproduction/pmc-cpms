import { router, usePage } from '@inertiajs/react';
import { ReactNode, useCallback, useEffect, useRef, useState } from 'react';
import TourOverlay, { anchorElement } from './TourOverlay';
import { TOURS, TOUR_ROLES, stepsFor, type TourStep } from './tours';

// Guided tours for the department-side roles: a welcome offer on their first
// sign-in, a Guide button in the bottom-right corner of every page, and the
// tour itself. Everyone else gets nothing from this — the layout renders the
// children untouched.

interface PageProps {
    auth: { user: { name: string; role?: string | null; tour_seen_at?: string | null } | null };
    [key: string]: unknown;
}

// Once the welcome offer is answered the server remembers it; this covers the
// moment before that reply lands, and any navigation in between.
const WELCOMED_KEY = 'cpms.tour.welcomed';
// Set before jumping to a page whose tour should start on arrival.
const AUTOSTART_KEY = 'cpms.tour.autostart';

const xsrfToken = () =>
    decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

function session(key: string): string | null {
    try { return window.sessionStorage.getItem(key); } catch { return null; }
}

function remember(key: string, value: string | null) {
    try {
        if (value === null) window.sessionStorage.removeItem(key);
        else window.sessionStorage.setItem(key, value);
    } catch { /* private mode etc.: the server-side flag still covers it */ }
}

export default function TourProvider({ children }: { children: ReactNode }) {
    const { component, url, props } = usePage<PageProps>();
    const user = props.auth?.user;
    const role = user?.role ?? null;
    const eligible = !!role && (TOUR_ROLES as string[]).includes(role);

    const [steps, setSteps] = useState<TourStep[] | null>(null);
    const [index, setIndex] = useState(0);
    const [welcomeOpen, setWelcomeOpen] = useState(false);

    const tour = TOURS[component];
    const firstName = user?.name?.split(' ')[0] ?? '';

    const end = useCallback(() => setSteps(null), []);

    // Resolve the current page's tour for this role, dropping steps whose
    // anchor is not on the page right now.
    const start = useCallback(() => {
        const available = stepsFor(component, role).filter(step => !step.target || anchorElement(step.target));
        if (available.length === 0) return;
        setIndex(0);
        setSteps(available);
    }, [component, role]);

    // Moving on skips any anchor that has gone since the tour started.
    const goTo = useCallback((next: number, direction: 1 | -1) => {
        if (!steps) return;
        let i = next;
        while (i >= 0 && i < steps.length) {
            const step = steps[i];
            if (!step.target || anchorElement(step.target)) {
                setIndex(i);
                return;
            }
            i += direction;
        }
        // Ran off the end: the tour is over. Ran off the start: stay put.
        if (i >= steps.length) setSteps(null);
    }, [steps]);

    const onNext = useCallback(() => goTo(index + 1, 1), [goTo, index]);
    const onBack = useCallback(() => goTo(index - 1, -1), [goTo, index]);

    // A tour belongs to the page it started on.
    useEffect(() => { setSteps(null); }, [component, url]);

    // First sign-in: offer the tour once. The offer is recorded as answered
    // whichever way it goes, so it never comes back — the Guide button covers
    // later replays.
    useEffect(() => {
        if (!eligible || user?.tour_seen_at || session(WELCOMED_KEY)) return;
        setWelcomeOpen(true);
    }, [eligible, user?.tour_seen_at]);

    const answerWelcome = (takeTour: boolean) => {
        setWelcomeOpen(false);
        remember(WELCOMED_KEY, '1');
        fetch(route('tour.seen'), {
            method: 'PATCH',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json' },
        }).catch(() => { /* the next sign-in simply offers again */ });

        if (takeTour) window.setTimeout(start, 150);
    };

    // A page can be asked to start its tour on arrival (the System overview
    // link on the Guide panel sends people to the Dashboard this way).
    const startRef = useRef(start);
    startRef.current = start;
    useEffect(() => {
        if (!eligible || session(AUTOSTART_KEY) !== component) return;
        remember(AUTOSTART_KEY, null);
        const t = window.setTimeout(() => startRef.current(), 350);
        return () => window.clearTimeout(t);
    }, [eligible, component]);

    if (!eligible) return <>{children}</>;

    const active = steps !== null && steps[index] !== undefined;

    return (
        <>
            {children}

            <GuideFloater
                pageName={tour?.name ?? null}
                summary={tour?.summary ?? null}
                stepTitles={stepsFor(component, role).filter(s => s.target && s.target !== 'tour-floater').map(s => s.title)}
                onDashboard={component === 'dashboard'}
                onStart={start}
                hidden={welcomeOpen}
                pulse={!user?.tour_seen_at && !session(WELCOMED_KEY)}
            />

            {welcomeOpen && (
                <WelcomeModal firstName={firstName} onAnswer={answerWelcome} />
            )}

            {active && (
                <TourOverlay
                    step={steps![index]}
                    index={index}
                    count={steps!.length}
                    onNext={onNext}
                    onBack={onBack}
                    onClose={end}
                />
            )}
        </>
    );
}

// ── First-login welcome ─────────────────────────────────────────────────────
function WelcomeModal({ firstName, onAnswer }: { firstName: string; onAnswer: (takeTour: boolean) => void }) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onAnswer(false); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onAnswer]);

    return (
        <div className="print-hide" role="dialog" aria-modal="true" aria-labelledby="cpms-welcome-title"
            style={{ position: 'fixed', inset: 0, zIndex: 9100, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '18px', fontFamily: "'Inter', sans-serif" }}>
            <style>{`@keyframes cpmsWelcomeIn { from { opacity: 0; transform: translateY(10px) scale(0.98); } to { opacity: 1; transform: none; } }`}</style>
            <div style={{ position: 'absolute', inset: 0, background: 'rgba(15,23,42,0.55)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: '460px', background: '#fff', borderRadius: '14px', boxShadow: '0 30px 80px rgba(15,23,42,0.35)', overflow: 'hidden', animation: 'cpmsWelcomeIn 0.25s ease' }}>
                <div style={{ background: 'linear-gradient(135deg, #1e3a8a, #2563eb)', padding: '26px 26px 22px', color: '#fff' }}>
                    <div style={{ width: '44px', height: '44px', borderRadius: '12px', background: 'rgba(255,255,255,0.16)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '14px' }}>
                        <CompassIcon size={22} />
                    </div>
                    <div id="cpms-welcome-title" style={{ fontSize: '19px', fontWeight: 800, letterSpacing: '-0.3px', marginBottom: '4px' }}>
                        Welcome{firstName ? `, ${firstName}` : ''}!
                    </div>
                    <div style={{ fontSize: '13px', color: 'rgba(255,255,255,0.85)', lineHeight: 1.55 }}>
                        This looks like your first time in the Construction Project Management System.
                    </div>
                </div>
                <div style={{ padding: '20px 26px 22px' }}>
                    <p style={{ fontSize: '13.5px', color: '#334155', lineHeight: 1.65, margin: '0 0 8px' }}>
                        Would you like a quick tour? It points out the parts of each page — where to raise a request,
                        follow its approval, view projects and sign Notices to Proceed.
                    </p>
                    <p style={{ fontSize: '12.5px', color: '#64748b', lineHeight: 1.6, margin: '0 0 18px' }}>
                        You can start a tour any time from the <strong style={{ color: '#2563eb' }}>Guide</strong> button at the bottom right of the screen.
                    </p>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                        <button
                            type="button"
                            onClick={() => onAnswer(false)}
                            style={{ padding: '9px 16px', borderRadius: '8px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '13px', fontWeight: 600, color: '#374151', cursor: 'pointer', fontFamily: 'inherit' }}
                        >
                            Maybe later
                        </button>
                        <button
                            type="button"
                            onClick={() => onAnswer(true)}
                            autoFocus
                            style={{ padding: '9px 20px', borderRadius: '8px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: '7px' }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Show me around
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ── The Guide button and its panel ──────────────────────────────────────────
function GuideFloater({ pageName, summary, stepTitles, onDashboard, onStart, hidden, pulse }: {
    pageName: string | null;
    summary: string | null;
    stepTitles: string[];
    onDashboard: boolean;
    onStart: () => void;
    hidden: boolean;
    pulse: boolean;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onClick = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onClick);
        window.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onClick);
            window.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const startHere = () => { setOpen(false); onStart(); };

    const systemOverview = () => {
        setOpen(false);
        remember(AUTOSTART_KEY, 'dashboard');
        router.visit(route('dashboard'));
    };

    if (hidden) return null;

    return (
        <div ref={ref} className="print-hide" style={{ position: 'fixed', right: '22px', bottom: '22px', zIndex: 150, fontFamily: "'Inter', sans-serif" }}>
            <style>{`
                @keyframes cpmsGuidePulse { 0% { box-shadow: 0 0 0 0 rgba(37,99,235,0.45); } 70% { box-shadow: 0 0 0 12px rgba(37,99,235,0); } 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); } }
                @keyframes cpmsGuidePanel { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
            `}</style>

            {open && (
                <div role="dialog" aria-label="Quick guide" style={{
                    position: 'absolute', right: 0, bottom: '54px', width: '300px', maxWidth: 'calc(100vw - 44px)',
                    background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px',
                    boxShadow: '0 20px 50px rgba(15,23,42,0.22)', overflow: 'hidden',
                    animation: 'cpmsGuidePanel 0.18s ease',
                }}>
                    <div style={{ padding: '14px 16px 12px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'flex-start', gap: '10px' }}>
                        <span style={{ width: '30px', height: '30px', borderRadius: '8px', background: '#eff6ff', color: '#2563eb', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                            <CompassIcon size={16} />
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.6px' }}>Quick Guide</div>
                            <div style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a', marginTop: '1px' }}>{pageName ?? 'This page'}</div>
                            {summary && <div style={{ fontSize: '12px', color: '#64748b', marginTop: '3px', lineHeight: 1.5 }}>{summary}</div>}
                        </div>
                    </div>

                    {stepTitles.length > 0 ? (
                        <div style={{ padding: '10px 16px', maxHeight: '220px', overflowY: 'auto' }}>
                            <div style={{ fontSize: '10.5px', fontWeight: 800, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '6px' }}>On this page</div>
                            <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                {stepTitles.map((title, i) => (
                                    <li key={i} style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12.5px', color: '#334155' }}>
                                        <span style={{ width: '5px', height: '5px', borderRadius: '50%', background: '#2563eb', flexShrink: 0 }} />
                                        {title}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <div style={{ padding: '14px 16px', fontSize: '12.5px', color: '#64748b', lineHeight: 1.5 }}>
                            There is no tour for this page yet. The Dashboard tour introduces the whole system.
                        </div>
                    )}

                    <div style={{ padding: '12px 16px 14px', borderTop: '1px solid #f3f4f6', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        {stepTitles.length > 0 && (
                            <button
                                type="button"
                                onClick={startHere}
                                style={{ width: '100%', padding: '9px 14px', borderRadius: '8px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '13px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '7px' }}
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Tour this page
                            </button>
                        )}
                        {!onDashboard && (
                            <button
                                type="button"
                                onClick={systemOverview}
                                style={{ width: '100%', padding: '8px 14px', borderRadius: '8px', border: '1px solid #e5e7eb', background: '#fff', color: '#374151', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer', fontFamily: 'inherit' }}
                            >
                                System overview (on the Dashboard)
                            </button>
                        )}
                        <a
                            href={route('manual.index')}
                            onClick={e => { e.preventDefault(); setOpen(false); router.visit(route('manual.index')); }}
                            style={{ fontSize: '12px', fontWeight: 600, color: '#2563eb', textDecoration: 'none', textAlign: 'center', marginTop: '2px' }}
                        >
                            Read the user manual →
                        </a>
                    </div>
                </div>
            )}

            <button
                type="button"
                data-tour="tour-floater"
                onClick={() => setOpen(o => !o)}
                aria-expanded={open}
                aria-label="Quick guide"
                title="Quick guide and page tour"
                style={{
                    display: 'inline-flex', alignItems: 'center', gap: '7px',
                    height: '42px', padding: '0 16px 0 13px', borderRadius: '999px', border: 'none',
                    background: open ? '#1d4ed8' : '#2563eb', color: '#fff',
                    fontSize: '13px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit',
                    boxShadow: '0 10px 24px rgba(37,99,235,0.35)',
                    animation: pulse && !open ? 'cpmsGuidePulse 2s ease-out infinite' : 'none',
                    transition: 'background 0.15s',
                }}
            >
                <CompassIcon size={17} />
                Guide
            </button>
        </div>
    );
}

function CompassIcon({ size }: { size: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
        </svg>
    );
}
