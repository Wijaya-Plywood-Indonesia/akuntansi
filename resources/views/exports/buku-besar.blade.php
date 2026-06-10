<table>
    <thead>
        <tr>
            <th colspan="10" style="font-weight: bold; font-size: 16px; text-align: center; background-color: #2d6a4f; color: #ffffff; border: 1px solid #000000; height: 35px; vertical-align: middle;">LAPORAN BUKU BESAR</th>
        </tr>
        <tr>
            <th colspan="10" style="font-weight: bold; font-size: 11px; text-align: center; background-color: #d8f3dc; color: #1b4332; border: 1px solid #000000; height: 25px; vertical-align: middle;">
                Periode: {{ \Carbon\Carbon::parse($filterBulan)->locale('id')->isoFormat('MMMM YYYY') }}
            </th>
        </tr>
        <tr></tr>
    </thead>
    <tbody>
        @foreach($indukAkuns as $induk)
            @php
                $totalInduk = $exporter->getTotalRecursive($induk);
                
                $adaMutasiInduk = collect(array_keys($saldoMap))->contains(function ($kode) use ($induk) {
                    return $induk->anakAkuns->whereNull('parent')->contains(function ($anak) use ($kode) {
                        if (($anak->kode_anak_akun ?? null) === $kode) return true;
                        foreach (($anak->subAnakAkuns ?? collect()) as $sub) {
                            if ($sub->kode_sub_anak_akun === $kode) return true;
                        }
                        foreach (($anak->children ?? collect()) as $child) {
                            if (($child->kode_anak_akun ?? null) === $kode) return true;
                            foreach (($child->subAnakAkuns ?? collect()) as $sub) {
                                if ($sub->kode_sub_anak_akun === $kode) return true;
                            }
                        }
                        return false;
                    });
                });
            @endphp

            @if($adaMutasiInduk || $totalInduk != 0)
                <tr>
                    <td colspan="9" style="font-weight: bold; background-color: #52b788; color: #ffffff; font-size: 11px; border: 1px solid #000000; height: 25px; vertical-align: middle;">
                        [{{ $induk->kode_induk_akun }}] {{ $induk->nama_induk_akun }}
                    </td>
                    <td style="font-weight: bold; background-color: #52b788; color: #ffffff; text-align: right; font-size: 11px; border: 1px solid #000000; height: 25px; vertical-align: middle;">
                        Rp {{ number_format($totalInduk, 0, ',', '.') }}
                    </td>
                </tr>

                @foreach($induk->anakAkuns->whereNull('parent') as $anak)
                    @include('exports.buku-besar-item', ['akun' => $anak, 'depth' => 0])
                @endforeach
                
                <tr></tr>
            @endif
        @endforeach
    </tbody>
</table>
