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

## API

### Login Driver

Endpoint:

```http
POST /api/driver-login
```

Payload:

```json
{
  "driver_no": "DRV001"
}
```

Contoh `curl`:

```bash
curl -X POST http://localhost:8080/api/driver-login \
  -H "Content-Type: application/json" \
  -d '{"driver_no":"DRV001"}'
```

Response sukses:

```json
{
  "success": true,
  "message": "Driver ditemukan",
  "data": {
    "id": 1,
    "driver_no": "DRV001",
    "name": "Budi Santoso",
    "role": "driver"
  }
}
```

### Simpan Scan RFID/NFC

Endpoint:

```http
POST /api/scan
```

Payload:

```json
{
  "driver_id": 1,
  "device_uuid": "UNIWA-W999-01",
  "rfid_uid": "NFC-COMP-001",
  "latitude": -6.200000,
  "longitude": 106.816666
}
```

Contoh `curl`:

```bash
curl -X POST http://localhost:8080/api/scan \
  -H "Content-Type: application/json" \
  -d '{
    "driver_id": 1,
    "device_uuid": "UNIWA-W999-01",
    "rfid_uid": "NFC-COMP-001",
    "latitude": -6.2,
    "longitude": 106.816666
  }'
```

Response sukses berisi data driver, device, mobil tangki, kompartemen, dan `scan_log_id`.

Validasi penting:

- `driver_id` wajib ada dan driver harus aktif.
- `device_uuid` wajib ada dan device harus aktif.
- `rfid_uid` harus terdaftar pada data kompartemen.
- `latitude` opsional, rentang `-90` sampai `90`.
- `longitude` opsional, rentang `-180` sampai `180`.

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
