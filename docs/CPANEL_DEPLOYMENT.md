# RutinKu cPanel Deployment and Security Checklist

This checklist is the Phase 11 production gate. Complete it on the real hosting account; development defaults intentionally do not force HTTPS or secure cookies so local HTTP remains usable.

## 1. Required hosting capabilities

- PHP 8.2 or newer with `intl`, `mbstring`, `json`, `mysqlnd`/`mysqli`, `openssl`, `fileinfo`, and `curl` enabled.
- MariaDB/MySQL with InnoDB, foreign-key support, and `utf8mb4`.
- Composer 2, SSH/Terminal access or a controlled local build/upload workflow.
- Apache `mod_rewrite`, permission to set the domain document root, and a valid TLS certificate.
- A cron facility is optional; RutinKu Phase 11 has no required background job.

## 2. Safe directory layout

Preferred layout:

```text
/home/ACCOUNT/rutinku/          application root (not web-accessible)
├── app/
├── vendor/
├── writable/
├── .env
└── public/                     domain document root
```

Point the domain or subdomain document root to `/home/ACCOUNT/rutinku/public`. The root `.htaccess` denies web access as defense in depth. Only `public/` may be exposed.

If cPanel cannot change the document root, keep the application in `/home/ACCOUNT/rutinku` and copy only the contents of `public/` into `public_html/`. Adjust the front-controller paths in `public_html/index.php` to the private application location. Never place `.env`, `app`, `vendor`, `tests`, `writable`, `composer.json`, database dumps, or logs in `public_html`.

## 3. Production environment

Copy `.env.example` to the private project root as `.env`, then set:

```dotenv
CI_ENVIRONMENT = production
app.baseURL = 'https://your-domain.example/'
app.forceGlobalSecureRequests = true
cookie.secure = true
cookie.httponly = true
cookie.samesite = Lax
database.default.DBDriver = MySQLi
```

Replace all database placeholders. Generate the encryption key with `php spark key:generate`. Do not commit or expose `.env`. Confirm `display_errors=Off` in production; CodeIgniter's production bootstrap already disables detailed output.

When a reverse proxy/CDN terminates TLS, configure only the actual trusted proxy IP ranges in `app.proxyIPs`; never trust forwarding headers from every address.

## 4. Install and database commands

Run from the private application root:

```bash
composer install --no-dev --optimize-autoloader
php spark migrate --all
php spark cache:clear
```

Run `php spark db:seed DemoSeeder` only in a disposable development/staging database. It creates known demonstration credentials and must not be used for production family data.

Before migrating a live upgrade, take a database backup and verify its restore procedure. Run the full test suite against the target MariaDB/MySQL version in staging.

## 5. Writable and session permissions

- The PHP/web-server user needs write access only to `writable/cache`, `writable/logs`, `writable/session`, `writable/uploads`, and `writable/debugbar` when used.
- Prefer ownership-based `0750` or group-based `0770`, depending on the host. Do not use `0777`.
- Keep the file-session path outside `public/`; the default `writable/session` is suitable when ownership is correct. A private absolute path may be set through `session.savePath`.
- Confirm session garbage collection runs on the host and session files cannot be downloaded.

## 6. HTTPS and cookies

- Install and validate TLS before enabling `app.forceGlobalSecureRequests` and `cookie.secure`.
- Confirm Parent session, CSRF, and `rutinku_child_device` cookies are `Secure`, `HttpOnly`, and `SameSite=Lax` in browser developer tools.
- Test trusted Child provisioning from the final HTTPS hostname. Hostname changes invalidate the practical cookie binding and require reprovisioning.
- Consider HSTS only after every relevant subdomain supports HTTPS; enable it in cPanel/Apache at that point.

## 7. Security verification

- Auto-routing stays disabled; only declared routes are reachable.
- CSRF remains global and every state-changing action remains POST.
- Parent and Child pages return `Cache-Control: no-store`; shared proxies and the service worker must not retain family HTML.
- Confirm invalid/revoked Child devices receive `401 Device Setup Required`.
- Confirm a deactivated then reactivated Child cannot reuse the old device cookie; Parent must provision a new device.
- Confirm trusted-device rows have a server-side `expires_at` and an expired token is revoked on its next request.
- Confirm responses emit the restrictive Content Security Policy and browser assets load only from the application origin.
- Confirm production exceptions show a generic page while details go only to private logs.
- Inspect logs and audit entries to ensure no password, raw device token, cookie, CSRF token, or database credential appears.
- Restrict database privileges to the RutinKu database. The account needs normal DML and migration DDL, not global server administration.
- Back up the database and private `writable/uploads` if uploads are enabled later. Do not back up sessions/cache as application data.

## 8. PWA verification

- Serve `manifest.webmanifest`, `service-worker.js`, icons, and `offline.html` over HTTPS with correct MIME types.
- Open both Parent and trusted Child modes from the final origin and verify installation.
- Go offline after one successful load: navigation should show only `offline.html`, never a previously viewed authenticated page.
- Update `STATIC_CACHE` in `service-worker.js` whenever a cached asset changes.
- Keep the vendored Bootstrap file in the static allowlist; do not replace it with an unpinned runtime CDN URL.
- Keep service-worker scope at the application origin root. If the app is deliberately hosted in a subdirectory, update manifest paths, scope, service-worker asset URLs, and the public-path tests together.

## 9. Final smoke test

1. Parent login and logout.
2. Both Parents see the same family.
3. Provision, use, revoke, and reset each Child device.
4. Complete and undo a task; verify the ledger and points.
5. Add an adjustment with an audit entry.
6. Request, approve, and reject rewards.
7. Verify daily, weekly, monthly ranking and reports.
8. Verify no Child route exposes siblings, ranking, reports, logout, registration, or switching.
9. Verify PWA installation and offline fallback on Parent and Child phones.
