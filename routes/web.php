<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:reservation.view')->get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::middleware('permission:reservation.create')->post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::middleware('permission:reservation.approve')->post('/reservations/{id}/decide', [ReservationController::class, 'decide'])->name('reservations.decide');

    Route::middleware('permission:visitor.view')->get('/visitors', [VisitorController::class, 'index'])->name('visitors.index');
    Route::middleware('permission:visitor.register')->post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
    Route::middleware('permission:visitor.checkin')->post('/visitors/{id}/check-in', [VisitorController::class, 'checkIn'])->name('visitors.checkin');
    Route::middleware('permission:visitor.checkin')->post('/visitors/{id}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.checkout');
    Route::middleware('permission:visitor.manage')->post('/visitors/{id}/toggle-watchlist', [VisitorController::class, 'toggleWatchlist'])->name('visitors.watchlist');

    Route::middleware('permission:document.view')->get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::middleware('permission:document.upload')->post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::middleware('permission:document.view')->get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::middleware('permission:document.upload')->post('/documents/{id}/version', [DocumentController::class, 'addVersion'])->name('documents.version');
    Route::post('/documents/{id}/access', [DocumentController::class, 'updateAccess'])->middleware('auth')->name('documents.access');

    Route::middleware('permission:retention.view')->get('/retention', [RetentionController::class, 'index'])->name('retention.index');
    Route::middleware('permission:retention.manage')->post('/retention/policies', [RetentionController::class, 'storePolicy'])->name('retention.policies');
    Route::middleware('permission:retention.manage')->post('/retention/assign', [RetentionController::class, 'storeAssignment'])->name('retention.assign');
    Route::middleware('permission:retention.manage')->post('/retention/{id}/dispose', [RetentionController::class, 'dispose'])->name('retention.dispose');
    Route::middleware('permission:retention.manage')->post('/retention/holds', [RetentionController::class, 'storeHold'])->name('retention.holds');
    Route::middleware('permission:retention.manage')->post('/retention/holds/{id}/release', [RetentionController::class, 'releaseHold'])->name('retention.release');

    Route::middleware('permission:legal.view')->get('/legal', [LegalCaseController::class, 'index'])->name('legal.index');
    Route::middleware('permission:legal.manage')->post('/legal', [LegalCaseController::class, 'store'])->name('legal.store');
    Route::middleware('permission:legal.view')->get('/legal/{id}', [LegalCaseController::class, 'show'])->name('legal.show');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/parties', [LegalCaseController::class, 'addParty'])->name('legal.parties');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/milestones', [LegalCaseController::class, 'addMilestone'])->name('legal.milestones');
    Route::middleware('permission:legal.manage')->post('/legal/{caseId}/milestones/{milestoneId}/toggle', [LegalCaseController::class, 'toggleMilestone'])->name('legal.milestones.toggle');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/documents', [LegalCaseController::class, 'linkDocument'])->name('legal.documents');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/costs', [LegalCaseController::class, 'addCost'])->name('legal.costs');

    Route::middleware('permission:contract.view')->get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::middleware('permission:contract.create')->post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::middleware('permission:contract.view')->get('/contracts/{id}', [ContractController::class, 'show'])->name('contracts.show');
    Route::middleware('permission:contract.approve')->post('/contracts/{id}/approve', [ContractController::class, 'approve'])->name('contracts.approve');
    Route::post('/contracts/{id}/amend', [ContractController::class, 'amend'])->name('contracts.amend');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});
