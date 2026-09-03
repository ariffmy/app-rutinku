# Phase 5 Report — Points

## Outcome

Phase 5 makes `point_transactions` the source of truth for every point balance. Task completion awards points once, undo appends one negative reversal without deleting ledger history, and both operations commit atomically with completion state.

## Delivered

- Migration `CreatePointTransactions` with signed points, required indexes, foreign keys, and unique type/reference idempotency.
- Safe backfill of active Phase 4 completions into one task transaction per completion during migration.
- `PointTransactionType`, append-only `PointTransactionModel`, `PointException`, and `PointService`.
- Ledger-derived balance and chronological account history.
- Task award and reversal methods that use completion snapshots and stable polymorphic references.
- Earned points include only task/bonus awards that have not been cancelled by a linked reversal. Reward spending and manual adjustments do not affect this score. Repeated complete/undo cycles cannot accumulate earned points; the original ledger history remains intact.
- Parent-only points screen with same-family Child selection, signed manual adjustments, mandatory reason, CSRF, and audit logs.
- Trusted Child Today balance and Progress balance/history with no sibling data.
- Transaction rollback tests proving a completion cannot exist without its award and an undo cannot succeed without its reversal.

## Ledger rules

- Balance is always `SUM(point_transactions.points)`; no mutable balance column exists.
- Ledger rows cannot be updated or deleted through `PointTransactionModel`.
- Task awards reference `task_completion`; reversals reference the original point transaction.
- Unique `(type, reference_type, reference_id)` prevents duplicate task awards and reversals while allowing multiple manual adjustments with null references.
- Manual adjustments may be positive or negative, cannot be zero, require a reason, and create an audit record in the same transaction.

## Phase boundary at delivery

At Phase 5 delivery, rewards, streak, and ranking remained deferred. They were subsequently implemented in Phases 6–8; reports, PWA assets, and later profile functionality remain outside this report.

## Verification

Final automated result: **55 tests, 218 assertions, all passing** on PHP 8.4.25 using the CodeIgniter SQLite test connection. All eleven migrations and `DemoSeeder` pass through Spark CLI. A second integration run against the exact cPanel MySQL/MariaDB version remains a deployment gate.
