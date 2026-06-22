<?php

use App\Http\Controllers\JurnalProduksiController;
use App\Http\Controllers\PenjualanExportController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\NotaThermalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\SuratJalanPrintController;
use App\Models\Pembelian;
use App\Models\Supplier;

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

Route::get('/internal/supplier-detail/{id}', function ($id) {
    $validStatuses = [
        Pembelian::STATUS_HUTANG,
        Pembelian::STATUS_CICILAN,
        Pembelian::STATUS_LUNAS,
    ];

    $supplier = Supplier::find($id);
    if (!$supplier) return response()->json(['error' => 'Not found'], 404);

    $invoices = Pembelian::where('supplier_id', $id)
        ->whereIn('status', $validStatuses)
        ->select(['id', 'nomor_nota', 'tanggal', 'grand_total', 'status'])
        ->orderByDesc('tanggal')
        ->get()
        ->map(fn($p) => [
            'id'          => $p->id,
            'nomor_nota'  => $p->nomor_nota,
            'tanggal'     => $p->tanggal?->format('Y-m-d'),
            'grand_total' => (float) $p->grand_total,
            'status'      => $p->status,
        ]);

    return response()->json([
        'name'            => $supplier->name,
        'total_pembelian' => $invoices->sum('grand_total'),
        'nota_dicetak'    => $invoices->count(),
        'invoices'        => $invoices,
    ]);
})->middleware(['web', 'auth'])->name('internal.supplier-detail');

Route::post('/jurnal-produksi/import', [JurnalProduksiController::class, 'importExcel'])->name('jurnal-produksi.import');
