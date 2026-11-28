<x-filament-panels::page>
    @php
        $data = $this->neracaSaldoData;
        $indukAkuns = $data['indukAkuns'];
        $totalDebet = $data['total_debet'];
        $totalKredit = $data['total_kredit'];
        $selisih = $data['selisih'];
        $isBalanced = $data['is_balanced'];
        $tanggalLabel = \Illuminate\Support\Carbon::parse($data['tanggal'])->locale('id')->isoFormat('D MMMM Y');
    @endphp

    @push('styles')
        <style>
            :root {
                --tree-font-body: 'DM Sans', sans-serif;
                --tree-font-mono: 'IBM Plex Mono', monospace;
                --tree-card-bg: #ffffff;
                --tree-border: #e4e7f0;
                --tree-line: #d1d5e8;
                --tree-text-primary: #1a1d2e;
                --tree-text-secondary: #5a6080;
                --tree-text-muted: #9aa0bb;
                --tree-hover: #f1f3fc;
                --tree-active: #eef0ff;
                --lvl-0-accent: #4f46e5;
                --lvl-1-accent: #0891b2;
                --lvl-2-accent: #7c3aed;
                --lvl-3-accent: #059669;
                --lvl-4-accent: #d97706;
                --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            }

            .dark {
                --tree-card-bg: #161822;
                --tree-border: #252838;
                --tree-line: #2e3249;
                --tree-text-primary: #e8eaf4;
                --tree-text-secondary: #9aa0bb;
                --tree-text-muted: #5a6080;
                --tree-hover: #1c1f2e;
                --tree-active: #1e2140;
                --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            }

            .tree-wrapper {
                font-family: var(--tree-font-body);
                color: var(--tree-text-primary);
            }

            .tree-toolbar {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .tree-search {
                flex: 1;
                min-width: 200px;
                position: relative;
            }

            .tree-search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--tree-text-muted);
                pointer-events: none;
            }

            .tree-search input {
                width: 100%;
                padding: 8px 14px 8px 36px;
                background: var(--tree-card-bg);
                border: 1.5px solid var(--tree-border);
                border-radius: 10px;
                font-size: 13px;
                color: var(--tree-text-primary);
                box-shadow: var(--shadow-sm);
                outline: none;
            }

            .tree-search input:focus {
                border-color: var(--lvl-0-accent);
            }

            .tree-action-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 12px;
                border-radius: 9px;
                font-size: 12.5px;
                font-weight: 500;
                cursor: pointer;
                border: 1.5px solid var(--tree-border);
                background: var(--tree-card-bg);
                color: var(--tree-text-secondary);
                box-shadow: var(--shadow-sm);
                white-space: nowrap;
            }

            .tree-action-btn:hover {
                border-color: var(--lvl-0-accent);
                color: var(--lvl-0-accent);
                background: var(--tree-active);
            }

            .ns-header-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 16px;
                margin-bottom: 8px;
                background: var(--tree-card-bg);
                border: 1.5px solid var(--tree-border);
                border-radius: 10px;
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--tree-text-secondary);
            }

            .ns-cols {
                display: grid;
                grid-template-columns: 150px 150px;
                gap: 12px;
                margin-left: auto;
                flex-shrink: 0;
                text-align: right;
            }

            .ns-col-val {
                font-family: var(--tree-font-mono);
                font-size: 12.5px;
                font-weight: 600;
                white-space: nowrap;
            }

            .ns-col-val.zero {
                color: var(--tree-text-muted);
                font-weight: 400;
            }

            .tree-container {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .tree-induk-card {
                background: var(--tree-card-bg);
                border: 1.5px solid var(--tree-border);
                border-radius: 14px;
                overflow: hidden;
                box-shadow: var(--shadow-sm);
            }

            .tree-induk-header {
                display: flex;
                align-items: center;
                cursor: pointer;
                transition: background 0.15s;
                padding-right: 16px;
            }

            .tree-induk-header:hover {
                background: var(--tree-hover);
            }

            .tree-induk-header[aria-expanded="true"] {
                background: var(--tree-active);
            }

            .tree-induk-strip {
                width: 5px;
                align-self: stretch;
                background: var(--lvl-0-accent);
                flex-shrink: 0;
            }

            .tree-induk-content {
                flex: 1;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                min-width: 0;
            }

            .tree-chevron {
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 5px;
                background: rgba(79, 70, 229, 0.1);
                color: var(--lvl-0-accent);
                flex-shrink: 0;
                transition: transform 0.2s ease;
            }

            [aria-expanded="true"] .tree-chevron {
                transform: rotate(90deg);
            }

            .tree-code {
                font-family: var(--tree-font-mono);
                font-size: 11.5px;
                font-weight: 600;
                padding: 3px 8px;
                border-radius: 6px;
                background: rgba(79, 70, 229, 0.08);
                color: var(--lvl-0-accent);
                flex-shrink: 0;
            }

            .tree-name {
                font-size: 14px;
                font-weight: 600;
                color: var(--tree-text-primary);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .tree-children-wrap {
                display: none;
                border-top: 1.5px solid var(--tree-border);
            }

            .tree-children-wrap.open {
                display: block;
            }

            .tree-body {
                padding: 8px 16px 8px 20px;
                display: flex;
                flex-direction: column;
                gap: 3px;
            }

            .tree-node {
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .tree-node::before {
                content: '';
                position: absolute;
                left: -12px;
                top: 0;
                bottom: 0;
                width: 1.5px;
                background: var(--tree-line);
            }

            .tree-node:last-child::before {
                height: 20px;
            }

            .tree-node::after {
                content: '';
                position: absolute;
                left: -12px;
                top: 18px;
                width: 10px;
                height: 1.5px;
                background: var(--tree-line);
            }

            .tree-node-row {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 7px 0 7px 8px;
                border-radius: 8px;
                cursor: default;
                border-left: 2.5px solid transparent;
            }

            .tree-node-row.clickable {
                cursor: pointer;
            }

            .tree-node-row:hover {
                background: var(--tree-hover);
            }

            .tree-node-row.open-row {
                background: var(--tree-active);
            }

            .tree-node-code {
                font-family: var(--tree-font-mono);
                font-size: 11px;
                font-weight: 600;
                padding: 2px 7px;
                border-radius: 5px;
                flex-shrink: 0;
            }

            .tree-node-name {
                font-size: 13px;
                font-weight: 500;
                color: var(--tree-text-primary);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                flex: 1;
            }

            .level-1 .tree-node-code { background: rgba(8, 145, 178, 0.1); color: var(--lvl-1-accent); }
            .level-1 .tree-chevron { background: rgba(8, 145, 178, 0.1); color: var(--lvl-1-accent); }
            .level-2 .tree-node-code { background: rgba(124, 58, 237, 0.1); color: var(--lvl-2-accent); }
            .level-2 .tree-chevron { background: rgba(124, 58, 237, 0.1); color: var(--lvl-2-accent); }
            .level-3 .tree-node-code { background: rgba(5, 150, 105, 0.1); color: var(--lvl-3-accent); }
            .level-3 .tree-chevron { background: rgba(5, 150, 105, 0.1); color: var(--lvl-3-accent); }
            .level-4 .tree-node-code { background: rgba(217, 119, 6, 0.1); color: var(--lvl-4-accent); }
            .level-4 .tree-chevron { background: rgba(217, 119, 6, 0.1); color: var(--lvl-4-accent); }

            .tree-node-children {
                padding-left: 20px;
                padding-top: 3px;
                padding-bottom: 2px;
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .tree-node-children.collapsed {
                display: none;
            }

            .tree-leaf-row {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 6px 0 6px 8px;
                border-radius: 7px;
            }

            .tree-leaf-row:hover {
                background: var(--tree-hover);
            }

            .tree-leaf-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: var(--tree-text-muted);
                flex-shrink: 0;
            }

            .tree-leaf-code {
                font-family: var(--tree-font-mono);
                font-size: 10.5px;
                color: var(--tree-text-muted);
                flex-shrink: 0;
            }

            .tree-leaf-name {
                font-size: 12.5px;
                color: var(--tree-text-secondary);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                flex: 1;
            }

            .ns-total-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 12px;
                padding: 14px 16px;
                background: var(--tree-card-bg);
                border: 2px solid var(--tree-border);
                border-radius: 12px;
                font-weight: 700;
                box-shadow: var(--shadow-sm);
            }

            @media (max-width: 640px) {
                .ns-cols {
                    grid-template-columns: 100px 100px;
                    gap: 8px;
                }
            }

            .barang-group-title {
                font-size: 12.5px;
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--tree-text-secondary, #5a6080);
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .barang-empty-state {
                text-align: center;
                padding: 24px;
                font-size: 13px;
                color: var(--tree-text-muted, #9aa0bb);
            }
            .barang-list-stok {
                font-family: var(--tree-font-mono);
                font-size: 12px;
                font-weight: 600;
                color: var(--tree-text-primary, #1a1d2e);
                flex-shrink: 0;
                white-space: nowrap;
            }
            .barang-list-stok.positif { color: #16a34a; }
            .barang-list-stok.negatif { color: #dc2626; }
            .barang-list-stok.nol { color: var(--tree-text-muted, #9aa0bb); }
            .pembeli-toolbar {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
                margin-bottom: 12px;
            }
            .pembeli-toolbar input[type="text"] {
                flex: 1;
                min-width: 180px;
                padding: 7px 12px;
                background: var(--tree-card-bg);
                border: 1.5px solid var(--tree-border);
                border-radius: 9px;
                font-size: 13px;
                color: var(--tree-text-primary);
                outline: none;
            }
            .pembeli-toolbar label {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 12px;
                color: var(--tree-text-secondary);
                cursor: pointer;
                white-space: nowrap;
            }
            .pembeli-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 8px;
                max-height: 65vh;
                overflow-y: auto;
                padding-right: 4px;
            }
            .pembeli-card {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 10px 12px;
                border: 1px solid var(--tree-border);
                border-radius: 10px;
                background: var(--tree-card-bg);
                cursor: pointer;
                transition: background 0.12s, border-color 0.12s;
            }
            .pembeli-card:hover {
                background: var(--tree-hover);
                border-color: var(--lvl-1-accent);
            }
            .pembeli-card-top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                font-size: 10.5px;
                color: var(--tree-text-muted);
            }
            .pembeli-card-top .telat { color: #f87171; }
            .pembeli-card-nama {
                font-size: 13px;
                font-weight: 500;
                color: var(--tree-text-primary);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pembeli-card-saldo {
                font-family: var(--tree-font-mono);
                font-size: 13px;
                font-weight: 600;
                text-align: right;
            }
        </style>
    @endpush

    <div class="tree-wrapper">
        {{-- Filter & Toolbar --}}
        <div class="tree-toolbar">
            <input
                type="date"
                wire:model.live="tanggalAkhir"
                title="Per Tanggal"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            />

            <div class="tree-search">
                <span class="tree-search-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                </span>
                <input type="text" id="treeSearch" placeholder="Cari kode atau nama akun..."
                    oninput="treeSearch(this.value)" autocomplete="off" />
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model.live="tampilkanSaldoNol"
                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800" />
                <span>Tampilkan saldo 0</span>
            </label>

            <button type="button" class="tree-action-btn" onclick="treeExpandAll()">Expand Semua</button>
            <button type="button" class="tree-action-btn" onclick="treeCollapseAll()">Collapse Semua</button>

            @if ($isBalanced)
                <span class="inline-flex items-center rounded-full bg-success-50 px-3 py-1 text-xs font-semibold text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-950/50 dark:text-success-400">
                    SEIMBANG (BALANCE)
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-danger-50 px-3 py-1 text-xs font-semibold text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-950/50 dark:text-danger-400">
                    TIDAK SEIMBANG &middot; Selisih: Rp {{ number_format($selisih, 0, ',', '.') }}
                </span>
            @endif
        </div>

        {{-- Header Kolom --}}
        <div class="ns-header-bar">
            <span>Neraca Saldo &middot; Per {{ $tanggalLabel }}</span>
            <div class="ns-cols">
                <span>Debet (Rp)</span>
                <span>Kredit (Rp)</span>
            </div>
        </div>

        {{-- Tree Grouping COA --}}
        <div class="tree-container" id="treeContainer"
            wire:key="neraca-tree-{{ $data['tanggal'] }}-{{ $this->tampilkanSaldoNol ? '1' : '0' }}"
            wire:ignore>
            @foreach ($indukAkuns as $induk)
                @php
                    $indukDebet = (float) ($induk->ns_debet ?? 0);
                    $indukKredit = (float) ($induk->ns_kredit ?? 0);
                    $indukHasSaldo = $indukDebet > 0 || $indukKredit > 0;
                @endphp

                @if ($this->tampilkanSaldoNol || $indukHasSaldo)
                    <div class="tree-induk-card" id="induk-{{ $induk->id }}">
                        <div class="tree-induk-header" aria-expanded="false" onclick="toggleInduk(this)"
                            data-id="{{ $induk->id }}"
                            data-search="{{ strtolower($induk->kode_induk_akun . ' ' . $induk->nama_induk_akun) }}">
                            <div class="tree-induk-strip"></div>
                            <div class="tree-induk-content">
                                <span class="tree-chevron">
                                    <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                                        <path d="M3 2l4 3-4 3V2z" />
                                    </svg>
                                </span>
                                <span class="tree-code">{{ $induk->kode_induk_akun }}</span>
                                <span class="tree-name">
                                    {{ $induk->nama_induk_akun }}
                                    @if (($induk->buku_pembantu_count ?? 0) > 0)
                                        <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:9999px; margin-left:6px; background:rgba(251,191,36,0.15); color:#f59e0b;">
                                            Buku Pembantu ({{ $induk->buku_pembantu_count }})
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="ns-cols">
                                <span class="ns-col-val {{ $indukDebet > 0 ? 'has-val' : 'zero' }}">
                                    {{ $indukDebet > 0 ? number_format($indukDebet, 0, ',', '.') : '-' }}
                                </span>
                                <span class="ns-col-val {{ $indukKredit > 0 ? 'has-val' : 'zero' }}">
                                    {{ $indukKredit > 0 ? number_format($indukKredit, 0, ',', '.') : '-' }}
                                </span>
                            </div>
                        </div>

                        @if ($induk->anakAkuns->whereNull('parent')->isNotEmpty())
                            <div class="tree-children-wrap" id="children-induk-{{ $induk->id }}">
                                <div class="tree-body">
                                    @foreach ($induk->anakAkuns->whereNull('parent') as $anak)
                                        @include('filament.components._neraca_saldo_node', [
                                            'node' => $anak,
                                            'level' => 1,
                                            'tampilkanSaldoNol' => $this->tampilkanSaldoNol,
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Grand Total --}}
        <div class="ns-total-bar">
            <span class="text-sm uppercase tracking-wider">Total Neraca Saldo</span>
            <div class="ns-cols">
                <span class="ns-col-val">Rp {{ number_format($totalDebet, 0, ',', '.') }}</span>
                <span class="ns-col-val">Rp {{ number_format($totalKredit, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- MODAL LEVEL 1: Buku Pembantu Piutang/Utang/Campuran --}}
    <x-filament::modal id="piutang-pembeli-modal" width="5xl">
        <x-slot name="heading">
            Buku Pembantu {{ $this->selectedBukuIsCampuran ? '' : ($this->selectedBukuIsUtang ? 'Utang' : 'Piutang') }}
            @if ($this->selectedPiutangSubAkun)
                <span style="font-weight:400; color:var(--tree-text-muted,#9aa0bb);">
                    — {{ $this->selectedPiutangSubAkun->kode_sub_anak_akun }}
                    {{ $this->selectedPiutangSubAkun->nama_sub_anak_akun }}
                </span>
            @endif
        </x-slot>

        @php
            $pembeliList = $this->piutangPembeliList->sortByDesc('saldo')->values();
            $isCampuranBuku = $this->selectedBukuIsCampuran;
            $labelPihak = $isCampuranBuku ? 'Supplier / Pembeli' : ($this->selectedBukuIsUtang ? 'Supplier' : 'Pembeli');
            $labelBuku = $isCampuranBuku ? 'Titipan' : ($this->selectedBukuIsUtang ? 'Utang' : 'Piutang');
        @endphp

        @if ($pembeliList->isEmpty())
            <div class="barang-empty-state">
                Belum ada transaksi {{ strtolower($labelBuku) }} yang terposting di akun ini.
            </div>
        @else
            <div x-data="{
                q: '',
                sembunyiNol: false,
                cocok(nama, adaSaldo) {
                    return (!this.sembunyiNol || adaSaldo) && nama.includes(this.q.toLowerCase().trim());
                }
            }">
                <div class="barang-group-title">Saldo per {{ $labelPihak }} ({{ $pembeliList->count() }})</div>

                <div class="pembeli-toolbar">
                    <input type="text" x-model="q" placeholder="Cari nama {{ strtolower($labelPihak) }}..."
                        autocomplete="off">
                    <label>
                        <input type="checkbox" x-model="sembunyiNol">
                        Sembunyikan saldo 0
                    </label>
                </div>

                <div class="pembeli-grid">
                    @foreach ($pembeliList as $p)
                        @php
                            $saldoClass = $p->saldo > 0 ? 'positif' : ($p->saldo < 0 ? 'negatif' : 'nol');
                            $telat = $p->saldo > 0 && $p->hari_sejak_terakhir !== null && $p->hari_sejak_terakhir >= 30;
                        @endphp
                        <div class="pembeli-card"
                            x-show="cocok(@js(strtolower($p->nama)), {{ $p->saldo != 0 ? 'true' : 'false' }})"
                            wire:click="openPiutangDetail('{{ $p->key }}')">
                            <div class="pembeli-card-top">
                                <span>{{ $p->jumlah_transaksi }}x transaksi</span>
                                @if ($p->saldo > 0 && $p->hari_sejak_terakhir !== null)
                                    <span class="{{ $telat ? 'telat' : '' }}">{{ $p->hari_sejak_terakhir }} hari lalu</span>
                                @endif
                            </div>
                            <div class="pembeli-card-nama" title="{{ $labelBuku }} {{ $p->nama }}">
                                {{ $isCampuranBuku ? '' : $labelBuku.' ' }}{{ $p->nama }}
                                @if ($isCampuranBuku && $p->jenis_pihak)
                                    <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:9999px; margin-left:4px;
                                        background:{{ $p->jenis_pihak === 'Supplier' ? 'rgba(96,165,250,0.15)' : 'rgba(52,211,153,0.15)' }};
                                        color:{{ $p->jenis_pihak === 'Supplier' ? '#60a5fa' : '#34d399' }};">{{ $p->jenis_pihak }}</span>
                                @endif
                            </div>
                            <div class="pembeli-card-saldo barang-list-stok {{ $saldoClass }}">
                                Rp {{ number_format($p->saldo, 0, ',', '.') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::modal>

    {{-- MODAL LEVEL 2: Kartu Piutang/Utang --}}
    <x-filament::modal id="piutang-detail-modal" width="4xl">
        <x-slot name="heading">
            Kartu {{ $this->selectedBukuIsCampuran ? 'Titipan' : ($this->selectedBukuIsUtang ? 'Utang' : 'Piutang') }}
            @if ($this->selectedPembeliRingkasan)
                <span style="font-weight:400; color:var(--tree-text-muted,#9aa0bb);">
                    — {{ $this->selectedPembeliRingkasan->nama }}
                </span>
            @endif
        </x-slot>

        @php
            $transaksi = $this->piutangDetailTransaksi;
            $ringkasan = $this->selectedPembeliRingkasan;
        @endphp

        @if ($ringkasan)
            <div style="display:flex; gap:16px; margin-bottom:12px; font-size:12px; color:var(--tree-text-muted,#9aa0bb); flex-wrap:wrap; align-items:center;">
                <span>Total Debit: <strong style="color:#34d399;">Rp {{ number_format($ringkasan->debit, 0, ',', '.') }}</strong></span>
                <span>Total Kredit: <strong style="color:#f87171;">Rp {{ number_format($ringkasan->kredit, 0, ',', '.') }}</strong></span>
                <span>Sisa {{ $this->selectedBukuIsCampuran ? 'Titipan' : ($this->selectedBukuIsUtang ? 'Utang' : 'Piutang') }}: <strong>Rp {{ number_format($ringkasan->saldo, 0, ',', '.') }}</strong></span>
                @if ($ringkasan->saldo > 0 && $ringkasan->hari_sejak_terakhir !== null)
                    <span style="padding:2px 8px; border-radius:9999px; font-weight:600;
                        background:{{ $ringkasan->hari_sejak_terakhir >= 30 ? 'rgba(248,113,113,0.15)' : 'rgba(154,160,187,0.15)' }};
                        color:{{ $ringkasan->hari_sejak_terakhir >= 30 ? '#f87171' : '#9aa0bb' }};">
                        {{ $ringkasan->hari_sejak_terakhir }} hari sejak transaksi terakhir
                    </span>
                @endif
            </div>
        @endif

        @if ($transaksi->isEmpty())
            <div class="barang-empty-state">
                Tidak ada rincian transaksi untuk {{ $this->selectedBukuIsCampuran ? 'pihak' : ($this->selectedBukuIsUtang ? 'supplier' : 'pembeli') }} ini.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead>
                        <tr style="text-align:left; color:var(--tree-text-muted,#9aa0bb); border-bottom:1px solid rgba(255,255,255,0.08);">
                            <th style="padding:6px 8px;">Tanggal</th>
                            <th style="padding:6px 8px;">No. SJ / Nota</th>
                            <th style="padding:6px 8px;">Keterangan</th>
                            <th style="padding:6px 8px; text-align:right;">Debit</th>
                            <th style="padding:6px 8px; text-align:right;">Kredit</th>
                            <th style="padding:6px 8px; text-align:right;">Saldo Berjalan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaksi as $t)
                            <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                <td style="padding:6px 8px; white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($t->tgl)->format('d/m/Y') }}</td>
                                <td style="padding:6px 8px; white-space:nowrap;">{{ $t->no_dokumen ?: '-' }}</td>
                                <td style="padding:6px 8px;">{{ $t->keterangan ?: '-' }}</td>
                                <td style="padding:6px 8px; text-align:right; color:#34d399;">
                                    {{ $t->debit > 0 ? number_format($t->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td style="padding:6px 8px; text-align:right; color:#f87171;">
                                    {{ $t->kredit > 0 ? number_format($t->kredit, 0, ',', '.') : '-' }}
                                </td>
                                <td style="padding:6px 8px; text-align:right; font-weight:600;">
                                    {{ number_format($t->saldo_berjalan, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::modal>

    @push('scripts')
        <script>
            function toggleInduk(el) {
                const expanded = el.getAttribute('aria-expanded') === 'true';
                const id = el.dataset.id;
                const children = document.getElementById('children-induk-' + id);
                el.setAttribute('aria-expanded', String(!expanded));
                if (children) children.classList.toggle('open', !expanded);
            }

            function toggleNode(rowEl) {
                const node = rowEl.closest('.tree-node');
                const childrenEl = node.querySelector(':scope > .tree-node-children');
                if (!childrenEl) return;
                const isOpen = !childrenEl.classList.contains('collapsed');
                childrenEl.classList.toggle('collapsed', isOpen);
                rowEl.classList.toggle('open-row', !isOpen);
                const chevron = rowEl.querySelector('.tree-chevron');
                if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(90deg)';
            }

            function treeExpandAll() {
                document.querySelectorAll('.tree-induk-header').forEach(h => h.setAttribute('aria-expanded', 'true'));
                document.querySelectorAll('.tree-children-wrap').forEach(el => el.classList.add('open'));
                document.querySelectorAll('.tree-node-children').forEach(el => el.classList.remove('collapsed'));
                document.querySelectorAll('.tree-node-row.clickable').forEach(el => {
                    el.classList.add('open-row');
                    const chevron = el.querySelector('.tree-chevron');
                    if (chevron) chevron.style.transform = 'rotate(90deg)';
                });
            }

            function treeCollapseAll() {
                document.querySelectorAll('.tree-induk-header').forEach(h => h.setAttribute('aria-expanded', 'false'));
                document.querySelectorAll('.tree-children-wrap').forEach(el => el.classList.remove('open'));
                document.querySelectorAll('.tree-node-children').forEach(el => el.classList.add('collapsed'));
                document.querySelectorAll('.tree-node-row').forEach(el => {
                    el.classList.remove('open-row');
                    const chevron = el.querySelector('.tree-chevron');
                    if (chevron) chevron.style.transform = '';
                });
            }

            let searchTimeout;
            function treeSearch(query) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => doSearch(query), 200);
            }

            function doSearch(query) {
                const q = query.toLowerCase().trim();
                const container = document.getElementById('treeContainer');
                if (!q) {
                    container.querySelectorAll('.tree-induk-card').forEach(c => c.style.display = '');
                    treeCollapseAll();
                    return;
                }

                container.querySelectorAll('.tree-induk-card').forEach(card => card.style.display = 'none');

                const showCard = (card, indukHeader) => {
                    card.style.display = '';
                    const id = indukHeader.dataset.id;
                    indukHeader.setAttribute('aria-expanded', 'true');
                    const ch = document.getElementById('children-induk-' + id);
                    if (ch) ch.classList.add('open');
                };

                container.querySelectorAll('[data-search]').forEach(el => {
                    if (el.getAttribute('data-search').includes(q)) {
                        const card = el.closest('.tree-induk-card');
                        if (card) showCard(card, el);
                    }
                });

                container.querySelectorAll('[data-node-search]').forEach(el => {
                    if (el.getAttribute('data-node-search').includes(q)) {
                        const card = el.closest('.tree-induk-card');
                        const header = card?.querySelector('.tree-induk-header');
                        if (card && header) showCard(card, header);

                        let parent = el.closest('.tree-node-children');
                        while (parent) {
                            parent.classList.remove('collapsed');
                            const row = parent.previousElementSibling;
                            if (row) {
                                row.classList.add('open-row');
                                const chevron = row.querySelector('.tree-chevron');
                                if (chevron) chevron.style.transform = 'rotate(90deg)';
                            }
                            parent = parent.closest('.tree-node')?.closest('.tree-node-children');
                        }
                    }
                });
            }
        </script>
    @endpush
</x-filament-panels::page>
