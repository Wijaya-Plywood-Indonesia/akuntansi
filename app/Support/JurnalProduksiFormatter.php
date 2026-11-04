<?php

namespace App\Support;

/**
 * Merapikan baris jurnal produksi: REP, ROT (rotary + kayu keluar/habis), DRY, JOIN, KEDI, HP.
 *
 * Data di database:
 *   no_dokumen : REP/20261023/289
 *   nama       : 244x122x0,5 Sengon KW4                      <- sebenarnya deskripsi barang
 *   keterangan : tembel | No.Jurnal: REP/20261023/289        <- sebenarnya label proses/shift
 *
 * Format yang benar (sama dengan sheet "jurnal produksi v2" di semua laporan):
 *   No (E)   : kosong
 *   Nama (G) : label proses   -> tembel / nyambung / bongkar / dryer pagi / hp 1 pagi / kupasan (m - sanji) ...
 *   Ket  (H) : deskripsi barang, dengan pengecualian:
 *              - Hutang Gaji (2195.1) & Selisih harga patok produksi (5069.2) -> kosong
 *              - bahan penolong (lem, tepung, solasi, dst.)                   -> nama barang (dari nama akun)
 */
class JurnalProduksiFormatter
{
    /** [No.Jurnal: ]REP/20261023/289 , ROT/20261023/SPINDLESS , KEDI/20261023 , ... */
    private const RE_NO_JURNAL = '~^\s*(?:No\.?\s*Jurnal:\s*)?(?<jenis>REP|ROT|DRY|JOIN|KEDI|HP)/(?<tgl>\d{8})(?:/(?<akhir>[^/\s]+))?\s*$~i';

    /** "<label> | No.Jurnal: <no jurnal>" */
    private const RE_LABEL = '~^\s*(?<label>.+?)\s*\|\s*No\.?\s*Jurnal:\s*(?<no>[A-Z]+/\S+)\s*$~i';

    /** Akun yang kolom Keterangannya harus kosong */
    private const AKUN_KETERANGAN_KOSONG = ['2195.1', '5069.2'];

    /** Akun bahan penolong: Keterangan = nama barang (nama akun tanpa awalan "Persediaan ") */
    private const AKUN_BAHAN_PENOLONG = [
        '1402.9', '1402.10', '1402.11', '1402.12', '1402.13', '1402.14', '1402.15', '1402.23',
    ];

    /**
     * @return array{no: null, nama: string, keterangan: ?string, jenis: string}|null
     *                                                                                null bila bukan baris produksi / pola keterangan tidak dikenali (dibiarkan apa adanya)
     */
    public static function parse(
        ?string $noDokumen,
        ?string $nama,
        ?string $keterangan,
        ?string $noAkun = null,
        ?string $namaAkun = null,
    ): ?array {
        if (! preg_match(self::RE_NO_JURNAL, (string) $noDokumen, $j)) {
            return null;
        }
        if (! preg_match(self::RE_LABEL, (string) $keterangan, $l)) {
            return null;
        }

        $kode = trim((string) $noAkun);
        $deskripsi = $nama !== null && trim($nama) !== '' ? trim($nama) : null;

        if (in_array($kode, self::AKUN_KETERANGAN_KOSONG, true)) {
            $deskripsi = null;
        } elseif (in_array($kode, self::AKUN_BAHAN_PENOLONG, true)) {
            $deskripsi = trim(preg_replace('~^\s*Persediaan\s+~i', '', (string) $namaAkun));
        }

        return [
            'no' => null,
            'nama' => trim($l['label']),
            'keterangan' => $deskripsi,
            'jenis' => strtoupper($j['jenis']),
        ];
    }
}
