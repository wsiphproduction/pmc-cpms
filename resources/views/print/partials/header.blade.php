{{--
    The controlled-document banner: crest | corporate block | crest | doc control.

    `sheet` is omitted on the forms that carry no sheet count. The two crests
    are different marks — the company's on the left, the department's on the
    right — inlined as data URIs so the document is self-contained and
    rendering never depends on the app being reachable from the machine
    running Chrome.
--}}
@php
    $crest     = \App\Support\PdfRenderer::embeddedImage(public_path('logow.png'));
    $deptCrest = \App\Support\PdfRenderer::embeddedImage(public_path('logo@2xb.png'));
@endphp
<div class="hdr">
    <div class="logo"><img src="{{ $crest }}" alt="PMC"></div>
    <div class="mid">
        <div class="c1">PHILSAGA MINING CORPORATION</div>
        <div class="c2">MINDANAO MINERAL PROCESSING AND REFINING CORPORATION</div>
        <div class="c3">PROJECT MANAGEMENT DEPARTMENT</div>
    </div>
    <div class="logo"><img src="{{ $deptCrest }}" alt="PMD"></div>
    <div class="doc">
        <div>Doc No.: {{ $docNo }}</div>
        <div>Rev No.: {{ $rev }}</div>
        <div>Effective: {{ $effective }}</div>
        @isset($sheet)
            <div>Sheet No.: {{ $sheet }}</div>
        @endisset
    </div>
</div>
<div class="titlebar">{{ $title }}</div>
