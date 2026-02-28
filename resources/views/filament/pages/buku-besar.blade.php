<x-filament-panels::page wire:init="initLoad">
    @if($isLoading)
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="flex flex-col items-center gap-4 text-primary-600">
            <svg class="w-10 h-10 animate-spin" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-lg font-semibold">Memuat Buku Besar...</span>
            <span class="text-sm text-gray-500">Sedang menghitung saldo secara rekursif...</span>
        </div>
    </div>
    @else
    <div class="space-y-6">
        {{-- FILTER PERIODE --}}
        <div class="flex justify-end p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium">Periode:</label>
                <input type="month" wire:model.live="filterBulan"
                    class="block text-sm border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
        </div>

@foreach($indukAkuns as $induk)

    @php
        $totalInduk = $induk->anakAkuns
            ->whereNull('parent')
            ->sum(fn($a) => $this->getTotalRecursive($a));

        $hasActivity = $totalInduk != 0;
    @endphp

    @if($hasActivity)
        <div x-data="{ open: true }" 
             class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl">

            {{-- HEADER INDUK --}}
            <div @click="open = !open"
                 class="flex justify-between px-6 py-4 cursor-pointer bg-gray-50 border-b">

                <div class="text-lg font-bold">
                    {{ $induk->kode_induk_akun }} - {{ $induk->nama_induk_akun }}
                </div>

                <div class="text-lg font-extrabold text-primary-600">
                    Rp {{ number_format($totalInduk, 0, ',', '.') }}
                </div>
            </div>

            {{-- LIST ANAK --}}
            <div x-show="open" x-collapse class="p-4 space-y-4">
                @foreach($induk->anakAkuns->whereNull('parent') as $anak)
                    @include('filament.pages.partials.buku-besar-anak', ['akun' => $anak])
                @endforeach
            </div>
        </div>
    @endif

@endforeach
    </div>
    @endif
</x-filament-panels::page>