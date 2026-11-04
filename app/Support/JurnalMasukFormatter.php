<?php

namespace App\Support;

/**
 * Merapikan baris jurnal yang No. Jurnal-nya mengandung "MASUK/".
 *
 * Data di database (hasil import web):
 *   no_dokumen : MASUK/20261023/2210202624414007
 *   nama       : No.Jurnal: MASUK/20261023/2210202624414007
 *   keterangan : Jainul m | Seri: 244 | No.Jurnal: MASUK/20261023/2210202624414007
 *
 * Format akuntan (sama dengan sheet "jurnal produksi" laporan kayu masuk):
 *   No (E)   : 244          <- Seri
 *   Nama (G) : Jainul m     <- nama supplier
 *   Ket  (H) : lahan        <- hanya bila ada "Lahan: X" di keterangan, selain itu kosong
 */
class JurnalMasukFormatter
{
    /** No. Jurnal: [No.Jurnal: ]MASUK/<yyyymmdd>/<ddmmyyyy><seri 3 digit><urutan> */
    private const RE_NO_JURNAL = '~^\s*(?:No\.?\s*Jurnal:\s*)?(?<no>MASUK/(?<tgl>\d{8})/(?<nota>\d{11,}))\s*$~i';

    /** "Seri: 244" */
    private const RE_SERI = '~Seri:\s*(?<seri>[^|]+?)\s*(?:\||$)~i';

    /** "Lahan: GA" (opsional) */
    private const RE_LAHAN = '~Lahan:\s*(?<lahan>[^|]+?)\s*(?:\||$)~i';

    /** Segmen pertama sebelum "|" yang bukan label (Seri:/Lahan:/No.Jurnal:) = nama supplier */
    private const RE_NAMA = '~^\s*(?!Seri:|Lahan:|No\.?\s*Jurnal:)(?<nama>[^|]+?)\s*\|~i';

    public static function isMasuk(?string $noDokumen): bool
    {
        return (bool) preg_match(self::RE_NO_JURNAL, (string) $noDokumen);
    }

    /**
     * @return array{no: string, nama: ?string, lahan: ?string, no_jurnal: string}|null
     *                                                                                  null bila bukan baris MASUK (dibiarkan apa adanya)
     */
    public static function parse(?string $noDokumen, ?string $nama, ?string $keterangan): ?array
    {
        // Pemicu: No. Jurnal (no_dokumen, atau cadangan dari kolom nama) mengandung MASUK/...
        if (! preg_match(self::RE_NO_JURNAL, (string) $noDokumen, $j)
            && ! preg_match(self::RE_NO_JURNAL, (string) $nama, $j)) {
            return null;
        }

        $ket = (string) $keterangan;

        // Seri: dari keterangan; cadangan = 3 digit setelah tanggal (ddmmyyyy) pada nomor nota
        $seri = preg_match(self::RE_SERI, $ket, $m) ? trim($m['seri']) : substr($j['nota'], 8, 3);

        return [
            'no' => $seri,
            'nama' => preg_match(self::RE_NAMA, $ket, $m) ? trim($m['nama']) : null,
            'lahan' => preg_match(self::RE_LAHAN, $ket, $m) ? trim($m['lahan']) : null,
            'no_jurnal' => $j['no'],
        ];
    }
}
