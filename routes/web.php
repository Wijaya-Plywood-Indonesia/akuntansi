<?php

use App\Http\Controllers\PenjualanExportController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\NotaThermalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\SuratJalanPrintController;


Route::prefix('surat-jalan')->group(function () {

    Route::get('gudang/{id}/cetak', [SuratJalanPrintController::class, 'cetak'])
        ->name('surat-jalan.gudang.cetak');

});

Route::prefix('surat-jalan')->group(function () {

    Route::get('penjualan/{penjualan}/cetak', [SuratJalanController::class, 'print'])
        ->name('surat-jalan.penjualan.cetak');

});

Route::get('/surat-jalan/{penjualan}', [SuratJalanController::class, 'print'])
    ->name('surat-jalan.cetak');

Route::get('/nota/{penjualan}/cetak', [NotaController::class, 'print'])
    ->name('nota.cetak');

Route::get('/nota/{penjualan}/cetakThermal', [NotaThermalController::class, 'print'])
    ->name('nota.cetakThermal');


Route::get('/', function () {
    return redirect('/admin');
});


Route::get('/force-download-excel', [PenjualanExportController::class, 'download'])
    ->name('force.download');
// ->middleware('auth:filament.admin');
