<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AkunGroup extends Model
{
    use HasFactory;

    protected $table = 'akun_groups';

    protected $fillable = [
        'nama',
        'parent_id',
        'order',
        'hidden',
        'tipe',
        'kategori_arus_kas',
    ];

    protected $casts = [
        'hidden' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Many-to-many: AkunGroup <-> AnakAkun
     */
    public function anakAkuns()
    {
        return $this->belongsToMany(
            AnakAkun::class,
            'akun_group_anak_akun',
            'akun_group_id',
            'anak_akun_id'
        )->withTimestamps();
    }

    /**
     * Parent Group
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Children Group
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if group is leaf (tidak punya child)
     */
    public function isLeaf(): bool
    {
        return !$this->children()->exists();
    }

    /**
     * Check if group punya child
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Recursive children (untuk laporan)
     */
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Scope hanya grup yang sudah ditandai kategori arus kas.
     */
    public function scopeBerkategoriArusKas($query)
    {
        return $query->whereNotNull('kategori_arus_kas');
    }

    /**
     * Label kategori arus kas untuk ditampilkan di UI.
     */
    public static function labelKategoriArusKas(): array
    {
        return [
            'penjualan'      => 'Penjualan',
            'pendanaan'      => 'Pendanaan (modal, utang, piutang, pinjaman)',
            'pembelian_stok' => 'Pembelian / Stok',
            'produksi'       => 'Produksi',
            'beban_usaha'    => 'Beban Operasional',
            'lainnya'        => 'Lainnya',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope hanya group leaf
     */
    public function scopeLeaf($query)
    {
        return $query->doesntHave('children');
    }

    /**
     * Scope hanya yang visible
     */
    public function scopeVisible($query)
    {
        return $query->where('hidden', false);
    }

    /**
     * Scope urut berdasarkan order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Total akun yang terdaftar di grup ini, mencakup Anak Akun.
     *
     * Untuk grup yang punya children (bukan leaf), dihitung rekursif dari
     * seluruh children-nya, di kedalaman berapa pun.
     */
    public function getTotalAnakAkunsAttribute(): int
    {
        if (! $this->hasChildren()) {
            return $this->anakAkuns()->count();
        }

        return $this->children
            ->sum(fn(self $child) => $child->total_anak_akuns);
    }
}