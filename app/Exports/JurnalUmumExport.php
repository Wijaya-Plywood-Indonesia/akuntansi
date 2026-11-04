<?php

namespace App\Exports;

use App\Models\JurnalUmum as JurnalModel;
use App\Support\JurnalMasukFormatter;
use App\Support\JurnalProduksiFormatter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class JurnalUmumExport extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithTitle
{
    protected int $rowIndex = 2; // Mulai row 3 (karena Row 1 = Total Summary, Row 2 = Headings)

    /**
     * @param  bool  $formatJurnal  true = kolom No/Nama/Keterangan baris MASUK/REP/ROT/DRY/JOIN/KEDI/HP dirapikan
     *                              (lihat JurnalMasukFormatter & JurnalProduksiFormatter)
     */
    public function __construct(
        protected ?string $tglDari = null,
        protected ?string $tglSampai = null,
        protected bool $formatJurnal = true,
    ) {}

    public function title(): string
    {
        return 'isi jurnal';
    }

    public function collection(): Collection
    {
        $query = JurnalModel::query();

        if (! empty($this->tglDari)) {
            $query->whereDate('tgl', '>=', $this->tglDari);
        }
        if (! empty($this->tglSampai)) {
            $query->whereDate('tgl', '<=', $this->tglSampai);
        }

        return $query->orderBy('tgl')->orderBy('jurnal')->orderBy('id')->get();
    }

    public function headings(): array
    {
        return [
            // Baris 1: Summary / Total Header baris paling atas persis milik akuntan
            [
                '=SUM(A3:A$ROW)', '', '', '', '', '', '', '', '', '', '', '', '',
                '=SUM(N3:N$ROW)', '', '', '=SUM(Q3:Q$ROW)', '', '', '=SUM(T3:T$ROW)',
                '', '', '=SUM(W3:W$ROW)', '=SUM(X3:X$ROW)', '',
            ],
            // Baris 2: Header kolom
            [
                'Nama Akun',      // A
                'tgl',            // B
                'jurnal',         // C
                'No Akun',        // D
                'No',             // E
                'mm/keterangan',  // F
                'Nama',           // G
                'Keterangan',     // H
                'map',            // I
                'hit kbk',        // J
                'Banyak',         // K
                'M3',             // L
                'Harga',          // M
                'Total',          // N
                'bykd',           // O
                'm3d',            // P
                'Debet',          // Q
                'bykk',           // R
                'm3k',            // S
                'Kredit',         // T
                '',               // U
                '',               // V
                'DEBET',          // W
                'KREDIT',         // X
                '',               // Y
            ],
        ];
    }

    public function map($row): array
    {
        $this->rowIndex++;
        $r = $this->rowIndex;

        $noDokumen = $row->no_dokumen;
        $nama = $row->nama;
        $keterangan = $row->keterangan;

        if ($this->formatJurnal) {
            // MASUK/... -> No = Seri, Nama = supplier, Keterangan = lahan (bila ada)
            $masuk = JurnalMasukFormatter::parse($row->no_dokumen, $row->nama, $row->keterangan);
            if ($masuk !== null) {
                $noDokumen = $masuk['no'];
                $nama = $masuk['nama'] ?? $row->nama;
                $keterangan = $masuk['lahan'];
            } else {
                // REP/ROT/DRY/JOIN/KEDI/HP -> No = kosong, Nama = label proses, Keterangan = deskripsi barang
                $produksi = JurnalProduksiFormatter::parse(
                    $row->no_dokumen, $row->nama, $row->keterangan, $row->no_akun, $row->nama_akun
                );
                if ($produksi !== null) {
                    $noDokumen = $produksi['no'];
                    $nama = $produksi['nama'];
                    $keterangan = $produksi['keterangan'];
                }
            }
        }

        return [
            $row->nama_akun,
            $row->tgl ? ExcelDate::dateTimeToExcel($row->tgl) : null,
            $row->jurnal,
            $row->no_akun,
            $noDokumen,
            $row->mm,
            $nama,
            $keterangan,
            $row->map,
            $row->hit_kbk,
            $row->banyak !== null ? (float) $row->banyak : null,
            $row->m3 !== null ? (float) $row->m3 : null,
            $row->harga !== null ? (float) $row->harga : null,
            // N: Total -> formula dinamis sesuai hit_kbk: jika 'b' => K*M, jika 'm' => L*M, jika kosong => M
            '=IF(J'.$r.'="b",K'.$r.'*M'.$r.',IF(J'.$r.'="m",L'.$r.'*M'.$r.',M'.$r.'))',
            // O: bykd -> =IF(I{r}="d",K{r},"")
            '=IF(I'.$r.'="d",K'.$r.',"")',
            // P: m3d -> =IF(I{r}="d",L{r},"")
            '=IF(I'.$r.'="d",L'.$r.',"")',
            // Q: Debet -> =IF(I{r}="d",N{r},0)
            '=IF(I'.$r.'="d",N'.$r.',0)',
            // R: bykk -> =IF(I{r}="k",K{r},"")
            '=IF(I'.$r.'="k",K'.$r.',"")',
            // S: m3k -> =IF(I{r}="k",L{r},"")
            '=IF(I'.$r.'="k",L'.$r.',"")',
            // T: Kredit -> =IF(I{r}="k",N{r},0)
            '=IF(I'.$r.'="k",N'.$r.',0)',
            '', // U
            '', // V
            '', // W
            '', // X
            '', // Y
        ];
    }

    /**
     * Kolom D (No Akun) dan E (No) selalu disimpan sebagai teks.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (
            in_array($cell->getColumn(), ['D', 'E'], true)
            && $value !== null
            && $value !== ''
        ) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 38,
            'B' => 12,
            'C' => 8,
            'D' => 10,
            'E' => 18,
            'F' => 8,
            'G' => 28,
            'H' => 40,
            'I' => 6,
            'J' => 6,
            'K' => 12,
            'L' => 12,
            'M' => 17,
            'N' => 17,
            'O' => 14,
            'P' => 15,
            'Q' => 17,
            'R' => 10,
            'S' => 15,
            'T' => 17,
            'U' => 8,
            'V' => 8,
            'W' => 14,
            'X' => 14,
            'Y' => 14,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => 'dd-mm-yyyy',
            'D' => '@',
            'E' => '@',
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0',
            'N' => '#,##0',
            'O' => '#,##0',
            'P' => '#,##0.0000',
            'Q' => '#,##0',
            'R' => '#,##0',
            'S' => '#,##0.0000',
            'T' => '#,##0',
            'W' => '#,##0',
            'X' => '#,##0',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max($sheet->getHighestRow(), 2);

                // Fix formula row 1 yang mengandung token $ROW
                foreach (['A1', 'N1', 'Q1', 'T1', 'W1', 'X1'] as $coord) {
                    $cell = $sheet->getCell($coord);
                    $val = $cell->getValue();
                    if (is_string($val) && str_contains($val, '$ROW')) {
                        $cell->setValue(str_replace('$ROW', (string) $lastRow, $val));
                    }
                }

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                $fullRange = "A2:T{$lastRow}";

                // 1. Fill Cyan (#CCFFFF) untuk kolom B sampai M (persis milik akuntan)
                $sheet->getStyle("B2:M{$lastRow}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFFFF'],
                    ],
                ]);

                // 2. Kolom A, C, dan N s/d T tetap putih / transparan
                foreach (['A', 'C'] as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF'],
                        ],
                    ]);
                }

                // 3. Header Row 2: Bold & Alignment
                $sheet->getStyle('A2:T2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Summary Row 1: Bold
                $sheet->getStyle('A1:T1')->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // 4. Border Tipis
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // 5. Alignment data baris 3 s/d lastRow
                if ($lastRow >= 3) {
                    $sheet->getStyle("B3:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I3:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D3:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("K3:T{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Format teks untuk NO.AKUN dan NO
                $sheet->getStyle("D3:E{$lastRow}")->getNumberFormat()->setFormatCode('@');

                $sheet->setAutoFilter("A2:T{$lastRow}");
                $sheet->freezePane('A3');
            },
        ];
    }
}
