@php
    $hasChildren = $induk->anakAkuns->isNotEmpty();
    $saldo = $induk->saldo_normal;
@endphp

<div data-akun-wrapper class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    {{-- Header Induk --}}
    @if($hasChildren)
        <div
            class="tree-toggle-btn flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            onclick="toggleNode(this)"
        >
            <div class="flex items-center gap-3">
                <span class="tree-toggle-icon text-gray-400 dark:text-gray-500 font-bold text-base">▶</span>
                <span class="font-bold text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $induk->kode_induk_akun }}</span>
                <span class="font-bold text-gray-800 dark:text-gray-100">{{ $induk->nama_induk_akun }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if($saldo)
                    <span class="badge-{{ $saldo }}">{{ strtoupper($saldo) }}</span>
                @endif
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $induk->anakAkuns->count() }} akun</span>
            </div>
        </div>

        <div class="tree-children border-t border-gray-100 dark:border-gray-700">
            <div class="pl-4 py-2 space-y-1">
                @foreach ($induk->anakAkuns as $anak)
                    @include('filament.components.tree-node-anak', ['anak' => $anak, 'depth' => 2])
                @endforeach
            </div>
        </div>

    @else
        <div data-akun-search="{{ strtolower($induk->kode_induk_akun . ' ' . $induk->nama_induk_akun) }}"
             class="akun-row flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="w-4"></span>
                <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $induk->kode_induk_akun }}</span>
                <span class="text-gray-700 dark:text-gray-200">{{ $induk->nama_induk_akun }}</span>
            </div>
            @if($saldo)
                <span class="badge-{{ $saldo }}">{{ strtoupper($saldo) }}</span>
            @endif
        </div>
    @endif

</div>