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

    <tr class="{{ $depth === 0
        ? 'bg-gray-100 dark:bg-gray-800'
        : ($depth === 1
            ? 'bg-gray-50 dark:bg-gray-800/60'
            : 'bg-white dark:bg-gray-900') }}">
        <td class="py-2.5 text-xs text-gray-400"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;"></td>
        <td class="py-2.5 pr-6
            {{ $depth === 0
                ? 'text-gray-700 dark:text-gray-100 font-bold text-xs uppercase tracking-widest'
                : ($depth === 1
                    ? 'text-gray-600 dark:text-gray-200 font-semibold text-sm'
                    : 'text-gray-500 dark:text-gray-300 font-medium text-sm') }}"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2.5 px-6 text-right
            {{ $depth === 0 ? 'text-gray-700 dark:text-gray-100 font-bold text-sm' : 'text-gray-600 dark:text-gray-200 font-semibold text-sm' }}">
            @if($depth > 0 && !$hasChildren)
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-300 dark:text-gray-600">–</span>
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

    {{-- Subtotal row setelah children --}}
    @if($hasChildren && $depth > 0)
        <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30">
            <td style="padding-left:{{ $paddingLeft }}px; padding-right:16px;"></td>
            <td class="py-2 pr-6 text-xs font-semibold text-gray-400 dark:text-gray-500 italic"
                style="padding-left:{{ $paddingLeft }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            <td class="py-2 px-6 text-right text-sm font-semibold
                {{ $nilai >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500 dark:text-red-400' }}">
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-300 dark:text-gray-600">–</span>
                @endif
            </td>
        </tr>
    @endif

{{-- ================================================================
     ANAK AKUN — collapsible jika punya sub
================================================================ --}}
@elseif($isAnak)

    @if($hasChildren)
    <tbody
        x-data="{ open: false }"
        @laba-rugi-expand.window="open = true"
        @laba-rugi-collapse.window="open = false"
    >
        <tr class="cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors border-t border-gray-100 dark:border-gray-800"
            @click="open = !open">
            <td class="py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500"
                style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
                <span class="inline-flex items-center gap-1">
                    <svg x-show="open" class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <svg x-show="!open" class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                    {{ $node['kode'] }}
                </span>
            </td>
            <td class="py-2.5 pr-6 text-sm text-gray-600 dark:text-gray-300"
                style="padding-left:{{ $paddingLeft }}px">
                {{ $node['nama'] }}
            </td>
            <td class="py-2.5 px-6 text-right text-sm">
                <span x-show="!open"
                    class="{{ $nilai != 0 ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-300 dark:text-gray-600' }}">
                    {{ $nilai != 0 ? $this->formatRupiah($nilai) : '–' }}
                </span>
                <span x-show="open" class="text-gray-300 dark:text-gray-600 text-xs">▾</span>
            </td>
        </tr>

        <tr x-show="open" x-collapse style="display: none;">
            <td colspan="3" class="p-0">
                <table class="w-full">
                    @foreach($node['children'] as $child)
                        @include('filament.pages.partials.laba-rugi-node', [
                            'node'  => $child,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                    <tr class="border-t border-dashed border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                        <td style="padding-left:{{ ($depth+1)*20+24 }}px; padding-right:16px;"></td>
                        <td class="py-1.5 pr-6 text-xs italic text-gray-400 dark:text-gray-500"
                            style="padding-left:{{ ($depth+1)*20+24 }}px">
                            Jumlah {{ $node['nama'] }}
                        </td>
                        <td class="py-1.5 px-6 text-right text-sm font-semibold
                            {{ $nilai >= 0 ? 'text-gray-600 dark:text-gray-300' : 'text-red-500' }}">
                            {{ $this->formatRupiah($nilai) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>

    @else
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors border-t border-gray-100 dark:border-gray-800">
        <td class="py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2.5 pr-6 text-sm text-gray-600 dark:text-gray-300"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2.5 px-6 text-right text-sm
            {{ $nilai != 0 ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-300 dark:text-gray-600' }}">
            {{ $nilai != 0 ? $this->formatRupiah($nilai) : '–' }}
        </td>
    </tr>
    @endif

{{-- ================================================================
     SUB ANAK AKUN
================================================================ --}}
@elseif($isSub)

    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors border-t border-gray-100 dark:border-gray-800">
        <td class="py-2 text-xs font-mono text-gray-300 dark:text-gray-600"
            style="padding-left:{{ $paddingLeft }}px; padding-right:16px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2 pr-6 text-sm text-gray-500 dark:text-gray-400"
            style="padding-left:{{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2 px-6 text-right text-sm
            {{ $node['nilai'] != 0 ? 'text-gray-600 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600' }}">
            {{ $node['nilai'] != 0 ? $this->formatRupiah($node['nilai']) : '–' }}
        </td>
    </tr>

@endif