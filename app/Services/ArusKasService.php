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
            // Pakai nilai_masuk/nilai_keluar yang sudah dipisah per transaksi
            // (bukan 'tipe' tunggal per kategori — kategori bisa berisi
            // campuran transaksi masuk & keluar sekaligus).
            $totalMasuk += $r['nilai_masuk'] ?? 0.0;
            $totalKeluar += $r['nilai_keluar'] ?? 0.0;
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

            // ── Nama akun kas/bank yang kena mutasi pada transaksi ini ──
            // Biasanya 1 akun kas per transaksi, tapi kalau ada lebih dari 1
            // (mis. jurnal yang menyentuh 2 rekening sekaligus / transfer
            // antar kas), tampilkan semua yang terlibat.
            $namaKasList = $barisKasDiJurnalIni->pluck('nama_akun')->filter()->unique()->values()->all();
            $namaKas = implode(', ', $namaKasList);

            $tanggal = optional($barisKasDiJurnalIni->first()->tgl)->format('Y-m-d');
            $netKas = $nilaiMasuk - $nilaiKeluar;
            $tipe = $isTransferInternal ? 'netral' : ($netKas >= 0 ? 'in' : 'out');

            // ── Sisi mana yang benar-benar jadi TUJUAN kas (bukan sekadar ──
            // ikut membiayai bersama kas). Dalam double-entry, baris yang ada
            // di sisi BERLAWANAN dengan kas itulah yang "menerima" nilai kas
            // (mis. beli barang: kas kredit/keluar, akun barang didebit —
            // itu tujuan sungguhan). Baris non-kas yang kebetulan ada di sisi
            // SAMA dengan kas (sama-sama kredit, atau sama-sama debit) bukan
            // tujuan pengeluaran baru — dia cuma "ikut membiayai" bersama kas
            // untuk melunasi baris lawan lainnya. Kasus nyata: pelunasan
            // utang yang dibayar sebagian pakai uang muka (dikredit, sisi
            // sama dengan kas) + sebagian pakai kas baru — uang muka itu
            // BUKAN kategori arus kas baru, dia cuma metode pembayaran co-
            // funding, sehingga harus dikeluarkan dari dasar perhitungan
            // rasio supaya tidak dihitung dobel dan tidak salah kategori.
            $arahKas = $nilaiMasuk >= $nilaiKeluar ? 'd' : 'k';
            $barisTujuan = $barisNonKas->filter(fn($b) => strtolower($b->map) !== $arahKas);
            // Fallback: kalau ternyata semua baris lawan ada di sisi yang
            // sama dengan kas (tidak ada baris "tujuan" sungguhan — jarang
            // terjadi, tapi bisa saja), tetap pakai semua baris supaya
            // transaksi tidak hilang begitu saja dari laporan.
            if ($barisTujuan->isEmpty()) {
                $barisTujuan = $barisNonKas;
            }

            // Label fallback untuk transaksi tanpa split (1 kategori saja)
            // atau transfer internal: pakai baris TUJUAN pertama (bukan
            // sekadar baris lawan pertama di jurnal), supaya tidak salah
            // ambil baris co-funding seperti Uang Muka pada contoh di atas.
            $namaAkunLawanUtama = optional($barisTujuan->first())->nama_akun;
            $keteranganUtama = optional($barisTujuan->first())->keterangan
                ?: optional($barisKasDiJurnalIni->first())->keterangan;


            // ── Pecah 1 transaksi kas ke beberapa kategori sekaligus, kalau ──
            // baris lawannya (barisTujuan) memang punya kategori berbeda-beda
            // (mis. 1 kas keluar dipakai beli 3 barang beda kategori dalam 1
            // jurnal). Proporsi tiap kategori dihitung dari besar nilai baris
            // tujuan masing-masing terhadap total nilai baris tujuan, dan
            // masing-masing kategori bawa label dari baris lawan yang
            // benar-benar jadi sumbernya sendiri (bukan baris pertama di
            // jurnal secara keseluruhan). Kalau hanya ada 1 baris tujuan
            // (kasus paling umum), hasilnya sama seperti sebelumnya: 1
            // kategori dengan proporsi 100%.
            $splits = $isTransferInternal
                ? [self::KATEGORI_TRANSFER_INTERNAL => [
                    'proporsi'       => 1.0,
                    'nama_akun'      => $namaAkunLawanUtama,
                    'keterangan'     => $keteranganUtama,
                    'nilai_kategori' => abs($nilaiMasuk - $nilaiKeluar),
                    'total_nilai'    => abs($nilaiMasuk - $nilaiKeluar),
                ]]
                : $this->splitKategori($barisTujuan, $kategoriMap);


            $splitBerganda = count($splits) > 1;

            foreach ($splits as $kodeKategori => $info) {
                $proporsi = $info['proporsi'];
                $namaKategori = $isTransferInternal
                    ? 'Transfer Kas Internal'
                    : ($labelKategori[$kodeKategori] ?? 'Lainnya');

                if (!isset($agregat[$kodeKategori])) {
                    $agregat[$kodeKategori] = [
                        'kode_kategori' => $kodeKategori,
                        'nama'          => $namaKategori,
                        'nilai_masuk'   => 0.0,
                        'nilai_keluar'  => 0.0,
                        'transaksi'     => [],
                    ];
                }

                $nilaiPorsi = abs($netKas) * $proporsi;

                // PENTING: akumulasi masuk & keluar terpisah per transaksi.
                // Jangan pakai satu "tipe" per kategori (bug lama: kategori
                // yang berisi campuran transaksi masuk & keluar akan salah
                // tanda, karena tipe kategori sempat dikunci dari transaksi
                // PERTAMA saja, lalu semua nilai lain — termasuk yang
                // arahnya berlawanan — ikut dijumlah pakai tanda yang sama).
                if ($tipe === 'in') {
                    $agregat[$kodeKategori]['nilai_masuk'] += $nilaiPorsi;
                } elseif ($tipe === 'out') {
                    $agregat[$kodeKategori]['nilai_keluar'] += $nilaiPorsi;
                }
                // 'netral' (transfer internal) sengaja tidak menambah masuk/keluar,
                // konsisten dengan pengecualian dari total di hitung().

                // Label khusus untuk baris kategori ini: nama akun lawan yang
                // benar-benar menyumbang ke kategori ini (bukan baris pertama
                // jurnal secara umum), dengan fallback ke label utama kalau
                // baris tsb kebetulan tidak punya nama akun.
                $namaAkunLawanPorsi = $info['nama_akun'] ?: $namaAkunLawanUtama;
                $keteranganPorsi = $info['keterangan'] ?: $keteranganUtama;
                $deskripsiPorsi = $namaAkunLawanPorsi ?: ($keteranganPorsi ?: 'Transaksi #' . $noJurnal);

                // Rumus perhitungan porsi ini, ditulis apa adanya (bukan cuma
                // hasil akhir), supaya admin bisa langsung memverifikasi
                // tanpa perlu menghitung ulang rasio dari nol. Cuma dibuat
                // kalau memang di-split (>1 kategori); transaksi 1 lawan
                // tidak perlu rumus karena nilainya sudah = nilai kas persis.
                $caraHitung = $splitBerganda
                    ? number_format($info['nilai_kategori'], 0, ',', '.')
                        . ' / ' . number_format($info['total_nilai'], 0, ',', '.')
                        . ' × Rp ' . number_format(abs($netKas), 0, ',', '.')
                        . ' = Rp ' . number_format($nilaiPorsi, 0, ',', '.')
                    : null;

                $agregat[$kodeKategori]['transaksi'][] = [
                    'jurnal'      => $noJurnal,
                    'tgl'         => $tanggal,
                    'deskripsi'   => $splitBerganda
                        ? $deskripsiPorsi . ' (porsi ' . round($proporsi * 100) . '%)'
                        : $deskripsiPorsi,
                    'keterangan'  => ($namaAkunLawanPorsi && $keteranganPorsi && $keteranganPorsi !== $namaAkunLawanPorsi) ? $keteranganPorsi : null,
                    'kas'         => $namaKas,
                    'nilai'       => $nilaiPorsi,
                    'tipe'        => $tipe,
                    'cara_hitung' => $caraHitung,
                ];
            }
        }

        // Ubah agregat masuk/keluar per kategori jadi net (nilai + tipe)
        // untuk ditampilkan sebagai satu baris ringkasan per kategori.
        $hasil = [];
        foreach ($agregat as $kodeKategori => $a) {
            $net = $a['nilai_masuk'] - $a['nilai_keluar'];
            $hasil[] = [
                'kode_kategori' => $a['kode_kategori'],
                'nama'          => $a['nama'],
                'tipe'          => $kodeKategori === self::KATEGORI_TRANSFER_INTERNAL
                    ? 'netral'
                    : ($net >= 0 ? 'in' : 'out'),
                'nilai'         => abs($net),
                'nilai_masuk'   => $a['nilai_masuk'],
                'nilai_keluar'  => $a['nilai_keluar'],
                'transaksi'     => $a['transaksi'],
            ];
        }

        // Urutkan: kategori kas masuk dulu, lalu keluar, transfer internal di akhir.
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

    /**
     * Pecah proporsi kategori arus kas dari kumpulan baris lawan (non-kas)
     * dalam 1 jurnal. Tiap baris disumbang nilainya (nilaiBaris) ke
     * kategori akunnya masing-masing (fallback 'lainnya' kalau akun belum
     * di-set kategori), lalu dinormalisasi jadi proporsi 0..1 yang totalnya
     * 1.0 (100%). Untuk tiap kategori, disimpan juga baris "wakil" (nilai
     * terbesar di kategori itu) supaya label yang ditampilkan sesuai dengan
     * akun lawan yang benar-benar jadi sumber kategori tsb — bukan selalu
     * baris pertama di jurnal (yang bisa jadi bukan bagian dari kategori itu).
     * Nilai kategori (`nilai_kategori`) dan total baris lawan (`total_nilai`)
     * juga disertakan supaya tampilan bisa menunjukkan rumus perhitungannya
     * apa adanya (mis. "88.750 / 127.500 × Rp 50.000"), bukan cuma hasil
     * akhirnya — supaya admin bisa memverifikasi tanpa menghitung ulang dari
     * nol.
     *
     * Kasus 1 lawan transaksi (paling umum): hasilnya 1 entri, proporsi 1.0.
     * Kasus banyak lawan beda kategori: kategori dengan nilai lebih besar
     * dapat proporsi lebih besar pula.
     *
     * @return array<string,array{proporsi:float,nama_akun:?string,keterangan:?string,nilai_kategori:float,total_nilai:float}>
     */
    private function splitKategori($barisNonKas, array $kategoriMap): array
    {
        $nilaiPerKategori = [];
        $wakilPerKategori = []; // kode => ['nilai'=>, 'nama_akun'=>, 'keterangan'=>]
        $totalNilai = 0.0;

        foreach ($barisNonKas as $b) {
            $kode = $kategoriMap[$b->no_akun] ?? 'lainnya';
            $nilai = $this->nilaiBaris($b);

            $nilaiPerKategori[$kode] = ($nilaiPerKategori[$kode] ?? 0.0) + $nilai;
            $totalNilai += $nilai;

            // Baris dengan nilai terbesar di kategori ini dipakai sebagai
            // wakil label (nama akun lawan + keterangan aslinya).
            if (!isset($wakilPerKategori[$kode]) || $nilai > $wakilPerKategori[$kode]['nilai']) {
                $wakilPerKategori[$kode] = [
                    'nilai'      => $nilai,
                    'nama_akun'  => $b->nama_akun,
                    'keterangan' => $b->keterangan,
                ];
            }
        }

        if ($totalNilai <= 0.0) {
            // Tidak ada nilai yang bisa dijadikan dasar proporsi (mis. semua
            // baris lawan nol) — fallback ke kategori baris lawan pertama
            // dengan proporsi penuh, supaya transaksi tetap tercatat.
            $kodeFallback = collect($nilaiPerKategori)->keys()->first() ?? 'lainnya';
            $wakil = $wakilPerKategori[$kodeFallback] ?? ['nama_akun' => null, 'keterangan' => null];
            return [$kodeFallback => [
                'proporsi'       => 1.0,
                'nama_akun'      => $wakil['nama_akun'],
                'keterangan'     => $wakil['keterangan'],
                'nilai_kategori' => $nilaiPerKategori[$kodeFallback] ?? 0.0,
                'total_nilai'    => $totalNilai,
            ]];
        }

        $hasil = [];
        foreach ($nilaiPerKategori as $kode => $nilai) {
            $hasil[$kode] = [
                'proporsi'       => $nilai / $totalNilai,
                'nama_akun'      => $wakilPerKategori[$kode]['nama_akun'] ?? null,
                'keterangan'     => $wakilPerKategori[$kode]['keterangan'] ?? null,
                'nilai_kategori' => $nilai,
                'total_nilai'    => $totalNilai,
            ];
        }

        return $hasil;
    }
}