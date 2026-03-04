{{--
    PARTIAL: laba-rugi-subtotal.blade.php
    Variables:
    - $label : string
    - $nilai  : float
    - $style  : string
    - $kode   : string
    - $rumus  : string (keterangan rumus kecil)
--}}

@php
    $styleMap = match($style) {
        'pendapatan_bruto' => [
            'row'       => 'bg-emerald-50 dark:bg-emerald-900/10 border-t border-emerald-100 dark:border-emerald-800',
            'kode'      => 'text-emerald-300',
            'label'     => 'text-emerald-700 dark:text-emerald-300 font-semibold',
            'nilai_pos' => 'text-emerald-700 dark:text-emerald-300 font-semibold',
            'nilai_neg' => 'text-red-500 font-semibold',
            'rumus'     => 'text-emerald-400 dark:text-emerald-600',
        ],
        'penjualan_bersih' => [
            'row'       => 'bg-slate-50 dark:bg-slate-800/50 border-t-2 border-slate-200 dark:border-slate-600',
            'kode'      => 'text-slate-400',
            'label'     => 'text-slate-700 dark:text-slate-200 font-semibold',
            'nilai_pos' => 'text-slate-700 dark:text-slate-200 font-semibold',
            'nilai_neg' => 'text-red-500 font-semibold',
            'rumus'     => 'text-slate-400 dark:text-slate-500',
        ],
        'total_hpp' => [
            'row'       => 'bg-orange-50 dark:bg-orange-900/10 border-t border-orange-100 dark:border-orange-800',
            'kode'      => 'text-orange-300',
            'label'     => 'text-orange-600 dark:text-orange-300 font-semibold',
            'nilai_pos' => 'text-orange-600 dark:text-orange-300 font-semibold',
            'nilai_neg' => 'text-red-500 font-semibold',
            'rumus'     => 'text-orange-400 dark:text-orange-600',
        ],
        'laba_kotor' => [
            'row'       => 'bg-blue-50 dark:bg-blue-900/20 border-t-2 border-blue-200 dark:border-blue-700',
            'kode'      => 'text-blue-300 dark:text-blue-500',
            'label'     => 'text-blue-700 dark:text-blue-300 font-bold',
            'nilai_pos' => 'text-blue-700 dark:text-blue-300 font-bold',
            'nilai_neg' => 'text-red-500 font-bold',
            'rumus'     => 'text-blue-400 dark:text-blue-600',
        ],
        'total_beban' => [
            'row'       => 'bg-rose-50 dark:bg-rose-900/10 border-t border-rose-100 dark:border-rose-800',
            'kode'      => 'text-rose-300',
            'label'     => 'text-rose-600 dark:text-rose-300 font-semibold',
            'nilai_pos' => 'text-rose-600 dark:text-rose-300 font-semibold',
            'nilai_neg' => 'text-red-500 font-semibold',
            'rumus'     => 'text-rose-400 dark:text-rose-600',
        ],
        'laba_usaha' => [
            'row'       => 'bg-indigo-50 dark:bg-indigo-900/20 border-t-2 border-indigo-200 dark:border-indigo-700',
            'kode'      => 'text-indigo-300 dark:text-indigo-500',
            'label'     => 'text-indigo-700 dark:text-indigo-300 font-bold',
            'nilai_pos' => 'text-indigo-700 dark:text-indigo-300 font-bold',
            'nilai_neg' => 'text-red-500 font-bold',
            'rumus'     => 'text-indigo-400 dark:text-indigo-600',
        ],
        'laba_sebelum_pajak' => [
            'row'       => 'bg-violet-50 dark:bg-violet-900/20 border-t-2 border-violet-300 dark:border-violet-600',
            'kode'      => 'text-violet-300 dark:text-violet-500',
            'label'     => 'text-violet-700 dark:text-violet-300 font-bold',
            'nilai_pos' => 'text-violet-700 dark:text-violet-300 font-bold',
            'nilai_neg' => 'text-red-500 font-bold',
            'rumus'     => 'text-violet-400 dark:text-violet-600',
        ],
        default => [
            'row'       => 'bg-gray-50 dark:bg-gray-800 border-t border-gray-200',
            'kode'      => 'text-gray-400',
            'label'     => 'text-gray-600 dark:text-gray-300 font-semibold',
            'nilai_pos' => 'text-gray-700 dark:text-gray-200 font-semibold',
            'nilai_neg' => 'text-red-500 font-semibold',
            'rumus'     => 'text-gray-400',
        ],
    };
@endphp

<tr class="{{ $styleMap['row'] }}">
    <td class="px-6 py-2.5 text-xs font-mono {{ $styleMap['kode'] }}">{{ $kode ?? '' }}</td>
    <td class="px-6 py-2.5">
        <p class="text-sm {{ $styleMap['label'] }} uppercase tracking-wide">{{ $label }}</p>
        @if(!empty($rumus))
            <p class="text-xs {{ $styleMap['rumus'] }} mt-0.5">{{ $rumus }}</p>
        @endif
    </td>
    <td class="px-6 py-2.5 text-right text-sm {{ $nilai >= 0 ? $styleMap['nilai_pos'] : $styleMap['nilai_neg'] }}">
        {{ 'Rp ' . number_format(abs($nilai), 0, ',', '.') }}
        @if($nilai < 0)
            <span class="text-xs font-normal ml-1">(Rugi)</span>
        @endif
    </td>
</tr>