{{--
    Shared chrome for the controlled PMD paper forms — RFQ (FRM-03), NTP
    (FRM-04) and the completion documents (FRM-06 / FRM-12).

    The printed output is a facsimile of the forms the department already signs
    by hand: a four-cell header banner, a title bar, gold-filled table headings,
    fixed-length tables that keep their blank rows, and boxed signature cells
    left empty for wet signatures. Anything the system does not hold prints as
    an empty cell rather than being omitted, so there is still somewhere to
    write it.

    Rendered to PDF by headless Chrome (see App\Support\PdfRenderer), which is
    why grid, flexbox and the rotated approval stamp survive into the printout.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $title }}</title>
    <style>

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
    .sig .cell { border: 1px solid #000; padding: 8px 10px 10px; min-height: 78px; position: relative; }
    /* A stamped cell grows and drops its name, so the stamp sits in the clear
       space above the signature rather than printing across it. */
    .sig .cell.stamped { min-height: 96px; }
    .sig .cell.stamped .name { margin-top: 42px; }
    /* Names stay on one baseline across a row, so an unstamped block beside a
       stamped one doesn't sit high. :has() is progressive — without it the
       stamped cells above are still correct, the row is just less even. */
    .sig:has(.stamped) .cell { min-height: 96px; }
    .sig:has(.stamped) .name { margin-top: 42px; }
    .sig .stamp {
        position: absolute; top: 15px; left: 50%;
        transform: translateX(-50%) rotate(-6deg);
        border: 2px solid #15803d; border-radius: 5px;
        padding: 3px 12px 4px; text-align: center; pointer-events: none;
        color: #15803d; background: #fff; white-space: nowrap;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .sig .stamp .word { font-size: 12px; font-weight: 900; letter-spacing: 2.5px; line-height: 1.15; }
    .sig .stamp .when {
        font-size: 9.5px; font-weight: 700; line-height: 1.2; margin-top: 2px; padding-top: 2px;
        border-top: 1px solid #86efac; color: #166534;
    }
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
    </style>
</head>
<body>
@yield('form')
</body>
</html>
