<?php

namespace App\Exports;

use App\Models\Penjualan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PenjualanExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected ?string $tglDari = null,
        protected ?string $tglSampai = null,
    ) {}

    public function collection(): Collection
    {
        $penjualans = Penjualan::query()
            ->scopes(['aktif'])
            ->whereNotNull('validated_by')
            ->when($this->tglDari, fn ($q) => $q->whereDate('tanggal', '>=', $this->tglDari))
            ->when($this->tglSampai, fn ($q) => $q->whereDate('tanggal', '<=', $this->tglSampai))
            ->with(['user', 'validator', 'details'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $rows = collect();

        foreach ($penjualans as $p) {
            if ($p->details->isEmpty()) {
                $rows->push([
                    'penjualan' => $p,
                    'detail' => null,
                ]);
            } else {
                foreach ($p->details as $d) {
                    $rows->push([
                        'penjualan' => $p,
                        'detail' => $d,
                    ]);
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'No. SJ / Nota',
            'Tanggal',
            'Status Transaksi',
            'Nama Pelanggan',
            'Member / Regular',
            'Alamat',
            'Metode Pembayaran',
            'Bank',
            'No. Rekening',
            'Nama Barang',
            'Qty',
            'Satuan',
            'Harga Jual',
            'Subtotal Item',
            'Total Nota',
            'Kasir',
            'Validator',
            'Kendaraan',
            'Plat Kendaraan',
            'Nama Sopir',
            'Keterangan Nota',
        ];
    }

    /**
     * @param array $row
     */
    public function map($row): array
    {
        $p = $row['penjualan'];
        $d = $row['detail'];

        return [
            $p->no_nota,
            $p->tanggal ? $p->tanggal->format('Y-m-d H:i:s') : '-',
            $p->status_transaksi ?? '-',
            $p->nama_customer ?? '-',
            $p->is_member ? 'MEMBER' : 'REGULAR',
            $p->alamat ?? '-',
            $p->metode_pembayaran ?? '-',
            $p->bank ?? '-',
            $p->no_rekening ? ' ' . $p->no_rekening : '-',
            $d ? $d->nama_barang : '-',
            $d ? $d->qty : '-',
            $d ? $d->satuan : '-',
            $d ? $d->harga_jual : 0,
            $d ? $d->subtotal : 0,
            $p->total ?? 0,
            $p->user?->name ?? '-',
            $p->validator?->name ?? '-',
            $p->kendaraan ?? '-',
            $p->plat_kendaraan ?? '-',
            $p->nama_sopir ?? '-',
            $p->keterangan ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4ED8'],
                ],
            ],
        ];
    }
}
