<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ProjectRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/about', function () {
    return Inertia::render('about');
});

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // ── Project Requests ──────────────────────────────────────────────────
    Route::resource('requests', ProjectRequestController::class)
        ->parameters(['requests' => 'projectRequest']);

    Route::get('requests/{projectRequest}/comments',  [CommentController::class, 'index'])->name('comments.index');
    Route::post('requests/{projectRequest}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}',               [CommentController::class, 'destroy'])->name('comments.destroy');

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
