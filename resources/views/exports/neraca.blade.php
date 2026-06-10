<table>
    <thead>
        <tr>
            <th colspan="8" style="font-weight: bold; font-size: 16px; text-align: center; border: 1px solid #000000;">LAPORAN NERACA</th>
        </tr>
        <tr></tr>
    </thead>
    <tbody>
        @foreach($neracaMulti as $key => $neraca)
            @php
            $isBalance = abs($neraca['totalAktiva'] - $neraca['totalPasiva']) < 1;

            $flattenSections = null;
            $flattenSections = function(array $sections, int $depth = 0) use (&$flattenSections): array {
                $rows = [];
                foreach ($sections as $section) {
                    $hasSub = !empty($section['sub_sections']);
                    $hasItem = !empty($section['items']);

                    $rows[] = [
                        'type' => $depth === 0 ? 'header' : 'subheader',
                        'label' => $section['group'],
                        'kode' => null,
                        'depth' => $depth,
                    ];

                    if ($hasSub) {
                        $rows = array_merge($rows, $flattenSections($section['sub_sections'], $depth + 1));
                        $rows[] = [
                            'type' => 'subtotal',
                            'label' => 'Total ' . $section['group'],
                            'kode' => null,
                            'nilai' => $section['total'],
                            'qty' => null,
                            'depth' => $depth,
                        ];
                    }

                    if ($hasItem) {
                        foreach ($section['items'] as $item) {
                            $rows[] = [
                                'type' => 'item',
                                'label' => $item['nama'],
                                'kode' => $item['kode'],
                                'nilai' => $item['nilai'],
                                'm3' => $item['m3'] ?? null,
                                'qty' => $item['qty'] ?? null,
                                'depth' => $depth,
                            ];
                        }
                        $rows[] = [
                            'type' => 'subtotal',
                            'label' => 'Total ' . $section['group'],
                            'kode' => null,
                            'nilai' => $section['total'],
                            'qty' => null,
                            'depth' => $depth,
                        ];
                    }
                }
                return $rows;
            };

            $aktivaRowsRaw = $flattenSections($neraca['aktiva']['sections']);
            $pasivaRowsRaw = $flattenSections($neraca['pasiva']['sections']);

            $filterRows = function(array $rows) use ($tampilkanSaldoNol): array {
                if ($tampilkanSaldoNol) return $rows;
                return array_values(array_filter($rows, function($row) {
                    if ($row['type'] === 'item' && ($row['nilai'] ?? 0) == 0) {
                        return false;
                    }
                    return true;
                }));
            };

            $aktivaRows = $filterRows($aktivaRowsRaw);
            $pasivaRows = $filterRows($pasivaRowsRaw);
            $maxRows = max(count($aktivaRows), count($pasivaRows), 1);
            @endphp

            <tr>
                <th colspan="8" style="font-weight: bold; font-size: 14px; background-color: #f7faf7; border: 1px solid #000000;">
                    Neraca &mdash; {{ $neraca['label'] }} (Status: {{ $isBalance ? 'Balance' : 'Tidak Balance' }})
                </th>
            </tr>

            <tr>
                <th colspan="4" style="font-weight: bold; background-color: #e6f2ff; text-align: center; border: 1px solid #000000;">AKTIVA</th>
                <th colspan="4" style="font-weight: bold; background-color: #e6ffe6; text-align: center; border: 1px solid #000000;">PASIVA</th>
            </tr>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <th style="border: 1px solid #000000;">Akun</th>
                <th style="border: 1px solid #000000; text-align: right;">Qty</th>
                <th style="border: 1px solid #000000; text-align: right;">m³</th>
                <th style="border: 1px solid #000000; text-align: right;">Nilai (Rp)</th>
                <th style="border: 1px solid #000000;">Akun</th>
                <th style="border: 1px solid #000000; text-align: right;">Qty</th>
                <th style="border: 1px solid #000000; text-align: right;">m³</th>
                <th style="border: 1px solid #000000; text-align: right;">Nilai (Rp)</th>
            </tr>

            @for($i = 0; $i < $maxRows; $i++)
                @php
                $aRow = $aktivaRows[$i] ?? null;
                $pRow = $pasivaRows[$i] ?? null;

                $rowType = $aRow['type'] ?? $pRow['type'] ?? 'item';
                $isHdr = $rowType === 'header';
                $isSub = $rowType === 'subheader';
                $isTot = $rowType === 'subtotal';
                $isItem = $rowType === 'item';

                $bgClass = '';
                if ($isHdr) {
                    $bgClass = 'background-color: #edf3ed; font-weight: bold;';
                } elseif ($isSub) {
                    $bgClass = 'background-color: #f7faf7; font-weight: bold;';
                } elseif ($isTot) {
                    $bgClass = 'background-color: #edf3ed; font-weight: bold;';
                }
                @endphp
                <tr>
                    @if($aRow)
                        @php
                        $aDepth = $aRow['depth'] ?? 0;
                        $padding = str_repeat('    ', $aDepth);
                        $itemBg = ($aRow['type'] === 'header' || $aRow['type'] === 'subheader' || $aRow['type'] === 'subtotal') ? $bgClass : '';
                        @endphp
                        <td style="border: 1px solid #000000; {{ $itemBg }}">
                            @if($aRow['type'] === 'item' && !empty($aRow['kode']))
                                [{{ $aRow['kode'] }}] {{ $aRow['label'] }}
                            @else
                                {{ $padding }}{{ $aRow['label'] }}
                            @endif
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ ($aRow['type'] === 'item' && !empty($aRow['qty'])) ? number_format($aRow['qty'], 0, ',', '.') : '' }}
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ ($aRow['type'] === 'item' && !empty($aRow['m3'])) ? number_format($aRow['m3'], 2, ',', '.') : '' }}
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ isset($aRow['nilai']) && $aRow['type'] !== 'header' && $aRow['type'] !== 'subheader' ? number_format($aRow['nilai'], 0, ',', '.') : '' }}
                        </td>
                    @else
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                    @endif

                    @if($pRow)
                        @php
                        $pDepth = $pRow['depth'] ?? 0;
                        $padding = str_repeat('    ', $pDepth);
                        $itemBg = ($pRow['type'] === 'header' || $pRow['type'] === 'subheader' || $pRow['type'] === 'subtotal') ? $bgClass : '';
                        @endphp
                        <td style="border: 1px solid #000000; {{ $itemBg }}">
                            @if($pRow['type'] === 'item' && !empty($pRow['kode']))
                                [{{ $pRow['kode'] }}] {{ $pRow['label'] }}
                            @else
                                {{ $padding }}{{ $pRow['label'] }}
                            @endif
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ ($pRow['type'] === 'item' && !empty($pRow['qty'])) ? number_format($pRow['qty'], 0, ',', '.') : '' }}
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ ($pRow['type'] === 'item' && !empty($pRow['m3'])) ? number_format($pRow['m3'], 2, ',', '.') : '' }}
                        </td>
                        <td style="border: 1px solid #000000; text-align: right; {{ $itemBg }}">
                            {{ isset($pRow['nilai']) && $pRow['type'] !== 'header' && $pRow['type'] !== 'subheader' ? number_format($pRow['nilai'], 0, ',', '.') : '' }}
                        </td>
                    @else
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                        <td style="border: 1px solid #000000;"></td>
                    @endif
                </tr>
            @endfor

            <tr style="background-color: #333333; color: #ffffff; font-weight: bold;">
                <td colspan="3" style="border: 1px solid #000000;">TOTAL AKTIVA</td>
                <td style="border: 1px solid #000000; text-align: right;">
                    {{ number_format($neraca['totalAktiva'], 0, ',', '.') }}
                </td>
                <td colspan="3" style="border: 1px solid #000000;">TOTAL PASIVA</td>
                <td style="border: 1px solid #000000; text-align: right;">
                    {{ number_format($neraca['totalPasiva'], 0, ',', '.') }}
                </td>
            </tr>
            
            <tr></tr>
            <tr></tr>
        @endforeach
    </tbody>
</table>
