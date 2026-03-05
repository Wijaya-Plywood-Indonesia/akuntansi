<x-filament-panels::page>

{{-- ============================================================
     FILTER
============================================================ --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
        Filter Periode
    </h3>
    <form wire:submit.prevent="filter">
        {{ $this->schema }}
        <div class="mt-4 flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                <x-heroicon-m-funnel class="w-4 h-4" />
                Tampilkan Laporan
            </button>
            @if($sudahFilter)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Periode: <strong>
                        {{ $this->getNamaBulan($bulan_dari) }}
                        @if($bulan_dari !== $bulan_sampai) – {{ $this->getNamaBulan($bulan_sampai) }} @endif
                        {{ $tahun }}
                    </strong>
                </span>
            @endif
        </div>
    </form>
</div>

@if($sudahFilter && count($laporanData) === 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
        <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-gray-500 dark:text-gray-400 font-medium">Grup "Laba Rugi" belum ditemukan</p>
    </div>
@endif

@if($sudahFilter && count($laporanData) > 0)

@php
    $r    = $ringkasanPerBulan;
    $buls = $bulanList;

    // Cari indeks section untuk posisi subtotal
    $tipeReturPotongan = ['retur_potongan'];
    $tipeHPP           = ['hpp', 'beban_produksi'];
    $tipeBebanUsaha    = ['beban_usaha'];
    $tipeLain          = ['pendapatan_lain', 'beban_lain'];

    $lastPendapatanIdx = null;
    $lastReturIdx      = null;
    $lastHppIdx        = null;
    $lastBebanIdx      = null;
    $lastLainIdx       = null;

    foreach ($laporanData as $idx => $section) {
        $tipe = $section['tipe'];
        if ($tipe === 'pendapatan')              $lastPendapatanIdx = $idx;
        if (in_array($tipe, $tipeReturPotongan)) $lastReturIdx      = $idx;
        if (in_array($tipe, $tipeHPP))           $lastHppIdx        = $idx;
        if (in_array($tipe, $tipeBebanUsaha))    $lastBebanIdx      = $idx;
        if (in_array($tipe, $tipeLain))          $lastLainIdx       = $idx;
    }
    if ($lastReturIdx === null) $lastReturIdx = $lastPendapatanIdx;

    $colCount = count($buls) + 1; // +1 kolom nama akun
@endphp

<div
    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
    x-data="{}"
    @laba-rugi-expand.window="$el.querySelectorAll('[data-collapse]').forEach(el => el.style.display = '')"
    @laba-rugi-collapse.window="$el.querySelectorAll('[data-collapse]').forEach(el => el.style.display = 'none')"
>
    {{-- Header --}}
    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Laporan Laba Rugi</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Periode {{ $this->getNamaBulan($bulan_dari) }}
                @if($bulan_dari !== $bulan_sampai) – {{ $this->getNamaBulan($bulan_sampai) }} @endif
                {{ $tahun }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                @click="$dispatch('laba-rugi-collapse')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                Collapse All
            </button>
            <button type="button"
                @click="$dispatch('laba-rugi-expand')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                Expand All
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
    <tr class="bg-amber-400 dark:bg-amber-500">
        <th class="px-4 py-3 text-left text-xs font-bold text-amber-900 uppercase tracking-wider min-w-[120px]">
            Kode Akun
        </th>
        <th class="px-4 py-3 text-left text-xs font-bold text-amber-900 uppercase tracking-wider sticky left-0 bg-amber-400 dark:bg-amber-500 min-w-[280px]">
            Nama Akun
        </th>
        @foreach($buls as $bulan)
            <th class="px-4 py-3 text-right text-xs font-bold text-amber-900 uppercase tracking-wider min-w-[160px]">
                {{ $this->getNamaBulan($bulan) }}
            </th>
        @endforeach
    </tr>
</thead>

            <tbody>
            @foreach($laporanData as $idx => $section)

                @include('filament.pages.partials.laba-rugi-node', [
                    'node'  => $section,
                    'depth' => 0,
                    'buls'  => $buls,
                ])

                {{-- PENDAPATAN BRUTO --}}
                @if($idx === $lastPendapatanIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Pendapatan Bruto',
                        'key'   => 'total_pendapatan',
                        'style' => 'pendapatan_bruto',
                        'rumus' => 'Total semua akun pendapatan',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                @endif

                {{-- PENJUALAN BERSIH --}}
                @if($idx === $lastReturIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Penjualan Bersih',
                        'key'   => 'penjualan_bersih',
                        'style' => 'penjualan_bersih',
                        'rumus' => 'Pendapatan Bruto − Retur & Potongan',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                @endif

                {{-- TOTAL HPP + LABA KOTOR --}}
                @if($idx === $lastHppIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Total HPP & Biaya Produksi',
                        'key'   => 'total_hpp',
                        'style' => 'total_hpp',
                        'rumus' => 'HPP + Biaya Produksi',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba Kotor',
                        'key'   => 'laba_kotor',
                        'style' => 'laba_kotor',
                        'rumus' => 'Penjualan Bersih − Total HPP',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                @endif

                {{-- TOTAL BEBAN + LABA USAHA --}}
                @if($idx === $lastBebanIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Total Beban Usaha',
                        'key'   => 'total_beban_usaha',
                        'style' => 'total_beban',
                        'rumus' => 'Total semua akun beban usaha',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba (Rugi) Usaha',
                        'key'   => 'laba_usaha',
                        'style' => 'laba_usaha',
                        'rumus' => 'Laba Kotor − Total Beban Usaha',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                @endif

                {{-- LABA SEBELUM PAJAK --}}
                @if($idx === $lastLainIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba (Rugi) Sebelum Pajak',
                        'key'   => 'laba_sebelum_pajak',
                        'style' => 'laba_sebelum_pajak',
                        'rumus' => 'Laba Usaha + Pendapatan Lain − Beban Lain',
                        'buls'  => $buls,
                        'r'     => $r,
                    ])
                @endif

            @endforeach
            </tbody>

            {{-- GRAND TOTAL --}}
            <tfoot>
    <tr class="bg-gray-900 dark:bg-gray-950 border-t-2 border-gray-700">
        <td class="px-4 py-4 bg-gray-900 dark:bg-gray-950"></td>
        <td class="px-4 py-4 text-sm font-bold text-white uppercase tracking-widest bg-gray-900 dark:bg-gray-950">
            Laba (Rugi) Bersih / Sebelum Pajak
        </td>
        @foreach($buls as $bulan)
            @php $val = $r[$bulan]['laba_sebelum_pajak'] ?? 0; @endphp
            <td class="px-4 py-4 text-right text-sm font-bold {{ $val >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $this->formatRupiah($val) }}
                @if($val < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
            </td>
        @endforeach
    </tr>
</tfoot>
        </table>
    </div>
</div>
@endif

</x-filament-panels::page>