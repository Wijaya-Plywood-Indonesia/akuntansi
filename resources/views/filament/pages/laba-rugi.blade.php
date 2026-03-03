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
                    Periode: <strong>{{ $this->getNamaBulan($bulan_dari) }} – {{ $this->getNamaBulan($bulan_sampai) }} {{ $tahun }}</strong>
                </span>
            @endif
        </div>
    </form>
</div>

{{-- ============================================================
     TIDAK ADA DATA / BELUM KONFIGURASI
============================================================ --}}
@if($sudahFilter && count($laporanData) === 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
        <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-gray-500 dark:text-gray-400 font-medium mb-1">Grup "Laba Rugi" belum ditemukan</p>
        <p class="text-gray-400 dark:text-gray-500 text-sm">
            Buat sebuah Akun Group dengan nama <strong>"Laba Rugi"</strong> (tanpa parent),
            lalu buat child group-nya (Penjualan, HPP, Beban Usaha, dll) dan isi kolom <strong>Tipe</strong>.
        </p>
    </div>
@endif

{{-- ============================================================
     TABEL LAPORAN
============================================================ --}}
@if($sudahFilter && count($laporanData) > 0)

@php
    $r = $ringkasan;

    // Kita akan track tipe terakhir yang sudah dirender
    // untuk tahu kapan menyisipkan subtotal
    $tipeYangSudahRender = [];
    $allTipes = collect($laporanData)->pluck('tipe')->unique()->toArray();

    // Tentukan setelah tipe apa subtotal Laba Kotor muncul
    // = setelah semua grup hpp & beban_produksi selesai
    $tipeHPP      = ['hpp', 'beban_produksi'];
    $tipeBeban    = ['beban_usaha'];
    $tipeLain     = ['beban_lain', 'pendapatan_lain'];

    // Cari indeks section terakhir yang bertipe hpp/beban_produksi
    $lastHppIdx      = null;
    $lastBebanIdx    = null;
    $lastBebanLainIdx = null;
    foreach ($laporanData as $idx => $section) {
        if (in_array($section['tipe'], $tipeHPP))   $lastHppIdx      = $idx;
        if (in_array($section['tipe'], $tipeBeban)) $lastBebanIdx    = $idx;
        if (in_array($section['tipe'], $tipeLain))  $lastBebanLainIdx = $idx;
    }
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

    {{-- Header --}}
    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Laporan Laba Rugi</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            Periode {{ $this->getNamaBulan($bulan_dari) }} – {{ $this->getNamaBulan($bulan_sampai) }} {{ $tahun }}
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Kode Akun</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Akun</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-52">Nilai Komersial</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">

                @foreach($laporanData as $idx => $section)

                    {{-- ── Render section (group + children) ── --}}
                    @include('filament.pages.partials.laba-rugi-node', [
                        'node'  => $section,
                        'depth' => 0,
                    ])

                    {{-- ── SUBTOTAL: LABA KOTOR
                         Muncul setelah section HPP/beban_produksi terakhir ── --}}
                    @if($idx === $lastHppIdx && ($r['ada_hpp'] || $r['total_pendapatan'] != 0))
                        <tr class="bg-blue-50 dark:bg-blue-900/20 border-t-2 border-blue-200 dark:border-blue-700">
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3 text-sm font-bold text-blue-800 dark:text-blue-200 uppercase tracking-wide">
                                Laba Kotor
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-bold {{ $r['laba_kotor'] >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-red-600 dark:text-red-400' }}">
                                {{ $this->formatRupiah($r['laba_kotor']) }}
                                @if($r['laba_kotor'] < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
                            </td>
                        </tr>
                    @endif

                    {{-- ── SUBTOTAL: LABA (RUGI) USAHA
                         Muncul setelah semua beban_usaha selesai ── --}}
                    @if($idx === $lastBebanIdx)
                        <tr class="bg-indigo-50 dark:bg-indigo-900/20 border-t-2 border-indigo-200 dark:border-indigo-700">
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3 text-sm font-bold text-indigo-800 dark:text-indigo-200 uppercase tracking-wide">
                                Laba (Rugi) Usaha
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-bold {{ $r['laba_usaha'] >= 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-red-600 dark:text-red-400' }}">
                                {{ $this->formatRupiah($r['laba_usaha']) }}
                                @if($r['laba_usaha'] < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
                            </td>
                        </tr>
                    @endif

                    {{-- ── SUBTOTAL: LABA (RUGI) SEBELUM PAJAK
                         Muncul setelah pendapatan_lain / beban_lain terakhir ── --}}
                    @if($idx === $lastBebanLainIdx && $r['ada_lain'])
                        <tr class="bg-emerald-50 dark:bg-emerald-900/20 border-t-2 border-emerald-200 dark:border-emerald-700">
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3 text-sm font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wide">
                                Laba (Rugi) Sebelum Pajak
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-bold {{ $r['laba_sebelum_pajak'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600 dark:text-red-400' }}">
                                {{ $this->formatRupiah($r['laba_sebelum_pajak']) }}
                                @if($r['laba_sebelum_pajak'] < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
                            </td>
                        </tr>
                    @endif

                @endforeach

            </tbody>

            {{-- ── GRAND TOTAL: LABA (RUGI) BERSIH ── --}}
            <tfoot>
                @php
                    // Kalau tidak ada pendapatan_lain/beban_lain, laba bersih = laba usaha
                    // Kalau ada, laba bersih = laba sebelum pajak
                    $labaBersih = $r['ada_lain'] ? $r['laba_sebelum_pajak'] : ($lastBebanIdx !== null ? $r['laba_usaha'] : $r['laba_kotor']);
                @endphp
                <tr class="bg-gray-900 dark:bg-gray-950 border-t-2 border-gray-700">
                    <td class="px-6 py-4"></td>
                    <td class="px-6 py-4 text-sm font-bold text-white uppercase tracking-widest">
                        Laba (Rugi) Bersih
                    </td>
                    <td class="px-6 py-4 text-right text-base font-bold {{ $labaBersih >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $this->formatRupiah($labaBersih) }}
                        @if($labaBersih < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ── RINGKASAN PANEL ── --}}
    <div class="px-6 py-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Ringkasan</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Pendapatan</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->formatRupiah($r['total_pendapatan']) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total HPP</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->formatRupiah($r['total_hpp']) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Laba Kotor</p>
                <p class="text-sm font-bold {{ $r['laba_kotor'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ $this->formatRupiah($r['laba_kotor']) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Beban Usaha</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->formatRupiah($r['total_beban_usaha']) }}</p>
            </div>
        </div>
    </div>

</div>
@endif

</x-filament-panels::page>