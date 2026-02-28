<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnakAkun extends Model
{
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

    public function subAnakAkuns(): HasMany
    {
        return $this->hasMany(SubAnakAkun::class, 'id_anak_akun');
    }
    /**
     * Parent Self Reference
     */
    public function parentAkun()
    {
        return $this->belongsTo(self::class, 'parent');
    }

    public function children(): HasMany
{
    return $this->hasMany(self::class, 'parent');
}

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scope
    |--------------------------------------------------------------------------
    */

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }
}