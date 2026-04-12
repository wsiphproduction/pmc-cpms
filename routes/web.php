<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProjectRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/about', function(){
    return Inertia::render('about');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () { return Inertia::render('dashboard'); })->name('dashboard');
    Route::resource('requests', ProjectRequestController::class)->parameters(['requests' => 'projectRequest']);
    Route::get('requests/{projectRequest}/comments',        [CommentController::class, 'index'])->name('comments.index');
    Route::post('requests/{projectRequest}/comments',       [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}',                     [CommentController::class, 'destroy'])->name('comments.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
