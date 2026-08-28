<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    //
    protected $fillable = [
        'no_nota',
        'tanggal',
        'nama_customer',
        'is_member',
        'alamat',
        'metode_pembayaran',
        'bank',
        'no_rekening',
        'kendaraan',
        'nama_sopir',
        'sub_total',
        'ppn_persen',
        'ppn_nominal',
        'total',
        'bayar',
        'kembalian',
        'user_id',
        'validated_by',
        'plat_kendaraan',
        'status_transaksi',
        'keterangan',
        'keterangan_pembayaran',
        'toko_id',
        'bayar_tunai',
        'bayar_transfer',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'sub_total' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'bayar_tunai' => 'decimal:2',
        'bayar_transfer' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'is_member' => 'boolean',
        'user_id' => 'integer',
        'validated_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function user_return()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function rekeningPerusahaan()
    {
        return $this->belongsTo(
            RekeningPerusahaan::class,
            'no_rekening',   // kolom di penjualans
            'no_rekening'    // kolom di rekening_perusahaan
        );
    }

    public function toko()
    {
        return $this->belongsTo(IdentitasToko::class, 'toko_id');
    }

    public function returns()
    {
        return $this->hasMany(ReturnPenjualan::class, 'no_nota', 'no_nota');
    }
}
