<x-filament-panels::page>
    @php
        // Extract podium players
        $firstPlace = $top3_raw[0] ?? null;
        $secondPlace = $top3_raw[1] ?? null;
        $thirdPlace = $top3_raw[2] ?? null;
        
        // Calculate max belanja as baseline for progress bars
        $maxBelanja = $firstPlace ? $firstPlace->total_belanja : 1;

        // Calculate global statistics
        $totalAllBelanja = 0;
        $totalAllTransaksi = 0;
        $totalPelanggan = count($others) + count(array_filter($podium));
        
        foreach(array_filter($podium) as $p) {
            $totalAllBelanja += $p->total_belanja;
            $totalAllTransaksi += $p->total_transaksi;
        }
        foreach($others as $o) {
            $totalAllBelanja += $o->total_belanja;
            $totalAllTransaksi += $o->total_transaksi;
        }
    @endphp

    <div x-data="{ search: '' }" class="space-y-8 py-2">
        
        {{-- Date Filter Card --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                {{-- Left: Filter Title / Icon --}}
                <div class="flex items-center gap-2.5">
                    <div class="p-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl text-zinc-500 dark:text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-zinc-950 dark:text-white">Filter Berdasarkan Tanggal</h3>
                        <p class="text-[10px] text-zinc-400 dark:text-zinc-500 font-medium">Batasi peringkat berdasarkan rentang tanggal penjualan</p>
                    </div>
                </div>

                {{-- Right: Date Inputs --}}
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Start Date Input --}}
                    <div class="flex flex-col space-y-1">
                        <label for="startDate" class="text-[9px] font-extrabold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Dari Tanggal</label>
                        <input type="date" id="startDate" wire:model.live="startDate" max="{{ now()->setTimezone('Asia/Jakarta')->format('Y-m-d') }}" 
                               class="block px-3 py-1.5 text-xs rounded-xl bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800/80 focus:ring-1 focus:ring-zinc-400 dark:focus:ring-zinc-700 focus:border-zinc-400 dark:focus:border-zinc-700 transition-all duration-200 text-zinc-800 dark:text-zinc-200 cursor-pointer placeholder-zinc-400 dark:placeholder-zinc-500">
                    </div>

                    {{-- Separator --}}
                    <span class="text-xs text-zinc-300 dark:text-zinc-700 self-end pb-2.5 hidden sm:inline-block">s/d</span>

                    {{-- End Date Input --}}
                    <div class="flex flex-col space-y-1">
                        <label for="endDate" class="text-[9px] font-extrabold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Hingga Tanggal</label>
                        <input type="date" id="endDate" wire:model.live="endDate" max="{{ now()->setTimezone('Asia/Jakarta')->format('Y-m-d') }}" 
                               class="block px-3 py-1.5 text-xs rounded-xl bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800/80 focus:ring-1 focus:ring-zinc-400 dark:focus:ring-zinc-700 focus:border-zinc-400 dark:focus:border-zinc-700 transition-all duration-200 text-zinc-800 dark:text-zinc-200 cursor-pointer placeholder-zinc-400 dark:placeholder-zinc-500">
                    </div>

                    {{-- Reset Button (Visible when filters are set) --}}
                    @if($startDate || $endDate)
                        <button type="button" wire:click="resetFilters" 
                                class="inline-flex items-center justify-center px-3 py-1.5 text-[10px] font-bold rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/50 dark:bg-rose-950/20 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-400 cursor-pointer transition-colors self-end mb-0.5">
                            Reset
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sort Toggle --}}
        <div class="flex justify-center">
            <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-inner">
                <button type="button" wire:click="$set('sortBy', 'belanja')"
                        @class([
                            'inline-flex items-center gap-1.5 px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 cursor-pointer',
                            'bg-white dark:bg-zinc-900 text-zinc-950 dark:text-white shadow-sm border border-zinc-200/50 dark:border-zinc-800/80' => $sortBy === 'belanja',
                            'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' => $sortBy !== 'belanja',
                        ])>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Total Belanja Terbanyak
                </button>
                <button type="button" wire:click="$set('sortBy', 'transaksi')"
                        @class([
                            'inline-flex items-center gap-1.5 px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 cursor-pointer',
                            'bg-white dark:bg-zinc-900 text-zinc-950 dark:text-white shadow-sm border border-zinc-200/50 dark:border-zinc-800/80' => $sortBy === 'transaksi',
                            'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' => $sortBy !== 'transaksi',
                        ])>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Nota / Transaksi Terbanyak
                </button>
            </div>
        </div>

        {{-- Top 3 Podium Section --}}
        <div class="hidden md:block space-y-4">
            <h2 class="text-center text-[10px] font-black uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                Top 3 Pelanggan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch max-w-5xl mx-auto px-2">
                
                {{-- 2nd Place Card (Left on Desktop, 2nd on Mobile) --}}
                <div class="flex flex-col justify-between bg-white dark:bg-zinc-900 rounded-2xl p-6 border border-zinc-200 dark:border-zinc-800/80 shadow-sm hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 transition-all duration-300 group order-2 md:order-1">
                    @if($secondPlace)
                        <div class="flex flex-col w-full">
                            <div class="flex items-start justify-between">
                                <span class="text-[9px] font-extrabold uppercase tracking-widest text-zinc-400 dark:text-zinc-500 font-mono">
                                    Peringkat 02
                                </span>
                                <span class="text-xs font-bold text-zinc-400">🥈</span>
                            </div>
                            
                            <div class="flex flex-col items-center text-center mt-4">
                                <div class="w-16 h-16 rounded-full border-2 border-slate-300 dark:border-slate-500 bg-zinc-50 dark:bg-zinc-950 p-0.5 group-hover:scale-[1.03] transition-transform duration-300 overflow-hidden shrink-0">
                                    <img src="{{ $secondPlace->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                                </div>
                                <h3 class="mt-4 font-bold text-zinc-950 dark:text-white text-sm md:text-base truncate w-full group-hover:text-zinc-800 dark:group-hover:text-zinc-200 transition-colors">
                                    {{ $secondPlace->name }}
                                </h3>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center w-full">
                            <div class="text-left">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Total Belanja</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 font-mono">Rp{{ number_format($secondPlace->total_belanja, 0, ',', '.') }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Aktivitas</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $secondPlace->total_transaksi }} Transaksi</span>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-full min-h-[160px] text-center text-zinc-400 dark:text-zinc-600">
                            <span class="text-xl font-light">?</span>
                            <p class="mt-2 text-xs font-medium">Peringkat 2 belum terisi</p>
                        </div>
                    @endif
                </div>

                {{-- 1st Place Card (High Contrast Center) --}}
                <div class="flex flex-col justify-between bg-white dark:bg-zinc-900 rounded-2xl p-6 border border-zinc-200 dark:border-zinc-800/80 shadow-sm hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 transition-all duration-300 group order-1 md:order-2">
                    @if($firstPlace)
                        <div class="flex flex-col w-full">
                            <div class="flex items-start justify-between">
                                <span class="text-[9px] font-extrabold uppercase tracking-widest text-zinc-400 dark:text-zinc-500 font-mono">
                                    Peringkat 01
                                </span>
                                <span class="text-xs font-bold">👑</span>
                            </div>
                            
                            <div class="flex flex-col items-center text-center mt-4">
                                <div class="w-16 h-16 rounded-full border-2 border-amber-400 dark:border-amber-500 bg-zinc-50 dark:bg-zinc-950 p-0.5 group-hover:scale-[1.03] transition-transform duration-300 overflow-hidden shrink-0">
                                    <img src="{{ $firstPlace->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                                </div>
                                <h3 class="mt-4 font-bold text-zinc-950 dark:text-white text-sm md:text-base truncate w-full group-hover:text-zinc-800 dark:group-hover:text-zinc-200 transition-colors">
                                    {{ $firstPlace->name }}
                                </h3>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center w-full">
                            <div class="text-left">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Total Belanja</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 font-mono">Rp{{ number_format($firstPlace->total_belanja, 0, ',', '.') }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Aktivitas</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $firstPlace->total_transaksi }} Transaksi</span>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-full min-h-[160px] text-center text-zinc-400 dark:text-zinc-600">
                            <span class="text-xl font-light">?</span>
                            <p class="mt-2 text-xs font-medium">Peringkat 1 belum terisi</p>
                        </div>
                    @endif
                </div>

                {{-- 3rd Place Card (Right on Desktop, 3rd on Mobile) --}}
                <div class="flex flex-col justify-between bg-white dark:bg-zinc-900 rounded-2xl p-6 border border-zinc-200 dark:border-zinc-800/80 shadow-sm hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 transition-all duration-300 group order-3 md:order-3">
                    @if($thirdPlace)
                        <div class="flex flex-col w-full">
                            <div class="flex items-start justify-between">
                                <span class="text-[9px] font-extrabold uppercase tracking-widest text-zinc-400 dark:text-zinc-500 font-mono">
                                    Peringkat 03
                                </span>
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-600">🥉</span>
                            </div>
                            
                            <div class="flex flex-col items-center text-center mt-4">
                                <div class="w-16 h-16 rounded-full border-2 border-orange-400 dark:border-orange-600 bg-zinc-50 dark:bg-zinc-950 p-0.5 group-hover:scale-[1.03] transition-transform duration-300 overflow-hidden shrink-0">
                                    <img src="{{ $thirdPlace->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                                </div>
                                <h3 class="mt-4 font-bold text-zinc-950 dark:text-white text-sm md:text-base truncate w-full group-hover:text-zinc-800 dark:group-hover:text-zinc-200 transition-colors">
                                    {{ $thirdPlace->name }}
                                </h3>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center w-full">
                            <div class="text-left">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Total Belanja</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 font-mono">Rp{{ number_format($thirdPlace->total_belanja, 0, ',', '.') }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Aktivitas</span>
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $thirdPlace->total_transaksi }} Transaksi</span>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-full min-h-[160px] text-center text-zinc-400 dark:text-zinc-600">
                            <span class="text-xl font-light">?</span>
                            <p class="mt-2 text-xs font-medium">Peringkat 3 belum terisi</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- Minimalist Stats Grid --}}
        <div class="relative bg-white dark:bg-zinc-900 rounded-2xl p-6 md:p-8 border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden transition-all duration-300">
            {{-- Clean Background Visual Accent --}}
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-zinc-50 dark:bg-zinc-800/30 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid grid-cols-3 gap-4 sm:gap-8">
                <div class="space-y-1">
                    <span class="text-[9px] sm:text-[10px] font-extrabold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest block truncate">Total Pembelian</span>
                    <div class="text-xs sm:text-sm md:text-base font-bold text-zinc-950 dark:text-white font-mono tracking-tight truncate">
                        Rp{{ number_format($totalAllBelanja, 0, ',', '.') }}
                    </div>
                </div>

                <div class="space-y-1 border-l border-zinc-200 dark:border-zinc-800/80 pl-4 sm:pl-8">
                    <span class="text-[9px] sm:text-[10px] font-extrabold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest block truncate">Total Transaksi</span>
                    <div class="text-xs sm:text-sm md:text-base font-bold text-zinc-950 dark:text-white font-mono tracking-tight truncate">
                        {{ number_format($totalAllTransaksi, 0, ',', '.') }} <span class="text-[9px] sm:text-xs font-normal text-zinc-500 font-sans">Kali</span>
                    </div>
                </div>

                <div class="space-y-1 border-l border-zinc-200 dark:border-zinc-800/80 pl-4 sm:pl-8">
                    <span class="text-[9px] sm:text-[10px] font-extrabold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest block truncate">Total Pelanggan</span>
                    <div class="text-xs sm:text-sm md:text-base font-bold text-zinc-950 dark:text-white font-mono tracking-tight truncate">
                        {{ $totalPelanggan }} <span class="text-[9px] sm:text-xs font-normal text-zinc-500 font-sans">Customer</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Customer Ranking List Container --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-6 py-5 border-b border-zinc-100 dark:border-zinc-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-950 dark:text-white">Peringkat Detail Pelanggan</h3>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 font-medium">Daftar lengkap pembeli teraktif beserta riwayat transaksi detail mereka</p>
                </div>
                
                {{-- Alpine.js Search Input --}}
                <div class="relative max-w-xs w-full">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input x-model="search" type="text" placeholder="Cari nama pelanggan..." class="block w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800/80 focus:ring-1 focus:ring-zinc-400 dark:focus:ring-zinc-700 focus:border-zinc-400 dark:focus:border-zinc-700 transition-all duration-200 placeholder-zinc-400 dark:placeholder-zinc-500">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-zinc-50/50 dark:bg-zinc-950/20 border-b border-zinc-100 dark:border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                            <th class="py-4 px-6 text-center w-20">Rank</th>
                            <th class="py-4 px-6">Pelanggan</th>
                            <th class="py-4 px-6 text-center w-36">Aktivitas</th>
                            <th class="py-4 px-6 text-right w-48">Total Belanja</th>
                            <th class="py-4 px-6 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                        @php
                            $allRecords = array_merge($top3_raw, $others);
                        @endphp
                        @forelse($allRecords as $index => $item)
                            @php
                                $rank = $index + 1;
                            @endphp
                            <tr x-show="!search || '{{ strtolower($item->name) }}'.includes(search.toLowerCase())"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors duration-150 group">
                                
                                <td class="py-4 px-6 text-center">
                                    <span class="font-mono text-xs font-bold text-zinc-400 dark:text-zinc-500">
                                        {{ sprintf("%02d", $rank) }}
                                    </span>
                                </td>
                                
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 p-0.5 shadow-sm overflow-hidden shrink-0">
                                            <img src="{{ $item->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate group-hover:text-zinc-950 dark:group-hover:text-white transition-colors">
                                                    {{ $item->name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800/80 text-[11px] font-medium text-zinc-600 dark:text-zinc-400">
                                        {{ $item->total_transaksi }} Transaksi
                                    </span>
                                </td>
                                
                                <td class="py-4 px-6 text-right font-bold text-xs text-zinc-800 dark:text-zinc-200 font-mono">
                                    Rp{{ number_format($item->total_belanja, 0, ',', '.') }}
                                </td>

                                <td class="py-4 px-6 text-center">
                                    <button type="button" wire:click="showCustomer('{{ addslashes($item->name) }}')" class="inline-flex items-center justify-center px-3 py-1 text-[10px] font-bold rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-400 transition-colors cursor-pointer">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            @if(count(array_filter($podium)) === 0)
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-zinc-400 dark:text-zinc-500">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <div class="p-3 bg-zinc-50 dark:bg-zinc-950 rounded-full border border-zinc-200 dark:border-zinc-800">
                                                <svg class="w-8 h-8 text-zinc-300 dark:text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.302M9 11.25H9.01M15 11.25H15.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">Belum ada transaksi penjualan yang tercatat</span>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td colspan="5" class="py-6 px-6 text-center text-zinc-400 dark:text-zinc-500 text-xs font-medium">
                                        Tidak ada peringkat tambahan
                                    </td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
        </div>

        {{-- Detail Transaksi Modal --}}
        @if($selectedCustomer)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm" wire:click.self="closeCustomer">
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden transform transition-all" x-data x-trap.noscroll="true">
                    {{-- Modal Header --}}
                    <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-center bg-zinc-50/50 dark:bg-zinc-950/20">
                        <div>
                            <h3 class="text-sm font-bold text-zinc-950 dark:text-white">Riwayat Transaksi</h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Pelanggan: <span class="text-zinc-950 dark:text-white font-bold">{{ $selectedCustomer }}</span></p>
                        </div>
                        <button type="button" wire:click="closeCustomer" class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 dark:text-zinc-400 cursor-pointer transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="overflow-y-auto p-6 space-y-4 flex-1 bg-zinc-50/50 dark:bg-zinc-950/40">
                        @forelse($customerTransactions as $tx)
                            <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800/80 rounded-2xl p-5 space-y-4 shadow-sm hover:shadow-md transition-shadow duration-200">
                                {{-- Card Header: Date & Nota Info --}}
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-zinc-100 dark:border-zinc-800/60">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-[10px] font-mono font-bold text-zinc-700 dark:text-zinc-300">
                                            {{ $tx->no_nota }}
                                        </span>
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500">•</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                                            {{ $tx->tanggal->format('d M Y, H:i') }} WIB
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 sm:text-right">
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Total Belanja:</span>
                                        <span class="text-sm font-extrabold text-zinc-950 dark:text-white font-mono">
                                            Rp{{ number_format($tx->total, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Card Body: Items Bought --}}
                                <div class="space-y-3">
                                    @foreach($tx->details as $detail)
                                        <div class="flex items-start justify-between gap-4 py-1.5 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 rounded-lg transition-colors">
                                            <div class="flex items-start gap-2.5 min-w-0">
                                                {{-- Qty badge --}}
                                                <span class="inline-flex items-center justify-center shrink-0 px-1.5 py-0.5 rounded bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-[10px] font-mono font-bold text-zinc-600 dark:text-zinc-400 min-w-[55px]">
                                                    {{ number_format($detail->qty, 0, ',', '.') }} {{ $detail->satuan }}
                                                </span>
                                                
                                                <div class="min-w-0">
                                                    <span class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate leading-tight">
                                                        {{ $detail->nama_barang }}
                                                    </span>
                                                    <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-medium leading-none">
                                                        Harga: Rp{{ number_format($detail->harga_jual, 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="text-right shrink-0">
                                                <span class="font-bold text-zinc-900 dark:text-zinc-200 font-mono text-xs">
                                                    Rp{{ number_format($detail->subtotal, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-zinc-400 dark:text-zinc-500">
                                <span class="text-xs font-medium">Belum ada riwayat transaksi penjualan.</span>
                            </div>
                        @endforelse
                    </div>

                    {{-- Modal Footer --}}
                    <div class="px-6 py-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-end bg-zinc-50/50 dark:bg-zinc-950/20">
                        <button type="button" wire:click="closeCustomer" class="px-4 py-1.5 text-xs font-bold rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
