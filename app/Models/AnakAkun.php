<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnakAkun extends Model
{
    //
    protected $table = 'anak_akuns';

    protected $fillable = [
        'id_induk_akun',
        'kode_anak_akun',
        'nama_anak_akun',
        'keterangan',
        'parent',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

}
