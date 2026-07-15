<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NtpReviewController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectHubController;
use App\Http\Controllers\ProjectRequestController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TechnicalFeedbackController;
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

    // ── Notifications ────────────────────────────────────────────────────
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // ── Account ──────────────────────────────────────────────────────────
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('account/profile', [AccountController::class, 'updateProfile'])->name('account.update-profile');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.update-password');

    // ── Project Requests ──────────────────────────────────────────────────
    Route::resource('requests', ProjectRequestController::class)
        ->parameters(['requests' => 'projectRequest']);

    Route::get('requests/{projectRequest}/comments',  [CommentController::class, 'index'])->name('comments.index');
    Route::post('requests/{projectRequest}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}',               [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('requests/{projectRequest}/feedback', [TechnicalFeedbackController::class, 'store'])->name('requests.feedback.store');
    Route::patch('feedback/{technicalFeedback}',      [TechnicalFeedbackController::class, 'update'])->name('requests.feedback.update');
    Route::delete('feedback/{technicalFeedback}',     [TechnicalFeedbackController::class, 'destroy'])->name('requests.feedback.destroy');

    // ── NTP Reviews (department-user approval of NTPs) ─────────────────────
    Route::get('ntp-reviews',                 [NtpReviewController::class, 'index'])->name('ntp-reviews.index');
    Route::patch('ntp-reviews/{ntp}/approve', [NtpReviewController::class, 'approve'])->name('ntp-reviews.approve');
    Route::patch('ntp-reviews/{ntp}/reject',  [NtpReviewController::class, 'reject'])->name('ntp-reviews.reject');

    // Project Management
    Route::get('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');
    Route::post('projects/{project}/completion', [ProjectController::class, 'saveCompletion'])
        ->middleware('can:update,project')->name('projects.completion.save');

    foreach (['rfq', 'ntp', 'permits', 'vof', 'qpp', 'mtr', 'rfp', 'ioc', 'acr', 'psr', 'at', 'todo'] as $section) {
        Route::get("projects/{project}/hub/{$section}", [ProjectController::class, 'hub'])
            ->defaults('section', $section)
            ->middleware('can:view,project')
            ->name("projects.hub.{$section}");
    }

    // Hub CRUD routes
    Route::prefix('projects/{project}/hub')->middleware('can:update,project')->group(function () {
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
        Route::patch('vof/{vof}/status',       [ProjectHubController::class, 'updateVofStatus'])->name('hub.vof.update-status');
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
        Route::delete('rfp/{billing}',         [ProjectHubController::class, 'destroyBilling'])->name('hub.rfp.destroy');
        // IOC / ACR
        Route::post('ioc',                     [ProjectHubController::class, 'storeIoc'])->name('hub.ioc.store');
        Route::patch('ioc/{ioc}',              [ProjectHubController::class, 'updateIoc'])->name('hub.ioc.update');
        Route::delete('ioc/{ioc}',             [ProjectHubController::class, 'destroyIoc'])->name('hub.ioc.destroy');
        // PSR
        Route::post('psr',                     [ProjectHubController::class, 'storePsr'])->name('hub.psr.store');
        Route::post('psr/import',              [ProjectHubController::class, 'importPsr'])->name('hub.psr.import');
        Route::delete('psr/{psr}',             [ProjectHubController::class, 'destroyPsr'])->name('hub.psr.destroy');
        // Todo
        Route::post('todo',                    [ProjectHubController::class, 'storeTodo'])->name('hub.todo.store');
        Route::patch('todo/{task}/toggle',     [ProjectHubController::class, 'toggleTodo'])->name('hub.todo.toggle');
        Route::delete('todo/{task}',           [ProjectHubController::class, 'destroyTodo'])->name('hub.todo.destroy');
    });

    // Billing status is editable by the assigned project manager or the
    // engineer who created the project — a different (and broader) set than the
    // general hub-edit (can:update) permission, so it lives outside that group.
    Route::patch('projects/{project}/hub/rfp/{billing}/status', [ProjectHubController::class, 'updateBillingStatus'])
        ->middleware('can:manageBillingStatus,project')
        ->name('hub.rfp.update-status');

    Route::resource('projects', ProjectController::class);

    Route::middleware(['role:approver|assistant_manager|admin'])->group(function () {
        // ── Users (admin only) ────────────────────────────────────────────────
        Route::middleware(['role:admin'])->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::patch('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
            Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
        });

        // ── Master Data ───────────────────────────────────────────────────────
        Route::get('master-data', [MasterDataController::class, 'index'])->name('master.index');

        // Generic active/inactive toggle for any master resource ({type} = slug).
        Route::patch('master/{type}/{id}/toggle', [MasterDataController::class, 'toggleActive'])->name('master.toggle');

        // Job Types
        Route::post('master/job-types',           [MasterDataController::class, 'storeJobType'])->name('master.job-types.store');
        Route::put('master/job-types/{jobType}',  [MasterDataController::class, 'updateJobType'])->name('master.job-types.update');
        Route::delete('master/job-types/{jobType}', [MasterDataController::class, 'destroyJobType'])->name('master.job-types.destroy');

        // Job Locations
        Route::post('master/job-locations',                [MasterDataController::class, 'storeJobLocation'])->name('master.job-locations.store');
        Route::put('master/job-locations/{jobLocation}',   [MasterDataController::class, 'updateJobLocation'])->name('master.job-locations.update');
        Route::delete('master/job-locations/{jobLocation}', [MasterDataController::class, 'destroyJobLocation'])->name('master.job-locations.destroy');

        // Cost Codes
        Route::post('master/cost-codes/import',       [MasterDataController::class, 'importCostCodes'])->name('master.cost-codes.import');
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

        // Suppliers
        Route::post('master/suppliers/import',       [MasterDataController::class, 'importSuppliers'])->name('master.suppliers.import');
        Route::post('master/suppliers',              [MasterDataController::class, 'storeSupplier'])->name('master.suppliers.store');
        Route::put('master/suppliers/{supplier}',    [MasterDataController::class, 'updateSupplier'])->name('master.suppliers.update');
        Route::delete('master/suppliers/{supplier}', [MasterDataController::class, 'destroySupplier'])->name('master.suppliers.destroy');
    });

    // ── System Settings (admin only) ────────────────────────────────────────
    Route::middleware(['role:admin'])->group(function () {
        Route::get('system-settings', [SettingController::class, 'index'])->name('system-settings.index');
        Route::patch('system-settings', [SettingController::class, 'update'])->name('system-settings.update');
    });

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
