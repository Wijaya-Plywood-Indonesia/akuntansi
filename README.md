# 📒 Akuntansi — PT. Wijaya Plywood Indonesia

> Sistem Akuntansi berbasis web untuk pengelolaan keuangan internal PT. Wijaya Plywood Indonesia, dapat diakses di **[akuntansi.wijayaplywoods.com](https://akuntansi.wijayaplywoods.com)**.

---

## 📋 Deskripsi

**Akuntansi** adalah aplikasi web internal berbasis Laravel yang dibangun untuk mengelola proses akuntansi perusahaan secara terintegrasi. Aplikasi ini menggunakan panel admin berbasis **FilamentPHP 5** dengan desain responsif dan antarmuka yang modern.

---

## ✨ Fitur Utama

| Modul | Deskripsi |
|-------|-----------|
| **Jurnal Umum** | Pencatatan transaksi keuangan harian |
| **Jurnal Pembantu** | Jurnal pembantu dengan header dan item detail |
| **Buku Besar** | Rekapitulasi per akun dari jurnal umum |
| **Neraca** | Laporan posisi keuangan perusahaan |
| **Chart of Account (COA)** | Struktur akun bertingkat: Induk Akun → Anak Akun → Sub Anak Akun |
| **Master Data** | Pengelolaan data master (vendor, customer, dll.) |
| **Penjualan** | Pencatatan dan pengelolaan transaksi penjualan |
| **Pembelian** | Pencatatan dan pengelolaan transaksi pembelian |

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend Framework | Laravel 12 |
| PHP Version | PHP ^8.2 |
| Admin Panel | FilamentPHP 5.0 |
| Role & Permission | Filament Shield 4.1 |
| Tree/Nested Data | kalnoy/nestedset 6.0 |
| Autentikasi API | Laravel Sanctum 4.0 |
| Realtime (opsional) | Pusher PHP Server 7.2 |
| Frontend Build | Vite + Tailwind CSS |
| Database Default | SQLite (dapat dikonfigurasi ke MySQL) |

---

## ⚙️ Requirements

Sebelum instalasi, pastikan environment memenuhi persyaratan berikut:

- PHP >= 8.2
- Composer
- Node.js & npm
- SQLite / MySQL / MariaDB
- Extension PHP: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`

---

## 🚀 Instalasi

### 1. Clone Repositori

```bash
git clone https://github.com/Wijaya-Plywood-Indonesia/akuntansi.git
cd akuntansi
```

### 2. Setup Otomatis (Rekomendasi)

```bash
composer run setup
```

Perintah ini akan secara otomatis menjalankan:
- `composer install`
- Menyalin `.env.example` → `.env`
- Generate application key
- Menjalankan migrasi database
- `npm install` & `npm run build`

### 3. Setup Manual (Langkah demi Langkah)

```bash
# Install dependensi PHP
composer install

# Salin file konfigurasi environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Jalankan migrasi database
php artisan migrate

# Install dependensi JavaScript
npm install

# Build asset frontend
npm run build
```

---

## 🔧 Konfigurasi Environment

Buka file `.env` dan sesuaikan konfigurasi berikut:

```env
APP_NAME="Akuntansi Wijaya"
APP_URL=http://localhost

# Konfigurasi Database (default: SQLite)
DB_CONNECTION=sqlite

# Untuk MySQL, uncomment dan isi berikut:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=akuntansi
# DB_USERNAME=root
# DB_PASSWORD=

# Konfigurasi Pusher (opsional, untuk fitur realtime)
# PUSHER_APP_ID=
# PUSHER_APP_KEY=
# PUSHER_APP_SECRET=
# PUSHER_HOST=
# PUSHER_PORT=443
# PUSHER_SCHEME=https
# PUSHER_APP_CLUSTER=mt1
```

---

## ▶️ Menjalankan Aplikasi

### Mode Development

```bash
composer run dev
```

Perintah ini akan menjalankan secara bersamaan:
- PHP development server (`php artisan serve`)
- Queue listener
- Log watcher (Pail)
- Vite dev server

Aplikasi akan tersedia di: **http://localhost:8000**

### Mode Production

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve
```

---

## 🧪 Testing

```bash
composer run test
```

---

## 📁 Struktur Direktori

```
akuntansi/
├── app/
│   ├── Filament/           # Resource, Pages, dan Widgets panel admin
│   ├── Models/             # Eloquent Models (dengan NestedSet untuk COA)
│   ├── Services/           # Business logic / Service layer
│   └── ...
├── config/                 # File konfigurasi Laravel
├── database/
│   ├── migrations/         # Migrasi database
│   ├── seeders/            # Data awal / seeder
│   └── factories/          # Factory untuk testing
├── public/                 # Asset publik & entry point web
├── resources/
│   ├── views/              # Blade templates (custom Filament pages)
│   └── css/ & js/          # Asset frontend
├── routes/                 # Definisi routing aplikasi
├── storage/                # Log, cache, file upload
├── tests/                  # Unit & Feature tests
├── .env.example            # Template konfigurasi environment
├── composer.json           # Dependensi PHP
└── package.json            # Dependensi JavaScript
```

---

## 🔐 Akses Panel Admin

Panel admin dibangun dengan **FilamentPHP** dan dapat diakses di:

```
http://localhost:8000/admin
```

Manajemen role dan permission menggunakan **Filament Shield**. Setelah instalasi, jalankan:

```bash
php artisan shield:generate --all
php artisan shield:super-admin --user=1
```

---

## 📦 Changelog / Releases

| Versi | Tanggal | Perubahan |
|-------|---------|-----------|
| **V.1.2.0** | 2 Jun 2026 | Master data + Modul Penjualan & Pembelian; update Migrations, Model, Blade, Filament Resource & Page, Service |
| **V.1.1.0** | 13 Mar 2026 | Penambahan Jurnal Pembantu (header & items) |
| **v1.0.0** | 3 Mar 2026 | Rilis perdana: Jurnal Umum, Buku Besar, Neraca, COA (Induk/Anak/Sub Anak Akun), desain responsif custom Filament |

Lihat semua rilis di: [GitHub Releases](https://github.com/Wijaya-Plywood-Indonesia/akuntansi/releases)

---

## 👥 Tim Pengembang

Dikembangkan oleh tim IT **PT. Wijaya Plywood Indonesia**.

---

## 📄 Lisensi

Proyek ini bersifat **privat** dan hanya digunakan untuk keperluan internal PT. Wijaya Plywood Indonesia.

---

*Dokumentasi ini akan terus diperbarui seiring perkembangan aplikasi.*
