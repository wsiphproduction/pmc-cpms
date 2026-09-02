{{--
    Project Completion Summary — PMD-PRJ-FRM-12.
--}}
@extends('print.layout', ['title' => 'Completion Summary — ' . $project->project_no])

@php
    $short = fn ($date) => optional($date)->format('d-M-y') ?? '';
    $long  = fn ($date) => optional($date)->format('F j, Y') ?? '';
    $money = fn ($n) => $n === null ? '' : '₱' . number_format((float) $n, 2);

    /** Actual minus baseline: positive is late, negative is early. */
    $slippage = function ($actual, $baseline): ?int {
        if (! $actual || ! $baseline) {
            return null;
        }

        return (int) $baseline->diffInDays($actual, false);
    };

    $planSlip = $slippage($completion?->plan_actual_end, $completion?->plan_baseline_end);
    $conSlip  = $slippage($completion?->con_actual_end, $completion?->con_baseline_end);

    $baseline = $completion?->baseline_amount !== null ? (float) $completion?->baseline_amount : null;
    $actual   = $completion?->actual_amount !== null ? (float) $completion?->actual_amount : null;

    $variance = '';
    if ($baseline !== null && $actual !== null && $baseline != 0.0) {
        $diff = $baseline - $actual;
        $variance = number_format(($diff / $baseline) * 100, 1) . '% (₱' . number_format($diff, 1) . ')';
    }

    $timeline = $conSlip === null ? '' : ($conSlip > 0 ? 'Delayed' : ($conSlip < 0 ? 'Advanced' : 'On-Time'));

    $photos = collect($completion?->photos ?? [])->take(4);
@endphp

@section('form')
    @include('print.partials.header', [
        'docNo' => 'PMD-PRJ-FRM-12', 'rev' => '00',
        'effective' => 'November 01, 2025',
        'title' => 'PROJECT COMPLETION SUMMARY',
    ])

    <p style="margin:10px 0;">
        <strong>Date Prepared:</strong> {{ $long($completion?->date_prepared) }}
        <span style="float:right;">Reference Number: {{ $completion?->reference_no }}</span>
    </p>

    <table class="box" style="margin-bottom:12px;">
        <tr>
            <td class="hd" style="width:140px;">Project Number</td><td>{{ $project->project_no }}</td>
            <td class="hd" style="width:130px;">Job Site/Location</td><td>{{ $project->site }}</td>
        </tr>
        <tr>
            <td class="hd">Project Title</td><td>{{ $project->title }}</td>
            <td class="hd">Project Owner</td><td>{{ $project->dept_owner }}</td>
        </tr>
        <tr>
            <td class="hd">Sub-Project Title</td><td>{{ $completion?->sub_project_title ?: 'N/A' }}</td>
            <td class="hd">Contractor</td><td>{{ $completion?->contractor }}</td>
        </tr>
        <tr>
            <td class="hd">Project Classification</td><td>{{ $completion?->classification }}</td>
            <td class="hd">Request Date</td><td>{{ $short($completion?->request_date) }}</td>
        </tr>
    </table>

    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:49%;vertical-align:top;">
                <table class="box">
                    <tr><th class="hd" colspan="2">PLANNING STATUS</th></tr>
                    @foreach ([
                        ['Baseline Start Date', $short($completion?->plan_baseline_start)],
                        ['Baseline End Date',   $short($completion?->plan_baseline_end)],
                        ['Actual Start Date',   $short($completion?->plan_actual_start)],
                        ['Actual End Date',     $short($completion?->plan_actual_end)],
                        ['Slippage (days)',     $planSlip === null ? '' : (string) $planSlip],
                    ] as [$label, $value])
                        <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </table>

                <table class="box" style="margin-top:10px;">
                    <tr><th class="hd" colspan="2">PROJECT COST</th></tr>
                    @foreach ([
                        ['Cost Code',       $project->cost_code],
                        ['Baseline Amount', $money($baseline)],
                        ['Actual Amount',   $money($actual)],
                        ['Variance',        $variance],
                        ['Payment Status',  $completion?->payment_status],
                    ] as [$label, $value])
                        <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </table>

                <table class="box" style="margin-top:10px;">
                    <tr><th class="hd" colspan="2">CONSTRUCTION STATUS</th></tr>
                    @foreach ([
                        ['Baseline Start Date', $short($completion?->con_baseline_start)],
                        ['Baseline End Date',   $short($completion?->con_baseline_end)],
                        ['Actual Start Date',   $short($completion?->con_actual_start)],
                        ['Actual End Date',     $short($completion?->con_actual_end)],
                        ['Slippage (days)',     $conSlip === null ? '' : (string) $conSlip],
                        ['Timeline Status',     $timeline],
                        ['Completion Status',   $completion?->completion_status],
                    ] as [$label, $value])
                        <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td style="width:2%;"></td>
            <td style="vertical-align:top;">
                <table class="box">
                    <tr><th class="hd">DOCUMENTATION</th></tr>
                    <tr>
                        <td>
                            @if ($photos->isNotEmpty())
                                {{-- Inlined, so the render never has to reach back into the app for assets. --}}
                                <div class="docimgs">
                                    @foreach ($photos as $path)
                                        <img src="{{ \App\Support\PdfRenderer::embeddedImage(storage_path('app/public/' . $path)) }}" />
                                    @endforeach
                                </div>
                            @else
                                <div style="border:1px solid #000;padding:40px;text-align:center;color:#666;">
                                    No documentation photos attached.
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="sig">
        @include('print.partials.signature', ['role' => 'Prepared by:', 'title' => 'Project Management Engineer', 'name' => $signatories['prepared_by'] ?? '',           'approval' => null])
        @include('print.partials.signature', ['role' => 'Checked by:',  'title' => 'PMD Assistant Manager',       'name' => $signatories['pmd_assistant_manager'] ?? '', 'approval' => null])
        @include('print.partials.signature', ['role' => 'Noted by:',    'title' => 'PMD Manager',                 'name' => $signatories['pmd_manager'] ?? '',           'approval' => null])
    </div>
    <div class="sig two">
        @include('print.partials.signature', ['role' => 'Endorsed by:',     'title' => 'ECS Division Manager',                'name' => $signatories['ecs_division_manager'] ?? '', 'approval' => null])
        @include('print.partials.signature', ['role' => 'Acknowledged by:', 'title' => 'Project Owner – Department Manager',  'name' => $completion?->acknowledged_by ?? '',        'approval' => null])
    </div>
@endsection
