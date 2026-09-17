import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { TourStep } from './tours';

// The running tour: a dimmed page with a cut-out around the current step's
// anchor and a card beside it. Written by hand rather than pulled in as a
// library so it looks like the rest of the system and needs no dependency.

/** The anchor's on-screen box, padded a little so the highlight breathes. */
interface Box {
    top: number;
    left: number;
    width: number;
    height: number;
}

type Side = 'top' | 'bottom' | 'left' | 'right' | 'center';

interface Placement {
    top: number;
    left: number;
    side: Side;
    /** Where along the card's edge the arrow sits, in px from that edge's start. */
    arrow: number;
}

const PAD = 6;         // highlight padding around the anchor
const GAP = 14;        // between the highlight and the card
const MARGIN = 12;     // the card never gets closer than this to the viewport edge
const HEADER = 72;     // room for the sticky top bar when scrolling an anchor into view
const CARD_WIDTH = 340;

export function anchorElement(target: string): HTMLElement | null {
    return document.querySelector<HTMLElement>(`[data-tour="${target}"]`);
}

function measure(el: HTMLElement): Box {
    const r = el.getBoundingClientRect();
    return { top: r.top - PAD, left: r.left - PAD, width: r.width + PAD * 2, height: r.height + PAD * 2 };
}

/**
 * Bring the anchor on screen. Centre it when it fits; an anchor taller than
 * the viewport is lined up under the top bar instead, so its start is seen.
 */
function reveal(el: HTMLElement) {
    const r = el.getBoundingClientRect();
    const vh = window.innerHeight;

    if (r.height > vh - HEADER - MARGIN) {
        window.scrollTo({ top: window.scrollY + r.top - HEADER, behavior: 'auto' });
    } else if (r.top < HEADER || r.bottom > vh - MARGIN) {
        el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
    }
}

const clamp = (n: number, min: number, max: number) => Math.min(Math.max(n, min), max);

interface Props {
    step: TourStep;
    index: number;
    count: number;
    onNext: () => void;
    onBack: () => void;
    onClose: () => void;
}

export default function TourOverlay({ step, index, count, onNext, onBack, onClose }: Props) {
    const [box, setBox] = useState<Box | null>(null);
    const [placement, setPlacement] = useState<Placement | null>(null);
    const cardRef = useRef<HTMLDivElement>(null);

    // Find the anchor, scroll it into view and follow it while the page
    // scrolls or resizes. The provider only hands over steps whose anchor
    // exists, but the page can still change under us, so a missing anchor
    // just falls back to a centred card.
    useLayoutEffect(() => {
        const el = step.target ? anchorElement(step.target) : null;

        if (!el) {
            setBox(null);
            return;
        }

        reveal(el);
        setBox(measure(el));

        let frame = 0;
        const follow = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => setBox(measure(el)));
        };

        window.addEventListener('resize', follow);
        window.addEventListener('scroll', follow, true);

        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('resize', follow);
            window.removeEventListener('scroll', follow, true);
        };
    }, [step]);

    // Place the card once it has a size: below the anchor when there is room,
    // otherwise above, then beside; a centred card when there is no anchor.
    useLayoutEffect(() => {
        const card = cardRef.current;
        if (!card) return;

        const cw = card.offsetWidth;
        const ch = card.offsetHeight;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        if (!box) {
            setPlacement({ top: (vh - ch) / 2, left: (vw - cw) / 2, side: 'center', arrow: 0 });
            return;
        }

        const below = vh - (box.top + box.height);
        const above = box.top;
        const right = vw - (box.left + box.width);
        const left = box.left;

        let side: Side;
        if (below >= ch + GAP + MARGIN) side = 'bottom';
        else if (above >= ch + GAP + MARGIN) side = 'top';
        else if (right >= cw + GAP + MARGIN) side = 'right';
        else if (left >= cw + GAP + MARGIN) side = 'left';
        else side = below >= above ? 'bottom' : 'top';

        const midX = box.left + box.width / 2;
        const midY = box.top + box.height / 2;
        let top: number;
        let x: number;

        switch (side) {
            case 'bottom': top = box.top + box.height + GAP; x = midX - cw / 2; break;
            case 'top':    top = box.top - GAP - ch;         x = midX - cw / 2; break;
            case 'right':  top = midY - ch / 2;              x = box.left + box.width + GAP; break;
            default:       top = midY - ch / 2;              x = box.left - GAP - cw; break;
        }

        x = clamp(x, MARGIN, Math.max(MARGIN, vw - cw - MARGIN));
        top = clamp(top, MARGIN, Math.max(MARGIN, vh - ch - MARGIN));

        // The arrow keeps pointing at the anchor even after the card is
        // nudged back inside the viewport.
        const arrow = side === 'top' || side === 'bottom'
            ? clamp(midX - x - 7, 16, cw - 30)
            : clamp(midY - top - 7, 16, ch - 30);

        setPlacement({ top, left: x, side, arrow });
    }, [box, step]);

    // Keyboard: arrows step, Enter advances, Escape leaves.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') { e.preventDefault(); onClose(); }
            else if (e.key === 'ArrowRight' || e.key === 'Enter') { e.preventDefault(); onNext(); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); onBack(); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onNext, onBack, onClose]);

    const last = index === count - 1;
    const centred = !box;

    // With no anchor the cut-out shrinks to a point mid-screen, so the same
    // shadow dims the whole page and the move to the next anchor animates.
    const hole: Box = box ?? {
        top: typeof window === 'undefined' ? 0 : window.innerHeight / 2,
        left: typeof window === 'undefined' ? 0 : window.innerWidth / 2,
        width: 0,
        height: 0,
    };

    const arrowStyle = (): React.CSSProperties => {
        if (!placement || placement.side === 'center') return { display: 'none' };
        const base: React.CSSProperties = {
            position: 'absolute', width: '14px', height: '14px', background: '#fff',
            transform: 'rotate(45deg)',
        };
        // Only the two edges facing the anchor get a border, so the arrow
        // reads as part of the card's outline.
        const edge = '1px solid #e5e7eb';
        switch (placement.side) {
            case 'bottom': return { ...base, top: '-8px', left: placement.arrow, borderTop: edge, borderLeft: edge };
            case 'top':    return { ...base, bottom: '-8px', left: placement.arrow, borderBottom: edge, borderRight: edge };
            case 'right':  return { ...base, left: '-8px', top: placement.arrow, borderBottom: edge, borderLeft: edge };
            default:       return { ...base, right: '-8px', top: placement.arrow, borderTop: edge, borderRight: edge };
        }
    };

    return (
        <div className="print-hide" role="dialog" aria-modal="true" aria-label={`Guided tour, step ${index + 1} of ${count}: ${step.title}`}
            style={{ position: 'fixed', inset: 0, zIndex: 9000, fontFamily: "'Inter', sans-serif" }}>
            <style>{`
                @keyframes cpmsTourIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
            `}</style>

            {/* Catches every click so the page underneath stays put during the tour. */}
            <div style={{ position: 'absolute', inset: 0 }} />

            {/* The cut-out: its shadow is what dims the rest of the page. */}
            <div style={{
                position: 'fixed',
                top: hole.top, left: hole.left, width: hole.width, height: hole.height,
                borderRadius: '10px',
                boxShadow: '0 0 0 9999px rgba(15,23,42,0.58), 0 0 0 3px rgba(37,99,235,0.85)',
                transition: 'top 0.28s ease, left 0.28s ease, width 0.28s ease, height 0.28s ease',
                pointerEvents: 'none',
            }} />

            <div
                ref={cardRef}
                key={index}
                style={{
                    position: 'fixed',
                    top: placement?.top ?? -9999,
                    left: placement?.left ?? -9999,
                    width: centred ? '400px' : `${CARD_WIDTH}px`,
                    maxWidth: 'calc(100vw - 24px)',
                    background: '#fff',
                    border: '1px solid #e5e7eb',
                    borderRadius: '12px',
                    boxShadow: '0 24px 60px rgba(15,23,42,0.3)',
                    padding: centred ? '26px 26px 20px' : '18px 20px 16px',
                    visibility: placement ? 'visible' : 'hidden',
                    animation: 'cpmsTourIn 0.22s ease',
                    boxSizing: 'border-box',
                }}
            >
                <div style={arrowStyle()} />

                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
                    <span style={{ fontSize: '10.5px', fontWeight: 800, color: '#2563eb', textTransform: 'uppercase', letterSpacing: '0.6px' }}>
                        Step {index + 1} of {count}
                    </span>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="End tour"
                        title="End tour (Esc)"
                        style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#6b7280' }}
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div style={{ fontSize: centred ? '17px' : '14.5px', fontWeight: 800, color: '#0f172a', marginBottom: '6px', letterSpacing: '-0.2px' }}>
                    {step.title}
                </div>
                <p style={{ fontSize: '13px', color: '#475569', lineHeight: 1.65, margin: '0 0 16px' }}>
                    {step.body}
                </p>

                {/* Progress */}
                <div style={{ height: '3px', background: '#f1f5f9', borderRadius: '99px', marginBottom: '14px', overflow: 'hidden' }}>
                    <div style={{ height: '100%', width: `${((index + 1) / count) * 100}%`, background: '#2563eb', borderRadius: '99px', transition: 'width 0.25s ease' }} />
                </div>

                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' }}>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{ background: 'none', border: 'none', padding: 0, fontSize: '12.5px', fontWeight: 600, color: '#9ca3af', cursor: 'pointer', fontFamily: 'inherit' }}
                    >
                        {last ? '' : 'Skip tour'}
                    </button>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        {index > 0 && (
                            <button
                                type="button"
                                onClick={onBack}
                                style={{ padding: '8px 14px', borderRadius: '7px', border: '1px solid #e5e7eb', background: '#fff', fontSize: '12.5px', fontWeight: 600, color: '#374151', cursor: 'pointer', fontFamily: 'inherit' }}
                            >
                                Back
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onNext}
                            autoFocus
                            style={{ padding: '8px 18px', borderRadius: '7px', border: 'none', background: '#2563eb', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                        >
                            {last ? 'Finish' : (index === 0 ? 'Start' : 'Next')}
                            {!last && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="9 18 15 12 9 6"/></svg>}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
