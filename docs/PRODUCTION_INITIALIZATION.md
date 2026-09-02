# Controlled production initialization

`ProductionSeeder` initializes **one family and two active Parent users only**. It never creates Child users, profiles, routines, tasks, rewards, devices, or other demo data. No public registration or Super Admin is introduced. No new migration is required.

## Configure private environment values

Set the following seven variables in the private production `.env` or hosting environment. `.env.example` intentionally contains only blank placeholders:

```dotenv
RUTINKU_FAMILY_NAME =
RUTINKU_PARENT1_NAME =
RUTINKU_PARENT1_EMAIL =
RUTINKU_PARENT1_PASSWORD =
RUTINKU_PARENT2_NAME =
RUTINKU_PARENT2_EMAIL =
RUTINKU_PARENT2_PASSWORD =
```

Supply your real values privately, quoting values with spaces or special characters as appropriate for CodeIgniter dotenv. Never commit credentials or paste them into command arguments, screenshots, logs, or support messages. Preserve `CI_ENVIRONMENT = production`, HTTPS, and secure-cookie settings from the cPanel guide.

All seven values are required, including on reruns. Names are trimmed and limited to 120 characters; emails are trimmed, lowercased, validated, limited to 190 characters, and must differ. Passwords must be non-blank, contain no NUL bytes, and fit within 72 bytes to avoid bcrypt truncation; intentional surrounding password spaces are preserved. Use strong, distinct passwords. Only `password_hash(..., PASSWORD_DEFAULT)` output is stored in the database.

## First setup sequence

1. Back up any existing live database and confirm the intended private `.env` and database target.
2. Configure the seven initialization variables above.
3. From the private application root, run the commands below **one process at a time**.
4. Login as a configured Parent.
5. Open **Children** and create Child 1, Child 2, and Child 3 through the existing Parent UI.
6. On each Child's phone, login as Parent and use the existing trusted-device setup flow.
7. After successful setup, remove the two bootstrap password variables from the private environment when no longer needed. Existing logins continue using stored hashes. Re-supply the required values privately only if intentionally rerunning initialization.

```bash
php spark migrate
php spark db:seed ProductionSeeder
```

These are operator instructions, not commands automatically executed by this change. Never reset or refresh the live database to initialize accounts.

## Reruns and existing live data

- An existing sole family is reused only when its name matches `RUTINKU_FAMILY_NAME` exactly after trimming the configured value. A different name or multiple families causes a clear exception and rollback.
- Parents are resolved by email without duplication. An existing user must already have the correct role and be active; the seeder never promotes a Child, reactivates a disabled user, renames users, or resets passwords.
- Missing memberships are added only if the user has no other family. Existing memberships are reused, not duplicated or moved.
- Any existing Parent outside the configured pair causes rejection, preventing accidental creation of a third Parent when configuration changes.
- All writes are transactional. Every insert must succeed before its ID is used. A failure creating either Parent or membership rolls back the entire run.
- This is a serial initialization tool, not a concurrent provisioning service. Run it once at a time.

If the live database still contains demo accounts/family, this seeder **does not convert, delete, or replace them**. A mismatch stops safely. Review and plan that migration separately; do not change configuration merely to bypass the guard, and do not assume rerunning changes a known demo password.

## DemoSeeder

`DemoSeeder` remains available for development/testing, but throws `RuntimeException` when either the booted `ENVIRONMENT` or `CI_ENVIRONMENT` is `production`. Compatible demo records and profiles are reused on reruns. Ambiguous families, role conflicts, inactive identities, or conflicting memberships stop with rollback instead of using a stale insert ID.

## Tests

```bash
php vendor/bin/phpunit --filter SeederInitializationTest
php vendor/bin/phpunit
```

Seeder tests explicitly create their own SQLite `:memory:` connection and schema; they do not use production database credentials. They cover initialization, no Child/demo data, password hashing, reruns, partial-existing setup, all missing/blank variables, invalid/duplicate emails, production guards, conflicts, and forced insert failures with rollback.

Verified locally on PHP 8.4.25 / SQLite: **41 seeder tests, 192 assertions** and **120 full-suite tests, 554 assertions**, all passing. The live MariaDB/MySQL database was not contacted or initialized.
