<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_DIKIRIM   = 'dikirim';
    const STATUS_DISETUJUI = 'disetujui';
    const STATUS_SELESAI   = 'selesai';
    const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'created_by',
        'nomor_po',
        'tanggal',
        'tanggal_jatuh_tempo',
        'foto',
        'supplier_id',
        'supplier_name',
        'supplier_phone',
        'supplier_address',
        'supplier_bank',
        'supplier_no_rek',
        'supplier_atas_nama',
        'sub_total',
        'total_diskon',
        'total_ppn',
        'ongkir',
        'biaya_lain',
        'grand_total',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal'            => 'date',
        'tanggal_jatuh_tempo'=> 'date',
        'foto'               => 'array',
        'sub_total'          => 'decimal:2',
        'total_diskon'       => 'decimal:2',
        'total_ppn'          => 'decimal:2',
        'ongkir'             => 'decimal:2',
        'biaya_lain'         => 'decimal:2',
        'grand_total'        => 'decimal:2',
    ];

    public static function labelStatus(): array
    {
        return [
            self::STATUS_DRAFT      => 'Draft',
            self::STATUS_DIKIRIM    => 'Dikirim ke Supplier',
            self::STATUS_DISETUJUI  => 'Disetujui',
            self::STATUS_SELESAI    => 'Selesai',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    public static function warnaBadgeStatus(): array
    {
        return [
            self::STATUS_DRAFT      => 'gray',
            self::STATUS_DIKIRIM    => 'info',
            self::STATUS_DISETUJUI  => 'warning',
            self::STATUS_SELESAI    => 'success',
            self::STATUS_DIBATALKAN => 'danger',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function hitungGrandTotal(
        ?float $subTotal    = null,
        ?float $totalDiskon = null,
        ?float $totalPpn    = null,
        ?float $ongkir      = null,
        ?float $biayaLain   = null,
    ): float {
        return max(
            0.0,
            ($subTotal    ?? (float) $this->sub_total)
                - ($totalDiskon ?? (float) $this->total_diskon)
                + ($totalPpn    ?? (float) $this->total_ppn)
                + ($ongkir      ?? (float) $this->ongkir)
                + ($biayaLain   ?? (float) $this->biaya_lain)
        );
    }
}
