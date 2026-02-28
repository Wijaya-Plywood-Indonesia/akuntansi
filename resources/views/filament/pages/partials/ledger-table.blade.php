{{--
  Partial: ledger-table.blade.php
  Vars: $transaksis (Collection), $saldoAwal (numeric)
--}}

@php
$saldoBerjalan = $saldoAwal;
$totalDebit    = 0;
$totalKredit   = 0;
$tglAwalan     = \Carbon\Carbon::parse($this->filterBulan)
                    ->startOfMonth()->subDay()->format('d/m/Y');
@endphp

<style>
/* Ledger Table — idempotent, load sekali */
.bb-tbl-wrap { overflow-x:auto; }

.bb-tbl {
    width:100%;
    border-collapse:collapse;
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:.76rem;
    font-weight:500;
}

/* HEAD */
.bb-tbl thead tr {
    background:var(--bb-surface-3);
    border-bottom:2px solid var(--bb-border);
}
.bb-tbl th {
    padding:.55rem .9rem;
    font-size:.63rem;
    font-weight:800;
    letter-spacing:.09em;
    text-transform:uppercase;
    color:var(--bb-text-3);
    white-space:nowrap;
    text-align:left;
}
.bb-tbl th.c { text-align:center; }
.bb-tbl th.r { text-align:right;  }

/* BODY */
.bb-tbl td {
    padding:.52rem .9rem;
    color:var(--bb-text-2);
    border-bottom:1px solid var(--bb-border-soft);
    vertical-align:middle;
    font-weight:500;
}
.bb-tbl tbody tr:last-child td { border-bottom:none; }

/* Zebra */
.bb-tbl tbody tr:nth-child(even) td { background:var(--bb-surface-2); }

/* Hover — konsisten sage, tidak bentrok dengan warna lain */
.bb-tbl tbody tr:hover td { background:var(--bb-accent-soft) !important; transition:background .12s; }

/* ── Baris Saldo Awal ── */
.bb-tbl .row-awal td {
    background: var(--bb-amber-bg) !important;
    border-bottom: 1px solid var(--bb-amber-border);
    font-style: italic;
    font-weight: 600;
}
.bb-tbl tbody tr.row-awal:hover td { background:var(--bb-amber-bg) !important; }

/* ── Baris Footer ── */
.bb-tbl .row-footer td {
    background: var(--bb-surface-3) !important;
    border-top: 2px solid var(--bb-border);
    font-weight: 800;
    font-size: .7rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    text-align: center;
}
.bb-tbl .row-footer td.bb-debit  { color: var(--bb-debit)  !important; text-align:right; }
.bb-tbl .row-footer td.bb-kredit { color: var(--bb-kredit) !important; text-align:right; }
.bb-tbl tbody tr.row-footer:hover td { background:var(--bb-surface-3) !important; }

/* Debit & kredit warna konsisten di SEMUA baris */
.bb-tbl td.bb-debit  { color: var(--bb-debit)  !important; }
.bb-tbl td.bb-kredit { color: var(--bb-kredit) !important; }

/* ── Cell types ── */
.bb-tgl {
    font-family:'JetBrains Mono',monospace;
    font-size:.68rem;
    font-weight:500;
    color:var(--bb-text-3);
    text-align:center;
    white-space:nowrap;
}

.bb-jurnal {
    font-family:'JetBrains Mono',monospace;
    font-size:.68rem;
    font-weight:600;
    color:var(--bb-accent-text);
    background:var(--bb-accent-soft);
    border:1px solid var(--bb-accent-mid);
    padding:2px 7px;
    border-radius:5px;
    display:inline-block;
    white-space:nowrap;
}

.dark .bb-jurnal {
    color:var(--bb-accent);
    background:var(--bb-accent-soft);
    border-color:var(--bb-accent-mid);
}

.bb-keterangan {
    color: var(--bb-text-1);
    font-weight: 600;
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bb-debit  { font-family:'JetBrains Mono',monospace; font-weight:600; font-size:.73rem; color:var(--bb-debit);  text-align:right; white-space:nowrap; }
.bb-kredit { font-family:'JetBrains Mono',monospace; font-weight:600; font-size:.73rem; color:var(--bb-kredit); text-align:right; white-space:nowrap; }
.bb-saldo  { font-family:'JetBrains Mono',monospace; font-weight:700; font-size:.73rem; color:var(--bb-text-1); text-align:right; white-space:nowrap; }
.bb-saldo.neg { color:var(--bb-neg); }

/* Saldo awal khusus */
.bb-saldo-awal-val {
    font-family:'JetBrains Mono',monospace;
    font-weight:700;
    font-size:.74rem;
    color:var(--bb-amber);
    text-align:right;
    white-space:nowrap;
}
.bb-saldo-awal-val.neg { color:var(--bb-neg); }

/* Footer saldo highlight */
.bb-footer-saldo {
    font-family:'JetBrains Mono',monospace;
    font-weight:800;
    font-size:.76rem;
    color:var(--bb-accent-text);
    background:var(--bb-accent-soft) !important;
    text-align:right;
    white-space:nowrap;
}
.bb-footer-saldo.neg { color:var(--bb-neg); background:color-mix(in srgb, var(--bb-neg) 10%, transparent) !important; }

/* Label saldo awal tengah */
.bb-awal-label {
    text-align: center;
    font-weight: 700;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--bb-amber);
}
</style>

<div class="bb-tbl-wrap">
<table class="bb-tbl">
    <thead>
        <tr>
            <th class="c" style="width:85px">Tgl</th>
            <th style="width:85px">Jurnal</th>
            <th>Keterangan</th>
            <th class="r" style="width:105px">Debit</th>
            <th class="r" style="width:105px">Kredit</th>
            <th class="r" style="width:115px">Saldo</th>
        </tr>
    </thead>
    <tbody>

        {{-- ── BARIS SALDO AWAL ── --}}
        <tr class="row-awal">
            <td class="bb-tgl" style="color:var(--bb-amber);font-style:italic">{{ $tglAwalan }}</td>
            <td style="text-align:center;color:var(--bb-amber);opacity:.7;font-size:.68rem;font-style:italic">—</td>
            <td class="bb-awal-label">Saldo Awalan</td>
            <td></td>
            <td></td>
            <td class="bb-saldo-awal-val {{ $saldoAwal < 0 ? 'neg' : '' }}">
                @if($saldoAwal < 0)–@endif{{ number_format(abs($saldoAwal), 0, ',', '.') }}
            </td>
        </tr>

        {{-- ── BARIS TRANSAKSI ── --}}
        @foreach($transaksis as $trx)
        @php
            $mode = strtolower($trx->hit_kbk ?? '');
            if ($mode === '' || $mode === null) {
                $nominal = $trx->harga ?? 0;
            } elseif ($mode === 'b' || $mode === 'banyak') {
                $nominal = ($trx->banyak ?? 0) * ($trx->harga ?? 0);
            } else {
                $nominal = ($trx->m3 ?? 0) * ($trx->harga ?? 0);
            }

            $isDebit = strtolower($trx->map) === 'd';
            if ($isDebit) {
                $saldoBerjalan += $nominal;
                $totalDebit    += $nominal;
            } else {
                $saldoBerjalan -= $nominal;
                $totalKredit   += $nominal;
            }
        @endphp
        <tr>
            <td class="bb-tgl">{{ \Carbon\Carbon::parse($trx->tgl)->format('d/m/Y') }}</td>
            <td>
                @if($trx->jurnal)
                    <span class="bb-jurnal">{{ $trx->jurnal }}</span>
                @else
                    <span style="color:var(--bb-text-3);font-size:.65rem">—</span>
                @endif
            </td>
            <td class="bb-keterangan" title="{{ $trx->keterangan }}">{{ $trx->keterangan }}</td>
            <td class="bb-debit">{{ $isDebit  ? number_format($nominal, 0, ',', '.') : '' }}</td>
            <td class="bb-kredit">{{ !$isDebit ? number_format($nominal, 0, ',', '.') : '' }}</td>
            <td class="bb-saldo {{ $saldoBerjalan < 0 ? 'neg' : '' }}">
                @if($saldoBerjalan < 0)–@endif{{ number_format(abs($saldoBerjalan), 0, ',', '.') }}
            </td>
        </tr>
        @endforeach

        {{-- ── FOOTER SISA SALDO ── --}}
        <tr class="row-footer">
            <td colspan="3" style="text-align:center;letter-spacing:.06em">Sisa Saldo</td>
            <td class="bb-debit"  style="font-weight:800;color:var(--bb-debit)">{{ number_format($totalDebit,  0, ',', '.') }}</td>
            <td class="bb-kredit" style="font-weight:800;color:var(--bb-kredit)">{{ number_format($totalKredit, 0, ',', '.') }}</td>
            <td class="bb-footer-saldo {{ $saldoBerjalan < 0 ? 'neg' : '' }}">
                @if($saldoBerjalan < 0)–@endif{{ number_format(abs($saldoBerjalan), 0, ',', '.') }}
            </td>
        </tr>

    </tbody>
</table>
</div>