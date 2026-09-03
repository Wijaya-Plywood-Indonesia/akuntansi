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
     * Definisi kanonik grup LEAF (tempat Anak Akun benar-benar didaftarkan)
     * untuk sinkronisasi otomatis. Key = prefix digit pertama kode induk akun.
     *
     * PENTING: sejak dipisah total dari grup Laba Rugi (tidak ada lagi
     * grup "hybrid"), tiap prefix bisa menunjuk ke LEBIH DARI SATU grup
     * target sekaligus — satu untuk struktur Neraca/Laba Rugi, satu lagi
     * untuk grup bantu Arus Kas. Karena itu, value tiap prefix sekarang
     * berupa ARRAY of array (bukan satu array definisi saja), supaya satu
     * Anak Akun baru otomatis ikut ter-sync ke SEMUA grup relevannya
     * sekaligus saat tombol "Sinkron Akun Baru" diklik.
     *
     * Catatan: sinkronisasi dilakukan di level Anak Akun (bukan Sub Anak
     * Akun), konsisten dengan tabel pivot akun_group_anak_akun yang aktif.
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkronAkun')
                ->label('Sinkron Akun Baru')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sinkronisasi Akun Otomatis')
                ->modalDescription('Aksi ini akan memasukkan Anak Akun baru ke Grup Akun (struktural: Aktiva Lancar/Pasiva/Laba Rugi, DAN grup bantu Arus Kas terkait) berdasarkan awalan kode akun induk secara otomatis, termasuk membuat grup yang belum ada. Lanjutkan?')
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
     * Logika sinkronisasi otomatis Anak Akun ke Akun Group.
     *
     * Sekarang bekerja di level Anak Akun (tabel pivot akun_group_anak_akun)
     * dan mendukung SATU Anak Akun ter-sync ke LEBIH DARI SATU grup target
     * sekaligus (mis. prefix '4' -> PENDAPATAN PENJUALAN [struktural] DAN
     * [Arus Kas] Penjualan [bantu arus kas] secara bersamaan).
     */
    protected function syncAnakAkunToGroup(): void
    {
        // Tarik semua grup — dipakai untuk pencarian case/spasi-insensitive
        // tanpa query berulang di dalam loop. Di-refresh manual tiap kali
        // ada create supaya pencarian berikutnya tetap melihat data terbaru.
        $semuaGroup = AkunGroup::all();

        // 1. Pastikan grup ROOT (AKTIVA, LABA RUGI) tersedia lebih dulu.
        $parentCache = []; // key PARENT_GROUPS => AkunGroup
        $grupBaruDibuat = []; // nama grup (root maupun leaf) yang baru dibuat
        $parentDiperbaiki = []; // nama grup leaf yang parent_id-nya baru diisi

        foreach (self::PARENT_GROUPS as $key => $def) {
            [$group, $baru] = $this->cariAtauBuatParent($semuaGroup, $key);
            $parentCache[$key] = $group;

            if ($baru) {
                $grupBaruDibuat[] = $key;
                $semuaGroup = AkunGroup::all(); // refresh supaya leaf berikutnya melihat parent baru ini
            }
        }

        // 2. Siapkan cache grup LEAF: cari yang sudah ada (case/spasi-
        //    insensitive), atau buat baru, atau perbaiki parent_id-nya
        //    kalau sebelumnya kosong. Sekarang loop-nya 2 tingkat: prefix
        //    -> daftar definisi (bisa lebih dari satu grup per prefix).
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

        // 3. Tarik semua Anak Akun beserta relasi induknya untuk mendapatkan kode induk akun
        $anakAkuns = AnakAkun::with('indukAkun')->get();

        $syncedCount = 0;

        foreach ($anakAkuns as $anakAkun) {
            // Abaikan jika relasi ke induk tidak valid
            if (! $anakAkun->indukAkun) {
                continue;
            }

            // Ambil awalan/digit pertama dari kode induk akun (misal: '1' dari '1578.00')
            $kodeInduk = (string) $anakAkun->indukAkun->kode_induk_akun;
            $prefix = substr($kodeInduk, 0, 1);

            $daftarDef = self::TARGET_GROUPS[$prefix] ?? [];
            if (empty($daftarDef)) {
                continue;
            }

            // Sync ke SEMUA grup target untuk prefix ini (struktural + arus kas)
            foreach ($daftarDef as $def) {
                $targetGroup = $leafCache[$def['nama']] ?? null;
                if (! $targetGroup) {
                    continue;
                }

                // syncWithoutDetaching berfungsi untuk mengaitkan data ke pivot
                // tanpa menghapus data lama dan otomatis mencegah duplikasi.
                $result = $targetGroup->anakAkuns()->syncWithoutDetaching([$anakAkun->id]);

                // Menghitung hanya data yang baru saja berhasil ditambahkan (bukan yang sudah ada sebelumnya)
                if (! empty($result['attached'])) {
                    $syncedCount++;
                }
            }
        }

        // 4. Tampilkan notifikasi keberhasilan
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