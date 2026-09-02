<?php

namespace App\Services;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ArusKasService
{
    /**
     * Prefix kode akun yang dianggap "Kas / Bank".
     * Sesuai kesepakatan: semua Sub Anak Akun di bawah Anak Akun 1101.x
     */
    private const PREFIX_KAS = '1101';

    /**
     * Kode kategori yang dianggap "netral" (bukan kas masuk/keluar
     * sungguhan) — transfer antar akun kas/bank sendiri.
     */
    private const KATEGORI_TRANSFER_INTERNAL = 'transfer_internal';

    /**
     * Hitung rekap arus kas untuk satu rentang tanggal.
     */
    public function hitung(Carbon $start, Carbon $end): array
    {
        $kodeKas = $this->getKodeKas();

        if (empty($kodeKas)) {
            return $this->emptyResult();
        }

        $saldoAwal = $this->getSaldoKasSebelum($start, $kodeKas);
        $kategoriMap = $this->getKategoriMap();

        $rincian = $this->hitungMutasi($start, $end, $kodeKas, $kategoriMap);

        $totalMasuk = 0.0;
        $totalKeluar = 0.0;
        foreach ($rincian as $r) {
            if ($r['kode_kategori'] === self::KATEGORI_TRANSFER_INTERNAL) {
                continue; // dikecualikan dari total, hanya info
            }
            if ($r['tipe'] === 'in') {
                $totalMasuk += $r['nilai'];
            } else {
                $totalKeluar += $r['nilai'];
            }
        }

        $saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;

        // Validasi: cocokkan dengan saldo riil akun kas per tanggal akhir
        $saldoAkhirRiil = $this->getSaldoKasSebelum($end->copy()->addDay(), $kodeKas);
        $selisih = round($saldoAkhirRiil - $saldoAkhir, 2);

        return [
            'saldo_awal'      => $saldoAwal,
            'saldo_akhir'     => $saldoAkhir,
            'total_masuk'     => $totalMasuk,
            'total_keluar'    => $totalKeluar,
            'rincian'         => $rincian,
            'balanced'        => abs($selisih) < 0.01,
            'selisih_validasi' => $selisih,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'saldo_awal' => 0.0, 'saldo_akhir' => 0.0,
            'total_masuk' => 0.0, 'total_keluar' => 0.0,
            'rincian' => [], 'balanced' => true, 'selisih_validasi' => 0.0,
        ];
    }

    /**
     * Kode Sub Anak Akun yang termasuk Kas/Bank (di bawah Anak Akun 1101.x).
     */
    private function getKodeKas(): array
    {
        return SubAnakAkun::whereHas(
            'anakAkun',
            fn($q) => $q->where('kode_anak_akun', 'like', self::PREFIX_KAS . '%')
        )->pluck('kode_sub_anak_akun')->toArray();
    }

    /**
     * Peta kode_sub_anak_akun => kode_kategori_arus_kas, dari seluruh
     * AkunGroup yang sudah ditandai `kategori_arus_kas` (lihat AkunGroupForm).
     * Mengikuti pola rekursif yang sama dengan LabaRugi/NeracaService.
     */
    private function getKategoriMap(): array
    {
        $groups = AkunGroup::berkategoriArusKas()
            ->with([
                'anakAkuns.subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun',
                'childrenRecursive.anakAkuns.subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun',
            ])
            ->get();

        $map = [];
        foreach ($groups as $group) {
            $this->collectKodeUntukGrup($group, $group->kategori_arus_kas, $map);
        }

        return $map;
    }

    private function collectKodeUntukGrup(AkunGroup $group, string $kategori, array &$map): void
    {
        foreach ($group->anakAkuns as $anak) {
            foreach ($anak->subAnakAkuns as $sub) {
                $map[$sub->kode_sub_anak_akun] = $kategori;
            }
        }

        foreach ($group->childrenRecursive as $child) {
            $this->collectKodeUntukGrup($child, $kategori, $map);
        }
    }

    /**
     * Saldo total akun kas per akhir hari SEBELUM tanggal $before.
     * (Belum dioptimasi pakai buku_besar seperti NeracaService — untuk
     * versi awal dihitung langsung dari jurnal_umum agar hasil pasti akurat
     * di rentang tanggal berapa pun. Bisa dioptimasi belakangan bila perlu.)
     */
    private function getSaldoKasSebelum(Carbon $before, array $kodeKas): float
    {
        if (empty($kodeKas)) {
            return 0.0;
        }

        $row = JurnalUmum::where('tgl', '<', $before->format('Y-m-d'))
            ->whereIn('no_akun', $kodeKas)
            ->selectRaw("
                SUM(
                    CASE WHEN LOWER(map) = 'd' THEN
                        CASE
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_debit,
                SUM(
                    CASE WHEN LOWER(map) = 'k' THEN
                        CASE
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_kredit
            ")
            ->first();

        // Akun Kas selalu saldo normal Debit.
        return (float) ($row->total_debit ?? 0) - (float) ($row->total_kredit ?? 0);
    }

    /**
     * Hitung mutasi kas dalam rentang [start, end], dikelompokkan per
     * nomor jurnal lalu per kategori (lihat algoritma di dokumentasi).
     */
    private function hitungMutasi(Carbon $start, Carbon $end, array $kodeKas, array $kategoriMap): array
    {
        // Semua baris kas dalam rentang, dikelompokkan per nomor jurnal.
        $barisKas = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('no_akun', $kodeKas)
            ->get()
            ->groupBy('jurnal');

        if ($barisKas->isEmpty()) {
            return [];
        }

        $nomorJurnal = $barisKas->keys()->toArray();

        // Semua baris (termasuk non-kas) dalam nomor jurnal yang sama, untuk
        // menentukan lawan transaksi / kategori.
        $semuaBarisPerJurnal = JurnalUmum::whereIn('jurnal', $nomorJurnal)
            ->get()
            ->groupBy('jurnal');

        $labelKategori = AkunGroup::labelKategoriArusKas();
        $agregat = []; // kode_kategori => ['nilai_in'=>, 'nilai_out'=>, 'transaksi'=>[]]

        foreach ($barisKas as $noJurnal => $barisKasDiJurnalIni) {
            $semuaBaris = $semuaBarisPerJurnal[$noJurnal] ?? collect();
            $barisNonKas = $semuaBaris->reject(fn($b) => in_array($b->no_akun, $kodeKas));

            // Nilai bersih pergerakan kas pada transaksi ini (debit kas = masuk)
            $nilaiMasuk = 0.0;
            $nilaiKeluar = 0.0;
            foreach ($barisKasDiJurnalIni as $b) {
                $nilai = $this->nilaiBaris($b);
                if (strtolower($b->map) === 'd') {
                    $nilaiMasuk += $nilai;
                } else {
                    $nilaiKeluar += $nilai;
                }
            }

            $isTransferInternal = $barisNonKas->isEmpty()
                || $barisNonKas->every(fn($b) => in_array($b->no_akun, $kodeKas));

            $kodeKategori = $isTransferInternal
                ? self::KATEGORI_TRANSFER_INTERNAL
                : $this->tentukanKategori($barisNonKas, $kategoriMap);

            $namaKategori = $isTransferInternal
                ? 'Transfer Kas Internal'
                : ($labelKategori[$kodeKategori] ?? 'Lainnya');

            // Prioritaskan nama akun lawan transaksi (lebih ringkas & konkret,
            // mis. "Piutang Usaha", "Kas Bu Mut") sebagai judul utama.
            // Keterangan (sering berisi istilah teknis akuntansi yang diketik
            // staff, mis. "Pembalikan DP yang sudah diterima") ditampilkan
            // terpisah sebagai info tambahan, bukan judul utama, supaya tidak
            // membingungkan pembaca non-akuntan.
            $namaAkunLawan = optional($barisNonKas->first())->nama_akun;
            $keteranganAsli = optional($barisNonKas->first())->keterangan
                ?: optional($barisKasDiJurnalIni->first())->keterangan;

            $deskripsi = $namaAkunLawan ?: ($keteranganAsli ?: 'Transaksi #' . $noJurnal);

            // ── Nama akun kas/bank yang kena mutasi pada transaksi ini ──
            // Biasanya 1 akun kas per transaksi, tapi kalau ada lebih dari 1
            // (mis. jurnal yang menyentuh 2 rekening sekaligus / transfer
            // antar kas), tampilkan semua yang terlibat.
            $namaKasList = $barisKasDiJurnalIni->pluck('nama_akun')->filter()->unique()->values()->all();
            $namaKas = implode(', ', $namaKasList);

            $tanggal = optional($barisKasDiJurnalIni->first()->tgl)->format('Y-m-d');
            $netKas = $nilaiMasuk - $nilaiKeluar;
            $tipe = $isTransferInternal ? 'netral' : ($netKas >= 0 ? 'in' : 'out');

            if (!isset($agregat[$kodeKategori])) {
                $agregat[$kodeKategori] = [
                    'kode_kategori' => $kodeKategori,
                    'nama'          => $namaKategori,
                    'tipe'          => $tipe,
                    'nilai'         => 0.0,
                    'transaksi'     => [],
                ];
            }

            $agregat[$kodeKategori]['nilai'] += abs($netKas);
            $agregat[$kodeKategori]['transaksi'][] = [
                'jurnal'     => $noJurnal,
                'tgl'        => $tanggal,
                'deskripsi'  => $deskripsi,
                'keterangan' => ($namaAkunLawan && $keteranganAsli && $keteranganAsli !== $namaAkunLawan) ? $keteranganAsli : null,
                'kas'        => $namaKas,
                'nilai'      => abs($netKas),
                'tipe'       => $tipe,
            ];
        }

        // Urutkan: kategori kas masuk dulu, lalu keluar, transfer internal di akhir.
        $hasil = array_values($agregat);
        usort($hasil, function ($a, $b) {
            $urutanTipe = ['in' => 0, 'out' => 1, 'netral' => 2];
            return $urutanTipe[$a['tipe']] <=> $urutanTipe[$b['tipe']];
        });

        return $hasil;
    }

    private function nilaiBaris(JurnalUmum $baris): float
    {
        return match (strtolower($baris->hit_kbk ?? '')) {
            'b'     => (float) ($baris->banyak ?? 0) * (float) ($baris->harga ?? 0),
            'm'     => (float) ($baris->m3 ?? 0) * (float) ($baris->harga ?? 0),
            default => (float) ($baris->harga ?? 0),
        };
    }

    private function tentukanKategori($barisNonKas, array $kategoriMap): string
    {
        foreach ($barisNonKas as $b) {
            if (isset($kategoriMap[$b->no_akun])) {
                return $kategoriMap[$b->no_akun];
            }
        }

        return 'lainnya';
    }
}