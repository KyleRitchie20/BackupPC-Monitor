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
    Route::get('/sites/{site}/backups', [\App\Http\Controllers\SiteController::class, 'showBackups'])->name('sites.backups');
    Route::get('/sites/{site}/download-report', [\App\Http\Controllers\SiteController::class, 'downloadReport'])->name('sites.downloadReport')->middleware('admin');
    Route::get('/sites/{site}/agent-config', [\App\Http\Controllers\SiteController::class, 'showAgentConfig'])->name('sites.agent-config')->middleware('admin');
    Route::post('/sites/{site}/generate-token', [\App\Http\Controllers\SiteController::class, 'generateAgentToken'])->name('sites.generate-token')->middleware('admin');

    // User management routes (admin users and general user management)
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index')->middleware('admin');
    Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create')->middleware('admin');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store')->middleware('admin');
    Route::get('/users/{user}', [\App\Http\Controllers\UserController::class, 'show'])->name('users.show')->middleware('admin');
    Route::post('/users/{user}/send-test-report', [\App\Http\Controllers\UserController::class, 'sendTestReport'])->name('users.send-test-report')->middleware('admin');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit')->middleware('admin');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update')->middleware('admin');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy')->middleware('admin');

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
    Route::get('/reports', [\App\Http\Controllers\DashboardController::class, 'reports'])->name('reports.index')->middleware('admin');
    Route::get('/download-report', [\App\Http\Controllers\DashboardController::class, 'downloadReport'])->name('download.report')->middleware('admin');
});

// Agent communication routes (no authentication required for agents)
Route::prefix('api/agent')->group(function () {
    Route::post('/data', [\App\Http\Controllers\AgentController::class, 'receiveData'])->name('agent.receive-data');
    Route::post('/register', [\App\Http\Controllers\AgentController::class, 'registerAgent'])->name('agent.register');
    Route::post('/config', [\App\Http\Controllers\AgentController::class, 'getSiteConfig'])->name('agent.config');
    Route::post('/command/poll', [\App\Http\Controllers\AgentController::class, 'pollCommand'])->name('agent.command.poll');
    Route::post('/command/ack', [\App\Http\Controllers\AgentController::class, 'acknowledgeCommand'])->name('agent.command.ack');
});

// Agent command routes (admin only)
Route::middleware(['auth', 'admin'])->prefix('api/agent')->group(function () {
    Route::post('/command/{siteId}', [\App\Http\Controllers\AgentCommandController::class, 'sendCommand'])->name('agent.command');
    Route::post('/command/bulk/refresh', [\App\Http\Controllers\AgentCommandController::class, 'bulkRefresh'])->name('agent.command.bulk');
});

require __DIR__.'/auth.php';
