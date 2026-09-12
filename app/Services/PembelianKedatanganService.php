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
                    })
                    // DP: barang SUDAH datang (lewat konfirmasi "belum
                    // lunas") tapi sisa tagihan masih perlu dicicil.
                    ->orWhere(function (Builder $q) {
                        $q->where('jenis_pembayaran', Pembelian::JENIS_DP)
                            ->whereNotNull('barang_diterima_at')
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

    public function bisaBayarHutangDp(Pembelian $pembelian): bool
    {
        return $pembelian->bisaBayarHutangDp();
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
        $tanggal = $this->resolveTanggal($payload['tanggal'] ?? null);

        return DB::transaction(function () use ($pembelian, $payload, $nominal, $metode, $userId, $tanggal) {
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
                'tanggal_bayar'          => $tanggal,
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? 'Pelunasan hutang (jatuh tempo)',
            ]);

            $this->jurnalService->buatJurnalBayarHutang($data, $bayar, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);

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
     * Aksi "Bayar Hutang" — khusus DP yang barangnya SUDAH datang (lewat
     * "Konfirmasi Barang Datang" versi belum-lunas), nominal BEBAS (boleh
     * dicicil). Di cicilan yang bikin sisaTagihan() jadi 0, jurnal service
     * otomatis ikut membalik Uang Muka Pembelian yang sudah terkumpul —
     * lihat JurnalPembelianTriplekService::buatJurnalBayarHutangDp().
     *
     * @param  array{nominal: int|float, payment_method: string, rekening_perusahaan_id?: int|null, reference_number?: string|null, catatan?: string|null}  $payload
     *
     * @throws InvalidArgumentException Jika input tidak valid.
     * @throws RuntimeException Jika nota tidak memenuhi syarat.
     */
    public function bayarHutangDp(Pembelian $pembelian, array $payload, int $userId): Pembelian
    {
        $nominal = (float) ($payload['nominal'] ?? 0);
        $metode = $payload['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;
        $tanggal = $this->resolveTanggal($payload['tanggal'] ?? null);

        return DB::transaction(function () use ($pembelian, $payload, $nominal, $metode, $userId, $tanggal) {
            /** @var Pembelian $data */
            $data = Pembelian::query()->lockForUpdate()->findOrFail($pembelian->id);

            if (! $data->bisaBayarHutangDp()) {
                throw new RuntimeException(
                    'Nota ini bukan jenis DP yang barangnya sudah datang dan masih punya sisa hutang, '.
                    'atau sudah dibatalkan.'
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
                'tanggal_bayar'          => $tanggal,
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? 'Pelunasan hutang (DP, setelah barang datang)',
            ]);

            // $bayar sudah tersimpan -> sisaTagihan() di dalam service jurnal
            // sudah mencerminkan pembayaran ini, jadi bisa dipakai untuk
            // deteksi "apakah ini cicilan terakhir".
            $this->jurnalService->buatJurnalBayarHutangDp($data, $bayar, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);

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
        $tanggal = $this->resolveTanggal($payload['tanggal'] ?? null);

        return DB::transaction(function () use ($pembelian, $payload, $nominal, $metode, $userId, $tanggal) {
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
                'tanggal_bayar'          => $tanggal,
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? null,
            ]);

            $this->jurnalService->buatJurnalTambahDp($data, $bayar, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);

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
        $tanggal = $this->resolveTanggal($payload['tanggal'] ?? null);

        return DB::transaction(function () use ($pembelian, $userId, $payload, $tanggal) {
            /** @var Pembelian $data */
            $data = Pembelian::query()->lockForUpdate()->findOrFail($pembelian->id);

            if (! $data->menungguKedatanganBarang()) {
                throw new RuntimeException(
                    'Pembelian ini bukan jenis BAYAR_DIMUKA/DP yang menunggu kedatangan barang, '.
                    'atau barangnya sudah pernah dikonfirmasi datang sebelumnya.'
                );
            }

            if ($data->jenis_pembayaran === Pembelian::JENIS_DP) {
                $this->konfirmasiBarangDatangDp($data, $payload, $userId, $tanggal);
            } else {
                // BAYAR_DIMUKA: sudah lunas 100% sejak awal, tidak perlu
                // pelunasan sisa apapun.
                $this->jurnalService->buatJurnalKedatanganBarangDimuka($data, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);
            }

            // Untuk DP, barang datang TIDAK selalu berarti lunas lagi
            // (bisa "barang datang belum lunas" / "barang datang + bayar
            // sebagian") — status akhir mengikuti sisaTagihan() yang FRESH
            // (query ke metodePembayarans, sudah termasuk baris pembayaran
            // yang baru saja dibuat di konfirmasiBarangDatangDp() kalau
            // ada). Untuk BAYAR_DIMUKA tetap selalu LUNAS seperti semula.
            $data->update([
                'barang_diterima_at' => $tanggal,
                'barang_diterima_by' => $userId,
                'status'             => match (true) {
                    $data->sisaTagihan() <= 0 => Pembelian::STATUS_LUNAS,
                    $data->totalSudahDibayar() > 0 => Pembelian::STATUS_CICILAN,
                    default => Pembelian::STATUS_HUTANG,
                },
            ]);

            return $data->fresh();
        });
    }

    /**
     * Sub-alur DP dari konfirmasiBarangDatang(). Sekarang mendukung 3
     * kondisi nyata di lapangan, dibedakan dari nominal yang diisi user:
     *
     *   1. nominal = 0 (atau kosong)         -> "barang datang duluan,
     *      belum bayar apa-apa". Cuma akui Persediaan + PPN + Utang Usaha
     *      PENUH (kitab 'pembelian_down_payment_barang_datang_belum_lunas').
     *      Uang Muka Pembelian yang sudah terkumpul TIDAK disentuh, disimpan
     *      utuh untuk dipakai nanti di cicilan terakhir (lihat
     *      bayarHutangDp()).
     *
     *   2. 0 < nominal < sisa                -> "barang datang + bayar
     *      sebagian sekarang". Sama seperti kondisi 1, PLUS langsung
     *      posting 1 cicilan pelunasan (lewat
     *      JurnalPembelianTriplekService::buatJurnalBayarHutangDp(), method
     *      yang sama dipakai menu "Bayar Hutang" belakangan) untuk nominal
     *      yang dibayar sekarang.
     *
     *   3. nominal >= sisa (atau sisa sudah 0)  -> "barang datang SEKALIGUS
     *      lunas" (kasus lama, tidak berubah). Pakai kitab gabungan
     *      'pembelian_down_payment_barang_datang_*' yang langsung menutup
     *      semuanya (persediaan + PPN + kas + Uang Muka) dalam 1 jurnal.
     */
    private function konfirmasiBarangDatangDp(Pembelian $data, array $payload, int $userId, \Illuminate\Support\Carbon $tanggal): void
    {
        $sisa = $data->sisaTagihan();
        $nominal = (float) ($payload['nominal'] ?? 0);
        $metode = $payload['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;

        if ($nominal < 0) {
            throw new InvalidArgumentException('Nominal pembayaran tidak boleh negatif.');
        }

        if ($nominal > $sisa) {
            throw new InvalidArgumentException(
                'Nominal pembayaran tidak boleh melebihi sisa tagihan (sisa: Rp '.number_format($sisa).').'
            );
        }

        if ($nominal > 0 && $metode === PembelianMetodePembayaran::METODE_TRANSFER && empty($payload['rekening_perusahaan_id'])) {
            throw new InvalidArgumentException('Rekening perusahaan wajib dipilih untuk pembayaran transfer.');
        }

        $iniLangsungLunas = $sisa <= 0 || round($nominal, 2) === round($sisa, 2);

        if ($iniLangsungLunas) {
            // ── Kondisi 3: barang datang sekaligus lunas (kasus lama) ────
            // DP yang SUDAH terkumpul sebelum pelunasan sisa ini — dihitung
            // SEBELUM baris PembelianMetodePembayaran baru dibuat, karena
            // inilah nominal yang harus dibalik dari Uang Muka Pembelian.
            $dpSudahDibayar = $data->totalSudahDibayar();

            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            // Tetap buat 1 baris PembelianMetodePembayaran (walau nominal 0,
            // kalau DP sebelumnya sudah menutup 100%) supaya jurnal service
            // punya rekening/metode pembayaran untuk resolve kode kitabnya.
            $bayarSisa = PembelianMetodePembayaran::create([
                'pembelian_id'           => $data->id,
                'created_by'             => $userId,
                'tanggal_bayar'          => $tanggal,
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? 'Pelunasan sisa saat barang datang (DP)',
            ]);

            $this->jurnalService->buatJurnalKedatanganBarangDp($data, $dpSudahDibayar, $bayarSisa, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);

            return;
        }

        // ── Kondisi 1 & 2: barang datang duluan, belum lunas (nominal bisa
        //    0, atau sebagian) ──────────────────────────────────────────
        // Snapshot DP yang sudah terkumpul SAAT INI (sebelum baris
        // pembayaran sebagian di bawah, kalau ada, ditambahkan) — inilah
        // yang dibaca lagi nanti di cicilan terakhir untuk menutup Uang
        // Muka Pembelian.
        $dpTerkumpul = $data->totalSudahDibayar();

        $this->jurnalService->buatJurnalKedatanganBarangDpBelumLunas($data, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);
        $data->dp_terkumpul_saat_barang_datang = $dpTerkumpul;

        if ($nominal > 0) {
            // Kondisi 2: sekalian bayar sebagian saat ini juga.
            $rekening = ! empty($payload['rekening_perusahaan_id'])
                ? RekeningPerusahaan::find($payload['rekening_perusahaan_id'])
                : null;

            $bayarSebagian = PembelianMetodePembayaran::create([
                'pembelian_id'           => $data->id,
                'created_by'             => $userId,
                'tanggal_bayar'          => $tanggal,
                'amount'                 => $nominal,
                'payment_method'         => $metode,
                'rekening_perusahaan_id' => $metode === PembelianMetodePembayaran::METODE_TRANSFER ? $rekening?->id : null,
                'reference_number'       => $payload['reference_number'] ?? null,
                'catatan'                => $payload['catatan'] ?? 'Bayar sebagian saat barang datang (DP)',
            ]);

            // $sisa awal > $nominal (bukan pelunasan penuh, sudah dicek di
            // atas) -> setelah baris ini, sisaTagihan() masih > 0, jadi
            // buatJurnalBayarHutangDp() otomatis TIDAK membalik Uang Muka
            // Pembelian di sini (baru nanti di cicilan yang benar-benar
            // menutup sisa ke 0).
            $this->jurnalService->buatJurnalBayarHutangDp($data, $bayarSebagian, $userId, $tanggal->toDateString(), $payload['foto'] ?? null);
        }
    }

    /**
     * Ubah string tanggal dari form (mis. "2026-09-10") jadi Carbon yang
     * aman dipakai. Kalau kosong/tidak valid, fallback ke sekarang — supaya
     * form lama yang belum kirim tanggal (atau baru dipasang) tetap jalan
     * seperti sebelumnya, bukan malah error.
     */
    private function resolveTanggal(?string $tanggal): \Illuminate\Support\Carbon
    {
        if (! $tanggal) {
            return now();
        }

        try {
            return \Illuminate\Support\Carbon::parse($tanggal)->startOfDay()->setTimeFromTimeString(now()->format('H:i:s'));
        } catch (\Throwable) {
            return now();
        }
    }
}