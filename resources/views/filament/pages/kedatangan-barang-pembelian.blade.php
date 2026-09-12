<x-filament::page>
    <div class="pelunasan-dashboard min-h-screen -m-8 p-8 bg-gray-100 dark:bg-gray-950 flex flex-col gap-4 lg:gap-6">

        <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">

            {{-- LEFT: LIST PEMBELIAN MENUNGGU KEDATANGAN --}}
            <div class="w-full xl:w-[60%] flex flex-col gap-4 order-1">

                <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-2 flex items-center pointer-events-none text-gray-400">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="search"
                            placeholder="Cari no. nota / nama supplier..."
                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg !pl-8 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary-500/20 transition-all" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-800 flex justify-between items-center bg-gray-50/30 dark:bg-gray-800/30">
                        <h3 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                            Menunggu Kedatangan Barang
                        </h3>
                        <span class="text-[10px] font-black text-primary-600 bg-primary-50 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase">
                            {{ count($notaResults) }} Nota
                        </span>
                    </div>

                    {{-- Desktop table --}}
                    <div class="hidden md:block w-full overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left border-collapse">
                            <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider">No Nota</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jenis</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Grand Total</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Dibayar</th>
                                    <th class="px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Sisa Tagihan</th>
                                    <th class="px-4 py-2 w-[10%]"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($notaResults as $nota)
                                    <tr wire:key="nota-row-{{ $nota->id }}"
                                        class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors {{ $pembelian_id === $nota->id ? 'bg-primary-50/60 dark:bg-primary-900/10' : '' }}">
                                        <td class="px-4 py-2.5">
                                            <span class="font-mono text-xs font-bold text-primary-600">{{ $nota->nomor_nota }}</span>
                                            <div class="text-[10px] text-gray-400">
                                                {{ optional($nota->tanggal)->format('d/m/Y') }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $nota->supplier_name }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="text-[9px] font-black uppercase px-2 py-1 rounded-full whitespace-nowrap inline-block {{ match($nota->jenis_pembayaran) { 'DP' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'NORMAL' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300', default => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' } }}">
                                                {{ match($nota->jenis_pembayaran) { 'DP' => 'DP', 'NORMAL' => 'Hutang (Normal)', default => 'Bayar Dimuka' } }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                            {{ number_format($nota->grand_total) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-semibold text-gray-500 dark:text-gray-400">
                                            {{ number_format($nota->totalSudahDibayar()) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-black {{ $nota->sisaTagihan() > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                            {{ $nota->sisaTagihan() > 0 ? number_format($nota->sisaTagihan()) : 'Lunas' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <button wire:click="pilihNota({{ $nota->id }})"
                                                class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg transition-all {{ $pembelian_id === $nota->id ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-primary-100 dark:hover:bg-primary-900/30' }}">
                                                {{ $pembelian_id === $nota->id ? 'Dipilih' : 'Pilih' }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-16 text-center opacity-30">
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
                            <div wire:key="nota-mobile-{{ $nota->id }}"
                                class="p-4 space-y-2 {{ $pembelian_id === $nota->id ? 'bg-primary-50/60 dark:bg-primary-900/10' : '' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-mono text-xs font-bold text-primary-600">{{ $nota->nomor_nota }}</div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $nota->supplier_name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ optional($nota->tanggal)->format('d/m/Y') }}</div>
                                        <span class="inline-block mt-1 text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap {{ match($nota->jenis_pembayaran) { 'DP' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'NORMAL' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300', default => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' } }}">
                                            {{ match($nota->jenis_pembayaran) { 'DP' => 'DP', 'NORMAL' => 'Hutang (Normal)', default => 'Bayar Dimuka' } }}
                                        </span>
                                    </div>
                                    <button wire:click="pilihNota({{ $nota->id }})"
                                        class="text-[10px] font-bold uppercase px-3 py-1.5 rounded-lg {{ $pembelian_id === $nota->id ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                                        {{ $pembelian_id === $nota->id ? 'Dipilih' : 'Pilih' }}
                                    </button>
                                </div>
                                <div class="flex justify-between items-end">
                                    <div>
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Grand Total</div>
                                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ number_format($nota->grand_total) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Dibayar</div>
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ number_format($nota->totalSudahDibayar()) }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[9px] text-gray-400 uppercase font-bold">Sisa Tagihan</div>
                                        <div class="text-xs font-black {{ $nota->sisaTagihan() > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                            {{ $nota->sisaTagihan() > 0 ? number_format($nota->sisaTagihan()) : 'Lunas' }}
                                        </div>
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

            {{-- RIGHT: PANEL KONFIRMASI --}}
            <div class="w-full xl:w-[40%] order-2">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-lg flex flex-col overflow-hidden sticky top-4">

                    @if (!$selectedNota)
                        <div class="p-10 text-center opacity-30">
                            <x-heroicon-o-cursor-arrow-rays class="w-10 h-10 mx-auto mb-2" />
                            <span class="text-xs font-black uppercase">Pilih nota di sebelah kiri</span>
                        </div>
                    @else
                        @php
                            $isDp = $selectedNota->jenis_pembayaran === \App\Models\Pembelian::JENIS_DP;
                            $sisa = $selectedNota->sisaTagihan();
                            $totalDibayar = $selectedNota->totalSudahDibayar();
                        @endphp

                        {{-- HEADER RINGKASAN NOTA --}}
                        <div class="p-4 lg:p-5 bg-primary-600 dark:bg-black text-white relative overflow-hidden shrink-0">
                            <div class="relative z-10 space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block">No Nota</span>
                                        <span class="text-sm font-mono font-black">{{ $selectedNota->nomor_nota }}</span>
                                    </div>
                                    <button wire:click="batalPilihNota" class="text-primary-100 hover:text-white">
                                        <x-heroicon-o-x-mark class="w-5 h-5" />
                                    </button>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block">Supplier</span>
                                    <span class="text-sm font-semibold">{{ $selectedNota->supplier_name }}</span>
                                </div>
                                <div class="pt-1 grid grid-cols-2 gap-3">
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block mb-1">Grand Total</span>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-xs font-bold opacity-50 dark:opacity-30">Rp</span>
                                            <span class="text-xl lg:text-2xl font-black tracking-tight leading-none">
                                                {{ number_format($selectedNota->grand_total) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-primary-100 dark:text-primary-500 block mb-1">
                                            {{ $sisa > 0 ? 'Sisa Tagihan' : 'Status' }}
                                        </span>
                                        <div class="flex items-baseline gap-1">
                                            @if ($sisa > 0)
                                                <span class="text-xs font-bold opacity-50 dark:opacity-30">Rp</span>
                                                <span class="text-xl lg:text-2xl font-black tracking-tight leading-none text-amber-300">
                                                    {{ number_format($sisa) }}
                                                </span>
                                            @else
                                                <span class="text-lg font-black tracking-tight leading-none text-emerald-300">LUNAS</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($isDp)
                                    <div class="text-[10px] text-primary-100 dark:text-primary-400">
                                        Sudah dibayar (DP): Rp {{ number_format($totalDibayar) }}
                                    </div>
                                @endif
                            </div>
                            <div class="absolute -right-6 -bottom-6 opacity-10 dark:opacity-5">
                                <x-heroicon-s-truck class="w-24 h-24" />
                            </div>
                        </div>

                        {{-- DAFTAR BARANG --}}
                        <div class="p-4 lg:p-5 space-y-3 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-wider">Barang yang Dipesan</h3>
                            <div class="space-y-2 max-h-52 overflow-y-auto">
                                @foreach ($selectedNota->detailPembelians as $detail)
                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 dark:border-gray-800 pb-2">
                                        <div>
                                            <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $detail->nama_barang }}</div>
                                            <div class="text-[10px] text-gray-400">
                                                {{ number_format($detail->qty) }} {{ $detail->satuan }} &times; Rp{{ number_format($detail->harga_beli) }}
                                            </div>
                                        </div>
                                        <div class="font-bold text-gray-700 dark:text-gray-300">
                                            Rp{{ number_format($detail->subtotal) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if ($selectedNota->jenis_pembayaran === \App\Models\Pembelian::JENIS_NORMAL)
                            {{-- ═══════════ PANEL: BAYAR HUTANG (NORMAL) ═══════════ --}}
                            {{-- Barang & Utang Usaha SUDAH diakui penuh sejak validasi
                                 awal. Di sini murni pelunasan uang yang jatuh tempo,
                                 boleh dicicil berkali-kali sampai sisa = 0. --}}
                            <div class="p-4 lg:p-5 space-y-3">
                                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800/50">
                                    <p class="text-[11px] text-blue-700 dark:text-blue-300 leading-relaxed">
                                        Barang &amp; <strong>Utang Usaha</strong> untuk nota ini sudah diakui penuh
                                        sejak divalidasi. Ini murni pelunasan hutang yang jatuh tempo — boleh dicicil,
                                        tidak wajib langsung lunas sekaligus.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Tanggal Bayar</label>
                                    <input type="date" wire:model="hutang_tanggal"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                </div>

                                <div class="flex flex-col gap-1.5"
                                    x-data="{
                                        nominal: @entangle('hutang_nominal'),
                                        format(val) {
                                            if (val === null || val === undefined || val === '') return '';
                                            let cleaned = val.toString().replace(/\D/g, '');
                                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                        }
                                    }">
                                    <div class="flex justify-between items-center">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Nominal Bayar</label>
                                        <button type="button" @click="nominal = '{{ (int) $sisa }}'" class="text-[9px] font-bold text-primary-600 hover:underline uppercase tracking-wide">Bayar Pas</button>
                                    </div>
                                    <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                        <span class="text-sm font-bold text-primary-600">Rp</span>
                                        <input type="text" inputmode="numeric" :value="format(nominal)"
                                            @input="
                                                let raw = $event.target.value.replace(/\D/g, '');
                                                nominal = raw ? raw : '';
                                                $el.value = format(raw);
                                            "
                                            @focus="$event.target.select()"
                                            class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 dark:text-white" placeholder="0" />
                                    </div>
                                    @error('hutang_nominal') <span class="text-[10px] text-danger-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button type="button" wire:click="$set('hutang_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TUNAI }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $hutang_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TUNAI ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Tunai
                                    </button>
                                    <button type="button" wire:click="$set('hutang_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TRANSFER }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $hutang_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Transfer
                                    </button>
                                </div>

                                @if ($hutang_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER)
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Rekening Tujuan</label>
                                        <select wire:model="hutang_rekening_perusahaan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                            <option value="">Pilih Rekening...</option>
                                            @foreach ($rekeningPerusahaan as $rek)
                                                <option value="{{ $rek->id }}">{{ $rek->atas_nama }} | {{ $rek->nama_bank }} | {{ $rek->no_rekening }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <input type="text" wire:model="hutang_reference_number" placeholder="No. Bukti / Ref (opsional)"
                                    class="w-full p-2 text-xs font-bold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <input type="text" wire:model="hutang_catatan" placeholder="Catatan (opsional)"
                                    class="w-full p-2 text-xs font-semibold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1">Foto Bukti Bayar (opsional)</label>
                                    <input type="file" wire:model="hutang_foto" multiple accept="image/*"
                                        class="w-full p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none" />
                                    <div wire:loading wire:target="hutang_foto" class="text-[10px] text-gray-400 mt-1">Mengupload...</div>
                                    @foreach ($hutang_foto as $fIdx => $f)
                                        <div class="relative inline-block mt-2 mr-2 group">
                                            <img src="{{ $f->temporaryUrl() }}" class="h-16 rounded border border-gray-200 dark:border-gray-700" />
                                            <button type="button" wire:click.prevent.stop="removeFoto('hutang_foto', {{ $fIdx }})"
                                                title="Hapus foto ini"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center bg-black/70 hover:bg-rose-600 text-white rounded-full text-xs leading-none transition-colors">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                                <button wire:click="bayarHutang" wire:confirm="Simpan pelunasan hutang ini? Jurnal pembayaran akan langsung dicatat."
                                    class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm py-2.5 rounded-lg shadow-sm transition-all active:translate-y-0.5 tracking-wide shrink-0">
                                    <x-heroicon-o-banknotes class="w-4 h-4 stroke-[3px]" />
                                    BAYAR HUTANG
                                </button>
                            </div>
                        @elseif ($isDp && $selectedNota->sudahDiterimaBarangnya() && $sisa > 0)
                            {{-- ═══════════ PANEL: BAYAR HUTANG DP (barang sudah datang) ═══════════ --}}
                            {{-- Barang & Utang Usaha sudah diakui penuh sejak "Konfirmasi Barang
                                 Datang" (versi belum lunas). Di sini murni cicilan hutangnya —
                                 nominal bebas, boleh dicicil. Cicilan yang menutup sisa ke 0 otomatis
                                 ikut membalik Uang Muka Pembelian yang sudah terkumpul sejak awal. --}}
                            <div class="p-4 lg:p-5 space-y-3 border-b border-gray-100 dark:border-gray-800">
                                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800/50">
                                    <p class="text-[11px] text-blue-700 dark:text-blue-300 leading-relaxed">
                                        Barang untuk nota DP ini <strong>sudah dikonfirmasi datang</strong> dan
                                        <strong>Utang Usaha</strong> sudah diakui penuh. Sisa yang ditagih di sini
                                        murni pelunasan uangnya — boleh dicicil, tidak wajib langsung lunas
                                        sekaligus. Cicilan yang menutup sisa ke 0 akan ikut membalik
                                        <strong>Uang Muka Pembelian</strong> yang sudah terkumpul.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Tanggal Bayar</label>
                                    <input type="date" wire:model="hutangdp_tanggal"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                </div>

                                <div class="flex flex-col gap-1.5"
                                    x-data="{
                                        nominal: @entangle('hutangdp_nominal'),
                                        format(val) {
                                            if (val === null || val === undefined || val === '') return '';
                                            let cleaned = val.toString().replace(/\D/g, '');
                                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                        }
                                    }">
                                    <div class="flex justify-between items-center">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Nominal Bayar</label>
                                        <button type="button" @click="nominal = '{{ (int) $sisa }}'" class="text-[9px] font-bold text-primary-600 hover:underline uppercase tracking-wide">Bayar Pas</button>
                                    </div>
                                    <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                        <span class="text-sm font-bold text-primary-600">Rp</span>
                                        <input type="text" inputmode="numeric" :value="format(nominal)"
                                            @input="
                                                let raw = $event.target.value.replace(/\D/g, '');
                                                nominal = raw ? raw : '';
                                                $el.value = format(raw);
                                            "
                                            @focus="$event.target.select()"
                                            class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 dark:text-white" placeholder="0" />
                                    </div>
                                    @error('hutangdp_nominal') <span class="text-[10px] text-danger-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button type="button" wire:click="$set('hutangdp_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TUNAI }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $hutangdp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TUNAI ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Tunai
                                    </button>
                                    <button type="button" wire:click="$set('hutangdp_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TRANSFER }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $hutangdp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Transfer
                                    </button>
                                </div>

                                @if ($hutangdp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER)
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Rekening Tujuan</label>
                                        <select wire:model="hutangdp_rekening_perusahaan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                            <option value="">Pilih Rekening...</option>
                                            @foreach ($rekeningPerusahaan as $rek)
                                                <option value="{{ $rek->id }}">{{ $rek->atas_nama }} | {{ $rek->nama_bank }} | {{ $rek->no_rekening }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <input type="text" wire:model="hutangdp_reference_number" placeholder="No. Bukti / Ref (opsional)"
                                    class="w-full p-2 text-xs font-bold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <input type="text" wire:model="hutangdp_catatan" placeholder="Catatan (opsional)"
                                    class="w-full p-2 text-xs font-semibold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1">Foto Bukti Bayar (opsional)</label>
                                    <input type="file" wire:model="hutangdp_foto" multiple accept="image/*"
                                        class="w-full p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none" />
                                    <div wire:loading wire:target="hutangdp_foto" class="text-[10px] text-gray-400 mt-1">Mengupload...</div>
                                    @foreach ($hutangdp_foto as $fIdx => $f)
                                        <div class="relative inline-block mt-2 mr-2 group">
                                            <img src="{{ $f->temporaryUrl() }}" class="h-16 rounded border border-gray-200 dark:border-gray-700" />
                                            <button type="button" wire:click.prevent.stop="removeFoto('hutangdp_foto', {{ $fIdx }})"
                                                title="Hapus foto ini"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center bg-black/70 hover:bg-rose-600 text-white rounded-full text-xs leading-none transition-colors">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                                <button wire:click="bayarHutangDp" wire:confirm="Simpan pelunasan hutang ini? Jurnal pembayaran akan langsung dicatat."
                                    class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm py-2.5 rounded-lg shadow-sm transition-all active:translate-y-0.5 tracking-wide shrink-0">
                                    <x-heroicon-o-banknotes class="w-4 h-4 stroke-[3px]" />
                                    BAYAR HUTANG
                                </button>
                            </div>
                        @else
                        @if ($isDp && $sisa > 0)
                            {{-- TAB SWITCHER: Tambah DP vs Barang Datang. Dipisah
                                 supaya admin tidak salah isi kolom nominal antara
                                 dua aksi yang beda konsekuensi. --}}
                            <div class="flex border-b border-gray-100 dark:border-gray-800">
                                <button type="button" wire:click="$set('panelTab', 'dp')"
                                    class="flex-1 flex items-center justify-center gap-1.5 py-3 text-[11px] font-black uppercase tracking-wide transition-all {{ $panelTab === 'dp' ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/30 dark:bg-primary-900/10' : 'text-gray-400 hover:text-gray-600' }}">
                                    <x-heroicon-o-banknotes class="w-4 h-4" />
                                    Tambah DP
                                </button>
                                <button type="button" wire:click="$set('panelTab', 'datang')"
                                    class="flex-1 flex items-center justify-center gap-1.5 py-3 text-[11px] font-black uppercase tracking-wide transition-all {{ $panelTab === 'datang' ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/30 dark:bg-primary-900/10' : 'text-gray-400 hover:text-gray-600' }}">
                                    <x-heroicon-o-truck class="w-4 h-4" />
                                    Barang Datang
                                </button>
                            </div>

                            @if ($panelTab === 'dp')
                            {{-- TAMBAH DP (boleh berkali-kali, nominal bebas) --}}
                            <div class="p-4 lg:p-5 space-y-3 border-b border-gray-100 dark:border-gray-800">
                                <p class="text-[10px] text-gray-400 leading-relaxed">
                                    Catat setoran DP baru. Boleh kurang dari sisa tagihan (dicicil lagi nanti).
                                    Barang belum diakui masuk — pakai ini kalau baru terima uang muka, belum
                                    ada barang fisik yang datang.
                                </p>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Tanggal Bayar DP</label>
                                    <input type="date" wire:model="dp_tanggal"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                </div>

                                <div class="flex flex-col gap-1.5"
                                    x-data="{
                                        nominal: @entangle('dp_nominal'),
                                        format(val) {
                                            if (val === null || val === undefined || val === '') return '';
                                            let cleaned = val.toString().replace(/\D/g, '');
                                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                        }
                                    }">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Nominal DP</label>
                                    <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                        <span class="text-sm font-bold text-primary-600">Rp</span>
                                        <input type="text" inputmode="numeric" :value="format(nominal)"
                                            @input="
                                                let raw = $event.target.value.replace(/\D/g, '');
                                                nominal = raw ? raw : '';
                                                $el.value = format(raw);
                                            "
                                            @focus="$event.target.select()"
                                            class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 dark:text-white" placeholder="0" />
                                    </div>
                                    @error('dp_nominal') <span class="text-[10px] text-danger-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button type="button" wire:click="$set('dp_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TUNAI }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $dp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TUNAI ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Tunai
                                    </button>
                                    <button type="button" wire:click="$set('dp_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TRANSFER }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $dp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Transfer
                                    </button>
                                </div>

                                @if ($dp_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER)
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Rekening Tujuan</label>
                                        <select wire:model="dp_rekening_perusahaan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                            <option value="">Pilih Rekening...</option>
                                            @foreach ($rekeningPerusahaan as $rek)
                                                <option value="{{ $rek->id }}">{{ $rek->atas_nama }} | {{ $rek->nama_bank }} | {{ $rek->no_rekening }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <input type="text" wire:model="dp_reference_number" placeholder="No. Bukti / Ref (opsional)"
                                    class="w-full p-2 text-xs font-bold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1">Foto Bukti Bayar (opsional)</label>
                                    <input type="file" wire:model="dp_foto" multiple accept="image/*"
                                        class="w-full p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none" />
                                    <div wire:loading wire:target="dp_foto" class="text-[10px] text-gray-400 mt-1">Mengupload...</div>
                                    @foreach ($dp_foto as $fIdx => $f)
                                        <div class="relative inline-block mt-2 mr-2 group">
                                            <img src="{{ $f->temporaryUrl() }}" class="h-16 rounded border border-gray-200 dark:border-gray-700" />
                                            <button type="button" wire:click.prevent.stop="removeFoto('dp_foto', {{ $fIdx }})"
                                                title="Hapus foto ini"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center bg-black/70 hover:bg-rose-600 text-white rounded-full text-xs leading-none transition-colors">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                                <button wire:click="tambahDp" wire:confirm="Simpan setoran DP ini? Jurnal Uang Muka Pembelian akan langsung dicatat."
                                    class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm py-2.5 rounded-lg shadow-sm transition-all active:translate-y-0.5 tracking-wide shrink-0">
                                    <x-heroicon-o-plus-circle class="w-4 h-4" />
                                    SIMPAN DP
                                </button>
                            </div>
                            @else
                            {{-- KONFIRMASI BARANG DATANG (khusus sisa DP) --}}
                            <div class="p-4 lg:p-5 space-y-3">
                                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800/50">
                                    <p class="text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed">
                                        Barang fisik sudah diterima? Kolom di bawah <strong>opsional, defaultnya 0</strong>
                                        (barang datang duluan, belum bayar apa-apa) — <strong>Persediaan</strong> &amp;
                                        <strong>Utang Usaha</strong> tetap diakui penuh, sisanya bisa dicicil belakangan
                                        lewat tab ini lagi. Isi nominal kalau mau sekalian bayar sebagian atau pas
                                        (nominal pas otomatis membalik seluruh <strong>Uang Muka Pembelian</strong>).
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Tanggal Barang Datang</label>
                                    <input type="date" wire:model="sisa_tanggal"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">No. Nota / Surat Jalan</label>
                                    <input type="text" wire:model="sisa_nomor_nota" placeholder="Isi/perbaiki nomor surat jalan asli dari supplier"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                    <p class="text-[9.5px] text-gray-500 dark:text-gray-400 leading-snug px-0.5">
                                        Boleh diperbaiki di sini kalau saat nota dibuat suratnya belum ada.
                                        Jurnal kedatangan barang akan memakai nomor yang tertulis di sini.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1.5"
                                    x-data="{
                                        nominal: @entangle('sisa_nominal'),
                                        format(val) {
                                            if (val === null || val === undefined || val === '') return '';
                                            let cleaned = val.toString().replace(/\D/g, '');
                                            return cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                        }
                                    }">
                                    <div class="flex justify-between items-center">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Nominal Dibayar Sekarang (opsional)</label>
                                        <button type="button" @click="nominal = '{{ (int) $sisa }}'" class="text-[9px] font-bold text-primary-600 hover:underline uppercase tracking-wide">Bayar Pas</button>
                                    </div>
                                    <div class="flex items-center gap-1.5 border-b border-primary-500 pb-0.5">
                                        <span class="text-sm font-bold text-primary-600">Rp</span>
                                        <input type="text" inputmode="numeric" :value="format(nominal)"
                                            @input="
                                                let raw = $event.target.value.replace(/\D/g, '');
                                                nominal = raw ? raw : '';
                                                $el.value = format(raw);
                                            "
                                            @focus="$event.target.select()"
                                            class="w-full bg-transparent border-none p-0 text-lg font-black focus:ring-0 dark:text-white" placeholder="0" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100/50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button type="button" wire:click="$set('sisa_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TUNAI }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $sisa_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TUNAI ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Tunai
                                    </button>
                                    <button type="button" wire:click="$set('sisa_payment_method', '{{ \App\Models\PembelianMetodePembayaran::METODE_TRANSFER }}')"
                                        class="py-1.5 rounded-md text-[10px] font-bold uppercase transition-all text-center {{ $sisa_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER ? 'bg-white dark:bg-gray-700 shadow-sm text-primary-600 font-black' : 'text-gray-500' }}">
                                        Transfer
                                    </button>
                                </div>

                                @if ($sisa_payment_method === \App\Models\PembelianMetodePembayaran::METODE_TRANSFER)
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Rekening Tujuan</label>
                                        <select wire:model="sisa_rekening_perusahaan_id"
                                            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 cursor-pointer dark:text-white">
                                            <option value="">Pilih Rekening...</option>
                                            @foreach ($rekeningPerusahaan as $rek)
                                                <option value="{{ $rek->id }}">{{ $rek->atas_nama }} | {{ $rek->nama_bank }} | {{ $rek->no_rekening }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <input type="text" wire:model="sisa_reference_number" placeholder="No. Bukti / Ref (opsional)"
                                    class="w-full p-2 text-xs font-bold bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none focus:ring-2 focus:ring-primary-500/10 transition-all" />

                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1">Foto Bukti / Surat Jalan (opsional)</label>
                                    <input type="file" wire:model="sisa_foto" multiple accept="image/*"
                                        class="w-full p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none" />
                                    <div wire:loading wire:target="sisa_foto" class="text-[10px] text-gray-400 mt-1">Mengupload...</div>
                                    @foreach ($sisa_foto as $fIdx => $f)
                                        <div class="relative inline-block mt-2 mr-2 group">
                                            <img src="{{ $f->temporaryUrl() }}" class="h-16 rounded border border-gray-200 dark:border-gray-700" />
                                            <button type="button" wire:click.prevent.stop="removeFoto('sisa_foto', {{ $fIdx }})"
                                                title="Hapus foto ini"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center bg-black/70 hover:bg-rose-600 text-white rounded-full text-xs leading-none transition-colors">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                                <button wire:click="konfirmasiDatang" wire:confirm="Konfirmasi barang sudah diterima secara fisik? Jurnal Persediaan akan langsung dicatat."
                                    class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm py-2.5 rounded-lg shadow-sm transition-all active:translate-y-0.5 tracking-wide shrink-0">
                                    <x-heroicon-o-truck class="w-4 h-4 stroke-[3px]" />
                                    KONFIRMASI BARANG DATANG
                                </button>
                            </div>
                            @endif
                        @else
                            {{-- BAYAR_DIMUKA: sudah lunas 100% sejak awal, tidak ada
                                 pilihan lain selain konfirmasi barang datang. --}}
                            <div class="p-4 lg:p-5 space-y-3">
                                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800/50">
                                    <p class="text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed">
                                        Pembelian ini sudah <strong>lunas</strong> dibayar dimuka.
                                        Menekan tombol di bawah akan mencatat <strong>Persediaan</strong> masuk gudang dan
                                        membalik akun <strong>Uang Muka Pembelian</strong> — pastikan barang memang sudah
                                        benar-benar fisik diterima sebelum konfirmasi.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">Tanggal Barang Datang</label>
                                    <input type="date" wire:model="sisa_tanggal"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider ml-1">No. Nota / Surat Jalan</label>
                                    <input type="text" wire:model="sisa_nomor_nota" placeholder="Isi/perbaiki nomor surat jalan asli dari supplier"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-primary-500/10 dark:text-white" />
                                    <p class="text-[9.5px] text-gray-500 dark:text-gray-400 leading-snug px-0.5">
                                        Boleh diperbaiki di sini kalau saat nota dibuat suratnya belum ada.
                                        Jurnal kedatangan barang akan memakai nomor yang tertulis di sini.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1">Foto Bukti / Surat Jalan (opsional)</label>
                                    <input type="file" wire:model="sisa_foto" multiple accept="image/*"
                                        class="w-full p-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg dark:text-white outline-none" />
                                    <div wire:loading wire:target="sisa_foto" class="text-[10px] text-gray-400 mt-1">Mengupload...</div>
                                    @foreach ($sisa_foto as $fIdx => $f)
                                        <div class="relative inline-block mt-2 mr-2 group">
                                            <img src="{{ $f->temporaryUrl() }}" class="h-16 rounded border border-gray-200 dark:border-gray-700" />
                                            <button type="button" wire:click.prevent.stop="removeFoto('sisa_foto', {{ $fIdx }})"
                                                title="Hapus foto ini"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center bg-black/70 hover:bg-rose-600 text-white rounded-full text-xs leading-none transition-colors">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/20">
                                <button wire:click="konfirmasiDatang" wire:confirm="Konfirmasi barang sudah diterima secara fisik? Jurnal Persediaan akan langsung dicatat."
                                    class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm py-2.5 rounded-lg shadow-sm transition-all active:translate-y-0.5 tracking-wide shrink-0">
                                    <x-heroicon-o-truck class="w-4 h-4 stroke-[3px]" />
                                    KONFIRMASI BARANG DATANG
                                </button>
                            </div>
                        @endif
                    @endif
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-filament::page>