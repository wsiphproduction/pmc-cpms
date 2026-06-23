<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectHubController;
use App\Http\Controllers\ProjectRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('/about', function () {
    return Inertia::render('about');
});

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // ── Project Requests ──────────────────────────────────────────────────
    Route::resource('requests', ProjectRequestController::class)
        ->parameters(['requests' => 'projectRequest']);

    Route::get('requests/{projectRequest}/comments',  [CommentController::class, 'index'])->name('comments.index');
    Route::post('requests/{projectRequest}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}',               [CommentController::class, 'destroy'])->name('comments.destroy');

    // Project Management
    Route::get('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');

    foreach (['rfq', 'ntp', 'permits', 'vof', 'qpp', 'mtr', 'rfp', 'ioc', 'acr', 'psr', 'at', 'todo'] as $section) {
        Route::get("projects/{project}/hub/{$section}", [ProjectController::class, 'hub'])
            ->defaults('section', $section)
            ->name("projects.hub.{$section}");
    }

    // Hub CRUD routes
    Route::prefix('projects/{project}/hub')->group(function () {
        // RFQ
        Route::post('rfq',                     [ProjectHubController::class, 'storeRfq'])->name('hub.rfq.store');
        Route::patch('rfq/{rfq}',              [ProjectHubController::class, 'updateRfq'])->name('hub.rfq.update');
        Route::patch('rfq/{rfq}/status',       [ProjectHubController::class, 'updateRfqStatus'])->name('hub.rfq.update-status');
        Route::delete('rfq/{rfq}',             [ProjectHubController::class, 'destroyRfq'])->name('hub.rfq.destroy');
        // NTP
        Route::post('ntp',                     [ProjectHubController::class, 'storeNtp'])->name('hub.ntp.store');
        Route::delete('ntp/{ntp}',             [ProjectHubController::class, 'destroyNtp'])->name('hub.ntp.destroy');
        // Permits
        Route::post('permits',                 [ProjectHubController::class, 'storePermit'])->name('hub.permits.store');
        Route::delete('permits/{permit}',      [ProjectHubController::class, 'destroyPermit'])->name('hub.permits.destroy');
        // Variation Orders
        Route::post('vof',                     [ProjectHubController::class, 'storeVof'])->name('hub.vof.store');
        Route::patch('vof/{vof}',              [ProjectHubController::class, 'updateVof'])->name('hub.vof.update');
        Route::delete('vof/{vof}',             [ProjectHubController::class, 'destroyVof'])->name('hub.vof.destroy');
        // Quality Docs
        Route::post('qpp',                     [ProjectHubController::class, 'storeQpp'])->name('hub.qpp.store');
        Route::delete('qpp/{qpp}',             [ProjectHubController::class, 'destroyQpp'])->name('hub.qpp.destroy');
        // MTR
        Route::post('mtr',                     [ProjectHubController::class, 'storeMtr'])->name('hub.mtr.store');
        Route::delete('mtr/{mtr}',             [ProjectHubController::class, 'destroyMtr'])->name('hub.mtr.destroy');
        // Billing (RFP)
        Route::post('rfp',                     [ProjectHubController::class, 'storeBilling'])->name('hub.rfp.store');
        Route::patch('rfp/{billing}',          [ProjectHubController::class, 'updateBilling'])->name('hub.rfp.update');
        Route::patch('rfp/{billing}/status',   [ProjectHubController::class, 'updateBillingStatus'])->name('hub.rfp.update-status');
        Route::delete('rfp/{billing}',         [ProjectHubController::class, 'destroyBilling'])->name('hub.rfp.destroy');
        // IOC / ACR
        Route::post('ioc',                     [ProjectHubController::class, 'storeIoc'])->name('hub.ioc.store');
        Route::patch('ioc/{ioc}',              [ProjectHubController::class, 'updateIoc'])->name('hub.ioc.update');
        Route::delete('ioc/{ioc}',             [ProjectHubController::class, 'destroyIoc'])->name('hub.ioc.destroy');
        // PSR
        Route::post('psr',                     [ProjectHubController::class, 'storePsr'])->name('hub.psr.store');
        Route::delete('psr/{psr}',             [ProjectHubController::class, 'destroyPsr'])->name('hub.psr.destroy');
        // Todo
        Route::post('todo',                    [ProjectHubController::class, 'storeTodo'])->name('hub.todo.store');
        Route::patch('todo/{task}/toggle',     [ProjectHubController::class, 'toggleTodo'])->name('hub.todo.toggle');
        Route::delete('todo/{task}',           [ProjectHubController::class, 'destroyTodo'])->name('hub.todo.destroy');
    });

    Route::resource('projects', ProjectController::class);

    // ── Users ─────────────────────────────────────────────────────────────
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');

    // ── Master Data ───────────────────────────────────────────────────────
    Route::get('master-data', [MasterDataController::class, 'index'])->name('master.index');

    // Job Types
    Route::post('master/job-types',           [MasterDataController::class, 'storeJobType'])->name('master.job-types.store');
    Route::put('master/job-types/{jobType}',  [MasterDataController::class, 'updateJobType'])->name('master.job-types.update');
    Route::delete('master/job-types/{jobType}', [MasterDataController::class, 'destroyJobType'])->name('master.job-types.destroy');

    // Job Locations
    Route::post('master/job-locations',                [MasterDataController::class, 'storeJobLocation'])->name('master.job-locations.store');
    Route::put('master/job-locations/{jobLocation}',   [MasterDataController::class, 'updateJobLocation'])->name('master.job-locations.update');
    Route::delete('master/job-locations/{jobLocation}', [MasterDataController::class, 'destroyJobLocation'])->name('master.job-locations.destroy');

    // Cost Codes
    Route::post('master/cost-codes',              [MasterDataController::class, 'storeCostCode'])->name('master.cost-codes.store');
    Route::put('master/cost-codes/{costCode}',    [MasterDataController::class, 'updateCostCode'])->name('master.cost-codes.update');
    Route::delete('master/cost-codes/{costCode}', [MasterDataController::class, 'destroyCostCode'])->name('master.cost-codes.destroy');

    // Sites
    Route::post('master/sites',          [MasterDataController::class, 'storeSite'])->name('master.sites.store');
    Route::put('master/sites/{site}',    [MasterDataController::class, 'updateSite'])->name('master.sites.update');
    Route::delete('master/sites/{site}', [MasterDataController::class, 'destroySite'])->name('master.sites.destroy');

    // Classes
    Route::post('master/classes',                    [MasterDataController::class, 'storeClass'])->name('master.classes.store');
    Route::put('master/classes/{masterClass}',       [MasterDataController::class, 'updateClass'])->name('master.classes.update');
    Route::delete('master/classes/{masterClass}',    [MasterDataController::class, 'destroyClass'])->name('master.classes.destroy');

    // Priorities
    Route::post('master/priorities',              [MasterDataController::class, 'storePriority'])->name('master.priorities.store');
    Route::put('master/priorities/{priority}',    [MasterDataController::class, 'updatePriority'])->name('master.priorities.update');
    Route::delete('master/priorities/{priority}', [MasterDataController::class, 'destroyPriority'])->name('master.priorities.destroy');

    // Statuses
    Route::post('master/statuses',                    [MasterDataController::class, 'storeStatus'])->name('master.statuses.store');
    Route::put('master/statuses/{masterStatus}',      [MasterDataController::class, 'updateStatus'])->name('master.statuses.update');
    Route::delete('master/statuses/{masterStatus}',   [MasterDataController::class, 'destroyStatus'])->name('master.statuses.destroy');

    // Departments
    Route::post('master/departments',                [MasterDataController::class, 'storeDepartment'])->name('master.departments.store');
    Route::put('master/departments/{department}',    [MasterDataController::class, 'updateDepartment'])->name('master.departments.update');
    Route::delete('master/departments/{department}', [MasterDataController::class, 'destroyDepartment'])->name('master.departments.destroy');

    // Categories
    Route::post('master/categories',              [MasterDataController::class, 'storeCategory'])->name('master.categories.store');
    Route::put('master/categories/{category}',    [MasterDataController::class, 'updateCategory'])->name('master.categories.update');
    Route::delete('master/categories/{category}', [MasterDataController::class, 'destroyCategory'])->name('master.categories.destroy');

    // Service Types
    Route::post('master/service-types',                [MasterDataController::class, 'storeServiceType'])->name('master.service-types.store');
    Route::put('master/service-types/{serviceType}',    [MasterDataController::class, 'updateServiceType'])->name('master.service-types.update');
    Route::delete('master/service-types/{serviceType}', [MasterDataController::class, 'destroyServiceType'])->name('master.service-types.destroy');

    // Work Forces
    Route::post('master/work-forces',              [MasterDataController::class, 'storeWorkForce'])->name('master.work-forces.store');
    Route::put('master/work-forces/{workForce}',    [MasterDataController::class, 'updateWorkForce'])->name('master.work-forces.update');
    Route::delete('master/work-forces/{workForce}', [MasterDataController::class, 'destroyWorkForce'])->name('master.work-forces.destroy');

    // Structures
    Route::post('master/structures',              [MasterDataController::class, 'storeStructure'])->name('master.structures.store');
    Route::put('master/structures/{structure}',    [MasterDataController::class, 'updateStructure'])->name('master.structures.update');
    Route::delete('master/structures/{structure}', [MasterDataController::class, 'destroyStructure'])->name('master.structures.destroy');

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
