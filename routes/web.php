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

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';