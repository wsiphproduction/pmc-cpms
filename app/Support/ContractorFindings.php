<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\AuditTrail;
use App\Models\ContractorEvaluation;
use App\Models\ContractorFinding;
use App\Support\ContractorEvaluationScoring as Scoring;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * What the non-compliance register shares between the hub, the monitoring
 * page and the contractor's portal: the row shape, file handling, and the
 * records raised from an approved evaluation.
 */
class ContractorFindings
{
    public const FILE_RULE = 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx';

    /**
     * Each "Required Corrective Actions" an approved evaluation recorded becomes
     * a non-compliance record, so it is tracked to closure rather than left in
     * the form. Safe to call twice: one record per evaluation category.
     *
     * @return int how many records were raised
     */
    public static function raiseFromEvaluation(ContractorEvaluation $evaluation): int
    {
        $evaluation->loadMissing('categories');
        $raised = 0;

        foreach ($evaluation->categories as $row) {
            if (blank($row->corrective_actions)) {
                continue;
            }

            $exists = ContractorFinding::withTrashed()
                ->where('contractor_evaluation_id', $evaluation->id)
                ->where('category', $row->category)
                ->exists();

            if ($exists) {
                continue;
            }

            $title = Scoring::categories()[$row->category]['title'] ?? $row->category;

            $finding = ContractorFinding::create([
                'project_id'               => $evaluation->project_id,
                'contractor_evaluation_id' => $evaluation->id,
                'category'                 => $row->category,
                'contractor_name'          => $evaluation->contractor_name,
                'supplier_id'              => $evaluation->supplier_id,
                'deficiency'               => filled($row->comments)
                    ? $row->comments
                    : "Shortfall found in {$title} on evaluation {$evaluation->control_no} ({$evaluation->period_label}).",
                'date_identified'          => ($evaluation->approved_at ?? now())->toDateString(),
                'corrective_action'        => $row->corrective_actions,
                'status'                   => ContractorFinding::STATUS_OPEN,
                'created_by'               => $evaluation->created_by,
            ]);

            AuditTrail::log("Non-compliance record {$finding->control_no} raised from evaluation {$evaluation->control_no} ({$title})", $evaluation->project, ['module' => 'Non-Compliance', 'type' => 'create']);
            $raised++;
        }

        return $raised;
    }

    /** A record as the hub and the monitoring page show it. */
    public static function row(ContractorFinding $finding): array
    {
        $files = fn (string $type) => $finding->attachments
            ->where('type', $type)
            ->map(fn (Attachment $a) => ['id' => $a->id, 'filename' => $a->filename, 'url' => $a->url])
            ->values();

        return [
            'id'                => $finding->id,
            'control_no'        => $finding->control_no,
            'project_id'        => $finding->project_id,
            'project_no'        => $finding->project?->project_no,
            'project_title'     => $finding->project?->title,
            'department'        => $finding->project?->dept_owner,
            'evaluation'        => $finding->evaluation ? ['id' => $finding->evaluation->id, 'control_no' => $finding->evaluation->control_no] : null,
            'category'          => $finding->category,
            'category_label'    => $finding->categoryLabel(),
            'contractor_name'   => $finding->contractor_name,
            'on_master_data'    => $finding->supplier_id !== null,
            'supplier_emails'   => $finding->supplier?->emails ?? [],
            'deficiency'        => $finding->deficiency,
            'date_identified'   => $finding->date_identified?->toDateString(),
            'corrective_action' => $finding->corrective_action,
            'target_date'       => $finding->target_date?->toDateString(),
            'status'            => $finding->status,
            'status_label'      => $finding->statusLabel(),
            'overdue'           => $finding->isOverdue(),
            'days_open'         => $finding->daysOpen(),
            'contractor_response' => $finding->contractor_response,
            'responded_at'      => $finding->responded_at?->format('M d, Y h:i A'),
            // Null when the contractor answered through the portal themselves.
            'response_recorded_by' => $finding->responseRecorder?->name,
            'closure_date'      => $finding->closure_date?->toDateString(),
            'closure_remarks'   => $finding->closure_remarks,
            'closed_by'         => $finding->closer?->name,
            'notified_email'    => $finding->notified_email,
            'notified_at'       => $finding->notified_at?->format('M d, Y h:i A'),
            'portal_url'        => $finding->portalUrl(),
            'raised_by'         => $finding->creator?->name,
            'evidence'          => $files(ContractorFinding::FILE_EVIDENCE),
            'response_files'    => $files(ContractorFinding::FILE_RESPONSE),
            'closure_files'     => $files(ContractorFinding::FILE_CLOSURE),
        ];
    }

    /** What a row needs eager-loaded. */
    public const WITH = ['project:id,project_no,title,dept_owner', 'evaluation:id,control_no', 'supplier:id,company,email', 'creator:id,name', 'closer:id,name', 'responseRecorder:id,name', 'attachments'];

    /** @param  UploadedFile[]  $files */
    public static function attach(ContractorFinding $finding, array $files, string $type): void
    {
        foreach ($files as $file) {
            $path = $file->store("contractor-findings/{$finding->id}", 'public');

            $attachment = $finding->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'type'     => $type,
            ]);

            $attachment->recordFileVersion($path, $file->getClientOriginalName(), mimeType: $file->getMimeType(), size: $file->getSize());
        }
    }

    public static function purge(Attachment $attachment): void
    {
        $attachment->purgeFileVersions();
        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();
    }
}
