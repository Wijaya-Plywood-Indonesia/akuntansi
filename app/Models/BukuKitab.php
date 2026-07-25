<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuKitab extends Model
{
    protected $table = 'buku_kitabs';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function akunDetail()
    {
        return $this->hasMany(BukuKitabAkun::class)->orderBy('urut');
    }

    /**
     * Helper: ambil template baris akun untuk suatu jenis transaksi
     * berdasarkan kode-nya. Dipakai oleh modul lain (Penjualan, Pembelian,
     * dll) supaya tidak perlu hardcode no_akun di kode program.
     *
     * Contoh pemakaian:
     *   $baris = BukuKitab::templateAkun('PENJUALAN_LOGCORE');
     *   foreach ($baris as $b) {
     *       // insert ke JurnalPembantuHeader/Item pakai $b->no_akun, $b->posisi
     *   }
     */
    public static function templateAkun(string $kode)
    {
        return static::where('kode', $kode)
            ->where('is_active', true)
            ->with('akunDetail')
            ->first()
            ?->akunDetail
            ?? collect();
    }
}