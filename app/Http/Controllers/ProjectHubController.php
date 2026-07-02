<?php

namespace App\Http\Controllers;

use App\Mail\RfqDispatched;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\Mail;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectIocItem;
use App\Models\ProjectMtrDoc;
use App\Models\ProjectNtp;
use App\Models\ProjectPermit;
use App\Models\ProjectQualityDoc;
use App\Models\ProjectRfq;
use App\Models\ProjectVariationOrder;
use App\Models\ProjectTask;
use App\Models\ProjectWeeklyReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        AuditTrail::log("Dispatched RFQ to {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'create']);

        return back()->with('success', 'RFQ dispatched successfully.');
    }

    public function updateRfq(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'scope_of_work'       => ['nullable', 'string'],
            'due_date'            => ['nullable', 'date'],
            'duration_days'       => ['nullable', 'integer', 'min:1'],
            'terms_conditions'    => ['nullable', 'string'],
            'inclusions'          => ['nullable', 'string'],
            'exclusions'          => ['nullable', 'string'],
            'items'               => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.qty'         => ['nullable', 'numeric', 'min:0'],
            'items.*.unit'        => ['nullable', 'string', 'max:50'],
            'items.*.unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'items.*.total_cost'  => ['nullable', 'numeric'],
            'quotation_file'      => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $rfq->update([
            'scope_of_work'    => ($data['scope_of_work']    ?? '') ?: null,
            'due_date'         => ($data['due_date']         ?? '') ?: null,
            'duration_days'    => ($data['duration_days']    ?? '') ?: null,
            'terms_conditions' => ($data['terms_conditions'] ?? '') ?: null,
            'inclusions'       => ($data['inclusions']       ?? '') ?: null,
            'exclusions'       => ($data['exclusions']       ?? '') ?: null,
        ]);

        if (!empty($data['quotation_file'])) {
            $oldPath = $rfq->fresh()->quotation_file;
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            $path = $data['quotation_file']->store('rfq-files', 'public');
            $rfq->update(['quotation_file' => $path]);
        }

        if (!empty($data['items'])) {
            $rfq->items()->delete();
            foreach (array_values($data['items']) as $i => $item) {
                if (empty($item['description'])) continue;
                $rfq->items()->create([
                    'seq'         => $i + 1,
                    'description' => $item['description'],
                    'qty'         => ($item['qty']        ?? '') !== '' ? (float) $item['qty']        : null,
                    'unit'        => ($item['unit']       ?? '') ?: null,
                    'unit_cost'   => ($item['unit_cost']  ?? '') !== '' ? (float) $item['unit_cost']  : null,
                    'total_cost'  => ($item['total_cost'] ?? '') !== '' ? (float) $item['total_cost'] : null,
                ]);
            }
        }

        AuditTrail::log("Updated RFQ details for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'update']);

        return back()->with('success', 'Quotation details saved.');
    }

    public function updateRfqStatus(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,submitted,awarded,expired'],
        ]);

        $rfq->update($data);

        AuditTrail::log("RFQ status updated to {$data['status']} for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'update']);

        return back()->with('success', 'RFQ status updated.');
    }

    public function destroyRfq(Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        AuditTrail::log("Deleted RFQ for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'delete']);
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
                    if ($value && $project->ntps()->where('project_rfq_id', $value)->exists()) {
                        $fail('An NTP has already been issued for this RFQ.');
                    }
                },
            ],
            'baseline_start'  => ['required', 'date'],
            'baseline_end'    => ['required', 'date', 'after:baseline_start'],
            'approved_cost'   => ['required', 'numeric', 'min:0'],
        ]);

        $ntpNo = $this->nextNtpNo();

        $project->ntps()->create([
            ...$data,
            'ntp_no'      => $ntpNo,
            'issued_date' => now()->toDateString(),
            'issued_by'   => auth()->id(),
            'created_by'  => auth()->id(),
        ]);

        // Mark RFQ as awarded if linked
        if (!empty($data['project_rfq_id'])) {
            ProjectRfq::where('id', $data['project_rfq_id'])->update(['status' => 'awarded']);
        }

        AuditTrail::log("NTP {$ntpNo} issued to {$data['contractor_name']}", $project, ['module' => 'NTP', 'type' => 'create']);

        $this->recalculateBudgetTotal($project);

        return back()->with('success', "NTP {$ntpNo} issued successfully.");
    }

    public function destroyNtp(Project $project, ProjectNtp $ntp): RedirectResponse
    {
        abort_unless((int) $ntp->project_id === (int) $project->id, 403);

        AuditTrail::log("NTP {$ntp->ntp_no} deleted", $project, ['module' => 'NTP', 'type' => 'delete']);
        $ntp->delete();

        $this->recalculateBudgetTotal($project);

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
            'status'       => ['required', 'in:pending,approved,paid'],
            'file'         => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

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
            'status'       => $data['status'],
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

        AuditTrail::log("Billing {$billing->stmt_no} updated — status: {$data['status']}", $project, ['module' => 'RFP', 'type' => 'update']);

        $this->recalculateBudgetPaid($project);

        return back()->with('success', 'Billing updated.');
    }

    public function updateBillingStatus(Request $request, Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless((int) $billing->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $billing->update($data);

        AuditTrail::log("Billing {$billing->stmt_no} status changed to {$data['status']}", $project, ['module' => 'RFP', 'type' => 'finance']);

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
        $project->update([
            'budget_paid' => $project->billings()->where('status', 'paid')->sum('amount'),
        ]);
    }

    /**
     * Total Project Cost is derived from the sum of approved NTP contract values
     * (the officially awarded cost) rather than a manually-entered figure.
     */
    private function recalculateBudgetTotal(Project $project): void
    {
        $project->update([
            'budget_total' => $project->ntps()->sum('approved_cost'),
        ]);
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
            'week_code'         => ['required', 'string', 'max:20'],
            'completion_pct'    => ['required', 'integer', 'min:0', 'max:100'],
            'identified_issues' => ['nullable', 'string'],
            'progress_updates'  => ['nullable', 'string'],
            'file'              => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/psr/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $report = $project->weeklyReports()->create([
            'week_code'         => $data['week_code'],
            'completion_pct'    => $data['completion_pct'],
            'identified_issues' => $data['identified_issues'] ?? null,
            'progress_updates'  => $data['progress_updates'] ?? null,
            'file_path'         => $filePath,
            'filename'          => $filename,
            'submitted_date'    => now()->toDateString(),
            'created_by'        => auth()->id(),
        ]);

        // Update project completion percent
        $project->update(['completion_percent' => $data['completion_pct']]);

        AuditTrail::log("Weekly report {$report->week_code} submitted — {$data['completion_pct']}% complete", $project, ['module' => 'PSR', 'type' => 'upload']);

        return back()->with('success', "Weekly report {$report->week_code} submitted.");
    }

    public function destroyPsr(Project $project, ProjectWeeklyReport $psr): RedirectResponse
    {
        abort_unless((int) $psr->project_id === (int) $project->id, 403);

        AuditTrail::log("Weekly report {$psr->week_code} deleted", $project, ['module' => 'PSR', 'type' => 'delete']);

        if ($psr->file_path) {
            Storage::disk('public')->delete($psr->file_path);
        }
        $psr->delete();

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
