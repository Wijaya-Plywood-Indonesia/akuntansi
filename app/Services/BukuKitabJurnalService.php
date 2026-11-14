<?php

namespace App\Services;

use App\Models\AnakAkun;
use App\Models\BukuKitab;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\SubAnakAkun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Engine generik pembuat jurnal dari template Buku Kitab.
 *
 * Dipakai oleh SEMUA modul (Penjualan, Pembelian, Produksi, dll) - yang
 * beda antar modul cuma kode template & isi $context, bukan fungsi ini.
 *
 * Lihat README_BUKU_KITAB.md untuk penjelasan konsep lengkap.
 */
class BukuKitabJurnalService
{
    /**
     * Cache in-memory per request supaya nama akun yang sama tidak di-query
     * berkali-kali kalau 1 transaksi menghasilkan banyak baris/header.
     *
     * @var array<string, string|null>
     */
    private array $namaAkunCache = [];

    /**
     * Ambil nama akun TERKINI dari Chart of Accounts berdasarkan no_akun,
     * bukan dari snapshot lama yang tersimpan di buku_kitab_akuns.nama_akun.
     */
    private function resolveNamaAkun(?string $noAkun, ?string $namaSnapshot): ?string
    {
        if (blank($noAkun)) {
            return $namaSnapshot;
        }

        if (array_key_exists($noAkun, $this->namaAkunCache)) {
            return $this->namaAkunCache[$noAkun] ?? $namaSnapshot;
        }

        $namaTerkini = SubAnakAkun::where('kode_sub_anak_akun', $noAkun)->value('nama_sub_anak_akun')
            ?? AnakAkun::where('kode_anak_akun', $noAkun)->value('nama_anak_akun');

        $this->namaAkunCache[$noAkun] = $namaTerkini;

        return $namaTerkini ?? $namaSnapshot;
    }

    /**
     * Bangun jurnal (JurnalPembantuHeader + Item) dari 1 template Buku Kitab.
     *
     * @param  string  $kodeKitab  Kode di tabel buku_kitabs, mis. 'pelunasan_triplek_ppn_tunai'
     * @param  array  $context  Kamus nilai, mis. ['hpp' => 15000000, 'ppn' => 2500000, ...]
     * @param  string  $noDokumen  Nomor nota/dokumen sumber transaksi
     * @param  \DateTimeInterface|string  $tglTransaksi  Tanggal transaksi
     * @param  string  $modulAsal  Penanda modul pemanggil
     * @param  string  $jenisTransaksi  Salah satu dari JurnalPembantuHeader::JENIS
     * @param  int  $userId  ID user yang membuat (dibuat_oleh)
     * @param  string  $jenisPihak  Salah satu dari JurnalPembantuItem::JENIS_PIHAK
     * @param  string  $namaPihak  Nama customer/supplier/dst untuk item
     * @param  string|null  $keteranganDefault  Keterangan default kalau baris tidak punya keterangan sendiri
     * @param  array  $itemBreakdown  Rincian PER BARANG untuk variabel_nilai tertentu
     *                                (banyak x harga SATUAN; 'no_akun'/'nama_akun' opsional menggantikan akun template).
     * @param  array<int, string>  $splitHeaderPerBarang
     *                                                    Variabel_nilai yang breakdown-nya HARUS jadi HEADER TERPISAH per barang.
     * @param  array<int, string>  $tampilkanMeskiNol
     *                                                 Daftar variabel_nilai yang barisnya TETAP dibuat walau nilainya 0
     *                                                 (mis. ['persediaan_barang_jadi', 'hpp']). Variabel yang tidak
     *                                                 terdaftar tetap di-skip kalau 0, seperti perilaku semula.
     * @return Collection<JurnalPembantuHeader> Header-header yang berhasil dibuat
     *
     * @throws \RuntimeException Kalau kode template tidak ditemukan / tidak aktif,
     *                           atau tidak ada satupun baris yang dibuat.
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
        ?int $pihakId = null,
        ?string $keteranganDefault = null,
        array $itemBreakdown = [],
        array $splitHeaderPerBarang = [],
        ?int $noJurnalOverride = null,
        array $catatanPerVariabel = [],
        array $tampilkanMeskiNol = [],
    ) {
        $barisTemplate = BukuKitab::templateAkun($kodeKitab);

        if ($barisTemplate->isEmpty()) {
            throw new \RuntimeException(
                "Template Buku Kitab dengan kode '{$kodeKitab}' tidak ditemukan atau tidak aktif. ".
                'Cek menu Buku Kitab, pastikan kode-nya benar dan status Aktif.'
            );
        }

        // Validasi dini: pastikan tiap baris punya variabel_nilai terisi.
        $barisBelumLengkap = $barisTemplate->filter(fn ($b) => blank($b->variabel_nilai));

        if ($barisBelumLengkap->isNotEmpty()) {
            $urutBermasalah = $barisBelumLengkap->pluck('urut')->implode(', ');
            throw new \RuntimeException(
                "Template Buku Kitab '{$kodeKitab}' punya baris (urut: {$urutBermasalah}) ".
                'yang belum diisi variabel_nilai-nya. Lengkapi dulu di menu Buku Kitab.'
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
            $pihakId,
            $keteranganDefault,
            $kodeKitab,
            $itemBreakdown,
            $splitHeaderPerBarang,
            $noJurnalOverride,
            $catatanPerVariabel,
            $tampilkanMeskiNol,
        ) {
            $noJurnal = $noJurnalOverride ?? NomorJurnalService::nextJurnal();

            $headersDibuat = collect();
            $adaBarisTerpakai = false;

            foreach ($barisTemplate as $baris) {
                $nominal = (float) ($context[$baris->variabel_nilai] ?? 0);

                // Baris bernilai 0 dilewati (mis. PPN di transaksi non-PPN),
                // KECUALI variabelnya didaftarkan di $tampilkanMeskiNol, dan
                // variabel itu memang dikirim di $context (key-nya ada).
                $wajibTampil = in_array($baris->variabel_nilai, $tampilkanMeskiNol, true)
                    && array_key_exists($baris->variabel_nilai, $context);

                if ($nominal <= 0 && ! $wajibTampil) {
                    continue;
                }

                $adaBarisTerpakai = true;

                $breakdown = $itemBreakdown[$baris->variabel_nilai] ?? null;
                $harusSplitHeader = ! empty($breakdown)
                    && in_array($baris->variabel_nilai, $splitHeaderPerBarang, true);

                if ($harusSplitHeader) {
                    // -- HEADER DIKELOMPOKKAN PER AKUN + PER BARANG --------
                    //  1. Barang dengan id_barang BEDA selalu jadi header
                    //     SENDIRI-SENDIRI, walau akunnya sama.
                    //  2. Komponen "generik" (tanpa id_barang) digabung ke
                    //     header barang LAIN yang akunnya sama, TAPI cuma
                    //     kalau di akun itu cuma ada SATU barang.
                    $itemBerId = [];    // kunci: no_akun|id_barang
                    $itemGenerik = [];  // kunci: no_akun (tanpa id_barang)

                    foreach ($breakdown as $b) {
                        $banyakItem = (float) ($b['banyak'] ?? 0);
                        $hargaItem = (float) ($b['harga'] ?? 0);
                        $nominalItem = $this->nominalBreakdown($b);

                        $ketItem = $b['keterangan'] ?? ($b['nama_barang'] ?? $baris->keterangan ?? $keteranganDefault ?? $kodeKitab);

                        $noAkunDipakai = $b['no_akun'] ?? $baris->no_akun;
                        $namaAkunSnapshot = $b['nama_akun'] ?? $baris->nama_akun;
                        $namaAkunDipakai = $this->resolveNamaAkun($noAkunDipakai, $namaAkunSnapshot);
                        $idBarang = $b['id_barang'] ?? null;

                        $itemData = [
                            'no_akun' => $noAkunDipakai,
                            'nama_akun' => $namaAkunDipakai,
                            'ket_item' => $ketItem,
                            'banyak' => $banyakItem,
                            'harga' => $hargaItem,
                            'nominal' => $nominalItem,
                            'm3' => (float) ($b['m3'] ?? 0),
                            'hit_kbk' => $b['hit_kbk'] ?? 'b',
                            'nama_barang' => $b['nama_barang'] ?? null,
                            'id_barang' => $idBarang,
                        ];

                        if (! empty($idBarang)) {
                            $kunciBarang = ($noAkunDipakai ?: '__tanpa_akun__').'|'.$idBarang;
                            $itemBerId[$kunciBarang] ??= [
                                'no_akun' => $noAkunDipakai, 'nama_akun' => $namaAkunDipakai, 'items' => [],
                            ];
                            $itemBerId[$kunciBarang]['items'][] = $itemData;
                        } else {
                            $kunciAkun = $noAkunDipakai ?: '__tanpa_akun__';
                            $itemGenerik[$kunciAkun] ??= [
                                'no_akun' => $noAkunDipakai, 'nama_akun' => $namaAkunDipakai, 'items' => [],
                            ];
                            $itemGenerik[$kunciAkun]['items'][] = $itemData;
                        }
                    }

                    $barangPerAkun = [];
                    foreach ($itemBerId as $kunciBarang => $grup) {
                        $akunKey = $grup['no_akun'] ?: '__tanpa_akun__';
                        $barangPerAkun[$akunKey] = ($barangPerAkun[$akunKey] ?? 0) + 1;
                    }

                    $grupFinal = []; // list of ['no_akun','nama_akun','items']

                    foreach ($itemBerId as $kunciBarang => $grup) {
                        $grupFinal[] = $grup;
                    }

                    foreach ($itemGenerik as $akunKey => $grupGenerik) {
                        if (($barangPerAkun[$akunKey] ?? 0) === 1) {
                            foreach ($grupFinal as &$g) {
                                if (($g['no_akun'] ?: '__tanpa_akun__') === $akunKey) {
                                    array_push($g['items'], ...$grupGenerik['items']);

                                    continue 2;
                                }
                            }
                            unset($g);
                        }
                        $grupFinal[] = $grupGenerik;
                    }

                    $catatanBaris = trim((string) ($catatanPerVariabel[$baris->variabel_nilai] ?? ''));

                    foreach ($grupFinal as $grup) {
                        $items = $grup['items'];
                        if (empty($items)) {
                            continue;
                        }
                        $totalNominalGrup = round(array_sum(array_column($items, 'nominal')), 4);

                        if (count($items) === 1) {
                            $ketHeader = $items[0]['ket_item'];
                        } else {
                            $namaUnik = collect($items)->pluck('nama_barang')->filter()->unique()->values();
                            $ketHeader = $namaUnik->isNotEmpty()
                                ? ($baris->keterangan ?: $keteranganDefault ?: $kodeKitab).' '.$namaUnik->implode(', ')
                                : ($baris->keterangan ?: $keteranganDefault ?: $kodeKitab);
                        }

                        $headerBarang = JurnalPembantuHeader::create([
                            'no_jurnal_pembantu' => NomorJurnalService::nextNoJP(),
                            'tgl_transaksi' => $tglTransaksi,
                            'jenis_transaksi' => $jenisTransaksi,
                            'modul_asal' => $modulAsal,
                            'jurnal' => $noJurnal,
                            'no_akun' => $grup['no_akun'],
                            'nama_akun' => $grup['nama_akun'],
                            'map' => $baris->posisi,
                            'keterangan' => "{$ketHeader} | Nota: {$noDokumen}".($catatanBaris !== '' ? " ({$catatanBaris})" : ''),
                            'no_dokumen' => $noDokumen,
                            'total_nilai' => $totalNominalGrup,
                            'status' => JurnalPembantuHeader::STATUS_DRAFT,
                            'dibuat_oleh' => $userId,
                        ]);

                        $urutItem = 1;
                        foreach ($items as $it) {
                            $dataItem = [
                                'jurnal_pembantu_header_id' => $headerBarang->id,
                                'urut' => $urutItem++,
                                'jenis_pihak' => $jenisPihak,
                                'nama_pihak' => $namaPihak,
                                'pihak_id' => $pihakId,
                                'nama_barang' => $it['nama_barang'],
                                'no_dokumen' => $noDokumen,
                                'keterangan' => $it['ket_item'],
                                'banyak' => $it['banyak'],
                                'm3' => $it['m3'] ?? 0,
                                'harga' => $it['harga'],
                                'hit_kbk' => $it['hit_kbk'] ?? 'b',
                                'jumlah' => $it['nominal'],
                                'status' => true,
                                'created_by' => $userId,
                            ];

                            if (! empty($it['id_barang'])) {
                                $dataItem['id_barang'] = $it['id_barang'];
                            }

                            JurnalPembantuItem::create($dataItem);
                        }

                        $headersDibuat->push($headerBarang);
                    }

                    continue; // sudah selesai untuk baris ini, lanjut ke baris berikutnya
                }

                if (! empty($breakdown)) {
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
                    $ketBaris .= ' '.$labelBarang;
                }

                $catatanBaris = trim((string) ($catatanPerVariabel[$baris->variabel_nilai] ?? ''));

                $header = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => NomorJurnalService::nextNoJP(),
                    'tgl_transaksi' => $tglTransaksi,
                    'jenis_transaksi' => $jenisTransaksi,
                    'modul_asal' => $modulAsal,
                    'jurnal' => $noJurnal,
                    'no_akun' => $baris->no_akun,
                    'nama_akun' => $this->resolveNamaAkun($baris->no_akun, $baris->nama_akun),
                    'map' => $baris->posisi,
                    'keterangan' => "{$ketBaris} | Nota: {$noDokumen}".($catatanBaris !== '' ? " ({$catatanBaris})" : ''),
                    'no_dokumen' => $noDokumen,
                    'total_nilai' => $nominal,
                    'status' => JurnalPembantuHeader::STATUS_DRAFT,
                    'dibuat_oleh' => $userId,
                ]);

                if (! empty($breakdown)) {
                    $urutItem = 1;
                    foreach ($breakdown as $b) {
                        $banyakItem = (float) ($b['banyak'] ?? 0);
                        $hargaItem = (float) ($b['harga'] ?? 0);
                        $nominalItem = $this->nominalBreakdown($b);

                        $dataItem = [
                            'jurnal_pembantu_header_id' => $header->id,
                            'urut' => $urutItem++,
                            'jenis_pihak' => $jenisPihak,
                            'nama_pihak' => $namaPihak,
                            'pihak_id' => $pihakId,
                            'nama_barang' => $b['nama_barang'] ?? null,
                            'no_dokumen' => $noDokumen,
                            'keterangan' => $b['keterangan'] ?? ($b['nama_barang'] ?? $ketBaris),
                            'banyak' => $banyakItem,
                            'm3' => (float) ($b['m3'] ?? 0),
                            'harga' => $hargaItem,
                            'hit_kbk' => $b['hit_kbk'] ?? 'b',
                            'jumlah' => $nominalItem,
                            'status' => true,
                            'created_by' => $userId,
                        ];

                        if (! empty($b['id_barang'])) {
                            $dataItem['id_barang'] = $b['id_barang'];
                        }

                        JurnalPembantuItem::create($dataItem);
                    }
                } else {
                    JurnalPembantuItem::create([
                        'jurnal_pembantu_header_id' => $header->id,
                        'urut' => 1,
                        'jenis_pihak' => $jenisPihak,
                        'nama_pihak' => $namaPihak,
                        'pihak_id' => $pihakId,
                        'no_dokumen' => $noDokumen,
                        'keterangan' => $baris->keterangan ?: $keteranganDefault,
                        'banyak' => 1,
                        'm3' => 0,
                        'harga' => $nominal,
                        'hit_kbk' => 'b', // banyak(1) x harga(nominal) = nominal
                        'jumlah' => $nominal,
                        'status' => true,
                        'created_by' => $userId,
                    ]);
                }

                $headersDibuat->push($header);
            }

            if (! $adaBarisTerpakai) {
                throw new \RuntimeException(
                    "Template Buku Kitab '{$kodeKitab}' tidak menghasilkan satupun baris jurnal - ".
                    'semua nilai di $context adalah 0 atau kosong. Cek lagi data transaksi yang dikirim.'
                );
            }

            return $headersDibuat;
        });
    }

    /**
     * Nilai rupiah satu baris breakdown.
     * hit_kbk = 'm' -> m3 x harga (harga = harga PER M3);
     * selain itu -> banyak x harga.
     */
    private function nominalBreakdown(array $b): float
    {
        $harga = (float) ($b['harga'] ?? 0);

        if (($b['hit_kbk'] ?? 'b') === 'm') {
            return round((float) ($b['m3'] ?? 0) * $harga, 4);
        }

        return round((float) ($b['banyak'] ?? 0) * $harga, 4);
    }
}
