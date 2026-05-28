<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectIocItem;
use App\Models\ProjectMtrDoc;
use App\Models\ProjectNtp;
use App\Models\ProjectPermit;
use App\Models\ProjectQualityDoc;
use App\Models\ProjectRfq;
use App\Models\ProjectVariationOrder;
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
            'contractor_name' => ['required', 'string', 'max:255'],
            'due_date'        => ['nullable', 'date'],
        ]);

        $rfq = $project->rfqs()->create([
            ...$data,
            'sent_date'  => now()->toDateString(),
            'status'     => 'pending',
            'created_by' => auth()->id(),
        ]);

        AuditTrail::log("Dispatched RFQ to {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'create']);

        return back()->with('success', 'RFQ dispatched successfully.');
    }

    public function updateRfq(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless($rfq->project_id === $project->id, 403);

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
        ]);

        $rfq->update(\Arr::except($data, ['items']));

        if (isset($data['items'])) {
            $rfq->items()->delete();
            foreach (array_values($data['items']) as $i => $item) {
                if (empty($item['description'])) continue;
                $rfq->items()->create([
                    'seq'        => $i + 1,
                    'description'=> $item['description'],
                    'qty'        => $item['qty']        ?? null,
                    'unit'       => $item['unit']        ?? null,
                    'unit_cost'  => $item['unit_cost']   ?? null,
                    'total_cost' => $item['total_cost']  ?? null,
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
        abort_unless($rfq->project_id === $project->id, 403);

        AuditTrail::log("Deleted RFQ for {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'delete']);
        $rfq->delete();

        return back()->with('success', 'RFQ deleted.');
    }

    // ── NTP ──────────────────────────────────────────────────────────────────

    public function storeNtp(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'contractor_name' => ['required', 'string', 'max:255'],
            'project_rfq_id'  => ['nullable', 'exists:project_rfqs,id'],
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

        return back()->with('success', "NTP {$ntpNo} issued successfully.");
    }

    public function destroyNtp(Project $project, ProjectNtp $ntp): RedirectResponse
    {
        abort_unless($ntp->project_id === $project->id, 403);

        AuditTrail::log("NTP {$ntp->ntp_no} deleted", $project, ['module' => 'NTP', 'type' => 'delete']);
        $ntp->delete();

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
        abort_unless($permit->project_id === $project->id, 403);

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
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount'      => ['required', 'numeric', 'min:0'],
        ]);

        $voNo = $this->nextVoNo($project);

        $project->variationOrders()->create([
            ...$data,
            'vo_no'          => $voNo,
            'status'         => 'pending',
            'submitted_date' => now()->toDateString(),
            'created_by'     => auth()->id(),
        ]);

        AuditTrail::log("Variation Order {$voNo} submitted: {$data['title']}", $project, ['module' => 'VOF', 'type' => 'create']);

        return back()->with('success', "Variation Order {$voNo} submitted.");
    }

    public function updateVof(Request $request, Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless($vof->project_id === $project->id, 403);

        $data = $request->validate([
            'status'        => ['required', 'in:pending,approved,rejected'],
            'approved_date' => ['nullable', 'date'],
        ]);

        $vof->update($data);

        AuditTrail::log("Variation Order {$vof->vo_no} status changed to {$data['status']}", $project, ['module' => 'VOF', 'type' => 'update']);

        return back()->with('success', 'Variation order updated.');
    }

    public function destroyVof(Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless($vof->project_id === $project->id, 403);

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
        abort_unless($qpp->project_id === $project->id, 403);

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
        abort_unless($mtr->project_id === $project->id, 403);

        AuditTrail::log("Material test report deleted: {$mtr->label}", $project, ['module' => 'MTR', 'type' => 'delete']);
        Storage::disk('public')->delete($mtr->file_path);
        $mtr->delete();

        return back()->with('success', 'Report deleted.');
    }

    // ── Billing (RFP) ─────────────────────────────────────────────────────────

    public function storeBilling(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'billing_type' => ['required', 'string', 'max:50'],
            'period_from'  => ['nullable', 'date'],
            'period_to'    => ['nullable', 'date'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'progress_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'summary'      => ['nullable', 'string'],
            'remarks'      => ['nullable', 'string'],
            'file'         => ['nullable', 'file', 'max:20480'],
        ]);

        $stmtNo = $this->nextStmtNo($project);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/rfp/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $project->billings()->create([
            ...$data,
            'stmt_no'    => $stmtNo,
            'status'     => 'pending',
            'file_path'  => $filePath,
            'filename'   => $filename,
            'created_by' => auth()->id(),
        ]);

        AuditTrail::log("Billing {$stmtNo} submitted ({$data['billing_type']}) — PhP {$data['amount']}", $project, ['module' => 'RFP', 'type' => 'finance']);

        return back()->with('success', "Billing statement {$stmtNo} submitted.");
    }

    public function updateBilling(Request $request, Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless($billing->project_id === $project->id, 403);

        $data = $request->validate([
            'billing_type' => ['required', 'string', 'max:50'],
            'period_from'  => ['nullable', 'date'],
            'period_to'    => ['nullable', 'date'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'progress_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'summary'      => ['nullable', 'string'],
            'remarks'      => ['nullable', 'string'],
            'status'       => ['required', 'in:pending,approved,paid'],
        ]);

        $wasPaid = $billing->status === 'paid';
        $billing->update($data);

        if (!$wasPaid && $data['status'] === 'paid') {
            $project->increment('budget_paid', $billing->amount);
        }

        AuditTrail::log("Billing {$billing->stmt_no} updated — status: {$data['status']}", $project, ['module' => 'RFP', 'type' => 'update']);

        return back()->with('success', 'Billing updated.');
    }

    public function updateBillingStatus(Request $request, Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless($billing->project_id === $project->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $billing->update($data);

        if ($data['status'] === 'paid') {
            $project->increment('budget_paid', $billing->amount);
        }

        AuditTrail::log("Billing {$billing->stmt_no} status changed to {$data['status']}", $project, ['module' => 'RFP', 'type' => 'finance']);

        return back()->with('success', 'Billing status updated.');
    }

    public function destroyBilling(Project $project, ProjectBilling $billing): RedirectResponse
    {
        abort_unless($billing->project_id === $project->id, 403);

        AuditTrail::log("Billing {$billing->stmt_no} deleted ({$billing->billing_type})", $project, ['module' => 'RFP', 'type' => 'delete']);

        if ($billing->file_path) {
            Storage::disk('public')->delete($billing->file_path);
        }
        $billing->delete();

        return back()->with('success', 'Billing record deleted.');
    }

    // ── IOC ───────────────────────────────────────────────────────────────────

    public function storeIoc(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
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
        abort_unless($ioc->project_id === $project->id, 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
        ]);

        $ioc->update($data);

        AuditTrail::log("Other cost updated: {$data['description']} — PhP {$data['amount']}", $project, ['module' => 'IOC', 'type' => 'update']);

        return back()->with('success', 'Cost entry updated.');
    }

    public function destroyIoc(Project $project, ProjectIocItem $ioc): RedirectResponse
    {
        abort_unless($ioc->project_id === $project->id, 403);

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
        abort_unless($psr->project_id === $project->id, 403);

        AuditTrail::log("Weekly report {$psr->week_code} deleted", $project, ['module' => 'PSR', 'type' => 'delete']);

        if ($psr->file_path) {
            Storage::disk('public')->delete($psr->file_path);
        }
        $psr->delete();

        return back()->with('success', 'Weekly report deleted.');
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

    private function nextVoNo(Project $project): string
    {
        $count = $project->variationOrders()->withTrashed()->count() + 1;

        return 'VO-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function nextStmtNo(Project $project): string
    {
        $year  = now()->format('Y');
        $count = $project->billings()->withTrashed()->count() + 1;

        return 'BLG-' . $year . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}
