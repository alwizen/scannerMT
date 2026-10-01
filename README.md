# CEKDISIT MT

CEKDISIT adalah aplikasi untuk pencatatan scan RFID/NFC kompartemen mobil tangki.

## Dokumentasi Akses API

### Base URL dan aturan umum

Gunakan base URL sesuai cara menjalankan aplikasi:

| Cara menjalankan | Base URL |
| --- | --- |
| Docker Compose | `http://localhost:8080/api` |
| `php artisan serve` | `http://127.0.0.1:8000/api` |

Semua endpoint menerima dan mengembalikan JSON. Tambahkan header berikut pada request:

```http
Accept: application/json
Content-Type: application/json
```

API saat ini tidak memerlukan token Sanctum atau header `Authorization`. Login driver memvalidasi nomor driver aktif dan mengembalikan identitas driver. Sesi scan dibuat otomatis saat scan pertama untuk kombinasi driver, device, dan tanker yang ditemukan dari RFID.

Setelah menjalankan `php artisan migrate --seed`, data contoh yang dapat dipakai adalah:

| Data | Nilai |
| --- | --- |
| Nomor driver | `712D1717` |
| `driver_id` | biasanya `1`, cek ID dari response login |
| `device_uuid` | `63adafc2f137b5c0` |
| RFID kompartemen | `NFC-COMP-001`, `NFC-COMP-002`, atau `NFC-COMP-003` |

### 1. Login driver

```http
POST {{base_url}}/driver-login
```

Body JSON:

```json
{
  "driver_no": "712D1717",
  "device_uuid": "63adafc2f137b5c0"
}
```

| Field | Keterangan |
| --- | --- |
| `driver_no` | Nomor driver (wajib) |
| `device_uuid` | UUID device Android (wajib, harus terdaftar dan aktif di Master Data → Devices) |

Response `200 OK`:

```json
{
  "success": true,
  "message": "Driver ditemukan",
  "data": {
    "id": 1,
    "driver_no": "712D1717",
    "name": "Irwan Pras",
    "role": "driver"
  }
}
```

Simpan `data.id` dari response untuk dipakai sebagai `driver_id` pada request scan dan riwayat.

Response error:

| HTTP | Kondisi |
| --- | --- |
| `403` | Device tidak terdaftar atau tidak aktif |
| `404` | Driver tidak ditemukan atau tidak aktif |

### 2. Mengambil tanker tersedia

```http
GET {{base_url}}/tankers/available
```

Endpoint ini hanya mengembalikan tanker dengan status `available`. Pilih `data.id` dari salah satu tanker untuk membuat sesi.

### 3. Membuat sesi scan secara eksplisit (opsional)

```http
POST {{base_url}}/scan-sessions
```

Body JSON:

```json
{
  "driver_id": 1,
  "device_uuid": "63adafc2f137b5c0",
  "tanker_id": 1
}
```

Response `201 Created`:

```json
{
  "success": true,
  "message": "Sesi scan berhasil dibuat",
  "data": {
    "scan_session_id": 1,
    "driver_id": 1,
    "device_id": 1,
    "tanker_id": 1,
    "status": "in_progress",
    "started_at": "2026-09-04 10:00:00"
  }
}
```

Endpoint ini tetap tersedia untuk client yang ingin memulai sesi secara eksplisit. Namun, endpoint scan tidak mewajibkannya: jika `scan_session_id` tidak dikirim, backend akan memakai sesi `in_progress` yang sesuai atau membuat sesi baru secara otomatis.

### 4. Menyimpan scan RFID/NFC

```http
POST {{base_url}}/scan
```

Body JSON dengan koordinat:

```json
{
  "scan_session_id": 1,
  "driver_id": 1,
  "device_uuid": "63adafc2f137b5c0",
  "rfid_uid": "NFC-COMP-001",
  "latitude": -6.2000000,
  "longitude": 106.8166660
}
```

`latitude` dan `longitude` boleh dihilangkan bersama-sama. Jika keduanya dikirim, API menentukan apakah titik tersebut berada di lokasi parkir yang terdaftar.

Response `200 OK` memiliki bentuk berikut:

```json
{
  "success": true,
  "message": "Scan berhasil disimpan",
  "data": {
    "scan_log_id": 1,
    "scanned_at": "2026-09-03 10:00:00",
    "driver": {},
    "device": {},
    "tanker": {},
    "compartment": {},
    "geofence": {
      "is_inside": false,
      "location_id": null,
      "location_name": null,
      "status_text": "Di luar lokasi parkir MT"
    }
  }
}
```

`driver_id`, `device_uuid`, dan `rfid_uid` wajib dikirim. `scan_session_id` bersifat opsional. Backend menemukan tanker dari RFID, memakai sesi aktif yang sesuai, atau membuat sesi baru jika ritase sebelumnya sudah `completed`. Kompartemen yang sama tidak dapat discan dua kali dalam satu sesi. Setelah semua kompartemen tanker discan, status sesi menjadi `completed`, tetapi seluruh log tetap tersimpan. Koordinat bersifat opsional; `latitude` harus berada di antara `-90` dan `90`, sedangkan `longitude` di antara `-180` dan `180`.

### 5. Mengambil riwayat scan

Route utama:

```http
GET {{base_url}}/scan-history?driver_id=1&page=1&per_page=15
```

Alias yang juga tersedia: `GET {{base_url}}/scan_history?driver_id=1`.

Query parameter opsional:

| Parameter | Keterangan |
| --- | --- |
| `driver_id` | ID driver (wajib) |
| `search` | Cari nopol, rfid_uid, atau catatan |
| `from` | Filter tanggal mulai (`YYYY-MM-DD`) |
| `until` | Filter tanggal akhir (`YYYY-MM-DD`) |
| `page` | Nomor halaman (default `1`) |
| `per_page` | Jumlah data per halaman (default `15`, maksimum `100`) |

Response `200 OK` mengembalikan data per halaman, diurutkan dari scan terbaru:

```json
{
  "success": true,
  "message": "Data riwayat scan berhasil diambil",
  "data": [
    {
      "scan_log_id": 1,
      "scanned_at": "2026-09-03 10:00:00",
      "tanker": {},
      "compartment": {},
      "geofence": {},
      "scan_status": "kurang"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### 6. Ringkasan scanlog (per ritase/AMT)

Endpoint ini mengembalikan ringkasan log scan per kombinasi AMT + sesi scan + MT + tanggal, dengan field:

- `tanggal`
- `nama_amt`
- `nopol`
- `kapasitas`
- `jabatan` (`AMT 1` = driver, `AMT 2` = helper)
- `status` (`done` jika seluruh kompartemen MT sudah discan, selain itu `kurang`)

```http
GET {{base_url}}/scanlogs
GET {{base_url}}/scan-logs
```

Query parameter opsional:

| Parameter | Keterangan |
| --- | --- |
| `date` | Filter tanggal scan persis (`YYYY-MM-DD`) |
| `from` | Filter scan dari tanggal (`YYYY-MM-DD`) |
| `until` | Filter scan sampai tanggal (`YYYY-MM-DD`) |
| `driver_id` | Filter berdasarkan id AMT/driver |
| `nopol` | Filter sebagian nomor polisi MT |
| `page` | Nomor halaman (default `1`) |
| `per_page` | Jumlah data per halaman (default `15`, maksimum `100`) |

Contoh request:

```http
GET {{base_url}}/scanlogs?date=2026-09-30&nopol=B%201234&per_page=20
```

Response `200 OK`:

```json
{
  "success": true,
  "message": "Data riwayat scan berhasil diambil",
  "data": [
    {
      "tanggal": "2026-09-30",
      "nama_amt": "Budi Santoso",
      "nopol": "B 1234 KT",
      "kapasitas": 24,
      "jabatan": "AMT 1",
      "status": "done",
      "status_text": "Complete",
      "driver_id": 1,
      "scan_session_id": 10,
      "tanker_id": 1,
      "scanned_compartments": 2,
      "total_compartments": 2,
      "last_update": "2026-09-30 10:35:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

### Status error

| HTTP | Kondisi |
| --- | --- |
| `400` | `driver_id` tidak dikirim pada endpoint history |
| `403` | Device tidak terdaftar atau tidak aktif (saat login) |
| `404` | Driver tidak ditemukan/tidak aktif, device tidak ditemukan/tidak aktif, atau RFID tidak ditemukan |
| `409` | Kompartemen sudah discan dalam sesi yang sama |
| `422` | Payload tidak lolos validasi, driver/device/tanker tidak tersedia, atau koordinat di luar rentang |

Response error umumnya memiliki `success: false` dan `message`. Error validasi Laravel juga menyertakan object `errors`.

---

## API Integrasi TMS

Endpoint khusus untuk integrasi dengan TMS (Transport Management System). Data dikelompokkan per **ritase** (sesi scan yang sudah selesai), bukan per baris scan.

### Konsep Ritase

1 ritase = 1 kunjungan MT ke satu lokasi = 1 `scan_session` berstatus `completed`. Di dalamnya terdapat beberapa kompartemen yang di-scan.

### GET /tms/scanlogs

```http
GET {{base_url}}/tms/scanlogs
```

Query parameter:

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| `date` | `YYYY-MM-DD` | Filter tanggal ritase spesifik |
| `from` | `YYYY-MM-DD` | Filter ritase dari tanggal |
| `until` | `YYYY-MM-DD` | Filter ritase sampai tanggal |
| `since_id` | int | Incremental sync — hanya return ritase dengan `id > since_id` |
| `per_page` | int | Jumlah ritase per halaman (default `100`, maksimum `500`) |

Contoh request:

```http
# Polling harian
GET {{base_url}}/tms/scanlogs?date=2026-10-01

# Incremental sync (efisien untuk TMS)
GET {{base_url}}/tms/scanlogs?since_id=250

# Range tanggal
GET {{base_url}}/tms/scanlogs?from=2026-09-25&until=2026-10-01
```

Response `200 OK`:

```json
{
  "success": true,
  "message": "Data ritase berhasil diambil",
  "data": [
    {
      "ritase_id": 5,
      "tanggal": "2026-10-01",
      "nopol": "B 1234 KT",
      "kapasitas_kl": 24,
      "driver": {
        "id": 1,
        "nama": "Budi Santoso",
        "jabatan": "AMT 1"
      },
      "status": "done",
      "status_text": "Complete",
      "needs_action": true,
      "action_text": "Perlu Tindakan",
      "scanned_compartments": 4,
      "total_compartments": 4,
      "waktu_mulai": "2026-10-01 06:00:00",
      "waktu_selesai": "2026-10-01 08:12:00",
      "kompartemen": [
        {
          "no": 1,
          "kapasitas_kl": 8.0,
          "rfid_uid": "NFC-COMP-001",
          "content_status": "kosong",
          "catatan": null,
          "scanned_at": "2026-10-01 06:05:00"
        },
        {
          "no": 2,
          "kapasitas_kl": 8.0,
          "rfid_uid": "NFC-COMP-002",
          "content_status": "sisa_minyak",
          "catatan": "Sisa 200L",
          "scanned_at": "2026-10-01 06:15:00"
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 100,
    "total": 1
  }
}
```

### Field Response

#### Level Ritase

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `ritase_id` | int | ID sesi scan (scan_session_id) |
| `tanggal` | string | Tanggal ritase (`YYYY-MM-DD`) |
| `nopol` | string | Nomor polisi MT |
| `kapasitas_kl` | int | Kapasitas MT (KL) |
| `driver.id` | int | ID driver |
| `driver.nama` | string | Nama driver |
| `driver.jabatan` | string | `AMT 1` (driver) atau `AMT 2` (helper) |
| `status` | string | `done` jika semua kompartemen discan, `kurang` jika belum |
| `status_text` | string | `Complete` atau `Belum Lengkap` |
| `needs_action` | boolean | `true` jika ada kompartemen `sisa_minyak` atau `air` |
| `action_text` | string | `Perlu Tindakan` atau `Ready` |
| `scanned_compartments` | int | Jumlah kompartemen yang sudah discan |
| `total_compartments` | int | Total kompartemen di MT |
| `waktu_mulai` | string | Waktu sesi dimulai (`YYYY-MM-DD HH:mm:ss`) |
| `waktu_selesai` | string | Waktu sesi selesai (`YYYY-MM-DD HH:mm:ss`) |
| `kompartemen` | array | Detail kompartemen (lihat di bawah) |

#### Level Kompartemen

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `no` | int | Nomor kompartemen |
| `kapasitas_kl` | float | Kapasitas kompartemen (KL) |
| `rfid_uid` | string | UID RFID kompartemen |
| `content_status` | string | `kosong`, `air`, `sisa_minyak`, atau `lainnya` |
| `catatan` | string/null | Catatan tambahan |
| `scanned_at` | string | Waktu kompartemen discan |

### Logika Status

| Kondisi | `status` | `needs_action` | `action_text` |
| --- | --- | --- | --- |
| Semua kompartemen `kosong` | `done` | `false` | `Ready` |
| Ada `sisa_minyak` atau `air` | `done` | `true` | `Perlu Tindakan` |
| Scan belum lengkap | `kurang` | `true`/`false` | sesuai konten kompartemen |

### Cara TMS Menggunakan Endpoint Ini

**Polling harian** — ambil semua ritase tanggal tertentu:
```
GET /api/tms/scanlogs?date=2026-10-01
```

**Incremental sync** — efisien, hanya ambil data baru:
```
GET /api/tms/scanlogs?since_id=250
→ TMS simpan last_ritase_id = 250
→ Sync berikutnya: GET /api/tms/scanlogs?since_id=250
```

**Filter perlu tindakan** — gabungkan dengan parameter lain:
```
GET /api/tms/scanlogs?date=2026-10-01
→ Filter di response: needs_action == true
```

**Range tanggal** — ambil data beberapa hari sekaligus:
```
GET /api/tms/scanlogs?from=2026-09-25&until=2026-10-01
```

## Menjalankan API di Postman

1. Jalankan aplikasi dengan Docker atau `php artisan serve`.
2. Buat environment baru, lalu isi variable `base_url` dengan `http://localhost:8080/api` untuk Docker atau `http://127.0.0.1:8000/api` untuk server lokal.
3. Buat request `POST {{base_url}}/driver-login`, pilih **Body > raw > JSON**, masukkan body login, lalu klik **Send**.
4. Catat nilai `data.id` dari response login.
5. Buat request `GET {{base_url}}/tankers/available`, lalu pilih `data.id` tanker.
6. Buat request `POST {{base_url}}/scan` dengan body scan. Session akan dibuat atau dipilih otomatis oleh backend.
7. Ulangi request scan untuk seluruh kompartemen. Setelah selesai, scan berikutnya pada MT yang sama akan masuk ke session/ritase baru.
8. Buat request `GET {{base_url}}/scan-history?driver_id=1&page=1&per_page=15` untuk melihat riwayat.

Alternatif cepat tanpa environment: ganti `{{base_url}}` langsung dengan `http://localhost:8080/api` atau `http://127.0.0.1:8000/api`.

## Menjalankan API di Insomnia

1. Jalankan aplikasi dan buat **Collection** baru.
2. Tambahkan environment variable berikut:

```json
{
  "base_url": "http://localhost:8080/api"
}
```

Gunakan `http://127.0.0.1:8000/api` jika memakai `php artisan serve`.
3. Buat request login dengan method `POST` ke `{{ base_url }}/driver-login`, lalu pilih **Body > JSON**.
4. Tambahkan request scan dengan method `POST` ke `{{ base_url }}/scan` dan body JSON scan.
5. Tambahkan request history dengan method `GET` ke `{{ base_url }}/scan-history?driver_id=1`.
6. Jalankan request login terlebih dahulu, kemudian scan, lalu history.

Pada Postman maupun Insomnia, endpoint API tidak memakai trailing slash dan tidak membutuhkan token login.

## Command Harian

Masuk ke container aplikasi:

```bash
docker compose exec app sh
```

Menjalankan migration:

```bash
docker compose exec app php artisan migrate
```

Reset database dan seed ulang:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Menjalankan test:

```bash
docker compose exec app php artisan test
```

Melihat log container:

```bash
docker compose logs -f app
docker compose logs -f web
docker compose logs -f db
```

Rebuild container setelah perubahan Dockerfile atau dependency sistem:

```bash
docker compose up -d --build
```

Menghentikan container:

```bash
docker compose down
```

Menghentikan container sekaligus menghapus data MySQL:

```bash
docker compose down -v
```

## Development Tanpa Docker

Jika ingin menjalankan lokal tanpa Docker:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Sesuaikan koneksi database di `.env`. Default `.env.example` menggunakan SQLite, sedangkan konfigurasi Docker di atas menggunakan MySQL.

## Instalasi Di Ubuntu

Bagian ini ditujukan untuk instalasi langsung di server Ubuntu, tanpa Docker. Contoh menggunakan Ubuntu 24.04, PHP 8.4, MySQL, Nginx, dan Node.js LTS.

### 1. Install dependency sistem

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor git unzip curl \
  php8.4-cli php8.4-fpm php8.4-mysql php8.4-sqlite3 php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl
```

Jika package PHP 8.4 belum tersedia pada Ubuntu yang digunakan, tambahkan repository PHP yang sesuai terlebih dahulu atau gunakan versi PHP yang memenuhi requirement pada `composer.json`.

Install Composer dan Node.js:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Clone dan siapkan aplikasi

Contoh lokasi aplikasi adalah `/var/www/scannermt`:

```bash
sudo mkdir -p /var/www
sudo git clone <URL_REPOSITORY> /var/www/scannermt
sudo chown -R "$USER":www-data /var/www/scannermt
cd /var/www/scannermt

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Edit `.env` dan isi minimal konfigurasi berikut:

```dotenv
APP_NAME="Scanner MT"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.example
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scannermt
DB_USERNAME=scannermt
DB_PASSWORD=password-database-yang-kuat

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Buat database dan user MySQL:

```bash
sudo mysql
```

```sql
CREATE DATABASE scannermt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'scannermt'@'localhost' IDENTIFIED BY 'password-database-yang-kuat';
GRANT ALL PRIVILEGES ON scannermt.* TO 'scannermt'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Gunakan password database yang berbeda dari contoh dan jangan commit file `.env`.

Jalankan migration, seeder, dan optimasi cache Laravel:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

Pastikan permission runtime Laravel benar:

```bash
sudo chown -R www-data:www-data /var/www/scannermt/storage /var/www/scannermt/bootstrap/cache
sudo chmod -R ug+rwx /var/www/scannermt/storage /var/www/scannermt/bootstrap/cache
```

### 3. Konfigurasi Nginx

Buat `/etc/nginx/sites-available/scannermt`:

```nginx
server {
  listen 80;
  server_name domain-anda.example;
  root /var/www/scannermt/public;

  index index.php;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
  }

  location ~ /\.(?!well-known).* {
    deny all;
  }
}
```

Aktifkan site dan cek konfigurasi:

```bash
sudo ln -s /etc/nginx/sites-available/scannermt /etc/nginx/sites-enabled/scannermt
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable --now nginx php8.4-fpm mysql
```

Sesuaikan `php8.4-fpm.sock` jika server menggunakan versi PHP lain.

### 4. Konfigurasi Supervisor Untuk Queue

Aplikasi menggunakan `QUEUE_CONNECTION=database`, sehingga migration harus sudah membuat tabel `jobs` sebelum worker dijalankan. Buat file `/etc/supervisor/conf.d/scannermt-worker.conf`:

```ini
[program:scannermt-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/scannermt/artisan queue:work database --sleep=3 --tries=3 --timeout=90
directory=/var/www/scannermt
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/scannermt-worker.log
stopwaitsecs=3600
user=www-data
```

`numprocs=2` menjalankan dua worker. Naikkan atau turunkan jumlahnya sesuai CPU dan beban server. Nilai `stopwaitsecs` harus lebih besar dari timeout job terlama agar worker dapat berhenti dengan baik.

Aktifkan konfigurasi Supervisor:

```bash
sudo touch /var/log/scannermt-worker.log
sudo chown www-data:www-data /var/log/scannermt-worker.log
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start scannermt-worker:*
sudo supervisorctl status
```

Perintah operasional queue:

```bash
# Melihat log worker
sudo tail -f /var/log/scannermt-worker.log

# Restart worker setelah deploy atau perubahan kode
cd /var/www/scannermt
php artisan queue:restart
sudo supervisorctl restart scannermt-worker:*

# Melihat job gagal
php artisan queue:failed

# Mencoba ulang job gagal
php artisan queue:retry all
```

Setelah setiap deployment, jalankan urutan berikut agar worker membaca kode terbaru:

```bash
cd /var/www/scannermt
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
sudo supervisorctl restart scannermt-worker:*
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

Jika worker terus restart, periksa log Laravel dan Supervisor:

```bash
tail -f /var/www/scannermt/storage/logs/laravel.log
sudo tail -f /var/log/supervisor/supervisord.log
sudo supervisorctl status
```
