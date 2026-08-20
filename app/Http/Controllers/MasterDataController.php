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
use App\Models\Supplier;
use App\Models\WorkForce;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MasterDataController extends Controller
{
    // ── Index (render the page with all master data) ─────────────────────
    public function index()
    {
        return Inertia::render('master-data/index', [
            'jobTypes'     => JobType::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'jobLocations' => JobLocation::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'costCodes'    => CostCode::latest()->get(['id', 'name', 'description', 'division', 'cost_center', 'activity', 'expense_description', 'agu_per_class', 'agu_per_stat', 'is_active', 'created_at']),
            'sites'        => Site::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'classes'      => MasterClass::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'priorities'   => Priority::orderByRaw('CASE WHEN sequence_no IS NULL THEN 1 ELSE 0 END, sequence_no ASC')
                ->orderBy('name')
                ->get(['id', 'name', 'sequence_no', 'description', 'is_active', 'created_at']),
            'statuses'     => MasterStatus::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'departments'  => Department::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'categories'   => Category::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'serviceTypes' => ServiceType::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'workForces'   => WorkForce::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            'structures'   => Structure::latest()->get(['id', 'name', 'description', 'is_active', 'created_at']),
            // Only PMD's own supplier list is managed here. Every other row on
            // file — bulk CSV imports and the pre-loaded set — stays hidden and
            // out of the RFQ dropdown until someone adds it to this list.
            'suppliers'    => Supplier::pmd()->with('creator:id,name')->latest()
                ->get(['id', 'company', 'accredited', 'email', 'telephone_no', 'mobile_no', 'is_active', 'created_by', 'created_at'])
                ->map(fn (Supplier $s) => [
                    ...$s->only(['id', 'company', 'accredited', 'email', 'telephone_no', 'mobile_no', 'is_active', 'created_at']),
                    'added_by' => $s->creator?->name,
                ]),
            'importedSupplierCount' => Supplier::where('source', Supplier::SOURCE_IMPORT)->count(),
        ]);
    }

    /**
     * Master resource slugs → model class. Used by the generic active toggle.
     */
    private const TOGGLE_MODELS = [
        'job-types'     => JobType::class,
        'job-locations' => JobLocation::class,
        'cost-codes'    => CostCode::class,
        'sites'         => Site::class,
        'classes'       => MasterClass::class,
        'priorities'    => Priority::class,
        'statuses'      => MasterStatus::class,
        'departments'   => Department::class,
        'categories'    => Category::class,
        'service-types' => ServiceType::class,
        'work-forces'   => WorkForce::class,
        'structures'    => Structure::class,
        'suppliers'     => Supplier::class,
    ];

    /**
     * Flip the is_active flag of any master-data record. Deactivated records
     * stay in the admin list but are filtered out of every dropdown elsewhere.
     */
    public function toggleActive(string $type, int $id)
    {
        $model = self::TOGGLE_MODELS[$type] ?? null;
        abort_unless($model !== null, 404);

        $record = $model::findOrFail($id);
        $record->is_active = ! $record->is_active;
        $record->save();

        return redirect()->back()->with(
            'success',
            'Entry ' . ($record->is_active ? 'activated' : 'deactivated') . '.'
        );
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
        $data = $this->validateCostCode($request);
        $data['description'] = $data['expense_description'] ?? ($data['cost_center'] ?? null);

        CostCode::create($data);

        return redirect()->back()->with('success', 'Cost code added.');
    }

    public function updateCostCode(Request $request, CostCode $costCode)
    {
        $data = $this->validateCostCode($request, $costCode->id);
        $data['description'] = $data['expense_description'] ?? ($data['cost_center'] ?? null);

        $costCode->update($data);

        return redirect()->back()->with('success', 'Cost code updated.');
    }

    /**
     * Validate a cost code create/update. Description is derived from
     * expense_description → cost_center to match the CSV import semantics.
     */
    private function validateCostCode(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                => 'required|string|max:191|unique:cost_codes,name' . ($ignoreId ? ',' . $ignoreId : ''),
            'division'            => 'nullable|string|max:191',
            'cost_center'         => 'nullable|string|max:191',
            'activity'            => 'nullable|string|max:191',
            'expense_description' => 'nullable|string|max:500',
            'agu_per_class'       => 'nullable|string|max:191',
            'agu_per_stat'        => 'nullable|string|max:100',
            'is_active'           => 'boolean',
        ]);
    }

    public function destroyCostCode(CostCode $costCode)
    {
        $costCode->delete();

        return redirect()->back()->with('success', 'Cost code deleted.');
    }

    // ── Suppliers ─────────────────────────────────────────────────────────

    /**
     * Type-ahead for the Add Supplier form.
     *
     * Searches every supplier on file, not just PMD's list, so the dormant pool
     * (bulk CSV rows and the pre-loaded set) can be found by name and added
     * back. `listed` tells the caller which matches are already on the list and
     * therefore not addable again. Matching starts from the first character, so
     * the capped result set is what keeps a broad query cheap. Queried live
     * rather than shipped with the page because a CSV import can leave
     * thousands of rows dormant.
     */
    public function searchSuppliers(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        return response()->json(
            Supplier::where('company', 'like', '%' . $term . '%')
                ->orderBy('company')
                ->limit(8)
                ->get(['id', 'company', 'source', 'email', 'telephone_no', 'mobile_no'])
                ->map(fn (Supplier $s) => [
                    'id'           => $s->id,
                    'company'      => $s->company,
                    'listed'       => $s->source === Supplier::SOURCE_PMD,
                    'email'        => $s->email,
                    'telephone_no' => $s->telephone_no,
                    'mobile_no'    => $s->mobile_no,
                ])
        );
    }

    public function storeSupplier(Request $request)
    {
        // No `unique` rule here: most suppliers on file sit outside PMD's list
        // (bulk CSV rows and the pre-loaded set), and rejecting a name the user
        // cannot even see would be a dead end. The lookup below decides whether
        // this is a brand-new supplier or one to promote into the list.
        $data = $request->validate([
            'company'      => 'required|string|max:191',
            'accredited'   => 'nullable|boolean',
            'email'        => 'nullable|email|max:191',
            'telephone_no' => 'nullable|string|max:100',
            'mobile_no'    => 'nullable|string|max:100',
        ]);
        // The Add form no longer asks about accreditation — being on PMD's list
        // is what makes a supplier usable — so an absent value means true.
        $data['accredited'] = $request->boolean('accredited', true);

        $existing = Supplier::where('company', $data['company'])->first();

        if ($existing) {
            if ($existing->source === Supplier::SOURCE_PMD) {
                throw ValidationException::withMessages([
                    'company' => 'This supplier is already on the list.',
                ]);
            }

            // Promote the hidden row rather than duplicating it. Blank contact
            // fields mean "nothing typed", not "erase" — the add form starts
            // empty, so whatever the row already holds is kept.
            $existing->update([
                'accredited' => $data['accredited'],
                ...array_filter([
                    'email'        => $data['email'] ?? null,
                    'telephone_no' => $data['telephone_no'] ?? null,
                    'mobile_no'    => $data['mobile_no'] ?? null,
                ], fn ($value) => filled($value)),
                'source'     => Supplier::SOURCE_PMD,
                'is_active'  => true,
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'Supplier added from the existing supplier pool.');
        }

        $data['source']     = Supplier::SOURCE_PMD;
        $data['created_by'] = auth()->id();

        Supplier::create($data);

        return redirect()->back()->with('success', 'Supplier added.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'company'      => 'required|string|max:191|unique:suppliers,company,' . $supplier->id,
            'accredited'   => 'nullable|boolean',
            'email'        => 'nullable|email|max:191',
            'telephone_no' => 'nullable|string|max:100',
            'mobile_no'    => 'nullable|string|max:100',
        ]);
        $data['accredited'] = $request->boolean('accredited', true);

        $supplier->update($data);

        return redirect()->back()->with('success', 'Supplier updated.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier deleted.');
    }

    /**
     * Bulk-import suppliers from a CSV file.
     *
     * Expected columns (header row, case-insensitive): company, email,
     * telephone_no, mobile_no. Existing suppliers (matched by company) are
     * updated; new ones are created. Chunked upsert stays under SQL Server's
     * 2100 bind-parameter limit and is fast on MySQL.
     *
     * Newly created rows are tagged as imported, so they feed the RFQ supplier
     * pool without cluttering PMD's own Master Data list.
     */
    public function importSuppliers(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return redirect()->back()->with('error', 'The CSV file is empty.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $columns   = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $companyIdx = $this->findCsvColumn($columns, ['company', 'company_name', 'name', 'supplier']);
        $emailIdx   = $this->findCsvColumn($columns, ['email', 'email_address']);
        $telIdx     = $this->findCsvColumn($columns, ['telephone_no', 'telephone', 'tel', 'landline']);
        $mobileIdx  = $this->findCsvColumn($columns, ['mobile_no', 'mobile', 'cellphone', 'cell']);

        if ($companyIdx === null) {
            fclose($handle);
            return redirect()->back()->with('error', 'CSV must contain a "company" column.');
        }

        $now      = now()->toDateTimeString();
        $seen     = [];
        $batch    = [];
        $imported = 0;
        $chunk    = 100; // 100 rows × 6 columns = 600 bind params — safe for SQL Server.

        $cell = function ($row, $idx) {
            if ($idx === null) {
                return null;
            }
            $v = trim((string) ($row[$idx] ?? ''));
            // Common "empty" placeholders in the source data.
            if ($v === '' || in_array(strtolower($v), ['na', 'n/a', 'none'], true)) {
                return null;
            }
            return $v;
        };

        $flush = function () use (&$batch, &$imported) {
            if ($batch) {
                Supplier::upsert($batch, ['company'], ['email', 'telephone_no', 'mobile_no', 'updated_at']);
                $imported += count($batch);
                $batch = [];
            }
        };

        while (($row = fgetcsv($handle)) !== false) {
            $company = trim((string) ($row[$companyIdx] ?? ''));
            if ($company === '') {
                continue;
            }
            $company = mb_substr($company, 0, 191);
            if (isset($seen[$company])) {
                continue;
            }
            $seen[$company] = true;

            $email = $cell($row, $emailIdx);

            $batch[] = [
                'company'      => $company,
                // Marks the row as bulk data rather than part of PMD's own list.
                // Existing rows keep whatever source they already have — the
                // upsert below only touches the contact columns.
                'source'       => Supplier::SOURCE_IMPORT,
                'email'        => $email !== null ? mb_substr($email, 0, 191) : null,
                'telephone_no' => $cell($row, $telIdx) !== null ? mb_substr($cell($row, $telIdx), 0, 100) : null,
                'mobile_no'    => $cell($row, $mobileIdx) !== null ? mb_substr($cell($row, $mobileIdx), 0, 100) : null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            if (count($batch) >= $chunk) {
                $flush();
            }
        }
        $flush();
        fclose($handle);

        if ($imported === 0) {
            return redirect()->back()->with('error', 'No valid suppliers found in the CSV.');
        }

        return redirect()->back()->with('success', "Imported {$imported} supplier(s) from CSV.");
    }

    /**
     * Bulk-import cost codes from a CSV file.
     *
     * Every column of the GL-code CSV is captured (header row, case-insensitive):
     * Full_GL_Codes → name (unique key), Division, Cost_Center, Activity,
     * Expense_Description, AGU_PER_CLASS, AGU_PER_STAT and isActive. The
     * human-readable description falls back to Expense_Description → Cost_Center.
     * Existing codes (matched by name) are updated; new ones are created. Chunked
     * upsert keeps the parameter count under SQL Server's 2100 limit while
     * staying fast on MySQL too.
     */
    public function importCostCodes(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return redirect()->back()->with('error', 'The CSV file is empty.');
        }

        // Strip a UTF-8 BOM from the first header cell, then normalise headers.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $columns   = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $nameIdx     = $this->findCsvColumn($columns, ['full_gl_codes', 'gl_code', 'gl_codes', 'code', 'name']);
        $divisionIdx = $this->findCsvColumn($columns, ['division']);
        $centerIdx   = $this->findCsvColumn($columns, ['cost_center', 'cost center']);
        $activityIdx = $this->findCsvColumn($columns, ['activity']);
        $expenseIdx  = $this->findCsvColumn($columns, ['expense_description', 'expense description']);
        $classIdx    = $this->findCsvColumn($columns, ['agu_per_class', 'agu per class', 'class']);
        $statIdx     = $this->findCsvColumn($columns, ['agu_per_stat', 'agu per stat', 'status']);
        $activeIdx   = $this->findCsvColumn($columns, ['isactive', 'is_active', 'active']);

        if ($nameIdx === null) {
            fclose($handle);
            return redirect()->back()->with('error', 'CSV must contain a "Full_GL_Codes" (code) column.');
        }

        $now      = now()->toDateTimeString();
        $seen     = [];
        $batch    = [];
        $imported = 0;
        $chunk    = 150; // 150 rows × 11 columns = 1650 bind params — safe for SQL Server.

        // Trim a cell to a string, or null when blank.
        $cell = function ($row, $idx) {
            if ($idx === null) {
                return null;
            }
            $v = trim((string) ($row[$idx] ?? ''));
            return $v === '' ? null : $v;
        };

        $clip = fn ($v, $len) => $v === null ? null : mb_substr($v, 0, $len);

        $flush = function () use (&$batch, &$imported) {
            if ($batch) {
                CostCode::upsert(
                    $batch,
                    ['name'],
                    ['description', 'division', 'cost_center', 'activity', 'expense_description', 'agu_per_class', 'agu_per_stat', 'is_active', 'updated_at']
                );
                $imported += count($batch);
                $batch = [];
            }
        };

        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[$nameIdx] ?? ''));
            if ($name === '') {
                continue;
            }
            $name = mb_substr($name, 0, 191);

            // De-duplicate within the file — the GL code is the unique key, so
            // the first occurrence wins (duplicates in the source repeat data).
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $expense = $cell($row, $expenseIdx);
            $center  = $cell($row, $centerIdx);
            // Prefer the readable expense name for the description, else the cost center.
            $desc    = $expense ?? $center;

            $activeRaw = $cell($row, $activeIdx);
            $isActive  = $activeRaw === null
                ? true
                : in_array(strtolower($activeRaw), ['1', 'true', 'yes', 'active', 'open'], true);

            $batch[] = [
                'name'                => $name,
                'description'         => $clip($desc, 500),
                'division'            => $clip($cell($row, $divisionIdx), 191),
                'cost_center'         => $clip($center, 191),
                'activity'            => $clip($cell($row, $activityIdx), 191),
                'expense_description' => $clip($expense, 500),
                'agu_per_class'       => $clip($cell($row, $classIdx), 191),
                'agu_per_stat'        => $clip($cell($row, $statIdx), 100),
                'is_active'           => $isActive,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            if (count($batch) >= $chunk) {
                $flush();
            }
        }
        $flush();
        fclose($handle);

        if ($imported === 0) {
            return redirect()->back()->with('error', 'No valid cost codes found in the CSV.');
        }

        return redirect()->back()->with('success', "Imported {$imported} cost code(s) from CSV.");
    }

    private function findCsvColumn(array $columns, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $columns, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
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
