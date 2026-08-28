<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuKitabAkun extends Model
{
    protected $table = 'buku_kitab_akuns';

    protected $fillable = [
        'buku_kitab_id',
        'urut',
        'no_akun',
        'nama_akun',
        'posisi',
        'keterangan',
        'variabel_nilai',
    ];

    public function bukuKitab()
    {
        return $this->belongsTo(BukuKitab::class);
    }
}