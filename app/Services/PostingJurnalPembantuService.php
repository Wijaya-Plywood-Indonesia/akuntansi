<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostingJurnalPembantuService
{
    /**
     * Posting satu nomor jurnal pembantu ke Jurnal Umum.
     *
     * @param int|string $nomorJurnal
     * @param int|null $userId
     * @throws \Exception
     */
    public function postingByNomorJurnal(int|string $nomorJurnal, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id() ?? 1;

        DB::transaction(function () use ($nomorJurnal, $userId) {
            $headers = JurnalPembantuHeader::query()
                ->with([
                    'items' => fn($q) => $q->where('status', true)
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

            $nomorFinal = (int) $nomorJurnal;

            $sudahAda = JurnalUmum::query()
                ->where('jurnal', $nomorFinal)
                ->lockForUpdate()
                ->exists();

            if ($sudahAda) {
                $maxJU = (int) (JurnalUmum::query()->lockForUpdate()->max('jurnal') ?? 0);
                $maxJP = (int) (JurnalPembantuHeader::query()->lockForUpdate()->max('jurnal') ?? 0);

                $nomorFinal = max($maxJU, $maxJP) + 1;

                JurnalPembantuHeader::query()
                    ->where('jurnal', $nomorJurnal)
                    ->update(['jurnal' => $nomorFinal]);
            }

            $namaGlobal = null;
            $noDokumenGlobal = null;

            foreach ($headers as $h) {
                if (empty($noDokumenGlobal) && !empty($h->no_dokumen)) {
                    $noDokumenGlobal = $h->no_dokumen;
                }

                if (empty($namaGlobal)) {
                    $parts = explode('|', $h->keterangan);
                    $parsedNama = isset($parts[2]) ? trim($parts[2]) : null;

                    if (!empty($parsedNama)) {
                        $namaGlobal = $parsedNama;
                    } else {
                        $firstItem = $h->items->first();
                        if ($firstItem) {
                            $namaGlobal = $firstItem->nama_pihak ?: $firstItem->nama_barang;
                        }
                    }
                }
            }

            if (empty($namaGlobal)) {
                $namaGlobal = JurnalPembantuHeader::JENIS[$headers->first()->jenis_transaksi] ?? null;
            }

            foreach ($headers as $header) {
                $itemsPerBarang = $header->items->groupBy('id_barang');

                foreach ($itemsPerBarang as $idBarang => $items) {
                    $idBarangFinal = $idBarang !== '' ? $idBarang : null;

                    $totalBanyak = (float) $items->sum('banyak');
                    $totalM3 = (float) $items->sum('m3');
                    $totalNilaiGrup = (float) $items->sum(function ($item) {
                        return match ($item->hit_kbk) {
                            'k'      => (float)$item->harga * (float)($item->m3 ?? 0) * 1000,
                            'm'      => (float)$item->harga * (float)($item->m3 ?? 0),
                            'b'      => (float)$item->harga * (float)($item->banyak ?? 0),
                            null, '' => $item->banyak > 0 ? (float)$item->harga * (float)$item->banyak : (float)$item->harga,
                            default  => (float)$item->harga * (float)($item->banyak ?? 0),
                        };
                    });

                    $firstItem = $items->first();
                    $itemHitKbk = $firstItem?->hit_kbk;

                    $hitKbk = '';
                    $prefix = substr($header->no_akun, 0, 3);
                    $isCashOrPayment = in_array($prefix, ['110', '111', '112', '113', '114', '210', '220', '230']);

                    if (!$isCashOrPayment) {
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
                        if (!$banyakFinal && !$isCashOrPayment) $banyakFinal = 1;
                        $hargaFinal = $totalBanyak > 0 ? ($totalNilaiGrup / $totalBanyak) : $totalNilaiGrup;
                    } else {
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
        }, 5);
    }

    /**
     * Posting batch jurnal berdasarkan periode hari (1 hari, 7 hari, 30 hari / 1 bulan).
     * Urutan posting dijamin dari nomor jurnal terkecil ke terbesar (orderBy('jurnal', 'asc')).
     *
     * @param int $jumlahHari Jumlah hari ke belakang (1 = hari ini, 7 = 7 hari terakhir, 30 = 1 bulan terakhir)
     * @param Carbon|null $referenceDate Tanggal acuan (default hari ini)
     * @param int|null $userId
     * @return array{total_jurnal: int, sukses: int, gagal: int, errors: array<string>}
     */
    public function postingBatchPeriode(int $jumlahHari, ?Carbon $referenceDate = null, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id() ?? 1;
        $referenceDate = $referenceDate ? $referenceDate->copy() : now();
        $startDate = $referenceDate->copy()->subDays($jumlahHari - 1)->startOfDay();
        $endDate = $referenceDate->copy()->endOfDay();

        return $this->postingBatchRentang($startDate, $endDate, $userId);
    }

    /**
     * Posting batch jurnal berdasarkan rentang tanggal.
     * Mengurutkan nomor jurnal dari yang terkecil (asc).
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $userId
     * @return array{total_jurnal: int, sukses: int, gagal: int, errors: array<string>}
     */
    public function postingBatchRentang(Carbon $startDate, Carbon $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id() ?? 1;

        // Ambil nomor-nomor jurnal berstatus draft pada rentang tanggal tersebut
        // Diurutkan dari nomor jurnal terkecil (ASC)
        $nomorJurnals = JurnalPembantuHeader::query()
            ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
            ->whereBetween('tgl_transaksi', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
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
                $errors[] = "Jurnal #{$nomorJurnal}: " . $e->getMessage();
            }
        }

        return [
            'total_jurnal' => count($nomorJurnals),
            'sukses' => $sukses,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }
}
