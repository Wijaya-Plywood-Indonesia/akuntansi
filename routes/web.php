<?php

use App\Http\Controllers\NotaController;
use App\Http\Controllers\NotaThermalController;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

Route::get('/nota/{penjualan}/cetak', [NotaController::class, 'print'])
    ->name('nota.cetak');

Route::get('/nota/{penjualan}/cetakThermal', [NotaThermalController::class, 'print'])
    ->name('nota.cetakThermal');
Route::get('/', function () {
    return Redirect('/admin');
});
