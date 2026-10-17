<?php

use App\Http\Controllers\Organization\StructureController;
use App\Http\Middleware\AdministrationAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('structure')->name('structure.')->group(function () {
    Route::get('/', [StructureController::class, 'index'])->name('index');
    Route::middleware(AdministrationAccess::class)->group(function () {
        Route::prefix('catalog/{type}')->whereIn('type', ['legal-entities', 'organizations'])->name('catalog.')->group(function () {
            Route::get('/', [StructureController::class, 'catalog'])->name('index');
            Route::get('/create', [StructureController::class, 'form'])->name('create');
            Route::post('/', [StructureController::class, 'save'])->name('store');
            Route::get('/{record}/edit', [StructureController::class, 'form'])->whereNumber('record')->name('edit');
            Route::put('/{record}', [StructureController::class, 'save'])->whereNumber('record')->name('update');
        });
        Route::prefix('{organization}/catalog/{type}')->whereNumber('organization')->whereIn('type', ['departments', 'branches', 'teams', 'positions', 'cost-centres'])->name('units.')->group(function () {
            Route::get('/', [StructureController::class, 'catalog'])->name('index');
            Route::get('/create', [StructureController::class, 'form'])->name('create');
            Route::post('/', [StructureController::class, 'save'])->name('store');
            Route::get('/{record}/edit', [StructureController::class, 'form'])->whereNumber('record')->name('edit');
            Route::put('/{record}', [StructureController::class, 'save'])->whereNumber('record')->name('update');
        });
        Route::get('/{organization}/people/{membership}/edit', [StructureController::class, 'assignment'])->whereNumber(['organization', 'membership'])->name('assignment');
        Route::put('/{organization}/people/{membership}', [StructureController::class, 'assign'])->whereNumber(['organization', 'membership'])->name('assign');
    });
    Route::get('/{organization}', [StructureController::class, 'overview'])->whereNumber('organization')->name('overview');
});
