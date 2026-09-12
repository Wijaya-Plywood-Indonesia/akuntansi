<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalLampiran extends Model
{
    protected $fillable = [
        'jurnal',
        'paths',
        'uploaded_by',
    ];

    protected $casts = [
        'jurnal' => 'integer',
        'paths'  => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Ambil (atau siapkan instance baru, belum disimpan) lampiran untuk
     * satu nomor jurnal tertentu — dipakai untuk isi form modal upload,
     * baik dari halaman Jurnal Pembantu Headers maupun Jurnal Umum.
     */
    public static function untukJurnal(int $jurnal): self
    {
        return static::firstOrNew(['jurnal' => $jurnal]);
    }

    /**
     * Pindahkan lampiran dari nomor jurnal lama ke nomor baru — dipakai saat
     * nomor jurnal di-renumber (misal karena tabrakan waktu posting).
     *
     * AMAN terhadap tabrakan: kalau nomor TUJUAN kebetulan sudah punya
     * lampiran sendiri (baik dari testing lama atau tabrakan renumber
     * berantai), foto-nya di-GABUNG (union path, tanpa duplikat) alih-alih
     * asal ->update() yang akan melanggar unique constraint kolom `jurnal`.
     */
    public static function pindahkanKe(int $dariJurnal, int $keJurnal, int $userId): void
    {
        if ($dariJurnal === $keJurnal) {
            return;
        }

        $sumber = static::where('jurnal', $dariJurnal)->first();

        if (! $sumber) {
            return;
        }

        $tujuan = static::where('jurnal', $keJurnal)->first();

        if ($tujuan) {
            $gabungan = array_values(array_unique(array_merge(
                $tujuan->paths ?? [],
                $sumber->paths ?? [],
            )));

            $tujuan->update([
                'paths'       => $gabungan,
                'uploaded_by' => $userId,
            ]);

            $sumber->delete();
        } else {
            $sumber->update(['jurnal' => $keJurnal]);
        }
    }

    /**
     * Hapus satu path foto dari lampiran (dipakai tombol "hapus" di UI
     * preview). Menghapus file fisiknya juga dari disk 'public', dan kalau
     * paths jadi kosong sekalian hapus baris lampiran-nya.
     */
    public function hapusFoto(string $path): void
    {
        $sisa = array_values(array_filter($this->paths ?? [], fn ($p) => $p !== $path));

        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);

        if (empty($sisa)) {
            $this->delete();
        } else {
            $this->update(['paths' => $sisa]);
        }
    }
}