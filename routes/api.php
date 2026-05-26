<?php


use App\Http\Controllers\Api\JurnalApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TerimaPressDryerController;
use App\Http\Controllers\Api\AkuntansiRotaryJurnalController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/jurnal/store', [JurnalApiController::class, 'store']);
});

Route::prefix('jurnal/rotary')->group(function () {
    Route::post('/create', [AkuntansiRotaryJurnalController::class, 'create']);
    Route::get('/check/{noJurnal}', [AkuntansiRotaryJurnalController::class, 'check']);
});

Route::post('/terima-produksi-dryer', [TerimaPressDryerController::class, 'terima']);

