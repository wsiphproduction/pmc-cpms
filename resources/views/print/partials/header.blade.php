{{--
    The controlled-document banner: crest | corporate block | crest | doc control.

    `sheet` is omitted on the forms that carry no sheet count. The crest is
    inlined as a data URI so the document is self-contained and rendering never
    depends on the app being reachable from the machine running Chrome.
--}}
@php
    $crest = \App\Support\PdfRenderer::embeddedImage(public_path('logow.png'));
@endphp
<div class="hdr">
    <div class="logo"><img src="{{ $crest }}" alt="PMC"></div>
    <div class="mid">
        <div class="c1">PHILSAGA MINING CORPORATION</div>
        <div class="c2">MINDANAO MINERAL PROCESSING AND REFINING CORPORATION</div>
        <div class="c3">PROJECT MANAGEMENT DEPARTMENT</div>
    </div>
    <div class="logo"><img src="{{ $crest }}" alt=""></div>
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
