<x-filament-panels::page>
    <div class="min-h-screen bg-stone-50 dark:bg-gray-900 text-stone-800 dark:text-gray-100 font-sans p-4 md:p-8 relative overflow-hidden rounded-3xl -m-4 transition-colors duration-300">
        
        {{-- Custom Styles & Animations --}}
        <style>
            @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes bounceIn { 0% { opacity: 0; transform: translateY(50px) scale(0.9); } 60% { opacity: 1; transform: translateY(-10px) scale(1.02); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
            @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0px); } }
            @keyframes fall { 0% { transform: translateY(-10vh) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(110vh) rotate(360deg); opacity: 0; } }
            header.fi-header { display: none !important; }
        </style>

        <!-- Tailwind CDN Khusus untuk Page Ini (Dukungan Dark Mode 'class') -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class', // Filament injects 'dark' class to html element
                corePlugins: { preflight: false }
            }
        </script>

        <!-- Background Ambient Wood Glow -->
        <div class="absolute top-0 left-1/2 w-full h-[600px] bg-amber-400/10 dark:bg-amber-500/5 rounded-full blur-[100px] -translate-x-1/2 pointer-events-none z-0"></div>
        
        <!-- Efek Hujan Serbuk Kayu -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            @for ($i = 0; $i < 40; $i++)
                @php
                    $left = rand(0, 100) . '%';
                    $width = rand(2, 8) . 'px';
                    $height = rand(2, 6) . 'px';
                    $animDuration = rand(40, 90) / 10 . 's';
                    $animDelay = rand(0, 50) / 10 . 's';
                    $isDark = rand(0, 1);
                    $rotation = rand(0, 360) . 'deg';
                @endphp
                <div class="absolute rounded-sm {{ $isDark ? 'bg-amber-800/20 dark:bg-amber-700/20' : 'bg-amber-600/20 dark:bg-amber-500/20' }}"
                     style="left: {{ $left }}; top: -5%; width: {{ $width }}; height: {{ $height }}; animation: fall {{ $animDuration }} linear infinite {{ $animDelay }}; transform: rotate({{ $rotation }})">
                </div>
            @endfor
        </div>

        <div class="max-w-7xl mx-auto space-y-8 relative z-10">
            
            <!-- Header & Filter Control (Layout Disimetriskan ke Tengah) -->
            <div class="flex flex-col items-center bg-white/90 dark:bg-gray-800/90 backdrop-blur-md p-6 md:p-8 rounded-3xl border border-stone-200 dark:border-gray-700 shadow-xl transition-colors duration-300">
                <div class="text-center mb-8">
                    <h1 class="text-3xl md:text-4xl font-black bg-gradient-to-r from-amber-600 via-yellow-600 to-orange-600 dark:from-amber-400 dark:via-yellow-400 dark:to-orange-400 bg-clip-text text-transparent flex items-center justify-center gap-3 tracking-tight drop-shadow-sm">
                        <!-- Ikon Crown Asli SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500">
                            <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
                        </svg>
                        LEADERBOARD BARANG
                    </h1>
                    <p class="text-stone-500 dark:text-gray-400 mt-2 font-medium">Peringkat produk paling laris.</p>
                </div>
                
                <div class="flex flex-col md:flex-row items-center justify-center gap-4 w-full">
                    <!-- Sorting -->
                    <div class="flex bg-stone-100 dark:bg-gray-900/50 p-1.5 rounded-2xl border border-stone-200 dark:border-gray-700 w-full md:w-auto overflow-x-auto shadow-inner transition-colors duration-300">
                        <button wire:click="setSortBy('value')" class="flex-1 md:flex-none whitespace-nowrap px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 {{ $sortBy === 'value' ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-md border border-stone-200 dark:border-gray-600' : 'text-stone-500 dark:text-gray-400 hover:text-stone-800 dark:hover:text-gray-100 hover:bg-white/50 dark:hover:bg-gray-800/50' }}">Nilai Tertinggi</button>
                        <button wire:click="setSortBy('qty')" class="flex-1 md:flex-none whitespace-nowrap px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 {{ $sortBy === 'qty' ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-md border border-stone-200 dark:border-gray-600' : 'text-stone-500 dark:text-gray-400 hover:text-stone-800 dark:hover:text-gray-100 hover:bg-white/50 dark:hover:bg-gray-800/50' }}">Terbanyak (Qty)</button>
                        <button wire:click="setSortBy('nota')" class="flex-1 md:flex-none whitespace-nowrap px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 {{ $sortBy === 'nota' ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-md border border-stone-200 dark:border-gray-600' : 'text-stone-500 dark:text-gray-400 hover:text-stone-800 dark:hover:text-gray-100 hover:bg-white/50 dark:hover:bg-gray-800/50' }}">Sering Dibeli</button>
                    </div>

                    <!-- Date Filter -->
                    <div class="flex bg-white dark:bg-gray-800 border border-stone-300 dark:border-gray-600 rounded-2xl overflow-hidden shadow-sm focus-within:ring-2 focus-within:ring-amber-500 transition-all duration-300">
                        <div class="flex items-center px-4 py-2.5 border-r border-stone-200 dark:border-gray-600 bg-stone-50/50 dark:bg-gray-900/50 hover:bg-stone-100 dark:hover:bg-gray-700 transition-colors">
                            <x-heroicon-o-calendar class="text-stone-400 dark:text-gray-500 mr-2 w-4 h-4" />
                            <span class="text-xs font-bold text-stone-500 dark:text-gray-400 mr-2 hidden md:inline">DARI</span>
                            <input type="date" wire:model.live="startDate" class="bg-transparent text-sm font-semibold text-stone-700 dark:text-gray-200 focus:outline-none cursor-pointer w-full border-none p-0 focus:ring-0 dark:[color-scheme:dark]" />
                        </div>
                        <div class="flex items-center px-4 py-2.5 bg-stone-50/50 dark:bg-gray-900/50 hover:bg-stone-100 dark:hover:bg-gray-700 transition-colors">
                            <span class="text-xs font-bold text-stone-500 dark:text-gray-400 mr-2 hidden md:inline">S/D</span>
                            <span class="text-xs font-bold text-stone-500 dark:text-gray-400 mr-2 md:hidden">-</span>
                            <input type="date" wire:model.live="endDate" class="bg-transparent text-sm font-semibold text-stone-700 dark:text-gray-200 focus:outline-none cursor-pointer w-full border-none p-0 focus:ring-0 dark:[color-scheme:dark]" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div wire:loading class="w-full text-center py-12">
                <div class="inline-flex flex-col items-center justify-center space-y-4">
                    <svg class="animate-spin text-amber-500 w-12 h-12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <p class="text-stone-500 dark:text-gray-400 font-bold animate-pulse">Menghitung ulang data...</p>
                </div>
            </div>

            <div wire:loading.remove>
                @php
                    $leaderboard = $this->leaderboardData;
                    $top3 = collect([
                        $leaderboard->firstWhere('rank', 2),
                        $leaderboard->firstWhere('rank', 1),
                        $leaderboard->firstWhere('rank', 3)
                    ])->filter();
                    $others = $leaderboard->where('rank', '>', 3);
                @endphp

                <!-- Podium Top 3 -->
                @if($top3->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-end mt-28 mb-12 h-auto md:h-[400px]">
                        @foreach($top3 as $item)
                            @php
                                $isRank1 = $item->rank === 1;
                                $isRank2 = $item->rank === 2;
                                
                                // Tema warna otomatis menyesuaikan dark/light mode
                                $colorTheme = $isRank1 ? 'from-amber-50 via-yellow-50 to-white border-x-yellow-300 border-b-yellow-300 shadow-[0_15px_40px_-15px_rgba(245,158,11,0.3)] dark:from-gray-800 dark:via-gray-800 dark:to-gray-900 dark:border-x-amber-500/50 dark:border-b-amber-500/50 dark:shadow-[0_15px_40px_-15px_rgba(245,158,11,0.15)]' 
                                  : ($isRank2 ? 'from-stone-100 via-stone-50 to-white border-x-stone-300 border-b-stone-300 shadow-[0_15px_40px_-15px_rgba(168,162,158,0.3)] dark:from-gray-800 dark:via-gray-800 dark:to-gray-900 dark:border-x-gray-600 dark:border-b-gray-600 dark:shadow-[0_15px_40px_-15px_rgba(0,0,0,0.4)]' 
                                  : 'from-orange-50 via-red-50 to-white border-x-orange-200 border-b-orange-200 shadow-[0_15px_40px_-15px_rgba(249,115,22,0.2)] dark:from-gray-800 dark:via-gray-800 dark:to-gray-900 dark:border-x-orange-700/50 dark:border-b-orange-700/50 dark:shadow-[0_15px_40px_-15px_rgba(249,115,22,0.1)]');
                                
                                $heightClass = $isRank1 ? 'h-[360px] md:scale-110 z-10' : ($isRank2 ? 'h-[300px]' : 'h-[260px]');
                                $delay = $isRank1 ? 'animate-[bounceIn_0.6s_ease-out]' : ($isRank2 ? 'animate-[bounceIn_0.6s_ease-out_0.1s]' : 'animate-[bounceIn_0.6s_ease-out_0.2s]');
                                $orderClass = $isRank1 ? 'order-1 md:order-2' : ($isRank2 ? 'order-2 md:order-1' : 'order-3 md:order-3');
                            @endphp

                            <div wire:click="openModal({{ $item->id }}, '{{ $item->name }}')" style="animation-fill-mode: both" class="relative bg-gradient-to-b {{ $colorTheme }} border-x border-b backdrop-blur-xl rounded-b-3xl p-6 flex flex-col items-center justify-start cursor-pointer hover:shadow-[0_20px_50px_-15px_rgba(0,0,0,0.2)] dark:hover:shadow-[0_20px_50px_-15px_rgba(0,0,0,0.6)] hover:-translate-y-2 transition-all duration-300 {{ $heightClass }} {{ $orderClass }} {{ $delay }}">
                                
                                <div class="absolute top-0 left-0 w-full h-4 rounded-t-xl bg-[repeating-linear-gradient(90deg,rgba(0,0,0,0.06)_0px,rgba(0,0,0,0.06)_2px,transparent_2px,transparent_4px)] dark:bg-[repeating-linear-gradient(90deg,rgba(255,255,255,0.03)_0px,rgba(255,255,255,0.03)_2px,transparent_2px,transparent_4px)] {{ $isRank1 ? 'bg-amber-300 dark:bg-amber-600' : ($isRank2 ? 'bg-stone-300 dark:bg-gray-600' : 'bg-orange-300 dark:bg-orange-700') }}"></div>

                                <!-- Wrapper Simetris Kapsul Angka & Mahkota -->
                                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-30 flex flex-col items-center justify-center">
                                    @if($isRank1)
                                        <div class="absolute bottom-full mb-2 flex justify-center w-[120%] animate-[float_3s_ease-in-out_infinite]">
                                            <x-heroicon-o-sparkles class="absolute -top-1 -left-2 text-amber-400 animate-pulse w-5 h-5" />
                                            <x-heroicon-o-sparkles class="absolute -bottom-1 -right-2 text-orange-400 animate-pulse w-4 h-4" />
                                            <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500 drop-shadow-lg">
                                                <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="absolute bottom-full mb-2 flex justify-center">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-gray-800 border-[3px] {{ $isRank2 ? 'border-stone-300 text-stone-400 dark:border-gray-500 dark:text-gray-400' : 'border-orange-300 text-orange-400 dark:border-orange-600 dark:text-orange-500' }} shadow-md">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="lucide lucide-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <span class="font-black text-xl bg-white dark:bg-gray-800 px-6 py-1.5 rounded-full shadow-md z-10 {{ $isRank1 ? 'text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-600/50' : ($isRank2 ? 'text-stone-500 dark:text-gray-300 border border-stone-200 dark:border-gray-600' : 'text-orange-500 dark:text-orange-400 border border-orange-200 dark:border-orange-700/50') }}">
                                        #{{ $item->rank }}
                                    </span>
                                </div>

                                <div class="text-center mt-12 w-full flex-1 flex flex-col z-20">
                                    <h3 class="font-black text-xl mb-4 leading-tight uppercase tracking-wide {{ $isRank1 ? 'text-amber-700 dark:text-amber-400' : 'text-stone-800 dark:text-gray-100' }}" title="{{ $item->name }}">{{ $item->name }}</h3>
                                    <div class="space-y-2 text-sm mt-auto w-full">
                                        <div class="flex justify-between items-center px-3 py-2 rounded-xl transition-colors {{ $sortBy === 'qty' ? 'bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50' : 'bg-stone-100/80 dark:bg-gray-800/80 border border-stone-100 dark:border-gray-700' }}">
                                            <span class="text-stone-500 dark:text-gray-400 flex items-center gap-1.5"><x-heroicon-o-square-3-stack-3d class="w-4 h-4"/> Qty</span>
                                            <span class="font-bold {{ $sortBy === 'qty' ? 'text-amber-700 dark:text-amber-400 scale-110 transform origin-right transition-transform' : 'text-stone-700 dark:text-gray-200' }}">{{ number_format($item->qty, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between items-center px-3 py-2 rounded-xl transition-colors {{ $sortBy === 'value' ? 'bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50' : 'bg-stone-100/80 dark:bg-gray-800/80 border border-stone-100 dark:border-gray-700' }}">
                                            <span class="text-stone-500 dark:text-gray-400 flex items-center gap-1.5"><x-heroicon-o-arrow-trending-up class="w-4 h-4"/> Nilai</span>
                                            <span class="font-bold {{ $sortBy === 'value' ? 'text-amber-700 dark:text-amber-400 scale-110 transform origin-right transition-transform' : 'text-stone-700 dark:text-gray-200' }}">Rp {{ number_format($item->value, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between items-center px-3 py-2 rounded-xl transition-colors {{ $sortBy === 'nota' ? 'bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50' : 'bg-stone-100/80 dark:bg-gray-800/80 border border-stone-100 dark:border-gray-700' }}">
                                            <span class="text-stone-500 dark:text-gray-400 flex items-center gap-1.5"><x-heroicon-o-document-text class="w-4 h-4"/> Nota</span>
                                            <span class="font-bold {{ $sortBy === 'nota' ? 'text-amber-700 dark:text-amber-400 scale-110 transform origin-right transition-transform' : 'text-stone-700 dark:text-gray-200' }}">{{ $item->notaCount }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- List View untuk Rank 4+ -->
                @if($others->isNotEmpty())
                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-3xl border border-stone-200 dark:border-gray-700 overflow-hidden shadow-xl animate-[fadeIn_0.5s_ease-out] transition-colors duration-300">
                        <div class="p-5 border-b border-stone-200 dark:border-gray-700 flex justify-between items-center bg-stone-50/80 dark:bg-gray-900/50">
                            <h2 class="font-bold text-xl text-stone-800 dark:text-gray-100 flex items-center gap-2"><x-heroicon-o-square-3-stack-3d class="text-amber-500 w-5 h-5" /> Peringkat Lainnya</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-stone-600 dark:text-gray-300 border-collapse min-w-[800px]">
                                <thead class="bg-stone-100/80 dark:bg-gray-900/80 text-stone-500 dark:text-gray-400 uppercase font-bold text-xs tracking-wider">
                                    <tr>
                                        <th class="px-6 py-5 w-24 text-center">Rank</th>
                                        <th class="px-6 py-5">Nama Barang</th>
                                        <th class="px-6 py-5 text-right transition-colors {{ $sortBy === 'qty' ? 'text-amber-600 dark:text-amber-400' : '' }}">Total Qty</th>
                                        <th class="px-6 py-5 text-right transition-colors {{ $sortBy === 'value' ? 'text-amber-600 dark:text-amber-400' : '' }}">Total Nilai</th>
                                        <th class="px-6 py-5 text-center transition-colors {{ $sortBy === 'nota' ? 'text-amber-600 dark:text-amber-400' : '' }}">Jml Nota</th>
                                        <th class="px-6 py-5 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100 dark:divide-gray-700/50">
                                    @foreach($others as $index => $item)
                                        <tr wire:click="openModal({{ $item->id }}, '{{ $item->name }}')" style="animation: fadeInUp 0.3s ease-out {{ $loop->index * 0.05 }}s both" class="hover:bg-stone-50 dark:hover:bg-gray-700 transition-all duration-300 cursor-pointer group">
                                            <td class="px-6 py-4">
                                                <div class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 border border-stone-200 dark:border-gray-600 shadow-sm flex items-center justify-center font-black text-stone-400 dark:text-gray-500 group-hover:bg-amber-50 dark:group-hover:bg-amber-900/30 group-hover:text-amber-600 dark:group-hover:text-amber-400 group-hover:border-amber-200 dark:group-hover:border-amber-700/50 transition-colors mx-auto">
                                                    #{{ $item->rank }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <x-heroicon-s-square-3-stack-3d class="w-5 h-5 text-stone-300 dark:text-gray-600 group-hover:text-amber-500 dark:group-hover:text-amber-400 transition-colors" />
                                                    <span class="font-bold text-stone-700 dark:text-gray-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors text-base">{{ $item->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-right font-medium transition-colors {{ $sortBy === 'qty' ? 'text-amber-600 dark:text-amber-400 text-base font-bold' : 'text-stone-500 dark:text-gray-400' }}">
                                                {{ number_format($item->qty, 0, ',', '.') }} <span class="text-xs opacity-60">Pcs</span>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold transition-colors {{ $sortBy === 'value' ? 'text-amber-600 dark:text-amber-400 text-base' : 'text-stone-600 dark:text-gray-300' }}">
                                                Rp {{ number_format($item->value, 0, ',', '.') }}
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $sortBy === 'nota' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-700/50' : 'bg-stone-100 dark:bg-gray-700 text-stone-500 dark:text-gray-400' }}">
                                                    {{ $item->notaCount }} Nota
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-stone-400 dark:text-gray-500 group-hover:text-amber-500 dark:group-hover:text-amber-400">
                                                <x-heroicon-o-chevron-right class="w-5 h-5 mx-auto transform group-hover:translate-x-1 transition-transform" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Modal Pop-up -->
        @if($selectedBarangId)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-stone-900/60 backdrop-blur-sm transition-opacity animate-[fadeIn_0.2s_ease-out]" wire:click="closeModal"></div>
                <div class="relative bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-700/50 rounded-3xl w-full max-w-3xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.5)] flex flex-col max-h-[90vh] animate-[fadeInUp_0.3s_ease-out]">
                    
                    <div class="p-6 border-b border-stone-100 dark:border-gray-700 flex justify-between items-start bg-stone-50/80 dark:bg-gray-900/80 rounded-t-3xl">
                        <div class="flex gap-4 items-center">
                            <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-900/30 border-2 border-amber-200 dark:border-amber-700/50 flex items-center justify-center shrink-0 shadow-sm">
                                <span class="font-black text-2xl text-amber-500"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg></span>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-stone-800 dark:text-gray-100 mb-2 uppercase">{{ $selectedBarangName }}</h3>
                            </div>
                        </div>
                        <button wire:click="closeModal" class="p-2 hover:bg-stone-200 dark:hover:bg-gray-700 rounded-full text-stone-400 dark:text-gray-500 hover:text-stone-700 dark:hover:text-gray-200 transition-colors shrink-0">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto">
                        <div class="mb-6 relative">
                            <x-heroicon-o-magnifying-glass class="w-5 h-5 absolute left-4 top-1/2 transform -translate-y-1/2 text-stone-400 dark:text-gray-500" />
                            <input type="text" wire:model.live.debounce.300ms="searchNota" placeholder="Cari nomor nota atau nama customer..." class="w-full bg-stone-50 dark:bg-gray-900 border border-stone-200 dark:border-gray-700 text-stone-800 dark:text-gray-200 rounded-xl pl-12 pr-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-amber-400 dark:focus:ring-amber-500 transition-all shadow-inner border-none" />
                        </div>
                        
                        <div class="space-y-3 relative min-h-[200px]">
                            <!-- Loading Indicator Pencarian Modal -->
                            <div wire:loading wire:target="searchNota" class="absolute inset-0 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm z-10 flex items-center justify-center">
                                <svg class="animate-spin text-amber-500 w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </div>

                            @php $notas = $this->notasData; @endphp

                            @if(count($notas) === 0)
                                <div class="py-8 text-center text-stone-500 dark:text-gray-400 font-medium">Tidak ada nota penjualan ditemukan.</div>
                            @else
                                @foreach($notas as $index => $nota)
                                    <a href="#" style="animation: fadeInUp 0.3s ease-out {{ $index * 0.05 }}s both" class="bg-white dark:bg-gray-800/50 border border-stone-200 dark:border-gray-700 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between hover:bg-amber-50 dark:hover:bg-gray-700/80 hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-md cursor-pointer transition-all group block">
                                        <div class="flex items-start gap-4 mb-3 md:mb-0">
                                            <div class="bg-stone-100 dark:bg-gray-900 p-3 rounded-xl text-stone-400 dark:text-gray-500 group-hover:bg-amber-500 group-hover:text-white transition-colors border border-stone-200 dark:border-gray-700 group-hover:border-amber-500 shadow-sm">
                                                <x-heroicon-o-document-text class="w-5 h-5" />
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-stone-800 dark:text-gray-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">{{ $nota->penjualan->no_nota }}</h4>
                                                <div class="text-sm text-stone-500 dark:text-gray-400 mt-1 flex gap-3">
                                                    <span>{{ $nota->penjualan->tanggal->format('d-m-Y') }}</span>
                                                    <span class="text-stone-300 dark:text-gray-600">•</span>
                                                    <span class="text-stone-600 dark:text-gray-300 font-medium">{{ $nota->penjualan->nama_customer ?? 'Umum' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex md:flex-col items-center md:items-end justify-between md:justify-center gap-2 border-t md:border-t-0 border-stone-100 dark:border-gray-700 pt-3 md:pt-0">
                                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50 px-3 py-1 rounded-lg">{{ number_format($nota->qty, 0, ',', '.') }} Pcs</span>
                                            <span class="font-black text-amber-600 dark:text-amber-400">Rp {{ number_format($nota->subtotal, 0, ',', '.') }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

