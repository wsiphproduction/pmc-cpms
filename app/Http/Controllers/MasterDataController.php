<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CostCode;
use App\Models\Department;
use App\Models\JobLocation;
use App\Models\JobType;
use App\Models\MasterClass;
use App\Models\MasterStatus;
use App\Models\Priority;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\Structure;
use App\Models\WorkForce;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterDataController extends Controller
{
    // ── Index (render the page with all master data) ─────────────────────
    public function index()
    {
        return Inertia::render('master-data/index', [
            'jobTypes'     => JobType::latest()->get(['id', 'name', 'description', 'created_at']),
            'jobLocations' => JobLocation::latest()->get(['id', 'name', 'description', 'created_at']),
            'costCodes'    => CostCode::latest()->get(['id', 'name', 'description', 'created_at']),
            'sites'        => Site::latest()->get(['id', 'name', 'description', 'created_at']),
            'classes'      => MasterClass::latest()->get(['id', 'name', 'description', 'created_at']),
            'priorities'   => Priority::orderByRaw('CASE WHEN sequence_no IS NULL THEN 1 ELSE 0 END, sequence_no ASC')
                ->orderBy('name')
                ->get(['id', 'name', 'sequence_no', 'description', 'created_at']),
            'statuses'     => MasterStatus::latest()->get(['id', 'name', 'description', 'created_at']),
            'departments'  => Department::latest()->get(['id', 'name', 'description', 'created_at']),
            'categories'   => Category::latest()->get(['id', 'name', 'description', 'created_at']),
            'serviceTypes' => ServiceType::latest()->get(['id', 'name', 'description', 'created_at']),
            'workForces'   => WorkForce::latest()->get(['id', 'name', 'description', 'created_at']),
            'structures'   => Structure::latest()->get(['id', 'name', 'description', 'created_at']),
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

    // Sites
    public function storeSite(Request $request)
    {
        $data = $this->validateMasterData($request, 'sites');

        Site::create($data);

        return redirect()->back()->with('success', 'Site added.');
    }

    public function updateSite(Request $request, Site $site)
    {
        $data = $this->validateMasterData($request, 'sites', $site->id);

        $site->update($data);

        return redirect()->back()->with('success', 'Site updated.');
    }

    public function destroySite(Site $site)
    {
        $site->delete();

        return redirect()->back()->with('success', 'Site deleted.');
    }

    // Classes
    public function storeClass(Request $request)
    {
        $data = $this->validateMasterData($request, 'classes');

        MasterClass::create($data);

        return redirect()->back()->with('success', 'Class added.');
    }

    public function updateClass(Request $request, MasterClass $masterClass)
    {
        $data = $this->validateMasterData($request, 'classes', $masterClass->id);

        $masterClass->update($data);

        return redirect()->back()->with('success', 'Class updated.');
    }

    public function destroyClass(MasterClass $masterClass)
    {
        $masterClass->delete();

        return redirect()->back()->with('success', 'Class deleted.');
    }

    // Priorities
    public function storePriority(Request $request)
    {
        $data = $this->validatePriority($request);

        Priority::create($data);

        return redirect()->back()->with('success', 'Priority added.');
    }

    public function updatePriority(Request $request, Priority $priority)
    {
        $data = $this->validatePriority($request, $priority->id);

        $priority->update($data);

        return redirect()->back()->with('success', 'Priority updated.');
    }

    public function destroyPriority(Priority $priority)
    {
        $priority->delete();

        return redirect()->back()->with('success', 'Priority deleted.');
    }

    // Statuses
    public function storeStatus(Request $request)
    {
        $data = $this->validateMasterData($request, 'statuses');

        MasterStatus::create($data);

        return redirect()->back()->with('success', 'Status added.');
    }

    public function updateStatus(Request $request, MasterStatus $masterStatus)
    {
        $data = $this->validateMasterData($request, 'statuses', $masterStatus->id);

        $masterStatus->update($data);

        return redirect()->back()->with('success', 'Status updated.');
    }

    public function destroyStatus(MasterStatus $masterStatus)
    {
        $masterStatus->delete();

        return redirect()->back()->with('success', 'Status deleted.');
    }

    // Departments
    public function storeDepartment(Request $request)
    {
        $data = $this->validateMasterData($request, 'departments');

        Department::create($data);

        return redirect()->back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $data = $this->validateMasterData($request, 'departments', $department->id);

        $department->update($data);

        return redirect()->back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Department $department)
    {
        $department->delete();

        return redirect()->back()->with('success', 'Department deleted.');
    }

    // Categories
    public function storeCategory(Request $request)
    {
        $data = $this->validateMasterData($request, 'categories');

        Category::create($data);

        return redirect()->back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $this->validateMasterData($request, 'categories', $category->id);

        $category->update($data);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();

        return redirect()->back()->with('success', 'Category deleted.');
    }

    // Service Types
    public function storeServiceType(Request $request)
    {
        $data = $this->validateMasterData($request, 'service_types');

        ServiceType::create($data);

        return redirect()->back()->with('success', 'Service type added.');
    }

    public function updateServiceType(Request $request, ServiceType $serviceType)
    {
        $data = $this->validateMasterData($request, 'service_types', $serviceType->id);

        $serviceType->update($data);

        return redirect()->back()->with('success', 'Service type updated.');
    }

    public function destroyServiceType(ServiceType $serviceType)
    {
        $serviceType->delete();

        return redirect()->back()->with('success', 'Service type deleted.');
    }

    // Work Forces
    public function storeWorkForce(Request $request)
    {
        $data = $this->validateMasterData($request, 'work_forces');

        WorkForce::create($data);

        return redirect()->back()->with('success', 'Work force added.');
    }

    public function updateWorkForce(Request $request, WorkForce $workForce)
    {
        $data = $this->validateMasterData($request, 'work_forces', $workForce->id);

        $workForce->update($data);

        return redirect()->back()->with('success', 'Work force updated.');
    }

    public function destroyWorkForce(WorkForce $workForce)
    {
        $workForce->delete();

        return redirect()->back()->with('success', 'Work force deleted.');
    }

    // Structures
    public function storeStructure(Request $request)
    {
        $data = $this->validateMasterData($request, 'structures');

        Structure::create($data);

        return redirect()->back()->with('success', 'Structure added.');
    }

    public function updateStructure(Request $request, Structure $structure)
    {
        $data = $this->validateMasterData($request, 'structures', $structure->id);

        $structure->update($data);

        return redirect()->back()->with('success', 'Structure updated.');
    }

    public function destroyStructure(Structure $structure)
    {
        $structure->delete();

        return redirect()->back()->with('success', 'Structure deleted.');
    }

    private function validateMasterData(Request $request, string $table, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255|unique:' . $table . ',name' . ($ignoreId ? ',' . $ignoreId : ''),
            'description' => 'nullable|string|max:500',
        ]);
    }

    private function validatePriority(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255|unique:priorities,name' . ($ignoreId ? ',' . $ignoreId : ''),
            'sequence_no' => 'nullable|integer|min:1|max:999999',
            'description' => 'nullable|string|max:500',
        ]);
    }
}
