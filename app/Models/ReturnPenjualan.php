<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnPenjualan extends Model
{
    protected $table = "penjualan_return";
    protected $guarded = ["id"];

    protected $casts = [
        'tanggal' => 'datetime',
        'sub_total' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'is_member' => 'boolean',
        'created_by' => 'integer',
        'validate_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function details()
    {
        return $this->hasMany(ReturnPenjualanDetail::class, 'id_return', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validate_by');
    }

    public function rekeningPerusahaan()
    {
        return $this->belongsTo(
            RekeningPerusahaan::class,
            'no_rekening',
            'no_rekening'
        );
    }

    public function subAnakAkunPengembalian()
    {
        return $this->belongsTo(
            SubAnakAkun::class,
            'akun_pengembalian',
            'kode_sub_anak_akun'
        );
    }

    public function bukuKitab()
    {
        return $this->belongsTo(BukuKitab::class, 'kode_kitab', 'kode');
    }

    public function toko()
    {
        return $this->belongsTo(IdentitasToko::class, 'toko_id');
    }

    public function details_return()
    {
        return $this->hasMany(ReturnPenjualanDetail::class, 'id_return', 'id');
    }

    public function jurnalPembantuHeaders()
    {
        return $this->hasMany(JurnalPembantuHeader::class, 'no_dokumen', 'no_retur')
            ->where('modul_asal', 'penjualan_return');
    }

    public function getNoJurnalAttribute(): ?int
    {
        return $this->jurnalPembantuHeaders()->first()?->jurnal;
    }
}
