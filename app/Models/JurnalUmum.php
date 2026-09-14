<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    // Inisiasi table 
    protected $guarded = [];

    protected $fillable = [
        'tgl',
        'jurnal',
        'no_akun',
        'id_barang',
        'id_pembeli',
        'nama_akun',
        'nama',
        'banyak',
        'mm',
        'm3',
        'harga',
        'keterangan',
        'hit_kbk',
        'no-dokumen',
        'map'
    ];

    // Database Casting
    protected $casts = [
        'tgl' => 'date',
        'jurnal' => 'integer',
        'no_akun' => 'string',
        'banyak' => 'decimal:4',
        'harga' => 'decimal:2',
    ];

    // Accessor and Mutator to map no_dokumen (used in PHP/Blade/Livewire) to no-dokumen (database column)
    public function getNoDokumenAttribute()
    {
        return $this->attributes['no-dokumen'] ?? null;
    }

    public function setNoDokumenAttribute($value)
    {
        $this->attributes['no-dokumen'] = $value;
    }

    // RelationShip
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli');
    }

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

    // Nilai baris jurnal, mengikuti hit_kbk (samakan dengan rumus di
    // Filament\Pages\JurnalUmum agar Debit/Kredit di sini konsisten
    // dengan yang ditampilkan di halaman Jurnal Umum & Buku Pembantu):
    //   b -> banyak * harga | m -> m3 * harga | selain itu -> harga
    public function getNilaiAttribute()
    {
        $hitKbk = strtolower((string) $this->hit_kbk);

        return match ($hitKbk) {
            'b' => (float) $this->banyak * (float) $this->harga,
            'm' => (float) $this->m3 * (float) $this->harga,
            default => (float) $this->harga,
        };
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

    /**
     * Sama seperti di JurnalPembantuHeader — bersihkan lampiran yang jadi
     * sampah kalau nomor jurnal ini (setelah baris JurnalUmum dihapus)
     * ternyata sudah tidak punya baris apa pun lagi sama sekali, baik di
     * sini maupun di jurnal_pembantu_headers.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $jurnal) {
            JurnalPembantuHeader::bersihkanLampiranJikaKosong((int) $jurnal->jurnal);
        });
    }
}