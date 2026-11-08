<?php

use App\Http\Controllers\IntegrationApiController;
use App\Http\Controllers\IntegrationInboundController;
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
            ->where('system', 'hr1|hr2|hr3|hr4')
            ->middleware('integration:documents:write')->name('inbound.documents');
        Route::post('/inbound/hr1/events', [IntegrationInboundController::class, 'events'])
            ->middleware('integration:events:write')->name('inbound.hr1.events');

        Route::post('/visitors/{id}/verify-id', [IntegrationInboundController::class, 'verifyVisitorId'])
            ->middleware('integration:visitors:write')->name('visitors.verify-id');
    });
