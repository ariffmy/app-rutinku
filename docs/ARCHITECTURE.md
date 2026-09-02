# RutinKu — Final Architecture (Phase 1–11)

Semua Phase 1–11 telah siap. RutinKu ialah server-rendered CodeIgniter PWA menggunakan Parent session dan persistent trusted-device cookie untuk Child.

## Security model

- Parent session disahkan semula terhadap active user, role, dan current family.
- Child tiada public login/register/logout/switch; identity hanya daripada server-resolved `TrustedChildContext`.
- Device token ialah 32 random bytes; database menyimpan SHA-256 hash sahaja. Browser dan server expiry ialah 180 hari.
- Deactivate Child revoke semua trusted devices transactionally; reactivation perlukan provisioning baru.
- Global session CSRF, disabled auto-routing, private/no-store dynamic responses, dan static-only PWA cache.

## Migration 1–14

`CreateUsers`, `CreateFamilies`, `CreateFamilyUsers`, `CreateChildProfiles`, `CreateAuditLogs`, `CreateUserDevices`, `CreateRoutines`, `CreateRoutineDays`, `CreateRoutineTasks`, `CreateTaskCompletions`, `CreatePointTransactions`, `CreateRewards`, `CreateRewardRedemptions`, dan `AddExpiryToUserDevices`.

Points ialah append-only ledger. Balance, streak, ranking, dan reports dikira daripada source records. Historical data dipelihara melalui deactivate/archive dan reversal rows.

## Services

- Identity: `AuthService`, `FamilyService`, `FamilyAuthorizationService`, `ChildManagementService`, `ChildDeviceService`, `TrustedChildContext`, `AuditLogService`.
- Routine: `RoutineService`, `TodayTaskResolver`, `TaskCompletionService`.
- Points/rewards: `PointService`, `StreakService`, `RewardService`.
- Analytics: `RankingService`, `ReportService`.

Services memiliki authorization/transactions. Parent IDs diperiksa terhadap family yang sama; Child controller tidak menerima Child ID daripada request. Row locks dan unique keys melindungi approval, points, reversals, dan completion idempotency.

## Functional surfaces

Parent-only: dashboard, Child CRUD/deactivate, devices, routine/task CRUD, points, rewards/approval, ranking, dan reports. Trusted Child-only: Today, complete/undo, progress, rewards, dan own profile. Tiada sibling, Child ranking, atau Child report endpoints.

## Rules

- Complete + award atomically; undo menambah reversal negatif.
- Reward approval lock rows, recheck balance, deduct, update, dan audit atomically.
- Perfect day memerlukan semua required tasks; optional tidak mempengaruhi streak.
- Neutral day tidak menambah atau memutus streak.
- Ranking hanya eligible Children; reports semua active Children.
- Current period hanya sehingga hari ini.

## PWA/browser

Manifest, icons, install prompt, service worker, dan offline fallback tersedia. Navigation network-only `cache: 'no-store'`; Bootstrap 5.3.3 ialah same-origin. CSP mengehadkan browser sources. Tiada token/state dalam Web Storage.

## cPanel dan final gate

Document root mesti ke `<project>/public`; alternatif hanya salin kandungan `public/` ke `public_html`. `.env`, `app`, `vendor`, dan `writable` tidak boleh exposed.

- PHPUnit: 79 tests, 362 assertions.
- PHP lint: 155 files.
- Migration 1–14 refresh + seed: lulus.
- Composer validation/audit: lulus, tiada advisory.
- Security scan: 3 penemuan snapshot, semuanya diremediasi.

Live HTTPS, Secure cookies, document root, ownership, dan MySQL/InnoDB masih perlu disahkan sebelum go-live.
