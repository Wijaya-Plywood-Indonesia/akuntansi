<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <style>
        /* ── Palet warna: default = light mode, di-override di bawah .dark ── */
        .rak-wrap {
            --rak-fg: #1f2328;
            --rak-fg-muted: #6b7280;
            --rak-fg-faint: #9ca3af;
            --rak-border: #e5e7eb;
            --rak-surface: #ffffff;
            --rak-surface-2: #f9fafb;
            --rak-input-bg: #ffffff;

            --rak-in-bg: #ecfdf5;
            --rak-in-fg: #059669;
            --rak-in-fg-strong: #047857;

            --rak-out-bg: #fef2f2;
            --rak-out-fg: #dc2626;
            --rak-out-fg-strong: #b91c1c;

            --rak-end-bg: #eff6ff;
            --rak-end-fg: #2563eb;
            --rak-end-fg-strong: #1d4ed8;

            --rak-accent: #2563eb;
            --rak-accent-fg: #ffffff;
            --rak-accent-soft-bg: #eff6ff;

            --rak-netral-bg: #f3f4f6;
            --rak-netral-fg: #6b7280;

            --rak-warn-bg: #fffbeb;
            --rak-warn-border: #fcd34d;
            --rak-warn-fg: #92400e;

            --rak-error: #dc2626;

            max-width: 780px;
            margin: 0 auto;
            color: var(--rak-fg);
        }

        html.dark .rak-wrap {
            --rak-fg: #f0efec;
            --rak-fg-muted: #a8a7a1;
            --rak-fg-faint: #78766f;
            --rak-border: #2c2c2a;
            --rak-surface: #171715;
            --rak-surface-2: #131311;
            --rak-input-bg: #1c1c1a;

            --rak-in-bg: #04342c;
            --rak-in-fg: #5dcaa5;
            --rak-in-fg-strong: #9fe1cb;

            --rak-out-bg: #501313;
            --rak-out-fg: #f09595;
            --rak-out-fg-strong: #f7c1c1;

            --rak-end-bg: #042c53;
            --rak-end-fg: #85b7eb;
            --rak-end-fg-strong: #b5d4f4;

            --rak-accent: #378add;
            --rak-accent-fg: #042c53;
            --rak-accent-soft-bg: #042c53;

            --rak-netral-bg: #23232066;
            --rak-netral-fg: #a8a7a1;

            --rak-warn-bg: #1c1305;
            --rak-warn-border: #633806;
            --rak-warn-fg: #fac775;

            --rak-error: #f09595;
        }

        .rak-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
        .rak-title { font-size: 19px; font-weight: 600; color: var(--rak-fg); }
        .rak-subtitle { font-size: 13px; color: var(--rak-fg-muted); margin-top: 3px; }
        .rak-period-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .rak-btn {
            font-family: inherit; font-size: 13px; background: var(--rak-surface);
            border: 1px solid var(--rak-border); color: var(--rak-fg); padding: 7px 14px;
            border-radius: 8px; cursor: pointer;
        }
        .rak-btn:hover { background: var(--rak-surface-2); }
        .rak-btn.active { border-color: var(--rak-accent); color: var(--rak-accent); }
        .rak-rangebox {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            background: var(--rak-surface-2); border: 1px solid var(--rak-border); border-radius: 10px;
            padding: 10px 14px; margin-bottom: 1.25rem;
        }
        .rak-rangebox-label { font-size: 12.5px; color: var(--rak-fg-muted); margin-right: 4px; }
        .rak-rangebox input[type="date"] {
            font-family: inherit; font-size: 13px; background: var(--rak-input-bg); border: 1px solid var(--rak-border);
            color: var(--rak-fg); padding: 6px 10px; border-radius: 7px; color-scheme: light;
        }
        html.dark .rak-rangebox input[type="date"] { color-scheme: dark; }
        .rak-primary-btn { background: var(--rak-accent); border: 1px solid var(--rak-accent); color: var(--rak-accent-fg); font-weight: 600; }
        .rak-primary-btn:hover { filter: brightness(1.1); }
        .rak-range-error { font-size: 12px; color: var(--rak-error); }
        .rak-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 1.25rem; }
        @media (max-width: 640px) { .rak-cards { grid-template-columns: repeat(2, 1fr); } }
        .rak-card { background: var(--rak-surface); border: 1px solid var(--rak-border); border-radius: 10px; padding: 1rem; }
        .rak-card.in { background: var(--rak-in-bg); border-color: transparent; }
        .rak-card.out { background: var(--rak-out-bg); border-color: transparent; }
        .rak-card.end { background: var(--rak-end-bg); border-color: transparent; }
        .rak-card-label { font-size: 12.5px; color: var(--rak-fg-muted); }
        .rak-card.in .rak-card-label { color: var(--rak-in-fg); }
        .rak-card.out .rak-card-label { color: var(--rak-out-fg); }
        .rak-card.end .rak-card-label { color: var(--rak-end-fg); }
        .rak-card-value { font-size: 21px; font-weight: 600; margin-top: 4px; color: var(--rak-fg); }
        .rak-card.in .rak-card-value { color: var(--rak-in-fg-strong); }
        .rak-card.out .rak-card-value { color: var(--rak-out-fg-strong); }
        .rak-card.end .rak-card-value { color: var(--rak-end-fg-strong); }
        .rak-flowbox { background: var(--rak-surface-2); border: 1px solid var(--rak-border); border-radius: 12px; padding: 1.1rem 1.25rem; margin-bottom: 1.25rem; }
        .rak-flowbox-title { font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--rak-fg); }
        .rak-flowrow { display: flex; align-items: center; gap: 8px; }
        .rak-flowlabel { font-size: 12px; color: var(--rak-fg-muted); min-width: 80px; }
        .rak-flowlabel.right { text-align: right; }
        .rak-track { flex: 1; height: 10px; background: var(--rak-border); border-radius: 6px; overflow: hidden; display: flex; }
        .rak-track .a { height: 100%; background: var(--rak-accent); }
        .rak-track .d { height: 100%; }
        .rak-deltatext { text-align: center; margin-top: 6px; font-size: 12px; }
        .rak-section-title { font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--rak-fg); }
        .rak-cat-list { display: flex; flex-direction: column; gap: 8px; }
        .rak-cat-row { background: var(--rak-surface-2); border: 1px solid var(--rak-border); border-radius: 8px; overflow: hidden; }
        .rak-cat-head { display: flex; align-items: center; gap: 12px; padding: 12px 14px; cursor: pointer; user-select: none; }
        .rak-cat-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }
        .rak-cat-icon.in { background: var(--rak-in-bg); color: var(--rak-in-fg); }
        .rak-cat-icon.out { background: var(--rak-out-bg); color: var(--rak-out-fg); }
        .rak-cat-icon.netral { background: var(--rak-netral-bg); color: var(--rak-netral-fg); }
        .rak-cat-name { font-size: 14px; font-weight: 500; color: var(--rak-fg); }
        .rak-cat-sub { font-size: 12px; color: var(--rak-fg-muted); }
        .rak-cat-value { font-size: 14px; font-weight: 600; margin-left: auto; }
        .rak-cat-value.in { color: var(--rak-in-fg); }
        .rak-cat-value.out { color: var(--rak-out-fg); }
        .rak-cat-value.netral { color: var(--rak-netral-fg); }
        .rak-chevron { font-size: 16px; color: var(--rak-fg-faint); transition: transform 0.15s ease; margin-left: 4px; }
        .rak-cat-row.expanded .rak-chevron { transform: rotate(180deg); }
        .rak-cat-body { border-top: 1px solid var(--rak-border); }
        .rak-tx-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px 10px 58px; font-size: 13px; flex-wrap: wrap; }
        .rak-tx-row + .rak-tx-row { border-top: 1px solid var(--rak-border); }
        .rak-tx-date { color: var(--rak-fg-muted); min-width: 78px; }
        .rak-tx-desc { flex: 1; color: var(--rak-fg); min-width: 120px; }
        .rak-tx-value { font-weight: 500; min-width: 90px; text-align: right; }
        .rak-tx-value.in { color: var(--rak-in-fg); }
        .rak-tx-value.out { color: var(--rak-out-fg); }
        .rak-tx-value.netral { color: var(--rak-netral-fg); }
        .rak-tx-link {
            font-size: 12px; color: var(--rak-accent); border: 1px solid var(--rak-accent); padding: 4px 10px;
            border-radius: 6px; white-space: nowrap; cursor: pointer; background: transparent;
            font-family: inherit; text-decoration: none; display: inline-block;
        }
        .rak-tx-link:hover { background: var(--rak-accent-soft-bg); }
        .rak-empty { text-align: center; color: var(--rak-fg-faint); font-size: 13px; padding: 2rem 0; }
        .rak-warn {
            background: var(--rak-warn-bg); border: 1px solid var(--rak-warn-border); color: var(--rak-warn-fg);
            font-size: 12px; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem;
        }
    </style>

    <div class="rak-wrap" x-data="{ expanded: {} }">

        @if(!empty($hasil['rincian']) && !($hasil['balanced'] ?? true))
            <div class="rak-warn">
                <i class="ti ti-alert-triangle" style="vertical-align:-2px;margin-right:4px"></i>
                Saldo akhir hasil hitungan berselisih
                Rp {{ number_format(abs($hasil['selisih_validasi']), 0, ',', '.') }}
                dari saldo riil akun kas. Ada kemungkinan data jurnal tidak konsisten pada periode ini.
            </div>
        @endif

        <div class="rak-header">
            <div>
                <div class="rak-title">Rekap arus kas</div>
                <div class="rak-subtitle">{{ $this->labelPeriode() }}</div>
            </div>
            <div class="rak-period-buttons">
                <button type="button" wire:click="terapkanPreset('kemarin')" class="rak-btn @if($preset === 'kemarin') active @endif">Kemarin</button>
                <button type="button" wire:click="terapkanPreset('hari_ini')" class="rak-btn @if($preset === 'hari_ini') active @endif">Hari ini</button>
                <button type="button" wire:click="terapkanPreset('minggu_ini')" class="rak-btn @if($preset === 'minggu_ini') active @endif">7 hari terakhir</button>
                <button type="button" wire:click="terapkanPreset('bulan_ini')" class="rak-btn @if($preset === 'bulan_ini') active @endif">Bulan ini</button>
            </div>
        </div>

        <div class="rak-rangebox">
            <span class="rak-rangebox-label">
                <i class="ti ti-calendar" style="font-size:14px;vertical-align:-2px;margin-right:4px"></i>
                Pilih rentang tanggal sendiri
            </span>
            <input type="date" wire:model="customDariInput">
            <span style="color: var(--rak-fg-faint);font-size:13px">&rarr;</span>
            <input type="date" wire:model="customSampaiInput">
            <button type="button" wire:click="terapkanRentangCustom" class="rak-btn rak-primary-btn">Terapkan</button>
            @if($rangeError)
                <span class="rak-range-error">{{ $rangeError }}</span>
            @endif
        </div>

        <div class="rak-cards">
            <div class="rak-card">
                <div class="rak-card-label">Kas awal</div>
                <div class="rak-card-value">Rp {{ number_format($hasil['saldo_awal'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rak-card in">
                <div class="rak-card-label"><i class="ti ti-arrow-down-left" style="font-size:13px;vertical-align:-1px;margin-right:3px"></i>Kas masuk</div>
                <div class="rak-card-value">Rp {{ number_format($hasil['total_masuk'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rak-card out">
                <div class="rak-card-label"><i class="ti ti-arrow-up-right" style="font-size:13px;vertical-align:-1px;margin-right:3px"></i>Kas keluar</div>
                <div class="rak-card-value">Rp {{ number_format($hasil['total_keluar'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rak-card end">
                <div class="rak-card-label">Kas akhir</div>
                <div class="rak-card-value">Rp {{ number_format($hasil['saldo_akhir'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>

        @php
            $awal = (float) ($hasil['saldo_awal'] ?? 0);
            $akhir = (float) ($hasil['saldo_akhir'] ?? 0);
            $maxVal = max($awal, $akhir, 1);
            $awalPct = round(($awal / $maxVal) * 60);
            $deltaPct = max(0, round((($akhir - $awal) / $maxVal) * 60));
            $naik = $akhir >= $awal;
            $persen = $this->persenPerubahan();
        @endphp

        <div class="rak-flowbox">
            <div class="rak-flowbox-title">Alur kas bersih</div>
            <div class="rak-flowrow">
                <div class="rak-flowlabel">Rp {{ number_format($awal, 0, ',', '.') }}</div>
                <div class="rak-track">
                    <div class="a" style="width: {{ $awalPct }}%"></div>
                    <div class="d" style="width: {{ $deltaPct }}%; background: {{ $naik ? '#639922' : '#e24b4a' }}"></div>
                </div>
                <div class="rak-flowlabel right">Rp {{ number_format($akhir, 0, ',', '.') }}</div>
            </div>
            <div class="rak-deltatext" style="color: {{ $naik ? '#3f8f1f' : '#c23b3a' }}">
                {{ $naik ? 'Naik' : 'Turun' }} Rp {{ number_format(abs($akhir - $awal), 0, ',', '.') }}
                ({{ $naik ? '+' : '-' }}{{ number_format(abs($persen), 1, ',', '.') }}%)
            </div>
        </div>

        <div class="rak-section-title">Rincian per kategori — klik untuk lihat transaksi</div>

        <div class="rak-cat-list">
            @forelse(($hasil['rincian'] ?? []) as $idx => $kat)
                @php $ikon = $this->ikonKategori()[$kat['kode_kategori']] ?? 'ti-dots'; @endphp
                <div class="rak-cat-row" :class="{ 'expanded': expanded[{{ $idx }}] }">
                    <div class="rak-cat-head" @click="expanded[{{ $idx }}] = !expanded[{{ $idx }}]">
                        <div class="rak-cat-icon {{ $kat['tipe'] }}"><i class="ti {{ $ikon }}"></i></div>
                        <div>
                            <div class="rak-cat-name">{{ $kat['nama'] }}</div>
                            <div class="rak-cat-sub">{{ count($kat['transaksi']) }} transaksi</div>
                        </div>
                        <div class="rak-cat-value {{ $kat['tipe'] }}">
                            {{ $kat['tipe'] === 'out' ? '- ' : '+ ' }}Rp {{ number_format($kat['nilai'], 0, ',', '.') }}
                        </div>
                        <i class="ti ti-chevron-down rak-chevron"></i>
                    </div>
                    <div class="rak-cat-body" x-show="expanded[{{ $idx }}]" x-transition>
                        @foreach($kat['transaksi'] as $tx)
                            <div class="rak-tx-row">
                                <div class="rak-tx-date">{{ \Carbon\Carbon::parse($tx['tgl'])->locale('id')->isoFormat('D MMM') }}</div>
                                <div class="rak-tx-desc">
                                    {{ $tx['deskripsi'] }}
                                    <span style="color: var(--rak-fg-faint);font-size:12px">&middot; JU-{{ $tx['jurnal'] }}</span>
                                </div>
                                <div class="rak-tx-value {{ $tx['tipe'] }}">
                                    {{ $tx['tipe'] === 'out' ? '- ' : '+ ' }}Rp {{ number_format($tx['nilai'], 0, ',', '.') }}
                                </div>
                                <a href="{{ $this->urlJurnal($tx['jurnal']) }}" target="_blank" class="rak-tx-link">
                                    Lihat di jurnal
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rak-empty">Tidak ada transaksi kas pada periode ini.</div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>