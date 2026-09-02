{{--
    Notice to Proceed — PMD-PRJ-FRM-04.

    Built from the record, never from the screen, so the print matches the
    controlled form rather than the hub layout. Signature blocks carry an
    APPROVED stamp for every step the chain has actually signed.
--}}
@extends('print.layout', ['title' => 'Notice to Proceed — ' . $ntp->ntp_no])

@php
    /** Blank cells read as "nothing recorded", never as a missing field. */
    $orBlank = fn ($v) => ($v === null || $v === '' || $v === '-') ? '' : $v;

    $signed = collect($ntp->approvalTimeline())
        ->where('status', 'approved')
        ->keyBy('role');

    /** The stamp for one chain step, or null while it is still unsigned. */
    $stamp = fn (string $role) => $signed->has($role)
        ? ['actor' => $signed[$role]['actor'], 'date' => $signed[$role]['acted_at']]
        : null;

    $scopeItems = $ntp->rfq?->items ?? collect();
    $blankRows  = max(0, 10 - $scopeItems->count());
@endphp

@section('form')
    @include('print.partials.header', [
        'docNo' => 'PMD-PRJ-FRM-04', 'rev' => '00',
        'effective' => 'October 05, 2025', 'sheet' => 'Page 1 of 1',
        'title' => 'NOTICE TO PROCEED',
    ])

    <div class="secrow">
        <h3 class="sec" style="margin:0;">PROJECT DETAILS</h3>
        <div class="refno">N.T.P. Number: <b>{{ $ntp->ntp_no }}</b></div>
    </div>

    <table class="kv">
        @foreach ([
            ['Project Number',            $orBlank($project->project_no)],
            ['Project Title',             $orBlank($project->title)],
            ['Sub-Project Title',         $orBlank($subProjectNo) ?: 'N/A'],
            ['Job Site / Location',       $orBlank($project->site)],
            ['Service Contractor',        $orBlank($ntp->contractor_name)],
            ['Project Owner',             $orBlank($project->dept_owner)],
            ['Cost Code',                 $orBlank($project->cost_code)],
            ['Baseline Project Duration', $ntp->rfq?->duration_days ? $ntp->rfq->duration_days . ' WORKING DAYS' : ''],
            ['Baseline Start Date',       optional($ntp->baseline_start)->format('M d, Y') ?? ''],
            ['Baseline End Date',         optional($ntp->baseline_end)->format('M d, Y') ?? ''],
            ['Project Cost',              'Php ' . number_format((float) $ntp->approved_cost, 2)],
        ] as [$label, $value])
            <tr>
                <td class="k">{{ $label }}</td>
                <td class="c">:</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <table class="box" style="margin-top:12px;">
        <thead>
            <tr>
                <th class="hd" style="width:44px;">Item</th>
                <th class="hd">Scope of Work</th>
                <th class="hd" style="width:90px;">Quantity</th>
                <th class="hd" style="width:90px;">UOM</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($scopeItems as $index => $item)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $orBlank($item->qty) }}</td>
                    <td class="num">{{ $orBlank($item->unit) }}</td>
                </tr>
            @endforeach

            {{-- The form keeps its full complement of rows so the blanks stay writable. --}}
            @for ($i = 1; $i <= $blankRows; $i++)
                <tr class="blank">
                    <td class="num">{{ $scopeItems->count() + $i }}</td>
                    <td></td><td></td><td></td>
                </tr>
            @endfor

            <tr><td colspan="4" class="band">Other Terms and Conditions</td></tr>

            {{-- A–E stay blank on the paper form for hand-written conditions. --}}
            @foreach (['A', 'B', 'C', 'D', 'E'] as $letter)
                <tr class="blank"><td class="num">{{ $letter }}</td><td colspan="3"></td></tr>
            @endforeach
        </tbody>
    </table>

    <div class="note">Note: Please attach a copy of the original quotation from the service contractor as a reference.</div>

    <div class="sig tight">
        @include('print.partials.signature', [
            'role' => 'Prepared by:', 'title' => 'Project Management Engineer',
            'name' => $ntp->creator->name ?? ($signatories['prepared_by'] ?? ''), 'approval' => null,
        ])
        @include('print.partials.signature', [
            'role' => 'Reviewed by:', 'title' => 'PMD Assistant Manager',
            'name' => $signatories['pmd_assistant_manager'] ?? '', 'approval' => $stamp('pmd_asst_manager'),
        ])
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'PMD Manager',
            'name' => $signatories['pmd_manager'] ?? '', 'approval' => $stamp('pmd_dept_manager'),
        ])
    </div>
    <div class="sig tight">
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'Mine Division Manager',
            'name' => '', 'approval' => $stamp('division_manager'),
        ])
        @include('print.partials.signature', [
            'role' => 'Checked by:', 'title' => 'Project Owner Representative',
            'name' => '', 'approval' => $stamp('requestor'),
        ])
        @include('print.partials.signature', [
            'role' => 'Received by:', 'title' => 'Service Contractor Representative',
            'name' => '', 'approval' => null,
        ])
    </div>
@endsection
