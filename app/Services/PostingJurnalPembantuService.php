<?php

namespace App\Services;

use App\Models\JurnalLampiran;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostingJurnalPembantuService
{
    /** Kelompok jurnal yang bisa difilter saat posting batch. */
    public const KELOMPOK_AKUNTANSI = 'akuntansi';

    public const KELOMPOK_PRODUKSI = 'produksi';

    public const KELOMPOK_KAYU_MASUK = 'kayu_masuk';

    /**
     * Posting satu nomor jurnal pembantu ke Jurnal Umum.
     *
     * @throws \Exception
     */
    public function postingByNomorJurnal(int|string $nomorJurnal, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id() ?? 1;

        DB::transaction(function () use ($nomorJurnal, $userId) {
            $headers = JurnalPembantuHeader::query()
                ->with([
                    'items' => fn ($q) => $q->where('status', true),
                ])
                ->where('jurnal', $nomorJurnal)
                ->orderBy('map')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($headers->isEmpty()) {
                throw new \Exception("Data jurnal No. {$nomorJurnal} tidak ditemukan.");
            }

            $adaNonDraft = $headers
                ->where('status', '!=', JurnalPembantuHeader::STATUS_DRAFT)
                ->count();

            if ($adaNonDraft > 0) {
                throw new \Exception("Sebagian atau seluruh jurnal No. {$nomorJurnal} sudah diposting.");
            }

            $totalDebit = $headers->where('map', 'd')->sum('total_nilai');
            $totalKredit = $headers->where('map', 'k')->sum('total_nilai');

            if (abs($totalDebit - $totalKredit) > 0.0001) {
                throw new \Exception("Jurnal No. {$nomorJurnal} tidak balance (Debit: {$totalDebit}, Kredit: {$totalKredit}).");
            }

            // Nomor jurnal final = nomor terbesar di Jurnal Umum + 1.
            // Nomor di Jurnal Pembantu hanya dipakai sebagai kunci pengambilan data,
            // bukan sebagai nomor di Jurnal Umum.
            $maxJU = (int) (JurnalUmum::query()->lockForUpdate()->max('jurnal') ?? 0);
            $nomorFinal = $maxJU + 1;

            if ($nomorFinal !== (int) $nomorJurnal) {
                JurnalPembantuHeader::query()
                    ->where('jurnal', $nomorJurnal)
                    ->update(['jurnal' => $nomorFinal]);

                // Lampiran foto ikut dipindah ke nomor baru agar tidak nyangkut di nomor lama.
                JurnalLampiran::pindahkanKe((int) $nomorJurnal, $nomorFinal, $userId);
            }

            $namaGlobal = null;
            $noDokumenGlobal = null;
            $pihakIdGlobal = null;

            foreach ($headers as $h) {
                if (empty($noDokumenGlobal) && ! empty($h->no_dokumen)) {
                    $noDokumenGlobal = $h->no_dokumen;
                }

                if (empty($namaGlobal)) {
                    $parts = explode('|', $h->keterangan);
                    $parsedNama = isset($parts[2]) ? trim($parts[2]) : null;

                    if (! empty($parsedNama)) {
                        $namaGlobal = $parsedNama;
                    } else {
                        $firstItem = $h->items->first();
                        if ($firstItem) {
                            $namaGlobal = $firstItem->nama_pihak ?: $firstItem->nama_barang;
                        }
                    }
                }

                if (empty($pihakIdGlobal)) {
                    $pihakIdGlobal = $h->items->first()?->pihak_id;
                }
            }

            if (empty($namaGlobal)) {
                $namaGlobal = JurnalPembantuHeader::JENIS[$headers->first()->jenis_transaksi] ?? null;
            }

            foreach ($headers as $header) {
                // Modul produksi/import/dsb: nilai baris diambil dari kolom `jumlah`
                // (sama persis dengan JALUR 1 di JurnalPembantuHeader::recalculateTotalNilai),
                // supaya Jurnal Umum selalu sama dengan total_nilai header.
                $pakaiJumlah = JurnalPembantuHeader::pakaiJumlahLangsung($header->modul_asal);

                $itemsPerBarang = $header->items->groupBy('id_barang');

                foreach ($itemsPerBarang as $idBarang => $items) {
                    $idBarangFinal = $idBarang !== '' ? $idBarang : null;

                    $totalBanyak = (float) $items->sum('banyak');
                    $totalM3 = (float) $items->sum('m3');

                    $totalNilaiGrup = $pakaiJumlah
                        ? (float) $items->sum('jumlah')
                        : (float) $items->sum(function ($item) {
                            return match ($item->hit_kbk) {
                                'k' => (float) $item->harga * (float) ($item->m3 ?? 0) * 1000,
                                'm' => (float) $item->harga * (float) ($item->m3 ?? 0),
                                'b' => (float) $item->harga * (float) ($item->banyak ?? 0),
                                null, '' => $item->banyak > 0 ? (float) $item->harga * (float) $item->banyak : (float) $item->harga,
                                default => (float) $item->harga * (float) ($item->banyak ?? 0),
                            };
                        });

                    $firstItem = $items->first();
                    $itemHitKbk = $firstItem?->hit_kbk;

                    $hitKbk = '';
                    $prefix = substr($header->no_akun, 0, 3);
                    $isCashOrPayment = in_array($prefix, ['110', '111', '112', '113', '114', '210', '220', '230']);

                    if (! $isCashOrPayment) {
                        $hitKbk = 'b';

                        if ($firstItem) {
                            $b = (float) $firstItem->banyak;
                            $m = (float) $firstItem->m3;
                            $h = (float) $firstItem->harga;
                            $j = (float) $firstItem->jumlah;

                            if ($m > 0 && abs($j - ($m * $h)) < 0.01) {
                                $hitKbk = 'm';
                            } elseif ($b > 0 && abs($j - ($b * $h)) < 0.01) {
                                $hitKbk = 'b';
                            }
                        }
                    }

                    if ($itemHitKbk === 'k') {
                        $hitKbk = 'm';
                    } elseif ($itemHitKbk === 'b') {
                        $hitKbk = 'b';
                    }

                    $m3Final = $totalM3 > 0 ? $totalM3 : null;
                    $banyakFinal = $totalBanyak > 0 ? $totalBanyak : null;

                    if ($hitKbk === 'm') {
                        $hargaFinal = $totalM3 > 0 ? ($totalNilaiGrup / $totalM3) : $totalNilaiGrup;
                    } elseif ($hitKbk === 'b') {
                        if (! $banyakFinal && ! $isCashOrPayment) {
                            $banyakFinal = 1;
                        }
                        $hargaFinal = $totalBanyak > 0 ? ($totalNilaiGrup / $totalBanyak) : $totalNilaiGrup;
                    } else {
                        $hargaFinal = $totalNilaiGrup;
                    }

                    // Jika hit_kbk 'b' atau 'm' menghasilkan selisih pembulatan rupiah vs totalNilaiGrup
                    // (misal karena beberapa item barang digabung di header yang sama dengan harga satuan beda),
                    // kosongkan hit_kbk dan gunakan harga = totalNilaiGrup langsung agar nilai rupiah
                    // Jurnal Umum 100% konsisten dengan Jurnal Pembantu dan tidak pernah selisih/unbalanced.
                    // Kolom banyak & m3 tetap disimpan sebagai catatan kuantitas fisik.
                    if (! $pakaiJumlah && in_array($hitKbk, ['b', 'm'])) {
                        $qtyCheck = $hitKbk === 'b' ? ($banyakFinal ?? 0) : ($m3Final ?? 0);
                        $nilaiHitung = round($qtyCheck * round($hargaFinal, 2), 2);
                        if (abs($nilaiHitung - round($totalNilaiGrup, 2)) > 0.01) {
                            $hitKbk = '';
                            $hargaFinal = $totalNilaiGrup;
                        }
                    }

                    if ($pakaiJumlah) {
                        // Nilai sudah final dari `jumlah`. hit_kbk kosong =>
                        // JurnalUmum::getNilaiAttribute memakai harga langsung,
                        // jadi tidak ada perkalian ulang dan tidak ada pembulatan per-satuan.
                        // banyak & m3 tetap disimpan sebagai informasi saja.
                        $hitKbk = '';
                        $hargaFinal = $totalNilaiGrup;
                    }

                    JurnalUmum::create([
                        'tgl' => $header->tgl_transaksi
                            ? $header->tgl_transaksi->format('Y-m-d')
                            : now()->format('Y-m-d'),
                        'jurnal' => $nomorFinal,
                        'no_akun' => $header->no_akun,
                        'nama_akun' => $header->nama_akun,
                        'no-dokumen' => $noDokumenGlobal,
                        'nama' => $namaGlobal,
                        'keterangan' => $header->keterangan,
                        'id_barang' => $idBarangFinal,
                        'id_pembeli' => $pihakIdGlobal,
                        'banyak' => $banyakFinal !== null ? round($banyakFinal, 4) : null,
                        'm3' => $m3Final !== null ? round($m3Final, 4) : null,
                        'harga' => round($hargaFinal, 2),
                        'hit_kbk' => $hitKbk,
                        'map' => strtolower($header->map),
                    ]);
                }

                $header->update([
                    'status' => JurnalPembantuHeader::STATUS_DIPOSTING,
                    'diposting_oleh' => $userId,
                    'tgl_posting' => now(),
                ]);
            }

            // Pengaman: pastikan Jurnal Umum hasil posting benar-benar balance.
            // Kalau tidak, seluruh transaksi di-rollback (status header kembali draft).
            $rowsJU = JurnalUmum::query()->where('jurnal', $nomorFinal)->get();
            $selisih = round($rowsJU->sum('debit') - $rowsJU->sum('kredit'), 2);

            if (abs($selisih) > 1) {
                throw new \Exception(
                    "Jurnal Umum No. {$nomorFinal} tidak balance setelah posting (selisih: {$selisih}). Posting dibatalkan."
                );
            }
        }, 5);
    }

    /**
     * Posting batch jurnal berdasarkan periode hari (1 hari, 7 hari, 30 hari / 1 bulan).
     * Urutan posting dijamin dari nomor jurnal terkecil ke terbesar (orderBy('jurnal', 'asc')).
     *
     * @param  int  $jumlahHari  Jumlah hari ke belakang (1 = hari ini, 7 = 7 hari terakhir, 30 = 1 bulan terakhir)
     * @param  Carbon|null  $referenceDate  Tanggal acuan (default hari ini)
     * @param  string|null  $kelompok  null = semua, atau 'akuntansi' | 'produksi' | 'kayu_masuk'
     * @return array{total_jurnal: int, sukses: int, gagal: int, errors: array<string>}
     */
    public function postingBatchPeriode(int $jumlahHari, ?Carbon $referenceDate = null, ?int $userId = null, ?string $kelompok = null): array
    {
        $userId = $userId ?? Auth::id() ?? 1;
        $referenceDate = $referenceDate ? $referenceDate->copy() : now();
        $startDate = $referenceDate->copy()->subDays($jumlahHari - 1)->startOfDay();
        $endDate = $referenceDate->copy()->endOfDay();

        return $this->postingBatchRentang($startDate, $endDate, $userId, $kelompok);
    }

    /**
     * Posting batch jurnal berdasarkan rentang tanggal.
     * Mengurutkan nomor jurnal dari yang terkecil (asc).
     *
     * @param  string|null  $kelompok  null = semua, atau 'akuntansi' | 'produksi' | 'kayu_masuk'
     * @return array{total_jurnal: int, sukses: int, gagal: int, errors: array<string>}
     */
    public function postingBatchRentang(Carbon $startDate, Carbon $endDate, ?int $userId = null, ?string $kelompok = null): array
    {
        $userId = $userId ?? Auth::id() ?? 1;

        // Ambil nomor-nomor jurnal berstatus draft pada rentang tanggal tersebut
        // (dan kelompok yang dipilih), diurutkan dari nomor jurnal terkecil (ASC)
        $query = JurnalPembantuHeader::query()
            ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
            ->whereBetween('tgl_transaksi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        $nomorJurnals = $this->filterKelompok($query, $kelompok)
            ->orderBy('jurnal', 'asc')
            ->distinct()
            ->pluck('jurnal')
            ->toArray();

        $sukses = 0;
        $gagal = 0;
        $errors = [];

        foreach ($nomorJurnals as $nomorJurnal) {
            try {
                $this->postingByNomorJurnal($nomorJurnal, $userId);
                $sukses++;
            } catch (Throwable $e) {
                $gagal++;
                $errors[] = "Jurnal #{$nomorJurnal}: ".$e->getMessage();
            }
        }

        return [
            'total_jurnal' => count($nomorJurnals),
            'sukses' => $sukses,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }

    /**
     * Batasi query header ke satu kelompok. Aturannya sama dengan tab di
     * halaman Jurnal Pembantu Headers.
     */
    private function filterKelompok(Builder $query, ?string $kelompok): Builder
    {
        return match ($kelompok) {
            self::KELOMPOK_KAYU_MASUK => $query->where('modul_asal', 'pembelian_kayu'),

            self::KELOMPOK_PRODUKSI => $query->where(
                fn (Builder $q) => $q->where('modul_asal', 'produksi')
                    ->orWhere('modul_asal', 'like', 'produksi\\_%')
                    ->orWhere('modul_asal', 'rotary')
            ),

            self::KELOMPOK_AKUNTANSI => $query->where(
                fn (Builder $q) => $q->whereNull('modul_asal')->orWhere(
                    fn (Builder $x) => $x->where('modul_asal', '!=', 'produksi')
                        ->where('modul_asal', 'not like', 'produksi\\_%')
                        ->where('modul_asal', '!=', 'rotary')
                        ->where('modul_asal', '!=', 'pembelian_kayu')
                )
            ),

            default => $query,
        };
    }
}
