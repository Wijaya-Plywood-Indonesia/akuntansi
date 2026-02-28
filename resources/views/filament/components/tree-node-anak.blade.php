@php
    $hasChildren = $anak->children->isNotEmpty();
    $hasSub = $anak->subAnakAkuns->isNotEmpty();
    $hasAny = $hasChildren || $hasSub;
    $saldo = $anak->saldo_normal;

    // Warna border kiri berdasarkan depth
    $depthColors = [
        2 => 'border-l-4 border-blue-400 dark:border-blue-600',
        3 => 'border-l-4 border-indigo-400 dark:border-indigo-600',
        4 => 'border-l-4 border-purple-400 dark:border-purple-600',
        5 => 'border-l-4 border-pink-400 dark:border-pink-600',
    ];
    $borderClass = $depthColors[$depth] ?? 'border-l-4 border-gray-300';
@endphp

<div data-akun-wrapper class="rounded-lg {{ $borderClass }} bg-gray-50 dark:bg-gray-750 overflow-hidden">

    {{-- Row dengan children --}}
    @if($hasAny)
        <div
            class="tree-toggle-btn flex items-center justify-between px-3 py-2.5 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            onclick="toggleNode(this)"
        >
            <div class="flex items-center gap-2">
                <span class="tree-toggle-icon text-gray-400 font-bold text-sm">▶</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono font-semibold">{{ $anak->kode_anak_akun }}</span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $anak->nama_anak_akun }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if($saldo)
                    <span class="badge-{{ $saldo }}">{{ strtoupper($saldo) }}</span>
                @endif
                <span class="text-xs text-gray-400">
                    {{ $hasChildren ? $anak->children->count().' sub' : '' }}
                    {{ $hasSub ? $anak->subAnakAkuns->count().' detail' : '' }}
                </span>
            </div>
        </div>

        <div class="tree-children border-t border-gray-100 dark:border-gray-700">
            <div class="pl-3 py-1.5 space-y-1">

                {{-- Anak rekursif (parent → child anak_akun) --}}
                @foreach ($anak->children as $child)
                    @include('filament.components.tree-node-anak', ['anak' => $child, 'depth' => $depth + 1])
                @endforeach

                {{-- Sub anak akun --}}
                @foreach ($anak->subAnakAkuns as $sub)
                    @include('filament.components.tree-node-sub', ['sub' => $sub])
                @endforeach

            </div>
        </div>

    @else
        {{-- Leaf node --}}
        <div
            data-akun-search="{{ strtolower($anak->kode_anak_akun . ' ' . $anak->nama_anak_akun) }}"
            class="akun-row flex items-center justify-between px-3 py-2.5"
        >
            <div class="flex items-center gap-2">
                <span class="w-3"></span>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $anak->kode_anak_akun }}</span>
                <span class="text-sm text-gray-700 dark:text-gray-200">{{ $anak->nama_anak_akun }}</span>
            </div>
            @if($saldo)
                <span class="badge-{{ $saldo }}">{{ strtoupper($saldo) }}</span>
            @endif
        </div>
    @endif

</div>