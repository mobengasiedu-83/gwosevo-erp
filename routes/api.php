<?php

use App\Http\Middleware\ActiveApiUser;
use App\Http\Controllers\CRM\LeadCaptureController;
use App\Http\Controllers\ContactCentreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', ActiveApiUser::class]);

Route::post('/v1/leads/capture/{organization:code}', [LeadCaptureController::class, 'website'])->middleware('throttle:crm-lead-capture')->name('api.leads.capture.website');
Route::middleware(['auth:sanctum', ActiveApiUser::class])->post('/v1/leads', [LeadCaptureController::class, 'api'])->name('api.leads.store');
Route::post('/v1/pbx/{connection}/events', [ContactCentreController::class, 'webhook'])->middleware('throttle:pbx-events')->name('api.pbx.events');
