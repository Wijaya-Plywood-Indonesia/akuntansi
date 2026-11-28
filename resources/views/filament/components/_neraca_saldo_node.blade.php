@php
    $children = $node->relationLoaded('children') ? $node->children : collect();
    $subAkuns = $node->relationLoaded('subAnakAkuns') ? $node->subAnakAkuns : collect();
    $hasChildren = $children->isNotEmpty();
    $hasSub = $subAkuns->isNotEmpty();
    $hasAny = $hasChildren || $hasSub;
    $nodeId = 'ns-node-' . $node->id . '-' . $level;

    $nodeDebet = (float) ($node->ns_debet ?? 0);
    $nodeKredit = (float) ($node->ns_kredit ?? 0);
    $nodeHasSaldo = $nodeDebet > 0 || $nodeKredit > 0;
@endphp

@if ($tampilkanSaldoNol || $nodeHasSaldo)
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
                @if (($node->buku_pembantu_count ?? 0) > 0)
                    <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:9999px; margin-left:6px; background:rgba(251,191,36,0.15); color:#f59e0b;">
                        Buku Pembantu ({{ $node->buku_pembantu_count }})
                    </span>
                @endif
            </span>

            <div class="ns-cols">
                <span class="ns-col-val {{ $nodeDebet > 0 ? 'has-val' : 'zero' }}">
                    {{ $nodeDebet > 0 ? number_format($nodeDebet, 0, ',', '.') : '-' }}
                </span>
                <span class="ns-col-val {{ $nodeKredit > 0 ? 'has-val' : 'zero' }}">
                    {{ $nodeKredit > 0 ? number_format($nodeKredit, 0, ',', '.') : '-' }}
                </span>
            </div>
        </div>

        @if ($hasAny)
            <div class="tree-node-children collapsed">
                @foreach ($children as $child)
                    @include('filament.components._neraca_saldo_node', [
                        'node' => $child,
                        'level' => $level + 1,
                        'tampilkanSaldoNol' => $tampilkanSaldoNol,
                    ])
                @endforeach

                @foreach ($subAkuns as $sub)
                    @php
                        $subDebet = (float) ($sub->ns_debet ?? 0);
                        $subKredit = (float) ($sub->ns_kredit ?? 0);
                        $subHasSaldo = $subDebet > 0 || $subKredit > 0;
                        $isUtang = $sub->tipe_buku_pembantu === 'utang';
                        $isCampuran = $sub->tipe_buku_pembantu === 'campuran';
                        $isPiutang = $sub->tipe_buku_pembantu === 'piutang' || $isUtang || $isCampuran;
                    @endphp
                    @if ($tampilkanSaldoNol || $subHasSaldo)
                        <div class="tree-node" data-node-search="{{ strtolower($sub->kode_sub_anak_akun . ' ' . $sub->nama_sub_anak_akun) }}">
                            <div class="tree-leaf-row"
                                @if ($isPiutang) style="cursor:pointer" wire:click="openPiutangModal({{ $sub->id }})" @endif>
                                <span class="tree-leaf-dot"></span>
                                <span class="tree-leaf-code">{{ $sub->kode_sub_anak_akun }}</span>
                                <span class="tree-leaf-name" title="{{ $sub->nama_sub_anak_akun }}">
                                    {{ $sub->nama_sub_anak_akun }}
                                    @if ($isPiutang)
                                        <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:9999px; margin-left:6px; background:rgba(251,191,36,0.15); color:#f59e0b;">
                                            {{ $isCampuran ? 'Campuran' : ($isUtang ? 'Utang' : 'Piutang') }}
                                        </span>
                                    @endif
                                </span>
                                <div class="ns-cols">
                                    <span class="ns-col-val {{ $subDebet > 0 ? 'has-val' : 'zero' }}">
                                        {{ $subDebet > 0 ? number_format($subDebet, 0, ',', '.') : '-' }}
                                    </span>
                                    <span class="ns-col-val {{ $subKredit > 0 ? 'has-val' : 'zero' }}">
                                        {{ $subKredit > 0 ? number_format($subKredit, 0, ',', '.') : '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif
