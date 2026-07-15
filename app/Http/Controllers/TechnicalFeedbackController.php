<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\ProjectRequest;
use App\Models\TechnicalFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechnicalFeedbackController extends Controller
{
    /**
     * Store technical feedback for a project request.
     *
     * Only approvers/admins (engineers) may give technical feedback — department
     * users submit and edit the request itself, they do not review it.
     */
    public function store(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['approver', 'assistant_manager', 'admin']), 403);

        $data = $this->validated($request);

        TechnicalFeedback::create([
            'project_request_id' => $projectRequest->id,
            'user_id'            => $request->user()->id,
            'disciplines'        => $data['disciplines'] ?? [],
            'permits'            => $data['permits'] ?? [],
            'priority'           => $data['priority'] ?? null,
            'remarks'            => $data['remarks'],
        ]);

        Notification::notify(
            $projectRequest->requester_id,
            "Technical feedback was added to project request #{$projectRequest->request_no}",
            route('requests.show', $projectRequest->id, absolute: false)
        );

        return redirect()->route('requests.show', $projectRequest->id)
            ->with('success', 'Technical feedback submitted.');
    }

    /**
     * Update an existing feedback entry. Only the author may edit it.
     */
    public function update(Request $request, TechnicalFeedback $technicalFeedback): RedirectResponse
    {
        abort_unless($technicalFeedback->user_id === $request->user()->id, 403);

        $data = $this->validated($request);

        $technicalFeedback->update([
            'disciplines' => $data['disciplines'] ?? [],
            'permits'     => $data['permits'] ?? [],
            'priority'    => $data['priority'] ?? null,
            'remarks'     => $data['remarks'],
        ]);

        return redirect()->route('requests.show', $technicalFeedback->project_request_id)
            ->with('success', 'Technical feedback updated.');
    }

    /**
     * Delete a feedback entry. Only the author may remove it.
     */
    public function destroy(Request $request, TechnicalFeedback $technicalFeedback): RedirectResponse
    {
        abort_unless($technicalFeedback->user_id === $request->user()->id, 403);

        $projectRequestId = $technicalFeedback->project_request_id;
        $technicalFeedback->delete();

        return redirect()->route('requests.show', $projectRequestId)
            ->with('success', 'Technical feedback deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'disciplines'   => ['nullable', 'array'],
            'disciplines.*' => ['string', 'max:255'],
            'permits'       => ['nullable', 'array'],
            'permits.*'     => ['string', 'max:255'],
            'priority'      => ['nullable', 'string', 'in:Critical,High,Medium,Low'],
            'remarks'       => ['required', 'string', 'max:5000'],
        ]);
    }
}
