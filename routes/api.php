<?php

use App\Http\Controllers\TankerScanController;
use Illuminate\Support\Facades\Route;

Route::post('driver-login', [TankerScanController::class, 'driverLogin']);
Route::post('scan', [TankerScanController::class, 'scan']);
