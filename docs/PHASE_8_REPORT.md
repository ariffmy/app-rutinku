# Phase 8 Report — Ranking

Phase 8 implements calculated, Parent-only daily, weekly, and monthly rankings without a ranking table.

- Daily periods use the Kuala Lumpur calendar date.
- Weekly periods are Monday–Sunday; monthly periods use calendar months.
- Current periods calculate scheduled/completed metrics only through today, avoiding future-task dilution.
- Score uses earned `task` and `bonus` ledger rows. Reward, adjustment, and reversal rows are excluded exactly as specified.
- Tie-breakers are earned points, completion percentage, perfect days, current streak, then name ascending.
- Ranking eligibility is applied before calculation.
- Child routes expose no ranking or sibling data.
- Parent Dashboard now includes tasks today/completed, pending rewards, today’s leader, and family progress.

Combined Phase 6–8 verification completes with **69 tests and 276 assertions**. All 13 migrations migrate, roll back, and migrate again on SQLite. Target-version MySQL/MariaDB verification remains required before deployment.
