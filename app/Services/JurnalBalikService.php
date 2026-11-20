<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Services\NomorJurnalService;
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

                // Foto nota asli ikut disalin ke jurnal balik yang baru,
                // supaya tetap kelihatan foto buktinya walau ini transaksi
                // (jurnal) yang terpisah dari yang asli.
                $lampiranAsli = \App\Models\JurnalLampiran::where('jurnal', (int) $noJurnalAsli)->first();
                if ($lampiranAsli && filled($lampiranAsli->paths)) {
                    \App\Models\JurnalLampiran::updateOrCreate(
                        ['jurnal' => $noJurnalBalik],
                        [
                            'paths'       => $lampiranAsli->paths,
                            'uploaded_by' => $userId,
                        ]
                    );
                }

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

                        // ✅ FIX 1: Copy langsung total nilai dari aslinya, jangan diset 0
                        'total_nilai'         => $headerAsli->total_nilai,

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
                            'id_barang'                 => $itemAsli->id_barang,
                            'no_dokumen'                => $itemAsli->no_dokumen,
                            'no_referensi'              => $itemAsli->no_referensi,
                            'keterangan'                => 'BALIK: ' . $itemAsli->keterangan,
                            'banyak'                    => $itemAsli->banyak,
                            'm3'                        => $itemAsli->m3,
                            'harga'                     => $itemAsli->harga,
                            'jumlah'                    => $itemAsli->jumlah,

                            // ✅ FIX 2: Sertakan shadow_harga dan shadow_jumlah agar Observer tidak "buta"
                            'shadow_harga'              => $itemAsli->shadow_harga ?? $itemAsli->harga,
                            'shadow_jumlah'             => $itemAsli->shadow_jumlah ?? $itemAsli->jumlah,

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

    /**
     * Pembatalan nota PENJUALAN (jurnal modul penjualan_triplek / penjualan_telur).
     *
     *  - Header yang sudah DIPOSTING : dibuatkan jurnal balik (D/K dibalik) berstatus
     *                                  DRAFT di Jurnal Pembantu Headers. TIDAK diposting
     *                                  otomatis; user memposting manual.
     *  - Header yang masih DRAFT     : ditandai DIBATALKAN (belum berefek ke Jurnal Umum).
     *  - Header yang sudah DIBALIK   : dilewati, jadi aman kalau jurnalnya sebelumnya
     *                                  sudah dibalik manual lewat Jurnal Pembantu Headers.
     *
     * Stok persediaan mengikuti Jurnal Umum, jadi stok baru kembali setelah jurnal
     * balik diposting manual.
     *
     * @return array{dibatalkan:int, dibalik:int, jurnal_balik:array<int,int>}
     */
    public function batalkanJurnalPenjualan(string $noNota, int $userId, string $alasan): array
    {
        return DB::transaction(function () use ($noNota, $userId, $alasan) {
            $headers = JurnalPembantuHeader::query()
                ->with(['items' => fn ($q) => $q->where('status', true)])
                ->where('no_dokumen', $noNota)
                ->where('adalah_jurnal_balik', false)
                ->whereIn('modul_asal', ['penjualan_triplek', 'penjualan_telur'])
                ->orderBy('map')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $hasil = ['dibatalkan' => 0, 'dibalik' => 0, 'jurnal_balik' => []];

            // 1) Draft -> dibatalkan
            foreach ($headers->where('status', JurnalPembantuHeader::STATUS_DRAFT) as $header) {
                $header->update([
                    'status'      => JurnalPembantuHeader::STATUS_DIBATALKAN,
                    'diubah_oleh' => $userId,
                ]);
                $hasil['dibatalkan']++;
            }

            // 2) Diposting -> jurnal balik, lalu diposting
            $diposting = $headers
                ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
                ->groupBy('jurnal');

            foreach ($diposting as $noJurnalAsli => $grup) {
                $nums = NomorJurnalService::nextJurnalDanNoJP();
                $noJurnalBalik = $nums['jurnal'];
                $noJP = $nums['no_jp'];

                // Foto nota asli ikut disalin ke jurnal balik.
                $lampiranAsli = \App\Models\JurnalLampiran::where('jurnal', (int) $noJurnalAsli)->first();
                if ($lampiranAsli && filled($lampiranAsli->paths)) {
                    \App\Models\JurnalLampiran::updateOrCreate(
                        ['jurnal' => $noJurnalBalik],
                        ['paths' => $lampiranAsli->paths, 'uploaded_by' => $userId]
                    );
                }

                foreach ($grup as $headerAsli) {
                    $ket = trim((string) $headerAsli->keterangan);

                    $headerBalik = JurnalPembantuHeader::create([
                        'no_jurnal_pembantu'  => $noJP++,
                        'tgl_transaksi'       => now()->toDateString(),
                        'jenis_transaksi'     => 'balik',
                        'modul_asal'          => $headerAsli->modul_asal,
                        'jurnal'              => $noJurnalBalik,
                        'no_akun'             => $headerAsli->no_akun,
                        'nama_akun'           => $headerAsli->nama_akun,
                        'map'                 => strtolower($headerAsli->map) === 'd' ? 'k' : 'd',
                        'keterangan'          => ($ket !== '' ? 'BALIK: '.$ket : 'BALIK').' // batal',
                        'no_dokumen'          => $headerAsli->no_dokumen,
                        'catatan_internal'    => "Pembatalan nota {$noNota}: {$alasan}",
                        'total_nilai'         => $headerAsli->total_nilai,
                        'status'              => JurnalPembantuHeader::STATUS_DRAFT,
                        'adalah_jurnal_balik' => true,
                        'membalik_id'         => $headerAsli->id,
                        'dibuat_oleh'         => $userId,
                    ]);

                    foreach ($headerAsli->items as $item) {
                        JurnalPembantuItem::create([
                            'jurnal_pembantu_header_id' => $headerBalik->id,
                            'urut'         => $item->urut,
                            'jenis_pihak'  => $item->jenis_pihak,
                            'pihak_id'     => $item->pihak_id,
                            'nama_pihak'   => $item->nama_pihak,
                            'nama_barang'  => $item->nama_barang,
                            'id_barang'    => $item->id_barang,
                            'no_dokumen'   => $item->no_dokumen,
                            'no_referensi' => $item->no_referensi,
                            'keterangan'   => $item->keterangan,
                            'ukuran'       => $item->ukuran,
                            'kualitas'     => $item->kualitas,
                            'banyak'       => $item->banyak,
                            'm3'           => $item->m3,
                            'harga'        => $item->harga,
                            'jumlah'       => $item->jumlah,
                            'hit_kbk'      => $item->hit_kbk,
                            'mm'           => $item->mm,
                            'status'       => true,
                            'created_by'   => $userId,
                            'updated_by'   => $userId,
                        ]);
                    }

                    $headerAsli->update([
                        'status'      => JurnalPembantuHeader::STATUS_DIBALIK,
                        'diubah_oleh' => $userId,
                    ]);
                    $hasil['dibalik']++;
                }

                $hasil['jurnal_balik'][] = (int) $noJurnalBalik;
            }

            return $hasil;
        });
    }

    private function nextNomorJurnal(): int
    {
        return NomorJurnalService::nextJurnal();
    }

    private function nextNomorPembantu(): int
    {
        return NomorJurnalService::nextNoJP();
    }
}