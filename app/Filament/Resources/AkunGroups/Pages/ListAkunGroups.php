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
     * Catatan: SEJAK migrasi pivot, sinkronisasi dilakukan di level
     * Anak Akun (bukan lagi Sub Anak Akun). Tabel pivot yang dipakai
     * adalah akun_group_anak_akun via relasi AkunGroup::anakAkuns().
     */
    private const TARGET_GROUPS = [
        '1' => ['nama' => 'AKTIVA LANCAR',         'tipe' => null,             'order' => 1, 'parent' => 'AKTIVA'],
        '2' => ['nama' => 'PASIVA',                 'tipe' => null,             'order' => 1, 'parent' => null],
        '3' => ['nama' => 'PASIVA',                 'tipe' => null,             'order' => 1, 'parent' => null],
        '4' => ['nama' => 'PENDAPATAN PENJUALAN',   'tipe' => 'pendapatan',     'order' => 1, 'parent' => 'LABA RUGI'],
        '5' => ['nama' => 'BEBAN',                  'tipe' => 'beban_produksi', 'order' => 2, 'parent' => 'LABA RUGI'],
        '6' => ['nama' => 'HPP',                    'tipe' => 'hpp',            'order' => 3, 'parent' => 'LABA RUGI'],
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
                ->modalDescription('Aksi ini akan memasukkan Anak Akun baru ke Grup Akun (Aktiva Lancar, Pasiva, dll) berdasarkan awalan kode akun induk secara otomatis, termasuk membuat grup induk (AKTIVA, LABA RUGI) jika belum ada. Lanjutkan?')
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
            fn(AkunGroup $g) => $this->normalisasiNama($g->nama) === $target
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
        $def      = self::PARENT_GROUPS[$namaParentKey];
        $existing = $this->cariDiCollection($semuaGroup, $namaParentKey);

        if ($existing) {
            return [$existing, false];
        }

        $baru = AkunGroup::create([
            'nama'      => $namaParentKey,
            'parent_id' => null,
            'tipe'      => null,
            'order'     => $def['order'],
            'hidden'    => false,
        ]);

        return [$baru, true];
    }

    /**
     * Cari atau buat grup LEAF (tempat Anak Akun didaftarkan). Kalau grup
     * sudah ada tapi parent_id-nya masih kosong sementara definisi kita
     * mengharuskan ada parent, parent_id akan DIISI (bukan ditimpa) —
     * supaya grup yang kepalang dibuat root (seperti kasus PENDAPATAN
     * PENJUALAN/BEBAN/HPP sebelumnya) otomatis ikut kekoneksi ke LABA RUGI.
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
            'nama'      => $def['nama'],
            'parent_id' => $parentGroup?->id,
            'tipe'      => $def['tipe'],
            'order'     => $def['order'],
            'hidden'    => false,
        ]);

        return [$baru, true, false];
    }

    /**
     * Logika sinkronisasi otomatis Anak Akun ke Akun Group.
     *
     * PERUBAHAN: sebelumnya sinkron dilakukan per Sub Anak Akun via tabel
     * pivot akun_group_sub_anak_akun. Tabel itu sudah di-drop dan datanya
     * dipindahkan ke akun_group_anak_akun. Method ini sekarang bekerja
     * di level Anak Akun, konsisten dengan struktur relasi yang aktif.
     */
    protected function syncAnakAkunToGroup(): void
    {
        // Tarik semua grup — dipakai untuk pencarian case/spasi-insensitive
        // tanpa query berulang di dalam loop. Di-refresh manual tiap kali
        // ada create supaya pencarian berikutnya (mis. prefix '3' mencari
        // 'PASIVA' yang baru dibuat oleh prefix '2') tetap melihat data terbaru.
        $semuaGroup = AkunGroup::all();

        // 1. Pastikan grup ROOT (AKTIVA, LABA RUGI) tersedia lebih dulu.
        $parentCache      = []; // key PARENT_GROUPS => AkunGroup
        $grupBaruDibuat   = []; // nama grup (root maupun leaf) yang baru dibuat
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
        //    kalau sebelumnya kosong. Beberapa prefix (2 & 3) sengaja
        //    menunjuk ke grup leaf yang sama ("PASIVA"), jadi kita cache
        //    per-nama supaya tidak dibuat/diproses dobel.
        $leafCache = []; // nama_kanonik => AkunGroup

        foreach (self::TARGET_GROUPS as $prefix => $def) {
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
            $prefix    = substr($kodeInduk, 0, 1);

            $def = self::TARGET_GROUPS[$prefix] ?? null;
            if (! $def) {
                continue;
            }

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

        // 4. Tampilkan notifikasi keberhasilan
        $bodyLines = ["Berhasil mensinkronkan <strong>{$syncedCount}</strong> Anak Akun baru ke Akun Group."];

        if (! empty($grupBaruDibuat)) {
            $daftarGrupBaru = implode(', ', array_unique($grupBaruDibuat));
            $bodyLines[] = "Grup baru otomatis dibuat: <strong>{$daftarGrupBaru}</strong>. Silakan cek dan sesuaikan Tipe/Urutan-nya jika diperlukan.";
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