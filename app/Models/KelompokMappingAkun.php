<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelompokMappingAkun extends Model
{
    use HasUserStamps;

    protected $table = 'kelompok_mapping_akun';
    protected $primaryKey = 'id';

    protected $fillable = [
        'kode_kelompok',
        'nama_kelompok',
        'kode_proses',
        'keterangan',
        'dibuat_oleh',
        'diedit_oleh',
    ];

    public function mappingAkun(): HasMany
    {
        return $this->hasMany(MappingAkunProduksi::class, 'id_kelompok')
            ->orderBy('urutan');
    }
}