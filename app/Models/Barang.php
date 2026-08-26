<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Barang extends Model
{
    //
    protected $table = 'barangs';

    protected $fillable = [
        'kode_barang',
        'barcode',
        'nama_barang',
        'id_sub_anak_akun',
        'id_kategori',
        'id_satuan',
        'harga_beli',
        'harga_jual',
        'stok_minimum',
        'is_active',
        'akun_pendapatan_id',
        'akun_hpp_id',
    ];

    protected $casts = [
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'id_satuan');
    }

    public function stok_toko()
    {
        return $this->hasOne(StokBarangToko::class, 'barang_id');
    }

    public function penjualanDetails()
    {
        return $this->hasMany(DetailPenjualan::class, 'barang_id');
    }

    public function stokBarangTokos()
    {
        // Pastikan foreign key 'barang_id' sesuai dengan di database
        return $this->hasMany(StokBarangToko::class, 'barang_id');
    }

    public function komposisi()
    {
        return $this->hasMany(Komposisi::class, 'id_barang');
    }

    // Barang ini dipakai sebagai bahan dalam detail komposisi
    public function detailKomposisi()
    {
        return $this->hasMany(DetailKomposisi::class, 'id_barang');
    }

    // Barang ini dipakai sebagai bahan aktual dalam produksi
    public function produksiPakanCampuran()
    {
        return $this->hasMany(ProduksiPakanCampuran::class);
    }

    public function produksiPakanMentah()
    {
        return $this->hasMany(ProduksiPakanMentah::class);
    }

    public function subAnakAkun()
    {
        // Parameter kedua adalah nama kolom foreign key yang kita buat di migration tadi
        return $this->belongsTo(SubAnakAkun::class, 'id_sub_anak_akun');
    }

    /** Relasi BARU: Akun Pendapatan */
    public function akunPendapatan()
    {
        return $this->belongsTo(SubAnakAkun::class, 'akun_pendapatan_id');
    }

    /** Relasi BARU: Akun HPP */
    public function akunHpp()
    {
        return $this->belongsTo(SubAnakAkun::class, 'akun_hpp_id');
    }

    public function getStokBukuBesarAttribute()
    {
        $subAkun = $this->subAnakAkun;
        $kodeAkun = $subAkun?->kode_sub_anak_akun;

        if (! $kodeAkun) {
            return 0.0;
        }

        // Baris jurnal yang sudah dikaitkan langsung ke barang ini (id_barang terisi)
        $transaksisById = JurnalUmum::where('id_barang', $this->id)
            ->select('map', DB::raw('SUM(COALESCE(banyak, 0)) as total_banyak'))
            ->groupBy('map')
            ->get();

        // Data lama (sebelum kolom id_barang ada) yang hanya bisa dikenali dari kode akun.
        // Sengaja dibiarkan sebagai fallback agar histori lama tidak hilang dari perhitungan,
        // meski untuk akun yang dipakai banyak barang, bagian ini masih tergabung (belum per-barang).
        $transaksisLegacy = JurnalUmum::where('no_akun', $kodeAkun)
            ->whereNull('id_barang')
            ->select('map', DB::raw('SUM(COALESCE(banyak, 0)) as total_banyak'))
            ->groupBy('map')
            ->get();

        $totalQty = 0.0;
        foreach ($transaksisById->concat($transaksisLegacy) as $trx) {
            $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
            $qty = (float) $trx->total_banyak;
            if ($isDebit) {
                $totalQty += $qty;
            } else {
                $totalQty -= $qty;
            }
        }

        return $totalQty;
    }

    /*
    |--------------------------------------------------------------------------
    | Stok versi Matrix (murni id_barang, tanpa fallback legacy no_akun)
    |--------------------------------------------------------------------------
    */

    /**
     * Accessor: $barang->stok_matrix
     * Total qty stok barang ini, logic-nya sama persis dengan StokMatrix::mount()
     * — murni matching via id_barang, TIDAK ada fallback ke no_akun.
     */
    public function getStokMatrixAttribute(): float
    {
        return $this->hitungStokMatrixQty();
    }

    /**
     * Accessor: $barang->stok_matrix_m3
     * Total m3 stok barang ini, logic-nya sama persis dengan StokMatrix::mount().
     */
    public function getStokMatrixM3Attribute(): float
    {
        return $this->hitungStokMatrixM3();
    }

    /**
     * Hitung qty stok murni dari Jurnal Umum berdasarkan id_barang.
     * Sama seperti StokMatrix: debit menambah, kredit mengurangi.
     */
    protected function hitungStokMatrixQty(): float
    {
        $transaksiPerMap = JurnalUmum::where('id_barang', $this->id)
            ->select('map', DB::raw('SUM(COALESCE(banyak, 0)) as total_qty'))
            ->groupBy('map')
            ->get();

        $totalQty = 0.0;
        foreach ($transaksiPerMap as $trx) {
            $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
            $qty = (float) $trx->total_qty;
            $totalQty += $isDebit ? $qty : -$qty;
        }

        return $totalQty;
    }

    /**
     * Hitung m3 stok murni dari Jurnal Umum berdasarkan id_barang.
     */
    protected function hitungStokMatrixM3(): float
    {
        $transaksiPerMap = JurnalUmum::where('id_barang', $this->id)
            ->select('map', DB::raw('SUM(COALESCE(m3, 0)) as total_m3'))
            ->groupBy('map')
            ->get();

        $totalM3 = 0.0;
        foreach ($transaksiPerMap as $trx) {
            $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
            $m3 = (float) $trx->total_m3;
            $totalM3 += $isDebit ? $m3 : -$m3;
        }

        return $totalM3;
    }
}
