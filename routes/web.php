<?php

use App\Http\Controllers\Admin\AdministrationController;
use App\Http\Controllers\Admin\ReferenceTransferController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Middleware\AdministrationAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('leads', LeadController::class);
    Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::post('/leads/{lead}/qualify', [LeadController::class, 'qualify'])->name('leads.qualify');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::post('/leads/{lead}/activities', [LeadController::class, 'activity'])->name('leads.activities.store');
});

Route::get('/branding/logo', [AdministrationController::class, 'logo'])->name('branding.logo');
Route::middleware(['auth', AdministrationAccess::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdministrationController::class, 'index'])->name('index');
    Route::put('/settings', [AdministrationController::class, 'settings'])->name('settings');
    Route::put('/modules/{key}', [AdministrationController::class, 'module'])->name('module');
    Route::post('/fields', [AdministrationController::class, 'field'])->name('fields.store');
    Route::put('/fields/{field}', [AdministrationController::class, 'field'])->name('fields.update');
    Route::post('/references', [AdministrationController::class, 'reference'])->name('references.store');
    Route::put('/references/{reference}', [AdministrationController::class, 'reference'])->name('references.update');
    Route::put('/sequences/{key}', [AdministrationController::class, 'sequence'])->name('sequence');
    Route::post('/health/probe', [AdministrationController::class, 'heartbeat'])->middleware('throttle:6,1')->name('health.probe');
    Route::get('/transfer', [ReferenceTransferController::class, 'index'])->name('transfer');
    Route::post('/transfer/upload', [ReferenceTransferController::class, 'upload'])->middleware('throttle:10,1')->name('transfer.upload');
    Route::post('/transfer/preview', [ReferenceTransferController::class, 'preview'])->name('transfer.preview');
    Route::post('/transfer/commit', [ReferenceTransferController::class, 'commit'])->name('transfer.commit');
    Route::post('/transfer/export', [ReferenceTransferController::class, 'export'])->middleware('throttle:10,1')->name('transfer.export');
});

require __DIR__.'/identity.php';

require __DIR__.'/organization.php';
