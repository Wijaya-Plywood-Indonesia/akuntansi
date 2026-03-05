<x-filament-panels::page>
{{-- FILTER SECTION TETAP SAMA --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Filter Periode</h3>
    <form wire:submit.prevent="filter">
        {{ $this->schema }}
        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                <x-heroicon-m-funnel class="w-4 h-4" /> Tampilkan Laporan
            </button>
        </div>
    </form>
</div>

@if($sudahFilter && count($laporanData) > 0)
@php
    $r = $ringkasanPerBulan;
    $buls = $bulanList;
    
    $tipeReturPotongan = ['retur_potongan'];
    $tipeHPP = ['hpp', 'beban_produksi'];
    $tipeBebanUsaha = ['beban_usaha'];
    $tipeLain = ['pendapatan_lain', 'beban_lain'];
    $lastPendapatanIdx = null; $lastReturIdx = null; $lastHppIdx = null; $lastBebanIdx = null; $lastLainIdx = null;

    foreach ($laporanData as $idx => $section) {
        $tipe = $section['tipe'];
        if ($tipe === 'pendapatan') $lastPendapatanIdx = $idx;
        if (in_array($tipe, $tipeReturPotongan)) $lastReturIdx = $idx;
        if (in_array($tipe, $tipeHPP)) $lastHppIdx = $idx;
        if (in_array($tipe, $tipeBebanUsaha)) $lastBebanIdx = $idx;
        if (in_array($tipe, $tipeLain)) $lastLainIdx = $idx;
    }
    if ($lastReturIdx === null) $lastReturIdx = $lastPendapatanIdx;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" 
     x-data="{ allOpen: false }"
     @laba-rugi-expand.window="allOpen = true; $el.querySelectorAll('[data-collapse]').forEach(el => el.style.display = '')"
     @laba-rugi-collapse.window="allOpen = false; $el.querySelectorAll('[data-collapse]').forEach(el => el.style.display = 'none')">
    
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest">Laporan Laba Rugi</h2>
        <div class="flex items-center gap-2">
            <button type="button" @click="$dispatch('laba-rugi-collapse')" class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-md shadow-sm">
                Collapse All
            </button>
            <button type="button" @click="$dispatch('laba-rugi-expand')" class="px-3 py-1.5 text-xs font-semibold text-white bg-gray-800 rounded-md shadow-sm">
                Expand All
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-amber-400">
                    <th class="px-4 py-3 text-left text-xs font-extrabold text-amber-950 uppercase tracking-widest w-32">KODE AKUN</th>
                    <th class="px-4 py-3 text-left text-xs font-extrabold text-amber-950 uppercase tracking-widest">NAMA AKUN</th>
                    @foreach($buls as $bulan)
                        <th class="px-4 py-3 text-right text-xs font-extrabold text-amber-950 uppercase tracking-widest min-w-[160px]">{{ $this->getNamaBulan($bulan) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($laporanData as $idx => $section)
                    @include('filament.pages.partials.laba-rugi-node', ['node' => $section, 'depth' => 0, 'buls' => $buls])
                    
                    {{-- Subtotal Logic --}}
                    @if($idx === $lastPendapatanIdx)
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Pendapatan Bruto', 'key' => 'total_pendapatan', 'style' => 'pendapatan_bruto', 'rumus' => 'Total semua akun pendapatan', 'buls' => $buls, 'r' => $r])
                    @endif
                    @if($idx === $lastReturIdx)
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Penjualan Bersih', 'key' => 'penjualan_bersih', 'style' => 'penjualan_bersih', 'rumus' => 'Pendapatan Bruto − Retur & Potongan', 'buls' => $buls, 'r' => $r])
                    @endif
                    @if($idx === $lastHppIdx)
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Total HPP & Biaya Produksi', 'key' => 'total_hpp', 'style' => 'total_hpp', 'rumus' => 'HPP + Biaya Produksi', 'buls' => $buls, 'r' => $r])
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Laba Kotor', 'key' => 'laba_kotor', 'style' => 'laba_kotor', 'rumus' => 'Penjualan Bersih − Total HPP', 'buls' => $buls, 'r' => $r])
                    @endif
                    @if($idx === $lastBebanIdx)
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Total Beban Usaha', 'key' => 'total_beban_usaha', 'style' => 'total_beban', 'rumus' => 'Total semua akun beban usaha', 'buls' => $buls, 'r' => $r])
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Laba (Rugi) Usaha', 'key' => 'laba_usaha', 'style' => 'laba_usaha', 'rumus' => 'Laba Kotor − Total Beban Usaha', 'buls' => $buls, 'r' => $r])
                    @endif
                    @if($idx === $lastLainIdx)
                        @include('filament.pages.partials.laba-rugi-subtotal', ['label' => 'Laba (Rugi) Sebelum Pajak', 'key' => 'laba_sebelum_pajak', 'style' => 'laba_sebelum_pajak', 'rumus' => 'Laba Usaha + Pendapatan Lain − Beban Lain', 'buls' => $buls, 'r' => $r])
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
</x-filament-panels::page>