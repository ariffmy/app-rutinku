# RutinKu Phase 3 Report

Phase 3 implements recurring weekly routines and task templates, then stops before task completion and points.

## Delivered

- `routines`, `routine_days`, and `routine_tasks` migrations with foreign keys, schedule uniqueness, and resolver indexes.
- `RoutineModel`, `RoutineDayModel`, and `RoutineTaskModel` with narrow writable fields and validation.
- `RoutineService` for same-family list/create/read/update/delete operations, transactional day replacement, and task CRUD.
- `FamilyAuthorizationService` checks for routine and routine-task ownership through the Child/family relationship.
- `TodayTaskResolver` using ISO weekday values `1=Monday` through `7=Sunday` in `Asia/Kuala_Lumpur`.
- Parent routine listing, Child filter, create/edit/delete, weekly-day selection, and nested task management UI.
- Trusted Child Today schedule grouped by routine, including task time, optional/required state, and configured points.
- Every dynamic routine/task value is escaped. Every mutation route is Parent-filtered and CSRF protected.

## Resolver behaviour

- Reads recurring definitions directly; it does not create dated task copies.
- Includes only the authenticated trusted Child’s active routines scheduled for the local weekday.
- Includes only active tasks.
- Optional tasks are included and labelled but have no completion behaviour yet.
- Returns task count, required-task count, and available configured points for display only.
- Ordering is deterministic by `sort_order`, time, then ID.
- A date/instant supplied in another timezone is converted to Kuala Lumpur before resolving its weekday.

## Routes

```text
GET  /routines
GET  /routines/new
POST /routines
GET  /routines/{routine}/edit
POST /routines/{routine}
POST /routines/{routine}/delete

GET  /routines/{routine}/tasks/new
POST /routines/{routine}/tasks
GET  /routine-tasks/{task}/edit
POST /routine-tasks/{task}
POST /routine-tasks/{task}/delete
```

All routes are protected by `parent-auth`; all POST routes also use global CSRF protection.

## Adopted assumptions

- A routine must have at least one valid weekly day.
- `points` is configuration only until the ledger is implemented; Phase 3 never awards points.
- Routine and task delete are hard deletes in Phase 3 because no completion history exists yet. Before Phase 4, deletion must change to archive/deactivate or be blocked once historical completions exist.
- Moving a routine between Children is allowed only when both Children belong to the authenticated Parent’s family.
- Routine type remains nullable free text with a 50-character limit.

## Explicitly out of scope

No task completion, undo, completion dates, point transactions, balances, streaks, rewards, ranking, reports, PWA manifest, or service worker was added.

## Verification

Final automated result: **32 tests, 126 assertions, all passing** on PHP 8.4.25 with the CodeIgniter SQLite test connection. The suite includes all Phase 1–2 regressions plus Phase 3 CRUD, rendered management views, family authorization, timezone, active/scheduled filtering, sibling isolation, route-boundary, cascade, CSRF, and output-escaping checks. All nine migrations and `DemoSeeder` also passed through Spark CLI. A target-version MySQL/MariaDB integration run remains required before deployment.

## Review checklist before Phase 4

1. Confirm routine/task ordering and mobile presentation on each Child phone.
2. Confirm the Monday–Sunday selection behaviour and Kuala Lumpur timezone around midnight.
3. Decide whether routines/tasks with completion history will be archived or deletion-blocked.
4. Decide the Parent task-reversal permission/window required in Phase 4.
5. Confirm whether same-day routine edits should immediately alter the remaining Today list.
