<x-filament-panels::page>

    <style>
        [x-cloak] { display: none !important; }

        .cat-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease;
        }

        .cat-row.expanded .cat-body {
            max-height: 2000px;
        }

        .chevron {
            transition: transform 0.15s ease;
        }

        .cat-row.expanded .chevron {
            transform: rotate(180deg);
        }
    </style>

    <div class="w-full mx-auto"
        x-data="{
            expanded: {},
            toggle(key) {
                this.expanded[key] = !this.expanded[key];
            },
            isOpen(key) {
                return !!this.expanded[key];
            }
        }">

        {{-- Header + Filter Periode --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
            <div>
                <h1 class="text-lg font-black text-gray-800 dark:text-gray-100">Rekap Arus Kas</h1>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ $labelPeriode }}</p>
            </div>

            <div class="grid grid-cols-2 lg:flex lg:flex-wrap items-center gap-2">
                <button type="button" wire:click="terapkanPreset('kemarin')"
                    class="px-4 py-2.5 lg:py-2 rounded-lg text-xs font-bold uppercase tracking-wider border transition-none text-center
                        {{ $periodeAktif === 'kemarin' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-300' }}">
                    Kemarin
                </button>
                <button type="button" wire:click="terapkanPreset('hari_ini')"
                    class="px-4 py-2.5 lg:py-2 rounded-lg text-xs font-bold uppercase tracking-wider border transition-none text-center
                        {{ $periodeAktif === 'hari_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-300' }}">
                    Hari ini
                </button>
                <button type="button" wire:click="terapkanPreset('minggu_ini')"
                    class="px-4 py-2.5 lg:py-2 rounded-lg text-xs font-bold uppercase tracking-wider border transition-none text-center
                        {{ $periodeAktif === 'minggu_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-300' }}">
                    7 hari terakhir
                </button>
                <button type="button" wire:click="terapkanPreset('bulan_ini')"
                    class="px-4 py-2.5 lg:py-2 rounded-lg text-xs font-bold uppercase tracking-wider border transition-none text-center
                        {{ $periodeAktif === 'bulan_ini' ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-300' }}">
                    Bulan ini
                </button>
            </div>
        </div>

        {{-- Rentang Tanggal Custom --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-5">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1.5 mb-3">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Pilih rentang tanggal sendiri
            </span>

            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <input type="date" wire:model="tglDariInput"
                        class="flex-1 lg:flex-none px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 min-w-0">
                    <span class="text-gray-300 dark:text-gray-600 flex-shrink-0">&rarr;</span>
                    <input type="date" wire:model="tglSampaiInput"
                        class="flex-1 lg:flex-none px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 min-w-0">
                </div>

                <button type="button" wire:click="terapkanRentangCustom"
                    class="w-full lg:w-auto px-5 py-2.5 lg:py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider transition-none">
                    Terapkan
                </button>
            </div>

            <div class="mt-3 flex flex-col lg:flex-row lg:items-center gap-1 lg:gap-3">
                @if($errorRentang)
                    <span class="text-xs font-medium text-rose-500">{{ $errorRentang }}</span>
                @endif
                <span class="text-[11px] text-gray-400 dark:text-gray-500 lg:ml-auto">Maksimal {{ self::MAX_RENTANG_HARI }} hari (1 tahun) sekali tampil</span>
            </div>
        </div>

        {{-- 4 Kartu Ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 min-w-0">
                <div class="text-[11px] lg:text-xs font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">Kas Awal</div>
                <div class="text-lg lg:text-xl font-black text-gray-700 dark:text-gray-200 mt-1 truncate">Rp {{ number_format($hasil['saldo_awal'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 min-w-0">
                <div class="text-[11px] lg:text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7L17 17M17 7V17H7" /></svg>
                    <span class="truncate">Kas Masuk</span>
                </div>
                <div class="text-lg lg:text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1 truncate">Rp {{ number_format($hasil['total_masuk'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-4 min-w-0">
                <div class="text-[11px] lg:text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider flex items-center gap-1 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7l10 10M7 17V7h10" /></svg>
                    <span class="truncate">Kas Keluar</span>
                </div>
                <div class="text-lg lg:text-xl font-black text-rose-600 dark:text-rose-400 mt-1 truncate">Rp {{ number_format($hasil['total_keluar'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 min-w-0">
                <div class="text-[11px] lg:text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider whitespace-nowrap">Kas Akhir</div>
                <div class="text-lg lg:text-xl font-black text-amber-600 dark:text-amber-400 mt-1 truncate">Rp {{ number_format($hasil['saldo_akhir'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>

        @if(!($hasil['balanced'] ?? true))
        <div class="mb-5 flex items-center gap-2 px-4 py-2.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-xs font-bold text-amber-600 dark:text-amber-400">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Ada selisih Rp {{ number_format(abs($hasil['selisih_validasi'] ?? 0), 0, ',', '.') }} antara hasil hitungan dan saldo riil — cek kembali data jurnal pada periode ini.
        </div>
        @endif

        {{-- Rincian per Kategori --}}
        <div class="text-sm font-black text-gray-700 dark:text-gray-200 mb-3">Rincian per kategori</div>

        @if(empty($hasil['rincian'] ?? []))
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-sm text-gray-400 italic">
            Tidak ada transaksi kas pada periode ini.
        </div>
        @else
        <div class="space-y-2">
            @foreach($hasil['rincian'] as $i => $kat)
            @php
                $isIn = $kat['tipe'] === 'in';
                $isNetral = $kat['tipe'] === 'netral';
                $rowKey = 'kat-' . $i;
            @endphp
            <div class="cat-row bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden"
                :class="isOpen('{{ $rowKey }}') ? 'expanded' : ''">

                <div class="flex items-center gap-3 px-4 py-3.5 cursor-pointer select-none" @click="toggle('{{ $rowKey }}')">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0
                        {{ $isNetral ? 'bg-gray-100 dark:bg-gray-800' : ($isIn ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20') }}">
                        <svg class="w-4 h-4 {{ $isNetral ? 'text-gray-400' : ($isIn ? 'text-emerald-500' : 'text-rose-500') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($isNetral)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                            @elseif($isIn)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7L17 17M17 7V17H7" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7l10 10M7 17V7h10" />
                            @endif
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">{{ $kat['nama'] }}</div>
                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ count($kat['transaksi']) }} transaksi</div>
                    </div>
                    <div class="text-sm font-black text-right whitespace-nowrap {{ $isNetral ? 'text-gray-500 dark:text-gray-400' : ($isIn ? 'text-emerald-500' : 'text-rose-500') }}">
                        {{ $isNetral ? '' : ($isIn ? '+ ' : '- ') }}Rp {{ number_format($kat['nilai'], 0, ',', '.') }}
                    </div>
                    <svg class="chevron w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                <div class="cat-body border-t border-gray-100 dark:border-gray-800" x-cloak x-show="isOpen('{{ $rowKey }}')">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($kat['transaksi'] as $tx)
                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 px-4 py-2.5 sm:pl-[3.75rem] text-sm">
                            <div class="flex items-center justify-between sm:contents">
                                <div class="text-xs text-gray-400 dark:text-gray-500 sm:min-w-[74px]">
                                    {{ \Carbon\Carbon::parse($tx['tgl'])->format('d M Y') }}
                                </div>
                                <div class="font-bold sm:hidden whitespace-nowrap
                                    {{ $isNetral ? 'text-gray-500 dark:text-gray-400' : ($isIn ? 'text-emerald-500' : 'text-rose-500') }}">
                                    {{ $isNetral ? '' : ($isIn ? '+ ' : '- ') }}Rp {{ number_format($tx['nilai'], 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ $tx['deskripsi'] }}
                                    <span class="text-gray-300 dark:text-gray-600">&middot; #{{ $tx['jurnal'] }}</span>
                                </div>
                                @if(!empty($tx['keterangan']))
                                <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $tx['keterangan'] }}</div>
                                @endif
                            </div>
                            <div class="hidden sm:block font-bold min-w-[100px] text-right whitespace-nowrap
                                {{ $isNetral ? 'text-gray-500 dark:text-gray-400' : ($isIn ? 'text-emerald-500' : 'text-rose-500') }}">
                                {{ $isNetral ? '' : ($isIn ? '+ ' : '- ') }}Rp {{ number_format($tx['nilai'], 0, ',', '.') }}
                            </div>
                            <a href="{{ $this->urlJurnal($tx['jurnal']) }}"
                                class="self-start sm:self-auto text-[11px] font-bold text-sky-500 border border-sky-300 dark:border-sky-800 rounded-md px-2.5 py-1 hover:bg-sky-50 dark:hover:bg-sky-900/20 whitespace-nowrap">
                                Lihat di Jurnal
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

    </div>
</x-filament-panels::page>