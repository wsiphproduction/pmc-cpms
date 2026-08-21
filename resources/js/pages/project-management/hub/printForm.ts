/**
 * Shared chrome for the controlled PMD paper forms — RFQ (FRM-03), NTP
 * (FRM-04) and the completion documents (FRM-06 / FRM-12).
 *
 * The printed output is a facsimile of the forms the department already signs
 * by hand, so the pieces here deliberately mirror the paper: a four-cell
 * header banner, a title bar, gold-filled table headings, fixed-length tables
 * that keep their blank rows, and boxed signature cells left empty for wet
 * signatures. Anything the system does not hold is rendered as an empty cell
 * rather than omitted, so the form still has somewhere to write it.
 */

/** Both header crests. The right-hand seal is the same mark on the paper form. */
export const LOGO_SRC = '/logow.png';

export function escapeHtml(value: unknown): string {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/** Blank cells read as "nothing recorded", never as a missing field. */
export const orBlank = (v: unknown) => (v == null || v === '' || v === '-' ? '' : escapeHtml(v));

export const peso = (n?: number | null) =>
    n == null ? '' : `Php ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Pads a table out to the form's fixed row count so the blanks stay writable. */
export function padRows(rows: string[], minRows: number, blank: string): string[] {
    const out = rows.slice();
    while (out.length < minRows) out.push(blank.replace('%N%', String(out.length + 1)));
    return out;
}

export const PRINT_CSS = `
    * { box-sizing: border-box; }
    body { font-family: 'Times New Roman', Georgia, serif; margin: 34px 40px; color: #000; font-size: 13px; }

    /* Header banner: crest | corporate block | crest | document control */
    .hdr { display: grid; grid-template-columns: 66px 1fr 58px 208px; border: 1.5px solid #000; }
    .hdr .logo { display: flex; align-items: center; justify-content: center; padding: 4px; border-right: 1px solid #000; }
    .hdr .logo img { width: 100%; height: auto; max-height: 46px; object-fit: contain; }
    .hdr .mid { text-align: center; padding: 6px 8px; border-right: 1px solid #000; }
    .hdr .mid .c1 { font-weight: 800; font-size: 14px; letter-spacing: .3px; }
    .hdr .mid .c2 { font-weight: 700; font-size: 9.5px; }
    .hdr .mid .c3 { font-size: 11px; margin-top: 2px; letter-spacing: .6px; }
    .hdr .doc { font-size: 10px; }
    .hdr .doc div { padding: 3px 8px; border-bottom: 1px solid #000; }
    .hdr .doc div:last-child { border-bottom: none; }
    .titlebar { border: 1.5px solid #000; border-top: none; text-align: center; font-weight: 800; font-size: 14px; padding: 6px; letter-spacing: .5px; }

    /* Label / value detail block */
    h3.sec { font-size: 12.5px; margin: 16px 0 6px; }
    .secrow { display: flex; justify-content: space-between; align-items: baseline; margin: 16px 0 6px; }
    .secrow .refno { font-size: 12.5px; }
    .secrow .refno b { font-size: 13px; }
    table.kv { border-collapse: collapse; width: 100%; }
    table.kv td { padding: 3px 6px; vertical-align: top; }
    table.kv td.k { width: 165px; }
    table.kv td.c { width: 12px; }
    .fill { display: inline-block; min-width: 150px; border-bottom: 1px solid #000; }

    /* Bordered tables. Gold headings match the printed forms. */
    table.box { border-collapse: collapse; width: 100%; }
    table.box td, table.box th { border: 1px solid #000; padding: 5px 8px; font-size: 12px; vertical-align: top; }
    table.box th.hd { background: #ffc000; text-align: center; font-weight: 800; }
    table.box td.band { background: #ffc000; text-align: center; font-weight: 800; }
    table.box td.num { text-align: center; width: 34px; }
    table.box td.r { text-align: right; }
    table.box tr.blank td { height: 21px; }
    .note { font-size: 11px; font-style: italic; margin: 6px 0 10px; }
    .instruct { font-size: 11px; font-style: italic; font-weight: 700; color: #c00000; margin: 10px 0 6px; }
    p.para { text-align: justify; line-height: 1.5; margin: 10px 0; }

    /* Signature blocks — names print above the rule, roles below it. */
    .sig { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; margin-top: 26px; }
    .sig.two { grid-template-columns: 1fr 1fr; }
    .sig.tight { margin-top: 0; }
    .sig .cell { border: 1px solid #000; padding: 8px 10px 10px; min-height: 78px; }
    .sig .role { font-size: 11px; }
    .sig .name { text-align: center; font-weight: 800; margin-top: 26px; text-transform: uppercase; font-size: 12px; }
    .sig .line { border-top: 1px solid #000; margin-top: 2px; padding-top: 2px; text-align: center; font-size: 10.5px; }

    .docimgs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .docimgs img { width: 100%; height: 150px; object-fit: cover; border: 1px solid #000; }

    @media print {
        body { margin: 12mm; }
        .sig { page-break-inside: avoid; }
        table.box { page-break-inside: auto; }
    }
`;

/**
 * The controlled-document banner. `sheet` is omitted on the forms that do not
 * carry a sheet count.
 */
export function formHeader(opts: { docNo: string; rev: string; effective: string; sheet?: string; title: string }) {
    return `
    <div class="hdr">
        <div class="logo"><img src="${LOGO_SRC}" alt="PMC"></div>
        <div class="mid">
            <div class="c1">PHILSAGA MINING CORPORATION</div>
            <div class="c2">MINDANAO MINERAL PROCESSING AND REFINING CORPORATION</div>
            <div class="c3">PROJECT MANAGEMENT DEPARTMENT</div>
        </div>
        <div class="logo"><img src="${LOGO_SRC}" alt=""></div>
        <div class="doc">
            <div>Doc No.: ${escapeHtml(opts.docNo)}</div>
            <div>Rev No.: ${escapeHtml(opts.rev)}</div>
            <div>Effective: ${escapeHtml(opts.effective)}</div>
            ${opts.sheet ? `<div>Sheet No.: ${escapeHtml(opts.sheet)}</div>` : ''}
        </div>
    </div>
    <div class="titlebar">${escapeHtml(opts.title)}</div>`;
}

export function sigCell(role: string, name: string, title: string) {
    return `<div class="cell">
        <div class="role">${escapeHtml(role)}</div>
        <div class="name">${escapeHtml(name || ' ')}</div>
        <div class="line">${escapeHtml(title)}</div>
    </div>`;
}

/**
 * Opens the form in a print window. Printing waits on the crest images —
 * firing on a fixed timer used to race them and print a headerless page.
 * The window is left open so the user can re-print or save as PDF.
 */
export function openPrintWindow(title: string, inner: string) {
    const win = window.open('', '_blank', 'width=980,height=800');
    if (!win) return;
    win.document.write(
        `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>` +
        `<style>${PRINT_CSS}</style></head><body>${inner}</body></html>`
    );
    win.document.close();

    const images = Array.from(win.document.images);
    Promise.all(images.map(img => img.complete
        ? Promise.resolve()
        : new Promise<void>(resolve => { img.onload = img.onerror = () => resolve(); })
    )).then(() => {
        win.focus();
        win.print();
    });
}
