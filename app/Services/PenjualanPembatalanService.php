<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\Penjualan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pembatalan nota penjualan.
 *
 * Satu langkah: jurnal balik dibuat sebagai DRAFT di Jurnal Pembantu Headers
 * (diposting manual oleh user), status nota menjadi DIBATALKAN, dan nota hilang
 * dari menu Pelunasan serta laporan penjualan. Nota tidak dihapus supaya jejak
 * pembukuannya tetap ada.
 */
class PenjualanPembatalanService
{
    public function __construct(
        private readonly JurnalBalikService $jurnalBalik,
    ) {}

    /** Tombol Batalkan hanya untuk nota yang sudah divalidasi dan belum lunas / belum dibatalkan. */
    public function bisaDibatalkan(Penjualan $penjualan): bool
    {
        return ! empty($penjualan->validated_by)
            && ! in_array($penjualan->status_transaksi, ['LUNAS', Penjualan::STATUS_DIBATALKAN], true);
    }

    /**
     * @return array{dibatalkan:int, dibalik:int, jurnal_balik:array<int,int>}
     *
     * @throws InvalidArgumentException Alasan kosong.
     * @throws RuntimeException Nota tidak memenuhi syarat dibatalkan.
     */
    public function batalkan(Penjualan $penjualan, int $userId, string $alasan): array
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($penjualan, $userId, $alasan) {
            /** @var Penjualan $nota */
            $nota = Penjualan::query()->lockForUpdate()->findOrFail($penjualan->id);

            if ($nota->isDibatalkan()) {
                throw new RuntimeException('Nota '.$nota->no_nota.' sudah dibatalkan sebelumnya.');
            }

            if ($nota->status_transaksi === 'LUNAS') {
                throw new RuntimeException(
                    'Nota '.$nota->no_nota.' berstatus LUNAS. Pembayaran harus dibatalkan lebih dulu '
                    .'(super admin: tombol "Batal Validasi") sebelum nota bisa dibatalkan.'
                );
            }

            if ($nota->returns()->exists()) {
                throw new RuntimeException('Nota '.$nota->no_nota.' sudah punya retur. Hapus data retur terlebih dahulu.');
            }

            $adaPelunasan = JurnalPembantuHeader::query()
                ->where('no_dokumen', $nota->no_nota)
                ->where('modul_asal', 'pelunasan_penjualan')
                ->where('adalah_jurnal_balik', false)
                ->whereIn('status', [JurnalPembantuHeader::STATUS_DRAFT, JurnalPembantuHeader::STATUS_DIPOSTING])
                ->exists();

            if ($adaPelunasan) {
                throw new RuntimeException('Nota '.$nota->no_nota.' sudah ada pelunasan. Batalkan pelunasannya lebih dulu.');
            }

            $hasil = $this->jurnalBalik->batalkanJurnalPenjualan($nota->no_nota, $userId, $alasan);

            $nota->update([
                'status_transaksi' => Penjualan::STATUS_DIBATALKAN,
                'dibatalkan_oleh'  => $userId,
                'dibatalkan_pada'  => now(),
                'alasan_batal'     => $alasan,
            ]);

            return $hasil;
        });
    }
}