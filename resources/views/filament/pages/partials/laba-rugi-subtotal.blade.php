@php
    $styleMap = match($style) {
        'pendapatan_bruto' => [
            'row' => 'bg-emerald-50/40 dark:bg-emerald-900/20', 
            'text' => 'text-emerald-700 dark:text-emerald-400'
        ],
        'penjualan_bersih' => [
            'row' => 'bg-slate-100/50 dark:bg-slate-800/40', 
            'text' => 'text-slate-800 dark:text-slate-200'
        ],
        'laba_kotor' => [
            'row' => 'bg-blue-50/60 dark:bg-blue-900/20', 
            'text' => 'text-blue-700 dark:text-blue-400'
        ],
        'laba_usaha' => [
            'row' => 'bg-indigo-50 dark:bg-indigo-900/30', 
            'text' => 'text-indigo-800 dark:text-indigo-400'
        ],
        default => [
            'row' => 'bg-gray-50 dark:bg-gray-800', 
            'text' => 'text-gray-900 dark:text-gray-100'
        ],
    };
@endphp

<tr class="{{ $styleMap['row'] }} border-y border-gray-100 dark:border-gray-800">
    <td class="py-4"></td>
    <td class="py-5 px-4 text-center">
        <div class="inline-block">
            <p class="text-sm font-bold {{ $styleMap['text'] }} uppercase tracking-[0.2em]">
                {{ $label }}
            </p>
            @if(!empty($rumus))
                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium italic mt-1 border-t border-gray-200 dark:border-gray-700 pt-1">
                    {{ $rumus }}
                </p>
            @endif
        </div>
    </td>
    @foreach($buls as $bulan)
        @php $val = $r[$bulan][$key] ?? 0; @endphp
        <td class="px-4 py-5 text-right text-sm font-black {{ $val >= 0 ? $styleMap['text'] : 'text-red-600 dark:text-red-400' }}">
            {{ $this->formatRupiah($val) }}
        </td>
    @endforeach
</tr>