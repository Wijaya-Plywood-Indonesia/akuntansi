<x-filament-panels::page>
    <div class="min-h-full pb-10 transition-colors duration-300">
        <div x-data="{
                items: @entangle('items'),
                ongkir: @entangle('ongkir'),
                biayaLain: @entangle('biaya_lain'),
                ppnPersen: @entangle('ppn_persen'),
                nomorPo: @entangle('nomor_po'),
                tanggal: @entangle('tanggal'),
                tanggalJatuhTempo: @entangle('tanggal_jatuh_tempo'),
                supplierId: @entangle('supplier_id'),
                supplierName: @entangle('supplier_name'),
                supplierPhone: @entangle('supplier_phone'),
                supplierAddress: @entangle('supplier_address'),
                isNewSupplier: @entangle('is_new_supplier'),
                catatan: @entangle('catatan'),
                status: @entangle('status'),

                get subTotal() {
                    return this.items.reduce((acc, item) => {
                        let pengali = (item.hitung_dari === 'm3') ? (parseFloat(item.kubikasi) || 0) : (parseFloat(item.qty) || 0);
                        return acc + (pengali * (parseFloat(item.harga_beli) || 0));
                    }, 0);
                },

                get ppnNominal() {
                    return Math.round((this.subTotal * (parseFloat(this.ppnPersen) || 0)) / 100);
                },

                get grandTotal() {
                    return Math.max(0,
                        Math.round(this.subTotal)
                        + Math.round(this.ppnNominal)
                        + Math.round(parseFloat(this.ongkir) || 0)
                        + Math.round(parseFloat(this.biayaLain) || 0)
                    );
                },

                fmt(n) {
                    return Math.round(n).toLocaleString('id-ID');
                },

                format(val) {
                    if (val === null || val === undefined || val === '' || val == 0) return '0';
                    let cleaned = val.toString().replace(/\D/g, '');
                    return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },
            }">

            <form wire:submit.prevent="simpan"
                class="pos-pro-dashboard flex flex-col gap-4 lg:gap-6">

                {{-- MAIN INFO BAR --}}
                <div class="flex flex-wrap lg:flex-nowrap items-center justify-between bg-white dark:bg-gray-900 px-4 py-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm gap-x-6 gap-y-2">
                    <div class="flex flex-wrap lg:flex-nowrap items-center gap-4 lg:gap-6 w-full lg:w-auto">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Nomor PO:</span>
                            <div class="flex flex-col">
                                <input type="text" x-model="nomorPo" required class="px-2 py-1 bg-gray-50/50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded text-sm font-black text-primary-600 font-mono focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 w-44 transition-all" placeholder="PO-001" />
                                @error('nomor_po')
                                <span class="text-[10px] text-red-500 font-bold mt-0.5">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="h-8 w-px bg-gray-100 dark:bg-gray-800 hidden sm:block"></div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Tanggal PO:</span>
                            <div class="flex flex-col">
                                <input type="date" x-model="tanggal" required class="px-2 py-1 bg-gray-50/50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded text-xs font-medium text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all" />
                                @error('tanggal')
                                <span class="text-[10px] text-red-500 font-bold mt-0.5">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="h-8 w-px bg-gray-100 dark:bg-gray-800 hidden sm:block"></div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Jatuh Tempo:</span>
                            <input type="date" x-model="tanggalJatuhTempo" class="px-2 py-1 bg-gray-50/50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded text-xs font-medium text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all" />
                        </div>
                        <div class="h-8 w-px bg-gray-100 dark:bg-gray-800 hidden sm:block"></div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Oleh:</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase">{{ $created_by_name }}</span>
                        </div>
                    </div>
                    <div class="hidden lg:flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 px-3 py-1.5 rounded-lg border border-amber-200 dark:border-amber-800">
                        <x-heroicon-s-document-text class="w-3.5 h-3.5 text-amber-500" />
                        <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-tight">PURCHASE ORDER</span>
                    </div>
                </div>

                {{-- TWO-COLUMN CONTENT GRID --}}
                <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">

                    {{-- LEFT COLUMN --}}
                    <div class="w-full xl:w-[68%] flex flex-col gap-4 xl:gap-6 order-1">

                        {{-- CARD: INFORMASI SUPPLIER --}}
                        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm p-4 sm:p-6 space-y-4">
                            <div class="flex justify-between items-center border-b border-gray-50 dark:border-gray-800 pb-3">
                                <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-building-storefront class="w-4 h-4 text-primary-500" /> Informasi Supplier
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div class="md:col-span-2 flex flex-col gap-1.5 relative">
                                    <div wire:ignore
                                        x-data="{
                                            isOpen: false,
                                            search: '',
                                            suppliers: @js(\App\Models\Supplier::select('id', 'nama', 'telepon', 'alamat')->get()),
                                            init() {
                                                this.search = $wire.supplier_name || '';
                                                this.$watch('$wire.supplier_name', value => {
                                                    this.search = value || '';
                                                });
                                            },
                                            get filteredSuppliers() {
                                                if (this.search === '') return this.suppliers;
                                                return this.suppliers.filter(s => s.nama.toLowerCase().includes(this.search.toLowerCase()));
                                            },
                                            selectSupplier(sup) {
                                                $wire.supplier_id = sup.id;
                                                $wire.supplier_name = sup.nama;
                                                $wire.supplier_phone = sup.telepon;
                                                $wire.supplier_address = sup.alamat;
                                                $wire.is_new_supplier = false;
                                                this.search = sup.nama;
                                                this.isOpen = false;
                                            },
                                            createNewSupplier() {
                                                $wire.supplier_id = null;
                                                $wire.supplier_name = this.search;
                                                $wire.supplier_phone = '';
                                                $wire.supplier_address = '';
                                                $wire.is_new_supplier = true;
                                                this.isOpen = false;
                                            },
                                            clearSearch() {
                                                this.search = '';
                                                $wire.supplier_id = null;
                                                $wire.supplier_name = '';
                                                $wire.supplier_phone = '';
                                                $wire.supplier_address = '';
                                                $wire.is_new_supplier = false;
                                            }
                                        }"
                                        @click.away="isOpen = false"
                                        class="flex flex-col gap-1.5 relative">

                                        <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide ml-1 flex items-center justify-between">
                                            <span>Cari Supplier <span class="text-danger-500 font-bold">*</span></span>
                                            <span x-show="isNewSupplier" x-cloak class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 font-bold border border-amber-300 uppercase tracking-wider rounded">Input Manual</span>
                                        </label>

                                        <div class="relative flex items-center">
                                            <input type="text" x-model="search" @focus="isOpen = true" placeholder="Ketik nama supplier..."
                                                class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg pl-3 pr-8 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/20 dark:text-white transition-all outline-none">
                                            <button type="button" x-show="search.length > 0" @click="clearSearch()" x-cloak class="absolute right-2 text-gray-400 hover:text-rose-500 p-0.5 transition-colors">
                                                <x-heroicon-o-x-mark class="w-4 h-4" stroke-width="3" />
                                            </button>
                                        </div>

                                        <div x-show="isOpen" x-transition x-cloak class="absolute top-[55px] z-50 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl max-h-48 overflow-y-auto p-1 rounded-lg">
                                            <template x-for="sup in filteredSuppliers" :key="sup.id">
                                                <button type="button" @click="selectSupplier(sup)" class="w-full text-left px-3 py-2 hover:bg-primary-50 dark:hover:bg-primary-900/40 rounded flex flex-col group border-b border-gray-100 dark:border-gray-700 last:border-0 transition-colors">
                                                    <span class="font-bold text-gray-800 dark:text-gray-200 text-xs group-hover:text-primary-700" x-text="sup.nama"></span>
                                                    <span class="text-[10px] text-gray-500 dark:text-gray-400" x-text="sup.telepon ? sup.telepon : '-'"></span>
                                                </button>
                                            </template>
                                            <button type="button" @click="createNewSupplier()" class="w-full text-left px-3 py-2 mt-1 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/60 border border-amber-300 dark:border-amber-700 rounded flex items-center gap-2 transition-colors group">
                                                <div class="p-1 bg-amber-200 dark:bg-amber-800 rounded-full group-hover:bg-amber-300 dark:group-hover:bg-amber-700 transition-colors">
                                                    <x-heroicon-o-plus class="w-3.5 h-3.5 text-amber-800 dark:text-amber-200 font-bold" />
                                                </div>
                                                <div>
                                                    <span class="font-bold text-amber-800 dark:text-amber-200 text-xs">Tambah "<span x-text="search || 'Baru'"></span>"</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    @error('supplier_id')
                                    <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide ml-1">Nama Supplier</label>
                                    <input type="text" x-model="supplierName" placeholder="Nama..."
                                        x-bind:disabled="!isNewSupplier"
                                        x-bind:class="isNewSupplier ? 'bg-white dark:bg-gray-950 border-amber-300 dark:border-amber-700 focus:ring-2 focus:ring-amber-500/20 dark:text-white' : 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 cursor-not-allowed opacity-90'"
                                        class="w-full border rounded-lg py-1.5 px-3 text-xs focus:ring-2 focus:ring-primary-500/20 dark:text-white" />
                                    @error('supplier_name')
                                    <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide ml-1">Telepon</label>
                                    <input type="text" x-model="supplierPhone" placeholder="Telepon..."
                                        x-bind:disabled="!isNewSupplier"
                                        x-bind:class="isNewSupplier ? 'bg-white dark:bg-gray-950 border-amber-300 dark:border-amber-700 focus:ring-2 focus:ring-amber-500/20 dark:text-white' : 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 cursor-not-allowed opacity-90'"
                                        class="w-full border rounded-lg py-1.5 px-3 text-xs focus:ring-2 focus:ring-primary-500/20 dark:text-white" />
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide ml-1">Alamat Supplier</label>
                                <textarea x-model="supplierAddress" rows="1" placeholder="Alamat..."
                                    x-bind:disabled="!isNewSupplier"
                                    x-bind:class="isNewSupplier ? 'bg-white dark:bg-gray-950 border-amber-300 dark:border-amber-700 focus:ring-2 focus:ring-amber-500/20 dark:text-white' : 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 cursor-not-allowed opacity-90'"
                                    class="w-full border rounded-lg py-1.5 px-3 text-xs focus:ring-2 focus:ring-primary-500/20 dark:text-white resize-none"></textarea>
                            </div>
                        </div>

                        {{-- SEARCH BARANG CARD --}}
                        <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm"
                            x-data
                            @click.outside="$wire.closeDropdown()">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-2 flex items-center pointer-events-none text-gray-400">
                                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                                </div>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="Cari barang untuk PO... (/)"
                                    id="po-search-input"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg !pl-8 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary-500/20 transition-all"
                                    x-on:keydown.slash.window="
                                        if (!['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                                            $event.preventDefault();
                                            document.getElementById('po-search-input').focus();
                                        }
                                    "
                                    wire:focus="openDropdown" />

                                @if($showDropdown && !empty($searchResults))
                                <div class="absolute inset-x-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl z-50 max-h-60 overflow-y-auto">
                                    @foreach($searchResults as $barang)
                                    <div wire:click="selectBarang({{ $barang['id'] }})" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer flex justify-between items-center border-b border-gray-50 dark:border-gray-700 last:border-0">
                                        <div>
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white">{{ $barang['nama_barang'] }}</div>
                                            <div class="text-[10px] text-gray-500 uppercase">{{ $barang['kode_barang'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-sm text-primary-600">Rp{{ number_format($barang['harga_beli']) }}</div>
                                            <div class="text-[9px] text-gray-400">Satuan: {{ $barang['satuan'] }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- ITEMS TABLE --}}
                        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-800 flex justify-between items-center bg-gray-50/30 dark:bg-gray-800/30">
                                <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-shopping-cart class="w-4 h-4 text-primary-500" /> Item PO
                                </h3>
                                <span class="text-[10px] font-black text-primary-600 bg-primary-50 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase">{{ count($items) }} Items</span>
                            </div>

                            @error('items')
                            <div class="bg-red-50 dark:bg-red-950/20 border-l-4 border-red-500 p-4 m-4 rounded-r-lg">
                                <p class="text-xs font-bold text-red-700 dark:text-red-400">{{ $message }}</p>
                            </div>
                            @enderror

                            <div class="hidden lg:block min-w-full">
                                <table class="w-full text-left table-fixed border-collapse">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                        <tr class="bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-[22%]">Item</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-[12%]">Qty</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-[10%]">M³</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-[10%]">Dasar</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-[8%]">Satuan</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right w-[16%]">Harga</th>
                                            <th class="px-2 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right w-[18%]">Subtotal</th>
                                            <th class="px-2 py-2 w-[4%]"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse($items as $index => $item)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors group"
                                            x-data="{
                                                get qty() { return items[{{ $index }}].qty; },
                                                set qty(val) { items[{{ $index }}].qty = val; },
                                                get m3() { return items[{{ $index }}].kubikasi; },
                                                set m3(val) { items[{{ $index }}].kubikasi = val; },
                                                get hitung_dari() { return items[{{ $index }}].hitung_dari; },
                                                set hitung_dari(val) { items[{{ $index }}].hitung_dari = val; },
                                                get harga() { return items[{{ $index }}].harga_beli; },
                                                set harga(v) { items[{{ $index }}].harga_beli = v; },
                                                format(val) {
                                                    if (val === null || val === undefined || val === '') return '';
                                                    let str = val.toString();
                                                    if (typeof val === 'number') {
                                                        let parts = str.split('.');
                                                        let ip = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                        return parts[1] !== undefined ? ip + ',' + parts[1] : ip;
                                                    }
                                                    let clean = str.replace(/[^0-9,]/g, '');
                                                    let parts = clean.split(',');
                                                    let ip = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                    return parts[1] !== undefined ? ip + ',' + parts[1] : ip;
                                                }
                                            }">

                                            <td class="px-2 py-2 align-middle">
                                                <div class="font-medium text-sm text-gray-900 dark:text-white line-clamp-1">{{ $item['nama_barang'] }}</div>
                                            </td>

                                            <td class="px-2 py-2 align-middle">
                                                <div class="flex items-center justify-center bg-gray-100/50 dark:bg-gray-800 rounded-md p-0.5 border border-gray-200 dark:border-gray-700">
                                                    <button @click="qty = Math.max(0.01, parseFloat(qty || 1) - 1)" type="button" class="w-4 h-4 flex items-center justify-center text-gray-400 hover:text-primary-600">
                                                        <x-heroicon-o-minus class="w-3 h-3" />
                                                    </button>
                                                    <input type="text"
                                                        :value="format(qty)"
                                                        @input="
                                                             let clean = $event.target.value.replace(/[^0-9,]/g, '');
                                                             let raw = clean.replace(',', '.');
                                                             qty = raw ? parseFloat(raw) : 0;
                                                             $el.value = format(clean);
                                                         "
                                                        class="w-full text-center border-none bg-transparent p-0 text-xs font-bold focus:ring-0 dark:text-white" />
                                                    <button @click="qty = parseFloat(qty || 1) + 1" type="button" class="w-4 h-4 flex items-center justify-center text-gray-400 hover:text-primary-600">
                                                        <x-heroicon-o-plus class="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </td>

                                            <td class="px-2 py-2 align-middle text-center">
                                                <input type="text"
                                                    :value="m3"
                                                    @input="
                                                        let clean = $event.target.value.replace(/[^0-9.]/g, '');
                                                        m3 = clean ? parseFloat(clean) : 0;
                                                        $el.value = clean;
                                                    "
                                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md py-1 px-1 text-xs font-bold text-center focus:ring-2 focus:ring-primary-500/10 dark:text-white outline-none"
                                                    placeholder="0.0000" />
                                            </td>

                                            <td class="px-2 py-2 align-middle text-center">
                                                <select x-model="hitung_dari" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md py-1 px-1 text-xs font-bold text-gray-700 dark:text-white focus:ring-2 focus:ring-primary-500/10 outline-none">
                                                    <option value="qty">Per Qty</option>
                                                    <option value="m3">Per M³</option>
                                                </select>
                                            </td>

                                            <td class="px-2 py-2 text-center align-middle">
                                                <span class="text-[10px] font-bold bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-1 rounded uppercase border border-gray-100 dark:border-gray-700">
                                                    {{ $item['satuan'] }}
                                                </span>
                                            </td>

                                            <td class="px-2 py-2 align-middle">
                                                <input
                                                    type="text"
                                                    :value="format(harga)"
                                                    @input="
                                                            let raw = $event.target.value.replace(/\D/g, '');
                                                            harga = raw ? parseInt(raw) : 0;
                                                            $el.value = format(harga);
                                                        "
                                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md py-1 px-2 text-xs font-bold text-right focus:ring-2 focus:ring-primary-500/10 dark:text-white outline-none" />
                                            </td>

                                            <td class="px-2 py-2 text-right font-bold text-sm text-primary-600 dark:text-primary-400 align-middle">
                                                <span x-text="'Rp ' + fmt( (hitung_dari === 'm3' ? parseFloat(m3 || 0) : parseFloat(qty || 0)) * parseFloat(harga || 0) )"></span>
                                            </td>

                                            <td class="px-2 py-2 text-center align-middle">
                                                <button wire:click="removeItem({{ $index }})" type="button" class="text-gray-300 hover:text-red-500 transition-colors">
                                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="py-12">
                                                <div class="flex flex-col items-center justify-center w-full">
                                                    <x-heroicon-o-document-text class="w-10 h-10 text-gray-250 dark:text-gray-750 mb-1" />
                                                    <span class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-600 tracking-[0.2em]">Belum ada item PO</span>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- CATATAN CARD --}}
                        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm p-4 sm:p-6">
                            <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest flex items-center gap-2 mb-4 pb-3 border-b border-gray-50 dark:border-gray-800">
                                <x-heroicon-o-chat-bubble-left-ellipsis class="w-4 h-4 text-primary-500" /> Catatan
                            </h3>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide ml-1">Catatan PO</label>
                                <textarea wire:model="catatan" rows="4" placeholder="Catatan, syarat, atau instruksi khusus untuk supplier..."
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-xs focus:ring-2 focus:ring-primary-500/20 dark:text-white resize-none transition-all outline-none"></textarea>
                            </div>

                            @if($isEdit && $record)
                            {{-- Tombol cetak PDF muncul saat mode edit --}}
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <a href="{{ route('purchase-order.cetak-pdf', $record->id) }}" target="_blank"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm">
                                    <x-heroicon-o-printer class="w-4 h-4" />
                                    Cetak PDF PO
                                </a>
                                <p class="text-[10px] text-gray-400 mt-1.5">Buka PDF di tab baru untuk dicetak atau disimpan.</p>
                            </div>
                            @else
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <p class="text-[10px] text-gray-400 flex items-center gap-1.5">
                                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-blue-400" />
                                    Tombol <strong>Cetak PDF</strong> tersedia setelah PO disimpan.
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- RIGHT COLUMN: SUMMARY --}}
                    <div class="w-full xl:w-[32%] flex flex-col gap-4 order-2 xl:sticky xl:top-6 xl:self-start">

                        {{-- STATUS CARD --}}
                        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm p-4 sm:p-5">
                            <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-4 pb-3 border-b border-gray-50 dark:border-gray-800 flex items-center gap-2">
                                <x-heroicon-o-flag class="w-4 h-4 text-amber-500" /> Status PO
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach(\App\Models\PurchaseOrder::labelStatus() as $value => $label)
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model="status" value="{{ $value }}" class="peer sr-only" />
                                    <div class="w-full text-center px-2 py-2 rounded-lg border-2 border-gray-100 dark:border-gray-800 text-[10px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:text-primary-700 dark:peer-checked:text-primary-400 transition-all">
                                        {{ $label }}
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- SUMMARY CARD --}}
                        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm p-4 sm:p-5">
                            <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-4 pb-3 border-b border-gray-50 dark:border-gray-800 flex items-center gap-2">
                                <x-heroicon-o-calculator class="w-4 h-4 text-primary-500" /> Ringkasan PO
                            </h3>

                            <div class="space-y-2">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Sub Total</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="'Rp ' + fmt(subTotal)"></span>
                                </div>

                                {{-- Diskon --}}
                                <div class="flex justify-between items-center py-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Total Diskon</label>
                                    <input type="number" wire:model.live="total_diskon" min="0"
                                        class="w-28 text-right bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-1 py-0.5 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="0" />
                                </div>

                                {{-- PPN --}}
                                <div class="flex justify-between items-center py-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">PPN (%)</label>
                                    <input type="number" wire:model.live="ppn_persen" min="0" max="100" step="0.1"
                                        class="w-16 text-right bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-1 py-0.5 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-primary-500/20" />
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">PPN</span>
                                    <span class="text-sm font-bold text-blue-500" x-text="'Rp ' + fmt(ppnNominal)"></span>
                                </div>

                                {{-- Ongkir --}}
                                <div class="flex justify-between items-center py-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Ongkir</label>
                                    <input type="number" wire:model.live="ongkir" min="0"
                                        class="w-28 text-right bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-1 py-0.5 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="0" />
                                </div>

                                {{-- Biaya Lain --}}
                                <div class="flex justify-between items-center py-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Biaya Lain</label>
                                    <input type="number" wire:model.live="biaya_lain" min="0"
                                        class="w-28 text-right bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-1 py-0.5 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="0" />
                                </div>

                                <div class="border-t border-gray-100 dark:border-gray-800 pt-3 mt-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">Grand Total</span>
                                        <span class="text-xl font-black text-primary-600 dark:text-primary-400" x-text="'Rp ' + fmt(grandTotal)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- INFO NOTICE --}}
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-4">
                            <div class="flex gap-2 items-start">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1">Informasi PO</p>
                                    <p class="text-[10px] text-amber-600 dark:text-amber-400 leading-relaxed">PO ini hanya sebagai pencatatan order pembelian dan <strong>tidak masuk ke jurnal akuntansi</strong>. Tidak mempengaruhi buku besar/kas.</p>
                                </div>
                            </div>
                        </div>

                        {{-- ACTION BUTTONS --}}
                        <div class="flex flex-col gap-2">
                            <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-black text-sm uppercase tracking-widest py-3 px-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                                <x-heroicon-o-document-check class="w-4 h-4" />
                                <span>{{ $isEdit ? 'Perbarui PO' : 'Simpan PO' }}</span>
                            </button>

                            <a href="{{ \App\Filament\Resources\PurchaseOrders\PurchaseOrdersResource::getUrl('index') }}"
                                class="w-full flex items-center justify-center gap-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs uppercase tracking-widest py-2.5 px-6 rounded-xl transition-all">
                                <x-heroicon-o-arrow-left class="w-4 h-4" />
                                <span>Kembali</span>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
