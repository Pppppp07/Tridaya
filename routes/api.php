<?php

use App\Http\Controllers\DwaradayaController;
use Illuminate\Support\Facades\Route;

// Stateless, tanpa CSRF karena dipanggil server SuperApp. JWT tetap wajib diverifikasi.
Route::post('/sso-check', [DwaradayaController::class, 'periksa'])
    ->middleware('throttle:dwaradaya-check')->name('sso.dwaradaya.periksa');
