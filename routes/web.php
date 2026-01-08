<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Site management routes - ordered to prevent parameter conflicts
    Route::get('/sites/create', [\App\Http\Controllers\SiteController::class, 'create'])->name('sites.create')->middleware('admin');
    Route::post('/sites', [\App\Http\Controllers\SiteController::class, 'store'])->name('sites.store')->middleware('admin');
    Route::get('/sites/{site}/edit', [\App\Http\Controllers\SiteController::class, 'edit'])->name('sites.edit')->middleware('admin');
    Route::put('/sites/{site}', [\App\Http\Controllers\SiteController::class, 'update'])->name('sites.update')->middleware('admin');
    Route::delete('/sites/{site}', [\App\Http\Controllers\SiteController::class, 'destroy'])->name('sites.destroy')->middleware('admin');
    Route::resource('sites', \App\Http\Controllers\SiteController::class)->only(['index', 'show']);
    Route::get('/sites/{site}/download-report', [\App\Http\Controllers\SiteController::class, 'downloadReport'])->name('sites.downloadReport')->middleware('admin');

    // Client user management routes
    Route::get('/client-users/dashboard', [\App\Http\Controllers\ClientUserController::class, 'dashboard'])->name('client-users.dashboard')->middleware('admin');
    Route::get('/client-users', [\App\Http\Controllers\ClientUserController::class, 'index'])->name('client-users.index')->middleware('admin');
    Route::get('/client-users/create', [\App\Http\Controllers\ClientUserController::class, 'create'])->name('client-users.create')->middleware('admin');
    Route::post('/client-users', [\App\Http\Controllers\ClientUserController::class, 'store'])->name('client-users.store')->middleware('admin');
    Route::get('/client-users/{client_user}/edit', [\App\Http\Controllers\ClientUserController::class, 'edit'])->name('client-users.edit')->middleware('admin');
    Route::put('/client-users/{client_user}', [\App\Http\Controllers\ClientUserController::class, 'update'])->name('client-users.update')->middleware('admin');
    Route::delete('/client-users/{client_user}', [\App\Http\Controllers\ClientUserController::class, 'destroy'])->name('client-users.destroy')->middleware('admin');

    // Dashboard API routes
    Route::post('/fetch-backup-data', [\App\Http\Controllers\DashboardController::class, 'fetchBackupData'])->name('fetch.backup.data');
    Route::post('/fetch-all-backup-data', [\App\Http\Controllers\DashboardController::class, 'fetchAllBackupData'])->name('fetch.all.backup.data');
    Route::get('/get-backup-status', [\App\Http\Controllers\DashboardController::class, 'getBackupStatus'])->name('get.backup.status');
    Route::get('/download-report', [\App\Http\Controllers\DashboardController::class, 'downloadReport'])->name('download.report')->middleware('admin');
});

require __DIR__.'/auth.php';
