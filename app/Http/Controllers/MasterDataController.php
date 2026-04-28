<?php

namespace App\Http\Controllers;

use App\Models\CostCode;
use App\Models\JobLocation;
use App\Models\JobType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterDataController extends Controller
{
    // ── Index (render the page with all master data) ─────────────────────
    public function index()
    {
        return Inertia::render('master-data/index', [
            'jobTypes'     => JobType::orderBy('name')->get(['id', 'name', 'description', 'created_at']),
            'jobLocations' => JobLocation::orderBy('name')->get(['id', 'name', 'description', 'created_at']),
            'costCodes'    => CostCode::orderBy('name')->get(['id', 'name', 'description', 'created_at']),
        ]);
    }

    // ── Job Types ─────────────────────────────────────────────────────────
    public function storeJobType(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:job_types,name',
            'description' => 'nullable|string|max:500',
        ]);

        JobType::create($data);

        return redirect()->back()->with('success', 'Job type added.');
    }

    public function updateJobType(Request $request, JobType $jobType)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:job_types,name,' . $jobType->id,
            'description' => 'nullable|string|max:500',
        ]);

        $jobType->update($data);

        return redirect()->back()->with('success', 'Job type updated.');
    }

    public function destroyJobType(JobType $jobType)
    {
        $jobType->delete();

        return redirect()->back()->with('success', 'Job type deleted.');
    }

    // ── Job Locations ─────────────────────────────────────────────────────
    public function storeJobLocation(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:job_locations,name',
            'description' => 'nullable|string|max:500',
        ]);

        JobLocation::create($data);

        return redirect()->back()->with('success', 'Job location added.');
    }

    public function updateJobLocation(Request $request, JobLocation $jobLocation)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:job_locations,name,' . $jobLocation->id,
            'description' => 'nullable|string|max:500',
        ]);

        $jobLocation->update($data);

        return redirect()->back()->with('success', 'Job location updated.');
    }

    public function destroyJobLocation(JobLocation $jobLocation)
    {
        $jobLocation->delete();

        return redirect()->back()->with('success', 'Job location deleted.');
    }

    // ── Cost Codes ────────────────────────────────────────────────────────
    public function storeCostCode(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:cost_codes,name',
            'description' => 'nullable|string|max:500',
        ]);

        CostCode::create($data);

        return redirect()->back()->with('success', 'Cost code added.');
    }

    public function updateCostCode(Request $request, CostCode $costCode)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:cost_codes,name,' . $costCode->id,
            'description' => 'nullable|string|max:500',
        ]);

        $costCode->update($data);

        return redirect()->back()->with('success', 'Cost code updated.');
    }

    public function destroyCostCode(CostCode $costCode)
    {
        $costCode->delete();

        return redirect()->back()->with('success', 'Cost code deleted.');
    }
}