# Phase 11 — cPanel Deployment, Security, dan Final QA

Status: **lengkap**.

## Hardening/remediation

- Root deny-by-default, public rewrite/MIME/cache rules, dan full cPanel checklist.
- Private/no-store response, Permissions Policy, restrictive CSP, dan same-origin Bootstrap.
- Failed login flash tidak menyimpan password; dummy hash mengurangkan timing distinction.
- Child deactivation revoke trusted devices atomically dan diaudit.
- Device token mempunyai server expiry 180 hari dan fail closed apabila expired.
- Production environment overrides dan sensitive trace masking disediakan.

## Verification

- 79 tests / 362 assertions: lulus.
- 155 PHP files: lint lulus.
- Migration 1–14 refresh + DemoSeeder: lulus.
- Composer strict validation: lulus.
- Dependency audit: tiada advisory.
- Security scan: 3 penemuan asal (1 sederhana, 2 rendah), semuanya diremediasi.

Live cPanel values masih wajib disahkan sebelum go-live.
