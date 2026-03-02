<x-filament-panels::page>

    {{-- ── FILTER ─────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 flex flex-wrap gap-4 items-end">

        {{-- Tahun --}}
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tahun</label>
            <select wire:model.live="tahun"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2 shadow-sm focus:ring-2 focus:ring-primary-500">
                @foreach($this->listTahun() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Bulan Awal --}}
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Dari Bulan</label>
            <select wire:model.live="bulanAwal"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2 shadow-sm focus:ring-2 focus:ring-primary-500">
                @foreach($this->listBulan() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Bulan Akhir --}}
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sampai Bulan</label>
            <select wire:model.live="bulanAkhir"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2 shadow-sm focus:ring-2 focus:ring-primary-500">
                @foreach($this->listBulan() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Info periode --}}
        <div class="ml-auto text-sm text-gray-500 dark:text-gray-400 self-center">
            Periode: {{ $this->namaBulan($bulanAwal) }} – {{ $this->namaBulan($bulanAkhir) }} {{ $tahun }}
        </div>
    </div>

    {{-- ── HEADER NERACA ───────────────────────────────────────────── --}}
    <div class="text-center mb-4">
        <p class="text-base font-bold uppercase tracking-wide">PT. NAMA PERUSAHAAN</p>
        <p class="text-sm font-semibold">Neraca</p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Per {{ $this->namaBulan($bulanAkhir) }} {{ $tahun }}
        </p>
    </div>

    {{-- ── TABEL NERACA DUA KOLOM ──────────────────────────────────── --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr>
                    <th class="border border-gray-400 bg-gray-100 dark:bg-gray-700 py-2 px-3 text-center font-bold uppercase w-1/2">
                        AKTIVA
                    </th>
                    <th class="border border-gray-400 bg-gray-100 dark:bg-gray-700 py-2 px-3 text-center font-bold uppercase w-1/2">
                        PASIVA
                    </th>
                </tr>
            </thead>
            <tbody>
                @php
                    $aktiva = $this->neraca['aktiva']['sections'] ?? [];
                    $pasiva = $this->neraca['pasiva']['sections'] ?? [];
                    $totalAktiva = $this->neraca['totalAktiva'] ?? 0;
                    $totalPasiva = $this->neraca['totalPasiva'] ?? 0;

                    // Flatten rows untuk sinkronisasi baris kiri-kanan
                    $aktivaRows = [];
                    foreach ($aktiva as $section) {
                        $aktivaRows[] = ['type' => 'header', 'label' => $section['group']];
                        foreach ($section['items'] as $item) {
                            $aktivaRows[] = ['type' => 'item', 'label' => $item['nama'], 'nilai' => $item['nilai']];
                        }
                        $aktivaRows[] = ['type' => 'subtotal', 'label' => 'Total ' . $section['group'], 'nilai' => $section['total']];
                    }

                    $pasivaRows = [];
                    foreach ($pasiva as $section) {
                        $pasivaRows[] = ['type' => 'header', 'label' => $section['group']];
                        foreach ($section['items'] as $item) {
                            $pasivaRows[] = ['type' => 'item', 'label' => $item['nama'], 'nilai' => $item['nilai']];
                        }
                        $pasivaRows[] = ['type' => 'subtotal', 'label' => 'Total ' . $section['group'], 'nilai' => $section['total']];
                    }

                    $maxRows = max(count($aktivaRows), count($pasivaRows));
                @endphp

                @for($i = 0; $i < $maxRows; $i++)
                    @php
                        $aRow = $aktivaRows[$i] ?? null;
                        $pRow = $pasivaRows[$i] ?? null;
                    @endphp
                    <tr>
                        {{-- Kolom AKTIVA --}}
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-1.5
                            {{ $aRow && $aRow['type'] === 'header' ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold text-center' : '' }}
                            {{ $aRow && $aRow['type'] === 'subtotal' ? 'font-bold' : '' }}">
                            @if($aRow)
                                <div class="flex justify-between items-center">
                                    <span class="{{ $aRow['type'] === 'item' ? 'pl-3' : '' }}">
                                        {{ $aRow['label'] }}
                                    </span>
                                    @if(isset($aRow['nilai']))
                                        <span class="{{ $aRow['type'] === 'subtotal' ? 'font-bold underline' : '' }}">
                                            {{ number_format($aRow['nilai'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>

                        {{-- Kolom PASIVA --}}
                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-1.5
                            {{ $pRow && $pRow['type'] === 'header' ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold text-center' : '' }}
                            {{ $pRow && $pRow['type'] === 'subtotal' ? 'font-bold' : '' }}">
                            @if($pRow)
                                <div class="flex justify-between items-center">
                                    <span class="{{ $pRow['type'] === 'item' ? 'pl-3' : '' }}">
                                        {{ $pRow['label'] }}
                                    </span>
                                    @if(isset($pRow['nilai']))
                                        <span class="{{ $pRow['type'] === 'subtotal' ? 'font-bold underline' : '' }}">
                                            {{ number_format($pRow['nilai'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endfor

                {{-- ── GRAND TOTAL ── --}}
                <tr class="bg-gray-100 dark:bg-gray-700 font-bold text-base">
                    <td class="border border-gray-400 px-3 py-2">
                        <div class="flex justify-between">
                            <span>Total Aktiva</span>
                            <span class="underline decoration-double">
                                {{ number_format($totalAktiva, 0, ',', '.') }}
                            </span>
                        </div>
                    </td>
                    <td class="border border-gray-400 px-3 py-2">
                        <div class="flex justify-between">
                            <span>Total Pasiva</span>
                            <span class="underline decoration-double">
                                {{ number_format($totalPasiva, 0, ',', '.') }}
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── INDIKATOR BALANCE ────────────────────────────────────────── --}}
    @php $selisih = abs($totalAktiva - $totalPasiva); @endphp
    @if($selisih > 0)
        <div class="mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-300 text-sm text-red-700 dark:text-red-400">
            ⚠️ Neraca tidak balance. Selisih: <strong>{{ number_format($selisih, 0, ',', '.') }}</strong>
        </div>
    @else
        <div class="mt-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-300 text-sm text-green-700 dark:text-green-400">
            ✅ Neraca balance.
        </div>
    @endif

</x-filament-panels::page>