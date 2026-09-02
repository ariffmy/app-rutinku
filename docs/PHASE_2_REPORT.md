# RutinKu Phase 2 Report

Phase 2 implements the trusted Child device boundary and stops before Routines.

## Delivered

- `audit_logs` and `user_devices` migrations with foreign keys and lookup indexes.
- Narrow `AuditLogModel` and `UserDeviceModel`.
- `AuditLogService` with recursive sensitive-key removal.
- `ChildDeviceService` for provisioning, resolution, listing, revocation, reset, cookie issue/expiry, and one-active-device enforcement.
- Request-scoped `TrustedChildContext`; Child controllers never accept identity from the frontend.
- `TrustedChildDeviceFilter` with fail-closed HTTP 401 setup-required response.
- Parent device-management UI and server-authorized setup/revoke/reset actions.
- Child Today placeholder accessible only through a valid trusted-device cookie.
- Audit events: `device.provisioned`, `device.revoked`, and `device.reset`.

## Security decisions

- Raw tokens are 32 random bytes encoded as 64 lowercase hexadecimal characters.
- The database stores only SHA-256 hashes in a unique column.
- The raw token is returned once in `rutinku_child_device`; it is never added to a view, URL, audit value, JavaScript, localStorage, or sessionStorage.
- Cookie lifetime is 180 days, `HttpOnly`, `SameSite=Lax`, path `/`, and follows `cookie.secure`. Production must set `cookie.secure = true` and force HTTPS.
- Invalid and revoked cookies are overwritten with an empty value and an expiry in the past.
- Provisioning destroys the Parent session on the browser being converted to Child Mode.
- Provisioning serializes on the Child user row for MySQL/MariaDB. Existing active devices are revoked in the same transaction.
- A revoked token is rejected on its next request. Child routes never redirect to public Parent login.

## Routes

```text
GET  /child
GET  /child/today
     filter: trusted-child-device

GET  /children/{child}/devices
POST /children/{child}/devices/setup
POST /children/{child}/devices/reset
POST /children/{child}/devices/{device}/revoke
     filter: parent-auth
```

Every POST also uses the global CSRF filter.

## Verification

Final automated result: **20 tests, 81 assertions, all passing** on PHP 8.4.25 with the CodeIgniter SQLite test connection. All six migrations and `DemoSeeder` also passed through Spark CLI. A target-version MySQL/MariaDB integration run remains required before deployment.

The automated suite covers:

- Parent-only and same-family provisioning.
- Token hash-at-rest and absence from audit logs.
- One active trusted device per Child.
- Valid trusted device access.
- Server-resolved Child identity despite a forged `child_id` query.
- Revoked and malformed token rejection.
- Explicit invalid-cookie expiry.
- Device/Child URL ownership checks.
- Reset and revoke auditing.
- Recursive sensitive audit-value sanitization.
- Persistent HttpOnly/SameSite cookie attributes.
- All Phase 1 regression tests.

## Review checklist before Phase 3

1. Test setup on each actual Child phone over production-like HTTPS.
2. Confirm the Parent session disappears immediately after setup.
3. Close/reopen the installed browser/PWA and confirm Child Mode persists.
4. Revoke from a Parent phone and confirm the Child phone shows “Device Setup Required” on its next request.
5. Test “Setup This Device” again and confirm the previous phone loses access.
6. Confirm cPanel/proxy preserves HTTPS detection so Secure cookies are emitted.

## Explicitly out of scope

No routines, routine days, routine tasks, completions, points, streaks, rewards, ranking, reports, manifest, service worker, or offline authenticated-page caching was added.
