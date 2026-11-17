# Hello BroSeven!

**Kelola Kehadiran, Tingkatkan Produktivitas.**

Hello BroSeven! adalah aplikasi web manajemen absensi karyawan berbasis **Laravel 13**. Aplikasi ini mengelola perusahaan, karyawan, jadwal kerja, absensi dengan **Face Recognition** dan **GPS Geofencing**, pengajuan izin, laporan kehadiran, serta perhitungan gaji harian (payroll).

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-3c6e71)

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Teknologi](#teknologi)
- [Peran Pengguna](#peran-pengguna)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi Lokal](#instalasi-lokal)
- [Konfigurasi](#konfigurasi)
- [Akun Default](#akun-default)
- [Model Face Recognition](#model-face-recognition)
- [Scheduler (Absent Otomatis)](#scheduler-absent-otomatis)
- [Struktur Proyek](#struktur-proyek)
- [Alur Absensi](#alur-absensi)
- [Perhitungan Gaji](#perhitungan-gaji)
- [Pengaturan Fitur Global](#pengaturan-fitur-global)
- [Keamanan](#keamanan)
- [Pengujian](#pengujian)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Fitur Utama

| Modul | Deskripsi |
|---|---|
| Landing Page | Halaman beranda publik dengan data perusahaan dari database |
| Autentikasi | Login manual, hash password, regenerasi session, rate limiting, remember me |
| Role & Akses | Super Admin, Admin, Karyawan dengan proteksi middleware di backend |
| Data Master | Jabatan, departemen, divisi, pendidikan, status karyawan, jenis izin, potongan gaji |
| Data Karyawan | CRUD lengkap, akun login otomatis, upload dokumen, filter, reset password |
| Data Jadwal | Shift, hari kerja, toleransi, assign per karyawan/departemen, kalender, hari libur |
| Face Recognition | Registrasi dan verifikasi wajah dengan face-api.js, liveness sederhana, threshold dapat dikonfigurasi |
| GPS Geofencing | Jarak dihitung dengan rumus Haversine, validasi ulang di backend, batas akurasi |
| Mode Kerja | WFO, WFH, Hybrid. WFH dikecualikan dari radius kantor, wajah tetap wajib |
| Absensi | Status hadir, terlambat, absent, izin; absent otomatis lewat Laravel Scheduler |
| Request Izin | Pengajuan dengan lampiran, approve/reject, catatan admin, riwayat |
| Laporan | Rekap kehadiran, export Excel, export PDF, print |
| Payroll | Gaji harian per jabatan, potongan per status, snapshot payroll, slip gaji |
| Profil Perusahaan | Logo, alamat, koordinat kantor, radius, zona waktu, jam operasional |
| Pengaturan Fitur | Aktif/nonaktifkan fitur global, dilindungi middleware dan cache |
| Audit Log | Pencatatan perubahan penting, termasuk koreksi absensi |

## Teknologi

- **Backend:** Laravel 13, PHP 8.3+, Eloquent ORM, Migration & Seeder
- **Database:** MySQL
- **Frontend:** Blade, Bootstrap 5 (dipasang manual), CSS custom, JavaScript Vanilla, Bootstrap Icons
- **Face Recognition:** [face-api.js](https://github.com/justadudewhohacks/face-api.js) dan HTML5 Canvas
- **Lokasi:** Browser Geolocation API
- **Pengujian:** PHPUnit / Pest bawaan Laravel

Proyek ini sengaja **tidak** memakai Breeze, Jetstream, Livewire, Inertia, Vue, React, Tailwind, jQuery, DataTables, SweetAlert, Alpine.js, maupun template admin pihak ketiga. Seluruh komponen UI (navbar, sidebar, modal, tabel, toast) dibuat sendiri.

### Palet Warna

| Kegunaan | Hex |
|---|---|
| Dark / Teks | `#353535` |
| Primary / Teal | `#3c6e71` |
| Background | `#ffffff` |
| Border / Abu-abu | `#d9d9d9` |
| Secondary / Navy | `#284b63` |

## Peran Pengguna

| Peran | Akses |
|---|---|
| **Super Admin** | Akses penuh, termasuk **Pengaturan Fitur** |
| **Admin** | Sama seperti Super Admin, kecuali Pengaturan Fitur |
| **Karyawan** | Hanya dashboard, absensi, riwayat, izin, jadwal, dan profil milik sendiri |

## Persyaratan Sistem

- PHP **8.3** atau lebih baru (atau versi minimum yang diminta Laravel 13)
- Ekstensi PHP: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- Composer 2.x
- MySQL 8 / MariaDB 10.6+
- Browser modern (Chrome, Edge, Firefox, Safari) dengan akses kamera dan lokasi
- **HTTPS** pada production (kamera dan Geolocation API hanya berjalan di HTTPS atau `localhost`)

## Instalasi Lokal

### 1. Clone repositori

```powershell
git clone https://github.com/ardanu020109/hello-broseven.git
cd hello-broseven
```

### 2. Install dependensi

```powershell
composer install
```

### 3. Siapkan environment

```powershell
copy .env.example .env
php artisan key:generate
```

### 4. Buat database

```sql
CREATE DATABASE hello_broseven CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Sesuaikan `.env`

```env
APP_NAME="Hello BroSeven!"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hello_broseven
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Migrasi, seeder, dan storage

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan optimize:clear
```

### 7. Jalankan aplikasi

```powershell
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Konfigurasi

Selain `.env` dasar, hal berikut dikelola dari dashboard:

| Pengaturan | Lokasi | Keterangan |
|---|---|---|
| Koordinat kantor, radius, zona waktu | Profile Perusahaan | Titik pusat geofence |
| Batas akurasi GPS | Profile Perusahaan / konfigurasi | Lokasi dengan akurasi buruk ditolak |
| Threshold kemiripan wajah | Konfigurasi absensi | Semakin kecil semakin ketat |
| Aktif/nonaktif fitur | Pengaturan Fitur (Super Admin) | Berlaku global untuk semua role |
| Persentase potongan gaji | Data Master → Potongan Gaji | Per status kehadiran |

Pastikan zona waktu aplikasi dan perusahaan konsisten (misalnya `Asia/Makassar`):

```env
APP_TIMEZONE=Asia/Makassar
```

## Akun Default

Akun berikut dibuat oleh seeder **hanya untuk development**.

| Role | Email | Password |
|---|---|---|
| Super Admin | `superadmin@hellobroseven.test` | `password123` |
| Admin | `admin@hellobroseven.test` | `password123` |
| Karyawan | `employee@hellobroseven.test` | `password123` |

> ⚠️ **Wajib** mengganti seluruh password default, atau menghapus akun seeder, sebelum aplikasi dipakai di production.

## Model Face Recognition

face-api.js membutuhkan file model yang diletakkan di:

```
public/assets/models/
```

Model yang umum dipakai:

- `tiny_face_detector_model`
- `face_landmark_68_model`
- `face_recognition_model`

Unduh dari repositori resmi face-api.js (folder `weights`), lalu salin seluruh file `*-weights_manifest.json` dan `*-shard*` ke folder di atas. Tanpa file ini, halaman absensi tidak dapat mendeteksi wajah.

### Catatan Biometrik

- Wajah disimpan sebagai **face descriptor**, bukan rekaman video.
- Akses descriptor dibatasi dan tidak ditampilkan melalui endpoint publik.
- Registrasi ulang wajah memerlukan persetujuan admin.
- Pemeriksaan liveness bersifat sederhana dan **bukan jaminan anti-spoofing**. Validasi tambahan di server tetap diterapkan.
- Pastikan karyawan memberikan persetujuan penggunaan data biometrik sesuai kebijakan perusahaan dan regulasi yang berlaku (misalnya UU Pelindungan Data Pribadi).

## Scheduler (Absent Otomatis)

Status **absent** dibuat otomatis oleh Laravel Scheduler setelah jadwal kerja berakhir. Scheduler harus berjalan terus.

**Lokal (development):**

```powershell
php artisan schedule:work
```

**Production (Linux, cron):**

```cron
* * * * * cd /var/www/hello-broseven && php artisan schedule:run >> /dev/null 2>&1
```

Karyawan yang jadwalnya libur atau memiliki izin approved tidak ditandai absent.

## Struktur Proyek

```
hello-broseven/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Auth, Dashboard, Employee, Attendance, Payroll, dll.
│   │   └── Middleware/         # RoleMiddleware, FeatureAccessMiddleware
│   └── Models/                 # User, Employee, AttendanceRecord, Payroll, dll.
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/
│   └── DEPLOYMENT.md           # Panduan deployment
├── public/
│   └── assets/
│       ├── css/
│       ├── js/
│       ├── images/
│       └── models/             # Model face-api.js
├── resources/views/
│   ├── layouts/ components/ landing/ auth/ dashboard/
│   ├── employees/ master/ schedules/ attendance/
│   ├── leave-requests/ reports/ payroll/
│   └── company/ feature-settings/ profile/
├── routes/web.php
└── tests/
```

## Alur Absensi

1. Karyawan membuka halaman **Absensi**.
2. Browser meminta izin kamera, lalu live camera tampil.
3. Sistem mendeteksi tepat satu wajah dan mencocokkannya dengan descriptor terdaftar.
4. Sistem meminta lokasi (Geolocation API).
5. Server memeriksa jadwal dan **mode kerja** hari itu.
6. **WFO:** wajah + GPS + radius wajib. **WFH:** wajah wajib, radius tidak diterapkan. **Hybrid:** mengikuti mode pada jadwal hari tersebut.
7. Jika valid, absensi disimpan lengkap dengan koordinat, akurasi, jarak, dan waktu.

Semua validasi (jarak Haversine, jadwal, akurasi) dijalankan ulang di backend.

## Perhitungan Gaji

```
Gaji per hari = Gaji harian jabatan × (100% − persentase potongan)
Total gaji    = Σ gaji per hari selama periode
```

Contoh konfigurasi (dapat diubah super admin): Absent 100%, Sakit 50%, Izin 50%, Terlambat 10%.

Ketentuan penting:

- Hanya hari yang terjadwal yang dihitung; hari libur tidak dianggap absent.
- Izin hanya sah jika berstatus **Approved**.
- Tidak ada perhitungan ganda.
- Hasil disimpan sebagai **snapshot payroll**, sehingga perubahan konfigurasi tidak mengubah payroll yang sudah ditutup.
- Status payroll: `Draft` → `Finalized` → `Paid`.

## Pengaturan Fitur Global

Super Admin dapat menonaktifkan fitur (misalnya Data Salary atau Face Recognition). Saat nonaktif:

- Menu dihapus dari sidebar (bukan sekadar disabled).
- URL dan endpoint terkait ditolak oleh `FeatureAccessMiddleware`.
- Berlaku untuk semua role, termasuk Super Admin dan Admin (kecuali halaman Pengaturan Fitur).
- Status fitur di-cache agar tidak membebani database.

## Keamanan

- CSRF protection, validasi server-side, Eloquent (proteksi SQL injection), escaping Blade (XSS)
- Password di-hash, regenerasi session saat login, rate limiting login
- Otorisasi berbasis role dan kepemilikan data (karyawan tidak bisa melihat data karyawan lain lewat ID di URL)
- Validasi dan pembatasan ukuran file upload
- Audit log untuk aksi penting
- Secure HTTP headers
- Data biometrik tidak diekspos ke endpoint publik

Menemukan celah keamanan? Jangan buka issue publik. Hubungi pemilik repositori melalui email.

## Pengujian

```powershell
php artisan test
```

Cakupan pengujian meliputi: login per role, proteksi role, CRUD master/karyawan/jadwal, registrasi dan verifikasi wajah, verifikasi GPS, pengecualian WFH, absensi tepat waktu/terlambat, absent otomatis, izin, payroll, pengaturan fitur, serta isolasi data antar karyawan.

## Deployment

Panduan lengkap (Nginx/Apache, HTTPS, cron, permission, backup, update) ada di **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

Ringkasan cepat:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Kamera tidak muncul | Gunakan HTTPS atau `localhost`, izinkan akses kamera di browser |
| Lokasi tidak terdeteksi | Aktifkan GPS dan izin lokasi; gunakan HTTPS |
| Wajah tidak terdeteksi / error model | Pastikan file model ada di `public/assets/models/` |
| Gambar/upload tidak tampil | Jalankan `php artisan storage:link` |
| Error 500 setelah deploy | Periksa `storage/logs/laravel.log` dan permission `storage/`, `bootstrap/cache/` |
| Status absent tidak terbentuk | Pastikan cron `schedule:run` aktif |
| Perubahan `.env` tidak berlaku | Jalankan `php artisan config:clear` |

## Kontribusi

1. Fork repositori ini
2. Buat branch fitur: `git checkout -b fitur/nama-fitur`
3. Commit perubahan: `git commit -m "Tambah nama fitur"`
4. Push: `git push origin fitur/nama-fitur`
5. Buka Pull Request

Jangan mengubah fitur yang sudah berfungsi tanpa pengujian, dan pertahankan batasan teknologi (tanpa framework frontend tambahan).

## Lisensi

Didistribusikan di bawah lisensi **MIT**. Sesuaikan bagian ini jika memakai lisensi lain.

---

© Hello BroSeven! — Kelola Kehadiran, Tingkatkan Produktivitas.
