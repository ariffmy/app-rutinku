# RutinKu — Architecture Semasa

RutinKu ialah server-rendered CodeIgniter PWA menggunakan Parent session serta session/trusted-device context untuk Child. Architecture ini merangkumi teras Phase 1–11 dan ciri lanjutan sehingga Weekly Missions.

## Security model

- Parent session disahkan semula terhadap active user, role, dan current family.
- Child tiada public login/register/logout/switch; identity hanya daripada server-resolved `TrustedChildContext`.
- Device token ialah 32 random bytes; database menyimpan SHA-256 hash sahaja. Browser dan server expiry ialah 180 hari.
- Deactivate Child revoke semua trusted devices transactionally; reactivation perlukan provisioning baru.
- Global session CSRF, disabled auto-routing, private/no-store dynamic responses, dan static-only PWA cache.

## Migration 1–26

`CreateUsers`, `CreateFamilies`, `CreateFamilyUsers`, `CreateChildProfiles`, `CreateAuditLogs`, `CreateUserDevices`, `CreateRoutines`, `CreateRoutineDays`, `CreateRoutineTasks`, `CreateTaskCompletions`, `CreatePointTransactions`, `CreateRewards`, `CreateRewardRedemptions`, dan `AddExpiryToUserDevices`.

Migration seterusnya menambah task schedules, kategori dan had ganjaran, routine/task groups, Parent Approval, Reward Goal, required/perfect-day eligibility, Perfect Day records, source metadata ledger, Achievements, dan Weekly Missions. Migration terkini ialah `CreateWeeklyMissions` versi `2026-09-11-000026`.

Points ialah append-only ledger. Balance, streak, ranking, dan reports dikira daripada source records. Historical data dipelihara melalui deactivate/archive dan reversal rows.

## Services

- Identity: `AuthService`, `FamilyService`, `FamilyAuthorizationService`, `ChildManagementService`, `ChildDeviceService`, `TrustedChildContext`, `AuditLogService`.
- Routine: `RoutineService`, `TodayTaskResolver`, `TaskCompletionService`, `PerfectDayService`.
- Points/rewards: `PointService`, `StreakService`, `RewardService`, `RewardGoalService`.
- Progress/gamification: `AchievementService`, `MissionService`.
- Analytics: `RankingService`, `ReportService`.

Services memiliki authorization/transactions. Parent IDs diperiksa terhadap family yang sama; Child controller tidak menerima Child ID daripada request. Row locks dan unique keys melindungi approval, points, reversals, completion, achievement, mission assignment, mission bonus, Perfect Day, dan Reward Goal daripada duplicate request.

## Functional surfaces

Parent-only: dashboard, Child CRUD/deactivate, devices, routine/task CRUD, approval, points, rewards, Weekly Missions, ranking, dan reports. Child-only: Today, complete/undo, progress, rewards/Reward Goal, Achievements, Weekly Missions, dan own profile. Tiada sibling, Child ranking, atau Child report endpoints.

## Rules

- Complete + award atomically; undo menambah reversal negatif.
- Reward approval lock rows, recheck balance, deduct, update, dan audit atomically.
- Reward Goal hanya menyimpan sasaran; mata hanya dipotong oleh redemption.
- Daily Progress mengira completion berstatus completed untuk rutin required hari tersebut.
- Perfect Day memerlukan semua rutin required dan eligible; pending/rejected tidak layak dan bonus 10 mata hanya sekali.
- Neutral day tidak menambah atau memutus streak.
- Achievement membaca activity sebenar dan unik bagi setiap Child + achievement.
- Weekly Mission menyokong `routine_completion_count` dan `perfect_day_count`; progress datang daripada rekod server dalam julat maksimum tujuh hari.
- Bonus achievement dan mission masuk melalui `PointService` dengan `reference_type`/`reference_id` unik.
- Ranking hanya eligible Children; reports semua active Children.
- Current period hanya sehingga hari ini.

## Akaun Anak dan rutin berkumpulan

Update Anak mengekalkan e-mel semasa apabila medan tidak berubah dan memeriksa duplicate terhadap pengguna lain. Login serta borang kata laluan Anak menyediakan kawalan show/hide tanpa menyimpan password dalam old input.

Rutin `assignment_scope = all` mempunyai satu salinan bebas bagi setiap Anak. Penciptaan Anak menyalin semua group routine, hari dan task sedia ada dalam transaksi yang sama. `RoutineService::listForParent()` turut reconcile salinan yang pernah tertinggal; pemeriksaan berulang adalah idempotent dan group rows dikunci sebelum insert.

## PWA/browser

Manifest, icons, install prompt, service worker, dan offline fallback tersedia. Navigation network-only `cache: 'no-store'`; Bootstrap 5.3.3 ialah same-origin. CSP mengehadkan browser sources. Tiada token/state dalam Web Storage.

## cPanel dan final gate

Document root mesti ke `<project>/public`; alternatif hanya salin kandungan `public/` ke `public_html`. `.env`, `app`, `vendor`, dan `writable` tidak boleh exposed.

- Migration 1–26 dan `DemoSeeder` disokong oleh SQLite test database; migration 26 turut disahkan pada MySQL development.
- Ujian tertumpu tersedia untuk points integrity, Reward Goal, progress, Perfect Day, Achievements, Weekly Missions, akaun Anak, dan routine-group reconciliation.
- Composer validation/audit: lulus, tiada advisory.
- Security scan: 3 penemuan snapshot, semuanya diremediasi.

Live HTTPS, Secure cookies, document root, ownership, dan MySQL/InnoDB masih perlu disahkan sebelum go-live.
