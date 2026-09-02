{{--
    Project Completion and Acceptance Certificate — PMD-PRJ-FRM-06.
--}}
@extends('print.layout', ['title' => 'Acceptance Certificate — ' . $project->project_no])

@php
    $short = fn ($date) => optional($date)->format('d-M-y') ?? '';
    $long  = fn ($date) => optional($date)->format('F j, Y') ?? '';
@endphp

@section('form')
    @include('print.partials.header', [
        'docNo' => 'PMD-PRJ-FRM-06', 'rev' => '00',
        'effective' => 'October 05, 2025',
        'title' => 'PROJECT COMPLETION AND ACCEPTANCE CERTIFICATE',
    ])

    <h3 class="sec">
        PROJECT DETAILS
        <span style="float:right;font-weight:400;">Reference No.: {{ $completion?->reference_no }}</span>
    </h3>

    <table class="kv">
        @foreach ([
            ['Project Number',     $project->project_no],
            ['Project Title',      $project->title],
            ['Sub-Project Title',  $completion?->sub_project_title ?: 'N/A'],
            ['Job Site/Location',  $project->site],
            ['Project Owner',      $project->dept_owner],
            ['Cost Code',          $project->cost_code],
            ['Service Contractor', $completion?->contractor],
            ['Actual Start Date',  $short($completion?->con_actual_start)],
            ['Actual End Date',    $short($completion?->con_actual_end)],
        ] as [$label, $value])
            <tr><td class="k">{{ $label }}</td><td>:</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <p class="para">This is to formally certify that the project referenced above has been successfully completed in full compliance with the approved design specifications, standard engineering practices, and all relevant codes, safety regulations, and contractual obligations.</p>
    <p class="para">The completion of this project reflects the diligent efforts, coordination, and technical competence demonstrated throughout all phases of execution, from planning and mobilization to implementation, testing, and final inspection.</p>
    <p class="para">We further certify that the quality standards, performance criteria, and full scope of work, as outlined in the original Work Request, Quotation, Inspection and Test Plan, and/or Construction/Service Agreement, have been entirely fulfilled. All deliverables have been reviewed, verified, and accepted by the Project Management Department (PMD) and the designated end-user representative.</p>
    <p class="para">Comprehensive inspections, quality assurance procedures, and functional testing have been conducted where applicable, and the completed work meets or exceeds the expectations and requirements set forth at the outset of the project.</p>
    <p class="para">This certificate is issued as a formal recognition of the project's successful completion, and it signifies the transfer of responsibility from the executing party to the end-user.</p>

    <p style="margin-top:14px;"><strong>Issued on:</strong> {{ $long($completion?->issued_on) }}</p>

    <div class="sig">
        @include('print.partials.signature', ['role' => 'Prepared by:', 'title' => 'Project Management Engineer',              'name' => $signatories['prepared_by'] ?? '',           'approval' => null])
        @include('print.partials.signature', ['role' => 'Reviewed by:', 'title' => 'Project Management Dept. Asst. Manager',  'name' => $signatories['pmd_assistant_manager'] ?? '', 'approval' => null])
        @include('print.partials.signature', ['role' => 'Received by:', 'title' => 'Project Owner Representative',            'name' => $completion?->received_by ?? '',             'approval' => null])
        @include('print.partials.signature', ['role' => 'Noted by:',    'title' => 'Project Management Dept. Manager',        'name' => $signatories['pmd_manager'] ?? '',           'approval' => null])
        @include('print.partials.signature', ['role' => 'Endorsed by:', 'title' => 'ECS Division Manager',                    'name' => $signatories['ecs_division_manager'] ?? '',  'approval' => null])
        @include('print.partials.signature', ['role' => 'Accepted by:', 'title' => 'Project Owner Division Manager',          'name' => $completion?->accepted_by ?? '',             'approval' => null])
    </div>
@endsection
