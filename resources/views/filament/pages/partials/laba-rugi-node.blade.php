{{--
    PARTIAL: laba-rugi-node.blade.php
    Lokasi: resources/views/filament/pages/partials/laba-rugi-node.blade.php

    Variables:
    - $node  : array node (group | anak_akun | sub_anak_akun)
    - $depth : int kedalaman rekursi (untuk indentasi)
--}}

@php
    $paddingLeft = ($depth * 20) + 24; // px, indentasi per level
    $isGroup     = $node['type'] === 'group';
    $isAnak      = $node['type'] === 'anak_akun';
    $isSub       = $node['type'] === 'sub_anak_akun';
    $hasChildren = !empty($node['children']);
    $nilai       = $isGroup ? $node['total_nilai'] : ($isAnak ? $node['total_nilai'] : $node['nilai']);
@endphp

{{-- ================================================================
     GROUP ROW (Header / Judul Kelompok)
================================================================ --}}
@if($isGroup && !$node['hidden'])

    {{-- GROUP HEADER --}}
    <tr class="{{ $depth === 0 ? 'bg-gray-800 dark:bg-gray-900' : ($depth === 1 ? 'bg-gray-100 dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-800/50') }}">
        <td class="py-3 text-xs text-gray-400 dark:text-gray-500" style="padding-left: {{ $paddingLeft }}px; padding-right: 24px;">
            {{-- Groups biasanya tidak punya kode --}}
        </td>
        <td class="py-3 pr-6 {{ $depth === 0 ? 'text-white font-bold text-xs uppercase tracking-widest' : ($depth === 1 ? 'text-gray-700 dark:text-gray-200 font-semibold text-sm' : 'text-gray-600 dark:text-gray-300 font-medium text-sm') }}"
            style="padding-left: {{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-3 px-6 text-right {{ $depth === 0 ? 'text-white font-bold text-sm' : 'text-gray-700 dark:text-gray-200 font-semibold text-sm' }}">
            {{-- Total group hanya tampil di level 0 atau kalau group adalah leaf --}}
            @if($depth === 0 || !$hasChildren)
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-400">-</span>
                @endif
            @endif
        </td>
    </tr>

    {{-- REKURSI: Render children --}}
    @foreach($node['children'] as $child)
        @include('filament.pages.partials.laba-rugi-node', [
            'node'  => $child,
            'depth' => $depth + 1,
        ])
    @endforeach

    {{-- SUBTOTAL ROW: Tampil setelah children jika group punya children --}}
    @if($hasChildren && $depth > 0)
        <tr class="border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/30">
            <td class="py-2 text-xs text-gray-400" style="padding-left: {{ $paddingLeft }}px; padding-right: 24px;"></td>
            <td class="py-2 pr-6 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide italic"
                style="padding-left: {{ $paddingLeft }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            <td class="py-2 px-6 text-right text-sm font-semibold {{ $nilai >= 0 ? 'text-gray-800 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                @if($nilai != 0)
                    {{ $this->formatRupiah($nilai) }}
                @else
                    <span class="text-gray-400">-</span>
                @endif
            </td>
        </tr>
    @endif

{{-- ================================================================
     ANAK AKUN ROW
================================================================ --}}
@elseif($isAnak)

    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors duration-75">
        <td class="py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500"
            style="padding-left: {{ $paddingLeft }}px; padding-right: 24px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2.5 pr-6 text-sm text-gray-700 dark:text-gray-300"
            style="padding-left: {{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2.5 px-6 text-right text-sm {{ $nilai != 0 ? 'text-gray-800 dark:text-gray-100 font-medium' : 'text-gray-400 dark:text-gray-600' }}">
            @if($nilai != 0)
                {{ $this->formatRupiah($nilai) }}
            @else
                <span>-</span>
            @endif
        </td>
    </tr>

    {{-- Jika AnakAkun punya Sub, render juga --}}
    @if($hasChildren)
        @foreach($node['children'] as $child)
            @include('filament.pages.partials.laba-rugi-node', [
                'node'  => $child,
                'depth' => $depth + 1,
            ])
        @endforeach

        {{-- Subtotal AnakAkun kalau punya sub --}}
        <tr class="border-t border-dashed border-gray-200 dark:border-gray-700">
            <td class="py-2 text-xs text-gray-400" style="padding-left: {{ ($depth + 1) * 20 + 24 }}px; padding-right: 24px;"></td>
            <td class="py-2 pr-6 text-xs font-semibold text-gray-500 dark:text-gray-400 italic"
                style="padding-left: {{ ($depth + 1) * 20 + 24 }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            <td class="py-2 px-6 text-right text-sm font-semibold {{ $nilai >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500' }}">
                {{ $this->formatRupiah($nilai) }}
            </td>
        </tr>
    @endif

{{-- ================================================================
     SUB ANAK AKUN ROW
================================================================ --}}
@elseif($isSub)

    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors duration-75">
        <td class="py-2 text-xs font-mono text-gray-400 dark:text-gray-500"
            style="padding-left: {{ $paddingLeft }}px; padding-right: 24px;">
            {{ $node['kode'] }}
        </td>
        <td class="py-2 pr-6 text-sm text-gray-600 dark:text-gray-400"
            style="padding-left: {{ $paddingLeft }}px">
            {{ $node['nama'] }}
        </td>
        <td class="py-2 px-6 text-right text-sm {{ $node['nilai'] != 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
            @if($node['nilai'] != 0)
                {{ $this->formatRupiah($node['nilai']) }}
            @else
                <span>-</span>
            @endif
        </td>
    </tr>

@endif