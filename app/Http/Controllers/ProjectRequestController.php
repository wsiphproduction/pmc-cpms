<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProjectRequest::with(['requester'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('job_type', 'like', "%{$search}%")
                  ->orWhere('job_location', 'like', "%{$search}%")
                  ->orWhere('costcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('job_type'))     $query->where('job_type', $request->job_type);
        if ($request->filled('job_location')) $query->where('job_location', 'like', "%{$request->job_location}%");
        if ($request->filled('costcode'))     $query->where('costcode', 'like', "%{$request->costcode}%");
        if ($request->filled('status') && is_array($request->status)) {
            $query->whereIn('status', $request->status);
        }

        return Inertia::render('requests/index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'filters'  => $request->only(['search', 'job_type', 'job_location', 'costcode', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('requests/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'job_type'        => ['required', 'string', 'max:100'],
            'description'     => ['required', 'string'],
            'job_location'    => ['required', 'string', 'max:255'],
            'costcode'        => ['nullable', 'string', 'max:100'],
            'opex'            => ['boolean'],
            'capex'           => ['boolean'],
            'for_budgeting'   => ['boolean'],

            'attachments'               => ['nullable', 'array'],
            'attachments.*.file'        => ['nullable', 'file', 'max:10240'],
            'attachments.*.type'        => ['required_with:attachments.*.file', 'string', 'in:picture,drawing,report'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $projectRequest = ProjectRequest::create([
            'title'         => $request->title,
            'job_type'      => $request->job_type,
            'description'   => $request->description,
            'requester_id'  => auth()->id(),
            'job_location'  => $request->job_location,
            'costcode'      => $request->costcode,
            'opex'          => $request->boolean('opex'),
            'capex'         => $request->boolean('capex'),
            'for_budgeting' => $request->boolean('for_budgeting'),
            'status'        => 'pending',
        ]);

        $this->storeAttachments($request, $projectRequest);

        return redirect()->route('requests.index')
            ->with('success', 'Project request submitted successfully.');
    }

    public function show(ProjectRequest $projectRequest): Response
    {
        return Inertia::render('requests/show', [
            'projectRequest' => $projectRequest->load(['requester', 'attachments']),
        ]);
    }

    public function edit(ProjectRequest $projectRequest): Response
    {
        return Inertia::render('requests/edit', [
            'projectRequest' => $projectRequest->load('attachments'),
        ]);
    }

    public function update(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        // Status-only patch (approve/reject from index or show page)
        if ($request->has('status') && count($request->all()) === 1) {
            $request->validate([
                'status' => ['required', 'string', 'in:approved,rejected,ongoing,completed,pending'],
            ]);

            $projectRequest->update(['status' => $request->status]);

            return back()->with('success', 'Request status updated.');
        }

        // Full update (from edit form)
        $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'job_type'        => ['required', 'string', 'max:100'],
            'description'     => ['required', 'string'],
            'job_location'    => ['required', 'string', 'max:255'],
            'costcode'        => ['nullable', 'string', 'max:100'],
            'opex'            => ['boolean'],
            'capex'           => ['boolean'],
            'for_budgeting'   => ['boolean'],

            'attachments'               => ['nullable', 'array'],
            'attachments.*.file'        => ['nullable', 'file', 'max:10240'],
            'attachments.*.type'        => ['required_with:attachments.*.file', 'string', 'in:picture,drawing,report'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],

            'deleted_attachments'   => ['nullable', 'array'],
            'deleted_attachments.*' => ['integer'],
        ]);

        $projectRequest->update([
            'title'         => $request->title,
            'job_type'      => $request->job_type,
            'description'   => $request->description,
            'job_location'  => $request->job_location,
            'costcode'      => $request->costcode,
            'opex'          => $request->boolean('opex'),
            'capex'         => $request->boolean('capex'),
            'for_budgeting' => $request->boolean('for_budgeting'),
        ]);

        // Delete removed attachments
        if ($request->filled('deleted_attachments')) {
            $toDelete = Attachment::whereIn('id', $request->deleted_attachments)
                ->where('reference_id', $projectRequest->id)
                ->where('reference_type', ProjectRequest::class)
                ->get();

            foreach ($toDelete as $att) {
                Storage::disk('public')->delete($att->filepath); // ✅ fixed: was Storage::delete()
                $att->delete();
            }
        }

        $this->storeAttachments($request, $projectRequest);

        return redirect()->route('requests.index')
            ->with('success', 'Project request updated successfully.');
    }

    public function destroy(ProjectRequest $projectRequest): RedirectResponse
    {
        foreach ($projectRequest->attachments as $att) {
            Storage::disk('public')->delete($att->filepath);
            $att->delete(); // 👈 this was missing!
        }

        $projectRequest->delete();

        return redirect()->route('requests.index')
            ->with('success', 'Project request deleted.');
    }

    private function storeAttachments(Request $request, ProjectRequest $projectRequest): void
    {
        $attachments = $request->file('attachments') ?? [];

        foreach ($attachments as $item) {
            if (empty($item['file'])) continue;

            $file = $item['file'];
            $type = $item['type'] ?? 'other';
            $desc = $item['description'] ?? null;

            $folder   = "requests/{$projectRequest->id}/{$type}s";
            $filepath = $file->store($folder, 'public');

            Attachment::create([
                'filename'       => $file->getClientOriginalName(),
                'filepath'       => $filepath,
                'reference_id'   => $projectRequest->id,
                'reference_type' => ProjectRequest::class,
                'description'    => $desc,
            ]);
        }
    }
}