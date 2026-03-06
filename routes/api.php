<?php

use App\Http\Controllers\Api\JurnalApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/jurnal/store', [JurnalApiController::class, 'store']);
});
