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
        'pembeli_id',
        'is_member',
        'alamat',
        'metode_pembayaran',
        'jenis_transaksi', // COD | BAYAR_DIMUKA | DP
        'bank',
        'no_rekening',
        'kendaraan',
        'nama_sopir',
        'sub_total',
        'ppn_persen',
        'ppn_nominal',
        'ongkir',
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
        'dibatalkan_oleh',
        'dibatalkan_pada',
        'alasan_batal',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'sub_total' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'ongkir' => 'decimal:2',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'bayar_tunai' => 'decimal:2',
        'bayar_transfer' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'is_member' => 'boolean',
        'user_id' => 'integer',
        'validated_by' => 'integer',
        'dibatalkan_oleh' => 'integer',
        'dibatalkan_pada' => 'datetime',
    ];

    public const STATUS_DIBATALKAN = 'DIBATALKAN';

    /**
     * Hanya nota yang TIDAK dibatalkan. Status NULL (nota POS yang belum
     * divalidasi) tetap ikut, makanya tidak cukup pakai "!= DIBATALKAN".
     */
    public function scopeAktif($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status_transaksi')
                ->orWhere('status_transaksi', '!=', self::STATUS_DIBATALKAN);
        });
    }

    public function isDibatalkan(): bool
    {
        return $this->status_transaksi === self::STATUS_DIBATALKAN;
    }

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

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class);
    }

    public function user_return()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function pembatal()
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
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

    /**
     * Total nilai retur yang sudah divalidasi (status DITERIMA/SELESAI)
     * DAN berjenis retur piutang (terjadi saat nota belum lunas).
     * Dipakai di PenjualanPelunasanService::sisaTagihan() untuk
     * mengurangi tagihan sehingga pelunasan tinggal membayar sisanya.
     */
    public function totalReturPiutangDivalidasi(): int
    {
        return (int) $this->returns()
            ->whereIn('status_return', ['DITERIMA', 'SELESAI'])
            ->where('is_retur_piutang', true)
            ->sum('total');
    }
}