<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\SessionController;
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

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::middleware('permission:reservation.view')->get('/reservations/export', [ReservationController::class, 'exportCsv'])->name('reservations.export');
    Route::middleware('permission:reservation.view')->get('/reservations/export/pdf', [ReservationController::class, 'exportPdf'])->name('reservations.export.pdf');
    Route::middleware('permission:reservation.view')->get('/reservations/calendar', [ReservationController::class, 'calendar'])->name('reservations.calendar');
    Route::middleware('permission:reservation.view')->get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::middleware('permission:reservation.create')->post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::middleware('permission:reservation.approve')->post('/reservations/maintenance', [ReservationController::class, 'storeMaintenance'])->name('reservations.maintenance');
    Route::middleware('permission:reservation.approve')->post('/reservations/{id}/decide', [ReservationController::class, 'decide'])->name('reservations.decide');

    Route::middleware('permission:visitor.view')->get('/visitors/export', [VisitorController::class, 'exportCsv'])->name('visitors.export');
    Route::middleware('permission:visitor.view')->get('/visitors/export/pdf', [VisitorController::class, 'exportPdf'])->name('visitors.export.pdf');
    Route::middleware('permission:visitor.view')->get('/visitors', [VisitorController::class, 'index'])->name('visitors.index');
    Route::middleware('permission:visitor.register')->post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
    Route::middleware('permission:visitor.checkin')->post('/visitors/{id}/check-in', [VisitorController::class, 'checkIn'])->name('visitors.checkin');
    Route::middleware('permission:visitor.checkin')->post('/visitors/{id}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.checkout');
    Route::middleware('permission:visitor.manage')->post('/visitors/{id}/toggle-watchlist', [VisitorController::class, 'toggleWatchlist'])->name('visitors.watchlist');
    Route::middleware('permission:visitor.view')->get('/visitors/{id}/badge', [VisitorController::class, 'badge'])->name('visitors.badge');

    Route::middleware('permission:document.view')->get('/documents/export', [DocumentController::class, 'exportCsv'])->name('documents.export');
    Route::middleware('permission:document.view')->get('/documents/export/pdf', [DocumentController::class, 'exportPdf'])->name('documents.export.pdf');
    Route::middleware('permission:document.view')->get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::middleware('permission:document.upload')->post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::middleware('permission:document.view')->get('/documents/{id}/compare', [DocumentController::class, 'compare'])->name('documents.compare');
    Route::middleware('permission:document.view')->get('/documents/{id}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::middleware('permission:document.view')->get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::middleware('permission:document.upload')->post('/documents/{id}/version', [DocumentController::class, 'addVersion'])->name('documents.version');
    Route::post('/documents/{id}/access', [DocumentController::class, 'updateAccess'])->middleware('auth')->name('documents.access');
    Route::middleware('permission:document.manage')->post('/documents/{id}/destroy', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::middleware('permission:retention.view')->get('/retention/export', [RetentionController::class, 'exportCsv'])->name('retention.export');
    Route::middleware('permission:retention.view')->get('/retention/export/pdf', [RetentionController::class, 'exportPdf'])->name('retention.export.pdf');
    Route::middleware('permission:retention.view')->get('/retention', [RetentionController::class, 'index'])->name('retention.index');
    Route::middleware('permission:retention.manage')->post('/retention/policies', [RetentionController::class, 'storePolicy'])->name('retention.policies');
    Route::middleware('permission:retention.manage')->post('/retention/assign', [RetentionController::class, 'storeAssignment'])->name('retention.assign');
    Route::middleware('permission:retention.manage')->post('/retention/{id}/dispose', [RetentionController::class, 'dispose'])->name('retention.dispose');
    Route::middleware('permission:retention.manage')->post('/retention/holds', [RetentionController::class, 'storeHold'])->name('retention.holds');
    Route::middleware('permission:retention.manage')->post('/retention/holds/{id}/release', [RetentionController::class, 'releaseHold'])->name('retention.release');

    Route::middleware('permission:legal.view')->get('/legal/export', [LegalCaseController::class, 'exportCsv'])->name('legal.export');
    Route::middleware('permission:legal.view')->get('/legal/export/pdf', [LegalCaseController::class, 'exportPdf'])->name('legal.export.pdf');
    Route::middleware('permission:legal.view')->get('/legal', [LegalCaseController::class, 'index'])->name('legal.index');
    Route::middleware('permission:legal.manage')->post('/legal', [LegalCaseController::class, 'store'])->name('legal.store');
    Route::middleware('permission:legal.manage')->post('/legal/vendors', [LegalCaseController::class, 'storeVendor'])->name('legal.vendors.store');
    Route::middleware('permission:legal.view')->get('/legal/{id}', [LegalCaseController::class, 'show'])->name('legal.show');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/parties', [LegalCaseController::class, 'addParty'])->name('legal.parties');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/milestones', [LegalCaseController::class, 'addMilestone'])->name('legal.milestones');
    Route::middleware('permission:legal.manage')->post('/legal/{caseId}/milestones/{milestoneId}/toggle', [LegalCaseController::class, 'toggleMilestone'])->name('legal.milestones.toggle');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/documents', [LegalCaseController::class, 'linkDocument'])->name('legal.documents');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/costs', [LegalCaseController::class, 'addCost'])->name('legal.costs');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/vendors', [LegalCaseController::class, 'linkVendor'])->name('legal.vendors.link');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/budget', [LegalCaseController::class, 'updateBudget'])->name('legal.budget');
    Route::middleware('permission:legal.manage')->post('/legal/{id}/destroy', [LegalCaseController::class, 'destroy'])->name('legal.destroy');

    Route::middleware('permission:contract.view')->get('/contracts/export', [ContractController::class, 'exportCsv'])->name('contracts.export');
    Route::middleware('permission:contract.view')->get('/contracts/export/pdf', [ContractController::class, 'exportPdf'])->name('contracts.export.pdf');
    Route::middleware('permission:contract.view')->get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::middleware('permission:contract.create')->post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::middleware('permission:contract.manage')->post('/contracts/templates', [ContractController::class, 'storeTemplate'])->name('contracts.templates');
    Route::middleware('permission:contract.manage')->post('/contracts/chains', [ContractController::class, 'storeChain'])->name('contracts.chains');
    Route::get('/contracts/sign/{token}', [ContractController::class, 'showSignForm'])->name('contracts.sign');
    Route::post('/contracts/sign/{token}', [ContractController::class, 'submitSign'])->name('contracts.sign.submit');
    Route::middleware('permission:contract.view')->get('/contracts/{id}', [ContractController::class, 'show'])->name('contracts.show');
    Route::middleware('permission:contract.approve')->post('/contracts/{id}/approve', [ContractController::class, 'approve'])->name('contracts.approve');
    Route::post('/contracts/{id}/amend', [ContractController::class, 'amend'])->name('contracts.amend');
    Route::middleware('permission:contract.view')->get('/contracts/{id}/amendments/{amendmentId}/diff', [ContractController::class, 'amendmentDiff'])->name('contracts.amendments.diff');
    Route::middleware('permission:contract.manage')->post('/contracts/{id}/destroy', [ContractController::class, 'destroy'])->name('contracts.destroy');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/import', [UserController::class, 'import'])->name('users.import');
        Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{id}/destroy', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/settings/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
        Route::post('/settings/roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
        Route::put('/settings/roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/settings/roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::get('/settings/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/settings/sessions/{id}/revoke', [SessionController::class, 'revoke'])->name('sessions.revoke');
    Route::post('/settings/sessions/revoke-others', [SessionController::class, 'revokeOthers'])->name('sessions.revokeOthers');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'exportCsv'])->name('audit.export');
    Route::get('/audit-logs/export/pdf', [AuditLogController::class, 'exportPdf'])->name('audit.export.pdf');
});
