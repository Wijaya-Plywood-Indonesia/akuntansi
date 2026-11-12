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
                        mode: @entangle('mode_ui'),
                        nominal: @entangle('nominal'),
                        nominal_split_a: @entangle('nominal_split_a'),
                        nominal_split_b: @entangle('nominal_split_b'),
                        akun_pelunasan_id: @entangle('akun_pelunasan_id'),
                        akun_split_a_id: @entangle('akun_split_a_id'),
                        akun_split_b_id: @entangle('akun_split_b_id'),
                        format(val) {
                            if (val === null || val === undefined || val === '' || val == 0) return '0';
                            let cleaned = val.toString().replace(/\D/g, '');
                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        },
                        get totalInput() {
                            if (this.mode === 'SPLIT') {
                                return (parseInt(this.nominal_split_a) || 0) + (parseInt(this.nominal_split_b) || 0);
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
                            {{-- TANGGAL PELUNASAN --}}
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wide block ml-1">
                                    Tanggal Pelunasan
                                </label>
                                <input type="date" wire:model.live="tanggal_pelunasan"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white" />
                            </div>

                            {{-- MODE PEMBAYARAN: PELUNASAN / SPLIT --}}
                            <div class="space-y-3">
                                <label
                                    class="text-[10px] font-bold text-gray-500 uppercase tracking-wide block ml-1">Mode
                                    Pembayaran</label>
                                <div
                                    class="grid grid-cols-2 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button wire:click="$set('mode_ui', 'PELUNASAN')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all {{ $mode_ui === 'PELUNASAN' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600' : 'text-gray-500' }}">Pelunasan</button>
                                    <button wire:click="$set('mode_ui', 'SPLIT')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all {{ $mode_ui === 'SPLIT' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600' : 'text-gray-500' }}">Split</button>
                                </div>

                                {{-- ═══ MODE: PELUNASAN (1 akun) ══════════════════════════════════════ --}}
                                @if ($mode_ui === 'PELUNASAN')
                                    <div wire:key="mode-pelunasan" class="space-y-2">

                                        {{-- Dropdown pilih akun 1101.x --}}
                                        <select wire:model.live="akun_pelunasan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 pr-8 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                            <option value="">— Pilih Akun Kas / Bank —</option>
                                            @foreach ($akunKasList as $akun)
                                                @php
                                                    $rekTerkait = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun->id);
                                                @endphp
                                                <option value="{{ $akun->id }}">
                                                    {{ $akun->kode_sub_anak_akun }} — {{ $akun->nama_sub_anak_akun }}
                                                    @if ($rekTerkait)
                                                        ({{ $rekTerkait->no_rekening }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>

                                        {{-- Chip info rekening terhubung --}}
                                        @if ($akun_pelunasan_id)
                                            @php
                                                $akunTerpilih = $akunKasList->firstWhere('id', $akun_pelunasan_id);
                                                $rekTerpilih = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun_pelunasan_id);
                                            @endphp
                                            @if ($rekTerpilih)
                                                <div
                                                    class="p-2 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-100 dark:border-primary-800/50">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-[8px] font-bold text-primary-600 dark:text-primary-400 uppercase">Rekening
                                                            Atas Nama</span>
                                                        <span
                                                            class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $rekTerpilih->atas_nama }}</span>
                                                        <div class="mt-1 flex justify-between items-center">
                                                            <span
                                                                class="text-sm font-black text-primary-700 dark:text-primary-300 font-mono tracking-tight">{{ $rekTerpilih->no_rekening }}</span>
                                                            <span
                                                                class="text-[8px] font-bold px-1.5 py-0.5 bg-primary-200 dark:bg-primary-800 rounded text-primary-800 dark:text-primary-200 uppercase">{{ $rekTerpilih->nama_bank }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif($akunTerpilih)
                                                <div
                                                    class="p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800/50">
                                                    <span
                                                        class="text-[10px] font-bold text-amber-700 dark:text-amber-400">💵
                                                        Kas Tunai — tidak ada rekening bank terhubung</span>
                                                </div>
                                            @endif
                                        @endif

                                        {{-- Input nominal --}}
                                        <div
                                            class="space-y-1.5 bg-gray-50/50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                                            <div class="space-y-1">
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
                                                        class="w-full bg-transparent border-none p-0 text-xl lg:text-2xl font-black focus:ring-0 tracking-tight dark:text-white"
                                                        placeholder="0" />
                                                </div>
                                            </div>

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
                                    </div>

                                {{-- ═══ MODE: SPLIT (2 akun) ══════════════════════════════════════════ --}}
                                @elseif ($mode_ui === 'SPLIT')
                                    <div wire:key="mode-split" class="space-y-3">

                                        {{-- Slot A --}}
                                        <div
                                            class="bg-gray-50/50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label
                                                class="text-[10px] font-black text-gray-500 uppercase tracking-wide">Slot
                                                A</label>

                                            <select wire:model.live="akun_split_a_id"
                                                class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 pr-8 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                                <option value="">— Pilih Akun —</option>
                                                @foreach ($akunKasList as $akun)
                                                    @php
                                                        $rek = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun->id);
                                                    @endphp
                                                    <option value="{{ $akun->id }}">
                                                        {{ $akun->kode_sub_anak_akun }} — {{ $akun->nama_sub_anak_akun }}
                                                        @if ($rek)
                                                            ({{ $rek->no_rekening }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>

                                            {{-- Chip rekening slot A --}}
                                            @if ($akun_split_a_id)
                                                @php
                                                    $rekA = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun_split_a_id);
                                                @endphp
                                                @if ($rekA)
                                                    <div
                                                        class="px-2 py-1 bg-primary-50 dark:bg-primary-900/20 rounded border border-primary-100 dark:border-primary-800/50 flex justify-between items-center">
                                                        <span
                                                            class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $rekA->atas_nama }}</span>
                                                        <span
                                                            class="text-[9px] font-bold font-mono text-primary-700 dark:text-primary-300">{{ $rekA->no_rekening }}</span>
                                                    </div>
                                                @else
                                                    <div
                                                        class="px-2 py-1 bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-100 dark:border-amber-800/50">
                                                        <span
                                                            class="text-[10px] font-bold text-amber-700 dark:text-amber-400">💵
                                                            Kas Tunai</span>
                                                    </div>
                                                @endif
                                            @endif

                                            <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                                <span class="text-xs font-bold text-primary-600">Rp</span>
                                                <input type="text" :value="format(nominal_split_a)"
                                                    @input="
                                                        let raw = $event.target.value.replace(/\D/g, '');
                                                        nominal_split_a = raw ? parseInt(raw) : 0;
                                                        $el.value = format(nominal_split_a);
                                                    "
                                                    class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 tracking-tight dark:text-white text-gray-900"
                                                    placeholder="0" />
                                            </div>
                                        </div>

                                        {{-- Slot B --}}
                                        <div
                                            class="bg-gray-50/50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800 space-y-2">
                                            <label
                                                class="text-[10px] font-black text-gray-500 uppercase tracking-wide">Slot
                                                B</label>

                                            <select wire:model.live="akun_split_b_id"
                                                class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 pr-8 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                                <option value="">— Pilih Akun —</option>
                                                @foreach ($akunKasList as $akun)
                                                    @php
                                                        $rek = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun->id);
                                                    @endphp
                                                    <option value="{{ $akun->id }}">
                                                        {{ $akun->kode_sub_anak_akun }} — {{ $akun->nama_sub_anak_akun }}
                                                        @if ($rek)
                                                            ({{ $rek->no_rekening }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>

                                            {{-- Chip rekening slot B --}}
                                            @if ($akun_split_b_id)
                                                @php
                                                    $rekB = $rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akun_split_b_id);
                                                @endphp
                                                @if ($rekB)
                                                    <div
                                                        class="px-2 py-1 bg-primary-50 dark:bg-primary-900/20 rounded border border-primary-100 dark:border-primary-800/50 flex justify-between items-center">
                                                        <span
                                                            class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $rekB->atas_nama }}</span>
                                                        <span
                                                            class="text-[9px] font-bold font-mono text-primary-700 dark:text-primary-300">{{ $rekB->no_rekening }}</span>
                                                    </div>
                                                @else
                                                    <div
                                                        class="px-2 py-1 bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-100 dark:border-amber-800/50">
                                                        <span
                                                            class="text-[10px] font-bold text-amber-700 dark:text-amber-400">💵
                                                            Kas Tunai</span>
                                                    </div>
                                                @endif
                                            @endif

                                            <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                                <span class="text-xs font-bold text-primary-600">Rp</span>
                                                <input type="text" :value="format(nominal_split_b)"
                                                    @input="
                                                        let raw = $event.target.value.replace(/\D/g, '');
                                                        nominal_split_b = raw ? parseInt(raw) : 0;
                                                        $el.value = format(nominal_split_b);
                                                    "
                                                    class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 tracking-tight dark:text-white text-gray-900"
                                                    placeholder="0" />
                                            </div>
                                        </div>

                                        {{-- Ringkasan split --}}
                                        <div
                                            class="bg-gray-50/50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
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
                                                    Total A + B harus pas menutup sisa tagihan.
                                                </p>
                                            </template>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- CATATAN --}}
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide block ml-1">Catatan
                                    Pelunasan</label>
                                <textarea wire:model.live="keterangan" rows="2" placeholder="Catatan (opsional)..."
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white dark:placeholder-gray-600"></textarea>
                            </div>

                            {{-- RIWAYAT --}}
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
                                        if (mode === 'PELUNASAN') {
                                            $wire.set('nominal', nominal);
                                        } else {
                                            $wire.set('nominal_split_a', nominal_split_a);
                                            $wire.set('nominal_split_b', nominal_split_b);
                                        }
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
