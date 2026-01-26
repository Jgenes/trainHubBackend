<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PesapalController;

Route::post('/pesapal/pay', [PesapalController::class, 'pay'])->name('pesapal.pay');

Route::get('/pesapal/redirect', [PesapalController::class, 'redirect'])
    ->name('pesapal.redirect');

Route::get('/pesapal/callback', [PesapalController::class, 'callback'])
    ->name('pesapal.callback');
Route::post('/pay', [PesapalController::class, 'pay'])->name('pesapal.pay');

Route::get('/pesapal/redirect', [PesapalController::class, 'redirect'])
    ->name('pesapal.redirect');

Route::post('/pesapal/callback', [PesapalController::class, 'callback'])
    ->name('pesapal.callback');
