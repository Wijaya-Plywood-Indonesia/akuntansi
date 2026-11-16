<?php

namespace App\Services\Penjualans;

use App\Models\DetailPenjualan;
use App\Models\JurnalPembantuHeader;
use App\Models\Penjualan;
use App\Services\JurnalPenjualanTriplekService;
use Illuminate\Support\Facades\DB;

class SyncPenjualanService
{
    /**
     * Jenis transaksi yang uangnya masuk LEWAT menu Pelunasan.
     * Kolom bayar/kembalian untuk jenis ini tidak boleh diubah dari sinkronisasi.
     */
    public const JENIS_BAYAR_TERKUNCI = ['COD', 'DP'];

    /**
     * Kalkulasi total dari detail_penjualans (Return float untuk decimal 15,2)
     */
    public static function calculateCurrentTotal(int $penjualanId): float
    {
        return (float) DetailPenjualan::where('penjualan_id', $penjualanId)
            ->sum('subtotal');
    }

    /**
     * Kalkulasi kembalian awal saat modal dibuka
     */
    public static function calculateKembalian(int $penjualanId, float $total_kemarin): float
    {
        $total_hari_ini = self::calculateCurrentTotal($penjualanId);

        // Jika harga turun, kembalikan selisihnya ke pelanggan
        if ($total_kemarin > $total_hari_ini) {
            return $total_kemarin - $total_hari_ini;
        }

        return 0;
    }

    /**
     * Proses Sinkronisasi ke Database dan Regenerate Jurnal jika masih Draft
     */
    public static function syncPenjualan(int $penjualanId, array $data, ?int $userId = null): void
    {
        DB::transaction(function () use ($penjualanId, $data, $userId) {
            $penjualan = Penjualan::with('details.barang')->findOrFail($penjualanId);

            // Hitung sub_total riil dari rincian detail_penjualans
            $subTotalBaru = (float) $penjualan->details->sum('subtotal');

            // Hitung ulang PPN nominal jika ada persentase PPN
            $ppnPersen = (float) ($penjualan->ppn_persen ?? 0);
            $ppnNominalBaru = $ppnPersen > 0 ? round($subTotalBaru * ($ppnPersen / 100), 2) : 0.0;

            // GUARD: untuk COD/DP, nilai bayar TIDAK boleh ditimpa dari sinkronisasi.
            // Uang yang diterima hanya boleh bertambah lewat menu Pelunasan
            // (yang sekaligus membuat jurnal pelunasannya). Kalau tidak, nota
            // tampak "LUNAS" padahal tidak ada jurnal kas/piutang yang menutup.
            if (in_array($penjualan->jenis_transaksi, self::JENIS_BAYAR_TERKUNCI, true)) {
                $bayarBaru = (float) $penjualan->bayar;
                $kembalianBaru = 0.0;
            } else {
                $bayarBaru = $data['bayar'];
                $kembalianBaru = $data['kembalian'];
            }

            $penjualan->update([
                'sub_total' => $subTotalBaru,
                'ppn_nominal' => $ppnNominalBaru,
                'total' => $data['total'],
                'bayar' => $bayarBaru,
                'kembalian' => $kembalianBaru,
                // 'metode_pembayaran' => strtoupper($data['metode_bayar']),
                'keterangan_pembayaran' => $data['keterangan'] ?? $penjualan->keterangan_pembayaran,
            ]);

            // Jika transaksi sudah pernah divalidasi dan memiliki Jurnal Pembantu yang masih DRAFT:
            // Regenerate jurnal pembantu agar mencerminkan rincian barang, harga, dan total terbaru.
            $headersDraft = JurnalPembantuHeader::where('no_dokumen', $penjualan->no_nota)
                ->where('adalah_jurnal_balik', false)
                ->where('modul_asal', 'penjualan_triplek')
                ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
                ->get();

            if ($headersDraft->isNotEmpty()) {
                // Simpan nomor jurnal lama agar nomornya tidak melonjak/berubah
                $nomorJurnalLama = (int) $headersDraft->first()->jurnal;

                // Hapus item dan header draft lama
                foreach ($headersDraft as $h) {
                    $h->items()->delete();
                    $h->delete();
                }

                // Terbitkan ulang jurnal pembantu dengan nomor jurnal yang sama
                $effectiveUserId = $userId ?? $penjualan->validated_by ?? auth()->id() ?? 1;
                $penjualan->refresh();
                $penjualan->loadMissing(['details.barang.subAnakAkun', 'rekeningPerusahaan.subAnakAkun']);

                app(JurnalPenjualanTriplekService::class)->buatJurnalPenjualan($penjualan, $effectiveUserId, $nomorJurnalLama);
            }
        });
    }
}