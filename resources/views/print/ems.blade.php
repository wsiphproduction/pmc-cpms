{{--
    Environmental Impact Assessment — PMD-PRJ-FRM-19, Rev 00. Printed from
    the EMS Survey Report record.

    The upper half is the project side: the engineer's header, then the
    assessment MEPEO's representative fills in, signed off by PMD. The lower
    half is MEPEO's own finding and signatures. Every block carries a stamp
    once the system has recorded that signature; until then it stays blank
    for a wet one.
--}}
@extends('print.layout', ['title' => 'EMS Survey Report — ' . $report->control_no])

@php
    $activities = config('ems.major_activities');
    $aspects    = config('ems.aspects');
    $statuses   = config('ems.statuses');

    $ticked      = fn (array $keys, string $key) => in_array($key, $keys, true);
    $activityOn  = $report->major_activities ?? [];
    $aspectOn    = $report->aspects ?? [];

    $signed = collect($report->approvalTimeline())->where('status', 'approved')->keyBy('role');

    /** The stamp for one chain step, or null while it is still unsigned. */
    $stamp = fn (string $role, string $word) => $signed->has($role)
        ? ['actor' => $signed[$role]['actor'], 'date' => $signed[$role]['acted_at'], 'word' => $word]
        : null;

    // Sending it to MEPEO is the engineer's sign-off; submitting it back is
    // the representative's.
    $prepared = $report->sent_at
        ? ['actor' => $report->creator?->name, 'date' => $report->sent_at->format('M d, Y h:i A'), 'word' => 'PREPARED']
        : null;
    $verified = $report->submitted_at && in_array($report->status, ['for_approval', 'approved'], true)
        ? ['actor' => $report->mepeoRep?->name, 'date' => $report->submitted_at->format('M d, Y h:i A'), 'word' => 'VERIFIED']
        : null;

    // Rows the aspects column runs to: the impacts sit beside the first seven,
    // the Mitigation heading beside the eighth, its text beside the rest.
    $aspectRows  = count($aspects) + 1;
    $impactRows  = 7;
    $mitigateRows = $aspectRows - $impactRows - 1;
@endphp

@section('form')
    {{-- FRM-19 belongs to the Environmental Management System, not PMD, so
         it keeps that system's own banner and type rather than the shared PMD
         header: crests and corporate block in one box, the title beneath, and
         the document control in a row of four under that. The body is set in
         Cambria, as the controlled form is.

         The form is one sheet: five signature blocks and two checklists share
         the page, so rows sit tighter than on the other PMD forms. --}}
    <style>
        body { font-family: Cambria, 'Times New Roman', Georgia, serif; }
        /* On screen the page reads as the A4 sheet it prints on. */
        @media screen { body { max-width: 794px; margin: 34px auto; } }

        .eia-hdr { font-family: Calibri, 'Segoe UI', Arial, sans-serif; }
        .eia-hdr .brand { display: flex; align-items: center; justify-content: space-between; border: 1px solid #000; padding: 5px 10px; }
        .eia-hdr .brand img { height: 62px; width: 62px; object-fit: contain; }
        .eia-hdr .brand .mid { text-align: center; flex: 1; }
        .eia-hdr .brand .c1 { font-family: Cambria, 'Times New Roman', serif; font-weight: 700; font-size: 15px; letter-spacing: .2px; }
        .eia-hdr .brand .c2 { font-family: Cambria, 'Times New Roman', serif; font-weight: 700; font-size: 13.5px; }
        .eia-hdr .brand .c3 { font-size: 13px; margin-top: 1px; }
        .eia-hdr .title { border: 1px solid #000; border-top: none; text-align: center; font-weight: 800; font-size: 16px; padding: 2px 6px; letter-spacing: .3px; }
        .eia-hdr .ctrl { display: grid; grid-template-columns: repeat(4, 1fr); border-bottom: 1.5px solid #000; }
        .eia-hdr .ctrl div { border: 1px solid #000; border-top: none; border-left-width: 0; text-align: center; font-size: 10.5px; padding: 1px 4px; }
        .eia-hdr .ctrl div:first-child { border-left-width: 1px; }

        .ems-meta { display: flex; justify-content: space-between; align-items: baseline; margin: 14px 0 8px; padding: 0 12px; font-size: 13px; }
        .ems-meta .fill { min-width: 200px; text-align: center; }
        table.box th.hd, table.box td.band { background: #ffff00; }
        table.box td, table.box th { padding-top: 2px; padding-bottom: 2px; font-size: 12px; }
        table.box.quarters { table-layout: fixed; }
        table.box td.txt { white-space: pre-wrap; font-size: 11.5px; }
        .gap { margin-top: 8px; }
        .cb {
            display: inline-block; width: 11px; height: 11px; border: 1px solid #000;
            margin-right: 6px; vertical-align: -1px; text-align: center;
            font-size: 10px; line-height: 10px; font-weight: 900;
        }
        .other { border-bottom: 1px solid #000; display: inline-block; min-width: 180px; }
        .mepeo-note { font-style: italic; font-size: 12px; margin: 10px 0 3px; padding-top: 6px; border-top: 2.5px solid #000; }
        .sig .cell { min-height: 64px; padding-top: 5px; padding-bottom: 6px; }
        .sig .cell.stamped, .sig:has(.stamped) .cell { min-height: 96px; }
        .sig .cell.stamped .name, .sig:has(.stamped) .name { margin-top: 49px; }
        .sig .stamp { top: 19px; }
        .sig.tight.gap { margin-top: 8px; }
    </style>

    <div class="eia-hdr">
        <div class="brand">
            <img src="{{ \App\Support\PdfRenderer::embeddedImage(public_path('logow.png')) }}" alt="PMC">
            <div class="mid">
                <div class="c1">PHILSAGA MINING CORPORATION</div>
                <div class="c2">MINDANAO MINERAL PROCESSING and REFINING CORPORATION</div>
                <div class="c3">Environmental Management System</div>
            </div>
            <img src="{{ \App\Support\PdfRenderer::embeddedImage(public_path('logo@2xb.png')) }}" alt="MMPRC">
        </div>
        <div class="title">ENVIRONMENTAL IMPACT ASSESSMENT</div>
        <div class="ctrl">
            <div>Doc No.: PMD-PRJ-FRM-19</div>
            <div>Revision No.: 00</div>
            <div>Effective: January 19, 2026</div>
            <div>Sheet No.: Page 1 of 1</div>
        </div>
    </div>

    <div class="ems-meta">
        <div>Date Requested: <span class="fill">{{ optional($report->date_requested)->format('F d, Y') }}</span></div>
        <div>Control Number: <b>{{ $report->control_no }}</b></div>
    </div>

    <table class="box quarters">
        <colgroup><col><col><col><col></colgroup>
        <tr>
            <td>Project Title</td>
            <td colspan="3">{{ $project->title }}</td>
        </tr>
        <tr>
            <td>Project Number</td><td>{{ $project->project_no }}</td>
            <td>Project Location</td><td>{{ $report->location }}</td>
        </tr>
        <tr>
            <td>Project Owner</td><td>{{ $project->dept_owner }}</td>
            <td>Target Start Date</td><td>{{ optional($report->target_start)->format('M d, Y') }}</td>
        </tr>
        <tr>
            <td>Contractor</td><td>{{ $report->contractor }}</td>
            <td>Target End Date</td><td>{{ optional($report->target_end)->format('M d, Y') }}</td>
        </tr>
    </table>

    <table class="box gap">
        <tr>
            <th class="hd" style="width:50%;">Major Activities</th>
            <th class="hd">Other Scope of Work</th>
        </tr>
        @foreach ($activities as $key => $label)
            <tr>
                <td><span class="cb">{{ $ticked($activityOn, $key) ? '✓' : '' }}</span>{{ $label }}</td>
                @if ($loop->first)
                    <td rowspan="{{ count($activities) + 1 }}" class="txt">{{ $report->other_scope }}</td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td><span class="cb">{{ filled($report->major_activities_other) ? '✓' : '' }}</span>Others: <span class="other">{{ $report->major_activities_other }}</span></td>
        </tr>
    </table>

    <table class="box gap">
        <tr>
            <th class="hd" style="width:50%;">Environmental Aspects</th>
            <th class="hd">Environmental Impacts</th>
        </tr>
        @foreach (array_values(array_keys($aspects)) as $i => $key)
            <tr>
                <td><span class="cb">{{ $ticked($aspectOn, $key) ? '✓' : '' }}</span>{{ $aspects[$key] }}</td>
                @if ($i === 0)
                    <td rowspan="{{ $impactRows }}" class="txt">{{ $report->impacts }}</td>
                @elseif ($i === $impactRows)
                    <td class="band">Mitigation</td>
                @elseif ($i === $impactRows + 1)
                    <td rowspan="{{ $mitigateRows }}" class="txt">{{ $report->mitigation }}</td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td><span class="cb">{{ filled($report->aspects_other) ? '✓' : '' }}</span>Others: <span class="other">{{ $report->aspects_other }}</span></td>
        </tr>
    </table>

    <div class="sig tight gap">
        @include('print.partials.signature', [
            'role' => 'Prepared by:', 'title' => 'Project Management Engineer',
            'name' => $report->creator?->name ?? '', 'approval' => $prepared,
        ])
        @include('print.partials.signature', [
            'role' => 'Reviewed by:', 'title' => 'PMD Assistant Manager',
            'name' => $pmdNames['pmd_asst_manager'], 'approval' => $stamp('pmd_asst_manager', 'REVIEWED'),
        ])
        @include('print.partials.signature', [
            'role' => 'Noted by:', 'title' => 'PMD Manager',
            'name' => $pmdNames['pmd_dept_manager'], 'approval' => $stamp('pmd_dept_manager', 'NOTED'),
        ])
    </div>

    <div class="mepeo-note">To be filled-out by MEPEO:</div>

    <table class="box">
        <tr><td class="band">Status</td></tr>
        @foreach ($statuses as $key => $label)
            <tr>
                <td>
                    <span class="cb">{{ $report->mepeo_status === $key ? '✓' : '' }}</span>
                    @if ($key === 'proceed')
                        <b>Notice To Proceed</b> with the activity (no need for an Environmental Survey Report)
                    @else
                        {{ $label }}
                    @endif
                </td>
            </tr>
        @endforeach
        <tr><td class="band">Remarks/Comments</td></tr>
        <tr><td class="txt" style="height:40px;">{{ $report->remarks }}</td></tr>
    </table>

    <div class="sig two tight gap">
        @include('print.partials.signature', [
            'role' => 'Inspected/Verified by:', 'title' => 'MEPEO Representative',
            'name' => $report->mepeoRep?->name ?? '', 'approval' => $verified,
        ])
        @include('print.partials.signature', [
            'role' => 'Approved by:', 'title' => 'MEPEO Manager',
            'name' => $pmdNames['mepeo_manager'], 'approval' => $stamp('mepeo_manager', 'APPROVED'),
        ])
    </div>
@endsection
