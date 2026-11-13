<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>PO {{ $po->nomor_po }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 28px 36px;
        }

        /* ── HEADER ── */
        .header-wrap {
            width: 100%;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 14px;
            text-align: center;
            font-family: "Times New Roman", Times, serif;
            font-weight: bold;
        }

        .company-name {
            font-size: 14px;
            text-transform: uppercase;
        }

        .company-address {
            font-size: 13px;
            margin-top: 6px;
        }

        .company-contact {
            font-size: 13px;
            margin-top: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-logo {
            width: 90px;
        }

        .header-text {
            text-align: center;
        }

        /* ── JUDUL ── */
        .judul-block {
            text-align: center;
            margin: 10px 0 14px;
        }

        .judul-block .judul {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .judul-block .sub {
            font-size: 11px;
            margin-top: 3px;
        }

        /* ── TABEL UTAMA ── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #111;
            padding: 6px 7px;
            font-size: 11px;
            vertical-align: top;
        }

        .main-table th {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
        }

        .kecil {
            font-size: 10px;
            color: #555;
        }

        /* ── TTD (di dalam tabel) ── */
        .ttd-cell {
            text-align: center;
            vertical-align: top;
            padding: 5px 7px 6px;
        }

        .ttd-space {
            height: 48px;
        }

        .ttd-name {
            font-size: 11px;
        }
    </style>
</head>

<body>

    {{-- ══════════ HEADER PERUSAHAAN ══════════ --}}
    @php
        $logoPath = public_path('images/logo-wijaya-1.png');
        $logoSrc = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    @endphp
    <div class="header-wrap">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="Logo" style="width:80px;">
                    @endif
                </td>
                <td class="header-text">
                    <div class="company-name">{{ config('app.company_name', 'PERUSAHAAN TRIPLEK WIJAYA') }}</div>
                    <div class="company-address">
                        {{ config('app.company_address', 'Jl. Dokter Cipto Gang 1B No 16 RT. 05 RW. 01, Desa Bedali Kec. Lawang Kab. Malang') }}
                    </div>
                    <div class="company-contact">Telp. {{ config('app.company_phone', '0341-426683') }} Email :
                        {{ config('app.company_email', 'triplekwijaya80@gmail.com') }}</div>
                </td>
                <td class="header-logo"></td>
            </tr>
        </table>
    </div>

    {{-- ══════════ JUDUL ══════════ --}}
    <div class="judul-block">
        <div class="judul">Purchase Order (PO)</div>
        <div class="sub">{{ $po->nomor_po }}</div>
        <div class="sub">{{ $po->tanggal ? $po->tanggal->format('d – m – Y') : '-' }}</div>
    </div>

    @php
        $items = $po->items;
        $rowCount = max($items->count(), 1);
        $adaExtra = $po->total_diskon > 0 || $po->total_ppn > 0 || $po->ongkir > 0 || $po->biaya_lain > 0;
        $rp = fn($n) => 'Rp. ' . number_format($n, 0, ',', '.') . ',-';
    @endphp

    {{-- ══════════ TABEL UTAMA ══════════ --}}
    <table class="main-table">
        <thead>
            <tr>
                <th style="width:5%;">No.</th>
                <th style="width:22%;">Kepada</th>
                <th style="width:13%;">QTY</th>
                <th style="width:38%;">Keterangan</th>
                <th style="width:22%;">Harga</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    @if ($i === 0)
                        <td class="center" rowspan="{{ $rowCount }}">1.</td>
                        <td class="center" rowspan="{{ $rowCount }}">
                            {{ $po->supplier_name ?? '-' }}
                            @if ($po->supplier_phone)
                                <br><span class="kecil">Telp. {{ $po->supplier_phone }}</span>
                            @endif
                            @if ($po->supplier_address)
                                <br><span class="kecil">{{ $po->supplier_address }}</span>
                            @endif
                        </td>
                    @endif
                    <td>{{ number_format($item->qty, 0, ',', '.') }} {{ $item->satuan }}</td>
                    <td>
                        {{ $item->nama_barang }}
                        @if ($item->kode_barang && $item->kode_barang !== $item->nama_barang)
                            ({{ $item->kode_barang }})
                        @endif
                        @if ($item->kubikasi > 0)
                            <br><span class="kecil">{{ number_format($item->kubikasi, 3, ',', '.') }} M³</span>
                        @endif
                        @if ($item->catatan)
                            <br><span class="kecil">{{ $item->catatan }}</span>
                        @endif
                    </td>
                    <td>{{ $rp($item->subtotal) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="center">1.</td>
                    <td class="center">{{ $po->supplier_name ?? '-' }}</td>
                    <td colspan="3" class="center" style="color:#888;">Tidak ada item</td>
                </tr>
            @endforelse

            {{-- Catatan PO --}}
            @if ($po->catatan)
                <tr>
                    <td colspan="5">
                        <strong>Catatan / Instruksi :</strong><br>
                        {!! nl2br(e($po->catatan)) !!}
                    </td>
                </tr>
            @endif

            {{-- Ringkasan nominal --}}
            @if ($adaExtra)
                <tr>
                    <td colspan="4" class="right">Subtotal</td>
                    <td>{{ $rp($po->sub_total) }}</td>
                </tr>
            @endif
            @if ($po->total_diskon > 0)
                <tr>
                    <td colspan="4" class="right">Diskon</td>
                    <td>– {{ $rp($po->total_diskon) }}</td>
                </tr>
            @endif
            @if ($po->total_ppn > 0)
                <tr>
                    <td colspan="4" class="right">PPN</td>
                    <td>{{ $rp($po->total_ppn) }}</td>
                </tr>
            @endif
            @if ($po->ongkir > 0)
                <tr>
                    <td colspan="4" class="right">Ongkir</td>
                    <td>{{ $rp($po->ongkir) }}</td>
                </tr>
            @endif
            @if ($po->biaya_lain > 0)
                <tr>
                    <td colspan="4" class="right">Biaya Lain</td>
                    <td>{{ $rp($po->biaya_lain) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="4" class="right">TOTAL</td>
                <td>{{ $rp($po->grand_total) }}</td>
            </tr>

            {{-- Tanda tangan (di dalam tabel) --}}
            <tr>
                <td colspan="2" class="ttd-cell">
                    Mengajukan,
                    <div class="ttd-space"></div>
                    <div class="ttd-name">Nia</div>
                </td>
                <td colspan="3" class="ttd-cell">
                    Mengetahui,
                    <div class="ttd-space"></div>
                    <div class="ttd-name">Safira</div>
                </td>
            </tr>
            <tr>
                <td colspan="5" class="ttd-cell">
                    Mengetahui,
                    <div class="ttd-space"></div>
                    <div class="ttd-name">B. Shirlyn</div>
                </td>
            </tr>
        </tbody>
    </table>

</body>

</html>
