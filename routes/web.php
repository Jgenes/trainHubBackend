<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PesapalController;

Route::get('/', fn () => response()->json([
    'application' => config('app.name'),
    'status'      => 'ok',
    'health'      => url('/up'),
]));

/*
| Legacy PesaPal browser flow. Names are namespaced so they never clash with
| the API callback used as the IPN / notification URL.
*/
Route::post('/pesapal/pay', [PesapalController::class, 'pay'])->name('pesapal.web.pay');
Route::post('/pay', [PesapalController::class, 'pay']);
Route::get('/pesapal/redirect', [PesapalController::class, 'redirect'])->name('pesapal.web.redirect');
Route::match(['get', 'post'], '/pesapal/callback', [PesapalController::class, 'callback'])
    ->name('pesapal.web.callback');
