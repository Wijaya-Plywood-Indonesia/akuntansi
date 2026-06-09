<x-filament::page>

    {{-- Main Container dengan Alpine.js --}}
    <div x-data="{
        search: '',
        filterStatus: 'all',
        items: @js($barangs->map(fn($barang) => [
            'id' => $barang->id,
            'nama' => $barang->nama_barang,
            'satuan' => $barang->satuan?->nama_satuan ?? 'pcs',
            'akun' => $barang->subAnakAkun?->kode_sub_anak_akun ?? '',
            'qty' => $stok[$barang->id]->stok ?? 0.0,
            'm3' => $stok[$barang->id]->m3 ?? 0.0
        ])),
        get filteredItems() {
            return this.items.filter(item => {
                const matchesSearch = item.nama.toLowerCase().includes(this.search.toLowerCase()) || 
                                       item.akun.toLowerCase().includes(this.search.toLowerCase());
                
                const matchesStatus = this.filterStatus === 'all' || 
                                       (this.filterStatus === 'available' && (item.qty > 0 || item.m3 > 0)) || 
                                       (this.filterStatus === 'empty' && item.qty <= 0 && item.m3 <= 0);
                                       
                return matchesSearch && matchesStatus;
            });
        },
        formatQty(val) {
            return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 });
        },
        formatM3(val) {
            return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 });
        }
    }" class="space-y-4">

        {{-- Minimal Search & Filter Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3 shadow-sm transition-colors duration-200">

            {{-- Live Search Input --}}
            <div class="relative flex-grow max-w-md w-full">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 dark:text-gray-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    x-model="search"
                    placeholder="Cari nama barang atau no akun..."
                    class="w-full pl-9 pr-8 py-2 bg-gray-50/60 dark:bg-gray-950/40 focus:bg-white dark:focus:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 dark:focus:ring-indigo-400/10 rounded-xl text-xs transition-all outline-none text-gray-700 dark:text-gray-300" />
                <button
                    x-show="search.length > 0"
                    @click="search = ''"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Dynamic Tabs untuk Status Filtering --}}
            <div class="flex bg-gray-100/80 dark:bg-gray-950/60 p-1 rounded-xl border border-gray-200/50 dark:border-gray-800/80 self-start sm:self-auto transition-colors duration-200">
                <button
                    @click="filterStatus = 'all'"
                    :class="filterStatus === 'all' ? 'bg-white dark:bg-gray-800 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1.5">
                    Semua
                    <span class="bg-gray-250 dark:bg-gray-700 px-1.5 py-0.5 rounded text-[9px] font-mono text-gray-600 dark:text-gray-300" x-text="items.length"></span>
                </button>
                <button
                    @click="filterStatus = 'available'"
                    :class="filterStatus === 'available' ? 'bg-white dark:bg-gray-800 shadow-sm text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1.5">
                    Tersedia
                    <span class="bg-emerald-50 dark:bg-emerald-950/30 px-1.5 py-0.5 rounded text-[9px] font-mono text-emerald-600 dark:text-emerald-400" x-text="items.filter(i => i.qty > 0 || i.m3 > 0).length"></span>
                </button>
                <button
                    @click="filterStatus = 'empty'"
                    :class="filterStatus === 'empty' ? 'bg-white dark:bg-gray-800 shadow-sm text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1.5">
                    Habis
                    <span class="bg-rose-50 dark:bg-rose-950/30 px-1.5 py-0.5 rounded text-[9px] font-mono text-rose-600 dark:text-rose-400" x-text="items.filter(i => i.qty <= 0 && i.m3 <= 0).length"></span>
                </button>
            </div>

        </div>

        {{-- Tabel Utama (Fokus pada Nama Barang, Stok, dan Kubikasi) --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm transition-colors duration-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider transition-colors duration-200">
                            <th class="p-4 sm:p-5">Nama Barang</th>
                            <th class="p-4 sm:p-5 text-right w-[180px] sm:w-[220px]">Stok Barang</th>
                            <th class="p-4 sm:p-5 text-right w-[180px] sm:w-[220px]">Total Kubikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="item in filteredItems" :key="item.id">
                            <tr
                                class="hover:bg-gray-50/50 dark:hover:bg-gray-950/30 transition-colors duration-150"
                                :class="item.qty <= 0 ? 'bg-rose-50/10 dark:bg-rose-950/5' : ''">
                                {{-- Nama Barang & Kode Akun --}}
                                <td class="p-4 sm:p-5">
                                    <div class="font-semibold text-gray-950 dark:text-white text-sm sm:text-base transition-colors duration-200" x-text="item.nama"></div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-mono tracking-wide mt-0.5 block" x-text="item.akun"></div>
                                </td>

                                {{-- Stok Barang (Read-Only) --}}
                                <td class="p-4 sm:p-5 text-right font-mono font-bold text-sm sm:text-base whitespace-nowrap transition-colors duration-200">
                                    <span :class="item.qty <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'" x-text="formatQty(item.qty)"></span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-semibold ml-1.5 uppercase" x-text="item.satuan"></span>
                                </td>

                                {{-- Total Kubikasi --}}
                                <td class="p-4 sm:p-5 text-right font-mono font-bold text-sm sm:text-base whitespace-nowrap text-gray-900 dark:text-white transition-colors duration-200">
                                    <span :class="item.m3 <= 0 ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white'" x-text="formatM3(item.m3)"></span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-semibold ml-1">m³</span>
                                </td>
                            </tr>
                        </template>

                        {{-- State Kosong / Pencarian Tidak Ditemukan --}}
                        <tr x-show="filteredItems.length === 0" class="transition-colors duration-200">
                            <td colspan="3" class="p-12 text-center bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 transition-colors duration-200">
                                <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.008 1.24l.885 1.77a2.25 2.25 0 002.007 1.24h1.98a2.25 2.25 0 002.007-1.24l.885-1.77a2.25 2.25 0 012.007-1.24h3.86m-18 0h18" />
                                </svg>
                                <p class="text-xs font-semibold">Tidak ada barang yang cocok dengan kriteria pencarian.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament::page>