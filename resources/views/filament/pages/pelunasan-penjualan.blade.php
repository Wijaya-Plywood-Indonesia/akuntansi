<x-filament::page>
    <div class="pelunasan-dashboard min-h-screen -m-8 p-8 bg-gray-100 dark:bg-gray-950 flex flex-col gap-4 lg:gap-6">

        <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">

            {{-- LEFT: LIST NOTA --}}
            <div class="w-full xl:w-[60%] flex flex-col gap-4 order-1">

                <div
                    class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-2 flex items-center pointer-events-none text-gray-400">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="search"
                            placeholder="Cari no. nota / nama customer..."
                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg !pl-8 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary-500/20 transition-all" />
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div
                        class="px-6 py-4 border-b border-gray-50 dark:border-gray-800 flex justify-between items-center bg-gray-50/30 dark:bg-gray-800/30">
                        <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                            Daftar Nota
                        </h3>
                        <span
                            class="text-[10px] font-black text-primary-600 bg-primary-50 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase">
                            {{ count($notaResults) }} Nota
                        </span>
                    </div>

                    {{-- Desktop table --}}
                    <div class="hidden md:block w-full overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left border-collapse">
                            <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                        No Nota</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                        Customer</th>
                                    <th
                                        class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">
                                        Total</th>
                                    <th
                                        class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">
                                        Dibayar</th>
                                    <th
                                        class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">
                                        Sisa</th>
                                    <th class="px-4 py-2 w-[10%]"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($notaResults as $nota)
                                    @php $sisaRow = max(0, (int) $nota->total - (int) $nota->bayar); @endphp
                                    <tr wire:key="nota-row-{{ $nota->id }}"
                                        class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors {{ $penjualan_id === $nota->id ? 'bg-primary-50/60 dark:bg-primary-900/10' : '' }} {{ $sisaRow <= 0 ? 'opacity-60' : '' }}">
                                        <td class="px-4 py-2.5">
                                            <span
                                                class="font-mono text-xs font-bold text-primary-600">{{ $nota->no_nota }}</span>
                                            <div class="text-[10px] text-gray-400 flex items-center gap-1">
                                                {{ $nota->tanggal?->format('d/m/Y H:i') }}
                                                <span
                                                    class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 uppercase">
                                                    {{ $nota->jenis_transaksi }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span
                                                class="text-sm font-medium text-gray-900 dark:text-white">{{ $nota->nama_customer }}</span>
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                            {{ number_format($nota->total) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-medium text-gray-500">
                                            {{ number_format($nota->bayar) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            @if ($sisaRow <= 0)
                                                <span
                                                    class="text-[10px] font-black text-green-500 uppercase">Lunas</span>
                                            @else
                                                <span
                                                    class="text-sm font-black text-red-500">{{ number_format($sisaRow) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            @if ($sisaRow <= 0)
                                                <span
                                                    class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                                    Lunas
                                                </span>
                                            @else
                                                <button wire:click="pilihNota({{ $nota->id }})"
                                                    class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg transition-all {{ $penjualan_id === $nota->id ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-primary-100 dark:hover:bg-primary-900/30' }}">
                                                    {{ $penjualan_id === $nota->id ? 'Dipilih' : 'Pilih' }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-16 text-center opacity-30">
                                            <x-heroicon-o-check-badge class="w-10 h-10 mx-auto mb-2" />
                                            <span class="text-xs font-black uppercase">Tidak Ada Data</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="md:hidden divide-y divide-gray-50 dark:divide-gray-800">
                        @forelse ($notaResults as $nota)
                            @php $sisaRow = max(0, (int) $nota->total - (int) $nota->bayar); @endphp
                            <div wire:key="nota-mobile-{{ $nota->id }}"
                                class="p-4 space-y-2 {{ $penjualan_id === $nota->id ? 'bg-primary-50/60 dark:bg-primary-900/10' : '' }} {{ $sisaRow <= 0 ? 'opacity-60' : '' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-mono text-xs font-bold text-primary-600">{{ $nota->no_nota }}
                                        </div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $nota->nama_customer }}</div>
                                        <div class="text-[10px] text-gray-400 flex items-center gap-1">
                                            {{ $nota->tanggal?->format('d/m/Y H:i') }}
                                            <span
                                                class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 uppercase">
                                                {{ $nota->jenis_transaksi }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($sisaRow <= 0)
                                        <span
                                            class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                            Lunas
                                        </span>
                                    @else
                                        <button wire:click="pilihNota({{ $nota->id }})"
                                            class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg {{ $penjualan_id === $nota->id ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                                            {{ $penjualan_id === $nota->id ? 'Dipilih' : 'Pilih' }}
                                        </button>
                                    @endif
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-right">
                                    <div>
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Total</div>
                                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                            {{ number_format($nota->total) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Dibayar</div>
                                        <div class="text-xs font-medium text-gray-500">
                                            {{ number_format($nota->bayar) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Sisa</div>
                                        @if ($sisaRow <= 0)
                                            <div class="text-xs font-black text-green-500 uppercase">Lunas</div>
                                        @else
                                            <div class="text-xs font-black text-red-500">
                                                {{ number_format($sisaRow) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-16 text-center opacity-30">
                                <x-heroicon-o-check-badge class="w-10 h-10 mx-auto mb-2" />
                                <span class="text-xs font-black uppercase">Tidak Ada Data</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- RIGHT: PANEL PELUNASAN --}}
            <div class="w-full xl:w-[40%] order-2">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-lg flex flex-col overflow-hidden sticky top-4"
                    x-data="{
                        sisa: {{ $this->getSisa() }},
                        metode: @entangle('metode_pembayaran'),
                        nominal: @entangle('nominal'),
                        nominal_tunai: @entangle('nominal_tunai'),
                        nominal_transfer: @entangle('nominal_transfer'),
                        format(val) {
                            if (val === null || val === undefined || val === '' || val == 0) return '0';
                            let cleaned = val.toString().replace(/\D/g, '');
                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        },
                        get totalInput() {
                            if (this.metode === 'TUNAI & TRANSFER') {
                                return (parseInt(this.nominal_tunai) || 0) + (parseInt(this.nominal_transfer) || 0);
                            }
                            return parseInt(this.nominal) || 0;
                        },
                        get sisaSetelah() {
                            return Math.max(0, this.sisa - this.totalInput);
                        },
                        get akanLunas() {
                            return this.totalInput === this.sisa && this.sisa > 0;
                        }
                    }">

                    @if (!$selectedNota)
                        <div class="p-10 text-center opacity-30">
                            <x-heroicon-o-cursor-arrow-rays class="w-10 h-10 mx-auto mb-2" />
                            <span class="text-xs font-black uppercase">Pilih nota di sebelah kiri</span>
                        </div>
                    @else
                        {{-- HEADER RINGKASAN NOTA --}}
                        <div
                            class="p-4 lg:p-5 bg-primary-600 dark:bg-black text-white relative overflow-hidden shrink-0">
                            <div class="relative z-10 space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span
                                            class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block">No
                                            Nota</span>
                                        <span class="text-sm font-mono font-black">{{ $selectedNota->no_nota }}</span>
                                    </div>
                                    <button wire:click="batalPilihNota" class="text-primary-100 hover:text-white">
                                        <x-heroicon-o-x-mark class="w-5 h-5" />
                                    </button>
                                </div>
                                <div>
                                    <span
                                        class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block">Customer</span>
                                    <span class="text-sm font-semibold">{{ $selectedNota->nama_customer }}</span>
                                </div>
                                <div class="pt-1">
                                    <span
                                        class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block mb-1">Sisa
                                        Tagihan</span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-sm font-bold opacity-50 dark:opacity-30">Rp</span>
                                        <span class="text-2xl lg:text-3xl font-black tracking-tight leading-none">
                                            {{ number_format($this->getSisa()) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="absolute -right-6 -bottom-6 opacity-10 dark:opacity-5">
                                <x-heroicon-s-banknotes class="w-24 h-24" />
                            </div>
                        </div>

                        {{-- RINCIAN TOTAL vs DIBAYAR --}}
                        <div class="px-4 lg:px-5 pt-4 pb-3 space-y-2 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total
                                    Tagihan</span>
                                <span
                                    class="font-black text-sm text-gray-900 dark:text-white">Rp{{ number_format($selectedNota->total) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Sudah
                                    Dibayar</span>
                                <span
                                    class="font-black text-sm text-green-600">Rp{{ number_format($selectedNota->bayar) }}</span>
                            </div>
                        </div>

                        <div class="p-4 lg:p-5 space-y-4">
                            {{-- METODE PEMBAYARAN --}}
                            <div class="space-y-2">
                                <label
                                    class="text-[10px] font-bold text-gray-500 uppercase tracking-wide block ml-1">Metode
                                    Pembayaran</label>
                                <div
                                    class="grid grid-cols-3 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button wire:click="$set('metode_pembayaran', 'TUNAI')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all {{ $metode_pembayaran === 'TUNAI' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600' : 'text-gray-500' }}">Tunai</button>
                                    <button wire:click="$set('metode_pembayaran', 'TRANSFER')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all {{ $metode_pembayaran === 'TRANSFER' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600' : 'text-gray-500' }}">Transfer</button>
                                    <button wire:click="$set('metode_pembayaran', 'TUNAI & TRANSFER')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all {{ $metode_pembayaran === 'TUNAI & TRANSFER' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600' : 'text-gray-500' }}">Tunai
                                        & Transfer</button>
                                </div>

                                @if ($metode_pembayaran === 'TRANSFER' || $metode_pembayaran === 'TUNAI & TRANSFER')
                                    <div wire:key="payment-bank-selector" class="space-y-2">
                                        <select wire:model.live="rekening_perusahaan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 pr-8 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer">
                                            <option value="">Pilih Bank...</option>
                                            @foreach ($rekeningPerusahaan as $rek)
                                                <option value="{{ $rek->id }}">{{ $rek->atas_nama }} |
                                                    {{ $rek->nama_bank }} | {{ $rek->no_rekening }}</option>
                                            @endforeach
                                        </select>

                                        @if ($selectedBank)
                                            <div wire:key="payment-selected-bank-details"
                                                class="p-2 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-100 dark:border-primary-800/50">
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-[8px] font-bold text-primary-600 dark:text-primary-400 uppercase">Rekening
                                                        Atas Nama</span>
                                                    <span
                                                        class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $selectedBank->atas_nama }}</span>
                                                    <div class="mt-1 flex justify-between items-center">
                                                        <span
                                                            class="text-sm font-black text-primary-700 dark:text-primary-300 font-mono tracking-tight">{{ $selectedBank->no_rekening }}</span>
                                                        <span
                                                            class="text-[8px] font-bold px-1.5 py-0.5 bg-primary-200 dark:bg-primary-800 rounded text-primary-800 dark:text-primary-200 uppercase">{{ $selectedBank->nama_bank }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- NOMINAL --}}
                            <div
                                class="space-y-1.5 bg-gray-50/50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">

                                @if ($metode_pembayaran === 'TUNAI & TRANSFER')
                                    <div wire:key="nominal-split-fields" class="flex flex-wrap gap-3 mb-2">
                                        <div class="flex-1 min-w-[120px] space-y-1">
                                            <label
                                                class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Tunai</label>
                                            <div class="flex items-center gap-1 border-b border-primary-500 pb-0.5">
                                                <span class="text-xs font-bold text-primary-600">Rp</span>
                                                <input type="text" :value="format(nominal_tunai)"
                                                    @input="
                                                        let raw = $event.target.value.replace(/\D/g, '');
                                                        nominal_tunai = raw ? parseInt(raw) : 0;
                                                        $el.value = format(nominal_tunai);
                                                    "
                                                    class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 tracking-tight dark:text-white text-gray-900" />
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-[120px] space-y-1">
                                            <label
                                                class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Transfer</label>
                                            <div class="flex items-center gap-1 border-b border-primary-500 pb-0.5">
                                                <span class="text-xs font-bold text-primary-600">Rp</span>
                                                <input type="text" :value="format(nominal_transfer)"
                                                    @input="
                                                        let raw = $event.target.value.replace(/\D/g, '');
                                                        nominal_transfer = raw ? parseInt(raw) : 0;
                                                        $el.value = format(nominal_transfer);
                                                    "
                                                    class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 tracking-tight dark:text-white text-gray-900" />
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div wire:key="nominal-single-field" class="space-y-1">
                                        <div class="flex justify-between items-center">
                                            <label
                                                class="text-[10px] font-bold text-gray-500 uppercase tracking-wide ml-1">Nominal
                                                Diterima</label>
                                            <button type="button" wire:click="setNominal('pas')"
                                                class="text-[9px] font-bold uppercase text-primary-600 hover:underline">Pas
                                                (Lunas)</button>
                                        </div>
                                        <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                            <span class="text-lg font-bold text-primary-600">Rp</span>
                                            <input type="text" :value="format(nominal)"
                                                @input="
                                                    let raw = $event.target.value.replace(/\D/g, '');
                                                    nominal = raw ? parseInt(raw) : 0;
                                                    $el.value = format(nominal);
                                                "
                                                class="w-full bg-transparent border-none p-0 text-xl lg:text-2xl font-black focus:ring-0 tracking-tight"
                                                placeholder="0" />
                                        </div>
                                    </div>
                                @endif

                                <div class="pt-0.5 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase ml-1"
                                        x-text="akanLunas ? 'Status' : 'Sisa Setelah Ini'"></span>
                                    <span class="text-base lg:text-lg font-bold"
                                        :class="akanLunas ? 'text-green-500' : 'text-red-500'"
                                        x-text="akanLunas ? 'LUNAS ✓' : format(sisaSetelah)">
                                    </span>
                                </div>

                                <template x-if="!akanLunas && totalInput > 0">
                                    <p class="text-[10px] font-semibold text-red-500 ml-1 pt-0.5">
                                        Nominal harus pas menutup sisa tagihan. Tidak bisa dicicil.
                                    </p>
                                </template>
                            </div>

                            {{-- CATATAN --}}
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide block ml-1">Catatan
                                    Pelunasan</label>
                                <textarea wire:model.live="keterangan" rows="2" placeholder="Catatan (opsional)..."
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white dark:placeholder-gray-600"></textarea>
                            </div>

                            {{-- RIWAYAT (placeholder, menunggu tabel riwayat) --}}
                            <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                                <span
                                    class="text-[10px] font-bold text-gray-400 uppercase tracking-wide block mb-2">Riwayat
                                    Pembayaran</span>
                                <div class="text-[11px] text-gray-400 italic">
                                    Awal: Rp{{ number_format($selectedNota->bayar) }} pada
                                    {{ $selectedNota->tanggal?->format('d/m/Y') }}
                                    {{-- TODO: ganti dengan list dari tabel riwayat pelunasan begitu tersedia --}}
                                </div>
                            </div>
                        </div>

                        {{-- ACTION --}}
                        <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                            <div class="flex gap-2">
                                <button :disabled="!akanLunas"
                                    @click="
                                        if (!akanLunas) return;
                                        $wire.set('nominal', nominal);
                                        $wire.set('nominal_tunai', nominal_tunai);
                                        $wire.set('nominal_transfer', nominal_transfer);
                                        $wire.simpanPelunasan();
                                    "
                                    :class="akanLunas ? '' : 'opacity-40 cursor-not-allowed pointer-events-none'"
                                    class="flex-grow py-2.5 btn-primary text-white rounded-lg font-bold text-sm active:translate-y-0.5 transition-all tracking-wide">
                                    Simpan Pelunasan
                                </button>
                                <button wire:click="batalPilihNota"
                                    class="py-2.5 px-4 btn-secondary text-sm font-bold tracking-wide transition-all rounded-lg">
                                    Batal
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .pelunasan-dashboard {
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background-color: #d97706 !important;
        }

        .btn-primary:hover {
            background-color: #b45309 !important;
        }

        .btn-secondary {
            background-color: #e5e7eb !important;
            color: #374151 !important;
        }

        .dark .btn-secondary {
            background-color: #374151 !important;
            color: #e5e7eb !important;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
        }
    </style>
</x-filament::page>
