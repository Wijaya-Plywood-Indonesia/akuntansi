<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jurnal_pembantu_headers', function (Blueprint $table) {

            $table->id();

            // ══════════════════════════════════════════════════
            // NOMOR & TANGGAL
            // ══════════════════════════════════════════════════

            $table->unsignedInteger('no_jurnal_pembantu')->index();
            // Nomor urut internal jurnal pembantu.
            // Di-generate otomatis oleh sistem, TERPISAH dari
            // kolom `jurnal` di jurnal_umums.

            $table->date('tgl_transaksi')->nullable();
            // Tanggal kejadian asli jika berbeda dari tgl pencatatan.
            // Contoh: barang dikirim tgl 19/01, dijurnal tgl 20/01.
            // Saat posting ke jurnal_umums, kolom ini yang dipakai
            // sebagai `tgl`. Jika null, fallback ke DATE(created_at).

            // ══════════════════════════════════════════════════
            // JENIS & SUMBER
            // ══════════════════════════════════════════════════

            $table->string('jenis_transaksi', 50);
            // Kode jenis transaksi:
            //   'bm'       = Bukti Masuk / Pembelian
            //   'bk'       = Bukti Keluar / Penjualan
            //   'dp'       = Down Payment / Pelunasan
            //   'gaji'     = Penggajian
            //   'produksi' = Jurnal dari proses produksi
            //   'balik'    = Jurnal Balik
            //   'lain'     = Lain-lain

            $table->string('modul_asal', 50)->nullable();
            // Modul yang menghasilkan transaksi ini.
            // Contoh: 'penjualan', 'pembelian', 'produksi_kupasan',
            //         'produksi_dryer', 'produksi_hotpress', 'penggajian'

            // ══════════════════════════════════════════════════
            // REFERENSI KE JURNAL UMUM
            // ══════════════════════════════════════════════════

            $table->unsignedInteger('jurnal')->index();
            // Nomor jurnal umum (ref ke jurnal_umums.jurnal).
            // Satu nomor jurnal bisa dimiliki BANYAK header
            // (misal: penjualan = 5 header dengan jurnal yang sama).
            // Balance D=K per nomor jurnal divalidasi di service
            // sebelum posting, BUKAN di level database.

            // ══════════════════════════════════════════════════
            // AKUN
            // ══════════════════════════════════════════════════

            $table->string('no_akun', 20);
            // Kode akun mengacu ke sub_anak_akuns.kode_sub_anak_akun
            // (e.g. '115-08', '210-02'). Disimpan sebagai string
            // karena format kode akun bisa mengandung karakter non-numerik.

            $table->string('nama_akun', 255)->nullable();
            // Cache nama akun saat jurnal dibuat.
            // Di-sync ulang oleh service jika CoA berubah sebelum posting.

            $table->enum('map', ['d', 'k']);
            // Posisi akun: 'd' = Debet, 'k' = Kredit.

            // ══════════════════════════════════════════════════
            // KETERANGAN & DOKUMEN
            // ══════════════════════════════════════════════════

            $table->text('keterangan');
            // Narasi lengkap transaksi. Disalin ke jurnal_umums.keterangan
            // saat di-posting.

            $table->string('no_dokumen', 100)->nullable();
            // [FIX v2] Kolom ini sebelumnya terkomentari di dalam blok
            // keterangan sehingga tidak terbuat. Sekarang dipindah keluar.
            // Isi: No. DO / INV / PO / Surat Jalan / dll.

            $table->text('catatan_internal')->nullable();
            // Catatan operator untuk keperluan internal.
            // TIDAK diteruskan ke jurnal_umums.

            // ══════════════════════════════════════════════════
            // NILAI — CACHE UNTUK VALIDASI BALANCE
            // ══════════════════════════════════════════════════

            $table->decimal('total_nilai', 20, 4)->default(0);
            // [BARU v2] Total nilai header ini (hasil SUM dari items).
            // Di-update otomatis setiap kali items berubah melalui service.
            // Digunakan untuk validasi balance: SUM(total_nilai WHERE map='d')
            // harus == SUM(total_nilai WHERE map='k') per nomor jurnal
            // sebelum boleh diposting.
            // Jika tidak ada items (jurnal non-barang), isi langsung di sini.

            // ══════════════════════════════════════════════════
            // STATUS & ALUR KERJA
            // ══════════════════════════════════════════════════

            $table->string('status', 20)->default('draft')->index();
            // Alur status:
            //   'draft'      → sedang diinput, bisa diedit bebas
            //   'diposting'  → sudah diposting ke jurnal_umums, TERKUNCI
            //   'dibalik'    → sudah dibalik oleh jurnal balik, TERKUNCI
            //   'dibatalkan' → dibatalkan sebelum sempat diposting

            // ══════════════════════════════════════════════════
            // RELASI JURNAL BALIK
            // ══════════════════════════════════════════════════

            $table->boolean('adalah_jurnal_balik')->default(false);
            // true jika jurnal ini dibuat untuk membalik jurnal lain.

            $table->foreignId('membalik_id')
                ->nullable()
                ->constrained('jurnal_pembantu_headers')
                ->nullOnDelete();
            // FK ke header yang dibalik oleh jurnal ini.
            // Contoh: jurnal balik no. 1050 membalik jurnal no. 751
            //         → membalik_id = id dari jurnal no. 751.

            // ══════════════════════════════════════════════════
            // AUDIT TRAIL
            // ══════════════════════════════════════════════════

            $table->foreignId('dibuat_oleh')
                ->constrained('users')
                ->comment('User yang pertama kali membuat record ini');

            $table->foreignId('diubah_oleh')
                ->nullable()
                ->constrained('users')
                ->comment('User yang terakhir mengubah record ini');

            $table->foreignId('diposting_oleh')
                ->nullable()
                ->constrained('users')
                ->comment('User yang melakukan posting ke jurnal umum');

            $table->timestamp('tgl_posting')->nullable();
            // [BARU v2] Timestamp eksak saat header ini diposting.
            // Tidak bisa digantikan updated_at karena updated_at
            // bisa berubah karena alasan lain (edit nama akun, dll).

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_pembantu_headers');
    }
};