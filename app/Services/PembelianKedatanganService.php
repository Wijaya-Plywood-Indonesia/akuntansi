<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\PembelianMetodePembayaran;
use App\Models\RekeningPerusahaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Menu "Kedatangan Barang & Pelunasan Hutang" — pasangan dari "Pelunasan" di
 * sisi Penjualan.
 *
 *   - BAYAR_DIMUKA : sudah lunas 100% sejak awal. Barang BELUM diakui.
 *                    Aksi: "Konfirmasi Barang Datang" (tanpa nominal).
 *   - DP           : boleh belum lunas. Barang BELUM diakui. Aksi: "Tambah
 *                    DP" (nominal bebas, boleh berkali-kali) dan/atau
 *                    "Konfirmasi Barang Datang" (WAJIB pas menutup sisa).
 *   - NORMAL       : barang & Utang Usaha PENUH SUDAH diakui sejak validasi
 *                    awal (lihat JurnalPembelianTriplekService::
 *                    buatJurnalPembelian()). Yang ditunggu di sini MURNI
 *                    pelunasan uangnya (jatuh tempo) — beda dari 2 jenis di
 *                    atas yang menunggu barangnya. Aksi: "Bayar Hutang"
 *                    (nominal bebas, boleh dicicil sampai sisa = 0).
 */
class PembelianKedatanganService
{
    public function __construct(
        private readonly JurnalPembelianTriplekService $jurnalService,
    ) {}

    public function queryMenungguKedatangan(?string $search = null): Builder
    {
        return Pembelian::query()
            ->whereNotNull('validated_by')
            ->where('status', '!=', Pembelian::STATUS_BATAL)
            ->where(function (Builder $query) {
                $query
                    // BAYAR_DIMUKA/DP: menunggu barang datang.
                    ->where(function (Builder $q) {
                        $q->whereIn('jenis_pembayaran', Pembelian::JENIS_BUTUH_KONFIRMASI_BARANG)
                            ->whereNull('barang_diterima_at');
                    })
                    // NORMAL: barang sudah diakui, menunggu hutang dilunasi.
                    ->orWhere(function (Builder $q) {
                        $q->where('jenis_pembayaran', Pembelian::JENIS_NORMAL)
                            ->whereIn('status', [Pembelian::STATUS_HUTANG, Pembelian::STATUS_CICILAN]);
                    });
            })
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('nomor_nota', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            // Yang paling BARU masuk antrian (baru divalidasi / baru dibuat)
            // tampil paling atas. Sengaja TIDAK sort by 'tanggal' transaksi
            // (itu bisa di-mundur-kan manual oleh kasir saat input, jadi
            // tidak mencerminkan urutan "baru masuk" yang sebenarnya).
            ->orderByDesc('tanggal_validasi')
            ->orderByDesc('id');
    }

    public function getMenungguKedatangan(?string $search = null, int $limit = 20): Collection
    {
        return $this->queryMenungguKedatangan($search)->limit($limit)->get();
    }

    public function bisaDikonfirmasi(Pembelian $pembelian): bool
    {
        return $pembelian->menungguKedatanganBarang();
    }

    public function bisaTambahDp(Pembelian $pembelian): bool
    {
        return $pembelian->bisaTambahDp();
    }

    public function bisaBayarHutang(Pembelian $pembelian): bool
    {
        return $pembelian->bisaBayarHutang();
    }

    /**
     * Aksi "Bayar Hutang" — khusus NORMAL, dipanggil kapan saja setelah
     * validasi, nominal BEBAS (boleh dicicil, tidak wajib langsung lunas).
     * Barang TIDAK disentuh sama sekali di sini (sudah diakui dari awal).
     *
     * @param  array{nominal: int|float, payment_method: string, rekening_perusahaan_id?: int|null, reference_number?: string|null, catatan?: string|null}  $payload
     *
     * @throws InvalidArgumentException Jika input tidak valid.
     * @throws RuntimeException Jika nota tidak memenuhi syarat.
     */
    public function bayarHutang(Pembelian $pembelian, array $payload, int $userId): Pembelian
    {
        $nominal = (float) ($payload['nominal'] ?? 0);
        $metode = $payload['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;

        return DB::transaction(function () use ($pembelian, $payload, $nominal, $metode, $userId) {
            /** @var Pembelian $data */
            $data = Pembelian::query()->lockForUpdate()->findOrFail($pembelian->id);

            if (! $data->bisaBayarHutang()) {
                throw new RuntimeException(
                    'Nota ini bukan jenis NORMAL yang masih punya hutang, atau sudah dibatalkan.'
                );
            }

            if ($nominal <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
            }

            $sisa = $data->sisaTagihan();

            if ($nominal > $sisa) {
                throw new InvalidArgumentException(
                    'Nominal pembayaran tidak boleh melebihi sisa hutang (sisa: Rp '.number_format($sisa).').'
                );
            }

            if ($metode === PembelianMetodePembayaran::METODE_TRANSFER && empty($payload['rekening_perusahaan_id'])) {
                throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
            }

            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            $bayar = PembelianMetodePembayaran::create([
                'pembelian_id'           => $data->id,
                'created_by'             => $userId,
                'tanggal_bayar'          => now(),
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? 'Pelunasan hutang (jatuh tempo)',
            ]);

            $this->jurnalService->buatJurnalBayarHutang($data, $bayar, $userId);

            $totalDibayar = $data->totalSudahDibayar();
            $data->update([
                'status' => $totalDibayar >= (float) $data->grand_total
                    ? Pembelian::STATUS_LUNAS
                    : Pembelian::STATUS_CICILAN,
            ]);

            return $data->fresh();
        });
    }

    /**
     * Aksi "Tambah DP" — hanya untuk nota jenis DP yang sudah divalidasi dan
     * belum diterima barangnya. Boleh dipanggil berkali-kali, nominal BEBAS
     * (boleh kurang dari sisa tagihan, tidak wajib langsung lunas).
     *
     * @param  array{
     *     nominal: int|float,
     *     payment_method: string,
     *     rekening_perusahaan_id?: int|null,
     *     reference_number?: string|null,
     *     catatan?: string|null,
     * }  $payload
     *
     * @throws InvalidArgumentException Jika input tidak valid.
     * @throws RuntimeException Jika nota tidak memenuhi syarat untuk ditambah DP.
     */
    public function tambahDp(Pembelian $pembelian, array $payload, int $userId): Pembelian
    {
        $nominal = (float) ($payload['nominal'] ?? 0);
        $metode = $payload['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;

        return DB::transaction(function () use ($pembelian, $payload, $nominal, $metode, $userId) {
            /** @var Pembelian $data */
            $data = Pembelian::query()->lockForUpdate()->findOrFail($pembelian->id);

            if (! $data->bisaTambahDp()) {
                throw new RuntimeException(
                    'Nota ini bukan jenis DP yang bisa ditambah cicilan, sudah lunas, '.
                    'atau barangnya sudah pernah dikonfirmasi datang.'
                );
            }

            if ($nominal <= 0) {
                throw new InvalidArgumentException('Nominal DP harus lebih dari 0.');
            }

            $sisa = $data->sisaTagihan();

            if ($nominal > $sisa) {
                throw new InvalidArgumentException(
                    'Nominal DP tidak boleh melebihi sisa tagihan (sisa: Rp '.number_format($sisa).').'
                );
            }

            if ($metode === PembelianMetodePembayaran::METODE_TRANSFER && empty($payload['rekening_perusahaan_id'])) {
                throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
            }

            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            $bayar = PembelianMetodePembayaran::create([
                'pembelian_id'           => $data->id,
                'created_by'             => $userId,
                'tanggal_bayar'          => now(),
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? null,
            ]);

            $this->jurnalService->buatJurnalTambahDp($data, $bayar, $userId);

            // Status pembayaran ikut diperbarui (hutang/cicilan/lunas) supaya
            // konsisten dengan status di menu lain — walaupun untuk DP,
            // "lunas" secara status TIDAK sama dengan barang sudah diterima
            // (barang & pembalikan DP baru terjadi saat Konfirmasi Barang
            // Datang).
            $totalDibayar = $data->totalSudahDibayar();
            $data->update([
                'status' => $totalDibayar >= (float) $data->grand_total
                    ? Pembelian::STATUS_LUNAS
                    : Pembelian::STATUS_CICILAN,
            ]);

            return $data->fresh();
        });
    }

    /**
     * Proses konfirmasi barang datang: posting jurnal, lalu tandai
     * barang_diterima_at.
     *
     * Untuk BAYAR_DIMUKA: $payload diabaikan (sudah lunas 100% sejak awal).
     * Untuk DP: $payload WAJIB berisi nominal pelunasan sisa yang PAS
     * menutup sisaTagihan() (tidak boleh dicicil lagi di titik ini), beserta
     * metode pembayarannya.
     *
     * @param  array{
     *     nominal?: int|float,
     *     payment_method?: string,
     *     rekening_perusahaan_id?: int|null,
     *     reference_number?: string|null,
     *     catatan?: string|null,
     * }  $payload
     *
     * @throws RuntimeException Kalau pembelian tidak memenuhi syarat.
     * @throws InvalidArgumentException Kalau input pelunasan sisa (khusus DP) tidak valid.
     */
    public function konfirmasiBarangDatang(Pembelian $pembelian, int $userId, array $payload = []): Pembelian
    {
        return DB::transaction(function () use ($pembelian, $userId, $payload) {
            /** @var Pembelian $data */
            $data = Pembelian::query()->lockForUpdate()->findOrFail($pembelian->id);

            if (! $data->menungguKedatanganBarang()) {
                throw new RuntimeException(
                    'Pembelian ini bukan jenis BAYAR_DIMUKA/DP yang menunggu kedatangan barang, '.
                    'atau barangnya sudah pernah dikonfirmasi datang sebelumnya.'
                );
            }

            if ($data->jenis_pembayaran === Pembelian::JENIS_DP) {
                $this->konfirmasiBarangDatangDp($data, $payload, $userId);
            } else {
                // BAYAR_DIMUKA: sudah lunas 100% sejak awal, tidak perlu
                // pelunasan sisa apapun.
                $this->jurnalService->buatJurnalKedatanganBarangDimuka($data, $userId);
            }

            $data->update([
                'barang_diterima_at' => now(),
                'barang_diterima_by' => $userId,
                'status'             => Pembelian::STATUS_LUNAS,
            ]);

            return $data->fresh();
        });
    }

    /**
     * Sub-alur DP dari konfirmasiBarangDatang(): validasi nominal pelunasan
     * sisa WAJIB pas, catat sebagai PembelianMetodePembayaran baru, lalu
     * posting jurnal kedatangan barang + pelunasan sisa (yang juga sekaligus
     * membalik SELURUH Uang Muka Pembelian yang terkumpul sebelumnya).
     */
    private function konfirmasiBarangDatangDp(Pembelian $data, array $payload, int $userId): void
    {
        $sisa = $data->sisaTagihan();
        $nominal = (float) ($payload['nominal'] ?? 0);
        $metode = $payload['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;

        // DP yang SUDAH terkumpul sebelum pelunasan sisa ini — dihitung
        // SEBELUM baris PembelianMetodePembayaran baru dibuat, karena inilah
        // nominal yang harus dibalik (dihabiskan) dari akun Uang Muka
        // Pembelian di jurnal kedatangan barang.
        $dpSudahDibayar = $data->totalSudahDibayar();

        if ($sisa > 0) {
            // Masih ada sisa tagihan -> WAJIB dilunasi PAS sekarang (tidak
            // boleh dicicil lagi di titik konfirmasi barang datang).
            if ($nominal <= 0) {
                throw new InvalidArgumentException(
                    'Nota DP ini masih punya sisa tagihan Rp '.number_format($sisa).
                    '. Isi nominal pelunasan sisa untuk melanjutkan.'
                );
            }

            if (round($nominal, 2) !== round($sisa, 2)) {
                throw new InvalidArgumentException(
                    'Nominal pelunasan sisa harus PAS menutup sisa tagihan (sisa: Rp '.number_format($sisa).
                    '), tidak bisa dicicil lagi di titik ini.'
                );
            }

            if ($metode === PembelianMetodePembayaran::METODE_TRANSFER && empty($payload['rekening_perusahaan_id'])) {
                throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
            }
        } else {
            // Sisa tagihan sudah 0 (DP sebelumnya sudah menutup 100% grand
            // total) -> tidak perlu pelunasan sisa apapun.
            $nominal = 0;
        }

        $rekening = ! empty($payload['rekening_perusahaan_id'])
            ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
            : null;

        // Tetap buat 1 baris PembelianMetodePembayaran (walau nominal 0)
        // supaya JurnalPembelianTriplekService::buatJurnalKedatanganBarangDp()
        // punya rekening/metode pembayaran untuk resolve kode kitabnya.
        $bayarSisa = PembelianMetodePembayaran::create([
            'pembelian_id'           => $data->id,
            'created_by'             => $userId,
            'tanggal_bayar'          => now(),
            'amount'                 => $nominal,
            'payment_method'         => $metode,
            'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
            'reference_number'       => $payload['reference_number'] ?? null,
            'catatan'                => $payload['catatan'] ?? 'Pelunasan sisa saat barang datang (DP)',
        ]);

        $this->jurnalService->buatJurnalKedatanganBarangDp($data, $dpSudahDibayar, $bayarSisa, $userId);
    }
}