<?php

namespace App\Http\Controllers;

use App\Mail\NtpIssuedToVendor;
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
use App\Models\ProjectRfqQuotation;
use App\Models\ProjectVariationOrder;
use App\Models\ProjectTask;
use App\Models\ProjectWeeklyReport;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\PsrTemplateWriter;
use App\Support\WeeklyReportSheet;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        // Every RFQ starts with one (empty) quotation so the vendor's first
        // offer has somewhere to land; further ones are added from the hub.
        $rfq->quotations()->create([
            'seq'         => 1,
            'label'       => 'Original quotation',
            'due_date'    => $data['due_date'] ?? null,
            'is_final'    => true,
            'origin'      => ProjectRfqQuotation::ORIGIN_STAFF,
            'status'      => ProjectRfqQuotation::STATUS_RECEIVED,
            'received_at' => now(),
            'received_by' => auth()->id(),
            'created_by'  => auth()->id(),
        ]);

        $this->mailRfq($rfq, $project, $data);

        AuditTrail::log("Dispatched RFQ to {$rfq->contractor_name}", $project, ['module' => 'RFQ', 'type' => 'create', 'rfq_id' => $rfq->id]);

        return back()->with('success', 'RFQ dispatched successfully.');
    }

    /**
     * Send the RFQ email again — same recipients dialog as the first dispatch,
     * so the address can be corrected or more people copied in.
     */
    public function resendRfq(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        $data = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'additional_recipients'   => ['nullable', 'array'],
            'additional_recipients.*' => ['email', 'max:255'],
            'cc_self'         => ['nullable', 'boolean'],
        ]);

        $oldEmail = (string) $rfq->recipient_email;
        $rfq->update(['recipient_email' => $data['recipient_email']]);

        $sent = $this->mailRfq($rfq, $project, $data);

        // The address change is persisted either way, so it is audited either
        // way — a silent edit with no trail is worse than a failed send.
        $fields = $oldEmail !== $data['recipient_email']
            ? [['field' => 'Recipient Email', 'old' => $oldEmail, 'new' => $data['recipient_email']]]
            : [];

        AuditTrail::log(
            $sent
                ? "Re-sent RFQ email to {$data['recipient_email']} ({$rfq->contractor_name})"
                : "RFQ re-send to {$data['recipient_email']} failed ({$rfq->contractor_name})",
            $project,
            ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => $fields],
        );

        if (! $sent) {
            return back()->with('error', 'The RFQ email could not be sent. Please check the mail settings and try again.');
        }

        return back()->with('success', "RFQ re-sent to {$data['recipient_email']}.");
    }

    /**
     * Deliver the RFQ mail to the recipient plus any CCs the sender chose.
     *
     * @param  array{recipient_email?: string|null, additional_recipients?: array<int, string>, cc_self?: bool}  $data
     * @return bool Whether the mail went out (false when it failed or no recipient was given).
     */
    private function mailRfq(ProjectRfq $rfq, Project $project, array $data): bool
    {
        if (empty($data['recipient_email'])) {
            return false;
        }

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

            return false;
        }

        return true;
    }

    // ── RFQ quotations ───────────────────────────────────────────────────────

    /**
     * Add another quotation to an RFQ — a revision, or a competing offer from
     * the same vendor. Optionally seeded from an existing one so a small change
     * doesn't mean retyping the whole form.
     */
    public function storeRfqQuotation(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        $this->guardRfqEditable($rfq, $project);

        $data = $request->validate([
            'label'     => ['nullable', 'string', 'max:255'],
            'copy_from' => ['nullable', Rule::exists('project_rfq_quotations', 'id')->where('project_rfq_id', $rfq->id)],
        ]);

        $seq    = (int) $rfq->quotations()->max('seq') + 1;
        $source = !empty($data['copy_from']) ? $rfq->quotations()->with('items')->find($data['copy_from']) : null;

        $quotation = $rfq->quotations()->create([
            'seq'         => $seq,
            'label'       => ($data['label'] ?? '') ?: null,
            'is_final'    => false,
            'origin'      => ProjectRfqQuotation::ORIGIN_STAFF,
            'status'      => ProjectRfqQuotation::STATUS_RECEIVED,
            'received_at' => now(),
            'received_by' => auth()->id(),
            'created_by'  => auth()->id(),
            // A copy carries the quotation body over; the file stays with the
            // original, since it is the document that offer was quoted on.
            ...collect(ProjectRfq::MIRRORED)
                ->reject(fn ($field) => $field === 'quotation_file')
                ->mapWithKeys(fn ($field) => [$field => $source?->{$field}])
                ->all(),
        ]);

        foreach ($source?->items ?? [] as $item) {
            $quotation->items()->create([
                'project_rfq_id' => $rfq->id,
                'seq'            => $item->seq,
                'description'    => $item->description,
                'qty'            => $item->qty,
                'unit'           => $item->unit,
                'unit_cost'      => $item->unit_cost,
                'total_cost'     => $item->total_cost,
            ]);
        }

        AuditTrail::log(
            "Added {$quotation->displayName()} for {$rfq->contractor_name}",
            $project,
            ['module' => 'RFQ', 'type' => 'create', 'rfq_id' => $rfq->id],
        );

        return back()->with('success', "{$quotation->displayName()} added.");
    }

    /** Kept for callers that address the RFQ rather than a specific quotation. */
    public function updateRfq(Request $request, Project $project, ProjectRfq $rfq): RedirectResponse
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);

        $this->guardRfqEditable($rfq, $project);

        $quotation = $rfq->finalQuotation()->first()
            ?? $rfq->quotations()->create([
                'seq'         => 1,
                'is_final'    => true,
            'origin'      => ProjectRfqQuotation::ORIGIN_STAFF,
            'status'      => ProjectRfqQuotation::STATUS_RECEIVED,
            'received_at' => now(),
            'received_by' => auth()->id(),
                'created_by'  => auth()->id(),
            ]);

        return $this->saveQuotation($request, $project, $rfq, $quotation);
    }

    public function updateRfqQuotation(Request $request, Project $project, ProjectRfq $rfq, ProjectRfqQuotation $quotation): RedirectResponse
    {
        $this->guardQuotation($rfq, $project, $quotation);
        $this->guardRfqEditable($rfq, $project);

        return $this->saveQuotation($request, $project, $rfq, $quotation);
    }

    /**
     * Choose which quotation the project runs with. The RFQ row mirrors it, so
     * the hub table, the printed form and any NTP raised from here all follow.
     */
    public function setFinalRfqQuotation(Project $project, ProjectRfq $rfq, ProjectRfqQuotation $quotation): RedirectResponse
    {
        $this->guardQuotation($rfq, $project, $quotation);
        $this->guardRfqEditable($rfq, $project);

        if (! $quotation->isSelectable()) {
            return back()->with('error', "{$quotation->displayName()} is still a draft the supplier has not sent — it cannot be made final.");
        }

        $previous = $rfq->finalQuotation()->first();
        if ($previous && (int) $previous->id === (int) $quotation->id) {
            return back();
        }

        DB::transaction(function () use ($rfq, $quotation) {
            $rfq->quotations()->update(['is_final' => false]);
            $quotation->update(['is_final' => true]);
            $rfq->refresh()->syncFromFinalQuotation();
        });

        AuditTrail::log(
            "Set {$quotation->displayName()} as the final quotation for {$rfq->contractor_name}",
            $project,
            ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => [[
                'field' => 'Final Quotation',
                'old'   => $previous?->displayName() ?? '',
                'new'   => $quotation->displayName(),
            ]]],
        );

        return back()->with('success', "{$quotation->displayName()} is now the final quotation.");
    }

    /**
     * Acknowledge a supplier's quotation. This is the point the offer is fixed:
     * the supplier can still see it in their portal, but no longer change it.
     */
    public function receiveRfqQuotation(Project $project, ProjectRfq $rfq, ProjectRfqQuotation $quotation): RedirectResponse
    {
        $this->guardQuotation($rfq, $project, $quotation);

        if ($quotation->status === ProjectRfqQuotation::STATUS_RECEIVED) {
            return back();
        }

        if ($quotation->status !== ProjectRfqQuotation::STATUS_SUBMITTED) {
            return back()->with('error', "{$quotation->displayName()} has not been sent by the supplier yet.");
        }

        $quotation->update([
            'status'      => ProjectRfqQuotation::STATUS_RECEIVED,
            'received_at' => now(),
            'received_by' => auth()->id(),
        ]);

        AuditTrail::log(
            "Marked {$quotation->displayName()} from {$rfq->contractor_name} as received",
            $project,
            ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => [[
                'field' => 'Quotation Status', 'old' => 'submitted', 'new' => 'received',
            ]]],
        );

        return back()->with('success', "{$quotation->displayName()} marked as received. The supplier can no longer edit it.");
    }

    public function destroyRfqQuotation(Project $project, ProjectRfq $rfq, ProjectRfqQuotation $quotation): RedirectResponse
    {
        $this->guardQuotation($rfq, $project, $quotation);
        $this->guardRfqEditable($rfq, $project);

        if ($rfq->quotations()->count() <= 1) {
            return back()->with('error', 'An RFQ must keep at least one quotation.');
        }

        $name    = $quotation->displayName();
        $wasFinal = $quotation->is_final;

        DB::transaction(function () use ($rfq, $quotation, $wasFinal) {
            if ($quotation->quotation_file) {
                Storage::disk('public')->delete($quotation->quotation_file);
            }
            // Items carry no foreign key onto the quotation (see the migration),
            // so they are removed here rather than by a cascade.
            $quotation->items()->delete();
            $quotation->delete();

            // Fall back to the latest offer the team actually holds. An unsent
            // supplier draft is not one, and promoting it would blank the RFQ's
            // mirrored scope and terms — better to leave no final at all until
            // someone picks a real one.
            if ($wasFinal) {
                // reorder(), because the relation already sorts by seq ascending
                // and SQL Server rejects the column appearing twice.
                $rfq->quotations()
                    ->whereIn('status', [ProjectRfqQuotation::STATUS_SUBMITTED, ProjectRfqQuotation::STATUS_RECEIVED])
                    ->reorder('seq', 'desc')
                    ->first()
                    ?->update(['is_final' => true]);
            }
            $rfq->refresh()->syncFromFinalQuotation();
        });

        AuditTrail::log(
            "Deleted {$name} for {$rfq->contractor_name}",
            $project,
            ['module' => 'RFQ', 'type' => 'delete', 'rfq_id' => $rfq->id],
        );

        return back()->with('success', "{$name} deleted.");
    }

    /** Write the quotation form onto one quotation, with a field-level audit diff. */
    private function saveQuotation(Request $request, Project $project, ProjectRfq $rfq, ProjectRfqQuotation $quotation): RedirectResponse
    {
        // All quotation fields are required except the file attachment.
        $data = $request->validate([
            'label'               => ['nullable', 'string', 'max:255'],
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
            'label'            => 'Quotation Label',
            'scope_of_work'    => 'Scope of Work',
            'due_date'         => 'Due Date',
            'duration_days'    => 'Duration (days)',
            'terms_conditions' => 'Terms & Conditions',
            'inclusions'       => 'Inclusions',
            'exclusions'       => 'Exclusions',
        ];
        $newValues = collect($labels)
            ->keys()
            ->mapWithKeys(fn ($field) => [$field => ($data[$field] ?? '') ?: null])
            ->all();

        $stringify = function ($value) {
            if ($value === null) return '';
            if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
            return (string) $value;
        };
        $changedFields = [];
        foreach ($newValues as $field => $newValue) {
            $oldStr = $stringify($quotation->getOriginal($field));
            $newStr = $stringify($newValue);
            if ($oldStr !== $newStr) {
                $changedFields[] = ['field' => $labels[$field], 'old' => $oldStr, 'new' => $newStr];
            }
        }

        // Snapshot the file and line items *before* they are changed below, so we
        // can record their old → new values in the audit trail too.
        $oldFile  = $quotation->quotation_file;
        $oldItems = $quotation->items()->get(['description', 'qty', 'unit', 'unit_cost', 'total_cost'])
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

        $quotation->update($newValues);

        if (!empty($data['quotation_file'])) {
            if ($oldFile) {
                Storage::disk('public')->delete($oldFile);
            }
            $path = $data['quotation_file']->store('rfq-files', 'public');
            $quotation->update(['quotation_file' => $path]);

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

            $quotation->items()->delete();
            foreach ($newItems as $i => $item) {
                $quotation->items()->create(array_merge(
                    ['project_rfq_id' => $rfq->id, 'seq' => $i + 1],
                    $item,
                ));
            }

            $oldItemsStr = $fmtItems($oldItems);
            $newItemsStr = $fmtItems($newItems);
            if ($oldItemsStr !== $newItemsStr) {
                $changedFields[] = ['field' => 'Line Items', 'old' => $oldItemsStr, 'new' => $newItemsStr];
            }
        }

        // The RFQ row mirrors the final quotation, so re-sync when that is the
        // one just edited.
        if ($quotation->is_final) {
            $rfq->syncFromFinalQuotation();
        }

        AuditTrail::log(
            "Updated {$quotation->displayName()} for {$rfq->contractor_name}",
            $project,
            ['module' => 'RFQ', 'type' => 'update', 'rfq_id' => $rfq->id, 'fields' => $changedFields],
        );

        return back()->with('success', 'Quotation details saved.');
    }

    private function guardQuotation(ProjectRfq $rfq, Project $project, ProjectRfqQuotation $quotation): void
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);
        abort_unless((int) $quotation->project_rfq_id === (int) $rfq->id, 403);
    }

    /** Once an NTP is riding on an RFQ its quotations are frozen. */
    private function guardRfqEditable(ProjectRfq $rfq, Project $project): void
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 403);
        abort_if(
            $project->ntps()->where('project_rfq_id', $rfq->id)->where('status', '!=', 'rejected')->exists(),
            403,
            'This RFQ already has an NTP — its quotations can no longer be changed.',
        );
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

        // Submitted for review — NOT issued yet. The RFQ is not awarded and the
        // budget is not recalculated until the whole chain has signed: the
        // department user first, then PMD and the Division Manager.
        $ntp = $project->ntps()->create([
            ...$data,
            'ntp_no'      => $ntpNo,
            'status'      => 'pending_review',
            'created_by'  => auth()->id(),
        ]);

        $ntp->startApprovalChain();

        AuditTrail::log("NTP {$ntpNo} submitted for department review ({$data['contractor_name']})", $project, array_filter(['module' => 'NTP', 'type' => 'create', 'rfq_id' => $data['project_rfq_id'] ?? null]));

        // Notify the project's department side that an NTP awaits review: its
        // requester, or the owning department when there is no request.
        Notification::notify(
            $project->departmentAudience(),
            "NTP {$ntpNo} for {$data['contractor_name']} is awaiting your review on project {$project->project_no}.",
            route('ntp-reviews.index', absolute: false)
        );

        return back()->with('success', "NTP {$ntpNo} submitted for department review.");
    }

    /**
     * Send the issued NTP to the contractor.
     *
     * Only once the chain is complete: until the Division Manager has signed
     * there is no notice to give, and telling a vendor to proceed on an
     * unapproved NTP is the one mistake this must not allow.
     */
    public function sendNtp(Request $request, Project $project, ProjectNtp $ntp): RedirectResponse
    {
        abort_unless((int) $ntp->project_id === (int) $project->id, 403);

        if ($ntp->status !== 'issued') {
            return back()->with('error', "NTP {$ntp->ntp_no} has not completed its approval chain yet.");
        }

        $data = $request->validate([
            'recipient_email'         => ['required', 'email', 'max:255'],
            'additional_recipients'   => ['nullable', 'array'],
            'additional_recipients.*' => ['email', 'max:255'],
            'cc_self'                 => ['nullable', 'boolean'],
        ]);

        $cc = $data['additional_recipients'] ?? [];
        if ($data['cc_self'] ?? false) {
            $cc[] = auth()->user()->email;
        }

        try {
            Mail::to($data['recipient_email'])
                ->when(!empty($cc), fn ($mail) => $mail->cc($cc))
                ->send(new NtpIssuedToVendor($ntp, $project));
        } catch (\Throwable $e) {
            \Log::error("NTP email failed for NTP #{$ntp->id}: " . $e->getMessage());

            AuditTrail::log(
                "NTP {$ntp->ntp_no} send to {$data['recipient_email']} failed",
                $project,
                array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id]),
            );

            return back()->with('error', 'The NTP email could not be sent. Please check the mail settings and try again.');
        }

        $ntp->update(['vendor_notified_at' => now()]);

        AuditTrail::log(
            "NTP {$ntp->ntp_no} sent to {$ntp->contractor_name} at {$data['recipient_email']}",
            $project,
            array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id]),
        );

        return back()->with('success', "NTP {$ntp->ntp_no} sent to {$data['recipient_email']}.");
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
            $permitFile = $permit->files()->create([
                'filename'  => $file->getClientOriginalName(),
                'path'      => $path,
                'mime_type' => $file->getMimeType(),
            ]);

            $permitFile->recordFileVersion(
                $path,
                $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                size: $file->getSize(),
            );
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


        $vof = $project->variationOrders()->create([
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

        if ($attachmentPath) {
            $upload = $request->file('attachment');
            $vof->recordFileVersion(
                $attachmentPath,
                $upload->getClientOriginalName(),
                mimeType: $upload->getMimeType(),
                size: $upload->getSize(),
            );
        }

        // A variation only moves the project cost once approved, but recompute
        // anyway so the figure is never stale relative to the list.
        $project->refreshBudgetTotal();

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
            // The superseded file stays on disk — it is v(n-1) of this VOF's
            // attachment and has to remain openable from the history.
            $version = $vof->storeVersionedFile($request->file('attachment'), 'vof-files');
            $vof->update(['attachment' => $version->filepath]);
        }

        $project->refreshBudgetTotal();

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

        $project->refreshBudgetTotal();

        AuditTrail::log("Variation Order {$vof->vo_no} status changed to {$data['status']}", $project, ['module' => 'VOF', 'type' => 'update']);

        return back()->with('success', 'Variation order status updated.');
    }

    public function destroyVof(Project $project, ProjectVariationOrder $vof): RedirectResponse
    {
        abort_unless((int) $vof->project_id === (int) $project->id, 403);

        AuditTrail::log("Variation Order {$vof->vo_no} deleted: {$vof->title}", $project, ['module' => 'VOF', 'type' => 'delete']);
        $vof->delete();

        $project->refreshBudgetTotal();

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

        $doc = $project->qualityDocs()->create([
            'label'      => $data['label'],
            'doc_type'   => $data['doc_type'],
            'file_path'  => $path,
            'filename'   => $file->getClientOriginalName(),
            'created_by' => auth()->id(),
        ]);

        $doc->recordFileVersion(
            $path,
            $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
        );

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

        $doc = $project->mtrDocs()->create([
            'label'         => $data['label'],
            'material_type' => $data['material_type'],
            'test_date'     => now()->toDateString(),
            'file_path'     => $path,
            'filename'      => $file->getClientOriginalName(),
            'created_by'    => auth()->id(),
        ]);

        $doc->recordFileVersion(
            $path,
            $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
        );

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
            'apply_retention' => ['nullable', 'boolean'],
            'file'           => ['nullable', 'file', 'max:20480'],
        ]);

        $stmtNo = $this->nextStmtNo();

        // Snapshot the rate onto the billing: changing the setting later must
        // not restate what has already been billed.
        $retentionPct = $request->boolean('apply_retention') ? $this->retentionPct() : null;
        $retention    = ProjectBilling::splitRetention((float) $data['amount'], $retentionPct);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/rfp/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $billing = $project->billings()->create([
            'project_ntp_id' => ($data['project_ntp_id'] ?? '') ?: null,
            'billing_type'   => $data['billing_type'],
            'period_from'    => ($data['period_from']  ?? '') ?: null,
            'period_to'      => ($data['period_to']    ?? '') ?: null,
            'amount'         => $data['amount'],
            'retention_pct'    => $retentionPct,
            'retention_amount' => $retention,
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

        if ($filePath) {
            $upload = $request->file('file');
            $billing->recordFileVersion(
                $filePath,
                $upload->getClientOriginalName(),
                mimeType: $upload->getMimeType(),
                size: $upload->getSize(),
            );
        }

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
            'apply_retention' => ['nullable', 'boolean'],
            'file'         => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        // Keep the rate this billing was created with when retention stays on;
        // only a billing that had none picks up the current setting.
        $retentionPct = $request->boolean('apply_retention')
            ? ($billing->retention_pct !== null ? (float) $billing->retention_pct : $this->retentionPct())
            : null;

        // Status is changed only through the dedicated PM-gated status flow,
        // never here — Edit handles billing details and may run at any status.
        $billing->update([
            'billing_type' => $data['billing_type'],
            'period_from'  => ($data['period_from']  ?? '') ?: null,
            'period_to'    => ($data['period_to']    ?? '') ?: null,
            'amount'       => $data['amount'],
            'retention_pct'    => $retentionPct,
            'retention_amount' => ProjectBilling::splitRetention((float) $data['amount'], $retentionPct),
            'progress_pct' => ($data['progress_pct'] ?? '') !== '' ? $data['progress_pct'] : null,
            'summary'      => ($data['summary']      ?? '') ?: null,
            'remarks'      => ($data['remarks']      ?? '') ?: null,
            'attachments'  => $data['attachments']   ?? null,
            'recommendation' => ($data['recommendation'] ?? '') ?: null,
        ]);

        if ($request->hasFile('file')) {
            // The statement this replaces stays on disk as the previous
            // version — a billing's paper trail must not lose what was filed.
            $version = $billing->storeVersionedFile($request->file('file'), "hub/rfp/{$project->id}");

            $billing->update([
                'file_path' => $version->filepath,
                'filename'  => $version->filename,
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

    /** The retention rate currently configured in system settings. */
    private function retentionPct(): float
    {
        return (float) Setting::get('retention_pct', SettingController::DEFAULT_RETENTION_PCT);
    }

    private function recalculateBudgetPaid(Project $project): void
    {
        $project->update(['budget_paid' => $this->approvedBillingTotal($project)]);

        // Billing a sub-project moves every ancestor's total too: each one's
        // hub lists the billings of its whole subtree, so they belong in its
        // numerator. Walking all the way up rather than one level keeps a
        // sub-sub-project's billing from stopping at its immediate parent.
        for ($ancestor = $project->parent, $i = 0; $ancestor !== null && $i < 10; $ancestor = $ancestor->parent, $i++) {
            $ancestor->update(['budget_paid' => $this->approvedBillingTotal($ancestor)]);
        }
    }

    /**
     * Approved billings for a project, rolled up with its sub-projects'.
     * "Approved" is treated as paid — an approved statement is cleared for
     * payment. The subtree is walked in full, so a sub-sub-project's billings
     * reach every project above it.
     */
    private function approvedBillingTotal(Project $project): float
    {
        $ids = $project->subtreeIds();

        // Retention is withheld until the project completes, so an approved
        // billing only counts for what it actually releases.
        return (float) ProjectBilling::whereIn('project_id', $ids)
            ->where('status', 'approved')
            ->sum(DB::raw('amount - retention_amount'));
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

        $ioc = $project->iocItems()->create([
            'description' => $data['description'],
            'cost_code'   => ($data['cost_code'] ?? '') ?: null,
            'amount'      => $data['amount'],
            'file_path'   => $filePath,
            'filename'    => $filename,
            'created_by'  => auth()->id(),
        ]);

        if ($filePath) {
            $upload = $request->file('file');
            $ioc->recordFileVersion(
                $filePath,
                $upload->getClientOriginalName(),
                mimeType: $upload->getMimeType(),
                size: $upload->getSize(),
            );
        }

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

        if ($filePath) {
            $upload = $request->file('file');
            $report->recordFileVersion(
                $filePath,
                $upload->getClientOriginalName(),
                mimeType: $upload->getMimeType(),
                size: $upload->getSize(),
            );
        }

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
