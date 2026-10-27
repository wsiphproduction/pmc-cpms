{{--
    Notice to Proceed — PMD-PRJ-FRM-04, Rev 01.

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
    {{-- Rev 01 differs from the shared look: a boxed, two-column details
         panel and bright-yellow table headings in place of gold. --}}
    <style>
        .ntp-meta { display: flex; justify-content: space-between; align-items: baseline; margin: 12px 0 5px; padding: 0 40px 0 60px; font-size: 13px; }
        .ntp-meta b { font-size: 13px; }
        table.ntp-details { border-collapse: collapse; width: 100%; border: 1px solid #000; }
        table.ntp-details td { padding: 3px 8px; vertical-align: top; font-size: 12.5px; }
        table.ntp-details td.title { text-align: center; font-weight: 800; font-size: 14px; border-bottom: 1px solid #000; padding: 5px; }
        table.ntp-details td.k { width: 140px; white-space: nowrap; }
        table.ntp-details td.k2 { width: 170px; white-space: nowrap; }
        table.ntp-details td.c { width: 16px; }
        /* The schedule panel is boxed off on the right of the lower rows. */
        table.ntp-details td.rp { border-left: 1px solid #000; }
        table.ntp-details tr.rp-top td.rt { border-top: 1px solid #000; }
        table.ntp-details td.remarks { font-style: italic; font-size: 10.5px; height: 40px; }
        table.box th.hd, table.box td.band { background: #ffff00; }
        /* Seven signature blocks have to share the page with the full form. */
        table.box td, table.box th { padding-top: 3px; padding-bottom: 3px; }
        table.box tr.blank td { height: 19px; }
    </style>

    @include('print.partials.header', [
        'docNo' => 'PMD-PRJ-FRM-04', 'rev' => '01',
        'effective' => 'July 27, 2026', 'sheet' => 'Page 1 of 1',
        'title' => 'NOTICE TO PROCEED', 'titleSize' => 'lg',
    ])

    <div class="ntp-meta">
        <div>Date Prepared: <b>{{ optional($ntp->created_at)->format('M d, Y') }}</b></div>
        <div>N.T.P. Number: <b>{{ $ntp->ntp_no }}</b></div>
    </div>

    <table class="ntp-details">
        <tr><td colspan="6" class="title">PROJECT DETAILS</td></tr>
        @foreach ([
            ['Project Number',    $orBlank($project->project_no)],
            ['Project Title',     $orBlank($project->title)],
            ['Sub-Project Title', $orBlank($subProjectNo) ?: 'N/A'],
        ] as [$label, $value])
            <tr>
                <td class="k">{{ $label }}</td>
                <td class="c">:</td>
                <td colspan="4">{{ $value }}</td>
            </tr>
        @endforeach
        <tr class="rp-top">
            <td class="k">Job Site / Location</td><td class="c">:</td><td>{{ $orBlank($project->site) }}</td>
            <td class="k2 rp rt">Baseline Project Duration</td><td class="c rt">:</td>
            <td class="rt">{{ $ntp->rfq?->duration_days ? $ntp->rfq->duration_days . ' WORKING DAYS' : '' }}</td>
        </tr>
        <tr>
            <td class="k">Service Contractor</td><td class="c">:</td><td>{{ $orBlank($ntp->contractor_name) }}</td>
            {{-- Left for a hand-written note on the schedule. --}}
            <td colspan="3" rowspan="2" class="rp remarks">Remarks:</td>
        </tr>
        <tr>
            <td class="k">Project Owner</td><td class="c">:</td><td>{{ $orBlank($project->dept_owner) }}</td>
        </tr>
        <tr>
            <td class="k">Project Cost (PhP)</td><td class="c">:</td><td>{{ number_format((float) $ntp->approved_cost, 2) }}</td>
            <td class="k2 rp">Baseline Start Date</td><td class="c">:</td>
            <td>{{ optional($ntp->baseline_start)->format('M d, Y') }}</td>
        </tr>
        <tr>
            <td class="k">Cost Code</td><td class="c">:</td><td>{{ $orBlank($project->cost_code) }}</td>
            <td class="k2 rp">Baseline End Date</td><td class="c">:</td>
            <td>{{ optional($ntp->baseline_end)->format('M d, Y') }}</td>
        </tr>
    </table>

    <table class="box" style="margin-top:14px;">
        <thead>
            <tr>
                <th class="hd" style="width:44px;">Item</th>
                <th class="hd">Scope of Work</th>
                <th class="hd" style="width:100px;">Quantity</th>
                <th class="hd" style="width:150px;">UOM</th>
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
        {{-- Creating the NTP is the engineer's sign-off, so their block is
             stamped from the moment the record exists. --}}
        @include('print.partials.signature', [
            'role' => 'Prepared by:', 'title' => 'Project Management Engineer',
            'name' => $ntp->creator->name ?? ($signatories['prepared_by'] ?? ''),
            'approval' => ['actor' => $ntp->creator->name ?? null, 'date' => $ntp->created_at?->format('M d, Y h:i A')],
        ])
        @include('print.partials.signature', [
            'role' => 'Reviewed by:', 'title' => 'PMD Assistant Manager',
            'name' => $named['pmd_asst_manager'] ?? $signatories['pmd_assistant_manager'] ?? '', 'approval' => $stamp('pmd_asst_manager'),
        ])
        @include('print.partials.signature', [
            'role' => 'Endorsed by:', 'title' => 'PMD Manager',
            'name' => $named['pmd_dept_manager'] ?? $signatories['pmd_manager'] ?? '', 'approval' => $stamp('pmd_dept_manager'),
        ])
    </div>
    <div class="sig tight">
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'ECS / Mines Division Manager',
            'name' => $named['division_manager'] ?? '', 'approval' => $stamp('division_manager'),
        ])
        @include('print.partials.signature', [
            'role' => 'Concurred by:', 'title' => 'Project Owner Representative',
            'name' => $named['requestor'] ?? '', 'approval' => $stamp('requestor'),
        ])
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'Project Owner Division Manager',
            'name' => $named['division_manager_user'] ?? '', 'approval' => $stamp('division_manager_user'),
        ])
    </div>
    {{-- The contractor signs on receipt, alone in the last column. --}}
    <div class="sig tight">
        <div></div><div></div>
        @include('print.partials.signature', [
            'role' => 'Received by:', 'title' => 'Service Contractor Representative',
            'name' => '', 'approval' => null,
        ])
    </div>
@endsection
