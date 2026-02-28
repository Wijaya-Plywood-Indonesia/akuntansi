<x-filament-panels::page>
    {{-- Flatpickr Styles & Custom Amber Theme --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_orange.css">

    <style>
        [x-cloak] {
            display: none !important;
        }

        .flatpickr-calendar {
            border-radius: 4px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e5e7eb !important;
            z-index: 9999 !important;
        }

        .dark .flatpickr-calendar {
            background: #111827 !important;
            border-color: #374151 !important;
            color: #fff !important;
        }

        .no-transition * {
            transition: none !important;
            animation: none !important;
        }

        .custom-scroll::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .dark .custom-scroll::-webkit-scrollbar-thumb {
            background: #374151;
        }
    </style>

    <div class="w-full mx-auto no-transition"
        x-cloak
        x-data="{ 
            tgl: @entangle('tgl'),
            jurnal: @entangle('jurnal'),
            no_akun: @entangle('no_akun'),
            nama_akun: @entangle('nama_akun'),
            keterangan: @entangle('keterangan'),
            banyak: @entangle('banyak'),
            harga_display: '',
            harga_raw: @entangle('harga'),
            map: @entangle('map'),
            searchTerm: '',
            isDropdownOpen: false,
            accounts: @js($accounts ?? []), 
            items: @entangle('items'),

            get filteredAccounts() {
                if (this.searchTerm === '') return this.accounts;
                return this.accounts.filter(acc => 
                    acc.no.toLowerCase().includes(this.searchTerm.toLowerCase()) || 
                    acc.nama.toLowerCase().includes(this.searchTerm.toLowerCase())
                );
            },
            selectAccount(acc) {
                this.no_akun = acc.no;
                this.nama_akun = acc.nama;
                this.searchTerm = acc.no;
                this.isDropdownOpen = false;
            },
            {{-- Fungsi Format Ribuan --}}
            formatRupiah(val) {
                if (val === null || val === undefined || val === '') return '';
                let numberString = val.toString().replace(/[^0-9]/g, '');
                return numberString.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            },
            initFlatpickr() {
                flatpickr(this.$refs.dateInput, {
                    dateFormat: 'Y-m-d',
                    defaultDate: this.tgl,
                    onChange: (selectedDates, dateStr) => {
                        this.tgl = dateStr;
                    }
                });
            },
            get totalDebit() {
                return this.items.reduce((acc, curr) => (curr.map === 'D' || curr.map === 'Debit' || curr.map === 'd') ? acc + parseFloat(curr.total) : acc, 0);
            },
            get totalKredit() {
                return this.items.reduce((acc, curr) => (curr.map === 'K' || curr.map === 'Kredit' || curr.map === 'k') ? acc + parseFloat(curr.total) : acc, 0);
            },
            get isBalanced() {
                return Math.abs(this.totalDebit - this.totalKredit) < 0.01 && this.items.length > 0;
            }
         }"
        x-init="
            initFlatpickr();
            {{-- Watcher untuk update display saat harga_raw berubah (auto-balance) --}}
            $watch('harga_raw', value => {
                harga_display = formatRupiah(value);
            });
            {{-- Sinkronisasi awal --}}
            harga_display = formatRupiah(harga_raw);
        ">

        {{-- FORM INPUT UTAMA --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[4px] shadow-sm overflow-hidden mb-6">
            <div class="bg-amber-600 dark:bg-amber-700 px-6 py-4 text-white">
                <h2 class="text-sm font-bold tracking-tight flex items-center gap-2 uppercase tracking-widest leading-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Masukkan detail transaksi untuk mutasi buku besar.
                </h2>
            </div>

            <form wire:submit.prevent="addItem" class="p-6 space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Tanggal --}}
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tanggal Transaksi</label>
                        <input type="text" x-ref="dateInput" readonly class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-[4px] outline-none font-medium text-gray-800 dark:text-gray-200 cursor-pointer">
                    </div>

                    {{-- No Jurnal --}}
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">No. Jurnal</label>
                        <input type="text" x-model="jurnal" placeholder="JR-0001" class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-[4px] outline-none font-medium text-gray-800 dark:text-gray-200">
                    </div>

                    {{-- Searchable No Akun --}}
                    <div class="space-y-1.5 relative" @click.away="isDropdownOpen = false">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cari Nomor Akun</label>
                        <input type="text" x-model="searchTerm" @focus="isDropdownOpen = true" placeholder="Ketik no/nama..." class="w-full px-3 py-2.5 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-900 rounded-[4px] font-bold text-amber-700 dark:text-amber-500 outline-none">
                        <div x-show="isDropdownOpen" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-[4px] shadow-lg max-h-60 overflow-y-auto p-1 custom-scroll">
                            <template x-for="acc in filteredAccounts" :key="acc.no">
                                <button type="button" @click="selectAccount(acc)" class="w-full text-left px-3 py-2 hover:bg-amber-50 dark:hover:bg-amber-900/40 rounded-[2px] flex flex-col group transition-none">
                                    <span class="font-bold text-gray-800 dark:text-gray-200 text-sm group-hover:text-amber-700" x-text="acc.no"></span>
                                    <span class="text-[10px] text-gray-500 dark:text-gray-300 font-medium" x-text="acc.nama"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Nama Akun --}}
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nama Akun</label>
                        <div class="px-3 py-2.5 bg-gray-100 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-[4px] text-gray-600 dark:text-gray-300 font-bold text-sm min-h-[42px] flex items-center" x-text="nama_akun || 'Pilih akun...'"></div>
                    </div>

                    {{-- Keterangan --}}
                    <div class="md:col-span-2 space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Keterangan Transaksi</label>
                        <input type="text" x-model="keterangan" placeholder="Masukkan detail..." class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-[4px] outline-none font-medium text-gray-800 dark:text-gray-200 focus:border-amber-500">
                    </div>

                    {{-- Kuantitas (Muted Text) --}}
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Kuantitas</label>
                        <input type="number" step="any" x-model="banyak" placeholder="0" class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-[4px] font-bold text-gray-500 dark:text-gray-300">
                    </div>

                    {{-- Harga (Separator Logic & Muted Text) --}}
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Harga</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rp</span>
                            <input type="text"
                                x-model="harga_display"
                                @input="harga_display = formatRupiah($event.target.value); harga_raw = $event.target.value.replace(/[^0-9]/g, '')"
                                placeholder="0"
                                class="w-full pl-9 pr-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-[4px] font-bold text-gray-500 dark:text-gray-300">
                        </div>
                    </div>

                    {{-- Tipe Mutasi --}}
                    <div class="space-y-3 pt-1">
                        <label class="text-[11px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider block text-center">Tipe Mutasi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="map = 'D'" :class="map === 'D' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400'" class="py-2.5 rounded-[4px] border font-black text-xs tracking-widest transition-none">DEBIT</button>
                            <button type="button" @click="map = 'K'" :class="map === 'K' ? 'bg-rose-600 border-rose-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400'" class="py-2.5 rounded-[4px] border font-black text-xs tracking-widest transition-none">KREDIT</button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 pt-6">
                    <button type="button" wire:click="resetForm" class="px-6 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-[4px] font-bold text-[10px] uppercase tracking-widest hover:bg-gray-50 transition-none">Batal</button>
                    <button type="submit" class="px-10 py-2.5 bg-amber-600 dark:bg-amber-700 text-white rounded-[4px] font-bold text-[10px] uppercase tracking-widest hover:bg-amber-700 transition-none flex items-center gap-2 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Masukkan Draft
                    </button>
                </div>
            </form>
        </div>

        {{-- TABLE DRAFT JURNAL SEMENTARA --}}
        <div x-show="items.length > 0" x-cloak class="space-y-4 mb-10">
            <div class="flex items-center justify-between px-1">
                <div :class="isBalanced ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20' : 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-900/20'" class="px-4 py-2.5 rounded-[4px] border flex items-center gap-3 font-black text-xs uppercase tracking-[0.2em] shadow-sm">
                    <div :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500 animate-pulse'" class="w-2 h-2 rounded-full"></div>
                    <span x-text="isBalanced ? 'Jurnal Balanced' : 'Jurnal Unbalanced'"></span>
                </div>
                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest italic">Temporary Draft Queue</div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[4px] shadow-sm overflow-hidden custom-scroll">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse table-fixed min-w-[1300px]">
                        <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 sticky top-0">
                            <tr class="text-[10px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-widest">
                                <th class="px-4 py-4 w-[110px]">No Akun</th>
                                <th class="px-4 py-4 w-[180px]">Nama Akun</th>
                                <th class="px-4 py-4 w-[240px]">Keterangan</th>
                                <th class="px-4 py-4 text-center w-[110px]">No Jurnal</th>
                                <th class="px-4 py-4 text-right w-[110px]">Qty</th>
                                <th class="px-4 py-4 text-right w-[140px]">Harga</th>
                                <th class="px-4 py-4 text-right w-[150px] text-emerald-600 bg-emerald-50/10 italic font-black">Debit (Rp)</th>
                                <th class="px-4 py-4 text-right w-[150px] text-rose-600 bg-rose-50/10 italic font-black">Kredit (Rp)</th>
                                <th class="px-4 py-4 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="(row, i) in items" :key="i">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 align-top transition-none">
                                    <td class="px-4 py-4 font-mono font-bold text-amber-600" x-text="row.no_akun"></td>
                                    <td class="px-4 py-4 font-bold text-gray-800 dark:text-gray-100" x-text="row.nama_akun"></td>
                                    <td class="px-4 py-4 text-[11px] leading-relaxed text-gray-500 italic whitespace-normal break-words" x-text="row.keterangan || '-'"></td>
                                    <td class="px-4 py-4 text-center text-gray-400 font-medium" x-text="row.jurnal"></td>
                                    <td class="px-4 py-4 text-right font-medium text-gray-400 dark:text-gray-500" x-text="new Intl.NumberFormat('id-ID').format(row.banyak)"></td>
                                    <td class="px-4 py-4 text-right text-gray-400 dark:text-gray-500 font-mono" x-text="new Intl.NumberFormat('id-ID').format(row.harga)"></td>
                                    <td class="px-4 py-4 text-right font-bold text-emerald-600 bg-emerald-50/5" x-text="(row.map === 'D' || row.map === 'd' || row.map === 'Debit') ? new Intl.NumberFormat('id-ID').format(row.total) : '-'"></td>
                                    <td class="px-4 py-4 text-right font-bold text-rose-600 bg-rose-50/5" x-text="(row.map === 'K' || row.map === 'k' || row.map === 'Kredit') ? new Intl.NumberFormat('id-ID').format(row.total) : '-'"></td>
                                    <td class="px-4 py-4 text-center">
                                        <button type="button" @click="@this.removeItem(i)" class="text-gray-300 hover:text-rose-600 transition-none">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
                            <tr class="font-black text-[10px] uppercase">
                                <td colspan="6" class="px-4 py-5 text-right text-gray-400 tracking-widest">Total Mutasi Draft</td>
                                <td class="px-4 py-5 text-right text-emerald-600 bg-emerald-100/10 text-base" x-text="new Intl.NumberFormat('id-ID').format(totalDebit)"></td>
                                <td class="px-4 py-5 text-right text-rose-600 bg-rose-100/10 text-base" x-text="new Intl.NumberFormat('id-ID').format(totalKredit)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="p-4 bg-amber-50 dark:bg-amber-900/10 border-t border-amber-100 dark:border-gray-800 flex justify-end">
                    <button type="button" wire:click="saveJurnal" :disabled="!isBalanced" :class="isBalanced ? 'bg-amber-600 text-white hover:bg-amber-700 shadow-md' : 'bg-gray-200 dark:bg-gray-800 text-gray-400 cursor-not-allowed border-transparent shadow-none'" class="px-12 py-3 rounded-[4px] font-black text-[10px] uppercase tracking-[0.2em] transition-none">Posting Jurnal</button>
                </div>
            </div>
        </div>

        {{-- TABLE HISTORY JURNAL UMUM (FINAL DATA) --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-sm font-black uppercase tracking-widest text-gray-500">Daftar Jurnal Umum (Final Data)</h2>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[4px] shadow-sm overflow-hidden custom-scroll">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse table-fixed min-w-[1400px]">
                        <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 sticky top-0">
                            <tr class="text-[10px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-widest">
                                <th class="px-4 py-4 w-[110px]">Tanggal</th>
                                <th class="px-4 py-4 w-[110px]">No Akun</th>
                                <th class="px-4 py-4 w-[180px]">Nama Akun</th>
                                <th class="px-4 py-4 text-center w-[110px]">Nomor Jurnal</th>
                                <th class="px-4 py-4 w-[240px]">Keterangan</th>
                                <th class="px-4 py-4 text-right w-[110px]">Kuantitas</th>
                                <th class="px-4 py-4 text-right w-[140px]">Harga</th>
                                <th class="px-4 py-4 text-right w-[150px] text-green-400 bg-green-50/10 font-black">Debit (Rp)</th>
                                <th class="px-4 py-4 text-right w-[150px] text-red-400 bg-red-50/10 font-black">Kredit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($historyJurnals as $hj)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 align-top transition-none">
                                <td class="px-4 py-4 text-gray-500 font-medium whitespace-nowrap">
                                    {{ $hj->tgl->format('d-m-Y') }}
                                </td>
                                <td class="px-4 py-4 font-mono font-bold text-amber-600 dark:text-amber-500">
                                    {{ $hj->no_akun }}
                                </td>
                                <td class="px-4 py-4 font-bold text-gray-800 dark:text-gray-100">
                                    {{ $hj->nama_akun }}
                                </td>
                                <td class="px-4 py-4 text-center text-gray-400 font-medium">
                                    {{ $hj->jurnal }}
                                </td>
                                <td class="px-4 py-4 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400 break-words whitespace-normal">
                                    {{ $hj->keterangan }}
                                </td>
                                <td class="px-4 py-4 text-right font-medium text-gray-400 dark:text-gray-500">
                                    {{ number_format($hj->banyak, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-right text-gray-400 dark:text-gray-500 font-mono">
                                    {{ number_format($hj->harga, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-green-400 bg-green-50/5">
                                    {{ in_array(strtolower($hj->map), ['d', 'debit']) ? number_format($hj->banyak * $hj->harga, 0, ',', '.') : '0' }}
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-red-400 bg-red-50/5">
                                    {{ in_array(strtolower($hj->map), ['k', 'kredit']) ? number_format($hj->banyak * $hj->harga, 0, ',', '.') : '0' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="px-6 py-10 text-center text-gray-400 italic text-xs">Belum ada riwayat transaksi yang diposting.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 font-black text-[10px] uppercase">
                            <tr>
                                <td colspan="7" class="px-4 py-5 text-right text-gray-400 tracking-widest uppercase">Total Akumulasi</td>
                                <td class="px-4 py-5 text-right text-green-400 bg-green-50/10 text-base">
                                    {{ number_format($historyJurnals->whereIn('map', ['D', 'debit', 'd', 'Debit'])->sum(fn($j) => $j->banyak * $j->harga), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-5 text-right text-red-400 bg-red-50/10 text-base">
                                    {{ number_format($historyJurnals->whereIn('map', ['K', 'kredit', 'k', 'Kredit'])->sum(fn($j) => $j->banyak * $j->harga), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</x-filament-panels::page>