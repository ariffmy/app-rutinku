# Phase 7 Report — Rewards

Phase 7 implements the shared family reward catalogue and approval-based redemption workflow.

- Added `rewards` and `reward_redemptions` migrations, models, and `RewardRedemptionStatus`.
- Parent can create, edit, and deactivate family rewards.
- Trusted Child can request an affordable active reward; request identity always comes from the device context.
- Request remains pending and does not reserve or deduct points.
- Approval locks the Child/redemption, rechecks balance, appends one negative reward transaction, updates status, and writes an audit event atomically.
- Rejection changes status and writes an audit event without deducting points.
- `points_used` snapshots the reward cost when requested.
- Same-family authorization is enforced server-side for every Parent action.

Insufficient-balance, duplicate decision, cross-family, sibling-forgery, and ledger idempotency paths are covered by tests.
