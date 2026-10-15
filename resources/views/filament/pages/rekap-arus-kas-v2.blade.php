<x-filament-panels::page>

    <style>
        .rak2-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .rak2-table {
            border-collapse: separate;
            border-spacing: 0;
            width: max-content;
            min-width: 100%;
        }
        .rak2-table th, .rak2-table td { white-space: nowrap; }
    </style>


    <div class="w-full mx-auto">

        {{-- Periode: label di kiri, semua kontrol (preset + rentang custom) digabung di kanan --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <p class="text-sm font-black text-gray-800 dark:text-gray-100">{{ $labelPeriode }}</p>

                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    <div class="grid grid-cols-4 lg:flex items-center gap-1 lg:gap-2">
                        <button type="button" wire:click="terapkanPreset('kemarin')"
                            class="px-1.5 lg:px-3 py-1.5 lg:py-2 rounded-lg text-[10px] lg:text-xs font-bold uppercase tracking-normal lg:tracking-wider border transition-none text-center
                                {{ $periodeAktif === 'kemarin' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200' }}">
                            Kemarin
                        </button>
                        <button type="button" wire:click="terapkanPreset('hari_ini')"
                            class="px-1.5 lg:px-3 py-1.5 lg:py-2 rounded-lg text-[10px] lg:text-xs font-bold uppercase tracking-normal lg:tracking-wider border transition-none text-center
                                {{ $periodeAktif === 'hari_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200' }}">
                            Hari ini
                        </button>
                        <button type="button" wire:click="terapkanPreset('minggu_ini')"
                            class="px-1.5 lg:px-3 py-1.5 lg:py-2 rounded-lg text-[10px] lg:text-xs font-bold uppercase tracking-normal lg:tracking-wider border transition-none text-center
                                {{ $periodeAktif === 'minggu_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200' }}">
                            7 hari
                        </button>
                        <button type="button" wire:click="terapkanPreset('bulan_ini')"
                            class="px-1.5 lg:px-3 py-1.5 lg:py-2 rounded-lg text-[10px] lg:text-xs font-bold uppercase tracking-normal lg:tracking-wider border transition-none text-center
                                {{ $periodeAktif === 'bulan_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200' }}">
                            Bulan ini
                        </button>
                    </div>

                    <span class="hidden lg:inline text-gray-300 dark:text-gray-700">|</span>

                    <div class="flex items-center gap-1.5 lg:gap-2 w-full lg:w-auto">
                        <input type="date" wire:model="tglDariInput"
                            class="flex-1 lg:flex-none min-w-0 px-1.5 lg:px-2.5 py-1.5 lg:py-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-[11px] lg:text-xs font-medium text-gray-800 dark:text-gray-200">
                        <span class="text-gray-500 dark:text-gray-400 flex-shrink-0">&rarr;</span>
                        <input type="date" wire:model="tglSampaiInput"
                            class="flex-1 lg:flex-none min-w-0 px-1.5 lg:px-2.5 py-1.5 lg:py-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-[11px] lg:text-xs font-medium text-gray-800 dark:text-gray-200">
                        <button type="button" wire:click="terapkanRentangCustom"
                            class="flex-shrink-0 px-2.5 lg:px-3 py-1.5 lg:py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[10px] lg:text-xs font-bold uppercase tracking-normal lg:tracking-wider transition-none">
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>

            @if($errorRentang)
            <p class="mt-2 text-xs font-medium text-rose-500 text-right">{{ $errorRentang }}</p>
            @endif
            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium text-right">Maksimal {{ self::MAX_RENTANG_HARI }} hari (1 tahun) sekali tampil</p>
        </div>

        {{-- Pilihan Akun Kas/Bank (poin 2 & 3) --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Kas / Bank yang ditampilkan
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="pilihSemuaAkun" class="text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline">Pilih semua</button>
                    <span class="text-gray-300 dark:text-gray-700">|</span>
                    <button type="button" wire:click="kosongkanAkun" class="text-[11px] font-bold text-gray-500 dark:text-gray-400 hover:underline">Kosongkan</button>
                </div>
            </div>

            @if(empty($daftarAkunKas))
                <div class="text-xs text-gray-500 dark:text-gray-400 italic">Belum ada Sub Anak Akun Kas/Bank (1101.x) yang terdaftar.</div>
            @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-3">
                @foreach($daftarAkunKas as $kode => $nama)
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-800 cursor-pointer text-sm
                    {{ in_array($kode, $akunTerpilih) ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-800' : 'bg-gray-50 dark:bg-gray-800' }}">
                    <input type="checkbox" wire:model.live="akunTerpilih" value="{{ $kode }}"
                        class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span class="font-medium text-gray-700 dark:text-gray-200 truncate">{{ $nama }}</span>
                </label>
                @endforeach
            </div>
            @endif
        </div>

        @php
            $kodeAkun = $hasil['kode_akun'] ?? [];
            $namaAkun = $hasil['nama_akun'] ?? [];
        @endphp

        @if(empty($kodeAkun))
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-sm text-gray-600 dark:text-gray-300 italic font-medium">
            Pilih minimal satu akun kas/bank untuk ditampilkan.
        </div>
        @else

        {{-- Kartu ringkasan saldo akhir per akun --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            @foreach($kodeAkun as $kode)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 min-w-0">
                <div class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider truncate">{{ $namaAkun[$kode] ?? $kode }}</div>
                <div class="text-xl font-black text-gray-800 dark:text-gray-100 mt-1 truncate">Rp {{ number_format($hasil['saldo_akhir'][$kode] ?? 0, 0, ',', '.') }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Awal Rp {{ number_format($hasil['saldo_awal'][$kode] ?? 0, 0, ',', '.') }}
                </div>
            </div>
            @endforeach
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 min-w-0">
                <div class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider truncate">Total Semua Kas &amp; Bank</div>
                <div class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1 truncate">
                    Rp {{ number_format(collect($hasil['saldo_akhir'] ?? [])->sum(), 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Tabel kolom sejajar per akun, mirip Form Setoran Kas Telur --}}
        @if(empty($hasil['baris'] ?? []))
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-sm text-gray-600 dark:text-gray-300 italic font-medium">
            Tidak ada transaksi kas pada periode ini untuk akun yang dipilih.
        </div>
        @else
        @php
            // Akun yang tidak ada mutasi sama sekali di periode ini
            // diringkas jadi 1 kolom Saldo saja (bukan D/K/Saldo penuh),
            // supaya kolom akun yang aktif transaksinya dapat ruang lebih
            // lega untuk nominalnya.
            $akunAktif = collect($kodeAkun)->mapWithKeys(fn($kode) => [
                $kode => (($hasil['total_masuk'][$kode] ?? 0) != 0 || ($hasil['total_keluar'][$kode] ?? 0) != 0),
            ]);
        @endphp
        <div class="rak2-table-wrap bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl">
            <table class="rak2-table text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100 uppercase tracking-wider font-black">
                        <th rowspan="2" class="bg-gray-100 dark:bg-gray-800 px-3 py-3 text-left border-b-2 border-r border-gray-300 dark:border-gray-700" style="min-width:90px;">Tgl</th>
                        <th rowspan="2" class="bg-gray-100 dark:bg-gray-800 px-3 py-3 text-left border-b-2 border-r border-gray-300 dark:border-gray-700" style="min-width:220px;">Keterangan</th>
                        @foreach($kodeAkun as $kode)
                        <th colspan="{{ $akunAktif[$kode] ? 3 : 1 }}" class="px-3 py-3 text-center border-b-2 border-l border-gray-300 dark:border-gray-700">{{ $namaAkun[$kode] ?? $kode }}</th>
                        @endforeach
                        <th rowspan="2" class="px-3 py-3 border-b-2 border-l border-gray-300 dark:border-gray-700" style="min-width:60px;"></th>
                    </tr>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 uppercase font-black">
                        @foreach($kodeAkun as $kode)
                        @if($akunAktif[$kode])
                        <th class="px-2 py-2 text-right border-l border-gray-300 dark:border-gray-700" style="min-width:105px;">D</th>
                        <th class="px-2 py-2 text-right" style="min-width:105px;">K</th>
                        <th class="px-2 py-2 text-right border-r border-gray-300 dark:border-gray-700" style="min-width:110px;">Saldo</th>
                        @else
                        <th class="px-2 py-2 text-right border-l border-r border-gray-300 dark:border-gray-700" style="min-width:90px;">Saldo</th>
                        @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    {{-- Baris saldo awal --}}
                    <tr class="bg-gray-50/60 dark:bg-gray-800/40 font-bold text-gray-700 dark:text-gray-200">
                        <td class="bg-gray-50 dark:bg-gray-800 px-3 py-2 border-r border-gray-200 dark:border-gray-800">Awal</td>
                        <td class="bg-gray-50 dark:bg-gray-800 px-3 py-2 border-r border-gray-200 dark:border-gray-800">Saldo awal periode</td>
                        @foreach($kodeAkun as $kode)
                        @if($akunAktif[$kode])
                        <td class="px-2 py-2 border-l border-gray-200 dark:border-gray-800"></td>
                        <td class="px-2 py-2"></td>
                        <td class="px-2 py-2 text-right border-r border-gray-200 dark:border-gray-800">{{ number_format($hasil['saldo_awal'][$kode] ?? 0, 0, ',', '.') }}</td>
                        @else
                        <td class="px-2 py-2 text-right border-l border-r border-gray-200 dark:border-gray-800">{{ number_format($hasil['saldo_awal'][$kode] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        @endforeach
                        <td class="px-2 py-2"></td>
                    </tr>

                    @foreach($hasil['baris'] as $b)
                    @php
                        // Selang-seling warna baris (zebra striping) supaya mata
                        // tidak ketuker baris saat geser pandangan dari kolom
                        // Keterangan ke kolom nominal yang jauh di kanan — makin
                        // penting di tabel ini karena banyak kolom sejajar per akun.
                        $rowBg = $loop->even ? 'bg-gray-50/70 dark:bg-gray-800/30' : 'bg-white dark:bg-gray-900';
                    @endphp
                    <tr class="{{ $rowBg }} hover:bg-amber-50 dark:hover:bg-amber-900/10">
                        <td class="{{ $rowBg }} px-3 py-2 border-r border-gray-100 dark:border-gray-800 font-semibold text-gray-700 dark:text-gray-200">
                            {{ \Carbon\Carbon::parse($b['tgl'])->format('d/m/y') }}
                        </td>
                        <td class="{{ $rowBg }} px-3 py-2 border-r border-gray-100 dark:border-gray-800 text-gray-800 dark:text-gray-100 truncate" style="max-width:320px;" title="{{ $b['deskripsi'] }}">
                            {{ $b['deskripsi'] }}
                        </td>
                        @foreach($kodeAkun as $kode)
                        @php $k = $b['kolom'][$kode] ?? ['debit'=>null,'kredit'=>null]; @endphp
                        @if($akunAktif[$kode])
                        <td class="px-2 py-2 text-right border-l border-gray-100 dark:border-gray-800 {{ $k['debit'] ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ $k['debit'] ? number_format($k['debit'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-2 py-2 text-right {{ $k['kredit'] ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ $k['kredit'] ? number_format($k['kredit'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-2 py-2 text-right border-r border-gray-100 dark:border-gray-800 text-gray-800 dark:text-gray-100">
                            {{ number_format($b['saldo'][$kode] ?? 0, 0, ',', '.') }}
                        </td>
                        @else
                        <td class="px-2 py-2 text-right border-l border-r border-gray-100 dark:border-gray-800 text-gray-800 dark:text-gray-100">
                            {{ number_format($b['saldo'][$kode] ?? 0, 0, ',', '.') }}
                        </td>
                        @endif
                        @endforeach
                        <td class="px-2 py-2 text-center">
                            <a href="{{ $this->urlJurnal($b['jurnal']) }}"
                                class="text-[10px] font-bold text-sky-600 dark:text-sky-400 border border-sky-300 dark:border-sky-800 rounded px-1.5 py-0.5 hover:bg-sky-50 dark:hover:bg-sky-900/20 whitespace-nowrap">
                                Lihat
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-800 font-black text-gray-800 dark:text-gray-100">
                        <td colspan="2" class="bg-gray-50 dark:bg-gray-800 px-3 py-2 border-t border-r border-gray-200 dark:border-gray-800">Total</td>
                        @foreach($kodeAkun as $kode)
                        @if($akunAktif[$kode])
                        <td class="px-2 py-2 text-right border-l border-t border-gray-200 dark:border-gray-800 text-emerald-600 dark:text-emerald-400">{{ number_format($hasil['total_masuk'][$kode] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-2 py-2 text-right border-t border-gray-200 dark:border-gray-800 text-rose-600 dark:text-rose-400">{{ number_format($hasil['total_keluar'][$kode] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-2 py-2 text-right border-r border-t border-gray-200 dark:border-gray-800">{{ number_format($hasil['saldo_akhir'][$kode] ?? 0, 0, ',', '.') }}</td>
                        @else
                        <td class="px-2 py-2 text-right border-l border-r border-t border-gray-200 dark:border-gray-800">{{ number_format($hasil['saldo_akhir'][$kode] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        @endforeach
                        <td class="px-2 py-2 border-t border-gray-200 dark:border-gray-800"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
        @endif
    </div>
</x-filament-panels::page>