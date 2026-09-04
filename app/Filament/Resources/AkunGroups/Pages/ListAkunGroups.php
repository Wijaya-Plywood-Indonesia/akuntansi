<?php

namespace App\Filament\Resources\AkunGroups\Pages;

use App\Filament\Resources\AkunGroups\AkunGroupResource;
use App\Models\AkunGroup;
use App\Models\AnakAkun;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAkunGroups extends ListRecords
{
    protected static string $resource = AkunGroupResource::class;

    /**
     * Grup ROOT (tanpa parent) yang menaungi grup-grup leaf di bawahnya.
     * Grup root sendiri selalu tipe = null (murni pengelompokan, tidak
     * dihitung langsung di Laba Rugi — yang dihitung adalah children-nya).
     */
    private const PARENT_GROUPS = [
        'AKTIVA'    => ['order' => 1],
        'LABA RUGI' => ['order' => 1],
    ];

    /**
     * Definisi kanonik grup LEAF yang anggotanya = "SEMUA Anak Akun di
     * bawah prefix kode induk akun tertentu". Key = prefix digit pertama
     * kode induk akun. Cocok untuk grup yang memang menaungi satu blok
     * penuh (mis. semua akun Pendapatan, semua akun Beban).
     *
     * Satu prefix bisa menunjuk ke LEBIH DARI SATU grup target sekaligus
     * (satu untuk struktur Neraca/Laba Rugi, satu lagi untuk grup bantu
     * Arus Kas yang isinya identik dengan grup struktural itu).
     */
    private const TARGET_GROUPS = [
        '1' => [
            ['nama' => 'AKTIVA LANCAR', 'tipe' => null, 'kategori_arus_kas' => null, 'order' => 1, 'parent' => 'AKTIVA', 'hidden' => false],
        ],
        '2' => [
            ['nama' => 'PASIVA', 'tipe' => null, 'kategori_arus_kas' => null, 'order' => 1, 'parent' => null, 'hidden' => false],
        ],
        '3' => [
            ['nama' => 'PASIVA', 'tipe' => null, 'kategori_arus_kas' => null, 'order' => 1, 'parent' => null, 'hidden' => false],
        ],
        '4' => [
            ['nama' => 'PENDAPATAN PENJUALAN', 'tipe' => 'pendapatan', 'kategori_arus_kas' => null, 'order' => 1, 'parent' => 'LABA RUGI', 'hidden' => false],
            ['nama' => '[Arus Kas] Penjualan', 'tipe' => null, 'kategori_arus_kas' => 'penjualan', 'order' => 94, 'parent' => null, 'hidden' => true],
        ],
        '5' => [
            ['nama' => 'BEBAN', 'tipe' => 'beban_produksi', 'kategori_arus_kas' => null, 'order' => 2, 'parent' => 'LABA RUGI', 'hidden' => false],
            ['nama' => '[Arus Kas] Produksi', 'tipe' => null, 'kategori_arus_kas' => 'produksi', 'order' => 95, 'parent' => null, 'hidden' => true],
        ],
        '6' => [
            ['nama' => 'HPP', 'tipe' => 'hpp', 'kategori_arus_kas' => null, 'order' => 3, 'parent' => 'LABA RUGI', 'hidden' => false],
            ['nama' => '[Arus Kas] Pembelian & Stok', 'tipe' => null, 'kategori_arus_kas' => 'pembelian_stok', 'order' => 96, 'parent' => null, 'hidden' => true],
        ],
    ];

    /**
     * Grup bantu Arus Kas yang anggotanya = KURASI MANUAL (bukan "semua
     * akun di bawah prefix X", tapi pilihan akun tertentu saja — mis.
     * hanya sebagian dari Aktiva Lancar/Pasiva yang relevan untuk kategori
     * "Pendanaan"). Karena tidak bisa ditangkap aturan prefix, di sini
     * ditandai eksplisit lewat `kode_anak_akun` satu-satu.
     *
     * INI SATU-SATUNYA SUMBER KEBENARAN untuk grup-grup ini — kalau
     * database di-reset dan grup Akun Group dihapus semua, menjalankan
     * "Sinkron Akun Baru" akan membangun ulang grup-grup ini persis
     * seperti semula, tanpa perlu SQL manual lagi.
     */
    private const EXPLICIT_ARUS_KAS_GROUPS = [
        '[Arus Kas] Piutang, Utang & Modal' => [
            'kategori_arus_kas' => 'pendanaan',
            'order' => 90,
            'kode_anak_akun' => [
                '1122', '1123', '1124', '1125', '1181', '1501', // Piutang & Aset Kontrak
                '2102', '2103', '2111', '2186', '2187', '2192',  // Utang jangka pendek
                '2201', '2202', '2301', '2303', '2304', '2312',  // Utang bank & jangka panjang
                '3102', '3120', '3298',                          // Modal & Ekuitas
                '4511',                                          // Pendapatan Bunga
            ],
        ],
        '[Arus Kas] Persediaan' => [
            'kategori_arus_kas' => 'pembelian_stok',
            'order' => 91,
            'kode_anak_akun' => ['1402', '1403', '1404'],
        ],
        '[Arus Kas] DP Penjualan' => [
            'kategori_arus_kas' => 'penjualan',
            'order' => 92,
            'kode_anak_akun' => ['2203'],
        ],
        '[Arus Kas] Biaya Operasional Lain' => [
            'kategori_arus_kas' => 'beban_usaha',
            'order' => 93,
            'kode_anak_akun' => ['1421', '1499', '2195'],
        ],
    ];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkronAkun')
                ->label('Sinkron Akun Baru')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sinkronisasi Akun Otomatis')
                ->modalDescription('Aksi ini akan memasukkan Anak Akun ke Grup Akun struktural (Aktiva Lancar/Pasiva/Laba Rugi), grup bantu Arus Kas berbasis prefix, DAN grup bantu Arus Kas dengan kurasi manual (Piutang/Utang/Modal, Persediaan, DP Penjualan, Biaya Operasional Lain) — termasuk membuat grup yang belum ada. Lanjutkan?')
                ->action(function () {
                    $this->syncAnakAkunToGroup();
                }),
            CreateAction::make(),
        ];
    }

    /**
     * Ratakan nama grup supaya perbandingan tidak terpengaruh oleh
     * perbedaan kapitalisasi atau spasi berlebih.
     * "Aktiva Lancar", "AKTIVA  LANCAR", "  aktiva lancar " -> "AKTIVA LANCAR"
     */
    private function normalisasiNama(string $nama): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $nama)));
    }

    /**
     * Cari AkunGroup berdasarkan nama secara case-insensitive & whitespace-
     * insensitive dari sebuah collection yang sudah ditarik lebih dulu.
     */
    private function cariDiCollection($semuaGroup, string $namaKanonik): ?AkunGroup
    {
        $target = $this->normalisasiNama($namaKanonik);

        return $semuaGroup->first(
            fn (AkunGroup $g) => $this->normalisasiNama($g->nama) === $target
        );
    }

    /**
     * Cari atau buat grup ROOT (tanpa parent) berdasarkan definisi di
     * PARENT_GROUPS. Dipanggil sebelum grup leaf, supaya parent_id-nya
     * sudah tersedia saat grup leaf dibuat/diperbaiki.
     *
     * Mengembalikan [AkunGroup $group, bool $baruDibuat].
     */
    private function cariAtauBuatParent($semuaGroup, string $namaParentKey): array
    {
        $def = self::PARENT_GROUPS[$namaParentKey];
        $existing = $this->cariDiCollection($semuaGroup, $namaParentKey);

        if ($existing) {
            return [$existing, false];
        }

        $baru = AkunGroup::create([
            'nama' => $namaParentKey,
            'parent_id' => null,
            'tipe' => null,
            'kategori_arus_kas' => null,
            'order' => $def['order'],
            'hidden' => false,
        ]);

        return [$baru, true];
    }

    /**
     * Cari atau buat grup LEAF (tempat Anak Akun didaftarkan). Kalau grup
     * sudah ada tapi parent_id-nya masih kosong sementara definisi kita
     * mengharuskan ada parent, parent_id akan DIISI (bukan ditimpa) —
     * supaya grup yang kepalang dibuat root otomatis ikut kekoneksi ke
     * parent seharusnya.
     * Kalau parent_id sudah terisi dengan grup LAIN (bukan yang kita
     * harapkan), TIDAK disentuh — dianggap itu pengaturan manual yang
     * sengaja dan wajib dihormati.
     *
     * Mengembalikan [AkunGroup $group, bool $baruDibuat, bool $parentDiperbaiki].
     */
    private function cariAtauBuatLeaf($semuaGroup, array $def, ?AkunGroup $parentGroup): array
    {
        $existing = $this->cariDiCollection($semuaGroup, $def['nama']);

        if ($existing) {
            $parentDiperbaiki = false;

            if ($parentGroup && is_null($existing->parent_id)) {
                $existing->parent_id = $parentGroup->id;
                $existing->save();
                $parentDiperbaiki = true;
            }

            return [$existing, false, $parentDiperbaiki];
        }

        $baru = AkunGroup::create([
            'nama' => $def['nama'],
            'parent_id' => $parentGroup?->id,
            'tipe' => $def['tipe'],
            'kategori_arus_kas' => $def['kategori_arus_kas'] ?? null,
            'order' => $def['order'],
            'hidden' => $def['hidden'] ?? false,
        ]);

        return [$baru, true, false];
    }

    /**
     * Cari atau buat grup ARUS KAS EKSPLISIT (kurasi manual, tanpa parent).
     * Sama polanya dengan cariAtauBuatLeaf, tapi definisinya lebih
     * sederhana (tidak butuh tipe/parent) karena grup ini murni bantu
     * Arus Kas, bukan bagian dari struktur Neraca/Laba Rugi.
     *
     * Mengembalikan [AkunGroup $group, bool $baruDibuat].
     */
    private function cariAtauBuatEksplisit($semuaGroup, string $nama, array $def): array
    {
        $existing = $this->cariDiCollection($semuaGroup, $nama);

        if ($existing) {
            return [$existing, false];
        }

        $baru = AkunGroup::create([
            'nama' => $nama,
            'parent_id' => null,
            'tipe' => null,
            'kategori_arus_kas' => $def['kategori_arus_kas'],
            'order' => $def['order'],
            'hidden' => true,
        ]);

        return [$baru, true];
    }

    /**
     * Logika sinkronisasi otomatis Anak Akun ke Akun Group.
     *
     * Dua mekanisme dijalankan berurutan:
     *  1. Berbasis PREFIX (TARGET_GROUPS) — untuk grup yang anggotanya
     *     memang "semua akun di bawah prefix induk akun X".
     *  2. Berbasis KODE EKSPLISIT (EXPLICIT_ARUS_KAS_GROUPS) — untuk grup
     *     bantu Arus Kas yang isinya kurasi manual, tidak bisa ditangkap
     *     aturan prefix.
     */
    protected function syncAnakAkunToGroup(): void
    {
        $semuaGroup = AkunGroup::all();

        $parentCache = [];
        $grupBaruDibuat = [];
        $parentDiperbaiki = [];
        $syncedCount = 0;

        // ══════════════════════════════════════════════════════════
        // BAGIAN 1 — Sinkronisasi berbasis PREFIX
        // ══════════════════════════════════════════════════════════

        // 1a. Pastikan grup ROOT (AKTIVA, LABA RUGI) tersedia lebih dulu.
        foreach (self::PARENT_GROUPS as $key => $def) {
            [$group, $baru] = $this->cariAtauBuatParent($semuaGroup, $key);
            $parentCache[$key] = $group;

            if ($baru) {
                $grupBaruDibuat[] = $key;
                $semuaGroup = AkunGroup::all();
            }
        }

        // 1b. Siapkan cache grup LEAF berbasis prefix.
        $leafCache = []; // nama_kanonik => AkunGroup

        foreach (self::TARGET_GROUPS as $prefix => $daftarDef) {
            foreach ($daftarDef as $def) {
                if (isset($leafCache[$def['nama']])) {
                    continue;
                }

                $parentGroup = $def['parent'] ? ($parentCache[$def['parent']] ?? null) : null;

                [$group, $baru, $diperbaiki] = $this->cariAtauBuatLeaf($semuaGroup, $def, $parentGroup);
                $leafCache[$def['nama']] = $group;

                if ($baru) {
                    $grupBaruDibuat[] = $def['nama'];
                    $semuaGroup = AkunGroup::all();
                }
                if ($diperbaiki) {
                    $parentDiperbaiki[] = $def['nama'];
                }
            }
        }

        // 1c. Sync tiap Anak Akun ke grup berbasis prefix yang cocok.
        $anakAkuns = AnakAkun::with('indukAkun')->get();

        foreach ($anakAkuns as $anakAkun) {
            if (! $anakAkun->indukAkun) {
                continue;
            }

            $kodeInduk = (string) $anakAkun->indukAkun->kode_induk_akun;
            $prefix = substr($kodeInduk, 0, 1);

            $daftarDef = self::TARGET_GROUPS[$prefix] ?? [];
            if (empty($daftarDef)) {
                continue;
            }

            foreach ($daftarDef as $def) {
                $targetGroup = $leafCache[$def['nama']] ?? null;
                if (! $targetGroup) {
                    continue;
                }

                $result = $targetGroup->anakAkuns()->syncWithoutDetaching([$anakAkun->id]);

                if (! empty($result['attached'])) {
                    $syncedCount++;
                }
            }
        }

        // ══════════════════════════════════════════════════════════
        // BAGIAN 2 — Sinkronisasi berbasis KODE EKSPLISIT (kurasi manual)
        // ══════════════════════════════════════════════════════════

        // Peta kode_anak_akun => id, sekali tarik, dipakai untuk semua grup eksplisit.
        $anakAkunByKode = AnakAkun::pluck('id', 'kode_anak_akun');

        foreach (self::EXPLICIT_ARUS_KAS_GROUPS as $nama => $def) {
            [$group, $baru] = $this->cariAtauBuatEksplisit($semuaGroup, $nama, $def);

            if ($baru) {
                $grupBaruDibuat[] = $nama;
                $semuaGroup = AkunGroup::all();
            }

            $idUntukDisync = collect($def['kode_anak_akun'])
                ->map(fn ($kode) => $anakAkunByKode[$kode] ?? null)
                ->filter()
                ->values()
                ->all();

            if (empty($idUntukDisync)) {
                continue;
            }

            $result = $group->anakAkuns()->syncWithoutDetaching($idUntukDisync);

            if (! empty($result['attached'])) {
                $syncedCount += count($result['attached']);
            }
        }

        // ══════════════════════════════════════════════════════════
        // 3. Notifikasi hasil
        // ══════════════════════════════════════════════════════════

        $bodyLines = ["Berhasil mensinkronkan <strong>{$syncedCount}</strong> pendaftaran Anak Akun baru ke Akun Group (struktural + arus kas)."];

        if (! empty($grupBaruDibuat)) {
            $daftarGrupBaru = implode(', ', array_unique($grupBaruDibuat));
            $bodyLines[] = "Grup baru otomatis dibuat: <strong>{$daftarGrupBaru}</strong>. Silakan cek dan sesuaikan Tipe/Kategori Arus Kas/Urutan-nya jika diperlukan.";
        }

        if (! empty($parentDiperbaiki)) {
            $daftarDiperbaiki = implode(', ', array_unique($parentDiperbaiki));
            $bodyLines[] = "Parent grup otomatis dilengkapi untuk: <strong>{$daftarDiperbaiki}</strong> (sebelumnya kosong).";
        }

        Notification::make()
            ->title('Sinkronisasi Selesai')
            ->body(implode('<br>', $bodyLines))
            ->success()
            ->send();
    }
}