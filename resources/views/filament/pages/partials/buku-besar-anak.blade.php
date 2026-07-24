@php
$kodeAkun     = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun;
$namaAkun     = $akun->nama_anak_akun ?? $akun->nama_sub_anak_akun;
$saldoAwal    = $this->getSaldoAwal($kodeAkun);
$saldoAwalQty = $this->getSaldoAwalQty($kodeAkun); // null jika bukan akun persediaan
$saldoAwalM3  = $this->getSaldoAwalM3($kodeAkun);  // null jika bukan akun persediaan
$saldoAkhir   = $this->getTotalRecursive($akun);
$stokAkhir    = $this->getTotalRecursiveQty($akun); // null jika tidak punya akun persediaan di bawahnya
$m3Akhir      = $this->getTotalRecursiveM3($akun);  // null jika tidak punya akun persediaan di bawahnya
$transaksis   = $this->getTransaksiByKode($kodeAkun);
$jumlahTrx    = $transaksis->count();
$depth        = $depth ?? 0;

$children = collect();
if (isset($akun->children))     $children = $children->merge($akun->children);
if (isset($akun->subAnakAkuns)) $children = $children->merge($akun->subAnakAkuns);

$tampilkan = ($jumlahTrx > 0) || ($saldoAwal != 0) || ($saldoAkhir != 0) || $children->count() > 0;

$saldoClass = $saldoAkhir < 0 ? 'neg' : '';
@endphp

@if($tampilkan)

<style>
.bb-anak { background:var(--bb-surface); border:1px solid var(--bb-border-soft); border-radius:var(--bb-r-md); overflow:hidden; box-shadow:var(--bb-shadow-sm); }
.bb-anak-head { display:flex; align-items:center; justify-content:space-between; padding:.6rem 1rem; background:var(--bb-surface-3); border-bottom:1px solid var(--bb-border-soft); flex-wrap:wrap; gap:.4rem; }
.bb-anak-left { display:flex; align-items:center; gap:.5rem; }
.bb-anak-dot { width:7px; height:7px; border-radius:50%; background:var(--bb-accent-mid); flex-shrink:0; }
.bb-anak-code { font-family:'JetBrains Mono',monospace; font-size:.68rem; font-weight:500; color:var(--bb-text-3); background:var(--bb-surface-2); border:1px solid var(--bb-border); padding:2px 7px; border-radius:5px; }
.bb-anak-name { font-size:.82rem; font-weight:700; color:var(--bb-text-1); }
.bb-anak-saldo { font-family:'JetBrains Mono',monospace; font-size:.82rem; font-weight:600; color:var(--bb-text-2); }
.bb-anak-saldo.neg { color:var(--bb-neg); }
.bb-anak-stok { font-family:'JetBrains Mono',monospace; font-size:.68rem; font-weight:700; color:var(--bb-accent-text); background:var(--bb-accent-soft); border:1px solid var(--bb-accent-mid); padding:2px 8px; border-radius:20px; }
.bb-anak-stok.neg { color:var(--bb-neg); background:transparent; border-color:var(--bb-neg); }
.bb-anak-m3 { font-family:'JetBrains Mono',monospace; font-size:.68rem; font-weight:700; color:var(--bb-amber); background:var(--bb-amber-bg); border:1px solid var(--bb-amber-border); padding:2px 8px; border-radius:20px; }
.bb-anak-m3.neg { color:var(--bb-neg); background:transparent; border-color:var(--bb-neg); }
.bb-sub-wrap { padding:.5rem 0 .5rem 1.25rem; border-left:2.5px solid var(--bb-border); margin:.5rem .75rem; display:flex; flex-direction:column; gap:.5rem; }
</style>

<div class="bb-anak">

    {{-- Header akun --}}
    <div class="bb-anak-head">
        <div class="bb-anak-left">
            <span class="bb-anak-dot" @if($depth > 0) style="background:var(--bb-amber-border)" @endif></span>
            <span class="bb-anak-code">{{ $kodeAkun }}</span>
            <span class="bb-anak-name">{{ $namaAkun }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:.6rem">
            @if($stokAkhir != 0)
            <span class="bb-anak-stok {{ $stokAkhir < 0 ? 'neg' : '' }}">
                Stok: {{ (float)$stokAkhir == (int)$stokAkhir ? number_format(abs($stokAkhir), 0, ',', '.') : rtrim(rtrim(number_format(abs($stokAkhir), 4, ',', '.'), '0'), ',') }}
            </span>
            @endif
            @if($m3Akhir != 0)
            <span class="bb-anak-m3 {{ $m3Akhir < 0 ? 'neg' : '' }}">
                M3: {{ rtrim(rtrim(number_format(abs($m3Akhir), 4, ',', '.'), '0'), ',') }}
            </span>
            @endif
            <span class="bb-anak-saldo {{ $saldoClass }}">
                @if($saldoAkhir < 0)–@endif
                Rp {{ number_format(abs($saldoAkhir), 0, ',', '.') }}
            </span>
        </div>
    </div>

    {{-- Children rekursif --}}
    @if($children->count())
    <div class="bb-sub-wrap">
        @foreach($children as $child)
            @include('filament.pages.partials.buku-besar-anak', ['akun' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
    @endif

    {{-- Ledger table --}}
    @if($jumlahTrx > 0 || $saldoAwal != 0)
        @include('filament.pages.partials.ledger-table', [
            'transaksis'   => $transaksis,
            'saldoAwal'    => $saldoAwal,
            'saldoAwalQty' => $saldoAwalQty,
            'saldoAwalM3'  => $saldoAwalM3,
            'saldoNormal'  => strtolower($akun->saldo_normal ?? 'debit'),
        ])
    @endif

</div>

@endif