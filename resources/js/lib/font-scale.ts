import { useSyncExternalStore } from 'react';

/**
 * The signed-in user's text size, as a percentage of normal.
 *
 * The pages size their text in fixed pixels, so the size is applied as CSS
 * zoom on <html> — the whole page grows, like zooming in Word. The server
 * sets it on first load (app.blade.php); from then on this module owns it,
 * so going Back to a page whose saved props predate a change cannot undo it.
 */

/** The steps of the control. Mirrors User::FONT_SCALES. */
export const FONT_SCALES = [80, 90, 100, 110, 125, 150] as const;

const listeners = new Set<() => void>();
let scale = 100;
/** Whose size is applied — re-read from the page when the account changes. */
let owner: number | null | undefined;
let saveTimer: number | undefined;

function apply(next: number) {
    scale = next;
    document.documentElement.style.zoom = next === 100 ? '' : String(next / 100);
    listeners.forEach(listener => listener());
}

/**
 * Take the size from the page's signed-in user — on first load, and when
 * someone signs in or out or impersonation starts or ends. Otherwise the
 * size already applied stands.
 */
export function syncFontScale(user?: { id: number; font_scale?: number | null } | null) {
    const id = user?.id ?? null;
    if (id === owner) return;
    owner = id;
    apply(user?.font_scale || 100);
}

const xsrfToken = () =>
    decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

/** Apply a size now and save it to the account once the clicking stops. */
export function setFontScale(next: number) {
    apply(next);

    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
        fetch(route('account.font-scale'), {
            method: 'PATCH',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ font_scale: next }),
        }).catch(() => { /* the size still applies until the next full page load */ });
    }, 400);
}

/** The next size up or down, staying at the ends. */
export function stepFontScale(current: number, direction: 1 | -1): number {
    const index = FONT_SCALES.findIndex(s => s >= current);
    const at = index === -1 ? FONT_SCALES.length - 1 : index;
    return FONT_SCALES[Math.min(Math.max(at + direction, 0), FONT_SCALES.length - 1)];
}

export function useFontScale(): number {
    return useSyncExternalStore(
        listener => { listeners.add(listener); return () => listeners.delete(listener); },
        () => scale,
        () => 100,
    );
}

/** The zoom applied to the page (1 = 100%). */
export const pageZoom = (): number => scale / 100;

/**
 * The factor that turns getBoundingClientRect() values into CSS pixels inside
 * the zoomed page. Current browsers report rects in screen pixels (so this
 * equals the zoom); measuring rather than assuming covers any that do not.
 */
export function rectScale(): number {
    const html = document.documentElement;
    return html.offsetWidth ? html.getBoundingClientRect().width / html.offsetWidth : 1;
}

/** The viewport in the zoomed page's CSS pixels. */
export const viewportSize = () => ({ width: window.innerWidth / pageZoom(), height: window.innerHeight / pageZoom() });
