{{--
    The CPMS user manual, as one printable document.

    One view renders every edition — the complete manual and each role booklet —
    from the section data in App\Support\ManualContent. The layout is built for
    paper first: A4, a cover, a contents page, and one section per page break,
    so the printed booklet reads the same as the PDF on screen.

    Rendered by App\Console\Commands\BuildManuals through headless Chrome, which
    is what gives us the page-numbered footer and the repeating page furniture.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>{{ $manual['title'] }} — {{ $manual['subtitle'] }}</title>
<style>
    @page { size: A4; margin: 18mm 16mm 20mm; }

    * { box-sizing: border-box; }

    body {
        font-family: Calibri, 'Segoe UI', Arial, Helvetica, sans-serif;
        color: #14181f;
        font-size: 11.2pt;
        line-height: 1.55;
        margin: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ── Cover ──────────────────────────────────────────────────────────── */
    .cover { height: 247mm; display: flex; flex-direction: column; page-break-after: always; }
    .cover-crest { display: flex; align-items: center; gap: 14px; border-bottom: 2.5px solid #b8860b; padding-bottom: 14px; }
    .cover-crest img { height: 46px; width: auto; }
    .cover-crest .org { line-height: 1.3; }
    .cover-crest .org .c1 { font-weight: 800; font-size: 12pt; letter-spacing: .2px; }
    .cover-crest .org .c2 { font-weight: 700; font-size: 8.2pt; color: #4b5563; }
    .cover-crest .org .c3 { font-size: 9pt; letter-spacing: .8px; color: #b8860b; font-weight: 700; margin-top: 2px; }

    .cover-mid { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .cover-kicker { font-size: 9.5pt; font-weight: 700; letter-spacing: 2.4px; text-transform: uppercase; color: #b8860b; }
    .cover-title { font-size: 34pt; font-weight: 800; line-height: 1.08; margin: 10px 0 0; letter-spacing: -.5px; }
    .cover-sub { font-size: 15pt; color: #374151; margin-top: 10px; font-weight: 600; }
    .cover-rule { width: 68px; height: 4px; background: #b8860b; margin: 22px 0; }
    .cover-audience { font-size: 11pt; color: #4b5563; max-width: 118mm; line-height: 1.6; }

    .glance { border: 1px solid #d8dde5; border-left: 4px solid #b8860b; background: #fbfaf6; padding: 14px 18px; margin-top: 26px; max-width: 132mm; }
    .glance h4 { margin: 0 0 8px; font-size: 9pt; letter-spacing: 1.4px; text-transform: uppercase; color: #7a5c07; }
    .glance ul { margin: 0; padding-left: 17px; }
    .glance li { margin: 5px 0; font-size: 10.4pt; }

    .cover-foot { border-top: 1px solid #d8dde5; padding-top: 12px; display: flex; justify-content: space-between; font-size: 9pt; color: #6b7280; }
    .cover-foot b { color: #14181f; }

    /* ── Contents ───────────────────────────────────────────────────────── */
    .toc { page-break-after: always; }
    .toc-list { margin-top: 18px; }
    .toc-row { display: flex; align-items: baseline; gap: 10px; padding: 6.5px 0; border-bottom: 1px dotted #d8dde5; }
    .toc-num { width: 30px; font-weight: 800; color: #b8860b; font-size: 10.5pt; flex: none; }
    .toc-title { font-size: 11pt; }

    /* ── Sections ───────────────────────────────────────────────────────── */
    .section { page-break-before: always; }
    .section-head { border-bottom: 2px solid #b8860b; padding-bottom: 8px; margin-bottom: 16px; }
    .section-num { font-size: 9pt; font-weight: 800; letter-spacing: 2px; color: #b8860b; text-transform: uppercase; }
    h1.section-title { font-size: 20pt; font-weight: 800; margin: 3px 0 0; letter-spacing: -.3px; }

    h2.page-title { font-size: 20pt; font-weight: 800; margin: 0 0 4px; letter-spacing: -.3px; }
    h3 { font-size: 12.6pt; font-weight: 800; margin: 20px 0 7px; color: #1f2937; page-break-after: avoid; }

    p { margin: 0 0 10px; text-align: justify; }
    ul, ol { margin: 0 0 12px; padding-left: 20px; }
    li { margin: 4px 0; }
    ol li { padding-left: 3px; }

    /* Where-to-find-it strip under a section heading. */
    .path { display: inline-block; background: #f1f3f7; border: 1px solid #dde2ea; border-radius: 3px; padding: 4px 11px; font-size: 9.6pt; color: #3f4a5a; margin: 0 0 13px; font-weight: 600; }
    .path .sep { color: #9aa3b0; padding: 0 3px; }

    table.grid { border-collapse: collapse; width: 100%; margin: 4px 0 14px; font-size: 10.2pt; page-break-inside: avoid; }
    table.grid th { background: #f4f2e9; border: 1px solid #ccd2dc; padding: 6px 9px; text-align: left; font-weight: 800; font-size: 9.6pt; letter-spacing: .3px; }
    table.grid td { border: 1px solid #ccd2dc; padding: 6px 9px; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: #fafbfc; }

    table.kv { border-collapse: collapse; width: 100%; margin: 4px 0 14px; font-size: 10.4pt; }
    table.kv td { padding: 5px 9px; vertical-align: top; border-bottom: 1px solid #e6eaf0; }
    table.kv td.k { width: 38%; font-weight: 700; color: #1f2937; }

    .callout { border-left: 3px solid; padding: 9px 14px; margin: 12px 0; font-size: 10.4pt; page-break-inside: avoid; }
    .callout .lbl { font-weight: 800; font-size: 8.6pt; letter-spacing: 1.2px; text-transform: uppercase; display: block; margin-bottom: 3px; }
    .callout.note { border-color: #2563eb; background: #f4f7fe; }
    .callout.note .lbl { color: #1d4ed8; }
    .callout.tip  { border-color: #16a34a; background: #f4fbf6; }
    .callout.tip .lbl { color: #15803d; }
    .callout.warn { border-color: #d97706; background: #fefaf3; }
    .callout.warn .lbl { color: #b45309; }
</style>
</head>
<body>

@php
    use App\Support\PdfRenderer;

    $crest     = PdfRenderer::embeddedImage(public_path('logow.png'));
    $deptCrest = PdfRenderer::embeddedImage(public_path('logo@2xb.png'));
@endphp

{{-- ── Cover ──────────────────────────────────────────────────────────── --}}
<div class="cover">
    <div class="cover-crest">
        @if($crest)<img src="{{ $crest }}" alt="">@endif
        <div class="org">
            <div class="c1">PHILSAGA MINING CORPORATION</div>
            <div class="c2">MINDANAO MINERAL PROCESSING AND REFINING CORPORATION</div>
            <div class="c3">PROJECT MANAGEMENT DEPARTMENT</div>
        </div>
        @if($deptCrest)<img src="{{ $deptCrest }}" alt="" style="margin-left:auto">@endif
    </div>

    <div class="cover-mid">
        <div class="cover-kicker">User Manual</div>
        <div class="cover-title">{{ $manual['title'] }}</div>
        <div class="cover-sub">{{ $manual['subtitle'] }}</div>
        <div class="cover-rule"></div>
        <div class="cover-audience">{{ $manual['audience'] }}</div>

        @if(!empty($manual['at_a_glance']))
            <div class="glance">
                <h4>At a glance</h4>
                <ul>
                    @foreach($manual['at_a_glance'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="cover-foot">
        <div>Document No. <b>{{ $docNo }}</b></div>
        <div>Version <b>{{ $version }}</b></div>
        <div>Issued <b>{{ $issuedOn }}</b></div>
    </div>
</div>

{{-- ── Contents ───────────────────────────────────────────────────────── --}}
<div class="toc">
    <h2 class="page-title">Contents</h2>
    <p style="color:#6b7280;margin-bottom:0">{{ count($sections) }} sections. Every procedure in this booklet applies to your role as it is set up in CPMS.</p>
    <div class="toc-list">
        @foreach($sections as $i => $section)
            <div class="toc-row">
                <div class="toc-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="toc-title">{{ $section['title'] }}</div>
            </div>
        @endforeach
    </div>
</div>

{{-- ── Body ───────────────────────────────────────────────────────────── --}}
@foreach($sections as $i => $section)
    <div class="section">
        <div class="section-head">
            <div class="section-num">Section {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <h1 class="section-title">{{ $section['title'] }}</h1>
        </div>

        @foreach($section['blocks'] as $block)
            @php $type = $block[0]; @endphp

            @if($type === 'p')
                <p>{{ $block[1] }}</p>

            @elseif($type === 'h')
                <h3>{{ $block[1] }}</h3>

            @elseif($type === 'path')
                <div class="path">{!! implode('<span class="sep">&rsaquo;</span>', array_map('e', array_map('trim', explode('>', $block[1])))) !!}</div>

            @elseif($type === 'ul')
                <ul>@foreach($block[1] as $item)<li>{{ $item }}</li>@endforeach</ul>

            @elseif($type === 'ol')
                <ol>@foreach($block[1] as $item)<li>{{ $item }}</li>@endforeach</ol>

            @elseif($type === 'kv')
                <table class="kv">
                    @foreach($block[1] as [$k, $v])
                        <tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>
                    @endforeach
                </table>

            @elseif($type === 'table')
                <table class="grid">
                    <thead><tr>@foreach($block[1] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($block[2] as $row)
                            <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif(in_array($type, ['note', 'tip', 'warn'], true))
                <div class="callout {{ $type }}">
                    <span class="lbl">{{ ['note' => 'Note', 'tip' => 'Tip', 'warn' => 'Important'][$type] }}</span>
                    {{ $block[1] }}
                </div>
            @endif
        @endforeach
    </div>
@endforeach

</body>
</html>
