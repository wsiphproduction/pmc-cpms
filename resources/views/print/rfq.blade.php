{{--
    Request for Quotation — PMD-PRJ-FRM-03.

    Reference No. and Revision No. are assigned on paper when the form is
    logged, so they print as blank rules. Figures come from the RFQ's final
    quotation.
--}}
@extends('print.layout', ['title' => 'RFQ — ' . $project->project_no . ' — ' . $rfq->contractor_name])

@php
    $orBlank = fn ($v) => ($v === null || $v === '' || $v === '-') ? '' : $v;
    $money   = fn ($v) => $v === null ? '' : number_format((float) $v, 2);

    $items      = $rfq->items;
    $grandTotal = (float) $items->sum('total_cost');
    $blankRows  = max(0, 10 - $items->count());

    /*
     * The paper form offers a fixed set of scope options; tick whichever the
     * stored free-text scope names, and fall back to "Others" when it is set to
     * something outside the list.
     */
    $scopes  = ['Conceptual Design', 'Detailed Drawing', 'Detailed Estimate', 'Preliminary', 'Ballpark Estimate'];
    $scope   = trim((string) $rfq->scope_of_work);
    $matched = collect($scopes)->first(fn ($o) => strcasecmp($o, $scope) === 0);
    $tick    = fn (bool $on) => $on ? '☒' : '☐';
@endphp

@section('form')
    @include('print.partials.header', [
        'docNo' => 'PMD-PRJ-FRM-03', 'rev' => '00',
        'effective' => 'October 05, 2025', 'sheet' => 'Page 1 of 1',
        'title' => 'REQUEST FOR QUOTATION',
    ])

    <div class="secrow">
        <h3 class="sec" style="margin:0;">PROJECT DETAILS</h3>
        <div class="refno">Reference No.: <span class="fill"></span></div>
    </div>

    <table class="kv">
        @foreach ([
            ['Service Contractor', $orBlank($rfq->contractor_name)],
            ['Date',               optional($rfq->sent_date)->format('M d, Y') ?? ''],
            ['Project Number',     $orBlank($project->project_no)],
        ] as [$label, $value])
            <tr><td class="k">{{ $label }}</td><td class="c">:</td><td>{{ $value }}</td></tr>
        @endforeach

        <tr><td class="k">Revision No.</td><td class="c">:</td><td><span class="fill"></span></td></tr>

        @foreach ([
            ['Project Title',       $orBlank($project->title)],
            ['Sub Project',         $orBlank($subProjectNo) ?: 'N/A'],
            ['Job Site / Location', $orBlank($project->site)],
            ['Project Owner',       $orBlank($project->dept_owner)],
        ] as [$label, $value])
            <tr><td class="k">{{ $label }}</td><td class="c">:</td><td>{{ $value }}</td></tr>
        @endforeach

        <tr>
            <td class="k">Scope of Work</td>
            <td class="c">:</td>
            <td>
                <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                    <tr>
                        <td style="padding:1px 0;">{{ $tick($matched === 'Conceptual Design') }} Conceptual Design</td>
                        <td style="padding:1px 0;">{{ $tick($matched === 'Detailed Drawing') }} Detailed Drawing</td>
                        <td style="padding:1px 0;">{{ $tick($matched === 'Detailed Estimate') }} Detailed Estimate</td>
                    </tr>
                    <tr>
                        <td style="padding:1px 0;">{{ $tick($matched === 'Preliminary') }} Preliminary</td>
                        <td style="padding:1px 0;">{{ $tick($matched === 'Ballpark Estimate') }} Ballpark Estimate</td>
                        <td style="padding:1px 0;">
                            {{ $tick($scope !== '' && ! $matched) }} Others:
                            <span class="fill" style="min-width:70px;">{{ ! $matched ? $scope : '' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="k">Date Needed</td>
            <td class="c">:</td>
            <td>{{ optional($rfq->due_date)->format('M d, Y') ?? '' }}</td>
        </tr>
    </table>

    <div class="sig">
        @include('print.partials.signature', [
            'role' => 'Requested by:', 'title' => 'Printed Name and Signature',
            'name' => $signatories['prepared_by'] ?? '', 'approval' => null,
        ])
        @include('print.partials.signature', [
            'role' => 'Reviewed by:', 'title' => 'PMD Assistant Manager',
            'name' => $signatories['pmd_assistant_manager'] ?? '', 'approval' => null,
        ])
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'PMD Manager',
            'name' => $signatories['pmd_manager'] ?? '', 'approval' => null,
        ])
    </div>

    <div class="instruct">
        Please submit/list down a conceptual estimate for the deliverables/activities, target duration,
        and conditions for the above-mentioned project.
    </div>

    <table class="box">
        <thead>
            <tr>
                <th class="hd" style="width:34px;">NO.</th>
                <th class="hd">DELIVERABLES, ACTIVITIES AND/OR RENTAL</th>
                <th class="hd" style="width:60px;">QTY</th>
                <th class="hd" style="width:60px;">UOM</th>
                <th class="hd" style="width:100px;">UNIT COST</th>
                <th class="hd" style="width:105px;">TOTAL COST</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $orBlank($item->qty) }}</td>
                    <td class="num">{{ $orBlank($item->unit) }}</td>
                    <td class="r">{{ $money($item->unit_cost) }}</td>
                    <td class="r">{{ $money($item->total_cost) }}</td>
                </tr>
            @endforeach

            @for ($i = 1; $i <= $blankRows; $i++)
                <tr class="blank">
                    <td class="num">{{ $items->count() + $i }}</td>
                    <td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor

            @if ($grandTotal > 0)
                <tr>
                    <td colspan="5" class="r" style="font-weight:800;">Grand Total</td>
                    <td class="r" style="font-weight:800;">{{ $money($grandTotal) }}</td>
                </tr>
            @endif

            <tr><td colspan="6" class="band">Other Quotation Details</td></tr>
            <tr>
                <td class="num">A.</td>
                <td>Target Project Duration</td>
                <td colspan="4">{{ $rfq->duration_days ? $rfq->duration_days . ' Working Days' : '' }}</td>
            </tr>
            <tr>
                <td class="num">B.</td>
                <td>Terms and Conditions;<br>Inclusions and exclusions</td>
                <td colspan="4" style="height:76px;">
                    {!! collect([$rfq->terms_conditions, $rfq->inclusions, $rfq->exclusions])
                        ->filter()
                        ->map(fn ($t) => e($t))
                        ->implode('<br>') !!}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="sig two tight" style="margin-top:14px;">
        @include('print.partials.signature', [
            'role' => 'Prepared by:', 'title' => 'Printed Name and Signature',
            'name' => $signatories['prepared_by'] ?? '', 'approval' => null,
        ])
        @include('print.partials.signature', [
            'role' => 'Noted by: (END USER)', 'title' => 'Printed Name and Signature',
            'name' => '', 'approval' => null,
        ])
    </div>
@endsection
