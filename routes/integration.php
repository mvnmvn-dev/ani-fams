<?php

use App\Http\Controllers\IntegrationApiController;
use App\Http\Controllers\IntegrationInboundController;
use App\Http\Controllers\CrossSystemIntegrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/integration/v1')
    ->middleware('throttle:120,1')
    ->name('api.integration.v1.')
    ->group(function (): void {
        Route::get('/departments', [IntegrationApiController::class, 'departments'])
            ->middleware('integration:reference:read')->name('departments');
        Route::get('/facilities', [IntegrationApiController::class, 'facilities'])
            ->middleware('integration:reference:read')->name('facilities');
        Route::get('/visitors', [IntegrationApiController::class, 'visitors'])
            ->middleware('integration:visitors:read')->name('visitors.index');
        Route::get('/visitors/{id}', [IntegrationApiController::class, 'visitor'])
            ->middleware('integration:visitors:read')->name('visitors.show');
        Route::post('/visitors', [IntegrationApiController::class, 'storeVisitor'])
            ->middleware('integration:visitors:write')->name('visitors.store');
        Route::get('/visits', [IntegrationApiController::class, 'visits'])
            ->middleware('integration:visitors:read')->name('visits.index');
        Route::post('/visits', [IntegrationApiController::class, 'storeVisit'])
            ->middleware('integration:visitors:write')->name('visits.store');
        Route::get('/reservations', [IntegrationApiController::class, 'reservations'])
            ->middleware('integration:reservations:read')->name('reservations.index');
        Route::post('/reservations', [IntegrationApiController::class, 'storeReservation'])
            ->middleware('integration:reservations:write')->name('reservations.store');
        Route::get('/visitors/{id}/history', [IntegrationApiController::class, 'visitorHistory'])
            ->middleware('integration:visitors:read')->name('visitors.history');
        Route::get('/data/{resource}', [IntegrationApiController::class, 'moduleData'])
            ->middleware('integration:reference:read')->name('data');

        Route::post('/inbound/hr1/employees', [IntegrationInboundController::class, 'employees'])
            ->middleware('integration:employees:write')->name('inbound.hr1.employees');
        Route::post('/inbound/hr1/interviews', [IntegrationInboundController::class, 'interviews'])
            ->middleware('integration:visitors:write')->name('inbound.hr1.interviews');
        Route::post('/inbound/{system}/documents', [IntegrationInboundController::class, 'documents'])
            ->where('system', 'hr1|hr2|hr3|hr4|fin|ecom')
            ->middleware('integration:documents:write')->name('inbound.documents');

        // Cross-system draft contracts: these are FAMS-side endpoints only.
        Route::post('/inbound/scm/vendor-visits', [CrossSystemIntegrationController::class, 'vendorVisit'])
            ->middleware('integration:visitors:write')->name('inbound.scm.vendor-visits');
        Route::post('/inbound/{system}/vehicles', [CrossSystemIntegrationController::class, 'vehicle'])
            ->where('system', 'fvm|vrds')->middleware('integration:vehicles:write')->name('inbound.vehicles');
        Route::post('/inbound/{system}/availability', [CrossSystemIntegrationController::class, 'availability'])
            ->where('system', 'fvm|vrds|sws|hr2')->middleware('integration:availability:write')->name('inbound.availability');
        Route::post('/inbound/{system}/contracts', [CrossSystemIntegrationController::class, 'contract'])
            ->where('system', 'scm|hr1|subscriptions')->middleware('integration:contracts:write')->name('inbound.contracts');
        Route::post('/inbound/{system}/disputes', [CrossSystemIntegrationController::class, 'dispute'])
            ->where('system', 'ecom|support')->middleware('integration:legal:write')->name('inbound.disputes');
        Route::post('/inbound/dtrs/tracking', [CrossSystemIntegrationController::class, 'documentTracking'])
            ->middleware('integration:documents:tracking:write')->name('inbound.dtrs.tracking');

        Route::post('/inbound/{system}/events', [IntegrationInboundController::class, 'events'])
            ->where('system', 'hr1|hr2')->middleware('integration:events:write')->name('inbound.events');

    });
