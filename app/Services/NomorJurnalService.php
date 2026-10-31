<?php

namespace App\Services;

use App\Models\JurnalUmum;
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
 *
 * CATATAN:
 * - nextJurnal() sekarang mengambil MAX dari jurnal_pembantu_headers DAN
 *   jurnal_umum, supaya draft baru tidak pernah mendapat nomor yang sudah
 *   terpakai di Jurnal Umum (penyebab nomor overlap, mis. No. 1218).
 * - dalamLock() dipakai PostingJurnalPembantuService untuk membungkus
 *   seluruh proses posting (alokasi nomor + insert ke Jurnal Umum + commit),
 *   karena posting memakai nomor max(JurnalUmum) + 1 dan harus serial.
 * - GET_LOCK re-entrant di MySQL >= 5.7 / MariaDB >= 10.0.2 (sesi yang sama
 *   boleh mengambil lock bernama sama berulang; tiap GET_LOCK butuh
 *   RELEASE_LOCK sendiri, dan itu sudah ditangani di withLock()).
 */
class NomorJurnalService
{
    private const LOCK_NAME = 'jurnal_nomor_alloc';

    private const LOCK_TIMEOUT = 10; // detik menunggu lock sebelum gagal

    /**
     * Ambil nomor jurnal berikutnya yang dijamin unik.
     * Melihat nomor terbesar di Jurnal Pembantu DAN Jurnal Umum.
     *
     * @throws \RuntimeException jika tidak bisa mendapat lock dalam batas waktu.
     */
    public static function nextJurnal(): int
    {
        return self::withLock(function () {
            return self::maxJurnalGabungan() + 1;
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
            $maxNoJP = (int) (DB::table('jurnal_pembantu_headers')->max('no_jurnal_pembantu') ?? 0);

            return [
                'jurnal' => self::maxJurnalGabungan() + 1,
                'no_jp' => $maxNoJP + 1,
            ];
        });
    }

    /**
     * Jalankan $callback di dalam advisory lock alokasi nomor jurnal.
     * Pakai ini untuk membungkus proses yang harus serial (mis. posting ke
     * Jurnal Umum): panggil di LUAR DB::transaction() supaya lock baru
     * dilepas SETELAH transaksi commit.
     */
    public static function dalamLock(callable $callback): mixed
    {
        return self::withLock($callback);
    }

    /**
     * Nomor jurnal terbesar yang pernah dipakai, di Jurnal Pembantu maupun
     * Jurnal Umum.
     */
    public static function maxJurnalGabungan(): int
    {
        $maxHeader = (int) (DB::table('jurnal_pembantu_headers')->max('jurnal') ?? 0);
        $maxJU = (int) (JurnalUmum::query()->max('jurnal') ?? 0);

        return max($maxHeader, $maxJU);
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
