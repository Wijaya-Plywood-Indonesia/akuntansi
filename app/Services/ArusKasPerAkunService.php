<?php

namespace App\Services;

use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use Carbon\Carbon;

class ArusKasPerAkunService
{
    /**
     * Prefix kode akun yang dianggap "Kas / Bank".
     * Sama seperti ArusKasService: semua Sub Anak Akun di bawah Anak Akun 1101.x
     */
    private const PREFIX_KAS = '1101';

    /**
     * Daftar semua Sub Anak Akun kas/bank yang aktif, untuk ditampilkan
     * sebagai pilihan checkbox "kas/bank mana yang mau ditampilkan".
     * key = kode_sub_anak_akun, value = nama_sub_anak_akun.
     *
     * Sub Anak Akun "header" yang namanya sama persis dengan nama Anak
     * Akun induknya (mis. "Kas dan Setara Kas") dianggap bukan rekening
     * sungguhan — hanya representasi grup — jadi tidak ikut ditampilkan.
     *
     * Daftar ini otomatis mengikuti data: kalau nanti ditambah akun
     * Bank baru sebagai Sub Anak Akun aktif di bawah 1101.x, otomatis
     * muncul di sini tanpa perlu ubah kode.
     */
    public function getDaftarAkunKas(): array
    {
        return SubAnakAkun::whereHas(
            'anakAkun',
            fn($q) => $q->where('kode_anak_akun', 'like', self::PREFIX_KAS . '%')
        )
            ->with('anakAkun:id,nama_anak_akun')
            ->aktif()
            ->orderBy('kode_sub_anak_akun')
            ->get(['kode_sub_anak_akun', 'nama_sub_anak_akun', 'id_anak_akun'])
            ->reject(fn(SubAnakAkun $sub) => $sub->anakAkun
                && trim(mb_strtolower($sub->nama_sub_anak_akun)) === trim(mb_strtolower($sub->anakAkun->nama_anak_akun)))
            ->pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')
            ->toArray();
    }

    /**
     * Saldo TERKINI (sampai hari ini) untuk sekumpulan akun kas/bank.
     * Dipakai untuk menentukan default checkbox: akun yang "ada isinya"
     * (saldo tidak nol) yang otomatis tercentang saat halaman dibuka.
     *
     * @param  string[]  $kodeAkunList
     * @return array<string,float>  kode_sub_anak_akun => saldo
     */
    public function hitungSaldoSekarang(array $kodeAkunList): array
    {
        $besok = Carbon::now()->addDay()->startOfDay();

        $saldo = [];
        foreach ($kodeAkunList as $kode) {
            $saldo[$kode] = $this->getSaldoAkunSebelum($besok, $kode);
        }

        return $saldo;
    }

    /**
     * Hitung rekap arus kas per akun (kolom sejajar), untuk satu rentang
     * tanggal dan satu set kode akun kas/bank yang dipilih user.
     *
     * Format hasil dirancang supaya gampang dirender sebagai tabel dengan
     * grup kolom D/K/Saldo per akun, persis pola "Form Setoran Kas" excel:
     * satu baris = satu transaksi (nomor jurnal), kolom akun yang tidak
     * tersentuh dikosongkan tapi saldo tetap ditampilkan (carry forward).
     *
     * @param  string[]  $kodeAkunTerpilih  kode_sub_anak_akun yang dipilih user, urut sesuai pilihan
     */
    public function hitung(Carbon $start, Carbon $end, array $kodeAkunTerpilih): array
    {
        $kodeAkunTerpilih = array_values(array_unique($kodeAkunTerpilih));

        if (empty($kodeAkunTerpilih)) {
            return $this->emptyResult($kodeAkunTerpilih);
        }

        $namaAkun = SubAnakAkun::whereIn('kode_sub_anak_akun', $kodeAkunTerpilih)
            ->pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')
            ->toArray();

        // ── Saldo awal per akun (posisi akhir hari SEBELUM $start) ──
        $saldoAwal = [];
        foreach ($kodeAkunTerpilih as $kode) {
            $saldoAwal[$kode] = $this->getSaldoAkunSebelum($start, $kode);
        }

        // ── Semua baris jurnal yang menyentuh akun-akun terpilih, dalam rentang ──
        $barisKas = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('no_akun', $kodeAkunTerpilih)
            ->orderBy('tgl')
            ->orderBy('jurnal')
            ->orderBy('id')
            ->get();

        if ($barisKas->isEmpty()) {
            return $this->emptyResult($kodeAkunTerpilih, $saldoAwal, $namaAkun);
        }

        $nomorJurnal = $barisKas->pluck('jurnal')->unique()->values()->all();
        $barisPerJurnal = $barisKas->groupBy('jurnal');

        // Untuk deskripsi baris ("Ket"), ambil juga baris NON-kas di jurnal yang
        // sama, supaya bisa dapat nama lawan transaksi kalau keterangan kosong.
        $semuaBarisPerJurnal = JurnalUmum::whereIn('jurnal', $nomorJurnal)
            ->get()
            ->groupBy('jurnal');

        $runningSaldo = $saldoAwal;
        $totalMasuk = array_fill_keys($kodeAkunTerpilih, 0.0);
        $totalKeluar = array_fill_keys($kodeAkunTerpilih, 0.0);

        $baris = [];

        foreach ($barisPerJurnal as $noJurnal => $barisKasDiJurnalIni) {
            $kolom = [];
            foreach ($kodeAkunTerpilih as $kode) {
                $kolom[$kode] = ['debit' => null, 'kredit' => null];
            }

            foreach ($barisKasDiJurnalIni as $b) {
                if (!in_array($b->no_akun, $kodeAkunTerpilih, true)) {
                    continue;
                }
                $nilai = $this->nilaiBaris($b);
                if (strtolower($b->map) === 'd') {
                    $kolom[$b->no_akun]['debit'] = ($kolom[$b->no_akun]['debit'] ?? 0) + $nilai;
                    $runningSaldo[$b->no_akun] += $nilai;
                    $totalMasuk[$b->no_akun] += $nilai;
                } else {
                    $kolom[$b->no_akun]['kredit'] = ($kolom[$b->no_akun]['kredit'] ?? 0) + $nilai;
                    $runningSaldo[$b->no_akun] -= $nilai;
                    $totalKeluar[$b->no_akun] += $nilai;
                }
            }

            $semuaBaris = $semuaBarisPerJurnal[$noJurnal] ?? collect();
            $deskripsi = $this->buatDeskripsi($noJurnal, $barisKasDiJurnalIni, $semuaBaris, $kodeAkunTerpilih);

            $baris[] = [
                'jurnal'    => $noJurnal,
                'tgl'       => optional($barisKasDiJurnalIni->first()->tgl)->format('Y-m-d'),
                'deskripsi' => $deskripsi,
                'kolom'     => $kolom,
                'saldo'     => $runningSaldo, // snapshot posisi SETELAH baris ini
            ];
        }

        return [
            'kode_akun'     => $kodeAkunTerpilih,
            'nama_akun'     => $namaAkun,
            'saldo_awal'    => $saldoAwal,
            'saldo_akhir'   => $runningSaldo,
            'total_masuk'   => $totalMasuk,
            'total_keluar'  => $totalKeluar,
            'baris'         => $baris,
        ];
    }

    private function emptyResult(array $kodeAkunTerpilih, array $saldoAwal = [], array $namaAkun = []): array
    {
        $saldoAwal = $saldoAwal ?: array_fill_keys($kodeAkunTerpilih, 0.0);

        return [
            'kode_akun'    => $kodeAkunTerpilih,
            'nama_akun'    => $namaAkun,
            'saldo_awal'   => $saldoAwal,
            'saldo_akhir'  => $saldoAwal,
            'total_masuk'  => array_fill_keys($kodeAkunTerpilih, 0.0),
            'total_keluar' => array_fill_keys($kodeAkunTerpilih, 0.0),
            'baris'        => [],
        ];
    }

    /**
     * Saldo satu akun kas/bank per akhir hari SEBELUM tanggal $before.
     */
    private function getSaldoAkunSebelum(Carbon $before, string $kodeAkun): float
    {
        $row = JurnalUmum::where('tgl', '<', $before->format('Y-m-d'))
            ->where('no_akun', $kodeAkun)
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
     * Susun deskripsi ringkas untuk kolom "Ket".
     *
     * Untuk transaksi yang berasal dari penjualan/pembelian (punya nomor
     * dokumen/nota dan nama pihak terkait), tampilkan cukup "No. Nota -
     * Nama" saja — bukan keterangan teknis yang panjang. Kalau kedua
     * kolom itu kosong (mis. jurnal manual seperti "Modal Awal"), baru
     * pakai keterangan asli / nama akun lawan sebagai fallback.
     */
    private function buatDeskripsi(int|string $noJurnal, $barisKasDiJurnalIni, $semuaBaris, array $kodeAkunTerpilih): string
    {
        $barisNota = $semuaBaris->first(fn($b) => filled($b->no_dokumen));
        $barisNama = $semuaBaris->first(fn($b) => filled($b->nama));

        $noNota = optional($barisNota)->no_dokumen;
        $namaPihak = optional($barisNama)->nama;

        // Catatan (mis. "beli lem") yang diinput user disisipkan di ujung
        // keterangan baris kas dalam kurung, contoh:
        // "KAS TUNAI | Nota: sj-9218 | DOVER CHEMICAL (beli lem)".
        // Ambil catatan itu supaya ikut tampil di kolom Ket rekap arus kas,
        // bukan cuma "No. Nota - Nama" saja.
        $keteranganKasMentah = optional($barisKasDiJurnalIni->first())->keterangan ?? '';
        $catatanUser = null;
        if (preg_match('/\(([^()]+)\)\s*$/', (string) $keteranganKasMentah, $m)) {
            $catatanUser = trim($m[1]);
        }

        if ($noNota || $namaPihak) {
            return collect([
                $noNota,
                $namaPihak,
                $catatanUser,
            ])->filter()->implode(' - ');
        }

        $keteranganKas = $keteranganKasMentah ?: null;
        $namaAkunLawan = optional(
            $semuaBaris->first(fn($b) => !in_array($b->no_akun, $kodeAkunTerpilih, true))
        )->nama_akun;

        return $keteranganKas ?: ($namaAkunLawan ?: 'Transaksi #' . $noJurnal);
    }

    private function nilaiBaris(JurnalUmum $baris): float
    {
        return match (strtolower($baris->hit_kbk ?? '')) {
            'b'     => (float) ($baris->banyak ?? 0) * (float) ($baris->harga ?? 0),
            'm'     => (float) ($baris->m3 ?? 0) * (float) ($baris->harga ?? 0),
            default => (float) ($baris->harga ?? 0),
        };
    }
}