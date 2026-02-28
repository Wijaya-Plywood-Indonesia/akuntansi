<div
    data-akun-wrapper
    data-akun-search="{{ strtolower($sub->kode_sub_anak_akun . ' ' . $sub->nama_sub_anak_akun) }}"
    class="akun-row flex items-center justify-between px-3 py-2 rounded-md border-l-4 border-emerald-400 dark:border-emerald-600 bg-white dark:bg-gray-800"
>
    <div class="flex items-center gap-2">
        <span class="text-emerald-400 text-xs">●</span>
        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $sub->kode_sub_anak_akun }}</span>
        <span class="text-sm text-gray-700 dark:text-gray-200">{{ $sub->nama_sub_anak_akun }}</span>
    </div>
    @if($sub->saldo_normal)
        <span class="badge-{{ $sub->saldo_normal }}">{{ strtoupper($sub->saldo_normal) }}</span>
    @endif
</div>