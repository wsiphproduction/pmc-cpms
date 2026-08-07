<?php

namespace App\Http\Controllers;

use App\Mail\RfqDispatched;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\Mail;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectIocItem;
use App\Models\ProjectMtrDoc;
use App\Models\Notification;
use App\Models\ProjectNtp;
use App\Models\ProjectPermit;
use App\Models\ProjectQualityDoc;
use App\Models\ProjectRfq;
use App\Models\ProjectVariationOrder;
use App\Models\ProjectTask;
use App\Models\ProjectWeeklyReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\PsrTemplateWriter;
use App\Support\WeeklyReportSheet;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectHubController extends Controller
{
    // ── RFQ ──────────────────────────────────────────────────────────────────

    public function storeRfq(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'contractor_name' => [
                'required', 'string', 'max:255',
                function ($attr, $value, $fail) use ($project) {
                    if ($project->rfqs()->where('contractor_name', $value)->exists()) {
                        $fail("An RFQ has already been sent to {$value} for this project.");
                    }
                },
            ],
            'due_date'        => ['nullable', 'date'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'additional_recipients'   => ['nullable', 'array'],
            'additional_recipients.*' => ['email', 'max:255'],
            'cc_self'         => ['nullable', 'boolean'],
        ]);

        $rfq = $project->rfqs()->create([
            'contractor_name' => $data['contractor_name'],
            'due_date'        => $data['due_date'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'sent_date'       => now()->toDateString(),
            'status'          => 'pending',
            'created_by'      => auth()->id(),
        ]);

        if (!empty($data['recipient_email'])) {
            $ccRecipients = $data['additional_recipients'] ?? [];
            if ($data['cc_self'] ?? false) {
                $ccRecipients[] = auth()->user()->email;
            }

            try {
                Mail::to($data['recipient_email'])
                    ->when(!empty($ccRecipients), fn ($mail) => $mail->cc($ccRecipients))
                    ->send(new RfqDispatched($rfq, $project));
            } catch (\Throwable $e) {
                \Log::error("RFQ email failed for RFQ #{$rfq->id}: " . $e->getMessage());
            }
        }

        AuditTrail::log("Dispatched RFQ to {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'create', 'rfq_id' => $rfq->id]);

        return back()->with('success', 'RFQ dispatched successfully.');
    }

    public function updateRfq(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        // All quotation fields are required except the file attachment.
        $data = $request->validate([
            'scope_of_work'       => ['required', 'string'],
            'due_date'            => ['required', 'date'],
            'duration_days'       => ['required', 'integer', 'min:1'],
            'terms_conditions'    => ['required', 'string'],
            'inclusions'          => ['required', 'string'],
            'exclusions'          => ['required', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.qty'         => ['nullable', 'numeric', 'min:0'],
            'items.*.unit'        => ['nullable', 'string', 'max:50'],
            'items.*.unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'items.*.total_cost'  => ['nullable', 'numeric'],
            'quotation_file'      => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        // Capture field-level old → new changes for the audit trail.
        $labels = [
            'scope_of_work'    => 'Scope of Work',
            'due_date'         => 'Due Date',
            'duration_days'    => 'Duration (days)',
            'terms_conditions' => 'Terms & Conditions',
            'inclusions'       => 'Inclusions',
            'exclusions'       => 'Exclusions',
        ];
        $newValues = [
            'scope_of_work'    => ($data['scope_of_work']    ?? '') ?: null,
            'due_date'         => ($data['due_date']         ?? '') ?: null,
            'duration_days'    => ($data['duration_days']    ?? '') ?: null,
            'terms_conditions' => ($data['terms_conditions'] ?? '') ?: null,
            'inclusions'       => ($data['inclusions']       ?? '') ?: null,
            'exclusions'       => ($data['exclusions']       ?? '') ?: null,
        ];
        $stringify = function ($value) {
            if ($value === null) return '';
            if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
            return (string) $value;
        };
        $changedFields = [];
        foreach ($newValues as $field => $newValue) {
            $oldStr = $stringify($rfq->getOriginal($field));
            $newStr = $stringify($newValue);
            if ($oldStr !== $newStr) {
                $changedFields[] = ['field' => $labels[$field], 'old' => $oldStr, 'new' => $newStr];
            }
        }

        // Snapshot the file and line items *before* they are changed below, so we
        // can record their old → new values in the audit trail too.
        $oldFile  = $rfq->quotation_file;
        $oldItems = $rfq->items()->get(['description', 'qty', 'unit', 'unit_cost', 'total_cost'])
            ->map(fn ($it) => [
                'description' => $it->description,
                'qty'         => $it->qty !== null ? (float) $it->qty : null,
                'unit'        => $it->unit,
                'unit_cost'   => $it->unit_cost !== null ? (float) $it->unit_cost : null,
                'total_cost'  => $it->total_cost !== null ? (float) $it->total_cost : null,
            ])->all();

        // Renders a line-item list as one line per item for a readable diff.
        $fmtItems = function (array $items): string {
            $num = fn ($n) => $n === null ? '—' : number_format((float) $n, 2);
            $lines = [];
            foreach ($items as $it) {
                $desc = trim((string) ($it['description'] ?? '')) ?: '(no description)';
                $lines[] = sprintf(
                    '%s — %s %s @ %s = %s',
                    $desc,
                    $it['qty'] !== null ? rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.') : '—',
                    trim((string) ($it['unit'] ?? '')) ?: '—',
                    $num($it['unit_cost'] ?? null),
                    $num($it['total_cost'] ?? null),
                );
            }
            return implode("\n", $lines);
        };

        $rfq->update($newValues);

        if (!empty($data['quotation_file'])) {
            if ($oldFile) {
                Storage::disk('public')->delete($oldFile);
            }
            $path = $data['quotation_file']->store('rfq-files', 'public');
            $rfq->update(['quotation_file' => $path]);

            $changedFields[] = [
                'field' => 'Quotation File',
                'old'   => $oldFile ? basename($oldFile) : '',
                'new'   => basename($path),
            ];
        }

        if (!empty($data['items'])) {
            // Build the normalized new item list first so it can feed both the
            // insert and the audit diff.
            $newItems = [];
            foreach (array_values($data['items']) as $item) {
                if (empty($item['description'])) continue;
                $newItems[] = [
                    'description' => $item['description'],
                    'qty'         => ($item['qty']        ?? '') !== '' ? (float) $item['qty']        : null,
                    'unit'        => ($item['unit']       ?? '') ?: null,
                    'unit_cost'   => ($item['unit_cost']  ?? '') !== '' ? (float) $item['unit_cost']  : null,
                    'total_cost'  => ($item['total_cost'] ?? '') !== '' ? (float) $item['total_cost'] : null,
                ];
            }

            $rfq->items()->delete();
            foreach ($newItems as $i => $item) {
                $rfq->items()->create(array_merge(['seq' => $i + 1], $item));
            }

            $oldItemsStr = $fmtItems($oldItems);
            $newItemsStr = $fmtItems($newItems);
            if ($oldItemsStr !== $newItemsStr) {
                $changedFields[] = ['field' => 'Line Items', 'old' => $oldItemsStr, 'new' => $newItemsStr];
            }
        }

        AuditTrail::log("Updated RFQ details for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => $changedFields]);

        return back()->with('success', 'Quotation details saved.');
    }

    public function updateRfqStatus(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,submitted,awarded,expired'],
        ]);

        $oldStatus = (string) $rfq->getOriginal('status');
        $rfq->update($data);

        $statusFields = $oldStatus !== $data['status']
            ? [['field' => 'Status', 'old' => $oldStatus, 'new' => $data['status']]]
            : [];

        AuditTrail::log("RFQ status updated to {$data['status']} for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => $statusFields]);

        return back()->with('success', 'RFQ status updated.');
    }

    public function destroyRfq(Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        AuditTrail::log("Deleted RFQ for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'delete', 'rfq_id' => $rfq->id]);
        $rfq->delete();

        return back()->with('success', 'RFQ deleted.');
    }

    // ── NTP ──────────────────────────────────────────────────────────────────

    public function storeNtp(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'contractor_name' => ['required', 'string', 'max:255'],
            'project_rfq_id'  => [
                'nullable', 'exists:project_rfqs,id',
                function ($attr, $value, $fail) use ($project) {
                    // A rejected NTP can be re-submitted; only block if one is still
                    // pending review or already issued for this RFQ.
                    if ($value && $project->ntps()->where('project_rfq_id', $value)->where('status', '!=', 'rejected')->exists()) {
                        $fail('An NTP is already pending review or issued for this RFQ.');
                    }
                },
            ],
            'baseline_start'  => ['required', 'date'],
            'baseline_end'    => ['required', 'date', 'after:baseline_start'],
            'approved_cost'   => ['required', 'numeric', 'min:0'],
        ]);

        $ntpNo = $this->nextNtpNo();

        // Submitted for department-user review — NOT issued yet. The RFQ is not
        // awarded and the budget is not recalculated until the NTP is approved.
        $project->ntps()->create([
            ...$data,
            'ntp_no'      => $ntpNo,
            'status'      => 'pending_review',
            'created_by'  => auth()->id(),
        ]);

        AuditTrail::log("NTP {$ntpNo} submitted for department review ({$data['contractor_name']})", $project, array_filter(['module' => 'NTP', 'type' => 'create', 'rfq_id' => $data['project_rfq_id'] ?? null]));

        // Notify the project's department user (requester) that an NTP awaits review.
        if ($requesterId = $project->projectRequest?->requester_id) {
            Notification::notify(
                $requesterId,
                "NTP {$ntpNo} for {$data['contractor_name']} is awaiting your review on project {$project->project_no}.",
                route('ntp-reviews.index', absolute: false)
            );
        }

        return back()->with('success', "NTP {$ntpNo} submitted for department review.");
    }

    public function destroyNtp(Project $project, ProjectNtp $ntp): RedirectResponse
    {
        abort_unless((int) $ntp->project_id === (int) $project->id, 403);

        AuditTrail::log("NTP {$ntp->ntp_no} deleted", $project, ['module' => 'NTP', 'type' => 'delete']);
        $ntp->delete();

        // Budget is set manually via the project's Project Cost field — not derived
        // from NTPs — so deleting an NTP no longer changes the project budget.

        return back()->with('success', 'NTP deleted.');
    }

    // ── Permits ───────────────────────────────────────────────────────────────

    public function storePermit(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'label'    => ['required', 'string', 'max:255'],
            'doc_type' => ['required', 'string', 'max:100'],
            'files'    => ['required', 'array', 'min:1'],
            'files.*'  => ['file', 'max:20480'],
        ]);

        $permit = $project->permits()->create([
            'label'      => $data['label'],
            'doc_type'   => $data['doc_type'],
            'created_by' => auth()->id(),
        ]);

        foreach ($request->file('files', []) as $file) {
            $path = $file->store("hub/permits/{$project->id}", 'public');
            $permit->files()->create([
                'filename'  => $file->getClientOriginalName(),
                'path'      => $path,
                'mime_type' => $file->getMimeType(),
            ]);
        }

        AuditTrail::log("Permit added: {$data['label']} ({$data['doc_type']})", $project, ['module' => 'Permit', 'type' => 'upload']);

        return back()->with('success', 'Permit record added.');
    }

    public function destroyPermit(Project $project, ProjectPermit $permit): RedirectResponse
    {
        abort_unless((int) $permit->project_id === (int) $project->id, 403);

        AuditTrail::log("Permit deleted: {$permit->label}", $project, ['module' => 'Permit', 'type' => 'delete']);

        foreach ($permit->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $permit->delete();

        return back()->with('success', 'Permit deleted.');
    }

    // ── Variation Orders ──────────────────────────────────────────────────────

    public function storeVof(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'duration_days'     => ['nullable', 'integer', 'min:0'],
            'requestor'         => ['nullable', 'string', 'max:255'],
            'date_of_request'   => ['nullable', 'date'],
            'priority'          => ['nullable', 'string', 'max:255'],
            'attachment'        => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'scope_original'    => ['nullable', 'string'],
            'scope_proposed'    => ['nullable', 'string'],
            'scope_remark'      => ['nullable', 'string'],
            'schedule_original' => ['nullable', 'string'],
            'schedule_proposed' => ['nullable', 'string'],
            'schedule_remark'   => ['nullable', 'string'],
            'cost_original'     => ['nullable', 'string'],
            'cost_proposed'     => ['nullable', 'string'],
            'cost_remark'       => ['nullable', 'string'],
        ]);

        $voNo = $this->nextVoNo();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('vof-files', 'public');
        }

        $project->variationOrders()->create([
            'title'             => $data['title'],
            'description'       => ($data['description']       ?? '') ?: null,
            'amount'            => $data['amount'],
            'duration_days'     => ($data['duration_days']     ?? '') !== '' ? (int) $data['duration_days'] : null,
            'requestor'         => ($data['requestor']         ?? '') ?: null,
            'date_of_request'   => ($data['date_of_request']   ?? '') ?: null,
            'priority'          => ($data['priority']           ?? '') ?: null,
            'attachment'        => $attachmentPath,
            'scope_original'    => ($data['scope_original']    ?? '') ?: null,
            'scope_proposed'    => ($data['scope_proposed']    ?? '') ?: null,
            'scope_remark'      => ($data['scope_remark']      ?? '') ?: null,
            'schedule_original' => ($data['schedule_original'] ?? '') ?: null,
            'schedule_proposed' => ($data['schedule_proposed'] ?? '') ?: null,
            'schedule_remark'   => ($data['schedule_remark']   ?? '') ?: null,
            'cost_original'     => ($data['cost_original']     ?? '') ?: null,
            'cost_proposed'     => ($data['cost_proposed']     ?? '') ?: null,
            'cost_remark'       => ($data['cost_remark']       ?? '') ?: null,
            'vo_no'             => $voNo,
            'status'            => 'pending',
            'submitted_date'    => now()->toDateString(),
            'created_by'        => auth()->id(),
        ]);

        AuditTrail::log("Variation Order {$voNo} submitted: {$data['title']}", $project, ['module' => 'VOF', 'type' => 'create']);

        return back()->with('success', "Variation Order {$voNo} submitted.");
    }

    public function updateVof(Request $request, Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless((int) $vof->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'duration_days'     => ['nullable', 'integer', 'min:0'],
            'status'            => ['required', 'in:pending,approved,rejected'],
            'approved_date'     => ['nullable', 'date'],
            'requestor'         => ['nullable', 'string', 'max:255'],
            'date_of_request'   => ['nullable', 'date'],
            'priority'          => ['nullable', 'string', 'max:255'],
            'attachment'        => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'scope_original'    => ['nullable', 'string'],
            'scope_proposed'    => ['nullable', 'string'],
            'scope_remark'      => ['nullable', 'string'],
            'schedule_original' => ['nullable', 'string'],
            'schedule_proposed' => ['nullable', 'string'],
            'schedule_remark'   => ['nullable', 'string'],
            'cost_original'     => ['nullable', 'string'],
            'cost_proposed'     => ['nullable', 'string'],
            'cost_remark'       => ['nullable', 'string'],
        ]);

        $vof->update([
            'title'             => $data['title'],
            'description'       => ($data['description']       ?? '') ?: null,
            'amount'            => $data['amount'],
            'duration_days'     => ($data['duration_days']     ?? '') !== '' ? (int) $data['duration_days'] : null,
            'status'            => $data['status'],
            'approved_date'     => ($data['approved_date']     ?? '') ?: null,
            'requestor'         => ($data['requestor']         ?? '') ?: null,
            'date_of_request'   => ($data['date_of_request']   ?? '') ?: null,
            'priority'          => ($data['priority']           ?? '') ?: null,
            'scope_original'    => ($data['scope_original']    ?? '') ?: null,
            'scope_proposed'    => ($data['scope_proposed']    ?? '') ?: null,
            'scope_remark'      => ($data['scope_remark']      ?? '') ?: null,
            'schedule_original' => ($data['schedule_original'] ?? '') ?: null,
            'schedule_proposed' => ($data['schedule_proposed'] ?? '') ?: null,
            'schedule_remark'   => ($data['schedule_remark']   ?? '') ?: null,
            'cost_original'     => ($data['cost_original']     ?? '') ?: null,
            'cost_proposed'     => ($data['cost_proposed']     ?? '') ?: null,
            'cost_remark'       => ($data['cost_remark']       ?? '') ?: null,
        ]);

        if ($request->hasFile('attachment')) {
            $oldPath = $vof->fresh()->attachment;
            if ($oldPath) Storage::disk('public')->delete($oldPath);
            $path = $request->file('attachment')->store('vof-files', 'public');
            $vof->update(['attachment' => $path]);
        }

        AuditTrail::log("Variation Order {$vof->vo_no} updated", $project, ['module' => 'VOF', 'type' => 'update']);

        return back()->with('success', 'Variation order updated.');
    }

    public function updateVofStatus(Request $request, Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless((int) $vof->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $vof->update([
            'status'        => $data['status'],
            'approved_date' => $data['status'] === 'pending' ? null : ($vof->approved_date ?? now()->toDateString()),
        ]);

        AuditTrail::log("Variation Order {$vof->vo_no} status changed to {$data['status']}", $project, ['module' => 'VOF', 'type' => 'update']);

        return back()->with('success', 'Variation order status updated.');
    }

    public function destroyVof(Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless((int) $vof->project_id === (int) $project->id, 403);

        AuditTrail::log("Variation Order {$vof->vo_no} deleted: {$vof->title}", $project, ['module' => 'VOF', 'type' => 'delete']);
        $vof->delete();

        return back()->with('success', 'Variation order deleted.');
    }

    // ── Quality Docs ──────────────────────────────────────────────────────────

    public function storeQpp(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'label'    => ['required', 'string', 'max:255'],
            'doc_type' => ['required', 'string', 'max:100'],
            'file'     => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store("hub/qpp/{$project->id}", 'public');

        $project->qualityDocs()->create([
            'label'      => $data['label'],
            'doc_type'   => $data['doc_type'],
            'file_path'  => $path,
            'filename'   => $file->getClientOriginalName(),
            'created_by' => auth()->id(),
        ]);

        AuditTrail::log("Quality document uploaded: {$data['label']} ({$data['doc_type']})", $project, ['module' => 'QPP', 'type' => 'upload']);

        return back()->with('success', 'Quality document uploaded.');
    }

    public function destroyQpp(Project $project, ProjectQualityDoc $qpp): RedirectResponse
    {
        abort_unless((int) $qpp->project_id === (int) $project->id, 403);

        AuditTrail::log("Quality document deleted: {$qpp->label}", $project, ['module' => 'QPP', 'type' => 'delete']);
        Storage::disk('public')->delete($qpp->file_path);
        $qpp->delete();

        return back()->with('success', 'Document deleted.');
    }

    // ── MTR Docs ──────────────────────────────────────────────────────────────

    public function storeMtr(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'label'         => ['required', 'string', 'max:255'],
            'material_type' => ['required', 'string', 'max:100'],
            'file'          => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store("hub/mtr/{$project->id}", 'public');

        $project->mtrDocs()->create([
            'label'         => $data['label'],
            'material_type' => $data['material_type'],
            'test_date'     => now()->toDateString(),
            'file_path'     => $path,
            'filename'      => $file->getClientOriginalName(),
            'created_by'    => auth()->id(),
        ]);

        AuditTrail::log("Material test report uploaded: {$data['label']} ({$data['material_type']})", $project, ['module' => 'MTR', 'type' => 'upload']);

        return back()->with('success', 'Material test report logged.');
    }

    public function destroyMtr(Project $project, ProjectMtrDoc $mtr): RedirectResponse
    {
        abort_unless((int) $mtr->project_id === (int) $project->id, 403);

        AuditTrail::log("Material test report deleted: {$mtr->label}", $project, ['module' => 'MTR', 'type' => 'delete']);
        Storage::disk('public')->delete($mtr->file_path);
        $mtr->delete();

        return back()->with('success', 'Report deleted.');
    }

    // ── Billing (RFP) ─────────────────────────────────────────────────────────

    public function storeBilling(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'project_ntp_id' => ['nullable', 'exists:project_ntps,id'],
            'billing_type'   => ['required', 'string', 'max:50'],
            'period_from'    => ['nullable', 'date'],
            'period_to'      => ['nullable', 'date'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'progress_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'summary'        => ['nullable', 'string'],
            'remarks'        => ['nullable', 'string'],
            'attachments'    => ['nullable', 'array'],
            'attachments.*'  => ['string'],
            'recommendation' => ['nullable', 'string', 'max:255'],
            'file'           => ['nullable', 'file', 'max:20480'],
        ]);

        $stmtNo = $this->nextStmtNo();

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/rfp/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $project->billings()->create([
            'project_ntp_id' => ($data['project_ntp_id'] ?? '') ?: null,
            'billing_type'   => $data['billing_type'],
            'period_from'    => ($data['period_from']  ?? '') ?: null,
            'period_to'      => ($data['period_to']    ?? '') ?: null,
            'amount'         => $data['amount'],
            'progress_pct'   => ($data['progress_pct'] ?? '') !== '' ? $data['progress_pct'] : null,
            'summary'        => ($data['summary']      ?? '') ?: null,
            'remarks'        => ($data['remarks']      ?? '') ?: null,
            'attachments'    => $data['attachments']   ?? null,
            'recommendation' => ($data['recommendation'] ?? '') ?: null,
            'stmt_no'        => $stmtNo,
            'status'         => 'pending',
            'file_path'      => $filePath,
            'filename'       => $filename,
            'created_by'     => auth()->id(),
        ]);

        AuditTrail::log("Billing {$stmtNo} submitted ({$data['billing_type']}) — PhP {$data['amount']}", $project, ['module' => 'RFP', 'type' => 'finance']);

        $this->recalculateBudgetPaid($project);

        return back()->with('success', "Billing statement {$stmtNo} submitted.");
    }

    public function updateBilling(Request $request, Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless((int) $billing->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'billing_type' => ['required', 'string', 'max:50'],
            'period_from'  => ['nullable', 'date'],
            'period_to'    => ['nullable', 'date'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'progress_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'summary'      => ['nullable', 'string'],
            'remarks'      => ['nullable', 'string'],
            'attachments'  => ['nullable', 'array'],
            'attachments.*'=> ['string'],
            'recommendation' => ['nullable', 'string', 'max:255'],
            'file'         => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        // Status is changed only through the dedicated PM-gated status flow,
        // never here — Edit handles billing details and may run at any status.
        $billing->update([
            'billing_type' => $data['billing_type'],
            'period_from'  => ($data['period_from']  ?? '') ?: null,
            'period_to'    => ($data['period_to']    ?? '') ?: null,
            'amount'       => $data['amount'],
            'progress_pct' => ($data['progress_pct'] ?? '') !== '' ? $data['progress_pct'] : null,
            'summary'      => ($data['summary']      ?? '') ?: null,
            'remarks'      => ($data['remarks']      ?? '') ?: null,
            'attachments'  => $data['attachments']   ?? null,
            'recommendation' => ($data['recommendation'] ?? '') ?: null,
        ]);

        if ($request->hasFile('file')) {
            if ($billing->file_path) {
                Storage::disk('public')->delete($billing->file_path);
            }

            $file = $request->file('file');
            $path = $file->store("hub/rfp/{$project->id}", 'public');

            $billing->update([
                'file_path' => $path,
                'filename'  => $file->getClientOriginalName(),
            ]);
        }

        AuditTrail::log("Billing {$billing->stmt_no} updated ({$data['billing_type']})", $project, ['module' => 'RFP', 'type' => 'update']);

        $this->recalculateBudgetPaid($project);

        return back()->with('success', 'Billing updated.');
    }

    public function updateBillingStatus(Request $request, Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless((int) $billing->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'status'  => ['required', 'in:pending,approved'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $billing->update(['status' => $data['status']]);

        $remarks = trim($data['remarks'] ?? '');

        $billing->statusLogs()->create([
            'user_id' => auth()->id(),
            'status'  => $data['status'],
            'remarks' => $remarks !== '' ? $remarks : null,
        ]);

        $message = "Billing {$billing->stmt_no} status changed to {$data['status']}"
            . ($remarks !== '' ? " — {$remarks}" : '');

        AuditTrail::log($message, $project, ['module' => 'RFP', 'type' => 'finance']);

        $this->recalculateBudgetPaid($project);

        return back()->with('success', 'Billing status updated.');
    }

    public function destroyBilling(Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless((int) $billing->project_id === (int) $project->id, 403);

        AuditTrail::log("Billing {$billing->stmt_no} deleted ({$billing->billing_type})", $project, ['module' => 'RFP', 'type' => 'delete']);

        if ($billing->file_path) {
            Storage::disk('public')->delete($billing->file_path);
        }
        $billing->delete();

        $this->recalculateBudgetPaid($project);

        return back()->with('success', 'Billing record deleted.');
    }

    private function recalculateBudgetPaid(Project $project): void
    {
        // "Approved" billings are treated as paid — an approved statement is
        // cleared for payment and counts toward the project's paid total.
        $project->update([
            'budget_paid' => $project->billings()->where('status', 'approved')->sum('amount'),
        ]);
    }

    /**
     * Physical completion tracks the latest weekly report. Recompute it from the
     * most recent remaining report (0 when none are left) so deleting reports
     * doesn't leave a stale value behind. The weeklyReports relation is already
     * ordered latest-submitted-first.
     */
    private function recalculateCompletionPercent(Project $project): void
    {
        $project->refreshCompletionFromReports();
    }

    // ── IOC ───────────────────────────────────────────────────────────────────

    public function storeIoc(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'cost_code'   => ['nullable', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'file'        => ['nullable', 'file', 'max:20480'],
        ]);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/ioc/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $project->iocItems()->create([
            'description' => $data['description'],
            'cost_code'   => ($data['cost_code'] ?? '') ?: null,
            'amount'      => $data['amount'],
            'file_path'   => $filePath,
            'filename'    => $filename,
            'created_by'  => auth()->id(),
        ]);

        AuditTrail::log("Other cost logged: {$data['description']} — PhP {$data['amount']}", $project, ['module' => 'IOC', 'type' => 'create']);

        return back()->with('success', 'Cost entry saved.');
    }

    public function updateIoc(Request $request, Project $project, ProjectIocItem $ioc): RedirectResponse
    {
        abort_unless((int) $ioc->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'cost_code'   => ['nullable', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
        ]);

        $ioc->update([
            'description' => $data['description'],
            'cost_code'   => ($data['cost_code'] ?? '') ?: null,
            'amount'      => $data['amount'],
        ]);

        AuditTrail::log("Other cost updated: {$data['description']} — PhP {$data['amount']}", $project, ['module' => 'IOC', 'type' => 'update']);

        return back()->with('success', 'Cost entry updated.');
    }

    public function destroyIoc(Project $project, ProjectIocItem $ioc): RedirectResponse
    {
        abort_unless((int) $ioc->project_id === (int) $project->id, 403);

        AuditTrail::log("Other cost deleted: {$ioc->description}", $project, ['module' => 'IOC', 'type' => 'delete']);

        if ($ioc->file_path) {
            Storage::disk('public')->delete($ioc->file_path);
        }
        $ioc->delete();

        return back()->with('success', 'Cost entry deleted.');
    }

    // ── PSR (Weekly Reports) ──────────────────────────────────────────────────

    public function storePsr(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'project_ntp_id'           => ['nullable', 'exists:project_ntps,id'],
            'week_code'                => ['required', 'string', 'max:20'],
            'completion_pct'           => ['required', 'integer', 'min:0', 'max:100'],
            'identified_issues'        => ['nullable', 'string'],
            'progress_updates'         => ['nullable', 'string'],
            // Site checklist and issue / action-plan rows from the submission form.
            'checklist'                => ['nullable', 'array'],
            'checklist.*.seq'          => ['required', 'string', 'max:20'],
            'checklist.*.status'       => ['nullable', Rule::in(['√', '✕', 'Ø'])],
            'checklist.*.remarks'      => ['nullable', 'string'],
            'issues'                   => ['nullable', 'array'],
            'issues.*.issue'           => ['nullable', 'string'],
            'issues.*.action'          => ['nullable', 'string'],
            'issues.*.commitment_date' => ['nullable', 'date'],
            'file'                     => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/psr/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        // Store only the rows that were answered, so an untouched checklist or a
        // blank issue row doesn't read back as a deliberate blank.
        $checklist = collect($data['checklist'] ?? [])
            ->map(fn ($c) => [
                'seq'     => $c['seq'],
                'status'  => $c['status'] ?? null,
                'remarks' => $c['remarks'] ?? null,
            ])
            ->filter(fn ($c) => $c['status'] !== null || $c['remarks'] !== null)
            ->values()->all();

        $issues = collect($data['issues'] ?? [])
            ->map(fn ($i) => [
                'issue'           => $i['issue'] ?? null,
                'action'          => $i['action'] ?? null,
                'commitment_date' => isset($i['commitment_date'])
                    ? \Carbon\Carbon::parse($i['commitment_date'])->toDateString()
                    : null,
            ])
            ->filter(fn ($i) => $i['issue'] !== null || $i['action'] !== null || $i['commitment_date'] !== null)
            ->values()->all();

        // The first issue row doubles as the report's headline issue, matching the
        // CSV importer.
        $identifiedIssues = $data['identified_issues'] ?? ($issues[0]['issue'] ?? null);

        $report = $project->weeklyReports()->create([
            'project_ntp_id'    => ($data['project_ntp_id'] ?? '') ?: null,
            'week_code'         => $data['week_code'],
            'completion_pct'    => $data['completion_pct'],
            'identified_issues' => $identifiedIssues,
            'progress_updates'  => $data['progress_updates'] ?? null,
            'checklist'         => $checklist ?: null,
            'issues'            => $issues ?: null,
            'file_path'         => $filePath,
            'filename'          => $filename,
            'submitted_date'    => now()->toDateString(),
            'created_by'        => auth()->id(),
        ]);

        // Reflect the latest report's progress on the project.
        $this->recalculateCompletionPercent($project);

        AuditTrail::log("Weekly report {$report->week_code} submitted — {$data['completion_pct']}% complete", $project, ['module' => 'PSR', 'type' => 'upload']);

        return back()->with('success', "Weekly report {$report->week_code} submitted.");
    }

    /**
     * Download the detailed import template as a workbook, with dropdowns on the
     * checklist status columns.
     */
    public function psrTemplate(Project $project): Response
    {
        return response((new PsrTemplateWriter)->build(), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="psr-template-detailed.xlsx"',
        ]);
    }

    /**
     * Bulk-import weekly reports from a .csv or .xlsx file into this project.
     *
     * Column discovery and row normalisation live in WeeklyReportSheet, shared
     * with the cross-project Weekly Status import; ntp_no is matched against
     * this project's NTP numbers.
     */
    public function importPsr(Request $request, Project $project): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        try {
            $sheet = new WeeklyReportSheet($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not read the uploaded file.');
        }

        if (! $sheet->hasWeekCode()) {
            return back()->with('error', 'The file must contain a "week_code" column.');
        }

        // NTP number -> id lookup for this project (case-insensitive).
        $ntpByNo = $project->ntps()->pluck('id', 'ntp_no')
            ->mapWithKeys(fn ($id, $no) => [strtolower(trim((string) $no)) => $id]);

        $imported = 0;

        foreach ($sheet->reports() as $report) {
            $project->weeklyReports()->create([
                'project_ntp_id'    => $report['ntp_no'] !== null ? ($ntpByNo[strtolower($report['ntp_no'])] ?? null) : null,
                'week_code'         => $report['week_code'],
                'completion_pct'    => $report['completion_pct'],
                'identified_issues' => $report['identified_issues'],
                'progress_updates'  => $report['progress_updates'],
                'checklist'         => $report['checklist'] ?: null,
                'issues'            => $report['issues'] ?: null,
                'submitted_date'    => $report['submitted_date'],
                'created_by'        => auth()->id(),
            ]);

            $imported++;
        }

        if ($imported === 0) {
            return back()->with('error', 'No valid weekly reports found in the uploaded file.');
        }

        // Reflect the latest report's progress on the project.
        $this->recalculateCompletionPercent($project);

        AuditTrail::log("Imported {$imported} weekly report(s) from spreadsheet", $project, ['module' => 'PSR', 'type' => 'upload']);

        return back()->with('success', "Imported {$imported} weekly report(s).");
    }

    public function destroyPsr(Project $project, ProjectWeeklyReport $psr): RedirectResponse
    {
        abort_unless((int) $psr->project_id === (int) $project->id, 403);

        AuditTrail::log("Weekly report {$psr->week_code} deleted", $project, ['module' => 'PSR', 'type' => 'delete']);

        if ($psr->file_path) {
            Storage::disk('public')->delete($psr->file_path);
        }
        $psr->delete();

        // Deleting a report may change (or clear) the project's latest progress.
        $this->recalculateCompletionPercent($project);

        return back()->with('success', 'Weekly report deleted.');
    }

    // ── Todo ─────────────────────────────────────────────────────────────────

    public function storeTodo(Project $project, Request $request): RedirectResponse
    {
        $request->validate([
            'task_name'   => ['required', 'string', 'max:255'],
            'target_date' => ['required', 'date'],
        ]);

        $task = $project->tasks()->create([
            'task_name'   => $request->task_name,
            'target_date' => $request->target_date,
            'status'      => 'pending',
        ]);

        AuditTrail::log("Task added: {$task->task_name}", $project, ['module' => 'Todo', 'type' => 'create']);

        return back()->with('success', 'Task added.');
    }

    public function toggleTodo(Project $project, ProjectTask $task): RedirectResponse
    {
        abort_unless((int) $task->project_id === (int) $project->id, 403);

        $task->update(['status' => $task->status === 'done' ? 'pending' : 'done']);

        AuditTrail::log("Task '{$task->task_name}' marked as {$task->status}", $project, ['module' => 'Todo', 'type' => 'update']);

        return back()->with('success', 'Task updated.');
    }

    public function destroyTodo(Project $project, ProjectTask $task): RedirectResponse
    {
        abort_unless((int) $task->project_id === (int) $project->id, 403);

        $taskName = $task->task_name;
        $task->delete();

        AuditTrail::log("Task deleted: {$taskName}", $project, ['module' => 'Todo', 'type' => 'delete']);

        return back()->with('success', 'Task deleted.');
    }

    // ── Auto-numbering helpers ────────────────────────────────────────────────

    private function nextNtpNo(): string
    {
        $year   = now()->format('Y');
        $latest = ProjectNtp::withTrashed()
            ->where('ntp_no', 'like', "PMC-NTP-{$year}-%")
            ->orderByDesc('ntp_no')
            ->value('ntp_no');

        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'PMC-NTP-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextVoNo(): string
    {
        $latest = ProjectVariationOrder::withTrashed()
            ->where('vo_no', 'like', 'VO-%')
            ->orderByDesc('vo_no')
            ->value('vo_no');

        $next = $latest ? ((int) substr($latest, 3)) + 1 : 1;

        return 'VO-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function nextStmtNo(): string
    {
        $year  = now()->format('Y');
        $latest = ProjectBilling::withTrashed()
            ->where('stmt_no', 'like', "BLG-{$year}-%")
            ->orderByDesc('stmt_no')
            ->value('stmt_no');

        $prefix = "BLG-{$year}-";
        $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return 'BLG-' . $year . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
