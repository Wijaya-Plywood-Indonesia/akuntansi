@php
    $styleMap = match($style) {
        'pendapatan_bruto' => ['row' => 'bg-emerald-50/40', 'text' => 'text-emerald-700'],
        'penjualan_bersih' => ['row' => 'bg-slate-100/50', 'text' => 'text-slate-800'],
        'laba_kotor'       => ['row' => 'bg-blue-50/60', 'text' => 'text-blue-700'],
        default            => ['row' => 'bg-gray-50', 'text' => 'text-gray-900'],
    };
@endphp

<tr class="{{ $styleMap['row'] }} border-y border-gray-100">
    <td class="py-4"></td>
    <td class="py-5 px-4 text-center bg-white/30">
        <div class="inline-block">
            <p class="text-sm font-bold {{ $styleMap['text'] }} uppercase tracking-[0.2em]">{{ $label }}</p>
            @if(!empty($rumus))
                <p class="text-[10px] text-gray-400 font-medium italic mt-1 border-t border-gray-200 pt-1">{{ $rumus }}</p>
            @endif
        </div>
    </td>
    @foreach($buls as $bulan)
        @php $val = $r[$bulan][$key] ?? 0; @endphp
        <td class="px-4 py-5 text-right text-sm font-black {{ $val >= 0 ? $styleMap['text'] : 'text-red-600' }}">
            {{ $this->formatRupiah($val) }}
        </td>
    @endforeach
</tr>