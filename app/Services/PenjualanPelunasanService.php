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
    public const JENIS_BISA_DILUNASI = ['COD', 'DP'];

    public const METODE_TUNAI = 'TUNAI';

    public const METODE_TRANSFER = 'TRANSFER';

    public const METODE_CAMPUR = 'TUNAI & TRANSFER';

    public function __construct(
        private readonly JurnalPenjualanPelunasanService $jurnalService,
    ) {}

    public function queryBelumLunas(?string $search = null): Builder
    {
        return Penjualan::query()
            ->whereNotNull('validated_by')
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('no_nota', 'like', "%{$search}%")
                        ->orWhere('nama_customer', 'like', "%{$search}%");
                });
            })
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

    public function sisaTagihan(Penjualan $penjualan): int
    {
        return max(0, (int) $penjualan->total - (int) $penjualan->bayar);
    }

    public function bisaDilunasi(Penjualan $penjualan): bool
    {
        return in_array($penjualan->jenis_transaksi, self::JENIS_BISA_DILUNASI, true)
            && $this->sisaTagihan($penjualan) > 0;
    }

    /**
     * Proses pelunasan penuh terhadap sebuah nota.
     *
     * Mendukung TUNAI, TRANSFER, dan CAMPUR (split tunai + transfer).
     * Untuk CAMPUR, Piutang Usaha akan tercatat di 2 baris jurnal terpisah
     * (1 dari kitab tunai, 1 dari kitab bank) namun tetap dalam 1 noJurnal
     * yang sama — lihat JurnalPenjualanPelunasanService::buatJurnalPelunasanCampur().
     *
     * PENTING: Pelunasan WAJIB sekaligus penuh (tidak boleh dicicil) untuk
     * SEMUA jenis transaksi (COD maupun DP). Nominal yang diinput harus
     * pas menutup sisa tagihan, tidak boleh kurang.
     *
     * Khusus nota jenis DP: pelunasan penuh ini juga memicu reklasifikasi
     * akun Uang Muka Pelanggan -> Piutang Usaha, yang cuma valid terjadi
     * sekali, pas piutang benar-benar tuntas.
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
     * @throws InvalidArgumentException Jika input tidak valid.
     * @throws RuntimeException Jika nota tidak memenuhi syarat, atau rekening tidak terpetakan ke kitab manapun.
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

            // Pelunasan wajib sekaligus penuh (tidak bisa dicicil/parsial),
            // berlaku untuk semua jenis transaksi (COD maupun DP). Nominal
            // harus pas menutup sisa tagihan, tidak boleh kurang atau lebih.
            if ($nominal !== $sisa) {
                throw new InvalidArgumentException(
                    'Nominal pelunasan harus pas melunasi sisa tagihan (sisa: Rp '.number_format($sisa).'), tidak bisa dicicil.'
                );
            }

            // DP awal = jumlah yang sudah dibayar SEBELUM pelunasan ini (uang
            // muka yang diterima saat nota dibuat). Diambil sebelum $nota->bayar
            // diupdate di bawah, karena DP selalu lunas sekaligus.
            $dpAwal = $nota->jenis_transaksi === 'DP' ? (int) $nota->bayar : null;

            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            if (in_array($metode, [self::METODE_TRANSFER, self::METODE_CAMPUR], true) && ! $rekening) {
                throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
            }

            $nominalTunaiCampur = 0;
            $nominalTransferCampur = 0;

            if ($metode === self::METODE_CAMPUR) {
                $nominalTunaiCampur = (int) ($payload['nominal_tunai'] ?? 0);
                $nominalTransferCampur = (int) ($payload['nominal_transfer'] ?? 0);

                if ($nominalTunaiCampur <= 0 || $nominalTransferCampur <= 0) {
                    throw new InvalidArgumentException(
                        'Untuk metode Tunai & Transfer, nominal tunai dan nominal transfer harus sama-sama lebih dari 0. '.
                        'Kalau salah satunya 0, gunakan metode Tunai atau Transfer saja.'
                    );
                }

                if (($nominalTunaiCampur + $nominalTransferCampur) !== $nominal) {
                    throw new InvalidArgumentException('Nominal tunai + nominal transfer tidak sama dengan total nominal pelunasan.');
                }
            }

            $tambahTunai = match ($metode) {
                self::METODE_TUNAI => $nominal,
                self::METODE_CAMPUR => $nominalTunaiCampur,
                default => 0,
            };

            $tambahTransfer = match ($metode) {
                self::METODE_TRANSFER => $nominal,
                self::METODE_CAMPUR => $nominalTransferCampur,
                default => 0,
            };

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

            // Karena nominal sudah divalidasi === $sisa di atas, setelah baris
            // ini nota->bayar pasti >= nota->total, jadi selalu jadi LUNAS.
            if ((int) $nota->bayar >= (int) $nota->total) {
                $nota->status_transaksi = 'LUNAS';
            }

            $nota->validated_by = Auth::id();
            $nota->save();

            match ($metode) {
                self::METODE_TUNAI => $this->jurnalService->buatJurnalPelunasanTunai(
                    nota: $nota,
                    userId: (int) Auth::id(),
                    nominal: (float) $nominal,
                    dpAwal: $dpAwal,
                ),
                self::METODE_TRANSFER => $this->jurnalService->buatJurnalPelunasanTransfer(
                    nota: $nota,
                    userId: (int) Auth::id(),
                    nominal: (float) $nominal,
                    rekening: $rekening,
                    dpAwal: $dpAwal,
                ),
                self::METODE_CAMPUR => $this->jurnalService->buatJurnalPelunasanCampur(
                    nota: $nota,
                    userId: (int) Auth::id(),
                    nominalTunai: (float) $nominalTunaiCampur,
                    nominalTransfer: (float) $nominalTransferCampur,
                    rekening: $rekening,
                    dpAwal: $dpAwal,
                ),
            };

            // TODO: catat baris riwayat pelunasan di sini setelah tabel
            // `pelunasan_penjualans` difinalkan (nota_id, nominal, metode, bank, user_id, dst).

            return $nota->fresh();
        });
    }
}
