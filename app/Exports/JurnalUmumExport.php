<?php

namespace App\Exports;

use App\Models\JurnalUmum as JurnalModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class JurnalUmumExport extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $tglDari = null,
        protected ?string $tglSampai = null,
    ) {}

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
            'NAMA AKUN',   // A
            'TANGGAL',     // B
            'No',          // C  nomor jurnal
            'NO.AKUN',     // D
            'NO',          // E  nomor dokumen (mis. "sj 9725")
            'mm',          // F
            'Nama',        // G
            'Keterangan',  // H
            'Map',         // I  d = debet, k = kredit
            'Hit',         // J
            'Banyak',      // K
            'm3',          // L
            'Harga',       // M
            'Total',       // N
        ];
    }

    public function map($row): array
    {
        return [
            $row->nama_akun,
            $row->tgl ? ExcelDate::dateTimeToExcel($row->tgl) : null,
            $row->jurnal,
            $row->no_akun,
            $row->no_dokumen,
            $row->mm,
            $row->nama,
            $row->keterangan,
            $row->map,
            $row->hit_kbk,
            $row->banyak !== null ? (float) $row->banyak : null,
            $row->m3 !== null ? (float) $row->m3 : null,
            $row->harga !== null ? (float) $row->harga : null,
            $row->nilai, // accessor model: b -> banyak*harga, m -> m3*harga, else harga
        ];
    }

    /**
     * Kolom D (NO.AKUN) dan E (NO) selalu disimpan sebagai teks.
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
            'A' => 28,
            'B' => 12,
            'C' => 6,
            'D' => 11,
            'E' => 12,
            'F' => 6,
            'G' => 24,
            'H' => 44,
            'I' => 6,
            'J' => 6,
            'K' => 10,
            'L' => 11,
            'M' => 16,
            'N' => 16,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => 'dd-mm-yyyy',
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0',
            'N' => '#,##0',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max($sheet->getHighestRow(), 1);
                $range = "A1:N{$lastRow}";

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // Fill cyan muda (#CCFFFF) untuk seluruh range (header + body)
                $sheet->getStyle($range)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFFFF'],
                    ],
                ]);

                // Kolom A (NAMA AKUN) dan C (No) tetap putih
                foreach (['A', 'C'] as $col) {
                    $sheet->getStyle("{$col}1:{$col}{$lastRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF'],
                        ],
                    ]);
                }

                // Header: tebal & rata tengah
                $sheet->getStyle('A1:N1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Border seluruh data
                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Alignment kolom data
                $sheet->getStyle("B2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Format teks untuk NO.AKUN dan NO
                $sheet->getStyle("D2:E{$lastRow}")->getNumberFormat()->setFormatCode('@');

                $sheet->setAutoFilter("A1:N{$lastRow}");
                $sheet->freezePane('A2');
            },
        ];
    }
}
