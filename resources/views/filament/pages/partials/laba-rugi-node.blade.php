{{--
    PARTIAL: laba-rugi-node.blade.php
    Variables: $node, $depth, $buls (array bulan), $parentId (optional)
--}}

@php
    $paddingLeft = ($depth * 16) + 16;
    $isGroup     = $node['type'] === 'group';
    $isAnak      = $node['type'] === 'anak_akun';
    $isSub       = $node['type'] === 'sub_anak_akun';
    $hasChildren = !empty($node['children']);
    $rowId       = 'row-' . ($node['kode'] ?? md5($node['nama'] . $depth . uniqid()));
@endphp

{{-- ================================================================ GROUP ================================================================ --}}
@if($isGroup && !$node['hidden'])

    <tr class="{{ $depth === 0 ? 'bg-gray-100 dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
        <td class="px-4 py-2.5 text-xs font-mono text-gray-400"></td>
        <td class="px-4 py-2.5 {{ $depth === 0 ? 'bg-gray-100 dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700/30' }}"
            colspan="{{ count($buls) + 1 }}"
            style="padding-left:{{ $paddingLeft }}px">
            <span class="{{ $depth === 0 ? 'text-gray-900 dark:text-white font-bold text-xs uppercase tracking-widest' : 'text-gray-700 dark:text-gray-200 font-semibold text-sm' }}">
                {{ $node['nama'] }}
            </span>
        </td>
    </tr>

    @foreach($node['children'] as $child)
        @include('filament.pages.partials.laba-rugi-node', [
            'node'  => $child,
            'depth' => $depth + 1,
            'buls'  => $buls,
        ])
    @endforeach

    @if($hasChildren && $depth > 0)
        <tr class="border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/20">
            <td class="px-4 py-2 text-xs font-mono text-gray-400"></td>
            <td class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-700/20"
                style="padding-left:{{ $paddingLeft }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            @foreach($buls as $bulan)
                @php $val = $node['nilai_per_bulan'][$bulan] ?? 0; @endphp
                <td class="px-4 py-2 text-right text-sm font-semibold {{ $val >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500 dark:text-red-400' }}">
                    @if($val != 0) {{ $this->formatRupiah($val) }}
                    @else <span class="text-gray-300 dark:text-gray-600">–</span>
                    @endif
                </td>
            @endforeach
        </tr>
    @endif

{{-- ================================================================ ANAK AKUN ================================================================ --}}
@elseif($isAnak)

    @if($hasChildren)
        <tr x-data="{ open: false }"
            x-on:laba-rugi-expand.window="
                open = true;
                document.querySelectorAll('[data-parent=\'{{ $rowId }}\']').forEach(r => r.style.display = '');
            "
            x-on:laba-rugi-collapse.window="
                open = false;
                document.querySelectorAll('[data-parent=\'{{ $rowId }}\']').forEach(r => r.style.display = 'none');
            "
            @click="
                open = !open;
                document.querySelectorAll('[data-parent=\'{{ $rowId }}\']').forEach(r => r.style.display = open ? '' : 'none');
            "
            class="cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors border-t border-gray-100 dark:border-gray-700">
            <td class="px-4 py-2.5 text-xs font-mono text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5">
                    <svg x-show="!open" class="w-3 h-3 text-gray-400 dark:text-gray-500 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                    <svg x-show="open" class="w-3 h-3 text-blue-400 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $node['kode'] }}</span>
                </span>
                {{-- {{ $node['kode'] }} --}}
            </td>
            <td class="px-4 py-2.5 bg-white dark:bg-gray-800" style="padding-left:{{ $paddingLeft }}px">
                <span class="inline-flex items-center gap-1.5">
                    
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $node['nama'] }}</span>
                </span>
            </td>
            @foreach($buls as $bulan)
                @php $val = $node['nilai_per_bulan'][$bulan] ?? 0; @endphp
                <td class="px-4 py-2.5 text-right text-sm font-semibold {{ $val != 0 ? 'text-gray-800 dark:text-gray-100' : 'text-gray-300 dark:text-gray-600' }}">
                    {{ $val != 0 ? $this->formatRupiah($val) : '–' }}
                </td>
            @endforeach
        </tr>

        @foreach($node['children'] as $child)
            @include('filament.pages.partials.laba-rugi-node', [
                'node'     => $child,
                'depth'    => $depth + 1,
                'buls'     => $buls,
                'parentId' => $rowId,
            ])
        @endforeach

        <tr data-parent="{{ $parentId ?? '' }}" style="display:none"
            class="border-t border-dashed border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/20">
            <td class="px-4 py-1.5 text-xs font-mono text-gray-400"></td>
            <td class="px-4 py-1.5 text-xs italic font-semibold text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800/20"
                style="padding-left:{{ ($depth + 1) * 16 + 16 }}px">
                Jumlah {{ $node['nama'] }}
            </td>
            @foreach($buls as $bulan)
                @php $val = $node['nilai_per_bulan'][$bulan] ?? 0; @endphp
                <td class="px-4 py-1.5 text-right text-sm font-semibold {{ $val >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500' }}">
                    {{ $this->formatRupiah($val) }}
                </td>
            @endforeach
        </tr>

    @else
        <tr data-parent="{{ $parentId ?? '' }}"
            @if(isset($parentId)) style="display:none" @endif
            class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors border-t border-gray-100 dark:border-gray-700">
            <td class="px-4 py-2.5 text-xs font-mono text-gray-500 dark:text-gray-400">
                {{ $node['kode'] }}
            </td>
            <td class="px-4 py-2.5 bg-white dark:bg-gray-800" style="padding-left:{{ $paddingLeft }}px">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $node['nama'] }}</span>
            </td>
            @foreach($buls as $bulan)
                @php $val = $node['nilai_per_bulan'][$bulan] ?? 0; @endphp
                <td class="px-4 py-2.5 text-right text-sm {{ $val != 0 ? 'text-gray-800 dark:text-gray-100 font-semibold' : 'text-gray-300 dark:text-gray-600' }}">
                    {{ $val != 0 ? $this->formatRupiah($val) : '–' }}
                </td>
            @endforeach
        </tr>
    @endif

{{-- ================================================================ SUB ANAK AKUN ================================================================ --}}
@elseif($isSub)

    <tr data-parent="{{ $parentId ?? '' }}" style="display:none"
        class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors border-t border-gray-100 dark:border-gray-700">
        <td class="px-4 py-2 text-xs font-mono text-gray-400 dark:text-gray-500">
            {{ $node['kode'] }}
        </td>
        <td class="px-4 py-2 bg-white dark:bg-gray-800" style="padding-left:{{ $paddingLeft }}px">
            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $node['nama'] }}</span>
        </td>
        @foreach($buls as $bulan)
            @php $val = $node['nilai_per_bulan'][$bulan] ?? 0; @endphp
            <td class="px-4 py-2 text-right text-sm {{ $val != 0 ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-300 dark:text-gray-600' }}">
                {{ $val != 0 ? $this->formatRupiah($val) : '–' }}
            </td>
        @endforeach
    </tr>

@endif