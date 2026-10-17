<?php

use App\Http\Controllers\Identity\ActivityController;
use App\Http\Controllers\Identity\DelegationController;
use App\Http\Controllers\Identity\MicrosoftController;
use App\Http\Controllers\Identity\OrganizationController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Identity\ReviewController;
use App\Http\Controllers\Identity\RoleController;
use App\Http\Controllers\Identity\SecurityController;
use App\Http\Controllers\Identity\UserController;
use App\Http\Middleware\AdministrationAccess;
use App\Http\Middleware\IdentityAccessAudit;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/invitation/{token}', [PasswordController::class, 'invitation'])->name('invitation.show');
    Route::post('/invitation/{token}', [PasswordController::class, 'accept'])->middleware('throttle:6,1')->name('invitation.accept');
    Route::get('/mfa/challenge', [SecurityController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [SecurityController::class, 'verifyChallenge'])->middleware('throttle:identity-mfa')->name('mfa.verify');
    Route::get('/auth/microsoft', [MicrosoftController::class, 'redirect'])->middleware('throttle:10,1')->name('microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'callback'])->middleware('throttle:10,1')->name('microsoft.callback');
});
Route::middleware(['auth', 'throttle:identity-security'])->prefix('account/security')->name('security.')->group(function () {
    Route::get('/', [SecurityController::class, 'index'])->name('index');
    Route::post('/mfa/setup', [SecurityController::class, 'setup'])->name('setup');
    Route::post('/mfa/enroll', [SecurityController::class, 'enroll'])->middleware('throttle:identity-mfa')->name('enroll');
    Route::post('/mfa/confirm', [SecurityController::class, 'confirm'])->middleware('throttle:identity-mfa')->name('confirm');
    Route::post('/mfa/recovery', [SecurityController::class, 'recovery'])->middleware('throttle:identity-mfa')->name('recovery');
    Route::delete('/mfa', [SecurityController::class, 'disable'])->middleware('throttle:identity-mfa')->name('disable');
    Route::put('/password', [SecurityController::class, 'password'])->middleware('throttle:identity-mfa')->name('password');
    Route::delete('/sessions/{identitySession}', [SecurityController::class, 'revoke'])->name('sessions.revoke');
});
Route::middleware(['auth', AdministrationAccess::class, IdentityAccessAudit::class])->prefix('admin/identity')->name('identity.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/resend', [UserController::class, 'resend'])->middleware('throttle:6,1')->name('users.resend');
    Route::delete('/users/{user}/sessions', [UserController::class, 'revoke'])->name('users.revoke');
    Route::post('/users/{user}/mfa-reset', [UserController::class, 'resetMfa'])->middleware('throttle:identity-mfa')->name('users.mfa-reset');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'save'])->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'save'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('/integrations', [ActivityController::class, 'integrations'])->name('integrations');
});
Route::post('/account/organization', [OrganizationController::class, 'switch'])->middleware('auth')->name('identity.organization');

Route::middleware(['auth', IdentityAccessAudit::class])->prefix('identity')->name('identity.')->group(function () {
    Route::get('/delegations', [DelegationController::class, 'index'])->name('delegations.index');
    Route::post('/delegations', [DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'revoke'])->name('delegations.revoke');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
    Route::put('/reviews/{review}/items/{item}', [ReviewController::class, 'decide'])->name('reviews.decide');
    Route::post('/reviews/{review}/items/{item}/refresh', [ReviewController::class, 'refresh'])->name('reviews.refresh');
    Route::post('/reviews/{review}/complete', [ReviewController::class, 'complete'])->name('reviews.complete');
});
