# Security Review: app-rutinku

> Remediation status (final tree): ketiga-tiga penemuan dalam snapshot ini telah dibaiki selepas laporan dimeterai. Failed login hanya flash input bukan rahsia; Child deactivation revoke token lama dan token mempunyai server-side expiry; Bootstrap kini same-origin dengan restrictive CSP. Lihat `PHASE_11_REPORT.md` untuk verification final.

## Scope

Complete RutinKu Phase 11 source security review.

- Scan mode: repository
- Target kind: directory_snapshot
- Target ID: target_sha256_4e7fc84a2962c389e8e583f84607505e02461af4eea05001e0f8294214b1be15
- Snapshot digest: codex-security-snapshot/v1:sha256:af671115a10ca3be4ee255adcf6df732f5eb0bc25d3c374e66e3e7bb70f4ceb5
- Inventory strategy: directory
- Included paths: .
- Excluded paths: none
- Runtime or test status: Tests, migrations, seed, Composer validation, and advisory audit passed before scan.
- Artifacts reviewed: app, public, .env.example, .htaccess, docs/CPANEL_DEPLOYMENT.md, targeted CodeIgniter sinks
- Scan context: Authentication, Child devices, family authorization, CSRF, transaction safety, PWA privacy, production configuration, and public exposure.

Limitations and exclusions:
- No live cPanel host or production configuration was available.
- The snapshot has no Git metadata.
- Excluded vendor/\*\*: Excluded except exact CodeIgniter sinks needed for validation.
- Excluded _tools/\*\*: Temporary local verification runtimes.
- Excluded build/\*\*: Generated test artifacts.
- Excluded vendor/\*\*: Third-party dependency source is excluded; locked production dependencies are checked separately for advisories.
- Excluded vendor/\*\*: Third-party dependencies are excluded as product source; composer.lock receives a separate advisory check.

### Scan Summary

| Field | Value |
| --- | --- |
| Scan outcome | completed |
| Reportable findings | 3 |
| Severity mix | medium: 1, low: 2 |
| Confidence mix | high: 3 |
| Coverage | partial |
| Validation mode | Multi-perspective manual source review and direct sink validation. |

Canonical artifacts: `scan-manifest.json`, `findings.json`, and `coverage.json`. This report is a deterministic projection of those files.

## Threat Model

A family PWA using Parent sessions and persistent Child device tokens; primary boundaries are credentials, family scope, device lifecycle, transactional ledgers, private caching, browser dependencies, and the cPanel public root.

### Assets

- Parent credentials and sessions
- Child bearer tokens
- Family data
- Points and rewards
- Production secrets

### Trust Boundaries

- Browser/application
- Parent/family services
- Child cookie/context
- Application/database
- Service worker/dynamic pages
- public/private files
- UI/third-party CSS

### Attacker Capabilities

- Remote unauthenticated requests
- Authenticated Parent ID manipulation
- Possession of copied Child cookie
- Cross-site browser requests
- Accidental shared-host exposure

### Security Objectives

- No plaintext secret persistence
- Family and Child isolation
- Authenticator revocation on disablement
- Atomic ledgers
- No authenticated caching
- Public-root isolation
- Controlled browser dependencies

### Assumptions

- Production follows deployment guide.
- HTTPS, Secure cookies, and MySQL/InnoDB are used.
- Parents manage their own family.

## Findings

| Finding | Severity | Confidence | Detailed write-up |
| --- | --- | --- | --- |
| [Reactivating a Child resurrects trusted-device bearer credentials issued before deactivation](#finding-1) | medium | high | inline below |
| [Failed Parent logins persist the submitted password in session flashdata](#finding-2) | low | high | inline below |
| [Authenticated and login pages trust remote Bootstrap CSS without integrity or CSP containment](#finding-3) | low | high | inline below |

### Confidence Scale

| Label | Meaning |
| --- | --- |
| high | Direct evidence supports the finding with no material unresolved blocker. |
| medium | Evidence supports a plausible issue, but material runtime or reachability proof remains. |
| low | Evidence is incomplete and the item is retained only for explicit follow-up. |

<a id="finding-1"></a>

### [1] Reactivating a Child resurrects trusted-device bearer credentials issued before deactivation

| Field | Value |
| --- | --- |
| Severity | medium |
| Confidence | high |
| Confidence rationale | The deactivate/reactivate path, unchanged user_devices row, and request-time active check were traced directly. |
| Category | Authentication lifecycle |
| CWE | CWE-613 |
| Affected lines | app/Services/ChildManagementService.php:115-169, app/Services/ChildDeviceService.php:196-232, app/Services/ChildDeviceService.php:152-193 |

#### Summary

Deactivation changes only users.is_active. Previously trusted device rows remain valid and regain Child authority when the account is reactivated.

#### Root Cause

Identity deactivation and persistent-authenticator revocation are not coupled transactionally.

**Deactivation updates only the user** — `app/Services/ChildManagementService.php:127`

No device row is revoked when the Child is disabled.

```
$users->update($childUserId, ['name' => $name, 'is_active' => $isActive])
```

**Old trusted rows are accepted after reactivation** — `app/Services/ChildDeviceService.php:207`

The old row stays trusted and unrevoked; reactivation removes the separate active-user rejection.

```
if ($device === null || ! (bool) $device['is_trusted'] || $device['revoked_at'] !== null) { return false; }
```

#### Validation

Validation outcomes are recorded below.

Validation method: Manual lifecycle trace across Parent update and device resolution.

- **Disposition:** reportable

Evidence:
- No device update occurs on deactivation and the row is accepted after reactivation.

#### Dataflow

Retained cookie -\> deactivation without revocation -\> reactivation -\> old token passes again

#### Reachability

Requires possession of an old authorized cookie and later legitimate reactivation.

Preconditions:
- Cookie remains within lifetime.
- Child is reactivated.

Existing controls:
- HttpOnly SameSite cookie
- Inactive-user check
- Explicit Parent reset

#### Severity

**Medium** — A retained 180-day device cookie can regain authenticated Child capabilities without a new Parent provisioning ceremony.

Lower if deactivation is irreversible or device rows are independently revoked; raise if Child permissions expand.

**Impact assessment:** Child task, progress, undo, and reward actions without reprovisioning.

**Likelihood assessment:** medium

#### Remediation

Revoke every active Child device in the same transaction when an active Child becomes inactive. Require fresh provisioning after reactivation.

Tests:
- Provision, deactivate, reactivate, and verify the old cookie remains unauthorized.
- Verify deactivation revokes all active devices atomically.

<a id="finding-2"></a>

### [2] Failed Parent logins persist the submitted password in session flashdata

| Field | Value |
| --- | --- |
| Severity | low |
| Confidence | high |
| Confidence rationale | The complete application-to-framework-to-file-session flow was verified directly. |
| Category | Sensitive data storage |
| CWE | CWE-312 |
| Affected lines | app/Controllers/Auth/LoginController.php:27-43, vendor/codeigniter4/framework/system/HTTP/RedirectResponse.php:92-98, app/Config/Session.php:25-61 |

#### Summary

Every failed Parent login path uses withInput(), causing CodeIgniter to serialize the entire POST body, including the plaintext password, into a file-backed session.

#### Root Cause

Generic old-input persistence is used instead of a non-secret allowlist.

**Authentication form preserves all fields** — `app/Controllers/Auth/LoginController.php:27`

withInput is called on a form containing password.

```
return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
```

**Framework persists the complete POST array** — `vendor/codeigniter4/framework/system/HTTP/RedirectResponse.php:95`

No password field is excluded before session serialization.

```
$session->setFlashdata('_ci_old_input', ['get' => $request->getGet(), 'post' => $request->getPostArray()]);
```

#### Validation

Validation outcomes are recorded below.

Validation method: Direct source-to-sink review.

- **Disposition:** reportable

Evidence:
- withInput stores getPostArray and the configured FileHandler persists it.

#### Dataflow

POST password -\> failed LoginController path -\> withInput -\> _ci_old_input -\> file session

#### Reachability

Any visitor can cause the write; reading requires unintended storage access.

Preconditions:
- Read access to session storage or copied backup.

Existing controls:
- Non-public writable directory
- Restrictive file permissions

#### Severity

**Low** — Credential disclosure is serious, but exploitation requires unintended read access to session files or backups; intended private storage and restrictive permissions reduce likelihood.

Raise if writable/session or backups are web-accessible or cross-tenant readable.

**Impact assessment:** Plaintext Parent credential disclosure.

**Likelihood assessment:** low

#### Remediation

Remove withInput() from every login failure branch and retain only normalized email and remember_me.

Tests:
- Invalid login preserves email but never password in _ci_old_input.
- Validation, throttle, and bad-credential branches exclude password.

<a id="finding-3"></a>

### [3] Authenticated and login pages trust remote Bootstrap CSS without integrity or CSP containment

| Field | Value |
| --- | --- |
| Severity | low |
| Confidence | high |
| Confidence rationale | Every application HTML entry point and CSP configuration were inspected. |
| Category | Supply-chain integrity |
| CWE | CWE-353 |
| Affected lines | app/Views/auth/login.php:8-9, app/Views/layouts/parent.php:11-15, app/Views/layouts/child.php:11-15, app/Views/child/device_setup_required.php:7-9, app/Config/App.php:187-201 |

#### Summary

Login, Parent, Child, and setup views load Bootstrap from a third-party CDN without SRI while CSP generation is disabled.

#### Root Cause

A browser dependency crosses a third-party boundary without local vendoring, integrity, or policy containment.

**Authenticated pages load an unpinned remote stylesheet** — `app/Views/layouts/parent.php:14`

The dependency is neither vendored nor protected by SRI.

```
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
```

**Framework CSP is disabled** — `app/Config/App.php:201`

No application CSP contains the third-party resource.

```
public bool $CSPEnabled = false;
```

#### Validation

Validation outcomes are recorded below.

Validation method: HTML entry-point and CSP config review.

- **Disposition:** reportable

Evidence:
- Four pages use cdn.jsdelivr without SRI and CSP is disabled.

#### Dataflow

Page load -\> CDN stylesheet fetch -\> compromised CSS applied to login/family UI

#### Reachability

A CDN or dependency-origin compromise affects page loads.

Preconditions:
- Malicious delivery from trusted CDN URL.

Existing controls:
- HTTPS
- Version pin
- Same-origin application JavaScript

#### Severity

**Low** — Compromised CSS can alter security-critical UI and cause outbound requests, but cannot directly execute JavaScript in modern browsers.

Raise if remote scripts or CSS-addressable secret values are introduced.

**Impact assessment:** UI deception, availability loss, and CSS-triggered outbound requests.

**Likelihood assessment:** low

#### Remediation

Vendor Bootstrap under public/assets and enable a tested restrictive CSP for application and PWA resources.

Tests:
- Views contain no third-party stylesheet URL.
- Responses emit a restrictive CSP and service worker registration remains allowed.

## Reviewed Surfaces

| Surface | Risk Area | Outcome | Notes |
| --- | --- | --- | --- |
| Parent authentication and session lifecycle | not recorded | Reported | No additional canonical notes were recorded. |
| Trusted Child device lifecycle | not recorded | Reported | No additional canonical notes were recorded. |
| Family and object authorization | not recorded | No issue found | No additional canonical notes were recorded. |
| Task, points, rewards, and concurrency | not recorded | No issue found | No additional canonical notes were recorded. |
| CSRF and route protection | not recorded | No issue found | No additional canonical notes were recorded. |
| PWA caching and offline behavior | not recorded | No issue found | No additional canonical notes were recorded. |
| Browser dependency integrity and CSP | not recorded | Reported | No additional canonical notes were recorded. |
| Deployment public root and production defaults | not recorded | No issue found | No additional canonical notes were recorded. |
| Login timing differences | not recorded | Rejected | No additional canonical notes were recorded. |
| Streak query cost | not recorded | Rejected | No additional canonical notes were recorded. |
| Architecture and trust boundaries | System boundaries | No issue found | Source-backed mapping completed; deployment prerequisites remain external. |
| Parent login and session handling | Authentication and secret handling | Needs follow-up | Two baseline candidates await validation. |
| Trusted Child device credential lifecycle | Authentication and credential lifecycle | Needs follow-up | Server-side expiry candidate awaits validation. |
| Streak calculation request cost | Availability | Needs follow-up | Potential unbounded historical loop awaits validation. |

## Open Questions And Follow Up

- Verify cPanel HTTPS, Secure cookies, public document root, permissions, and MySQL/InnoDB at deployment.
- Awaiting parent validation and independent investigator result.
  - Follow-up prompt: Review deferred unit deferred.login-password-flashdata and close its stated proof gap. Paths: app/Controllers/Auth/LoginController.php, app/Config/Session.php. Surfaces: auth.parent-login.
- Awaiting parent validation and independent investigator result.
  - Follow-up prompt: Review deferred unit deferred.child-token-expiration and close its stated proof gap. Paths: app/Services/ChildDeviceService.php, app/Database/Migrations/2026-09-01-000006_CreateUserDevices.php. Surfaces: auth.child-device.
- Awaiting parent validation and independent investigator result.
  - Follow-up prompt: Review deferred unit deferred.login-timing and close its stated proof gap. Paths: app/Services/AuthService.php, app/Controllers/Auth/LoginController.php. Surfaces: auth.parent-login.
- Awaiting parent validation and independent investigator result.
  - Follow-up prompt: Review deferred unit deferred.streak-resource-cost and close its stated proof gap. Paths: app/Services/StreakService.php, app/Services/TaskCompletionService.php. Surfaces: availability.streak.
