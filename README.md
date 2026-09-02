# RutinKu

RutinKu ialah Progressive Web App (PWA) mobile-first untuk mengurus rutin keluarga. Implementasi **Phase 1–11 telah lengkap** menggunakan CodeIgniter 4.7+, PHP 8.2+, dan MySQL/MariaDB.

## Fungsi siap

- Parent login, family authorization, Child management, dan trusted-device mode.
- Rutin/task, complete/undo, append-only points, streak, rewards, dan approvals.
- Ranking serta laporan daily/weekly/monthly yang Parent-only.
- Private Child profile, installable PWA, offline fallback, dan static-only cache.
- cPanel hardening: public-only root, no-store responses, CSRF, CSP, local Bootstrap, token expiry/revocation, dan audit.

Rujuk [architecture](docs/ARCHITECTURE.md), [deployment checklist](docs/CPANEL_DEPLOYMENT.md), dan [security scan](docs/SECURITY_SCAN_PHASE_11.md).

## Setup

Keperluan: PHP 8.2+ dengan `intl`, `mbstring`, `mysqli`, `json`, `openssl`, `fileinfo`; Composer 2; MySQL 8+ atau MariaDB yang disokong.

```bash
composer install
cp .env.example .env
# Tetapkan base URL dan database dalam .env.
php spark migrate
php spark db:seed DemoSeeder
php spark serve
```

Akaun demo development/testing sahaja: `parent1@example.com` / `password` dan `parent2@example.com` / `password`. Jangan jalankan `DemoSeeder` di production.

## Verification

```bash
composer test
composer validate --strict
composer audit --locked --no-dev
```

Final verification: **79 tests, 362 assertions**, 155 fail PHP lint, migration 1–14 refresh + seed berjaya, Composer sah, dan tiada advisory dependency produksi.

## Production

Gunakan HTTPS, `CI_ENVIRONMENT = production`, `app.forceGlobalSecureRequests = true`, `cookie.secure = true`, encryption key rawak, dan document root tepat ke `public/`. Ikut [CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md).
