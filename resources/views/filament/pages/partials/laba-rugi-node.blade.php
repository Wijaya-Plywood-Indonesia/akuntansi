{{--
    PARTIAL: laba-rugi-node.blade.php
    Lokasi: resources/views/filament/pages/partials/laba-rugi-node.blade.php
--}}

@php
    $paddingLeft = ($depth * 20) + 24;
    $isGroup     = $node['type'] === 'group';
    $isAnak      = $node['type'] === 'anak_akun';
    $isSub       = $node['type'] === 'sub_anak_akun';
    $hasChildren = !empty($node['children']);
    $nilai       = $isGroup ? $node['total_nilai']
                 : ($isAnak ? $node['total_nilai']
                 : $node['nilai']);
@endphp

{{-- ================================================================
     GROUP
================================================================ --}}
@if($isGroup && !$node['hidden'])

    {{-- Header group --}}
    <tr class="{{ $depth === 0
        ? 'bg-gray-700 dark:bg-gray-900'
        : ($depth === 1
            ? 'bg-gray-100 dark:bg-gray-800'
            : 'bg-gray-50 dark:bg-gray-800/40') }}">
        <td class="py-3 text-xs text-gray-400"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;"></td>
        <td class="py-3 pr-6
            {{ $depth === 0
                ? 'text-white font-bold text-xs uppercase tracking-widest'
                : ($depth === 1
                    ? 'text-gray-700 dark:text-gray-100 font-semibold text-sm'
                    : 'text-gray-600 dark:text-gray-300 font-medium text-sm') }}"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
            @if($depth === 0 && $node['tipe'] !== 'lainnya')
                @php
                    $badgeMap = [
                        'pendapatan'      => ['label' => 'Pendapatan',       'class' => 'bg-emerald-100 text-emerald-700'],
                        'hpp'             => ['label' => 'HPP',              'class' => 'bg-orange-100 text-orange-700'],
                        'beban_produksi'  => ['label' => 'Beban Produksi',   'class' => 'bg-yellow-100 text-yellow-700'],
                        'beban_usaha'     => ['label' => 'Beban Usaha',      'class' => 'bg-red-100 text-red-700'],
                        'pendapatan_lain' => ['label' => 'Pendapatan Lain',  'class' => 'bg-teal-100 text-teal-700'],
                        'beban_lain'      => ['label' => 'Beban Lain',       'class' => 'bg-rose-100 text-rose-700'],
                    ];
                    $badge = $badgeMap[$node['tipe']] ?? null;
                @endphp
                @if($badge)
                    <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $badge['class'] }}">
                        {{ $badge['label'] }}
                    </span>
                @endif
            @endif
        </td>
        <td class="py-3 px-6 text-right
            {{ $depth === 0 ? 'text-white font-bold text-sm' : 'text-gray-700 dark:text-gray-200 font-semibold text-sm' }}">
            @if($depth === 0 || !$hasChildren)
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-400">–</span>
                @endif
            @endif
        </td>
    </tr>

    {{-- Children rekursif --}}
    @foreach($node['children'] as $child)
        @include('filament.pages.partials.laba-rugi-node', [
            'node'  => $child,
            'depth' => $depth + 1,
        ])
    @endforeach

    {{-- Subtotal baris jika group bukan level 0 dan punya children --}}
    @if($hasChildren && $depth > 0)
        <tr class="border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/20">
            <td style="padding-left:{{ $paddingLeft }}px; padding-right:16px;"></td>
            <td class="py-2 pr-6 text-xs font-semibold text-gray-500 dark:text-gray-400 italic uppercase tracking-wide"
                style="padding-left:{{ $paddingLeft }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            <td class="py-2 px-6 text-right text-sm font-bold
                {{ $nilai >= 0 ? 'text-gray-800 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-400">–</span>
                @endif
            </td>
        </tr>
    @endif

{{-- ================================================================
     ANAK AKUN — collapsible jika punya sub
================================================================ --}}
@elseif($isAnak)

    @if($hasChildren)
    {{-- AnakAkun DENGAN sub: bisa collapse --}}
    <tbody x-data="{ open: true }">
        {{-- Header AnakAkun — bisa diklik --}}
        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors"
            @click="open = !open">
            <td class="py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500"
                style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
                <span class="inline-flex items-center gap-1">
                    {{-- Arrow icon --}}
                    <svg x-show="open"
                        class="w-3 h-3 text-gray-400 flex-shrink-0"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <svg x-show="!open"
                        class="w-3 h-3 text-gray-400 flex-shrink-0"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                    {{ $node['kode'] }}
                </span>
            </td>
            <td class="py-2.5 pr-6 text-sm font-medium text-gray-700 dark:text-gray-300"
                style="padding-left:{{ $paddingLeft }}px">
                {{ $node['nama'] }}
            </td>
            <td class="py-2.5 px-6 text-right text-sm">
                {{-- Saat collapsed: tampilkan total. Saat open: kosong (sub akan tampil) --}}
                <span x-show="!open"
                    class="{{ $nilai != 0 ? 'text-gray-800 dark:text-gray-100 font-medium' : 'text-gray-400' }}">
                    {{ $nilai != 0 ? $this->formatRupiah($nilai) : '–' }}
                </span>
                <span x-show="open" class="text-gray-300 dark:text-gray-600 text-xs">▾</span>
            </td>
        </tr>

        {{-- Sub rows — collapsible --}}
        <tr x-show="open" x-collapse style="display: none;">
            <td colspan="3" class="p-0">
                <table class="w-full">
                    @foreach($node['children'] as $child)
                        @include('filament.pages.partials.laba-rugi-node', [
                            'node'  => $child,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                    {{-- Subtotal AnakAkun --}}
                    <tr class="border-t border-dashed border-gray-200 dark:border-gray-700">
                        <td style="padding-left:{{ ($depth+1)*20+24 }}px; padding-right:16px;"></td>
                        <td class="py-2 pr-6 text-xs italic font-semibold text-gray-500 dark:text-gray-400"
                            style="padding-left:{{ ($depth+1)*20+24 }}px">
                            Jumlah {{ $node['nama'] }}
                        </td>
                        <td class="py-2 px-6 text-right text-sm font-semibold
                            {{ $nilai >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500' }}">
                            {{ $this->formatRupiah($nilai) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>

    @else
    {{-- AnakAkun TANPA sub: plain row biasa --}}
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
        <td class="py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2.5 pr-6 text-sm text-gray-700 dark:text-gray-300"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2.5 px-6 text-right text-sm
            {{ $nilai != 0 ? 'text-gray-800 dark:text-gray-100 font-medium' : 'text-gray-400 dark:text-gray-600' }}">
            @if($nilai != 0) {{ $this->formatRupiah($nilai) }}
            @else <span>–</span>
            @endif
        </td>
    </tr>
    @endif

{{-- ================================================================
     SUB ANAK AKUN
================================================================ --}}
@elseif($isSub)

    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
        <td class="py-2 text-xs font-mono text-gray-400 dark:text-gray-500"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2 pr-6 text-sm text-gray-600 dark:text-gray-400"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2 px-6 text-right text-sm
            {{ $node['nilai'] != 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
            @if($node['nilai'] != 0) {{ $this->formatRupiah($node['nilai']) }}
            @else <span>–</span>
            @endif
        </td>
    </tr>

@endif