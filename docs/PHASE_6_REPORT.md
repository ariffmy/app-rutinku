# Phase 6 Report — Streak

Phase 6 implements `StreakService`, perfect-day evaluation, period perfect-day counts, and current streak.

- A day qualifies only when at least one required task is scheduled.
- Every scheduled required task must be completed for a perfect day.
- Optional tasks never affect perfect-day or streak success.
- Days without required tasks are neutral: they neither increment nor break a streak.
- Today remains in progress and does not break the prior streak unless it is already perfect, in which case it increments the streak.
- Calculations use `Asia/Kuala_Lumpur` and current active routine definitions; no aggregate streak table is introduced.

The Child Today and Progress screens show only that Child’s current streak.
