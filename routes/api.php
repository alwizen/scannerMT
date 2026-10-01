<?php

use App\Http\Controllers\TankerScanController;
use Illuminate\Support\Facades\Route;

Route::post('driver-login', [TankerScanController::class, 'driverLogin']);
Route::get('tankers/available', [TankerScanController::class, 'availableTankers']);
Route::post('scan-sessions', [TankerScanController::class, 'startScanSession']);
#validasi Comp
Route::get('scan/validate-compartment', [TankerScanController::class, 'validateCompartment']);
Route::post('scan', [TankerScanController::class, 'scan']);
Route::get('scan-history', [TankerScanController::class, 'scanHistory']);
Route::get('scan_history', [TankerScanController::class, 'scanHistory']);
Route::get('scanlogs', [TankerScanController::class, 'scanLogs']);
Route::get('scan-logs', [TankerScanController::class, 'scanLogs']);
Route::get('tms/scanlogs', [TankerScanController::class, 'tmsScanLogs']);
