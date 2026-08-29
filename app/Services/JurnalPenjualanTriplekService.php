<?php

namespace App\Services;

use App\Models\Penjualan;

/**
 * Jurnal Penjualan Triplek & Turunannya (ampurlur, lem, dll) — berbasis
 * template Buku Kitab, BUKAN hardcode seperti JurnalPenjualanTelurService.
 *
 * Modul ini KHUSUS untuk web triplek (telur punya web terpisah), dan semua
 * transaksinya WAJIB lewat pengakuan Piutang dulu dulu (pengiriman besok),
 * baru dilunasi belakangan (alur pelunasan menyusul, belum diimplementasi
 * di sini — lihat catatan di README_BUKU_KITAB.md).
 */
class JurnalPenjualanTriplekService
{
    /** Hutang gaji dipatok tetap per transaksi (kesepakatan bisnis). */
    private const HUTANG_GAJI_TETAP = 300000.0;

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    /**
     * Bangun jurnal PENGAKUAN PIUTANG (saat transaksi divalidasi status
     * 'PIUTANG') — belum termasuk pelunasannya.
     *
     * Kode kitab dipilih otomatis: 'penjualan_triplek_ppn' kalau ada PPN,
     * 'penjualan_triplek_non_ppn' kalau tidak — tidak perlu if-else manual
     * untuk isi barisnya, itu semua sudah di tabel Buku Kitab.
     */
    public function buatJurnalPengakuanPiutang(Penjualan $penjualan, int $userId): void
    {
        $penjualan->loadMissing('details.barang');

        // ── Breakdown PER BARANG (wajib, supaya stok tiap produk benar) ──
        // Setiap barang jadi 1 item tersendiri di header Persediaan & HPP,
        // lengkap dengan id_barang-nya — meski akun persediaan-nya SAMA
        // (banyak barang berbagi 1 akun, mis. "1404.2 Persediaan Triplek
        // Siap Jual"), stok tetap terhitung terpisah per produk.
        $breakdownPersediaan = [];
        $breakdownHpp = [];
        $breakdownPenjualan = [];
        $nilaiPokokBarang = 0.0;

        foreach ($penjualan->details as $detail) {
            $qty = (float) $detail->qty;
            $hargaBeli = (float) ($detail->barang->harga_beli ?? 0);
            $nominal = $qty * $hargaBeli;

            if ($nominal <= 0) {
                continue;
            }

            $nilaiPokokBarang += $nominal;

            $breakdownPersediaan[] = [
                'id_barang'   => $detail->barang_id,
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,       // qty ASLI (mis. 3 lembar), bukan 1 —
                'harga'       => $hargaBeli, // harga SATUAN (per lembar), bukan total.
                'keterangan'  => 'Keluar stok ' . $detail->nama_barang,
            ];

            // HPP SENGAJA tidak diisi id_barang — akun HPP bukan akun stok,
            // jadi tidak butuh dipisah per barang saat posting ke Jurnal
            // Umum. Kalau id_barang ikut diisi beda-beda di sini, proses
            // posting akan memecahnya jadi baris terpisah per barang, padahal
            // untuk HPP kita memang mau tetap 1 baris gabungan (termasuk
            // baris "Alokasi Hutang Gaji" di dalamnya).
            $breakdownHpp[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaBeli,
                'keterangan'  => 'HPP ' . $detail->nama_barang,
            ];

            // Penjualan JUGA dipecah per barang, pakai harga JUAL BERSIH
            // (subtotal / qty) — bukan harga_jual mentah — supaya kalau ada
            // potongan/diskon per baris, nilainya tetap akurat. Sama seperti
            // HPP, tidak perlu id_barang (akun Pendapatan bukan akun stok).
            $hargaJualBersih = $qty > 0 ? round((float) $detail->subtotal / $qty, 4) : 0;
            $breakdownPenjualan[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaJualBersih,
                'keterangan'  => 'Penjualan ' . $detail->nama_barang,
            ];
        }

        $hutangGaji = self::HUTANG_GAJI_TETAP;

        // Hutang Gaji ditambahkan sebagai baris TERSENDIRI di breakdown HPP
        // (tanpa id_barang, karena bukan pergerakan barang) — supaya HPP
        // (Debit) tetap balance dengan Persediaan + Hutang Gaji (Kredit),
        // sekaligus HPP per barang tetap akurat (tidak "ketumpuk" gaji).
        // banyak=1, harga=nominal gaji — karena ini bukan barang bersatuan.
        $breakdownHpp[] = [
            'nama_barang' => null,
            'banyak'      => 1,
            'harga'       => $hutangGaji,
            'keterangan'  => 'Alokasi Hutang Gaji',
        ];

        // PENTING soal keseimbangan D/K (lihat README bagian pilot):
        // D: Piutang + HPP  =  K: PPN + Penjualan + Persediaan + Hutang Gaji
        // Karena Persediaan cuma nilai pokok barang (tanpa gaji), sementara
        // Hutang Gaji nongol di sisi Kredit tanpa "teman" debit-nya sendiri,
        // maka HPP di sisi Debit HARUS mencakup nilai pokok + hutang gaji
        // supaya baris ini tetap balance.
        $hpp = $nilaiPokokBarang + $hutangGaji;

        $ppnNominal = (float) $penjualan->ppn_nominal;
        $kodeKitab = $ppnNominal > 0 ? 'penjualan_triplek_ppn' : 'penjualan_triplek_non_ppn';

        $context = [
            'piutang_usaha'          => (float) $penjualan->total,
            'hpp'                    => $hpp,
            'ppn_keluaran'           => $ppnNominal,
            'nilai_penjualan'        => (float) $penjualan->sub_total,
            'persediaan_barang_jadi' => $nilaiPokokBarang,
            'hutang_gaji'            => $hutangGaji,
        ];

        $this->engine->buatJurnalDariKitab(
            kodeKitab: $kodeKitab,
            context: $context,
            noDokumen: $penjualan->no_nota,
            tglTransaksi: $penjualan->tanggal,
            modulAsal: 'penjualan_triplek',
            jenisTransaksi: 'bk',
            userId: $userId,
            jenisPihak: 'pelanggan',
            namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
            keteranganDefault: 'Penjualan Triplek',
            itemBreakdown: [
                'persediaan_barang_jadi' => $breakdownPersediaan,
                'hpp'                    => $breakdownHpp,
                'nilai_penjualan'        => $breakdownPenjualan,
            ],
            splitHeaderPerBarang: ['persediaan_barang_jadi'],
        );
    }
}