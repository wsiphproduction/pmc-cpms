<?php

namespace App\Support;

use App\Models\EmsSurveyReport;
use App\Models\Project;

/**
 * One EMS Survey Report as the pages need it — the hub, the MEPEO page and
 * the For Approval portal all show the same record, so they share this shape.
 */
class EmsReportPresenter
{
    public static function row(EmsSurveyReport $report, ?Project $sub = null): array
    {
        $project = $report->project;

        return [
            'id'                     => $report->id,
            'control_no'             => $report->control_no,
            'status'                 => $report->status,
            'status_label'           => $report->statusLabel(),
            'date_requested'         => $report->date_requested?->toDateString(),
            'location'               => $report->location,
            'contractor'             => $report->contractor,
            'target_start'           => $report->target_start?->toDateString(),
            'target_end'             => $report->target_end?->toDateString(),
            'major_activities'       => $report->major_activities ?? [],
            'major_activities_other' => $report->major_activities_other,
            'other_scope'            => $report->other_scope,
            'aspects'                => $report->aspects ?? [],
            'aspects_other'          => $report->aspects_other,
            'impacts'                => $report->impacts,
            'mitigation'             => $report->mitigation,
            'mepeo_status'           => $report->mepeo_status,
            'remarks'                => $report->remarks,
            'return_remarks'         => $report->return_remarks,
            'mepeo_rep'              => $report->mepeoRep ? ['id' => $report->mepeoRep->id, 'name' => $report->mepeoRep->name] : null,
            'prepared_by'            => $report->creator?->name,
            'sent_at'                => $report->sent_at?->format('M d, Y h:i A'),
            'submitted_at'           => $report->submitted_at?->format('M d, Y h:i A'),
            'created_at'             => $report->created_at?->format('M d, Y'),
            'approvals'              => $report->approvalTimeline(),
            'current_role'           => $report->status === EmsSurveyReport::STATUS_FOR_APPROVAL ? $report->currentApprovalRole() : null,
            'print_url'              => route('ems.print', $report->id),
            'project'                => [
                'id'         => $project?->id,
                'project_no' => $project?->project_no,
                'title'      => $project?->title,
                'site'       => $project?->site,
                'dept_owner' => $project?->dept_owner,
            ],
            'sub_project_id'         => $sub?->id,
            'sub_project_no'         => $sub?->project_no,
        ];
    }

    /** The form's checklists and finding options, from config/ems.php. */
    public static function options(): array
    {
        $pairs = fn (array $map) => collect($map)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();

        return [
            'major_activities' => $pairs(config('ems.major_activities')),
            'aspects'          => $pairs(config('ems.aspects')),
            'statuses'         => $pairs(config('ems.statuses')),
        ];
    }
}
