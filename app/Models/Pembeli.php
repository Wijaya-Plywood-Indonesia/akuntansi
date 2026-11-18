<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembeli extends Model
{
    //
    protected $fillable = [
        'nama',
        'nik',
        'alamat',
        'telepon',
        'email',
    ];

    /** ============================
     *  RELASI
     *  ============================ */

    // 1 pembeli bisa punya banyak rekening
    public function rekening()
    {
        return $this->hasMany(RekeningPembeli::class);
    }

    // 1 pembeli bisa muncul di banyak baris jurnal (buku pembantu piutang)
    public function jurnalUmums()
    {
        return $this->hasMany(JurnalUmum::class, 'id_pembeli');
    }

    /**
     * Tentukan master Pembeli untuk sebuah nota.
     *
     * ID hanya dipakai kalau namanya masih sama dengan nama customer yang
     * tertulis di nota. Kalau beda (mis. kasir menimpa nama setelah memilih
     * dari daftar, atau ID basi terbawa dari localStorage), cari/buat master
     * berdasarkan nama. Nama kosong ditolak supaya tidak lahir pembeli kosong.
     */
    public static function resolveUntukNota(?int $id, ?string $nama, ?string $alamat = null, ?string $telepon = null): self
    {
        $nama = trim((string) $nama);

        if ($nama === '') {
            throw new \InvalidArgumentException('Nama customer wajib diisi.');
        }

        if ($id) {
            $pembeli = static::find($id);

            if ($pembeli && mb_strtolower(trim($pembeli->nama)) === mb_strtolower($nama)) {
                return $pembeli;
            }
        }

        // Kalau ada beberapa master bernama sama, pakai yang paling awal dibuat
        // (konsisten dengan data lama), bukan membuat duplikat baru.
        return static::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])->orderBy('id')->first()
            ?? static::create(['nama' => $nama, 'alamat' => $alamat, 'telepon' => $telepon]);
    }
}