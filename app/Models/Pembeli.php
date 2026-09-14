<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembeli extends Model
{
    //
    protected $fillable = [
        'nama',
        'nik',
        'alamat',
        'telepon',
        'email',
    ];

    /** ============================
     *  RELASI
     *  ============================ */

    // 1 pembeli bisa punya banyak rekening
    public function rekening()
    {
        return $this->hasMany(RekeningPembeli::class);
    }

    // 1 pembeli bisa muncul di banyak baris jurnal (buku pembantu piutang)
    public function jurnalUmums()
    {
        return $this->hasMany(JurnalUmum::class, 'id_pembeli');
    }
}