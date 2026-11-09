<?php

namespace App\Services;

use App\Models\JurnalUmum;
use Illuminate\Support\Facades\DB;

/**
 * Alokasi nomor jurnal & No. JP berbasis tabel counter (row lock).
 *
 * CARA KERJA:
 * SELECT ... FOR UPDATE pada SATU baris counter. Lock baris ini ditahan
 * sampai transaksi commit/rollback, jadi request lain menunggu sampai nomor
 * sebelumnya benar-benar tersimpan. Locking read juga selalu membaca nilai
 * TERBARU (bukan snapshot REPEATABLE READ), sehingga tidak ada nomor basi.
 *
 * ATURAN WAJIB:
 * Semua method next*() harus dipanggil DI DALAM DB::transaction() yang sama
 * dengan insert yang memakai nomor tersebut. Kalau tidak, method akan throw.
 *
 * Rollback => nomor ikut batal (tidak ada gap).
 */
class NomorJurnalService
{
    /**
     * Nomor jurnal berikutnya (dipakai bersama oleh Jurnal Pembantu & Jurnal Umum).
     */
    public static function nextJurnal(): int
    {
        return self::next('jurnal', function () {
            return max(
                (int) (DB::table('jurnal_pembantu_headers')->max('jurnal') ?? 0),
                (int) (JurnalUmum::query()->max('jurnal') ?? 0)
            );
        });
    }

    /**
     * No. JP (no_jurnal_pembantu) berikutnya.
     */
    public static function nextNoJP(): int
    {
        return self::next('no_jp', function () {
            return (int) (DB::table('jurnal_pembantu_headers')->max('no_jurnal_pembantu') ?? 0);
        });
    }

    /**
     * @return array{jurnal: int, no_jp: int}
     */
    public static function nextJurnalDanNoJP(): array
    {
        return [
            'jurnal' => self::nextJurnal(),
            'no_jp' => self::nextNoJP(),
        ];
    }

    /**
     * Inti alokasi.
     *
     * $floorResolver = pengaman: kalau ada kode lama yang masih insert nomor
     * dengan cara MAX+1 (counter jadi tertinggal), nomor berikutnya tetap
     * dimulai dari max(counter, nilai terbesar di tabel) + 1.
     */
    private static function next(string $nama, ?callable $floorResolver = null): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException(
                "NomorJurnalService::next('{$nama}') harus dipanggil di dalam DB::transaction() ".
                'yang sama dengan insert-nya, supaya row lock ditahan sampai commit.'
            );
        }

        $row = DB::table('nomor_counters')
            ->where('nama', $nama)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            throw new \RuntimeException("Counter '{$nama}' belum ada di tabel nomor_counters.");
        }

        $current = (int) $row->nilai;

        if ($floorResolver) {
            $current = max($current, (int) $floorResolver());
        }

        $next = $current + 1;

        DB::table('nomor_counters')
            ->where('nama', $nama)
            ->update(['nilai' => $next, 'updated_at' => now()]);

        return $next;
    }
}
