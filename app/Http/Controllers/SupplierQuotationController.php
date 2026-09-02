<?php

namespace App\Http\Controllers;

use App\Mail\QuotationSubmitted;
use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectRfq;
use App\Models\ProjectRfqQuotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The supplier's own corner of the system.
 *
 * Suppliers have no accounts. The RFQ email carries a link holding the RFQ's
 * `portal_token`, and holding that token is the whole of the authorisation —
 * so every action here is scoped to the one RFQ it resolves to, and a supplier
 * only ever sees the quotations they themselves filled in, never the project
 * team's internal ones.
 */
class SupplierQuotationController extends Controller
{
    /** Blank line-item rows the form offers, matching the internal form. */
    private const FORM_ROWS = 10;

    public function show(string $token): Response
    {
        $rfq     = $this->resolveRfq($token);
        $project = $rfq->project;

        return Inertia::render('supplier-quote/index', [
            'rfq' => [
                'contractor' => $rfq->contractor_name,
                'sent'       => optional($rfq->sent_date)->format('F d, Y') ?? '—',
                'due'        => optional($rfq->due_date)->format('F d, Y'),
                'due_raw'    => optional($rfq->due_date)->format('Y-m-d'),
                // Set by the project team; the supplier quotes against it but
                // does not get to rewrite it.
                'scope_of_work' => $rfq->scope_of_work,
                'token'         => $rfq->portal_token,
            ],
            'project' => [
                'project_no' => $project->project_no,
                'title'      => $project->title,
                'site'       => $project->site,
                'owner'      => $project->dept_owner ?: $project->project_manager_name,
            ],
            // Only the supplier's own offers — staff-entered quotations stay
            // internal to the project team.
            'quotations' => $rfq->quotations()
                ->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)
                ->with('items')
                ->get()
                ->map(fn (ProjectRfqQuotation $q) => $this->quotationPayload($q))
                ->values(),
            'form_rows' => self::FORM_ROWS,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $rfq = $this->resolveRfq($token);

        $send = $request->boolean('send');
        $data = $this->validateQuotation($request, $send);

        $quotation = $rfq->quotations()->create([
            'seq'    => (int) $rfq->quotations()->max('seq') + 1,
            'origin' => ProjectRfqQuotation::ORIGIN_SUPPLIER,
            'status' => ProjectRfqQuotation::STATUS_DRAFT,
        ]);

        return $this->saveQuotation($request, $rfq, $quotation, $data, $send);
    }

    public function update(Request $request, string $token, ProjectRfqQuotation $quotation): RedirectResponse
    {
        $rfq = $this->resolveRfq($token);

        abort_unless((int) $quotation->project_rfq_id === (int) $rfq->id, 404);
        abort_unless(
            $quotation->isEditableBySupplier(),
            403,
            'This quotation has been received by the project team and can no longer be changed.',
        );

        $send = $request->boolean('send');

        return $this->saveQuotation($request, $rfq, $quotation, $this->validateQuotation($request, $send), $send);
    }

    /**
     * Write the form onto a quotation, then either leave it as a draft or hand
     * it to the project team.
     */
    private function saveQuotation(
        Request $request,
        ProjectRfq $rfq,
        ProjectRfqQuotation $quotation,
        array $data,
        bool $send,
    ): RedirectResponse {
        $quotation->fill([
            'label'            => ($data['label'] ?? '') ?: null,
            // The supplier's own description of what they are quoting for. The
            // form opens it pre-filled with the RFQ's scope, but it is theirs to
            // amend — their offer may cover more or less than what was asked.
            'scope_of_work'    => $data['scope_of_work'] ?? null,
            'due_date'         => $data['due_date'] ?? null,
            'duration_days'    => $data['duration_days'] ?? null,
            'terms_conditions' => $data['terms_conditions'] ?? null,
            'inclusions'       => $data['inclusions'] ?? null,
            'exclusions'       => $data['exclusions'] ?? null,
        ]);

        if ($file = $request->file('quotation_file')) {
            if ($quotation->quotation_file) {
                Storage::disk('public')->delete($quotation->quotation_file);
            }
            $quotation->quotation_file = $file->store('rfq-files', 'public');
        }

        if ($send) {
            $quotation->status       = ProjectRfqQuotation::STATUS_SUBMITTED;
            $quotation->submitted_at = now();
        }

        $quotation->save();

        $this->replaceItems($quotation, $rfq, $data['items'] ?? []);

        if ($send) {
            $this->announceSubmission($rfq, $quotation);
        }

        return redirect()
            ->route('supplier-quote.show', $rfq->portal_token)
            ->with('success', $send
                ? "{$quotation->displayName()} has been sent to the project team."
                : "{$quotation->displayName()} saved. You can come back to this link to finish it.");
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(ProjectRfqQuotation $quotation, ProjectRfq $rfq, array $items): void
    {
        $quotation->items()->delete();

        $seq = 0;
        foreach (array_values($items) as $item) {
            if (empty($item['description'])) {
                continue;
            }

            $quotation->items()->create([
                'project_rfq_id' => $rfq->id,
                'seq'            => ++$seq,
                'description'    => $item['description'],
                'qty'            => ($item['qty']        ?? '') !== '' ? (float) $item['qty']        : null,
                'unit'           => ($item['unit']       ?? '') ?: null,
                'unit_cost'      => ($item['unit_cost']  ?? '') !== '' ? (float) $item['unit_cost']  : null,
                'total_cost'     => ($item['total_cost'] ?? '') !== '' ? (float) $item['total_cost'] : null,
            ]);
        }
    }

    /**
     * Tell the project team a quotation has landed — in-app and by email.
     *
     * The creator and the project manager are usually the same engineer, so
     * {@see Project::team()} de-duplicates them and they hear about it once.
     */
    private function announceSubmission(ProjectRfq $rfq, ProjectRfqQuotation $quotation): void
    {
        $project = $rfq->project;
        $team    = $project->team();

        $total   = $quotation->fresh('items')->grandTotal();
        $message = "{$rfq->contractor_name} submitted {$quotation->displayName()} for project {$project->project_no}.";
        $link    = route('projects.hub.rfq', $project->id, absolute: false);

        Notification::notify($team, $message, $link);

        foreach ($team as $member) {
            if (! filled($member->email)) {
                continue;
            }

            try {
                Mail::to($member->email)->send(new QuotationSubmitted($rfq, $project, $quotation, $total));
            } catch (\Throwable $e) {
                // A mail outage must not lose the supplier's submission — it is
                // already saved, and the in-app notification still stands.
                Log::error("Quotation submission email failed for quotation #{$quotation->id}: " . $e->getMessage());
            }
        }

        AuditTrail::log(
            "{$rfq->contractor_name} submitted {$quotation->displayName()} through the supplier portal",
            $project,
            ['module' => 'RFQ', 'type' => 'create', 'rfq_id' => $rfq->id],
        );
    }

    /**
     * A draft may be as incomplete as the supplier likes; sending it demands
     * the whole form, matching what the project team requires internally.
     */
    private function validateQuotation(Request $request, bool $send): array
    {
        $required = fn (string ...$rules) => $send ? ['required', ...$rules] : ['nullable', ...$rules];

        $rules = [
            'label'               => ['nullable', 'string', 'max:255'],
            'scope_of_work'       => $required('string'),
            'due_date'            => $required('date'),
            'duration_days'       => $required('integer', 'min:1'),
            'terms_conditions'    => $required('string'),
            'inclusions'          => $required('string'),
            'exclusions'          => $required('string'),
            'items'               => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.qty'         => ['nullable', 'numeric', 'min:0'],
            'items.*.unit'        => ['nullable', 'string', 'max:50'],
            'items.*.unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'items.*.total_cost'  => ['nullable', 'numeric'],
            'quotation_file'      => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ];

        $data = $request->validate($rules);

        if ($send) {
            $costed = collect($data['items'] ?? [])
                ->filter(fn ($item) => filled($item['description'] ?? null))
                ->filter(fn ($item) => (float) ($item['total_cost'] ?? 0) > 0);

            if ($costed->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one line item with a total cost before sending.',
                ]);
            }
        }

        return $data;
    }

    private function resolveRfq(string $token): ProjectRfq
    {
        $rfq = ProjectRfq::where('portal_token', $token)->first();

        abort_if($rfq === null || $rfq->project === null, 404);

        return $rfq;
    }

    /** @return array<string, mixed> */
    private function quotationPayload(ProjectRfqQuotation $quotation): array
    {
        return [
            'id'             => $quotation->id,
            'name'           => $quotation->displayName(),
            'label'          => $quotation->label,
            'scope_of_work'  => $quotation->scope_of_work,
            'status'         => $quotation->status,
            'editable'       => $quotation->isEditableBySupplier(),
            'due_raw'        => optional($quotation->due_date)->format('Y-m-d'),
            'duration_days'  => $quotation->duration_days,
            'terms'          => $quotation->terms_conditions,
            'inclusions'     => $quotation->inclusions,
            'exclusions'     => $quotation->exclusions,
            'quotation_file' => $quotation->quotation_file ? Storage::disk('public')->url($quotation->quotation_file) : null,
            'grand_total'    => $quotation->grandTotal(),
            'submitted_at'   => optional($quotation->submitted_at)->format('M d, Y h:i A'),
            'received_at'    => optional($quotation->received_at)->format('M d, Y h:i A'),
            'items'          => $quotation->items->map(fn ($item) => [
                'seq'         => $item->seq,
                'description' => $item->description,
                'qty'         => $item->qty,
                'unit'        => $item->unit,
                'unit_cost'   => $item->unit_cost,
                'total_cost'  => $item->total_cost,
            ])->values(),
        ];
    }
}
