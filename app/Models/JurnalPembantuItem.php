<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalPembantuItem extends Model
{
    protected $table = 'jurnal_pembantu_items';

    protected $fillable = [
        'jurnal_pembantu_header_id',
        'urut',
        'jenis_pihak',
        'pihak_id',
        'nama_pihak',
        'nama_barang',
        'no_dokumen',
        'no_referensi',
        'keterangan',
        'ukuran',
        'kualitas',
        'banyak',
        'm3',
        'harga',
        'hit_kbk',
        'jumlah',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'banyak' => 'decimal:2',
        'm3' => 'decimal:4',
        'harga' => 'decimal:6',
        'jumlah' => 'decimal:4',
        'status' => 'boolean',
    ];

    // ── Konstanta ─────────────────────────────────────────────────────

    const JENIS_PIHAK = [
        'pelanggan' => 'Pelanggan',
        'pemasok' => 'Pemasok',
        'karyawan' => 'Karyawan',
        'produksi' => 'Produksi',
        'lain' => 'Lain-lain',
    ];

    const HIT_KBK = [
        'k' => '× M³',
        'b' => '× Banyak',
        null => 'Langsung',
    ];

    // ── Relasi ────────────────────────────────────────────────────────

    public function header(): BelongsTo
    {
        return $this->belongsTo(JurnalPembantuHeader::class, 'jurnal_pembantu_header_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Hitung jumlah otomatis sebelum simpan ─────────────────────────

    protected static function booted(): void
    {
        static::created(function ($item) {
            // Hanya recalculate jika BUKAN dari sync kayu masuk
            if ($item->header?->modul_asal !== 'kayu_masuk') {
                $item->header->recalculateTotalNilai();
            }
        });


        static::updated(function ($item) {
            if ($item->header?->modul_asal !== 'kayu_masuk') {
                $item->header->recalculateTotalNilai();
            }
        });

        // Setelah item disimpan/dihapus, update total_nilai di header
        static::saved(function (self $item) {
            $item->header->recalculateTotalNilai('status'); // ← ganti, bukan tambah
        });

        static::deleted(function (self $item) {
            $item->header->recalculateTotalNilai('status'); // ← ganti, bukan tambah

        });
    }

    public function hitungJumlah(): float
    {
        return match ($this->hit_kbk) {
            'k' => (float) $this->harga * (float) ($this->m3 ?? 0),
            'b' => (float) $this->harga * (float) ($this->banyak ?? 0),
            default => (float) $this->jumlah, // isi langsung, tidak dihitung ulang
        };
    }
}
