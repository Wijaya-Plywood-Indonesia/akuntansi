<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use Illuminate\Support\Facades\DB;

class JurnalBalikService
{
    public function buatJurnalBalikDariNota(string $noDokumen, int $userId): void
    {
        $headersAsli = JurnalPembantuHeader::where('no_dokumen', $noDokumen)
            ->where('adalah_jurnal_balik', false)
            ->where('status', '!=', JurnalPembantuHeader::STATUS_DIBALIK)
            ->whereIn('modul_asal', ['pembelian_barang', 'penjualan_telur'])
            ->get();

        if ($headersAsli->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($headersAsli, $userId) {
            $perNoJurnal = $headersAsli->groupBy('jurnal');

            foreach ($perNoJurnal as $noJurnalAsli => $headers) {
                $noJurnalBalik = $this->nextNomorJurnal();

                foreach ($headers as $headerAsli) {
                    $headerAsli->load('items');

                    $headerBalik = JurnalPembantuHeader::create([
                        'no_jurnal_pembantu'  => $this->nextNomorPembantu(),
                        'tgl_transaksi'       => now()->toDateString(),
                        'jenis_transaksi'     => $headerAsli->jenis_transaksi,
                        'modul_asal'          => $headerAsli->modul_asal,
                        'jurnal'              => $noJurnalBalik,
                        'no_akun'             => $headerAsli->no_akun,
                        'nama_akun'           => $headerAsli->nama_akun,
                        'map'                 => $headerAsli->map === 'd' ? 'k' : 'd', // ← DIBALIK
                        'keterangan'          => 'BALIK: ' . $headerAsli->keterangan,
                        'no_dokumen'          => $headerAsli->no_dokumen,
                        'catatan_internal'    => 'Jurnal balik atas pembatalan validasi nota ' . $headerAsli->no_dokumen,
                        'total_nilai'         => 0, // dihitung ulang oleh observer
                        'status'              => JurnalPembantuHeader::STATUS_DRAFT,
                        'adalah_jurnal_balik' => true,
                        'membalik_id'         => $headerAsli->id,
                        'dibuat_oleh'         => $userId,
                    ]);

                    $urut = 1;
                    foreach ($headerAsli->items as $itemAsli) {
                        JurnalPembantuItem::create([
                            'jurnal_pembantu_header_id' => $headerBalik->id,
                            'urut'                      => $urut++,
                            'jenis_pihak'               => $itemAsli->jenis_pihak,
                            'nama_pihak'                => $itemAsli->nama_pihak,
                            'nama_barang'               => $itemAsli->nama_barang,
                            'no_dokumen'                => $itemAsli->no_dokumen,
                            'no_referensi'              => $itemAsli->no_referensi,
                            'keterangan'                => 'BALIK: ' . $itemAsli->keterangan,
                            'banyak'                    => $itemAsli->banyak,
                            'm3'                        => $itemAsli->m3, 
                            'harga'                     => $itemAsli->harga,
                            'jumlah'                    => $itemAsli->jumlah,
                            
                            // ✅ FIX: Wajib disalin agar jurnal balik tidak menderita bug yg sama
                            'hit_kbk'                   => $itemAsli->hit_kbk, 
                            
                            'status'                    => true,
                            'created_by'                => $userId,
                            'updated_by'                => $userId,
                        ]);
                    }

                    $headerAsli->update([
                        'status'      => JurnalPembantuHeader::STATUS_DIBALIK,
                        'diubah_oleh' => $userId,
                    ]);
                }
            }
        });
    }

    private function nextNomorJurnal(): int
    {
        $max = JurnalPembantuHeader::lockForUpdate()->max('jurnal');
        return ($max ?? 0) + 1;
    }

    private function nextNomorPembantu(): int
    {
        $max = JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu');
        return ($max ?? 0) + 1;
    }
}