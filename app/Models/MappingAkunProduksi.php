<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingAkunProduksi extends Model
{
    use HasUserStamps;

    protected $table = 'mapping_akun_produksi';

    protected $fillable = [
        'id_kelompok',
        'sub_anak_akun_id',
        'posisi_jurnal',
        'urutan',
        'keterangan',
        'status',
        'dibuat_oleh',
        'diedit_oleh',
    ];

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokMappingAkun::class, 'id_kelompok');
    }

    public function subAnakAkun(): BelongsTo
    {
        return $this->belongsTo(SubAnakAkun::class, 'sub_anak_akun_id'); // ← standar FK integer
    }
}