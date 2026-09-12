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
}