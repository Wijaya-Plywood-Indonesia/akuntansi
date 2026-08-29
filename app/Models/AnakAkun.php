<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnakAkun extends Model
{
    protected $fillable = [
        'id_induk_akun',
        'parent',
        'kode_anak_akun',
        'nama_anak_akun',
        'keterangan',
        'saldo_normal',
        'status',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function indukAkun(): BelongsTo
    {
        return $this->belongsTo(IndukAkun::class, 'id_induk_akun');
    }

    public function parentAkun(): BelongsTo
    {
        return $this->belongsTo(AnakAkun::class, 'parent');
    }

    /**
     * Children rekursif — otomatis eager-load subAnakAkuns
     * dan children-nya sendiri di tiap level, tanpa perlu
     * nulis manual "children.children.children..." di controller.
     */
    public function children(): HasMany
    {
        return $this->hasMany(AnakAkun::class, 'parent')
            ->orderByRaw("CAST(SUBSTRING_INDEX(kode_anak_akun, '.', 1) AS UNSIGNED) asc")
            ->orderByRaw("CAST(SUBSTRING_INDEX(kode_anak_akun, '.', -1) AS UNSIGNED) asc")
            ->with([
                'subAnakAkuns' => function ($q) {
                    $q->orderByRaw("CAST(SUBSTRING_INDEX(kode_sub_anak_akun, '.', 1) AS UNSIGNED) asc")
                        ->orderByRaw("CAST(SUBSTRING_INDEX(kode_sub_anak_akun, '.', -1) AS UNSIGNED) asc");
                },
                'children', // rekursif ke dirinya sendiri
            ]);
    }

    public function subAnakAkuns(): HasMany
    {
        return $this->hasMany(SubAnakAkun::class, 'id_anak_akun');
    }

    public function akunGroups()
    {
        return $this->belongsToMany(
            AkunGroup::class,
            'akun_group_anak_akun',
            'anak_akun_id',
            'akun_group_id'
        )->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }
}
