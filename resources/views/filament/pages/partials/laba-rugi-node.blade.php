@php
    $isGroup     = $node['type'] === 'group';
    $isAnak      = $node['type'] === 'anak_akun';
    $isSub       = $node['type'] === 'sub_anak_akun';
    $hasChildren = !empty($node['children']);
    $rowId       = 'row-' . ($node['kode'] ?? md5($node['nama'] . uniqid()));
@endphp

@if($isGroup && !$node['hidden'])
    <tr class="bg-gray-50 border-b border-gray-100">
        <td class="px-4 py-3"></td>
        <td colspan="{{ count($buls) + 1 }}" class="px-4 py-3 font-bold text-xs uppercase text-gray-900 tracking-widest">
            {{ $node['nama'] }}
        </td>
    </tr>
    @foreach($node['children'] as $child)
        @include('filament.pages.partials.laba-rugi-node', ['node' => $child, 'depth' => $depth + 1, 'buls' => $buls])
    @endforeach

@elseif($isAnak)
    <tr x-data="{ open: false }" 
        x-init="$watch('allOpen', value => open = value)"
        @click="open = !open; document.querySelectorAll('[data-parent=\'{{ $rowId }}\']').forEach(r => r.style.display = open ? '' : 'none');" 
        class="cursor-pointer hover:bg-gray-50 border-t border-gray-100">
        
        <td class="px-4 py-2.5 text-sm font-medium text-gray-500 flex items-center gap-2">
            @if($hasChildren)
                <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 transition-transform text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                </svg>
            @else <span class="w-3"></span> @endif
            {{ $node['kode'] }}
        </td>
        <td class="px-2 py-2.5 text-sm font-semibold text-gray-800 bg-white">{{ $node['nama'] }}</td>
        @foreach($buls as $bulan)
            <td class="px-4 py-2.5 text-right text-sm font-semibold text-gray-900">{{ $this->formatRupiah($node['nilai_per_bulan'][$bulan] ?? 0) }}</td>
        @endforeach
    </tr>
    
    @foreach($node['children'] as $child)
        @include('filament.pages.partials.laba-rugi-node', ['node' => $child, 'depth' => $depth + 1, 'buls' => $buls, 'parentId' => $rowId])
    @endforeach

@elseif($isSub)
    {{-- DEFAULT: style display:none agar tertutup (collapse) --}}
    <tr data-parent="{{ $parentId ?? '' }}" data-collapse style="display: none;" class="hover:bg-gray-50/50 border-t border-dashed border-gray-100">
        <td class="px-10 py-2 text-xs font-mono text-gray-400 italic">{{ $node['kode'] }}</td>
        <td class="px-2 py-2 bg-white text-sm text-gray-500 italic" style="padding-left: 48px;">{{ $node['nama'] }}</td>
        @foreach($buls as $bulan)
            <td class="px-4 py-2 text-right text-sm text-gray-400 italic">{{ $this->formatRupiah($node['nilai_per_bulan'][$bulan] ?? 0) }}</td>
        @endforeach
    </tr>
@endif