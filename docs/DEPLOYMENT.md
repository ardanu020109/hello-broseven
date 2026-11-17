# Panduan Deployment — Hello BroSeven!

Dokumen ini menjelaskan cara men-deploy **Hello BroSeven!** (Laravel 13) ke server production, mulai dari persiapan server hingga pemeliharaan.

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Persyaratan](#2-persyaratan)
3. [Checklist Sebelum Deploy](#3-checklist-sebelum-deploy)
4. [Deployment di VPS Ubuntu (Nginx)](#4-deployment-di-vps-ubuntu-nginx)
5. [Alternatif: Apache](#5-alternatif-apache)
6. [Alternatif: Shared Hosting / cPanel](#6-alternatif-shared-hosting--cpanel)
7. [HTTPS (Wajib)](#7-https-wajib)
8. [Scheduler dan Queue](#8-scheduler-dan-queue)
9. [Model Face Recognition](#9-model-face-recognition)
10. [Konfigurasi `.env` Production](#10-konfigurasi-env-production)
11. [Keamanan Production](#11-keamanan-production)
12. [Backup dan Restore](#12-backup-dan-restore)
13. [Update / Rilis Versi Baru](#13-update--rilis-versi-baru)
14. [Rollback](#14-rollback)
15. [Monitoring dan Log](#15-monitoring-dan-log)
16. [Troubleshooting](#16-troubleshooting)
17. [Checklist Go-Live](#17-checklist-go-live)

---

## 1. Gambaran Umum

Komponen yang dibutuhkan di production:

| Komponen | Fungsi |
|---|---|
| Web server (Nginx/Apache) | Melayani aplikasi, `document root` ke folder `public/` |
| PHP-FPM 8.3+ | Menjalankan Laravel |
| MySQL 8 / MariaDB | Database |
| HTTPS (SSL) | **Wajib**: kamera dan Geolocation API diblokir browser tanpa HTTPS |
| Cron | Menjalankan Laravel Scheduler (status absent otomatis) |
| Supervisor (opsional) | Menjalankan queue worker jika queue dipakai |

## 2. Persyaratan

**Server minimum:** 1 vCPU, 1 GB RAM (disarankan 2 GB), 10 GB disk.

**PHP 8.3+** dengan ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

**Lainnya:** Composer 2.x, Git, MySQL 8 / MariaDB 10.6+, domain yang mengarah ke server.

> Sesuaikan versi PHP minimum dengan persyaratan resmi Laravel 13 yang Anda gunakan (`composer.json`).

## 3. Checklist Sebelum Deploy

- [ ] Kode sudah di-push ke GitHub dan tes lulus (`php artisan test`)
- [ ] `.env` production disiapkan (jangan di-commit)
- [ ] File model face-api.js tersedia di `public/assets/models/`
- [ ] Domain dan DNS sudah mengarah ke server
- [ ] Rencana mengganti/menghapus akun default seeder
- [ ] Koordinat kantor, radius, dan zona waktu perusahaan sudah diketahui
- [ ] Kebijakan persetujuan data biometrik karyawan sudah disiapkan

## 4. Deployment di VPS Ubuntu (Nginx)

Contoh untuk Ubuntu 22.04/24.04. Ganti `contoh.com` dengan domain Anda.

### 4.1 Update server dan install paket

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl software-properties-common
```

Install PHP 8.3 (tambahkan repositori Ondřej bila belum tersedia):

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 4.2 Siapkan database

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE hello_broseven CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'broseven'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON hello_broseven.* TO 'broseven'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Jangan memakai user `root` untuk aplikasi.

### 4.3 Ambil kode aplikasi

```bash
sudo mkdir -p /var/www/hello-broseven
sudo chown -R $USER:$USER /var/www/hello-broseven
cd /var/www
git clone https://github.com/ardanu020109/hello-broseven.git hello-broseven
cd hello-broseven
```

### 4.4 Install dependensi

```bash
composer install --no-dev --optimize-autoloader
```

### 4.5 Konfigurasi environment

```bash
cp .env.example .env
nano .env        # isi sesuai bagian 10
php artisan key:generate
```

### 4.6 Migrasi database

```bash
php artisan migrate --force
```

Seeder (hanya pertama kali, lihat catatan keamanan):

```bash
php artisan db:seed --force
```

> Seeder membuat akun default `password123`. **Segera ganti password** atau hapus akun tersebut setelah login pertama. Pertimbangkan membuat seeder khusus production yang hanya mengisi data master, perusahaan, dan satu Super Admin dengan password kuat.

### 4.7 Storage dan permission

```bash
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 4.8 Konfigurasi Nginx

Buat file `/etc/nginx/sites-available/hello-broseven`:

```nginx
server {
    listen 80;
    server_name contoh.com www.contoh.com;
    root /var/www/hello-broseven/public;

    index index.php;
    charset utf-8;

    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self), geolocation=(self), microphone=()" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache aset statis, termasuk model face-api.js
    location ~* \.(?:css|js|jpg|jpeg|png|gif|ico|svg|woff2?|json|bin)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public";
    }
}
```

> Header `Permissions-Policy` di atas **mengizinkan** kamera dan lokasi untuk domain sendiri. Jangan mengaturnya menjadi `camera=()` karena akan memblokir fitur absensi.

Aktifkan:

```bash
sudo ln -s /etc/nginx/sites-available/hello-broseven /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4.9 Optimasi cache Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> Setiap kali `.env` berubah, jalankan ulang `php artisan config:cache`.

### 4.10 Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Lanjutkan ke [bagian 7 (HTTPS)](#7-https-wajib) dan [bagian 8 (Scheduler)](#8-scheduler-dan-queue).

## 5. Alternatif: Apache

Install:

```bash
sudo apt install -y apache2 libapache2-mod-php8.3
sudo a2enmod rewrite headers
```

Virtual host `/etc/apache2/sites-available/hello-broseven.conf`:

```apache
<VirtualHost *:80>
    ServerName contoh.com
    ServerAlias www.contoh.com
    DocumentRoot /var/www/hello-broseven/public

    <Directory /var/www/hello-broseven/public>
        AllowOverride All
        Require all granted
    </Directory>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Permissions-Policy "camera=(self), geolocation=(self), microphone=()"

    ErrorLog ${APACHE_LOG_DIR}/hello-broseven-error.log
    CustomLog ${APACHE_LOG_DIR}/hello-broseven-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite hello-broseven
sudo systemctl reload apache2
```

File `public/.htaccess` bawaan Laravel harus tetap ada.

## 6. Alternatif: Shared Hosting / cPanel

Gunakan opsi ini hanya jika tidak memiliki VPS. Pastikan hosting mendukung PHP 8.3+, SSH/Terminal (disarankan), dan cron.

1. **Upload kode** di luar `public_html`, misalnya `/home/user/hello-broseven/`.
2. **Isi `public/`** disalin ke `public_html/` (atau subfolder domain).
3. Edit `public_html/index.php` agar path mengarah ke folder aplikasi:

   ```php
   require __DIR__.'/../hello-broseven/vendor/autoload.php';
   $app = require_once __DIR__.'/../hello-broseven/bootstrap/app.php';
   ```

4. Buat database dan user lewat **MySQL Databases** di cPanel, lalu isi `.env`.
5. Jalankan perintah lewat Terminal cPanel:

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   ```

6. **Symlink storage** bila `storage:link` tidak bisa dijalankan: buat symlink dari `public_html/storage` ke `hello-broseven/storage/app/public`, atau salin isinya secara berkala.
7. Tambahkan **Cron Job** di cPanel (setiap menit):

   ```
   /usr/local/bin/php /home/user/hello-broseven/artisan schedule:run >> /dev/null 2>&1
   ```

8. Aktifkan **SSL (AutoSSL/Let's Encrypt)** dari cPanel.

> Pastikan `.env`, `vendor/`, `storage/` dan source code **tidak** berada di dalam folder yang bisa diakses publik.

## 7. HTTPS (Wajib)

Tanpa HTTPS, browser memblokir kamera dan Geolocation API sehingga absensi tidak bisa digunakan (kecuali di `localhost`).

Dengan Let's Encrypt (Certbot) di Nginx:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d contoh.com -d www.contoh.com
sudo certbot renew --dry-run
```

Setelah HTTPS aktif:

- Set `APP_URL=https://contoh.com` di `.env`
- Set `SESSION_SECURE_COOKIE=true`
- Jalankan `php artisan config:cache`
- (Opsional) tambahkan HSTS:

  ```nginx
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  ```

  Aktifkan HSTS hanya setelah yakin HTTPS stabil.

## 8. Scheduler dan Queue

### 8.1 Scheduler (wajib)

Status **absent** otomatis dibuat oleh Laravel Scheduler. Tanpa cron, karyawan yang tidak absen tidak akan ditandai absent dan payroll menjadi tidak akurat.

```bash
sudo crontab -u www-data -e
```

Tambahkan:

```cron
* * * * * cd /var/www/hello-broseven && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi:

```bash
php artisan schedule:list
php artisan schedule:run
```

Pastikan zona waktu (`APP_TIMEZONE`) sama dengan zona waktu perusahaan agar batas waktu absent tepat.

### 8.2 Queue worker (opsional)

Hanya diperlukan jika aplikasi memakai queue (mis. `QUEUE_CONNECTION=database`). Gunakan Supervisor:

```bash
sudo apt install -y supervisor
```

`/etc/supervisor/conf.d/hello-broseven-worker.conf`:

```ini
[program:hello-broseven-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hello-broseven/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/hello-broseven/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hello-broseven-worker:*
```

Setelah deploy, restart worker: `php artisan queue:restart`.

## 9. Model Face Recognition

Pastikan folder berikut berisi file model face-api.js:

```
public/assets/models/
├── tiny_face_detector_model-weights_manifest.json
├── tiny_face_detector_model-shard1
├── face_landmark_68_model-weights_manifest.json
├── face_landmark_68_model-shard1
├── face_recognition_model-weights_manifest.json
├── face_recognition_model-shard1
└── face_recognition_model-shard2
```

Nama file dapat berbeda sesuai model yang Anda pakai di JavaScript. Pastikan:

- File model ikut ter-commit di repositori atau diunggah manual ke server.
- Web server mengizinkan akses ke ekstensi tanpa nama (`-shard1`, `-shard2`) dan `.json`. Jika model gagal dimuat, periksa tab **Network** di DevTools untuk status 404/403.
- Ukuran total model beberapa MB; aktifkan cache (lihat konfigurasi Nginx) agar loading cepat.

**Uji setelah deploy:** buka halaman Absensi dari perangkat karyawan, pastikan kamera meminta izin dan wajah terdeteksi.

## 10. Konfigurasi `.env` Production

```env
APP_NAME="Hello BroSeven!"
APP_ENV=production
APP_KEY=base64:DIISI_OLEH_key:generate
APP_DEBUG=false
APP_URL=https://contoh.com
APP_TIMEZONE=Asia/Makassar

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hello_broseven
DB_USERNAME=broseven
DB_PASSWORD=GANTI_DENGAN_PASSWORD_KUAT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=smtp.contoh.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@contoh.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Catatan:

- **`APP_DEBUG=false` wajib** di production. `true` membocorkan konfigurasi dan stack trace.
- Jangan pernah meng-commit `.env`. Pastikan `.gitignore` memuatnya.
- Jika memakai `SESSION_DRIVER=database` atau `CACHE_STORE=database`, pastikan migration tabel `sessions` dan `cache` sudah dijalankan. Alternatifnya gunakan `file`.
- Simpan `APP_KEY` dengan aman. Jika berubah, session dan data terenkripsi (termasuk descriptor wajah bila dienkripsi) bisa tidak terbaca.

## 11. Keamanan Production

**Akun dan data**

- [ ] Ganti semua password akun seeder (`password123`) atau hapus akunnya.
- [ ] Gunakan password kuat untuk Super Admin dan database.
- [ ] Batasi jumlah Super Admin.

**Server**

- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] Document root hanya `public/`. `.env`, `storage/`, `vendor/` tidak boleh bisa diakses dari web.
- [ ] HTTPS aktif dan HTTP dialihkan ke HTTPS.
- [ ] Firewall hanya membuka port 22 (idealnya dengan SSH key), 80, 443.
- [ ] MySQL hanya listen di `127.0.0.1`.
- [ ] Update sistem dan PHP secara berkala (`sudo apt upgrade`).
- [ ] Nonaktifkan login SSH dengan password; gunakan SSH key.

**Aplikasi**

- [ ] Rate limiting login aktif.
- [ ] Batas ukuran dan tipe file upload sesuai kebutuhan (`client_max_body_size`, `upload_max_filesize`, `post_max_size`).
- [ ] Folder upload dokumen sensitif (KTP, lampiran izin) tidak dapat dilisting dan tidak mengeksekusi PHP.
- [ ] Endpoint face descriptor hanya bisa diakses user terautentikasi sesuai haknya.

**Data biometrik**

- Dapatkan persetujuan tertulis/elektronik karyawan sebelum registrasi wajah.
- Tentukan masa retensi dan prosedur penghapusan data wajah ketika karyawan keluar.
- Batasi akses basis data dan backup yang berisi descriptor.
- Liveness sederhana tidak menjamin anti-spoofing; jangan menjadikannya satu-satunya kontrol untuk keputusan yang berisiko tinggi.

## 12. Backup dan Restore

### Yang perlu dibackup

| Data | Lokasi |
|---|---|
| Database | MySQL `hello_broseven` |
| File upload | `storage/app/public/` (dan `storage/app/` jika ada dokumen privat) |
| Konfigurasi | `.env` (simpan terenkripsi, di luar repositori) |

### Backup manual

```bash
mkdir -p ~/backups
mysqldump -u broseven -p --single-transaction --routines hello_broseven \
  | gzip > ~/backups/hello_broseven_$(date +%F).sql.gz

tar -czf ~/backups/storage_$(date +%F).tar.gz -C /var/www/hello-broseven storage/app
```

### Backup otomatis harian (cron)

```cron
0 2 * * * mysqldump -u broseven -pPASSWORD --single-transaction hello_broseven | gzip > /home/user/backups/db_$(date +\%F).sql.gz
0 3 * * * find /home/user/backups -type f -mtime +14 -delete
```

> Simpan password di file `~/.my.cnf` (permission `600`) agar tidak muncul di daftar proses. Salin backup ke lokasi lain (object storage atau server berbeda), bukan hanya di server yang sama.

### Restore

```bash
gunzip < ~/backups/hello_broseven_2025-01-01.sql.gz | mysql -u broseven -p hello_broseven
tar -xzf ~/backups/storage_2025-01-01.tar.gz -C /var/www/hello-broseven
sudo chown -R www-data:www-data /var/www/hello-broseven/storage
```

Uji restore secara berkala di lingkungan staging.

## 13. Update / Rilis Versi Baru

```bash
cd /var/www/hello-broseven

# 1. Backup database terlebih dahulu
mysqldump -u broseven -p --single-transaction hello_broseven | gzip > ~/backups/pre_update_$(date +%F_%H%M).sql.gz

# 2. Mode maintenance
php artisan down --retry=60

# 3. Ambil kode terbaru
git fetch --all
git pull origin main

# 4. Dependensi
composer install --no-dev --optimize-autoloader

# 5. Migrasi
php artisan migrate --force

# 6. Bersihkan dan bangun ulang cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart queue worker (jika dipakai)
php artisan queue:restart

# 8. Aktifkan kembali
php artisan up
```

Tips:

- Jangan menjalankan `php artisan migrate:fresh` atau `db:seed` pada database production yang berisi data.
- Cek `php artisan about` untuk memastikan environment sudah benar.
- Setelah update PHP-FPM atau OPcache, jalankan `sudo systemctl reload php8.3-fpm`.

## 14. Rollback

Jika rilis bermasalah:

```bash
php artisan down

# Kembali ke commit/tag sebelumnya
git log --oneline -n 10
git checkout <COMMIT_ATAU_TAG_SEBELUMNYA>

composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

php artisan up
```

Jika migration baru menyebabkan masalah:

```bash
# Hanya jika migration punya method down() yang aman
php artisan migrate:rollback --step=1 --force
```

Jika data rusak, restore dari backup `pre_update_*.sql.gz` (lihat bagian 12). Gunakan Git tag untuk setiap rilis (`git tag v1.0.0`) agar rollback mudah.

## 15. Monitoring dan Log

- Log aplikasi: `storage/logs/laravel-YYYY-MM-DD.log`
- Log Nginx: `/var/log/nginx/error.log` dan `access.log`
- Log PHP-FPM: `/var/log/php8.3-fpm.log`

```bash
tail -f storage/logs/laravel-$(date +%F).log
sudo tail -f /var/log/nginx/error.log
```

Rotasi log sudah ditangani `LOG_CHANNEL=daily`; atur `LOG_DAILY_DAYS` untuk jumlah hari retensi.

Pantau juga: ruang disk, penggunaan RAM/CPU, status MySQL, masa berlaku sertifikat SSL, dan keberhasilan cron scheduler.

## 16. Troubleshooting

| Gejala | Kemungkinan penyebab | Solusi |
|---|---|---|
| 500 Internal Server Error | Permission, `.env`, `APP_KEY` kosong | Cek `storage/logs`, jalankan `php artisan key:generate`, perbaiki permission `storage/` dan `bootstrap/cache/` |
| 404 di semua halaman selain beranda | Rewrite belum aktif | Nginx: pastikan `try_files`; Apache: `a2enmod rewrite` dan `AllowOverride All` |
| Halaman tanpa CSS/JS | `APP_URL` salah atau mixed content | Set `APP_URL` HTTPS yang benar, bersihkan cache |
| Gambar/upload tidak tampil | Symlink storage belum dibuat | `php artisan storage:link` |
| Kamera tidak bisa diakses | Bukan HTTPS atau Permissions-Policy memblokir | Aktifkan HTTPS, periksa header `Permissions-Policy` |
| Lokasi selalu ditolak | HTTPS tidak aktif, izin lokasi ditolak, akurasi buruk | Aktifkan HTTPS, izinkan lokasi, cek batas akurasi di konfigurasi |
| "Model tidak ditemukan" saat absen | File model face-api.js tidak ada/404 | Periksa `public/assets/models/` dan tab Network |
| Absent tidak terbentuk | Cron belum berjalan atau zona waktu salah | `php artisan schedule:list`, cek crontab, samakan `APP_TIMEZONE` |
| `SQLSTATE[HY000] [1045]` | Kredensial DB salah | Periksa `.env`, lalu `php artisan config:clear` |
| Perubahan `.env` tidak berlaku | Config ter-cache | `php artisan config:clear && php artisan config:cache` |
| Upload gagal untuk file besar | Batas PHP/Nginx | Naikkan `upload_max_filesize`, `post_max_size` di `php.ini`, `client_max_body_size` di Nginx |
| Session sering habis | `SESSION_LIFETIME` rendah / cookie secure tanpa HTTPS | Sesuaikan nilai; pastikan HTTPS aktif |
| Error setelah update | Cache lama | `php artisan optimize:clear` lalu cache ulang |

Pengaturan PHP yang sering perlu disesuaikan (`/etc/php/8.3/fpm/php.ini`):

```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 60
expose_php = Off
opcache.enable = 1
```

Setelah mengubah: `sudo systemctl reload php8.3-fpm`.

## 17. Checklist Go-Live

**Infrastruktur**
- [ ] Domain mengarah ke server, HTTPS valid
- [ ] Firewall aktif, SSH memakai key
- [ ] Cron scheduler berjalan
- [ ] Backup otomatis aktif dan sudah diuji restore

**Aplikasi**
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Migration berhasil, cache config/route/view dibuat
- [ ] `storage:link` berhasil, upload dan tampilan gambar berfungsi
- [ ] Model face-api.js termuat
- [ ] Password default diganti / akun default dihapus

**Data awal**
- [ ] Profile Perusahaan terisi: nama, logo, alamat, zona waktu
- [ ] Koordinat kantor, radius, dan batas akurasi GPS diatur
- [ ] Data master (jabatan, departemen, potongan gaji, jenis izin) terisi
- [ ] Jadwal kerja dan hari libur dibuat
- [ ] Fitur yang tidak dipakai dinonaktifkan di Pengaturan Fitur

**Uji akhir (lakukan dari perangkat nyata)**
- [ ] Login ketiga role dan proteksi role berjalan
- [ ] Registrasi wajah dan absensi masuk/pulang berhasil di lokasi kantor
- [ ] Absensi ditolak di luar radius (WFO) dan diterima untuk WFH dengan wajah valid
- [ ] Pengajuan dan persetujuan izin berjalan
- [ ] Laporan dan export Excel/PDF berfungsi
- [ ] Payroll terhitung dan slip gaji dapat dicetak
- [ ] Menonaktifkan fitur menyembunyikan menu dan menolak URL-nya

---

Dokumen ini adalah panduan umum. Sesuaikan perintah, path, versi PHP, dan nama domain dengan lingkungan server Anda.
