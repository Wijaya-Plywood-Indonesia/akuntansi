<?php


use App\Http\Controllers\Api\JurnalApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TerimaPressDryerController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/jurnal/store', [JurnalApiController::class, 'store']);
});



Route::post('/terima-produksi-dryer', [TerimaPressDryerController::class, 'terima']);

