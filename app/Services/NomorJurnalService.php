<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use Illuminate\Support\Facades\DB;

/**
 * Alokasi nomor jurnal & No. JP yang dijamin unik dan bebas race-condition.
 *
 * MASALAH LAMA:
 * Sebelumnya setiap tempat memakai pola:
 *   JurnalPembantuHeader::lockForUpdate()->max('jurnal') + 1
 *
 * `lockForUpdate()` pada aggregate query (MAX) di MySQL InnoDB TIDAK mengunci
 * baris tertentu — ia hanya mengunci "gap" index, bukan nilai max-nya.
 * Akibatnya dua request bersamaan bisa mendapat nilai MAX yang sama, lalu
 * keduanya menggunakan nomor jurnal yang sama → satu nomor jurnal isinya
 * dua nota yang berbeda.
 *
 * SOLUSI:
 * Gunakan MySQL advisory lock (GET_LOCK / RELEASE_LOCK) yang bekerja di
 * level koneksi/session, bukan di level baris. Selama lock dipegang,
 * tidak ada koneksi lain yang bisa masuk ke blok alokasi nomor yang sama.
 * Lock dilepas otomatis begitu koneksi selesai, jadi aman walau terjadi
 * crash di tengah jalan.
 */
class NomorJurnalService
{
    private const LOCK_NAME    = 'jurnal_nomor_alloc';
    private const LOCK_TIMEOUT = 10; // detik menunggu lock sebelum gagal

    /**
     * Ambil nomor jurnal berikutnya yang dijamin unik.
     *
     * WAJIB dipanggil di dalam DB::transaction() yang sudah berjalan,
     * ATAU bisa berdiri sendiri — lock akan melindungi alokasi ini.
     *
     * @throws \RuntimeException jika tidak bisa mendapat lock dalam batas waktu.
     */
    public static function nextJurnal(): int
    {
        return self::withLock(function () {
            return (int) (DB::table('jurnal_pembantu_headers')->max('jurnal') ?? 0) + 1;
        });
    }

    /**
     * Ambil No. JP (no_jurnal_pembantu) berikutnya yang dijamin unik.
     */
    public static function nextNoJP(): int
    {
        return self::withLock(function () {
            return (int) (DB::table('jurnal_pembantu_headers')->max('no_jurnal_pembantu') ?? 0) + 1;
        });
    }

    /**
     * Ambil nomor jurnal + No. JP sekaligus dalam satu lock —
     * lebih efisien daripada dua panggilan terpisah.
     *
     * @return array{jurnal: int, no_jp: int}
     */
    public static function nextJurnalDanNoJP(): array
    {
        return self::withLock(function () {
            $maxJurnal = (int) (DB::table('jurnal_pembantu_headers')->max('jurnal') ?? 0);
            $maxNoJP   = (int) (DB::table('jurnal_pembantu_headers')->max('no_jurnal_pembantu') ?? 0);

            return [
                'jurnal' => $maxJurnal + 1,
                'no_jp'  => $maxNoJP + 1,
            ];
        });
    }

    /**
     * Jalankan $callback di dalam MySQL advisory lock.
     * Lock dilepas otomatis setelah callback selesai (atau throw).
     */
    private static function withLock(callable $callback): mixed
    {
        $acquired = DB::selectOne(
            'SELECT GET_LOCK(?, ?) AS ok',
            [self::LOCK_NAME, self::LOCK_TIMEOUT]
        );

        if (! ($acquired->ok ?? false)) {
            throw new \RuntimeException(
                'Tidak bisa mendapatkan lock alokasi nomor jurnal dalam '.self::LOCK_TIMEOUT.' detik. '.
                'Kemungkinan ada proses lain yang sedang berjalan. Coba lagi sesaat.'
            );
        }

        try {
            return $callback();
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }
}
