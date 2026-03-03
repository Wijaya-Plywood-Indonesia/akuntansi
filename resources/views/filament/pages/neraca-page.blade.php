<x-filament-panels::page>

    {{-- ══════════════════════════════════════════════════════════
         FILTER — 2 Dropdown Besar
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">

        <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200 mb-5">
            Pilih Periode Neraca
        </h3>

        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-6">

            {{-- DARI --}}
            <div class="flex-1 w-full">
                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    Dari Periode
                </label>
                <select
                    wire:model.live="periodeAwal"
                    class="w-full rounded-xl border-2 border-gray-300 dark:border-gray-600
                           dark:bg-gray-700 dark:text-gray-200
                           text-base px-4 py-3 shadow-sm
                           focus:border-primary-500 focus:ring-2 focus:ring-primary-200
                           transition-colors cursor-pointer">
                    @foreach($this->opsiPeriode() as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Pemisah --}}
            <div class="hidden sm:flex items-center pb-3 text-gray-400 dark:text-gray-500">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </div>

            {{-- SAMPAI --}}
            <div class="flex-1 w-full">
                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    Sampai Periode
                </label>
                <select
                    wire:model.live="periodeAkhir"
                    class="w-full rounded-xl border-2 border-gray-300 dark:border-gray-600
                           dark:bg-gray-700 dark:text-gray-200
                           text-base px-4 py-3 shadow-sm
                           focus:border-primary-500 focus:ring-2 focus:ring-primary-200
                           transition-colors cursor-pointer">
                    @foreach($this->opsiPeriode() as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Info jumlah periode --}}
            <div class="flex-shrink-0 pb-1">
                @if(!$this->periodeValid())
                    <div class="flex items-center gap-2 bg-red-50 dark:bg-red-900/20
                                border border-red-200 dark:border-red-700
                                text-red-700 dark:text-red-400
                                rounded-xl px-4 py-3 text-sm font-medium">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        "Dari" tidak boleh lebih akhir dari "Sampai"
                    </div>
                @else
                    <div class="flex items-center gap-2 bg-primary-50 dark:bg-primary-900/20
                                border border-primary-200 dark:border-primary-700
                                text-primary-700 dark:text-primary-400
                                rounded-xl px-4 py-3 text-sm font-medium">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>
                            <strong>{{ $this->jumlahPeriode() }}</strong>
                            neraca ditampilkan
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Hint max 12 bulan --}}
        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
            * Maksimal 12 bulan sekaligus. Jika rentang melebihi 12 bulan, sistem otomatis membatasi sampai bulan ke-12.
        </p>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         KONTEN NERACA
    ══════════════════════════════════════════════════════════ --}}
    @if(!$this->periodeValid())
        {{-- Error state --}}
        <div class="text-center py-16">
            <svg class="mx-auto w-14 h-14 text-red-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Perbaiki periode filter terlebih dahulu.</p>
        </div>

    @elseif($this->jumlahPeriode() === 0)
        {{-- Empty state --}}
        <div class="text-center py-16">
            <svg class="mx-auto w-14 h-14 text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Pilih periode untuk menampilkan neraca.</p>
        </div>

    @else
    {{-- ── LOOP CARD PER BULAN ─────────────────────────────── --}}
    <div class="space-y-8">
        @foreach($this->neracaMulti as $key => $neraca)
        @php
            $isBalance = abs($neraca['totalAktiva'] - $neraca['totalPasiva']) < 1;

            // Flatten baris untuk sinkronisasi kiri-kanan dalam card ini
            $aktivaRows = [];
            foreach ($neraca['aktiva']['sections'] as $section) {
                $aktivaRows[] = ['type' => 'header',   'label' => $section['group']];
                foreach ($section['items'] as $item) {
                    $aktivaRows[] = ['type' => 'item', 'label' => '- ' . $item['nama'], 'nilai' => $item['nilai']];
                }
                $aktivaRows[] = ['type' => 'subtotal', 'label' => 'Total ' . $section['group'], 'nilai' => $section['total']];
            }

            $pasivaRows = [];
            foreach ($neraca['pasiva']['sections'] as $section) {
                $pasivaRows[] = ['type' => 'header',   'label' => $section['group']];
                foreach ($section['items'] as $item) {
                    $pasivaRows[] = ['type' => 'item', 'label' => '- ' . $item['nama'], 'nilai' => $item['nilai']];
                }
                $pasivaRows[] = ['type' => 'subtotal', 'label' => 'Total ' . $section['group'], 'nilai' => $section['total']];
            }

            $maxRows = max(count($aktivaRows), count($pasivaRows), 1);
            $fmt = fn(?float $v) => $v !== null ? number_format($v, 0, ',', '.') : '-';
        @endphp

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

            {{-- Card Header --}}
            <div class="flex items-center justify-between px-6 py-4
                        bg-gradient-to-r from-gray-50 to-gray-100
                        dark:from-gray-700 dark:to-gray-700/60
                        border-b border-gray-200 dark:border-gray-700">

                <div>
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5">
                        PT. NAMA PERUSAHAAN
                    </p>
                    <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                        Neraca — {{ $neraca['label'] }}
                    </h2>
                </div>

                {{-- Badge Balance --}}
                @if($isBalance)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                             bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400
                             border border-green-200 dark:border-green-700">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Balance
                </span>
                @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                             bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400
                             border border-red-200 dark:border-red-700">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Tidak Balance
                </span>
                @endif
            </div>

            {{-- Tabel Neraca Dua Kolom --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse" style="min-width: 600px">

                    {{-- Sub-header AKTIVA | PASIVA --}}
                    <thead>
                        <tr>
                            <th class="w-1/2 border border-gray-200 dark:border-gray-700
                                       bg-blue-50 dark:bg-blue-900/20
                                       py-3 px-5 text-center text-sm font-bold
                                       text-blue-700 dark:text-blue-300 uppercase tracking-wide">
                                AKTIVA
                            </th>
                            <th class="w-1/2 border border-gray-200 dark:border-gray-700
                                       bg-green-50 dark:bg-green-900/20
                                       py-3 px-5 text-center text-sm font-bold
                                       text-green-700 dark:text-green-300 uppercase tracking-wide">
                                PASIVA
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {{-- Baris data --}}
                        @for($i = 0; $i < $maxRows; $i++)
                        @php
                            $aRow = $aktivaRows[$i] ?? null;
                            $pRow = $pasivaRows[$i] ?? null;

                            $rowType = $aRow['type'] ?? $pRow['type'] ?? 'item';
                            $isHdr   = $rowType === 'header';
                            $isSub   = $rowType === 'subtotal';
                        @endphp

                        <tr class="
                            {{ $isHdr ? 'bg-gray-50 dark:bg-gray-700/50' : '' }}
                            {{ $isSub ? 'bg-gray-100 dark:bg-gray-700' : '' }}
                            {{ !$isHdr && !$isSub ? 'hover:bg-gray-50/50 dark:hover:bg-gray-700/20' : '' }}
                            transition-colors">

                            {{-- Kolom AKTIVA --}}
                            <td class="border border-gray-100 dark:border-gray-700 px-5 py-2
                                {{ $isHdr ? 'text-center font-bold text-gray-700 dark:text-gray-200' : '' }}
                                {{ $isSub ? 'font-semibold text-gray-800 dark:text-gray-100' : '' }}
                                {{ !$isHdr && !$isSub ? 'text-gray-700 dark:text-gray-300' : '' }}">
                                @if($aRow)
                                    <div class="flex justify-between items-center gap-4">
                                        <span>{{ $aRow['label'] }}</span>
                                        @if(isset($aRow['nilai']) && !$isHdr)
                                            <span class="tabular-nums flex-shrink-0
                                                {{ $isSub ? 'border-t border-b border-gray-400 dark:border-gray-500 px-1' : '' }}">
                                                {{ $fmt($aRow['nilai']) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- Kolom PASIVA --}}
                            <td class="border border-gray-100 dark:border-gray-700 px-5 py-2
                                {{ $isHdr ? 'text-center font-bold text-gray-700 dark:text-gray-200' : '' }}
                                {{ $isSub ? 'font-semibold text-gray-800 dark:text-gray-100' : '' }}
                                {{ !$isHdr && !$isSub ? 'text-gray-700 dark:text-gray-300' : '' }}">
                                @if($pRow)
                                    <div class="flex justify-between items-center gap-4">
                                        <span>{{ $pRow['label'] }}</span>
                                        @if(isset($pRow['nilai']) && !$isHdr)
                                            <span class="tabular-nums flex-shrink-0
                                                {{ $isSub ? 'border-t border-b border-gray-400 dark:border-gray-500 px-1' : '' }}">
                                                {{ $fmt($pRow['nilai']) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endfor

                        {{-- Grand Total --}}
                        <tr class="bg-gray-800 dark:bg-gray-900 text-white">
                            <td class="border border-gray-700 px-5 py-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-sm uppercase tracking-wide">Total Aktiva</span>
                                    <span class="tabular-nums font-bold text-base
                                                 border-t-2 border-b-4 border-double border-white px-2">
                                        {{ $fmt($neraca['totalAktiva']) }}
                                    </span>
                                </div>
                            </td>
                            <td class="border border-gray-700 px-5 py-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-sm uppercase tracking-wide">Total Pasiva</span>
                                    <span class="tabular-nums font-bold text-base
                                                 border-t-2 border-b-4 border-double border-white px-2">
                                        {{ $fmt($neraca['totalPasiva']) }}
                                    </span>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            {{-- Card Footer: info saldo awal --}}
            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700/30
                        border-t border-gray-100 dark:border-gray-700
                        text-xs text-gray-400 dark:text-gray-500 flex justify-between">
                <span>Saldo awal dari Buku Besar {{ $neraca['label'] }}</span>
                @if(!$isBalance)
                <span class="text-red-500 font-medium">
                    Selisih: {{ $fmt(abs($neraca['totalAktiva'] - $neraca['totalPasiva'])) }}
                </span>
                @else
                <span class="text-green-500 font-medium">✓ Aktiva = Pasiva</span>
                @endif
            </div>

        </div>{{-- end card --}}
        @endforeach
    </div>
    @endif

</x-filament-panels::page>