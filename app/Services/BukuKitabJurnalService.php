<?php

namespace App\Services;

use App\Models\BukuKitab;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use Illuminate\Support\Facades\DB;

/**
 * Engine generik pembuat jurnal dari template Buku Kitab.
 *
 * Dipakai oleh SEMUA modul (Penjualan, Pembelian, Produksi, dll) — yang
 * beda antar modul cuma kode template & isi $context, bukan fungsi ini.
 *
 * Lihat README_BUKU_KITAB.md untuk penjelasan konsep lengkap.
 */
class BukuKitabJurnalService
{
    /**
     * Bangun jurnal (JurnalPembantuHeader + Item) dari 1 template Buku Kitab.
     *
     * @param  string      $kodeKitab     Kode di tabel buku_kitabs, mis. 'pelunasan_triplek_ppn_tunai'
     * @param  array        $context       Kamus nilai, mis. ['hpp' => 15000000, 'ppn' => 2500000, ...]
     * @param  string       $noDokumen     Nomor nota/dokumen sumber transaksi
     * @param  \DateTimeInterface|string $tglTransaksi  Tanggal transaksi
     * @param  string       $modulAsal     Penanda modul pemanggil, mis. 'penjualan', 'pembelian_barang'
     * @param  string       $jenisTransaksi Salah satu dari JurnalPembantuHeader::JENIS (bm/bk/dp/gaji/produksi/lain)
     * @param  int          $userId        ID user yang membuat (dibuat_oleh)
     * @param  string       $jenisPihak    Salah satu dari JurnalPembantuItem::JENIS_PIHAK
     * @param  string       $namaPihak     Nama customer/supplier/dst untuk item
     * @param  string|null  $keteranganDefault  Keterangan default kalau baris tidak punya keterangan sendiri
     *
     * @return \Illuminate\Support\Collection<JurnalPembantuHeader>  Header-header yang berhasil dibuat
     *
     * @throws \RuntimeException  Kalau kode template tidak ditemukan / tidak aktif,
     *                             atau tidak ada satupun baris yang nilainya > 0.
     */
    public function buatJurnalDariKitab(
        string $kodeKitab,
        array $context,
        string $noDokumen,
        $tglTransaksi,
        string $modulAsal,
        string $jenisTransaksi,
        int $userId,
        string $jenisPihak = 'lain',
        string $namaPihak = '-',
        ?string $keteranganDefault = null,
    ) {
        $barisTemplate = BukuKitab::templateAkun($kodeKitab);

        if ($barisTemplate->isEmpty()) {
            throw new \RuntimeException(
                "Template Buku Kitab dengan kode '{$kodeKitab}' tidak ditemukan atau tidak aktif. ".
                "Cek menu Buku Kitab, pastikan kode-nya benar dan status Aktif."
            );
        }

        // Validasi dini: pastikan tiap baris punya variabel_nilai terisi,
        // supaya ketahuan dari awal kalau template belum lengkap diisi
        // (bukan diam-diam menghasilkan jurnal timpang / Rp 0 semua).
        $barisBelumLengkap = $barisTemplate->filter(fn($b) => blank($b->variabel_nilai));

        if ($barisBelumLengkap->isNotEmpty()) {
            $urutBermasalah = $barisBelumLengkap->pluck('urut')->implode(', ');
            throw new \RuntimeException(
                "Template Buku Kitab '{$kodeKitab}' punya baris (urut: {$urutBermasalah}) ".
                "yang belum diisi variabel_nilai-nya. Lengkapi dulu di menu Buku Kitab."
            );
        }

        return DB::transaction(function () use (
            $barisTemplate,
            $context,
            $noDokumen,
            $tglTransaksi,
            $modulAsal,
            $jenisTransaksi,
            $userId,
            $jenisPihak,
            $namaPihak,
            $keteranganDefault,
            $kodeKitab,
        ) {
            $noJurnal = JurnalPembantuHeader::lockForUpdate()->max('jurnal') + 1;

            $headersDibuat = collect();
            $adaBarisTerpakai = false;

            foreach ($barisTemplate as $baris) {
                $nominal = (float) ($context[$baris->variabel_nilai] ?? 0);

                // Baris yang nilainya 0/tidak ada di context dilewati —
                // contoh: baris PPN dilewati kalau transaksi non-PPN,
                // baris DP dilewati kalau memang tidak ada DP.
                if ($nominal <= 0) {
                    continue;
                }

                $adaBarisTerpakai = true;

                $header = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tglTransaksi,
                    'jenis_transaksi'    => $jenisTransaksi,
                    'modul_asal'         => $modulAsal,
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $baris->no_akun,
                    'nama_akun'          => $baris->nama_akun,
                    'map'                => $baris->posisi,
                    'keterangan'         => ($baris->keterangan ?: $keteranganDefault ?: $kodeKitab) . " | Nota: {$noDokumen}",
                    'no_dokumen'         => $noDokumen,
                    'total_nilai'        => $nominal,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'urut'         => 1,
                    'jenis_pihak'  => $jenisPihak,
                    'nama_pihak'   => $namaPihak,
                    'no_dokumen'   => $noDokumen,
                    'keterangan'   => $baris->keterangan ?: $keteranganDefault,
                    'banyak'       => 1,
                    'm3'           => 0,
                    'harga'        => $nominal,
                    'hit_kbk'      => 'b', // banyak(1) x harga(nominal) = nominal
                    'status'       => true,
                    'created_by'   => $userId,
                ]);

                $headersDibuat->push($header);
            }

            if (!$adaBarisTerpakai) {
                throw new \RuntimeException(
                    "Template Buku Kitab '{$kodeKitab}' tidak menghasilkan satupun baris jurnal — ".
                    "semua nilai di \$context adalah 0 atau kosong. Cek lagi data transaksi yang dikirim."
                );
            }

            return $headersDibuat;
        });
    }
}