{{--
    PARTIAL: laba-rugi-subtotal.blade.php
    Path: resources/views/filament/pages/partials/laba-rugi-subtotal.blade.php

    Variables:
    - $label : string
    - $nilai  : float
    - $style  : 'laba_kotor' | 'laba_usaha' | 'laba_bersih'
--}}

@php
    $bgClass = match($style) {
        'laba_kotor' => 'bg-blue-800 dark:bg-blue-950',
        'laba_usaha' => 'bg-indigo-800 dark:bg-indigo-950',
        'laba_bersih'=> 'bg-violet-900 dark:bg-violet-950',
        default      => 'bg-gray-700 dark:bg-gray-900',
    };
@endphp

<tr class="{{ $bgClass }}">
    <td class="px-3 py-3.5"></td>
    <td class="px-4 py-3.5"></td>
    <td class="px-4 py-3.5 text-sm font-bold text-white uppercase tracking-widest">
        {{ $label }}
    </td>
    <td class="px-4 py-3.5 text-right text-sm font-bold {{ $nilai >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
        {{ 'Rp ' . number_format(abs($nilai), 0, ',', '.') }}
        @if($nilai < 0)
            <span class="text-xs font-normal ml-1">(Rugi)</span>
        @endif
    </td>
</tr>