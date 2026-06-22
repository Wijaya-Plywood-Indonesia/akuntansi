<?php

namespace App\Exports;

use App\Models\JurnalUmum as JurnalModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class JurnalUmumExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithColumnFormatting,
    WithEvents
{
    public function __construct(
        protected ?string $tglDari = null,
        protected ?string $tglSampai = null,
    ) {}

    /**
     * Ambil data sesuai rentang tanggal, urut tgl & no jurnal seperti sheet "isi jurnal".
     */
    public function collection(): Collection
    {
        $query = JurnalModel::query();

        if (!empty($this->tglDari)) {
            $query->whereDate('tgl', '>=', $this->tglDari);
        }
        if (!empty($this->tglSampai)) {
            $query->whereDate('tgl', '<=', $this->tglSampai);
        }

        return $query->orderBy('tgl')->orderBy('jurnal')->orderBy('id')->get();
    }

    /**
     * Header kolom — persis sama dengan sheet "isi jurnal":
     * Nama Akun | tgl | jurnal | No Akun | No | mm | Nama | Keterangan | map | hit kbk | Banyak | M3 | Harga
     */
    public function headings(): array
    {
        return [
            'Nama Akun',
            'tgl',
            'jurnal',
            'No Akun',
            'No',
            'mm',
            'Nama',
            'Keterangan',
            'map',
            'hit kbk',
            'Banyak',
            'M3',
            'Harga',
        ];
    }

    /**
     * Mapping tiap baris model -> kolom export.
     * "No" pada sheet asli = kolom no-dokumen.
     */
    public function map($row): array
    {
        return [
            $row->nama_akun,
            $row->tgl ? $row->tgl->format('Y-m-d') : null,
            $row->jurnal,
            $row->no_akun,
            $row->no_dokumen,
            $row->mm,
            $row->nama,
            $row->keterangan,
            $row->map,
            $row->hit_kbk,
            $row->banyak,
            $row->m3,
            $row->harga,
        ];
    }

    /**
     * Lebar kolom — disamakan dengan sheet "isi jurnal" (dalam satuan karakter Excel).
     */
    public function columnWidths(): array
    {
        return [
            'A' => 36.18, // Nama Akun
            'B' => 10.82, // tgl
            'C' => 5.73,  // jurnal
            'D' => 10.27, // No Akun
            'E' => 14.27, // No
            'F' => 7.18,  // mm
            'G' => 20.82, // Nama
            'H' => 44.18, // Keterangan
            'I' => 7.18,  // map
            'J' => 6.82,  // hit kbk
            'K' => 10.0,  // Banyak
            'L' => 11.27, // M3
            'M' => 14.27, // Harga
        ];
    }

    /**
     * Format angka per kolom — disamakan dengan sheet "isi jurnal".
     */
    public function columnFormats(): array
    {
        return [
            'B' => 'dd-mm-yyyy',
            'D' => '0.00',
            'K' => 'General',
            'L' => '0.0000',
            'M' => '#,##0_);(#,##0)',
        ];
    }

    public function styles($sheet)
    {
        // Font default seluruh sheet: Calibri 11
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheet->getRowDimension(1)->setRowHeight(15);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $lastRow    = $sheet->getHighestRow();
                $lastColumn = 'M';

                // Fill cyan muda (#CCFFFF) untuk seluruh range data (header + body),
                // KECUALI kolom A (Nama Akun) dan C (jurnal) yang tetap putih —
                // sama persis seperti sheet "isi jurnal" pada file referensi.
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Calibri',
                        'size' => 11,
                        'bold' => false,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFFFF'],
                    ],
                ]);

                // Kembalikan kolom A (Nama Akun) dan C (jurnal) ke putih (tanpa fill).
                $sheet->getStyle("A1:A{$lastRow}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF'],
                    ],
                ]);
                $sheet->getStyle("C1:C{$lastRow}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF'],
                    ],
                ]);

                // Alignment header: jurnal center, No Akun & No & mm left (mengikuti sheet asli)
                $sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // AutoFilter pada baris header, sama seperti sheet referensi.
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

                // Freeze header row, sama seperti template asli
                $sheet->freezePane('A2');
            },
        ];
    }
}