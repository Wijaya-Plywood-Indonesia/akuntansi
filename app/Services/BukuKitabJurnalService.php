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
     * @param  array<string, array<int, array{id_barang?: int|null, nama_barang?: string|null, banyak: float, harga: float, keterangan?: string|null}>>  $itemBreakdown
     *         Rincian PER BARANG untuk variabel_nilai tertentu (mis. 'persediaan_barang_jadi', 'hpp').
     *         WAJIB isi 'banyak' (qty asli, mis. 3 lembar) dan 'harga' (harga
     *         SATUAN, bukan total) — nilai baris = banyak x harga. Ini penting
     *         karena pengurangan stok (Barang::getStokBukuBesarAttribute)
     *         dihitung dari kolom 'banyak', bukan dari nilai rupiah — kalau
     *         'banyak' selalu diisi 1, stok jadi salah hitung.
     *         Kalau suatu variabel_nilai ada di sini, baris jurnalnya dipecah jadi
     *         beberapa item (1 per barang) dengan id_barang masing-masing — PENTING
     *         supaya stok per produk (Barang::getStokBukuBesarAttribute) terhitung
     *         benar. Kalau tidak ada breakdown untuk suatu variabel, tetap 1 item
     *         generik seperti biasa (cocok untuk kas/ppn/piutang yang levelnya
     *         transaksi, bukan per barang).
     * @param  array<int, string>  $splitHeaderPerBarang
     *         Daftar variabel_nilai yang breakdown-nya HARUS jadi HEADER
     *         TERPISAH per barang (bukan cuma item terpisah dalam 1 header).
     *         WAJIB untuk akun Persediaan/Stok — karena saat posting ke
     *         Jurnal Umum, 1 header jadi 1 baris (item-itemnya digabung),
     *         jadi kalau beberapa barang ditumpuk di 1 header yang sama,
     *         info "barang mana" hilang pas sampai Jurnal Umum dan stok
     *         tidak berkurang per produk. Akun non-persediaan (mis. HPP)
     *         cukup 1 header banyak item, tidak perlu dimasukkan ke sini.
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
        array $itemBreakdown = [],
        array $splitHeaderPerBarang = [],
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
            $itemBreakdown,
            $splitHeaderPerBarang,
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

                $breakdown = $itemBreakdown[$baris->variabel_nilai] ?? null;
                $harusSplitHeader = !empty($breakdown)
                    && in_array($baris->variabel_nilai, $splitHeaderPerBarang, true);

                if ($harusSplitHeader) {
                    // ── HEADER TERPISAH PER BARANG ───────────────────────
                    // Tiap barang di breakdown jadi 1 header + 1 item sendiri,
                    // supaya saat diposting ke Jurnal Umum, tiap barang tetap
                    // jadi baris sendiri (id_barang tidak hilang tertumpuk).
                    foreach ($breakdown as $b) {
                        $banyakItem = (float) ($b['banyak'] ?? 0);
                        $hargaItem = (float) ($b['harga'] ?? 0);
                        $nominalItem = round($banyakItem * $hargaItem, 4);
                        if ($nominalItem <= 0) {
                            continue;
                        }

                        $ketItem = $b['keterangan'] ?? ($b['nama_barang'] ?? $baris->keterangan ?? $keteranganDefault ?? $kodeKitab);

                        $headerBarang = JurnalPembantuHeader::create([
                            'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                            'tgl_transaksi'      => $tglTransaksi,
                            'jenis_transaksi'    => $jenisTransaksi,
                            'modul_asal'         => $modulAsal,
                            'jurnal'             => $noJurnal,
                            'no_akun'            => $baris->no_akun,
                            'nama_akun'          => $baris->nama_akun,
                            'map'                => $baris->posisi,
                            'keterangan'         => "{$ketItem} | Nota: {$noDokumen}",
                            'no_dokumen'         => $noDokumen,
                            'total_nilai'        => $nominalItem,
                            'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                            'dibuat_oleh'        => $userId,
                        ]);

                        $dataItem = [
                            'jurnal_pembantu_header_id' => $headerBarang->id,
                            'urut'         => 1,
                            'jenis_pihak'  => $jenisPihak,
                            'nama_pihak'   => $namaPihak,
                            'nama_barang'  => $b['nama_barang'] ?? null,
                            'no_dokumen'   => $noDokumen,
                            'keterangan'   => $ketItem,
                            'banyak'       => $banyakItem,
                            'm3'           => 0,
                            'harga'        => $hargaItem,
                            'hit_kbk'      => 'b',
                            'status'       => true,
                            'created_by'   => $userId,
                        ];

                        if (!empty($b['id_barang'])) {
                            $dataItem['id_barang'] = $b['id_barang'];
                        }

                        JurnalPembantuItem::create($dataItem);

                        $headersDibuat->push($headerBarang);
                    }

                    continue; // sudah selesai untuk baris ini, lanjut ke baris berikutnya
                }

                // Kalau ada breakdown per barang, keterangan header ikut
                // menyebut nama-nama barangnya (bukan cuma teks generik),
                // supaya header tidak menyesatkan (mis. cuma sebut 1 nama
                // padahal isinya beberapa produk berbeda).
                if (!empty($breakdown)) {
                    $namaBarangUnik = collect($breakdown)
                        ->pluck('nama_barang')
                        ->filter()
                        ->unique()
                        ->values();
                    $labelBarang = $namaBarangUnik->isNotEmpty()
                        ? $namaBarangUnik->implode(', ')
                        : null;
                } else {
                    $labelBarang = null;
                }

                $ketBaris = $baris->keterangan ?: $keteranganDefault ?: $kodeKitab;
                if ($labelBarang) {
                    $ketBaris .= ' ' . $labelBarang;
                }

                $header = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tglTransaksi,
                    'jenis_transaksi'    => $jenisTransaksi,
                    'modul_asal'         => $modulAsal,
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $baris->no_akun,
                    'nama_akun'          => $baris->nama_akun,
                    'map'                => $baris->posisi,
                    'keterangan'         => "{$ketBaris} | Nota: {$noDokumen}",
                    'no_dokumen'         => $noDokumen,
                    'total_nilai'        => $nominal,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                if (!empty($breakdown)) {
                    // ── Item PER BARANG (id_barang terisi) ──────────────
                    // Wajib supaya Barang::getStokBukuBesarAttribute() bisa
                    // menghitung stok per produk dengan benar, terutama saat
                    // beberapa barang berbagi 1 akun persediaan yang sama.
                    $urutItem = 1;
                    foreach ($breakdown as $b) {
                        $banyakItem = (float) ($b['banyak'] ?? 0);
                        $hargaItem = (float) ($b['harga'] ?? 0);
                        $nominalItem = round($banyakItem * $hargaItem, 4);
                        if ($nominalItem <= 0) {
                            continue;
                        }

                        $dataItem = [
                            'jurnal_pembantu_header_id' => $header->id,
                            'urut'         => $urutItem++,
                            'jenis_pihak'  => $jenisPihak,
                            'nama_pihak'   => $namaPihak,
                            'nama_barang'  => $b['nama_barang'] ?? null,
                            'no_dokumen'   => $noDokumen,
                            'keterangan'   => $b['keterangan'] ?? ($b['nama_barang'] ?? $ketBaris),
                            'banyak'       => $banyakItem,
                            'm3'           => 0,
                            'harga'        => $hargaItem,
                            'hit_kbk'      => 'b',
                            'status'       => true,
                            'created_by'   => $userId,
                        ];

                        // id_barang cuma diisi kalau memang ada (baris non-barang,
                        // mis. "Alokasi Hutang Gaji" di header HPP, sengaja dibiarkan
                        // tanpa id_barang).
                        if (!empty($b['id_barang'])) {
                            $dataItem['id_barang'] = $b['id_barang'];
                        }

                        JurnalPembantuItem::create($dataItem);
                    }
                } else {
                    // ── Item generik (level transaksi, bukan per barang) ──
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
                }

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