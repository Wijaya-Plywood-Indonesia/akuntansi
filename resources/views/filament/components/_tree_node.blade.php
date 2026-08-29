@php
    $children = $node->relationLoaded('children') ? $node->children : collect();
    $subAkuns = $node->relationLoaded('subAnakAkuns') ? $node->subAnakAkuns : collect();
    $hasChildren = $children->isNotEmpty();
    $hasSub = $subAkuns->isNotEmpty();
    $hasAny = $hasChildren || $hasSub;
    $saldo = $node->saldo_normal;
    $totalBelow = $children->count() + $subAkuns->count();
    $nodeId = 'node-' . $node->id . '-' . $level;

    // Total barang di anak akun ini + semua turunannya (sudah dihitung
    // sekali di TreeAkunPage::attachBarangCounts, tinggal dibaca di sini).
    $barangTotal = $node->barang_count_total ?? 0;
@endphp

<div class="tree-node level-{{ $level }}" id="{{ $nodeId }}">

    <div class="tree-node-row {{ $hasAny ? 'clickable' : '' }}"
        data-node-search="{{ strtolower($node->kode_anak_akun . ' ' . $node->nama_anak_akun) }}"
        @if ($hasAny) onclick="toggleNode(this)" @endif>
        @if ($hasAny)
            <span class="tree-chevron" style="transition: transform 0.2s ease; flex-shrink:0;">
                <svg width="9" height="9" viewBox="0 0 10 10" fill="currentColor">
                    <path d="M3 2l4 3-4 3V2z" />
                </svg>
            </span>
        @else
            <span style="width:20px; flex-shrink:0;"></span>
        @endif

        <span class="tree-node-code">{{ $node->kode_anak_akun }}</span>

        <span class="tree-node-name" title="{{ $node->nama_anak_akun }}">
            {{ $node->nama_anak_akun }}
        </span>

        <div class="tree-node-meta">
            @if ($saldo)
                <span class="badge badge-{{ $saldo }}">{{ strtoupper(substr($saldo, 0, 2)) }}</span>
            @endif
            @if ($barangTotal > 0)
                <span class="barang-count-pill" title="Total barang di akun ini & turunannya">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" style="flex-shrink:0;">
                        <path
                            d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                        <path d="M3.3 7 12 12l8.7-5M12 22V12" />
                    </svg>
                    {{ $barangTotal }}
                </span>
            @endif
            @if ($totalBelow > 0)
                <span class="count-pill">{{ $totalBelow }}</span>
            @endif
        </div>
    </div>

    @if ($hasAny)
        <div class="tree-node-children collapsed">

            @foreach ($children as $child)
                @include('filament.components._tree_node', [
                    'node' => $child,
                    'level' => $level + 1,
                ])
            @endforeach

            @foreach ($subAkuns as $sub)
                @php $subBarangCount = $sub->barang_count ?? 0; @endphp
                <div class="tree-node level-leaf">
                    <div class="tree-leaf-row" style="cursor:pointer" wire:click="openBarangModal({{ $sub->id }})"
                        data-node-search="{{ strtolower($sub->kode_sub_anak_akun . ' ' . $sub->nama_sub_anak_akun) }}">
                        <span class="tree-leaf-dot"></span>
                        <span class="tree-leaf-code">{{ $sub->kode_sub_anak_akun }}</span>
                        <span class="tree-leaf-name"
                            title="{{ $sub->nama_sub_anak_akun }}">{{ $sub->nama_sub_anak_akun }}</span>
                        <div class="tree-node-meta">
                            @if ($sub->saldo_normal)
                                <span
                                    class="badge badge-{{ $sub->saldo_normal }}">{{ strtoupper(substr($sub->saldo_normal, 0, 2)) }}</span>
                            @endif
                            @if ($subBarangCount > 0)
                                <span class="barang-count-pill" title="Jumlah barang terhubung">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                                        <path
                                            d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                                        <path d="M3.3 7 12 12l8.7-5M12 22V12" />
                                    </svg>
                                    {{ $subBarangCount }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    @endif

</div>
