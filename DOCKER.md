# Docker — Panduan singkat untuk proyek

Prerequisites
- Docker & Docker Compose terinstal di mesin.

Build & Run (development)
```bash
docker compose up -d --build
```

Masuk ke container aplikasi:
```bash
docker compose exec app sh
```

Composer / Artisan (opsional jika entrypoint belum menjalankan):
```bash
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

Environment (.env)
- Pastikan `.env` ada dan memiliki `APP_KEY`.
- Konfigurasi database Docker sudah di-override dari `docker-compose.yml`, jadi `.env` lokal boleh tetap memakai SQLite untuk `php artisan serve`.
- Container `app` memakai variabel berikut:

```dotenv
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```

- Alternatif lokal tanpa Docker (SQLite):
```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```
Pastikan file `database/database.sqlite` dibuat jika menggunakan SQLite.

Ports
- Akses aplikasi lewat: http://localhost:8080

Common commands
- Stop & remove containers:
```bash
docker compose down -v
```
- Rebuild after changes:
```bash
docker compose up -d --build
```

Notes
- Entrypoint `docker/entrypoint.sh` mencoba menjalankan `composer install` bila `vendor/` tidak ada, membuat folder runtime Laravel, serta men-set permission untuk `storage` dan `bootstrap/cache` tanpa mengambil ownership dari host.
- Sesuaikan `.env` sebelum menjalankan migration pada environment produksi.
