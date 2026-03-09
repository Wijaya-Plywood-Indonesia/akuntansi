<?php

use App\Http\Controllers\TerimaPressDryerController;

Route::post('/terima-produksi-dryer', [TerimaPressDryerController::class, 'terima']);