<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\SubAnakAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\PembelianMetodePembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalPembelianService
{
    const KODE_KAS_TUNAI        = '1111.00'; 
    const KODE_BANK_TRANSFER    = '1210.00'; 
    const KODE_PPN_MASUKAN      = '1303.04'; 
    const KODE_HUTANG_DAGANG    = '2101-00'; 
    const KODE_BEBAN_ONGKIR     = '5860-00'; 
    const KODE_BEBAN_LAIN_LAIN  = '5870.00'; 

    public function buatJurnalDariPembelian(Pembelian $pembelian, int $userId): void
    {
        $pembelian->loadMissing([
            'detailPembelians.barang.subAnakAkun',
            'detailPembelians.barang.akunHpp',
            'detailPembelians.barang.akunPendapatan',
            'metodePembayarans',
            'supplier'
        ]);

        if ($pembelian->detailPembelians->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($pembelian, $userId) {
            $tgl = $pembelian->tanggal;
            $nota = $pembelian->nomor_nota;
            $supplier = $pembelian->supplier_name ?: 'Supplier';
            $grandTotal = (float) $pembelian->grand_total;

            $noJurnal = JurnalPembantuHeader::lockForUpdate()->max('jurnal') + 1;

            // ──────────────────────────────────────────────────────────────
            // SISI DEBIT (D) 1: Nilai Pokok Barang
            // ──────────────────────────────────────────────────────────────
            foreach ($pembelian->detailPembelians as $detail) {
                $barang = $detail->barang;

                $kodeAkunDebet = $barang?->subAnakAkun?->kode_sub_anak_akun;

                if (!$kodeAkunDebet) {
                    Log::warning("[JurnalPembelian] Barang '{$detail->nama_barang}' belum di-set akun persediaannya. Menggunakan fallback persediaan default '115-01'.");
                    $kodeAkunDebet = '115-01';
                }

                $namaAkunDebet = $this->getNamaAkun($kodeAkunDebet);

                $headerD = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $kodeAkunDebet,
                    'nama_akun'          => $namaAkunDebet,
                    'map'                => 'd',
                    'keterangan'         => "{$detail->nama_barang} | Nota: {$nota}",
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $detail->subtotal,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                // ✅ FIX: Deteksi otomatis hit_kbk (Jika hasil m3 * harga cocok dengan subtotal)
                $m3Detail = (float) ($detail->kubikasi ?? 0);
                $hargaDetail = (float) $detail->harga_beli;
                $subtotalDetail = (float) $detail->subtotal;
                
                $hitKbk = $detail->hit_kbk ?? null;
                if (empty($hitKbk)) {
                    if ($m3Detail > 0 && abs($subtotalDetail - ($m3Detail * $hargaDetail)) < 0.01) {
                        $hitKbk = 'm';
                    } else {
                        $hitKbk = 'b';
                    }
                }

                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerD->id,
                    'urut'         => 1,
                    'jenis_pihak'  => 'supplier',
                    'nama_pihak'   => $supplier,
                    'nama_barang'  => $detail->nama_barang,
                    'no_dokumen'   => $nota,
                    'keterangan'   => "Masuk Gudang " . (float)$detail->qty . " {$detail->satuan}",
                    'banyak'       => $detail->qty,
                    'm3'           => $m3Detail,
                    'harga'        => $hargaDetail,
                    'shadow_harga' => $hargaDetail,
                    'shadow_jumlah' => $subtotalDetail,
                    'jumlah'       => $subtotalDetail,
                    'hit_kbk'      => $hitKbk, // Parameter presisi yang dikirimkan
                    'status'       => true,
                    'created_by'   => $userId,
                ]);
            }

            // ──────────────────────────────────────────────────────────────
            // SISI DEBIT (D) 2: PAJAK PPN MASUKAN
            // ──────────────────────────────────────────────────────────────
            $ppnNominal = (float) ($pembelian->total_ppn ?? 0);
            if ($ppnNominal > 0) {
                $kodeAkunPpn = self::KODE_PPN_MASUKAN;
                $namaAkunPpn = $this->getNamaAkun($kodeAkunPpn) ?: 'PPN Masukan';

                $headerPpnD = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $kodeAkunPpn,
                    'nama_akun'          => $namaAkunPpn,
                    'map'                => 'd',
                    'keterangan'         => "Pajak Pertambahan Nilai (PPN) | Nota: {$nota}",
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $ppnNominal,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                $this->buatItemDetail($headerPpnD->id, 1, $supplier, $nota, "Alokasi Pajak {$nota}", $ppnNominal, $userId);
            }

            // ──────────────────────────────────────────────────────────────
            // SISI DEBIT (D) 3: BEBAN ONGKIR
            // ──────────────────────────────────────────────────────────────
            $ongkir = (float) ($pembelian->ongkir ?? 0);
            if ($ongkir > 0) {
                $kodeAkunOngkir = self::KODE_BEBAN_ONGKIR;
                $namaAkunOngkir = $this->getNamaAkun($kodeAkunOngkir) ?: 'Biaya Angkut Pembelian';

                $headerOngkirD = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $kodeAkunOngkir,
                    'nama_akun'          => $namaAkunOngkir,
                    'map'                => 'd',
                    'keterangan'         => "Beban Ongkos Kirim Pembelian | Nota: {$nota}",
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $ongkir,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                $this->buatItemDetail($headerOngkirD->id, 1, $supplier, $nota, "Alokasi Beban Ongkir Nota {$nota}", $ongkir, $userId);
            }

            // ──────────────────────────────────────────────────────────────
            // SISI DEBIT (D) 4: BIAYA LAIN-LAIN
            // ──────────────────────────────────────────────────────────────
            $biayaLain = (float) ($pembelian->biaya_lain ?? 0);
            if ($biayaLain > 0) {
                $kodeAkunLain = self::KODE_BEBAN_LAIN_LAIN;
                $namaAkunLain = $this->getNamaAkun($kodeAkunLain) ?: 'Beban Lain-Lain';

                $headerLainD = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $kodeAkunLain,
                    'nama_akun'          => $namaAkunLain,
                    'map'                => 'd',
                    'keterangan'         => "Beban Biaya Lain-Lain Pembelian | Nota: {$nota}",
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $biayaLain,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                $this->buatItemDetail($headerLainD->id, 1, $supplier, $nota, "Alokasi Biaya Lain-Lain Nota {$nota}", $biayaLain, $userId);
            }

            // ──────────────────────────────────────────────────────────────
            // SISI KREDIT (K) : EVALUASI PEMBAGIAN KAS VS HUTANG DAGANG
            // ──────────────────────────────────────────────────────────────
            $totalUangMuka = 0;
            $sisaHutang    = 0;

            $pembayaranPertama = $pembelian->metodePembayarans->sortBy('id')->first();
            $methodString = $pembayaranPertama?->payment_method ?? PembelianMetodePembayaran::METODE_TUNAI;

            if ($methodString === PembelianMetodePembayaran::METODE_TUNAI) {
                $totalUangMuka = $grandTotal;
                $sisaHutang    = 0;
            } else {
                $nominalUangMuka = $pembayaranPertama ? (float) $pembayaranPertama->amount : 0.0;
                $totalUangMuka = $nominalUangMuka;
                $sisaHutang    = max(0, $grandTotal - $nominalUangMuka);
            }

            if ($totalUangMuka > 0) {
                $metodeUtama = ($methodString === PembelianMetodePembayaran::METODE_TRANSFER)
                    ? self::KODE_BANK_TRANSFER
                    : self::KODE_KAS_TUNAI;

                $namaKas = $this->getNamaAkun($metodeUtama);
                if (empty($namaKas)) {
                    $namaKas = ($methodString === PembelianMetodePembayaran::METODE_TRANSFER) ? 'Bank Transfer' : 'Kas Tunai';
                }

                $keteranganHeaderKas = strtoupper($namaKas) . " | Nota: {$nota} | {$supplier}";
                if (!empty($pembayaranPertama?->reference_number)) {
                    $keteranganHeaderKas .= " [Ref: #{$pembayaranPertama->reference_number}]";
                }
                if (!empty($pembayaranPertama?->catatan)) {
                    $keteranganHeaderKas .= " ({$pembayaranPertama->catatan})";
                }

                $headerKas = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $metodeUtama,
                    'nama_akun'          => $namaKas,
                    'map'                => 'k',
                    'keterangan'         => $keteranganHeaderKas,
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $totalUangMuka,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerKas->id,
                    'urut'         => 1,
                    'jenis_pihak'  => 'supplier',
                    'nama_pihak'   => $supplier,
                    'no_dokumen'   => $nota,
                    'nama_barang'  => null,
                    'keterangan'   => "Pembayaran Tunai/Uang Muka",
                    'banyak'       => 1,
                    'm3'           => 0,
                    'harga'        => $totalUangMuka,
                    'shadow_harga' => $totalUangMuka,
                    'shadow_jumlah' => $totalUangMuka,
                    'jumlah'       => $totalUangMuka,
                    'hit_kbk'      => 'b', 
                    'status'       => true,
                    'created_by'   => $userId,
                ]);
            }

            if ($sisaHutang > 0) {
                $akunHutangDinamis = self::KODE_HUTANG_DAGANG;
                $namaHutang = $this->getNamaAkun($akunHutangDinamis) ?: 'Hutang Dagang';

                $headerHutang = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                    'tgl_transaksi'      => $tgl,
                    'jenis_transaksi'    => 'bm',
                    'modul_asal'         => 'pembelian_barang',
                    'jurnal'             => $noJurnal,
                    'no_akun'            => $akunHutangDinamis,
                    'nama_akun'          => $namaHutang,
                    'map'                => 'k',
                    'keterangan'         => "Hutang Pembayaran Nota: {$nota} | {$supplier}",
                    'no_dokumen'         => $nota,
                    'total_nilai'        => $sisaHutang,
                    'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh'        => $userId,
                ]);

                $urutKredit = 1;
                $totalSub   = $pembelian->detailPembelians->sum('subtotal');
                $sisaBarangHutang = max(0, $totalSub - $totalUangMuka);

                if ($sisaBarangHutang > 0) {
                    $this->buatItemDetail($headerHutang->id, $urutKredit++, $supplier, $nota, "Sisa Nilai Pokok Barang", $sisaBarangHutang, $userId);
                }
                if ($ppnNominal > 0) {
                    $this->buatItemDetail($headerHutang->id, $urutKredit++, $supplier, $nota, "Alokasi Pajak Pertambahan Nilai (PPN)", $ppnNominal, $userId);
                }
                if ($ongkir > 0) {
                    $this->buatItemDetail($headerHutang->id, $urutKredit++, $supplier, $nota, "Alokasi Komponen Biaya Ongkos Kirim", $ongkir, $userId);
                }
                if ($biayaLain > 0) {
                    $this->buatItemDetail($headerHutang->id, $urutKredit++, $supplier, $nota, "Alokasi Komponen Biaya Lain-lain", $biayaLain, $userId);
                }
            }
        });
    }

    private function buatItemDetail(int $headerId, int $urut, string $supplier, string $nota, string $ket, float $nominal, int $userId): void
    {
        JurnalPembantuItem::create([
            'jurnal_pembantu_header_id' => $headerId,
            'urut'         => $urut,
            'jenis_pihak'  => 'supplier',
            'nama_pihak'   => $supplier,
            'no_dokumen'   => $nota,
            'keterangan'   => $ket,
            'banyak'       => 1,
            'm3'           => 0,
            'harga'        => $nominal,
            'shadow_harga' => $nominal,
            'shadow_jumlah' => $nominal,
            'jumlah'       => $nominal,
            'hit_kbk'      => 'b', 
            'status'       => true,
            'created_by'   => $userId,
        ]);
    }

    private function getNamaAkun(string $kode): string
    {
        return SubAnakAkun::where('kode_sub_anak_akun', $kode)->value('nama_sub_anak_akun') ?? '';
    }
}