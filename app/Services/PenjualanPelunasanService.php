<?php

namespace App\Services;

use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PenjualanPelunasanService
{
    /**
     * Jenis transaksi yang bisa punya piutang & butuh proses pelunasan.
     * LUNAS dan BAYAR_DIMUKA sengaja tidak dimasukkan karena secara bisnis
     * proses uangnya sudah selesai di awal transaksi.
     */
    public const JENIS_BISA_DILUNASI = ['COD', 'DP'];

    public const METODE_TUNAI = 'TUNAI';

    public const METODE_TRANSFER = 'TRANSFER';

    public const METODE_CAMPUR = 'TUNAI & TRANSFER';

    /**
     * Query semua nota untuk ditampilkan di menu Pelunasan.
     * Semua jenis_transaksi ikut tampil (COD, DP, BAYAR_DIMUKA, LUNAS),
     * tapi yang jenis-nya COD/DP DAN masih ada sisa tagihan selalu
     * ditampilkan lebih dulu, baru disusul sisanya di bawah.
     */
    public function queryBelumLunas(?string $search = null): Builder
    {
        return Penjualan::query()
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('no_nota', 'like', "%{$search}%")
                        ->orWhere('nama_customer', 'like', "%{$search}%");
                });
            })
            // 0 = COD/DP dengan sisa tagihan (tampil dulu), 1 = sisanya (tampil belakangan)
            ->orderByRaw(
                'CASE WHEN jenis_transaksi IN (?, ?) AND bayar < total THEN 0 ELSE 1 END ASC',
                self::JENIS_BISA_DILUNASI
            )
            ->orderByDesc('tanggal');
    }

    public function getBelumLunas(?string $search = null, int $limit = 20): Collection
    {
        return $this->queryBelumLunas($search)->limit($limit)->get();
    }

    /**
     * Hitung sisa tagihan sebuah nota. Selalu dibulatkan tidak minus.
     */
    public function sisaTagihan(Penjualan $penjualan): int
    {
        return max(0, (int) $penjualan->total - (int) $penjualan->bayar);
    }

    /**
     * Hanya nota jenis COD/DP dengan sisa tagihan > 0 yang boleh diproses
     * pelunasannya. Nota LUNAS/BAYAR_DIMUKA yang ikut tampil di list
     * murni untuk informasi, tidak bisa dipilih untuk dibayar ulang.
     */
    public function bisaDilunasi(Penjualan $penjualan): bool
    {
        return in_array($penjualan->jenis_transaksi, self::JENIS_BISA_DILUNASI, true)
            && $this->sisaTagihan($penjualan) > 0;
    }

    /**
     * Proses satu kali pembayaran pelunasan/cicilan terhadap sebuah nota.
     *
     * @param  array{
     *     metode_pembayaran: string,
     *     nominal_tunai?: int,
     *     nominal_transfer?: int,
     *     nominal?: int,
     *     rekening_perusahaan_id?: int|null,
     *     keterangan?: string|null,
     * }  $payload
     * @return Penjualan Nota setelah diupdate.
     *
     * @throws InvalidArgumentException Jika input tidak valid (nominal 0, melebihi sisa, dsb).
     * @throws RuntimeException Jika nota tidak memenuhi syarat untuk dilunasi.
     */
    public function prosesPelunasan(Penjualan $penjualan, array $payload): Penjualan
    {
        $metode = $payload['metode_pembayaran'] ?? self::METODE_TUNAI;

        $nominal = $metode === self::METODE_CAMPUR
            ? (int) ($payload['nominal_tunai'] ?? 0) + (int) ($payload['nominal_transfer'] ?? 0)
            : (int) ($payload['nominal'] ?? 0);

        return DB::transaction(function () use ($penjualan, $payload, $metode, $nominal) {
            /** @var Penjualan $nota */
            $nota = Penjualan::query()->lockForUpdate()->findOrFail($penjualan->id);

            if (! in_array($nota->jenis_transaksi, self::JENIS_BISA_DILUNASI, true)) {
                throw new RuntimeException('Transaksi jenis '.$nota->jenis_transaksi.' tidak memerlukan pelunasan.');
            }

            $sisa = $this->sisaTagihan($nota);

            if ($sisa <= 0) {
                throw new RuntimeException('Nota ini sudah lunas.');
            }

            if ($nominal <= 0) {
                throw new InvalidArgumentException('Nominal pelunasan harus lebih dari 0.');
            }

            if ($nominal > $sisa) {
                throw new InvalidArgumentException(
                    'Nominal melebihi sisa tagihan (sisa: Rp '.number_format($sisa).').'
                );
            }

            if (in_array($metode, [self::METODE_TRANSFER, self::METODE_CAMPUR], true)) {
                $adaNominalTransfer = $metode === self::METODE_CAMPUR
                    ? (int) ($payload['nominal_transfer'] ?? 0) > 0
                    : true;

                if ($adaNominalTransfer && empty($payload['rekening_perusahaan_id'])) {
                    throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
                }
            }

            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            $tambahTunai = $metode === self::METODE_CAMPUR
                ? (int) ($payload['nominal_tunai'] ?? 0)
                : ($metode === self::METODE_TUNAI ? $nominal : 0);

            $tambahTransfer = $metode === self::METODE_CAMPUR
                ? (int) ($payload['nominal_transfer'] ?? 0)
                : ($metode === self::METODE_TRANSFER ? $nominal : 0);

            $nota->bayar = (int) $nota->bayar + $nominal;
            $nota->bayar_tunai = (int) $nota->bayar_tunai + $tambahTunai;
            $nota->bayar_transfer = (int) $nota->bayar_transfer + $tambahTransfer;

            if ($rekening) {
                $nota->bank = $rekening->nama_bank;
                $nota->no_rekening = $rekening->no_rekening;
            }

            if (! empty($payload['keterangan'])) {
                $nota->keterangan_pembayaran = trim(
                    ($nota->keterangan_pembayaran ? $nota->keterangan_pembayaran.' | ' : '').$payload['keterangan']
                );
            }

            if ((int) $nota->bayar >= (int) $nota->total) {
                $nota->status_transaksi = 'LUNAS';
            }

            $nota->validated_by = Auth::id();
            $nota->save();

            // TODO: catat baris riwayat pelunasan di sini setelah tabel
            // `pelunasan_penjualans` difinalkan (nota_id, nominal, metode, bank, user_id, dst).
            // TODO: trigger pembuatan jurnal akuntansi sesuai jenis_transaksi nota
            // (COD -> jurnal 1 tahap saat lunas; DP -> jurnal pelepasan uang muka + sisa).

            return $nota->fresh();
        });
    }
}
