<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Penjualan;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Jurnal Penjualan Triplek & Turunannya (ampurlur, lem, dll) — berbasis
 * template Buku Kitab, BUKAN hardcode seperti JurnalPenjualanTelurService.
 *
 * Modul ini KHUSUS untuk web triplek (telur punya web terpisah). Menangani
 * SEMUA jenis transaksi dari POS (COD / DP / BAYAR_DIMUKA) lewat 1 kode
 * kitab yang sama ('penjualan_triplek_ppn' / '_non_ppn') — bedanya cuma
 * seberapa besar bagian yang masuk Kas vs yang jadi Piutang:
 *
 *   - COD          : bayar = 0        -> semua jadi Piutang, Kas di-skip (0)
 *   - DP            : 0 < bayar < total -> sebagian Kas, sisanya Piutang
 *   - BAYAR_DIMUKA  : bayar = total    -> semua masuk Kas, Piutang di-skip (0)
 *
 * Baris yang nilainya 0 otomatis dilewati oleh engine (lihat
 * BukuKitabJurnalService::buatJurnalDariKitab), jadi tidak perlu percabangan
 * manual per jenis transaksi di sini.
 */
class JurnalPenjualanTriplekService
{
    /** Hutang gaji dipatok tetap per transaksi (kesepakatan bisnis). */
    private const HUTANG_GAJI_TETAP = 300000.0;

    /** Kas tunai default kalau tidak ada rekening spesifik yang cocok. */
    private const KODE_KAS_TUNAI = '1101.1';

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    /**
     * Bangun jurnal pengakuan penjualan (Kas + Piutang di sisi Debit,
     * proporsinya tergantung berapa yang sudah dibayar) — dipakai untuk
     * SEMUA jenis_transaksi (COD/DP/BAYAR_DIMUKA), tidak perlu method
     * terpisah per jenis.
     */
    public function buatJurnalPenjualan(Penjualan $penjualan, int $userId): void
    {
        $penjualan->loadMissing(['details.barang', 'rekeningPerusahaan.subAnakAkun']);

        // ── Breakdown PER BARANG untuk Persediaan & HPP (wajib, biar stok
        //    per produk benar) — sama seperti sebelumnya, tidak berubah.
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
                'banyak'      => $qty,
                'harga'       => $hargaBeli,
                'keterangan'  => 'Keluar stok ' . $detail->nama_barang,
            ];

            // HPP & Penjualan SENGAJA tidak diisi id_barang — bukan akun
            // stok, tidak butuh dipisah per barang saat posting.
            $breakdownHpp[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaBeli,
                'keterangan'  => 'HPP ' . $detail->nama_barang,
            ];

            $hargaJualBersih = $qty > 0 ? round((float) $detail->subtotal / $qty, 4) : 0;
            $breakdownPenjualan[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaJualBersih,
                'keterangan'  => 'Penjualan ' . $detail->nama_barang,
            ];
        }

        $hutangGaji = self::HUTANG_GAJI_TETAP;

        // D: Piutang + HPP  =  K: PPN + Penjualan + Persediaan + Hutang Gaji
        // (lihat README_BUKU_KITAB.md) — hutang gaji "numpang" di HPP supaya
        // baris ini tetap balance walau tidak ada debit lawan sendiri.
        $hpp = $nilaiPokokBarang + $hutangGaji;

        $ppnNominal = (float) $penjualan->ppn_nominal;
        $kodeKitab = $ppnNominal > 0 ? 'penjualan_triplek_ppn' : 'penjualan_triplek_non_ppn';

        // ── Bagi total tagihan jadi bagian Kas (sudah dibayar) & Piutang
        //    (sisa belum dibayar) — inilah yang membedakan COD/DP/BAYAR_DIMUKA.
        $totalTagihan = (float) $penjualan->total;
        $totalDiterima = min((float) $penjualan->bayar, $totalTagihan);
        $sisaPiutang = max($totalTagihan - $totalDiterima, 0);

        DB::transaction(function () use (
            $penjualan,
            $userId,
            $kodeKitab,
            $breakdownPersediaan,
            $breakdownHpp,
            $breakdownPenjualan,
            $hpp,
            $ppnNominal,
            $totalDiterima,
            $sisaPiutang,
        ) {
            // Nomor jurnal DITENTUKAN DI SINI (bukan di dalam engine) supaya
            // baris Kas (dibuat manual, dinamis per bank) dan baris dari
            // template kitab (dibuat lewat engine) tetap 1 grup jurnal.
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            // ── Bagian Kas (kalau ada yang sudah dibayar) — dinamis,
            //    tergantung tunai/transfer/rekening bank mana. Ini di LUAR
            //    Buku Kitab karena rekening bank sifatnya dinamis per
            //    transaksi, bukan tetap seperti baris template lain.
            foreach ($this->resolveBarisKasDiterima($penjualan, $totalDiterima) as $kas) {
                $header = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $penjualan->tanggal,
                    'jenis_transaksi'    => 'bk',
                    'modul_asal'         => 'penjualan_triplek',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $kas['kode'],
                    'nama_akun'          => $kas['nama'],
                    'map'                => 'd',
                    'keterangan'         => "Penerimaan Penjualan Triplek | Nota: {$penjualan->no_nota}",
                    'no_dokumen'         => $penjualan->no_nota,
                    'total_nilai'        => $kas['nominal'],
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'urut'         => 1,
                    'jenis_pihak'  => 'pelanggan',
                    'nama_pihak'   => $penjualan->nama_customer ?: 'Pelanggan',
                    'no_dokumen'   => $penjualan->no_nota,
                    'keterangan'   => 'Penerimaan ' . $kas['nama'],
                    'banyak'       => 1,
                    'm3'           => 0,
                    'harga'        => $kas['nominal'],
                    'hit_kbk'      => 'b',
                    'status'       => true,
                    'created_by'   => $userId,
                ]);
            }

            // ── Bagian dari template Buku Kitab (Piutang sisa, HPP, PPN,
            //    Penjualan, Persediaan, Hutang Gaji) — nomor jurnal DITITIPKAN
            //    supaya nyambung dengan header Kas di atas.
            $context = [
                'piutang_usaha'          => $sisaPiutang, // 0 untuk BAYAR_DIMUKA -> otomatis di-skip
                'hpp'                    => $hpp,
                'ppn_keluaran'           => $ppnNominal,
                'nilai_penjualan'        => (float) $penjualan->sub_total,
                'persediaan_barang_jadi' => array_sum(array_map(fn($b) => $b['banyak'] * $b['harga'], $breakdownPersediaan)),
                'hutang_gaji'            => self::HUTANG_GAJI_TETAP,
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
                noJurnalOverride: $noJurnal,
            );
        });
    }

    /**
     * Tentukan baris Kas yang perlu dibuat berdasarkan berapa yang SUDAH
     * dibayar (bisa 0/COD, sebagian/DP, atau penuh/BAYAR_DIMUKA), dan ke
     * rekening/kas mana — mendukung split Tunai + Transfer sekaligus,
     * mirror pola resolveBarisKas() di JurnalPenjualanTelurService.
     *
     * @return array<int, array{kode: string, nama: string, nominal: float}>
     */
    private function resolveBarisKasDiterima(Penjualan $penjualan, float $totalDiterima): array
    {
        if ($totalDiterima <= 0) {
            return [];
        }

        $bayarTunai = (float) ($penjualan->bayar_tunai ?? 0);
        $bayarTransfer = (float) ($penjualan->bayar_transfer ?? 0);
        $totalTercatatSplit = $bayarTunai + $bayarTransfer;

        $baris = [];

        // Fallback: kalau split tunai/transfer tidak tercatat (mis. data lama
        // atau field belum konsisten diisi), anggap semuanya 1 metode saja.
        if ($totalTercatatSplit <= 0) {
            $kode = $penjualan->metode_pembayaran === 'TRANSFER'
                ? ($penjualan->rekeningPerusahaan?->subAnakAkun?->kode_sub_anak_akun ?: self::KODE_KAS_TUNAI)
                : self::KODE_KAS_TUNAI;

            $baris[] = [
                'kode'    => $kode,
                'nama'    => $this->getNamaAkun($kode),
                'nominal' => $totalDiterima,
            ];

            return $baris;
        }

        if ($bayarTunai > 0) {
            $baris[] = [
                'kode'    => self::KODE_KAS_TUNAI,
                'nama'    => $this->getNamaAkun(self::KODE_KAS_TUNAI),
                'nominal' => $bayarTunai,
            ];
        }

        if ($bayarTransfer > 0) {
            $kodeBank = $penjualan->rekeningPerusahaan?->subAnakAkun?->kode_sub_anak_akun;
            if (! $kodeBank) {
                Log::warning("[JurnalPenjualanTriplek] Rekening transfer {$penjualan->no_rekening} belum di-mapping ke akun, fallback ke Kas Tunai.");
                $kodeBank = self::KODE_KAS_TUNAI;
            }

            $baris[] = [
                'kode'    => $kodeBank,
                'nama'    => $this->getNamaAkun($kodeBank),
                'nominal' => $bayarTransfer,
            ];
        }

        return $baris;
    }

    private function getNamaAkun(string $kode): string
    {
        return cache()->remember(
            "nama_akun_{$kode}",
            300,
            fn() => SubAnakAkun::where('kode_sub_anak_akun', $kode)->value('nama_sub_anak_akun') ?? $kode
        );
    }
}