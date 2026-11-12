/**
 * How a project counts its days — mirrors Project::DURATION_BASES on the server.
 * Calendar days count every day; working days are Monday to Saturday (Sundays off).
 */
export type DurationBasis = 'calendar' | 'working';

export const DURATION_BASIS_OPTIONS: { value: DurationBasis; label: string }[] = [
    { value: 'calendar', label: 'Calendar Days' },
    { value: 'working', label: 'Working Days (Mon–Sat)' },
];

/** "calendar day" / "working days" — the unit alone, pluralised for `n`. */
export function dayUnit(basis: DurationBasis | null | undefined, n = 2): string {
    return `${basis === 'working' ? 'working' : 'calendar'} day${n === 1 ? '' : 's'}`;
}

/** "Calendar Days" / "Working Days" — for field labels. */
export function dayUnitTitle(basis: DurationBasis | null | undefined): string {
    return basis === 'working' ? 'Working Days' : 'Calendar Days';
}

/** "71 calendar days" / "1 working day". */
export function durationLabel(days: number, basis: DurationBasis | null | undefined): string {
    return `${days} ${dayUnit(basis, days)}`;
}

/**
 * Add `days` to a YYYY-MM-DD date on the given basis, returning YYYY-MM-DD.
 * Working days skip Sundays, so the result is always a Monday–Saturday.
 * Number(days) guards against a string ("30") concatenating instead of adding.
 */
export function addDurationDays(dateStr: string, days: number, basis: DurationBasis | null | undefined): string {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    let left = Number(days);
    if (basis === 'working') {
        while (left > 0) {
            dt.setDate(dt.getDate() + 1);
            if (dt.getDay() !== 0) left--;
        }
    } else {
        dt.setDate(dt.getDate() + left);
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`;
}
