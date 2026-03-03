<x-filament-panels::page>

    {{-- ================================================================
         FILTER SECTION
    ================================================================ --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
            Filter Periode
        </h3>

        <form wire:submit.prevent="filter">
            {{ $this->schema }}

            <div class="mt-4 flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors duration-150"
                >
                    <x-heroicon-m-funnel class="w-4 h-4" />
                    Tampilkan Laporan
                </button>

                @if($sudahFilter)
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Periode: {{ $this->getNamaBulan($bulan_dari) }} – {{ $this->getNamaBulan($bulan_sampai) }} {{ $tahun }}
                    </span>
                @endif
            </div>
        </form>
    </div>

    {{-- ================================================================
         LAPORAN TABLE
    ================================================================ --}}
    @if($sudahFilter && count($laporanData) > 0)

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

            {{-- Header Laporan --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    Laporan Laba Rugi
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Periode {{ $this->getNamaBulan($bulan_dari) }} – {{ $this->getNamaBulan($bulan_sampai) }} {{ $tahun }}
                </p>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    {{-- Table Head --}}
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">
                                Kode Akun
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Nama Akun
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-48">
                                Nilai Komersial
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @foreach($laporanData as $rootNode)
                            @include('filament.pages.partials.laba-rugi-node', [
                                'node'  => $rootNode,
                                'depth' => 0,
                            ])
                        @endforeach
                    </tbody>

                    {{-- Grand Total --}}
                    @php
                        $grandTotal = collect($laporanData)->sum('total_nilai');
                    @endphp
                    <tfoot>
                        <tr class="bg-gray-900 dark:bg-gray-950">
                            <td class="px-6 py-4"></td>
                            <td class="px-6 py-4 text-sm font-bold text-white uppercase tracking-wide">
                                Laba (Rugi) Bersih
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold {{ $grandTotal >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $this->formatRupiah($grandTotal) }}
                                @if($grandTotal < 0)
                                    <span class="text-xs font-normal">(Rugi)</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    @elseif($sudahFilter && count($laporanData) === 0)

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
            <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Tidak ada data untuk periode yang dipilih, atau belum ada Akun Group yang dikonfigurasi.
            </p>
        </div>

    @endif

</x-filament-panels::page>