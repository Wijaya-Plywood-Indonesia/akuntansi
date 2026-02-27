<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    // Inisiasi table 
    protected $guarded = [];

    protected $fillable = [
        'tanggal',
        'nomor_jurnal',
        'nomor_akun',
        'nama_akun',
        'keterangan',
        'banyak',
        'harga',
        'created_by',
        'status',
        'synced_at',
        'synced_by',
    ];

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    // Database Casting
    protected $casts = [
        'tanggal' => 'date',
        'nomor_jurnal' => 'integer',
        'no_akun' => 'string',
        'banyak' => 'decimal:4',
        'harga' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    // RelationShip
    public function subAkun()
    {
        return $this->belongsTo(
            SubAnakAkun::class,
            'no_akun',
            'kode_sub_anak_akun'
        );
    }
    public function anakAkun()
    {
        return $this->subAkun?->anakAkun();
    }

    public function indukAkun()
    {
        return $this->subAkun?->indukAkun();
    }

    // Perhitungan Debit dan Kredit
    public function getDebitAttribute()
    {
        return in_array(strtolower($this->map), ['d', 'debit'])
            ? $this->nilai
            : 0;
    }

    public function getKreditAttribute()
    {
        return in_array(strtolower($this->map), ['k', 'kredit'])
            ? $this->nilai
            : 0;
    }
}
