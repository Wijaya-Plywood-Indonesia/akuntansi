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
                    Periode: <strong>{{ $this->getNamaBulan($bulan_dari) }}
                    @if($bulan_dari !== $bulan_sampai) – {{ $this->getNamaBulan($bulan_sampai) }} @endif
                    {{ $tahun }}</strong>
                </span>
            @endif
        </div>
    </form>
</div>

{{-- ============================================================
     TIDAK ADA DATA
============================================================ --}}
@if($sudahFilter && count($laporanData) === 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
        <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-gray-500 dark:text-gray-400 font-medium mb-1">Grup "Laba Rugi" belum ditemukan</p>
        <p class="text-gray-400 dark:text-gray-500 text-sm">
            Buat Akun Group dengan nama <strong>"Laba Rugi"</strong> (tanpa parent),
            lalu buat child group-nya dan isi kolom <strong>Tipe</strong>.
        </p>
    </div>
@endif

{{-- ============================================================
     TABEL LAPORAN
============================================================ --}}
@if($sudahFilter && count($laporanData) > 0)

@php
    $r = $ringkasan;

    // Tipe yang menentukan posisi subtotal fixed
    $tipeReturPotongan = ['retur_potongan'];
    $tipeHPP           = ['hpp', 'beban_produksi'];
    $tipeBebanUsaha    = ['beban_usaha'];
    $tipeLain          = ['pendapatan_lain', 'beban_lain'];

    // Cari indeks section TERAKHIR dari masing-masing tipe
    $lastPendapatanIdx = null;
    $lastReturIdx      = null;
    $lastHppIdx        = null;
    $lastBebanIdx      = null;
    $lastLainIdx       = null;

    foreach ($laporanData as $idx => $section) {
        $tipe = $section['tipe'];
        if ($tipe === 'pendapatan')               $lastPendapatanIdx = $idx;
        if (in_array($tipe, $tipeReturPotongan))  $lastReturIdx      = $idx;
        if (in_array($tipe, $tipeHPP))            $lastHppIdx        = $idx;
        if (in_array($tipe, $tipeBebanUsaha))      $lastBebanIdx      = $idx;
        if (in_array($tipe, $tipeLain))            $lastLainIdx       = $idx;
    }

    // Kalau tidak ada retur/potongan, pakai lastPendapatanIdx sebagai titik Penjualan Bersih
    if ($lastReturIdx === null) $lastReturIdx = $lastPendapatanIdx;
@endphp

<div
    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
    x-data="{ allOpen: false }"
    @laba-rugi-expand.window="allOpen = true"
    @laba-rugi-collapse.window="allOpen = false"
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

        {{-- Tombol Collapse All / Expand All --}}
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="$dispatch('laba-rugi-collapse')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Collapse All
            </button>
            <button
                type="button"
                @click="$dispatch('laba-rugi-expand')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                Expand All
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-amber-400 dark:bg-amber-500">
                    <th class="px-6 py-3 text-left text-xs font-bold text-amber-900 uppercase tracking-wider w-36">Kode Akun</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-amber-900 uppercase tracking-wider">Nama Akun</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-amber-900 uppercase tracking-wider w-52">Nilai Komersial</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">

            @foreach($laporanData as $idx => $section)

                {{-- Render section --}}
                @include('filament.pages.partials.laba-rugi-node', [
                    'node'  => $section,
                    'depth' => 0,
                ])

                {{-- ══════════════════════════════════════════════════
                     SUBTOTAL FIXED — muncul setelah section tertentu
                ══════════════════════════════════════════════════ --}}

                {{-- PENDAPATAN BRUTO: setelah section pendapatan terakhir --}}
                @if($idx === $lastPendapatanIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Pendapatan Bruto',
                        'nilai' => $r['total_pendapatan'],
                        'style' => 'pendapatan_bruto',
                        'kode'  => '',
                        'rumus' => 'Total semua akun pendapatan',
                    ])
                @endif

                {{-- PENJUALAN BERSIH: setelah retur/potongan terakhir --}}
                @if($idx === $lastReturIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Penjualan Bersih',
                        'nilai' => $r['penjualan_bersih'],
                        'style' => 'penjualan_bersih',
                        'kode'  => '',
                        'rumus' => 'Pendapatan Bruto − Retur & Potongan',
                    ])
                @endif

                {{-- TOTAL HPP: setelah HPP/beban_produksi terakhir --}}
                @if($idx === $lastHppIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Total HPP & Biaya Produksi',
                        'nilai' => $r['total_hpp'],
                        'style' => 'total_hpp',
                        'kode'  => '',
                        'rumus' => 'HPP + Biaya Produksi',
                    ])
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba Kotor',
                        'nilai' => $r['laba_kotor'],
                        'style' => 'laba_kotor',
                        'kode'  => '4300',
                        'rumus' => 'Penjualan Bersih − Total HPP',
                    ])
                @endif

                {{-- TOTAL BEBAN USAHA + LABA USAHA: setelah beban_usaha terakhir --}}
                @if($idx === $lastBebanIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Total Beban Usaha',
                        'nilai' => $r['total_beban_usaha'],
                        'style' => 'total_beban',
                        'kode'  => '5400',
                        'rumus' => 'Total semua akun beban usaha',
                    ])
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba (Rugi) Usaha',
                        'nilai' => $r['laba_usaha'],
                        'style' => 'laba_usaha',
                        'kode'  => '',
                        'rumus' => 'Laba Kotor − Total Beban Usaha',
                    ])
                @endif

                {{-- LABA SEBELUM PAJAK: setelah pendapatan_lain/beban_lain terakhir --}}
                @if($idx === $lastLainIdx)
                    @include('filament.pages.partials.laba-rugi-subtotal', [
                        'label' => 'Laba (Rugi) Sebelum Pajak',
                        'nilai' => $r['laba_sebelum_pajak'],
                        'style' => 'laba_sebelum_pajak',
                        'kode'  => '4800',
                        'rumus' => 'Laba Usaha + Pendapatan Lain − Beban Lain',
                    ])
                @endif

            @endforeach

            </tbody>

            {{-- ══ GRAND TOTAL ══ --}}
            <tfoot>
                @php
                    // Tentukan laba bersih berdasarkan section mana yang ada
                    $labaBersih = $r['laba_sebelum_pajak'];
                @endphp
                <tr class="bg-gray-900 dark:bg-gray-950 border-t-2 border-gray-700">
                    <td class="px-6 py-4 text-xs font-mono text-gray-500">4800</td>
                    <td class="px-6 py-4 text-sm font-bold text-white uppercase tracking-widest">
                        Laba (Rugi) Bersih / Sebelum Pajak
                    </td>
                    <td class="px-6 py-4 text-right text-base font-bold {{ $labaBersih >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $this->formatRupiah($labaBersih) }}
                        @if($labaBersih < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>


</div>
@endif

</x-filament-panels::page>