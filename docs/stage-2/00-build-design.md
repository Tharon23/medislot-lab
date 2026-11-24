# MediSlot Lab — Stage II Build Design

| Field | Value |
|-------|-------|
| Version | 1.0 (draft for approval) |
| Date | 2026-11-24 |
| Related | `docs/stage-1/01-statement-of-work.md`, `docs/stage-1/02-threat-model.md` |
| Status | Awaiting approval before build |
| Repo (planned) | `github.com/Tharon23/medislot-lab` (private) |

---

## 1. Intent and why

Build **MediSlot**, a deliberately vulnerable clinic appointment and patient-record
portal, as the Stage II lab. The team (SecureTrio) clones it from GitHub, runs it with
`docker compose up`, and pentests it against the Stage I threat model. Every planted
weakness must be real, reproducible, and map to a risk (R1–R10), a CWE, and — for the
primary RCE — a 2026 CVE.

This is a **teaching lab**, not production. All data synthetic. It exists to be broken.

## 2. Goals

- One-command bring-up: `docker compose up` on localhost, no external services.
- Realistic clinic UI (4 role dashboards) so the pentest feels authentic.
- ~15–20 planted findings: a few Critical/High, majority Medium, a few Low/Info.
- Primary RCE = **PHP2Shell (CVE-2026-109618 / CVE-2026-109619)** via a pinned
  vulnerable PHP-FPM version.
- Each finding documented in a matrix: file, role, R-id, CWE, how planted, how exploited.
- Seed data: 4 role accounts + synthetic patients, appointments, notes, attachments.
- Trivy/Lynis produce genuine Low/Info findings from the chosen base images.

## 3. Non-goals

- No production hardening, no real auth provider (Keycloak), no TLS.
- No automated exploit scripts shipped in the repo (testers do the exploitation).
- No real PII/PHI. No payment data beyond synthetic markers.
- Not bulletproof code quality — intentional weaknesses stay.

## 4. Anti-goals (would make this a failure even if it runs)

- Vulnerabilities that are fake/unexploitable (e.g. a "SQLi" that cannot actually inject).
- Headline web vulns solvable with a bare one-step payload (e.g. `<script>alert(1)</script>`,
  `' OR 1=1 --`, renaming a shell to `.php`). The key web findings must sit behind a naive
  but real filter/obstacle that forces a deliberate bypass — minutes-to-hours of effort, not
  an instant hit. (Infra/config findings in #16–#20 may stay simple.)
- A single god-file that concentrates all vulns — must feel like a real legacy app.
- Host-reachable DB/Redis by default beyond what the threat model intends.
- Secrets or vulns so hidden testers cannot find them within the engagement window.

## 5. Constraints (locked by Stage I)

- Plain PHP, no framework. Docker Compose, localhost only.
- Stack: Debian base, Nginx (reverse proxy), PHP-FPM (pinned vulnerable), MySQL, MinIO,
  Mailhog, Redis.
- Roles: Patient, Doctor, Reception, Admin.
- Ports (Appendix B): Nginx 8080, Mailhog 8025, MinIO 9000/9001. MySQL/Redis not
  published to host by default.

## 6. Approaches considered

| Option | Summary | Verdict |
|--------|---------|---------|
| **A — classic multi-file PHP** (chosen) | Each endpoint its own `.php` (login, appointment, note, upload, admin…), shared `db.php`/`auth.php`/`session.php` includes. | **Picked.** Realistic legacy app; each planted vuln self-contained and mappable. |
| B — single front-controller router | One `index.php` dispatches all routes. | Rejected. Cleaner than the legacy app the threat model describes; concentrates code, makes vulns feel artificial. |

**Primary RCE choice:** PHP2Shell (CVE-2026-109618/619) over CVE-2026-129289 (PHP-FPM
request smuggling). Both hit PHP-FPM, but PHP2Shell has public PoCs, is on CISA KEV, and
reproduces reliably once we fix the OS+version+server combo. Request smuggling needs
specific FastCGI connection-reuse config and is harder to make deterministic in a lab.

**Open validation (scaffold milestone):** confirm the exact `php:8.x.y-fpm` Docker tag
that is (a) below the fix (8.2.35 / 8.3.36 / 8.4.27 / 8.5.12) and (b) matched by a working
public PoC. Candidate: `php:8.3.35-fpm`. If no reliable PoC combo exists, fall back to
CVE-2026-129289 or the upload→webshell chain as the demonstrated Critical, and record
PHP2Shell as a version-fingerprint finding.

## 7. Architecture

```
Browser (host :8080)
   -> Nginx (reverse proxy, serves static + proxies .php to PHP-FPM)
        -> PHP-FPM (MediSlot app, PINNED vulnerable version)
             -> MySQL      (users, patients, appointments, notes, attachments meta, resets)
             -> Redis       (PHP sessions; no AUTH)
             -> MinIO       (attachment objects, S3 API)
             -> Mailhog     (password-reset emails, SMTP sink)
```

Compose services: `nginx`, `php` (build from Dockerfile pinning the PHP version),
`mysql`, `redis`, `minio`, `mailhog`. One `.env` (intentionally committed) holds lab
secrets. Named volumes for MySQL and MinIO data. Init SQL + seed script run on first boot.

## 8. Data model (MySQL)

- `users` (id, email, password_hash, role, full_name, is_active, created_at)
- `patients` (id, user_id, dob, phone, address, pesel_synth)
- `doctors` (id, user_id, specialty)
- `assignments` (doctor_id, patient_id)  — intended authz boundary (deliberately not enforced)
- `appointments` (id, patient_id, doctor_id, starts_at, status, reason)
- `notes` (id, patient_id, doctor_id, body_html, created_at)  — stored XSS sink
- `attachments` (id, patient_id, object_key, filename, content_type, uploaded_by)
- `password_resets` (id, user_id, token, created_at)  — weak token

## 9. Seed accounts (Appendix A fill)

| Role | Login | Lab password |
|------|-------|--------------|
| Patient | `pacjent1@novamed.lab` | `Patient123!` |
| Doctor | `lekarz1@novamed.lab` | `Doctor123!` |
| Reception | `recepcja@novamed.lab` | `Reception123!` |
| Admin | `admin@novamed.lab` | `Admin123!` |

Plus ~8 synthetic patients with appointments, notes, and a couple of attachments.

## 10. Planted vulnerability matrix (target 20)

Severity mix: Critical/High = 6, Medium = 8, Low/Info = 6.

| # | Vulnerability | Sev | R | CWE | File / location | Role to exploit |
|---|---------------|-----|---|-----|-----------------|-----------------|
| 1 | **PHP2Slot RCE** via pinned PHP-FPM (CVE-2026-109618/619) | Critical | R1 | — | `Dockerfile` (pinned version) | unauth |
| 2 | File upload -> webshell RCE (content-type-only check, double ext, stored in webroot) | Critical | R2 | 434 | `upload.php`, `uploads/` | Patient+ |
| 3 | IDOR on notes / appointments / attachments (no owner/assignment check) | High | R3 | 639 | `note.php`, `appointment.php`, `download.php` | any logged-in |
| 4 | Vertical priv-esc via mass-assignment of `role` on profile update | High | R4 | 915/269 | `profile_update.php` | Patient -> Admin |
| 5 | Broken access control: admin endpoints lack role check | High | R4 | 285 | `admin_users.php`, `admin_role.php` | any logged-in |
| 6 | Path traversal in attachment download (`?file=../../`) | High | R2 | 22 | `download.php` | any logged-in |
| 7 | Stored XSS in doctor note / appointment reason | Medium | R5 | 79 | `note.php`, `note_view.php` | Doctor/Patient |
| 8 | Weak password reset: predictable token, no expiry, reusable | Medium | R6 | 640 | `reset_request.php`, `reset.php` | unauth |
| 9 | Session fixation: session id not rotated after login | Medium | R7 | 384 | `session.php`, `login.php` | network |
| 10 | SQL injection on legacy report/search (string concat) | Medium | R8 | 89 | `search.php` / `report.php` | Reception+ |
| 11 | MinIO attachments on public bucket, guessable object keys | Medium | R3 | 552 | MinIO policy + `upload.php` | unauth |
| 12 | CSRF on state-changing actions (no token) | Medium | R4 | 352 | `admin_role.php`, `appointment.php` | cross-site |
| 13 | Weak password hashing (unsalted MD5 in `users`) | Medium | R6 | 916 | `auth.php` seed | post-dump |
| 14 | Password-reset email/host tampering -> takeover | Medium | R6 | 640 | `reset_request.php` | unauth |
| 15 | Username/account enumeration (login + reset distinct messages) | Low | R10 | 203 | `login.php`, `reset_request.php` | unauth |
| 16 | Information disclosure: `display_errors=On`, verbose errors, `phpinfo.php` | Low | R9 | 200 | `php.ini`, `phpinfo.php` | unauth |
| 17 | PHP version / server headers exposed (fingerprinting) | Info | R9 | 200 | Nginx/PHP config | unauth |
| 18 | Missing security headers (CSP, X-Frame-Options, HSTS, etc.) | Low | R9 | 693 | Nginx config | unauth |
| 19 | Redis session store with no AUTH (dump -> hijack) | Low | R7 | 306 | `docker-compose.yml`, redis conf | lab-net |
| 20 | Committed secrets / weak defaults in `.env` (DB, MinIO, Redis) | Low | R9 | 798 | `.env` | repo reader |

(Exact file split finalized in the plan; some endpoints share a file.)

### 10.1 Required bypass / difficulty (key web vulns)

Each headline web vuln ships behind a naive filter that must be deliberately bypassed.
No bare one-step payload works.

| # | Naive defense in place | Intended bypass (non-trivial) |
|---|------------------------|-------------------------------|
| 2 | Extension blacklist (`.php`) + `Content-Type` check | Upload `.phtml` / `.pht` **with** a `GIF89a` magic-byte prefix polyglot; Nginx passes `.phtml` to PHP-FPM. |
| 3 | IDs are not raw sequential ints — base64url-encoded object refs | Decode the ref, increment, re-encode; no ownership check behind it. |
| 4 | Profile form has no `role` field; server trusts any posted `role_id` | Add `role_id` param manually **and** find the numeric admin id (not string `"admin"`) by enumeration. |
| 7 | Single-pass blacklist strips the substring `script` and naive `on...=` handlers | Nested-tag defeat `<scr<script>ipt>` or an event on an allowed tag (`<svg><animate onbegin=...>`); output is stored and fires in another role's session. |
| 8 | Quotes escaped; obvious keyword filter on `UNION`/spaces | Numeric-context injection (no quotes) with `/**/` comment-based space bypass and `UNI/**/ON` to defeat the keyword filter; UNION column count to be discovered. |
| 10 | SQLi same endpoint as #8 | (see #8) |
| 8 | Reset token not purely random | Token = `md5(user_id . static_salt)` scheme — reconstructable once the pattern is inferred from a known account's Mailhog link. |

Principle: the obstacle is real but shallow — a competent tester gets through with
effort and iteration, not luck, and not a copy-pasted classic payload.

## 11. Repo layout (planned)

```
medislot-lab/
  docker-compose.yml
  .env                      # intentionally committed lab secrets
  README.md                 # how to run; NOT a vuln walkthrough
  docker/
    php/Dockerfile          # pins vulnerable PHP-FPM
    php/php.ini             # display_errors=On etc.
    nginx/default.conf
  app/                      # PHP source (served)
    includes/ (db.php, auth.php, session.php, config.php)
    *.php (login, dashboard, appointment, note, upload, download, admin_*, reset_*, search)
    assets/ (css, minimal js, logo)
    uploads/                # upload sink (web-reachable)
  db/
    init.sql                # schema
    seed.sql / seed.php     # synthetic data
  docs/ -> (keep stage-1 + stage-2 here or reference)
```

(Existing `docs/stage-1` + this file travel with the repo.)

## 12. UI direction

Clean clinic portal, lightly styled (calm blue/teal, sidebar nav, role dashboards,
tables, simple cards). Plain PHP templates + one hand-written CSS file, minimal JS.
Looks legitimate; no heavy framework. "Nice but not boring."

## 13. Milestones (checkpoint after each)

0. **This design doc** — approve.
1. **Repo scaffold + Compose up**: create private repo, compose with all services,
   validate PHP2Shell version+PoC combo. Checkpoint: stack runs, PHP version confirmed.
2. **Core app + auth + DB/seed**: login/session, dashboards per role, schema + seed data.
3. **Business features**: appointments, notes, attachments (MinIO), password reset (Mailhog).
4. **Plant vulnerabilities** (batched by severity) per the matrix.
5. **Scanner-surfaced findings + README + final sweep**: Trivy/Lynis Low/Info, docs.

## 14. Left to the builder

- Final file/endpoint split where the matrix groups several.
- Exact CSS/visual polish within the stated direction.
- Precise seed volume (count of patients/appointments/notes).
- Precise PHP tag after PoC validation in Milestone 1.
