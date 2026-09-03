# Scanner MT

Scanner MT adalah aplikasi Laravel untuk pencatatan scan RFID/NFC kompartemen mobil tangki. Aplikasi ini menyediakan:

- Admin panel Filament untuk mengelola driver, device scanner, mobil tangki, kompartemen, dan log scan.
- API login driver berdasarkan nomor driver.
- API scan RFID/NFC yang menyimpan driver, device, kompartemen, koordinat, dan waktu scan.
- Status scan per mobil tangki: `done` jika semua kompartemen sudah pernah discan, `kurang` jika belum lengkap.

## Stack

- PHP 8.4 FPM Alpine
- Laravel 13
- Filament 5
- MySQL 8.0
- Nginx Alpine
- Docker Compose

## Menjalankan Dengan Docker

Pastikan Docker dan Docker Compose sudah terpasang.

1. Salin file environment:

```bash
cp .env.example .env
```

2. Pastikan `.env` memiliki `APP_KEY`. Konfigurasi database untuk Docker sudah diatur langsung di `docker-compose.yml`, jadi `.env` lokal boleh tetap memakai SQLite untuk `php artisan serve`.

```dotenv
APP_NAME="Scanner MT"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
```

Docker Compose akan mengirim environment berikut ke container `app`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Catatan: `DB_HOST=db` dipakai karena aplikasi berjalan di dalam network Docker. Port `3307` hanya dipakai jika ingin mengakses MySQL dari host/laptop.

3. Build dan jalankan container:

```bash
docker compose up -d --build
```

4. Generate app key:

```bash
docker compose exec app php artisan key:generate
```

5. Jalankan migration dan seeder:

```bash
docker compose exec app php artisan migrate --seed
```

6. Akses aplikasi:

- Web: http://localhost:8080
- Admin panel: http://localhost:8080/admin
- API base URL: http://localhost:8080/api

## Konfigurasi Docker

Docker Compose menjalankan tiga service:

| Service | Container | Fungsi | Port |
| --- | --- | --- | --- |
| `app` | `scannermt_app` | PHP-FPM Laravel | internal `9000` |
| `web` | `scannermt_web` | Nginx reverse proxy ke PHP-FPM | host `8080` ke container `80` |
| `db` | `scannermt_db` | MySQL 8.0 | host `3307` ke container `3306` |

Konfigurasi database bawaan `docker-compose.yml`:

```dotenv
MYSQL_DATABASE=laravel
MYSQL_ROOT_PASSWORD=secret
MYSQL_USER=laravel
MYSQL_PASSWORD=secret
```

Volume Docker:

- Source code di-mount ke `/var/www/html`.
- Data MySQL disimpan di volume `db_data`.

Entrypoint container aplikasi (`docker/entrypoint.sh`) akan:

- Menjalankan `composer install` jika folder `vendor/` belum ada.
- Membuat folder runtime Laravel yang dibutuhkan.
- Mengatur permission folder `storage` dan `bootstrap/cache` tanpa mengambil ownership dari host.
- Menjalankan proses utama `php-fpm`.

Konfigurasi Nginx ada di `docker/nginx/default.conf` dan mengarah ke folder `public/`.

## Akun Dan Data Awal

Seeder membuat akun admin default:

```text
Email: test@example.com
Password: password
```

Seeder juga membuat data pilot:

- Driver `DRV001` - Budi Santoso
- Driver `DRV002` - Joko Prasetyo
- Device `UNIWA-W999-01`
- Mobil tangki `G 8123 XX` kapasitas 24 KL
- RFID/NFC kompartemen:
  - `NFC-COMP-001`
  - `NFC-COMP-002`
  - `NFC-COMP-003`

## Penggunaan Admin Panel

Buka http://localhost:8080/admin lalu login dengan akun seed. Menu utama yang tersedia:

- Drivers: mengelola nomor driver, nama, role, nomor telepon, dan status aktif.
- Devices: mengelola device scanner berdasarkan `device_uuid`.
- Tankers: mengelola mobil tangki, nomor polisi, kapasitas, dan status.
- Tanker Compartments: mengelola kompartemen, kapasitas, dan UID RFID/NFC.
- Scan Logs: melihat hasil scan, lokasi, waktu scan, dan status kelengkapan scan.

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

API saat ini tidak memerlukan token Sanctum atau header `Authorization`. Login driver hanya memvalidasi nomor driver aktif dan mengembalikan identitas driver.

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
  "driver_no": "712D1717"
}
```

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

### 2. Menyimpan scan RFID/NFC

```http
POST {{base_url}}/scan
```

Body JSON dengan koordinat:

```json
{
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

`driver_id`, `device_uuid`, dan `rfid_uid` wajib dikirim. Driver serta device juga harus aktif. Koordinat bersifat opsional; `latitude` harus berada di antara `-90` dan `90`, sedangkan `longitude` di antara `-180` dan `180`.

### 3. Mengambil riwayat scan

Route utama:

```http
GET {{base_url}}/scan-history?driver_id=1
```

Alias yang juga tersedia: `GET {{base_url}}/scan_history?driver_id=1`.

Response `200 OK` mengembalikan array `data`, diurutkan dari scan terbaru:

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
  ]
}
```

### Status error

| HTTP | Kondisi |
| --- | --- |
| `400` | `driver_id` tidak dikirim pada endpoint history |
| `404` | Driver tidak ditemukan/tidak aktif, device tidak ditemukan/tidak aktif, atau RFID tidak ditemukan |
| `422` | Payload tidak lolos validasi, misalnya field wajib kosong atau koordinat di luar rentang |

Response error umumnya memiliki `success: false` dan `message`. Error validasi Laravel juga menyertakan object `errors`.

## Menjalankan API di Postman

1. Jalankan aplikasi dengan Docker atau `php artisan serve`.
2. Buat environment baru, lalu isi variable `base_url` dengan `http://localhost:8080/api` untuk Docker atau `http://127.0.0.1:8000/api` untuk server lokal.
3. Buat request `POST {{base_url}}/driver-login`, pilih **Body > raw > JSON**, masukkan body login, lalu klik **Send**.
4. Catat nilai `data.id` dari response login.
5. Buat request `POST {{base_url}}/scan` dengan header JSON dan body scan. Ganti `driver_id` memakai ID dari langkah sebelumnya.
6. Buat request `GET {{base_url}}/scan-history?driver_id=1`, lalu klik **Send** untuk melihat riwayat.

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
