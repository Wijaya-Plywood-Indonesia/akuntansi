<?php

namespace App\Services;

use App\Models\AnakAkun;
use App\Models\Barang;
use App\Models\IndukAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Penjualan;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service Jurnal Pembantu — Penjualan
 */
class JurnalPenjualanTelurService
{
    const KODE_KAS = '1101.1';

    /**
     * Kode akun PPN Keluaran. Taruh di sini biar gampang diubah kalau
     * suatu saat kodenya berubah — dipakai oleh buatJurnalPpn() di bagian
     * paling bawah file ini (section PPN terpisah dari section Telur/Barang Lain).
     */
    const KODE_PPN_KELUARAN = '2191.1';

    const KG_PER_PETI = 10;

    const HARGA_PETI_DEFAULT = 6000;

    private array $akunCache = [];

    /**
     * Daftar prefix kode akun yang PERLU di-split per barang (id_barang) —
     * artinya: kalau 2 produk berbeda kebetulan resolve ke akun yang sama
     * TAPI kode akunnya mengandung salah satu prefix di bawah, mereka TETAP
     * dipisah jadi header/pasang jurnal sendiri-sendiri (tidak di-merge).
     * Akun di luar daftar ini tetap di-merge seperti biasa (1 header per
     * kode akun, item-nya tetap terpisah per produk di dalam header itu).
     *
     * 140 = akun Persediaan/Stok.
     * 506 = akun "Selisih harga patok produksi" (HPP), perlu dipisah per
     *       barang karena nilainya spesifik per produk.
     */
    private const KODE_AKUN_SPLIT_PER_BARANG = ['140', '506'];

    /**
     * Cek apakah kode akun tertentu masuk daftar yang perlu di-split per barang.
     */
    private function haruSplitPerBarang(string $kodeAkun): bool
    {
        foreach (self::KODE_AKUN_SPLIT_PER_BARANG as $prefix) {
            if (str_contains($kodeAkun, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bangun kunci groupBy untuk satu detail transaksi ($d) di bawah akun
     * $kodeAkun. Kalau $kodeAkun masuk daftar KODE_AKUN_SPLIT_PER_BARANG,
     * kuncinya kode_akun+barang_id (dipisah per produk). Kalau tidak, kuncinya
     * cuma kode_akun saja (di-merge seperti biasa).
     */
    private function kunciGrupAkun(string $kodeAkun, $d): string
    {
        if ($this->haruSplitPerBarang($kodeAkun)) {
            return $kodeAkun.'::'.($d->barang_id ?? $d->nama_barang);
        }

        return $kodeAkun;
    }

    /**
     * Prefix kode akun yang BOLEH membawa id_barang di item jurnal (akun
     * Persediaan/Stok, mis. 1401.1, 1402, 1404.2, dst). Ini SENGAJA terpisah
     * dari KODE_AKUN_SPLIT_PER_BARANG di atas — akun 506 (Selisih harga
     * patok produksi) perlu di-split per barang untuk urusan header/merge,
     * TAPI item-nya tetap TIDAK boleh membawa id_barang.
     */
    private const KODE_AKUN_BOLEH_ID_BARANG = '140';

    /**
     * Menghapus id_barang dari data item kecuali kode akun tujuan MENGANDUNG
     * KODE_AKUN_BOLEH_ID_BARANG (140). Ini berbeda dari haruSplitPerBarang()
     * yang menentukan apakah header di-split atau di-merge.
     */
    private function idBarangJikaAkunPersediaan(array $data, string $kodeAkun): array
    {
        if (! str_contains($kodeAkun, self::KODE_AKUN_BOLEH_ID_BARANG)) {
            unset($data['id_barang']);
        }

        return $data;
    }

    /**
     * BARU: registry header Kas per kode akun, dipakai bersama oleh bagian
     * Telur, Barang Lain, dan PPN supaya baris "D: Kas ..." untuk 1 nota
     * TIDAK dipecah jadi beberapa header (mis. 1 header utk penjualan +
     * 1 header lagi utk PPN). Semuanya digabung jadi 1 header per kode akun
     * kas/bank, item-nya tinggal ditambahkan urut berikutnya.
     *
     * Dipanggil dari 3 tempat: blok Kas Telur, blok Kas Barang Lain, dan
     * buatJurnalPpn(). $registry di-passing by reference supaya konsisten
     * sepanjang 1 transaksi (1 $noJurnal).
     */
    private function tulisKas(
        array &$registry,
        array $akun,
        int $noJurnal,
        string $tgl,
        string $keterangan,
        string $nota,
        int $userId,
        array $item
    ): void {
        $kode = $akun['kode'];

        if (! isset($registry[$kode])) {
            $registry[$kode] = [
                'header' => $this->buatHeader([
                    'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                    'tgl_transaksi' => $tgl,
                    'jenis_transaksi' => 'bk',
                    'modul_asal' => 'penjualan_telur',
                    'jurnal' => $noJurnal,
                    'no_akun' => $akun['kode'],
                    'nama_akun' => $akun['nama'],
                    'map' => 'd',
                    'keterangan' => $keterangan,
                    'no_dokumen' => $nota,
                    'dibuat_oleh' => $userId,
                ]),
                'urut' => 0,
            ];
        }

        $registry[$kode]['urut']++;
        $item['urut'] = $registry[$kode]['urut'];

        $this->buatItem($registry[$kode]['header']->id, $item);
    }

    public function buatJurnalDariPenjualan(Penjualan $penjualan, int $userId): void
    {
        $penjualan->loadMissing([
            'details.barang.subAnakAkun',
            'details.barang.akunPendapatan',
            'details.barang.akunHpp',
            'rekeningPerusahaan.subAnakAkun',
        ]);

        $itemTelur = collect();
        $itemLain = collect();

        foreach ($penjualan->details as $detail) {
            if (! $detail->barang) {
                // FIX: sebelumnya continue diam-diam, sekarang di-log supaya
                // ketahuan kalau ada detail penjualan yang barang_id-nya
                // tidak match ke tabel barang (barang terhapus / null / typo id).
                Log::warning('[JurnalPenjualan] Detail dilewati karena barang tidak ditemukan.', [
                    'penjualan_id' => $penjualan->id,
                    'no_nota' => $penjualan->no_nota,
                    'detail_id' => $detail->id,
                    'nama_barang' => $detail->nama_barang,
                    'barang_id' => $detail->barang_id,
                ]);

                continue;
            }

            $nama = strtolower($detail->nama_barang ?? '');

            if ($this->isTelur($nama)) {
                $itemTelur->push($detail);
            } else {
                $itemLain->push($detail);
            }
        }

        if ($itemTelur->isEmpty() && $itemLain->isEmpty()) {
            Log::warning('[JurnalPenjualan] Tidak ada item valid untuk dibuatkan jurnal.', [
                'penjualan_id' => $penjualan->id,
                'no_nota' => $penjualan->no_nota,
                'total_detail' => $penjualan->details->count(),
            ]);

            return;
        }

        // Sanity log: bandingkan jumlah detail asli vs yang diproses.
        $totalDiproses = $itemTelur->count() + $itemLain->count();
        if ($totalDiproses !== $penjualan->details->count()) {
            Log::warning('[JurnalPenjualan] Jumlah item yang diproses tidak sama dengan jumlah detail penjualan.', [
                'penjualan_id' => $penjualan->id,
                'no_nota' => $penjualan->no_nota,
                'total_detail' => $penjualan->details->count(),
                'total_diproses' => $totalDiproses,
            ]);
        }

        DB::transaction(function () use ($penjualan, $itemTelur, $itemLain, $userId) {

            $tgl = $penjualan->tanggal->toDateString();
            $nota = $penjualan->no_nota;
            $customer = $penjualan->nama_customer ?: 'Pelanggan';
            $noJurnal = $this->nextNomorJurnal();

            // BARU: registry header Kas, dipakai bersama Telur + Barang Lain + PPN
            // supaya baris D: Kas untuk 1 nota tidak kepecah jadi beberapa header.
            $kasRegistry = [];

            // ════════════════════════════════════════════════════════
            // BAGIAN TELUR
            // ════════════════════════════════════════════════════════
            if ($itemTelur->isNotEmpty()) {

                $totalTelur = $itemTelur->sum('subtotal');
                $totalHpp = $itemTelur->sum(
                    fn ($d) => (float) $d->qty * (float) ($d->barang->harga_beli ?? 0)
                );

                // 1. Hitung peti dari telur kiloan
                $totalKiloan = $itemTelur
                    ->filter(fn ($d) => $this->isKiloan(strtolower($d->nama_barang ?? '')))
                    ->sum('qty');
                $petiDariKiloan = ($totalKiloan > 0 && $totalKiloan % self::KG_PER_PETI === 0)
                    ? (int) ($totalKiloan / self::KG_PER_PETI) : 0;

                // 2. Hitung peti dari telur petian
                $petiDariPetian = $itemTelur
                    ->filter(fn ($d) => str_contains(strtolower($d->nama_barang ?? ''), 'petian'))
                    ->sum('qty');

                // 3. Gabungkan total peti
                $jumlahPeti = $petiDariKiloan + $petiDariPetian;

                $ketJual = $this->ket('Penjualan', $nota, $customer);
                $barisKas = $this->resolveBarisKas($penjualan, $totalTelur);

                // Di bagian D: Kas — Bagian Telur
                // BARU: pakai tulisKas() -> gabung ke header Kas yang sama
                // (dipakai bareng blok Barang Lain & PPN di bawah), bukan
                // bikin header sendiri per kelompok.
                foreach ($barisKas as $kas) {
                    $akunKas = ['kode' => $kas['kode'], 'nama' => $kas['nama']];

                    $list = $itemTelur->values();
                    $lastIndex = $list->count() - 1;
                    $sudahTersimpan = 0.0;

                    foreach ($list as $i => $d) {
                        $jumlahEksplisit = null;

                        if ($i === $lastIndex) {

                            $sisa = round($kas['nominal'] - $sudahTersimpan, 4);
                            $jumlahEksplisit = $sisa;
                            $harga = $d->qty > 0 ? round($sisa / (float) $d->qty, 4) : 0;
                        } else {
                            // Hitung harga bersih dengan proporsi potongan diskon
                            $hargaBersihDetail = $d->qty > 0 ? (float) $d->subtotal / (float) $d->qty : 0;
                            $harga = round($hargaBersihDetail * $kas['proporsi'], 4);
                            $sudahTersimpan += round((float) $d->qty * $harga, 4);
                        }

                        $this->tulisKas($kasRegistry, $akunKas, $noJurnal, $tgl, $ketJual, $nota, $userId, array_filter([
                            'jenis_pihak' => 'pelanggan',
                            'nama_pihak' => $customer,
                            'nama_barang' => $d->nama_barang,
                            // id_barang TIDAK diisi untuk baris Kas (bukan pergerakan barang).
                            'no_dokumen' => $nota,
                            'no_referensi' => (string) $d->id,
                            'keterangan' => $d->nama_barang.' '.$d->qty.' '.($d->satuan ?? ''),
                            'banyak' => $d->qty,
                            'harga' => $harga,
                            'jumlah' => $jumlahEksplisit, // null di-strip oleh array_filter, biar buatItem() hitung sendiri utk item non-terakhir
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ], fn ($v) => $v !== null));
                    }
                }

                // ── K: Pendapatan per akun pendapatan ────────────────────────
                // Merge biasa berdasarkan kode akun, KECUALI akun tersebut masuk
                // daftar KODE_AKUN_SPLIT_PER_BARANG (140, 506, dst) -> tetap dipisah per barang.
                $perPend = $itemTelur->groupBy(
                    fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[0], $d)
                );

                foreach ($perPend as $groupKey => $details) {
                    $kodePend = $this->kodePerJenis($details->first()->barang)[0];
                    $akunPend = $this->resolveAkun($kodePend);
                    $ketPendGrup = $this->ketGrup('Penjualan', $nota, $customer, $details);

                    $hPend = $this->buatHeader([
                        'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                        'tgl_transaksi' => $tgl,
                        'jenis_transaksi' => 'bk',
                        'modul_asal' => 'penjualan_telur',
                        'jurnal' => $noJurnal,
                        'no_akun' => $akunPend['kode'],
                        'nama_akun' => $akunPend['nama'],
                        'map' => 'k',
                        'keterangan' => $ketPendGrup,
                        'no_dokumen' => $nota,
                        'dibuat_oleh' => $userId,
                    ]);
                    $urut = 1;
                    foreach ($details as $d) {
                        // Hitung nilai real/bersih setelah potongan
                        $hargaBersih = $d->qty > 0 ? round((float) $d->subtotal / (float) $d->qty, 4) : 0;

                        $this->buatItem($hPend->id, $this->idBarangJikaAkunPersediaan([
                            'urut' => $urut++,
                            'jenis_pihak' => 'pelanggan',
                            'nama_pihak' => $customer,
                            'nama_barang' => $d->nama_barang,
                            'id_barang' => $d->barang_id,
                            'no_dokumen' => $nota,
                            'no_referensi' => (string) $d->id,
                            'keterangan' => $d->nama_barang.' '.$d->qty.' '.($d->satuan ?? ''),
                            'banyak' => $d->qty,
                            'harga' => $hargaBersih, // Tidak pakai $d->harga_jual mentah
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ], $akunPend['kode']));
                    }
                }

                // ── D: HPP & K: Persediaan ──
                if ($totalHpp > 0) {
                    // Merge biasa berdasarkan kode akun, KECUALI masuk daftar
                    // KODE_AKUN_SPLIT_PER_BARANG (140, 506, dst) -> tetap dipisah per barang.
                    $perHpp = $itemTelur->groupBy(
                        fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[1], $d)
                    );

                    foreach ($perHpp as $groupKeyHpp => $detailsHpp) {
                        $adaHpp = $detailsHpp->filter(
                            fn ($d) => (float) ($d->barang->harga_beli ?? 0) > 0
                        );
                        if ($adaHpp->isEmpty()) {
                            continue;
                        }

                        $ketHpp = $this->ketGrup('HPP Penjualan', $nota, null, $adaHpp);

                        $kodeHpp = $this->kodePerJenis($adaHpp->first()->barang)[1];
                        $akunHpp = $this->resolveAkun($kodeHpp);
                        $hHpp = $this->buatHeader([
                            'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                            'tgl_transaksi' => $tgl,
                            'jenis_transaksi' => 'bk',
                            'modul_asal' => 'penjualan_telur',
                            'jurnal' => $noJurnal,
                            'no_akun' => $akunHpp['kode'],
                            'nama_akun' => $akunHpp['nama'],
                            'map' => 'd',
                            'keterangan' => $ketHpp,
                            'no_dokumen' => $nota,
                            'dibuat_oleh' => $userId,
                        ]);
                        $urut = 1;
                        foreach ($adaHpp as $d) {
                            $this->buatItem($hHpp->id, $this->idBarangJikaAkunPersediaan([
                                'urut' => $urut++,
                                'nama_barang' => $d->nama_barang,
                                'id_barang' => $d->barang_id,
                                'no_dokumen' => $nota,
                                'no_referensi' => (string) $d->id,
                                'keterangan' => 'HPP '.$d->nama_barang,
                                'banyak' => $d->qty,
                                'harga' => $d->barang->harga_beli,
                                'created_by' => $userId,
                                'updated_by' => $userId,
                            ], $akunHpp['kode']));
                        }

                        // Split per barang otomatis kalau akun persediaan masuk daftar
                        // KODE_AKUN_SPLIT_PER_BARANG (140, dst).
                        $perPers = $adaHpp->groupBy(
                            fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[2], $d)
                        );

                        foreach ($perPers as $groupKeyPers => $detailsPers) {
                            $ketPers = $this->ketGrup('HPP Penjualan', $nota, null, $detailsPers);
                            $kodePers = $this->kodePerJenis($detailsPers->first()->barang)[2];
                            $akunPers = $this->resolveAkun($kodePers);
                            $hPers = $this->buatHeader([
                                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                                'tgl_transaksi' => $tgl,
                                'jenis_transaksi' => 'bk',
                                'modul_asal' => 'penjualan_telur',
                                'jurnal' => $noJurnal,
                                'no_akun' => $akunPers['kode'],
                                'nama_akun' => $akunPers['nama'],
                                'map' => 'k',
                                'keterangan' => $ketPers,
                                'no_dokumen' => $nota,
                                'dibuat_oleh' => $userId,
                            ]);
                            $urut = 1;
                            foreach ($detailsPers as $d) {
                                $this->buatItem($hPers->id, $this->idBarangJikaAkunPersediaan([
                                    'urut' => $urut++,
                                    'nama_barang' => $d->nama_barang,
                                    'id_barang' => $d->barang_id,
                                    'no_dokumen' => $nota,
                                    'no_referensi' => (string) $d->id,
                                    'keterangan' => 'Keluar stok '.$d->nama_barang,
                                    'banyak' => $d->qty,
                                    'harga' => $d->barang->harga_beli,
                                    'created_by' => $userId,
                                    'updated_by' => $userId,
                                ], $akunPers['kode']));
                            }
                        }
                    }
                }

                // ── Peti otomatis ─────────
                if ($jumlahPeti > 0) {
                    $ketPeti = $this->ket('Konversi Peti Telur', $nota, $customer);

                    $brgPetiKosong = Barang::with('subAnakAkun')->where('nama_barang', 'like', '%peti%kosong%')->first();
                    $brgPetiIsi = Barang::with('subAnakAkun')->where('nama_barang', 'like', '%peti%isi%telur%')->first();

                    $kodePetiKosong = $brgPetiKosong?->subAnakAkun?->kode_sub_anak_akun ?? '1600-01';
                    $hargaPetiKosong = (float) ($brgPetiKosong?->harga_beli ?? self::HARGA_PETI_DEFAULT);

                    $kodePetiIsi = $brgPetiIsi?->subAnakAkun?->kode_sub_anak_akun ?? '1600-02';
                    $hargaPetiIsi = (float) ($brgPetiIsi?->harga_beli ?? self::HARGA_PETI_DEFAULT);

                    // D: Peti Kosong
                    $akunPetiKosong = $this->resolveAkun($kodePetiKosong);
                    $hPetiKosong = $this->buatHeader([
                        'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                        'tgl_transaksi' => $tgl,
                        'jenis_transaksi' => 'bk',
                        'modul_asal' => 'penjualan_telur',
                        'jurnal' => $noJurnal,
                        'no_akun' => $akunPetiKosong['kode'],
                        'nama_akun' => $akunPetiKosong['nama'],
                        'map' => 'd',
                        'keterangan' => $ketPeti,
                        'no_dokumen' => $nota,
                        'dibuat_oleh' => $userId,
                    ]);
                    $this->buatItem($hPetiKosong->id, $this->idBarangJikaAkunPersediaan([
                        'urut' => 1,
                        'nama_barang' => $brgPetiKosong?->nama_barang ?? 'Peti Kosong',
                        'id_barang' => $brgPetiKosong?->id,
                        'no_dokumen' => $nota,
                        'keterangan' => 'Masuk stok peti kosong '.$jumlahPeti.' pcs',
                        'banyak' => $jumlahPeti,
                        'harga' => $hargaPetiKosong,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ], $akunPetiKosong['kode']));

                    // K: Peti Isi
                    $akunPetiIsi = $this->resolveAkun($kodePetiIsi);
                    $hPetiIsi = $this->buatHeader([
                        'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                        'tgl_transaksi' => $tgl,
                        'jenis_transaksi' => 'bk',
                        'modul_asal' => 'penjualan_telur',
                        'jurnal' => $noJurnal,
                        'no_akun' => $akunPetiIsi['kode'],
                        'nama_akun' => $akunPetiIsi['nama'],
                        'map' => 'k',
                        'keterangan' => $ketPeti,
                        'no_dokumen' => $nota,
                        'dibuat_oleh' => $userId,
                    ]);
                    $this->buatItem($hPetiIsi->id, $this->idBarangJikaAkunPersediaan([
                        'urut' => 1,
                        'nama_barang' => $brgPetiIsi?->nama_barang ?? 'Peti Isi Telur',
                        'id_barang' => $brgPetiIsi?->id,
                        'no_dokumen' => $nota,
                        'keterangan' => 'Keluar stok peti isi telur '.$jumlahPeti.' pcs',
                        'banyak' => $jumlahPeti,
                        'harga' => $hargaPetiIsi,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ], $akunPetiIsi['kode']));
                }
            }

            // ════════════════════════════════════════════════════════
            // BAGIAN BARANG LAIN (Ayam, Pakan, Kayu, dll)
            // ════════════════════════════════════════════════════════
            if ($itemLain->isNotEmpty()) {

                // FIX BUG BALANCE: sebelumnya $barisKas dihitung SEKALI di sini
                // pakai $totalLain (jumlah SEMUA produk non-telur digabung), lalu
                // dipakai lagi untuk tiap grup produk di dalam loop di bawah.
                // Akibatnya target nominal Kas tiap grup memakai nilai TOTAL
                // GABUNGAN semua produk, bukan porsi produk itu sendiri -> jurnal
                // jadi tidak balance (Kas salah satu produk kebesaran, produk lain
                // kekecilan/hilang). Sekarang $barisKas dihitung ULANG di DALAM
                // loop per grup, memakai subtotal grup itu sendiri saja.

                // Merge biasa berdasarkan kode akun, KECUALI masuk daftar
                // KODE_AKUN_SPLIT_PER_BARANG (140, 506, dst) -> tetap dipisah per barang.
                $perJenisLain = $itemLain->groupBy(
                    fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[0], $d)
                );

                foreach ($perJenisLain as $groupKeyLain => $details) {
                    $ketLain = $this->ketGrup('Penjualan', $nota, $customer, $details);
                    $kodePend = $this->kodePerJenis($details->first()->barang)[0];
                    $akunPend = $this->resolveAkun($kodePend);

                    // FIX: nominal Kas dihitung dari subtotal GRUP INI SAJA,
                    // bukan dari total seluruh itemLain.
                    $totalGrupIni = $details->sum('subtotal');
                    $barisKas = $this->resolveBarisKas($penjualan, $totalGrupIni);

                    // BARU: pakai tulisKas() -> gabung ke header Kas yang sama
                    // (dipakai bareng blok Telur & PPN), bukan bikin header sendiri.
                    foreach ($barisKas as $kas) {
                        $akunKas = ['kode' => $kas['kode'], 'nama' => $kas['nama']];

                        $list = $details->values();
                        $lastIndex = $list->count() - 1;
                        $sudahTersimpan = 0.0;

                        foreach ($list as $i => $d) {
                            if ($i === $lastIndex) {
                                $harga = $d->qty > 0
                                    ? round(($kas['nominal'] - $sudahTersimpan) / (float) $d->qty, 4)
                                    : 0;
                            } else {
                                $hargaBersihDetail = $d->qty > 0 ? (float) $d->subtotal / (float) $d->qty : 0;
                                $harga = round($hargaBersihDetail * $kas['proporsi'], 4);
                                $sudahTersimpan += round((float) $d->qty * $harga, 4);
                            }

                            $this->tulisKas($kasRegistry, $akunKas, $noJurnal, $tgl, $ketLain, $nota, $userId, [
                                'jenis_pihak' => 'pelanggan',
                                'nama_pihak' => $customer,
                                'nama_barang' => $d->nama_barang,
                                // id_barang TIDAK diisi untuk baris Kas (lihat
                                // penjelasan pada blok Kas bagian Telur di atas).
                                'no_dokumen' => $nota,
                                'no_referensi' => (string) $d->id,
                                'keterangan' => $d->nama_barang.' '.$d->qty.' '.($d->satuan ?? ''),
                                'banyak' => $d->qty,
                                'harga' => $harga,
                                'created_by' => $userId,
                                'updated_by' => $userId,
                            ]);
                        }
                    }

                    // K: Pendapatan
                    $hPend = $this->buatHeader([
                        'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                        'tgl_transaksi' => $tgl,
                        'jenis_transaksi' => 'bk',
                        'modul_asal' => 'penjualan_telur',
                        'jurnal' => $noJurnal,
                        'no_akun' => $akunPend['kode'],
                        'nama_akun' => $akunPend['nama'],
                        'map' => 'k',
                        'keterangan' => $ketLain,
                        'no_dokumen' => $nota,
                        'dibuat_oleh' => $userId,
                    ]);
                    $urut = 1;
                    foreach ($details as $d) {
                        // Menangani Potongan/Diskon
                        $hargaBersih = $d->qty > 0 ? round((float) $d->subtotal / (float) $d->qty, 4) : 0;

                        $this->buatItem($hPend->id, $this->idBarangJikaAkunPersediaan([
                            'urut' => $urut++,
                            'jenis_pihak' => 'pelanggan',
                            'nama_pihak' => $customer,
                            'nama_barang' => $d->nama_barang,
                            'id_barang' => $d->barang_id,
                            'no_dokumen' => $nota,
                            'no_referensi' => (string) $d->id,
                            'keterangan' => $d->nama_barang.' '.$d->qty.' '.($d->satuan ?? ''),
                            'banyak' => $d->qty,
                            'harga' => $hargaBersih,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ], $akunPend['kode']));
                    }

                    // D: HPP & K: Persediaan
                    $adaHpp = $details->filter(
                        fn ($d) => (float) ($d->barang->harga_beli ?? 0) > 0
                    );

                    if ($adaHpp->isNotEmpty()) {
                        // Merge biasa berdasarkan kode akun, KECUALI masuk daftar
                        // KODE_AKUN_SPLIT_PER_BARANG (140, 506, dst) -> tetap dipisah per barang.
                        $perHppLain = $adaHpp->groupBy(
                            fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[1], $d)
                        );

                        foreach ($perHppLain as $groupKeyHppLain => $detailsHpp) {
                            $ketHpp = $this->ketGrup('HPP', $nota, null, $detailsHpp);
                            $kodeHpp = $this->kodePerJenis($detailsHpp->first()->barang)[1];
                            $akunHpp = $this->resolveAkun($kodeHpp);
                            $hHpp = $this->buatHeader([
                                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                                'tgl_transaksi' => $tgl,
                                'jenis_transaksi' => 'bk',
                                'modul_asal' => 'penjualan_telur',
                                'jurnal' => $noJurnal,
                                'no_akun' => $akunHpp['kode'],
                                'nama_akun' => $akunHpp['nama'],
                                'map' => 'd',
                                'keterangan' => $ketHpp,
                                'no_dokumen' => $nota,
                                'dibuat_oleh' => $userId,
                            ]);
                            $urut = 1;
                            foreach ($detailsHpp as $d) {
                                $this->buatItem($hHpp->id, $this->idBarangJikaAkunPersediaan([
                                    'urut' => $urut++,
                                    'nama_barang' => $d->nama_barang,
                                    'id_barang' => $d->barang_id,
                                    'no_dokumen' => $nota,
                                    'no_referensi' => (string) $d->id,
                                    'keterangan' => 'HPP '.$d->nama_barang,
                                    'banyak' => $d->qty,
                                    'harga' => $d->barang->harga_beli,
                                    'created_by' => $userId,
                                    'updated_by' => $userId,
                                ], $akunHpp['kode']));
                            }

                            // Split per barang otomatis kalau akun persediaan masuk daftar
                            // KODE_AKUN_SPLIT_PER_BARANG (140, dst).
                            $perPersLain = $detailsHpp->groupBy(
                                fn ($d) => $this->kunciGrupAkun($this->kodePerJenis($d->barang)[2], $d)
                            );

                            foreach ($perPersLain as $groupKeyPersLain => $detailsPers) {
                                $ketPers = $this->ketGrup('HPP', $nota, null, $detailsPers);
                                $kodePers = $this->kodePerJenis($detailsPers->first()->barang)[2];
                                $akunPers = $this->resolveAkun($kodePers);
                                $hPers = $this->buatHeader([
                                    'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                                    'tgl_transaksi' => $tgl,
                                    'jenis_transaksi' => 'bk',
                                    'modul_asal' => 'penjualan_telur',
                                    'jurnal' => $noJurnal,
                                    'no_akun' => $akunPers['kode'],
                                    'nama_akun' => $akunPers['nama'],
                                    'map' => 'k',
                                    'keterangan' => $ketPers,
                                    'no_dokumen' => $nota,
                                    'dibuat_oleh' => $userId,
                                ]);
                                $urut = 1;
                                foreach ($detailsPers as $d) {
                                    $this->buatItem($hPers->id, $this->idBarangJikaAkunPersediaan([
                                        'urut' => $urut++,
                                        'nama_barang' => $d->nama_barang,
                                        'id_barang' => $d->barang_id,
                                        'no_dokumen' => $nota,
                                        'no_referensi' => (string) $d->id,
                                        'keterangan' => 'Keluar stok '.$d->nama_barang,
                                        'banyak' => $d->qty,
                                        'harga' => $d->barang->harga_beli,
                                        'created_by' => $userId,
                                        'updated_by' => $userId,
                                    ], $akunPers['kode']));
                                }
                            }
                        }
                    }
                }
            }

            // ════════════════════════════════════════════════════════
            // BAGIAN PPN — logic-nya ditaruh terpisah di method
            // buatJurnalPpn() (lihat bagian bawah file). Sengaja dipisah
            // karena section ini kemungkinan sering direvisi ke depannya.
            // BARU: $kasRegistry di-passing biar baris D: Kas PPN nyambung
            // ke header Kas yang sudah dibuat di blok Telur/Barang Lain
            // di atas (1 nota -> 1 header Kas per kode akun kas/bank).
            // ════════════════════════════════════════════════════════
            $this->buatJurnalPpn($penjualan, $tgl, $nota, $customer, $noJurnal, $userId, $kasRegistry);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // RESOLVE KAS
    // ══════════════════════════════════════════════════════════════

    private function resolveBarisKas(Penjualan $penjualan, float $totalNilai): array
    {
        $bayarTunai = (float) ($penjualan->bayar_tunai ?? 0);
        $bayarTransfer = (float) ($penjualan->bayar_transfer ?? 0);
        $total = $bayarTunai + $bayarTransfer;

        if ($total <= 0) {
            $metode = strtolower($penjualan->metode_pembayaran ?? 'tunai');
            $bayarTunai = $metode !== 'transfer' ? $totalNilai : 0;
            $bayarTransfer = $metode === 'transfer' ? $totalNilai : 0;
            $total = $totalNilai;
        }

        $baris = [];

        if ($bayarTunai > 0) {
            $akun = $this->resolveAkun(self::KODE_KAS);
            $proporsi = $bayarTunai / $total;
            $baris[] = [
                'kode' => $akun['kode'],
                'nama' => $akun['nama'],
                'proporsi' => $proporsi,
                // Gunakan proporsi dikali Grand Total Net,
                // mencegah nominal kas bengkak ketika uang fisik melebihi tagihan (ada kembalian)
                'nominal' => $proporsi * $totalNilai,
            ];
        }

        if ($bayarTransfer > 0) {
            $kodeBank = $penjualan->rekeningPerusahaan?->subAnakAkun?->kode_sub_anak_akun;
            if (! $kodeBank) {
                Log::warning("[JurnalPenjualan] Rekening transfer {$penjualan->no_rekening} belum di-mapping.");
                $kodeBank = self::KODE_KAS;
            }

            $akun = $this->resolveAkun($kodeBank);
            $proporsi = $bayarTransfer / $total;
            $baris[] = [
                'kode' => $akun['kode'],
                'nama' => $akun['nama'],
                'proporsi' => $proporsi,
                'nominal' => $proporsi * $totalNilai,
            ];
        }

        return $baris;
    }

    // ══════════════════════════════════════════════════════════════
    // RESOLVER AKUN
    // ══════════════════════════════════════════════════════════════

    private function resolveAkun(string $kode): array
    {
        if (isset($this->akunCache[$kode])) {
            return $this->akunCache[$kode];
        }

        $sub = SubAnakAkun::where('kode_sub_anak_akun', $kode)->where('status', 'aktif')->first();
        if ($sub) {
            return $this->akunCache[$kode] = ['kode' => $sub->kode_sub_anak_akun, 'nama' => $sub->nama_sub_anak_akun];
        }

        $anak = AnakAkun::where('kode_anak_akun', $kode)->where('status', 'aktif')->first();
        if ($anak) {
            return $this->akunCache[$kode] = ['kode' => $anak->kode_anak_akun, 'nama' => $anak->nama_anak_akun];
        }

        $induk = IndukAkun::where('kode_induk_akun', $kode)->where('status', 'aktif')->first();
        if ($induk) {
            return $this->akunCache[$kode] = ['kode' => $induk->kode_induk_akun, 'nama' => $induk->nama_induk_akun];
        }

        Log::warning("[JurnalPenjualan] Kode akun tidak ditemukan: {$kode}");

        return $this->akunCache[$kode] = [
            'kode' => $kode,
            'nama' => '⚠ Akun tidak ditemukan: '.$kode,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    private function isTelur(string $namaLower): bool
    {
        return str_contains($namaLower, 'telur') || str_contains($namaLower, '_butir')
            || str_contains($namaLower, '_kilo') || str_contains($namaLower, '_kg')
            || str_contains($namaLower, 'petian') || str_contains($namaLower, 'bentes');
    }

    private function isKiloan(string $namaLower): bool
    {
        if (str_contains($namaLower, 'bentes') || str_contains($namaLower, 'petian') || str_contains($namaLower, '_butir')) {
            return false;
        }

        return str_contains($namaLower, 'kilo') || str_contains($namaLower, '_kg')
            || str_contains($namaLower, 'telur ruko') || str_contains($namaLower, 'telur_ruko');
    }

    private function kodePerJenis(?Barang $barang = null): array
    {
        $kodePend = $barang?->akunPendapatan?->kode_sub_anak_akun ?: '4100-01';
        $kodeHpp = $barang?->akunHpp?->kode_sub_anak_akun ?: '6000-01';
        $kodePers = $barang?->subAnakAkun?->kode_sub_anak_akun ?: '1411-00';

        return [$kodePend, $kodeHpp, $kodePers];
    }

    private function ket(string $prefix, string $nota, ?string $customer = null): string
    {
        $k = $prefix.' | No.Nota: '.$nota;
        if ($customer) {
            $k .= ' | '.$customer;
        }

        return $k;
    }

    /**
     * FIX BARU: Sama seperti ket(), tapi kalau $details berisi lebih dari satu
     * produk yang berbeda (karena ke-grouping ke akun yang sama), semua nama
     * produk dicantumkan (dipisah koma), bukan cuma produk pertama.
     * Ini mencegah kesan "cuma 1 produk yang tercatat" padahal aslinya lebih
     * dari satu produk melebur di header/grup yang sama.
     */
    private function ketGrup(string $prefix, string $nota, ?string $customer, $details): string
    {
        $namaUnik = $details
            ->pluck('nama_barang')
            ->filter()
            ->unique()
            ->values();

        if ($namaUnik->count() > 1) {
            $label = $namaUnik->implode(', ');
        } else {
            $label = $namaUnik->first() ?? '';
        }

        $prefixLengkap = $label !== '' ? $prefix.' '.$label : $prefix;

        return $this->ket($prefixLengkap, $nota, $customer);
    }

    private function buatHeader(array $data): JurnalPembantuHeader
    {
        return JurnalPembantuHeader::create(array_merge([
            'status' => JurnalPembantuHeader::STATUS_DRAFT,
            'adalah_jurnal_balik' => false,
            'total_nilai' => 0, // Dikosongkan agar DB Observer/Trigger yang mengisinya
        ], $data));
    }

    private function buatItem(int $headerId, array $data): JurnalPembantuItem
    {
        // Menyiapkan field jumlah sebelum disimpan agar nilai jurnal tidak nol
        // dan menghindari increment ganda (karena sistem memiliki Observer sendiri).
        if (! isset($data['jumlah'])) {
            $banyak = (float) ($data['banyak'] ?? 0);
            $harga = (float) ($data['harga'] ?? 0);
            $data['jumlah'] = round($banyak * $harga, 4);
        }

        return JurnalPembantuItem::create(array_merge([
            'jurnal_pembantu_header_id' => $headerId,
            'status' => true,
        ], $data));
    }

    private function nextNomorJurnal(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;
    }

    private function nextNomorPembantu(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;
    }

    // ══════════════════════════════════════════════════════════════════════
    // ══════════════════════════════════════════════════════════════════════
    //   SECTION: PPN (Pajak Pertambahan Nilai)
    //   ─────────────────────────────────────────────────────────────────
    //   Sengaja dipisah jadi method sendiri di bagian PALING BAWAH file,
    //   supaya kalau aturan PPN berubah (misal: mau digabung proporsional
    //   ke tiap item, mau pakai akun beda per jenis produk, ganti kode
    //   akun, dsb) kita tinggal edit di sini saja tanpa nyentuh logic
    //   Telur/Barang Lain di atas.
    //
    //   Aturan saat ini:
    //   - Hanya jalan kalau $penjualan->ppn_nominal > 0.
    //   - D: Kas/Bank sebesar porsi PPN (ikut proporsi tunai/transfer,
    //     dihitung ulang via resolveBarisKas() supaya Kas tetap balance
    //     dengan Grand Total = subtotal barang + PPN). BARU: baris ini
    //     ditulis ke header Kas yang SAMA dengan blok Telur/Barang Lain
    //     (via $kasRegistry + tulisKas()), bukan bikin header baru -> jadi
    //     hanya ada 1 baris "D: Kas" per kode akun untuk 1 nota, bukan 2.
    //   - K: PPN Keluaran, kode akun di KODE_PPN_KELUARAN (2191.1).
    // ══════════════════════════════════════════════════════════════════════
    // ══════════════════════════════════════════════════════════════════════

    private function buatJurnalPpn(
        Penjualan $penjualan,
        string $tgl,
        string $nota,
        string $customer,
        int $noJurnal,
        int $userId,
        array &$kasRegistry
    ): void {
        $ppnNominal = (float) ($penjualan->ppn_nominal ?? 0);

        if ($ppnNominal <= 0) {
            return;
        }

        $ketPpn = $this->ket('PPN Penjualan', $nota, $customer);

        // ── D: Kas/Bank sebesar porsi PPN ───────────────────────────────
        // BARU: gabung ke header Kas yang sama (lihat $kasRegistry/tulisKas()).
        $barisKasPpn = $this->resolveBarisKas($penjualan, $ppnNominal);

        foreach ($barisKasPpn as $kas) {
            $akunKas = ['kode' => $kas['kode'], 'nama' => $kas['nama']];

            $this->tulisKas($kasRegistry, $akunKas, $noJurnal, $tgl, $ketPpn, $nota, $userId, [
                'jenis_pihak' => 'pelanggan',
                'nama_pihak' => $customer,
                'nama_barang' => 'PPN Keluaran',
                'no_dokumen' => $nota,
                'keterangan' => 'PPN '.$this->formatPersenPpn($penjualan->ppn_persen).'% | '.$nota,
                'banyak' => 1,
                'harga' => $kas['nominal'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        // ── K: PPN Keluaran (2191.1) ────────────────────────────────────
        $akunPpn = $this->resolveAkun(self::KODE_PPN_KELUARAN);

        $hPpn = $this->buatHeader([
            'no_jurnal_pembantu' => $this->nextNomorPembantu(),
            'tgl_transaksi' => $tgl,
            'jenis_transaksi' => 'bk',
            'modul_asal' => 'penjualan_telur',
            'jurnal' => $noJurnal,
            'no_akun' => $akunPpn['kode'],
            'nama_akun' => $akunPpn['nama'],
            'map' => 'k',
            'keterangan' => $ketPpn,
            'no_dokumen' => $nota,
            'dibuat_oleh' => $userId,
        ]);

        $this->buatItem($hPpn->id, [
            'urut' => 1,
            'jenis_pihak' => 'pelanggan',
            'nama_pihak' => $customer,
            'nama_barang' => 'PPN Keluaran',
            'no_dokumen' => $nota,
            'keterangan' => $ketPpn,
            'banyak' => 1,
            'harga' => $ppnNominal,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Format persen PPN untuk keterangan jurnal, buang trailing zero.
     * Contoh: 11.00 -> "11", 11.50 -> "11.5".
     */
    private function formatPersenPpn($persen): string
    {
        $formatted = number_format((float) $persen, 2);

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
