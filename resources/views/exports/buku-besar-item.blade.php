@php
$kodeAkun   = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun;
$namaAkun   = $akun->nama_anak_akun ?? $akun->nama_sub_anak_akun;
$saldoAwal  = $exporter->getSaldoAwal($kodeAkun);
$saldoAkhir = $exporter->getTotalRecursive($akun);
$transaksis = $exporter->getTransaksiByKode($kodeAkun);
$jumlahTrx  = $transaksis->count();
$depth      = $depth ?? 0;

$children = collect();
if (isset($akun->children))     $children = $children->merge($akun->children);
if (isset($akun->subAnakAkuns)) $children = $children->merge($akun->subAnakAkuns);

$tampilkan = ($jumlahTrx > 0) || ($saldoAwal != 0) || ($saldoAkhir != 0) || $children->count() > 0;
@endphp

@if($tampilkan)
    <tr>
        <td colspan="9" style="font-weight: bold; background-color: #d8f3dc; color: #1b4332; border: 1px solid #000000; padding-left: {{ $depth * 15 }}px; height: 22px; vertical-align: middle;">
            {{ str_repeat('   ', $depth) }}{{ $kodeAkun }} - {{ $namaAkun }}
        </td>
        <td style="font-weight: bold; text-align: right; background-color: #d8f3dc; color: #1b4332; border: 1px solid #000000; height: 22px; vertical-align: middle;">
            Rp {{ number_format($saldoAkhir, 0, ',', '.') }}
        </td>
    </tr>

    @if($jumlahTrx > 0 || $saldoAwal != 0)
        <tr style="background-color: #edf3ed; font-weight: bold; height: 20px;">
            <th style="border: 1px solid #000000; color: #3d5c3d; vertical-align: middle;">Tanggal</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">No.J</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; vertical-align: middle;">Nama</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; vertical-align: middle;">Keterangan</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">Qty</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">m³</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">Harga</th>
            <th style="border: 1px solid #000000; color: #1a6b3c; text-align: right; vertical-align: middle;">Debit</th>
            <th style="border: 1px solid #000000; color: #b5303a; text-align: right; vertical-align: middle;">Kredit</th>
            <th style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">Saldo</th>
        </tr>

        <tr style="background-color: #f9fbf9; height: 20px;">
            <td colspan="4" style="border: 1px solid #000000; font-style: italic; color: #666666; vertical-align: middle;">Saldo Awal Periode</td>
            <td style="border: 1px solid #000000; text-align: right; color: #888888; vertical-align: middle;">—</td>
            <td style="border: 1px solid #000000; text-align: right; color: #888888; vertical-align: middle;">—</td>
            <td style="border: 1px solid #000000; text-align: right; color: #888888; vertical-align: middle;">—</td>
            <td style="border: 1px solid #000000; text-align: right; color: #888888; vertical-align: middle;">—</td>
            <td style="border: 1px solid #000000; text-align: right; color: #888888; vertical-align: middle;">—</td>
            <td style="border: 1px solid #000000; text-align: right; font-weight: bold; color: #1a2e1a; vertical-align: middle;">
                {{ number_format($saldoAwal, 0, ',', '.') }}
            </td>
        </tr>

        @php
        $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
        $isKredit    = in_array($saldoNormal, ['kredit', 'credit', 'k']);
        $running     = (float) $saldoAwal;
        $totalDebit  = 0.0;
        $totalKredit = 0.0;
        $totalQty    = 0.0;
        $totalM3     = 0.0;
        @endphp

        @foreach($transaksis as $trx)
            @php
            $nominal = $exporter->hitungNominal($trx);
            $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
            $qty     = (float) ($trx->banyak ?? 0);
            $m3      = (float) ($trx->m3 ?? 0);

            if ($isKredit) {
                $running += $isDebit ? -$nominal : $nominal;
            } else {
                $running += $isDebit ? $nominal : -$nominal;
            }

            if ($isDebit) {
                $totalDebit += $nominal;
                if ($trx->banyak !== null && $qty > 0) {
                    $totalQty += $qty;
                }
                if ($trx->m3 !== null && $m3 > 0) {
                    $totalM3 += $m3;
                }
            } else {
                $totalKredit += $nominal;
                if ($trx->banyak !== null && $qty > 0) {
                    $totalQty -= $qty;
                }
                if ($trx->m3 !== null && $m3 > 0) {
                    $totalM3 -= $m3;
                }
            }
            @endphp
            <tr style="height: 18px;">
                <td style="border: 1px solid #000000; color: #2d3e2d; vertical-align: middle;">
                    {{ \Carbon\Carbon::parse($trx->tgl)->format('d/m/Y') }}
                </td>
                <td style="border: 1px solid #000000; text-align: right; color: #2d3e2d; vertical-align: middle;">
                    {{ $trx->jurnal }}
                </td>
                <td style="border: 1px solid #000000; color: #2d3e2d; vertical-align: middle;">
                    {{ $trx->nama ?? '—' }}
                </td>
                <td style="border: 1px solid #000000; color: #555555; vertical-align: middle;">
                    {{ $trx->keterangan ?? '—' }}
                </td>
                <td style="border: 1px solid #000000; text-align: right; color: #2d3e2d; vertical-align: middle;">
                    @if($trx->banyak !== null && (float)$trx->banyak > 0)
                        {{ (float)$trx->banyak == (int)$trx->banyak ? number_format((float)$trx->banyak, 0, ',', '.') : rtrim(rtrim(number_format((float)$trx->banyak, 4, ',', '.'), '0'), ',') }}
                    @else
                        —
                    @endif
                </td>
                <td style="border: 1px solid #000000; text-align: right; color: #2d3e2d; vertical-align: middle;">
                    @if($trx->m3 !== null && (float)$trx->m3 > 0)
                        {{ rtrim(rtrim(number_format((float)$trx->m3, 4, ',', '.'), '0'), ',') }}
                    @else
                        —
                    @endif
                </td>
                <td style="border: 1px solid #000000; text-align: right; color: #2d3e2d; vertical-align: middle;">
                    @if($trx->harga !== null && (float)$trx->harga > 0)
                        {{ number_format((float)$trx->harga, 0, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td style="border: 1px solid #000000; text-align: right; vertical-align: middle; @if($isDebit) font-weight: bold; color: #1a6b3c; @else color: #cccccc; @endif">
                    @if($isDebit)
                        {{ number_format($nominal, 0, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td style="border: 1px solid #000000; text-align: right; vertical-align: middle; @if(!$isDebit) font-weight: bold; color: #b5303a; @else color: #cccccc; @endif">
                    @if(!$isDebit)
                        {{ number_format($nominal, 0, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td style="border: 1px solid #000000; text-align: right; font-weight: bold; color: #1a2e1a; vertical-align: middle;">
                    {{ number_format($running, 0, ',', '.') }}
                </td>
            </tr>
        @endforeach

        <tr style="background-color: #edf3ed; font-weight: bold; height: 22px;">
            <td colspan="4" style="border: 1px solid #000000; color: #3d5c3d; text-align: right; vertical-align: middle;">Total Mutasi Bulan Ini</td>
            <td style="border: 1px solid #000000; text-align: right; color: #3d5c3d; vertical-align: middle;">
                @if($totalQty != 0)
                    {{ (float)$totalQty == (int)$totalQty ? number_format($totalQty, 0, ',', '.') : rtrim(rtrim(number_format($totalQty, 4, ',', '.'), '0'), ',') }}
                @else
                    —
                @endif
            </td>
            <td style="border: 1px solid #000000; text-align: right; color: #3d5c3d; vertical-align: middle;">
                @if($totalM3 != 0)
                    {{ rtrim(rtrim(number_format($totalM3, 4, ',', '.'), '0'), ',') }}
                @else
                    —
                @endif
            </td>
            <td style="border: 1px solid #000000; vertical-align: middle;"></td>
            <td style="border: 1px solid #000000; text-align: right; color: #1a6b3c; vertical-align: middle;">
                {{ number_format($totalDebit, 0, ',', '.') }}
            </td>
            <td style="border: 1px solid #000000; text-align: right; color: #b5303a; vertical-align: middle;">
                {{ number_format($totalKredit, 0, ',', '.') }}
            </td>
            <td style="border: 1px solid #000000; text-align: right; color: #1a2e1a; vertical-align: middle;">
                {{ number_format($saldoAkhir, 0, ',', '.') }}
            </td>
        </tr>
        <tr></tr>
    @endif

    @if($children->count())
        @foreach($children as $child)
            @include('exports.buku-besar-item', ['akun' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
@endif
