{{--
    PARTIAL: laba-rugi-subtotal.blade.php
    Variables: $label, $key, $style, $rumus, $buls, $r (ringkasanPerBulan)
--}}

@php
    $styleMap = match($style) {
        'pendapatan_bruto' => [
            'row'   => 'bg-emerald-50 dark:bg-emerald-900/10 border-t border-emerald-100 dark:border-emerald-800',
            'label' => 'text-emerald-700 dark:text-emerald-300 font-semibold',
            'rumus' => 'text-emerald-400 dark:text-emerald-600',
            'pos'   => 'text-emerald-700 dark:text-emerald-300 font-semibold',
            'neg'   => 'text-red-500 font-semibold',
        ],
        'penjualan_bersih' => [
            'row'   => 'bg-slate-50 dark:bg-slate-800/50 border-t-2 border-slate-200 dark:border-slate-600',
            'label' => 'text-slate-700 dark:text-slate-200 font-semibold',
            'rumus' => 'text-slate-400 dark:text-slate-500',
            'pos'   => 'text-slate-700 dark:text-slate-200 font-semibold',
            'neg'   => 'text-red-500 font-semibold',
        ],
        'total_hpp' => [
            'row'   => 'bg-orange-50 dark:bg-orange-900/10 border-t border-orange-100 dark:border-orange-800',
            'label' => 'text-orange-600 dark:text-orange-300 font-semibold',
            'rumus' => 'text-orange-400 dark:text-orange-600',
            'pos'   => 'text-orange-600 dark:text-orange-300 font-semibold',
            'neg'   => 'text-red-500 font-semibold',
        ],
        'laba_kotor' => [
            'row'   => 'bg-blue-50 dark:bg-blue-900/20 border-t-2 border-blue-200 dark:border-blue-700',
            'label' => 'text-blue-700 dark:text-blue-300 font-bold',
            'rumus' => 'text-blue-400 dark:text-blue-600',
            'pos'   => 'text-blue-700 dark:text-blue-300 font-bold',
            'neg'   => 'text-red-500 font-bold',
        ],
        'total_beban' => [
            'row'   => 'bg-rose-50 dark:bg-rose-900/10 border-t border-rose-100 dark:border-rose-800',
            'label' => 'text-rose-600 dark:text-rose-300 font-semibold',
            'rumus' => 'text-rose-400 dark:text-rose-600',
            'pos'   => 'text-rose-600 dark:text-rose-300 font-semibold',
            'neg'   => 'text-red-500 font-semibold',
        ],
        'laba_usaha' => [
            'row'   => 'bg-indigo-50 dark:bg-indigo-900/20 border-t-2 border-indigo-200 dark:border-indigo-700',
            'label' => 'text-indigo-700 dark:text-indigo-300 font-bold',
            'rumus' => 'text-indigo-400 dark:text-indigo-600',
            'pos'   => 'text-indigo-700 dark:text-indigo-300 font-bold',
            'neg'   => 'text-red-500 font-bold',
        ],
        'laba_sebelum_pajak' => [
            'row'   => 'bg-violet-50 dark:bg-violet-900/20 border-t-2 border-violet-300 dark:border-violet-600',
            'label' => 'text-violet-700 dark:text-violet-300 font-bold',
            'rumus' => 'text-violet-400 dark:text-violet-600',
            'pos'   => 'text-violet-700 dark:text-violet-300 font-bold',
            'neg'   => 'text-red-500 font-bold',
        ],
        default => [
            'row'   => 'bg-gray-50 dark:bg-gray-800 border-t border-gray-200',
            'label' => 'text-gray-600 dark:text-gray-300 font-semibold',
            'rumus' => 'text-gray-400',
            'pos'   => 'text-gray-700 dark:text-gray-200 font-semibold',
            'neg'   => 'text-red-500 font-semibold',
        ],
    };
@endphp

<tr class="{{ $styleMap['row'] }}">
    {{-- Kolom kode: kosong --}}
    <td class="px-4 py-3 {{ $styleMap['row'] }}"></td>
    {{-- Kolom nama: indent 48px agar tidak sejajar dengan group/akun --}}
    <td class="py-3 {{ $styleMap['row'] }}" style="padding-left: 48px;">
        <p class="text-sm {{ $styleMap['label'] }} uppercase tracking-wide">{{ $label }}</p>
        @if(!empty($rumus))
            <p class="text-xs {{ $styleMap['rumus'] }} mt-0.5">{{ $rumus }}</p>
        @endif
    </td>
    @foreach($buls as $bulan)
        @php $val = $r[$bulan][$key] ?? 0; @endphp
        <td class="px-4 py-3 text-right text-sm {{ $val >= 0 ? $styleMap['pos'] : $styleMap['neg'] }}">
            {{ 'Rp ' . number_format(abs($val), 0, ',', '.') }}
            @if($val < 0) <span class="text-xs font-normal">(Rugi)</span> @endif
        </td>
    @endforeach
</tr>