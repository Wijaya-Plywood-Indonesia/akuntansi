<?php

namespace App\Services\Jurnal;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RotaryJurnalReceiverService
 *
 * Menerima payload jurnal rotary dari ERP dan menyimpannya ke:
 *   jurnal_pembantu_headers  (1 record per akun = 8 records)
 *   jurnal_pembantu_items    (N records per header)
 *
 * FIELD MAPPING:
 * ┌─────────────────────────────────┬───────────────────────────────┐
 * │ payload                         │ jurnal_pembantu_headers       │
 * ├─────────────────────────────────┼───────────────────────────────┤
 * │ jurnal_header.no_jurnal         │ no_jurnal_pembantu (auto)     │
 * │ jurnal_header.no_jurnal         │ no_dokumen                    │
 * │ jurnal_header.tgl_transaksi     │ tgl_transaksi                 │
 * │ jurnal_header.jenis_transaksi   │ jenis_transaksi               │
 * │ jurnal_header.modul_asal        │ modul_asal                    │
 * │ jurnal_header.keterangan        │ keterangan                    │
 * │ jurnal_items[i].no_akun         │ no_akun                       │
 * │ jurnal_items[i].nama_akun       │ nama_akun                     │
 * │ jurnal_items[i].map             │ map                           │
 * │ jurnal_items[i].jumlah          │ total_nilai                   │
 * │ max(jurnal) + 1                 │ jurnal                        │
 * ├─────────────────────────────────┼───────────────────────────────┤
 * │ payload                         │ jurnal_pembantu_items         │
 * ├─────────────────────────────────┼───────────────────────────────┤
 * │ items[j].urut                   │ urut                          │
 * │ items[j].jenis_pihak            │ jenis_pihak                   │
 * │ items[j].nama_pihak             │ nama_pihak                    │
 * │ items[j].keterangan             │ keterangan                    │
 * │ items[j].ukuran                 │ ukuran                        │
 * │ items[j].banyak                 │ banyak                        │
 * │ items[j].m3                     │ m3                            │
 * │ items[j].harga                  │ harga                         │
 * │ items[j].hit_kbk                │ hit_kbk                       │
 * │ items[j].jumlah                 │ jumlah (langsung, skip auto)  │
 * └─────────────────────────────────┴───────────────────────────────┘
 */
class RotaryJurnalReceiverService
{
    // ID sistem user — dipakai untuk dibuat_oleh
    // Ubah sesuai ID user "system" di tabel users project akuntansi
    const SYSTEM_USER_ID = 1;

    /**
     * Cek apakah no_jurnal sudah pernah dibuat (idempotency)
     */
    public function isDuplicate(string $noJurnal): bool
    {
        return JurnalPembantuHeader::where('no_dokumen', $noJurnal)->exists();
    }

    /**
     * Store: insert semua header + items dalam 1 transaksi DB
     *
     * @return array ['jurnal' => int, 'jumlah_header' => int, 'jumlah_items' => int]
     */
    public function store(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $jh      = $payload['jurnal_header'];
            $items   = $payload['jurnal_items'];

            // ── 1. Ambil nomor jurnal baru ─────────────────────────────────
            // Lock baris untuk hindari race condition
            $maxJurnal = JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0;
            $nomorJurnal = $maxJurnal + 1;

            // ── 2. Ambil nomor JP (no_jurnal_pembantu) baru ────────────────
            $maxJP = JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0;
            $noJPBase = $maxJP + 1; // counter, tiap header increment

            $jumlahHeader = 0;
            $jumlahItems  = 0;

            // ── 3. Loop tiap akun → buat 1 header + N items ────────────────
            foreach ($items as $i => $item) {
                $noJP = $noJPBase + $i;

                // 3a. Buat jurnal_pembantu_header
                $header = JurnalPembantuHeader::create([
                    'no_jurnal_pembantu' => $noJP,
                    'jurnal'             => $nomorJurnal,
                    'tgl_transaksi'      => $jh['tgl_transaksi'],
                    'jenis_transaksi'    => $jh['jenis_transaksi'],
                    'modul_asal'         => $jh['modul_asal'],
                    'no_akun'            => $item['no_akun'],
                    'nama_akun'          => $item['nama_akun'],
                    'map'                => $item['map'],
                    'keterangan'         => $item['keterangan'],
                    'no_dokumen'         => $jh['no_jurnal'],   // ROT/20260115
                    'total_nilai'        => $item['jumlah'],    // akan di-recalculate setelah items masuk
                    'status'             => 'draft',
                    'adalah_jurnal_balik'=> false,
                    'dibuat_oleh'        => self::SYSTEM_USER_ID,
                ]);

                $jumlahHeader++;

                // 3b. Buat jurnal_pembantu_items
                foreach ($item['items'] as $detail) {
                    // Untuk hit_kbk='k' atau 'b': jumlah akan di-recalculate
                    // oleh JurnalPembantuItem::booted() → hitungJumlah()
                    // Untuk null: jumlah diisi langsung dari payload
                    JurnalPembantuItem::create([
                        'jurnal_pembantu_header_id' => $header->id,
                        'urut'                      => $detail['urut'],
                        'jenis_pihak'               => $detail['jenis_pihak'],
                        'nama_pihak'                => $detail['nama_pihak'],
                        'nama_barang'               => $detail['nama_barang'] ?? null,
                        'keterangan'                => $detail['keterangan'],
                        'ukuran'                    => $detail['ukuran']   ?? null,
                        'banyak'                    => $detail['banyak']   ?? null,
                        'm3'                        => $detail['m3']       ?? null,
                        'harga'                     => $detail['harga']    ?? 0,
                        'hit_kbk'                   => $detail['hit_kbk']  ?? null,
                        'jumlah'                    => (float) $detail['jumlah'],  // override hasil hitungJumlah()
                        'status'                    => true,
                    ]);

                    $jumlahItems++;
                }

                // 3c. Recalculate total_nilai header dari items yang sudah masuk
                // (sudah otomatis via observer JurnalPembantuItem::saved,
                //  tapi kita panggil eksplisit untuk memastikan)
                $header->refresh()->recalculateTotalNilai();
            }

            Log::info('[RotaryJurnal] Berhasil disimpan', [
                'no_dokumen'    => $jh['no_jurnal'],
                'jurnal'        => $nomorJurnal,
                'jumlah_header' => $jumlahHeader,
                'jumlah_items'  => $jumlahItems,
            ]);

            return [
                'jurnal'        => $nomorJurnal,
                'jumlah_header' => $jumlahHeader,
                'jumlah_items'  => $jumlahItems,
            ];
        });
    }
}