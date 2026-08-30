<?php

use App\Http\Controllers\TankerCompartmentQrController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/tanker-compartments/{compartment}/qr-code/download', [TankerCompartmentQrController::class, 'download'])
    ->name('tanker-compartment.qr-code.download');