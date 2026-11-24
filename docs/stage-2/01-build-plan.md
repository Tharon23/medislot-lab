# MediSlot Lab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the deliberately vulnerable MediSlot clinic portal as a one-command Docker Compose lab, pushed to a private GitHub repo, for the Stage II pentest.

**Architecture:** Classic multi-file plain PHP behind Nginx -> PHP-FPM (pinned vulnerable version), with MySQL, Redis (sessions), MinIO (attachments), Mailhog (reset mail). Each endpoint is its own `.php`; shared logic in `includes/`. 20 planted vulnerabilities, each behind a real but shallow obstacle.

**Tech Stack:** PHP-FPM (pinned ~8.3.35), Nginx, MySQL 8, Redis 7, MinIO, Mailhog, Docker Compose.

**Spec:** `docs/stage-2/00-build-design.md`

## Global Constraints

- Plain PHP, no framework. Docker Compose, localhost only. Synthetic data only.
- Ports: Nginx `8080`, Mailhog UI `8025`, MinIO API/console `9000`/`9001`. MySQL/Redis NOT published to host.
- Primary RCE = PHP2Shell (CVE-2026-109618/619) via pinned PHP-FPM below fix (8.2.35/8.3.36/8.4.27/8.5.12).
- Project + repo in English. Repo private under `Tharon23`, name `medislot-lab`, no collaborators added by builder.
- Headline web vulns (#2,#3,#4,#7,#8,#10) must require the non-trivial bypass in design §10.1 — no bare one-step payload works.
- `.env` with lab secrets IS committed (finding #20); `phpinfo.php` IS exposed (#16). Intentional.

## Review Focus

- **Vuln actually exploitable end-to-end** — each planted vuln must be demonstrably triggerable by the intended bypass, not just "looks wrong." Verified per vuln in Tasks 5a–5c.
- **Non-trivial bypass really required** — confirm the naive filter blocks the obvious payload AND the intended bypass passes. Both checked in Task 5.
- **Happy path still works for all 4 roles** — a tester must be able to log in and use each role; broken app = unusable lab. Smoke-checked in Task 2 and re-checked in Task 6.
- **PHP2Shell combo reproducible** — pinned tag + PoC must fire in our image/OS/server combo, else fall back (design §6). Validated in Task 1.
- **No host exposure of DB/Redis** — compose must not publish 3306/6379. Checked in Task 1.

---

### Task 1: Repo scaffold + Compose stack up + PHP2Shell validation

**Files:**
- Create: `docker-compose.yml`, `.env`, `.gitignore`, `README.md`
- Create: `docker/php/Dockerfile`, `docker/php/php.ini`, `docker/nginx/default.conf`
- Create: `app/index.php` (placeholder), `app/phpinfo.php`

**Deliverable:** `docker compose up` brings all 6 services healthy; PHP version confirmed vulnerable; repo pushed.

- [ ] **Step 1:** Create repo: `gh repo create Tharon23/medislot-lab --private --source . --remote origin` (after `git init`). Do NOT add collaborators.
- [ ] **Step 2:** Write `docker-compose.yml` with services `nginx, php, mysql, redis, minio, mailhog`. Publish only `8080, 8025, 9000, 9001`. Named volumes for mysql + minio. All on one bridge network.
- [ ] **Step 3:** Write `docker/php/Dockerfile` pinning the candidate tag `php:8.3.35-fpm` + install `pdo_mysql`, redis ext, minio/S3 via raw HTTP or `aws` sdk-free curl. `php.ini`: `display_errors=On`, `expose_php=On`.
- [ ] **Step 4:** Validate PHP2Shell combo. Run: `docker compose up -d && curl -sI localhost:8080 | grep -i x-powered-by` → expect pinned version. Then attempt a known public PoC against the combo.
  Expected: version < fix shown; PoC path confirmed OR documented fallback chosen (design §6).
- [ ] **Step 5:** Verify no host DB/Redis: `docker compose ps` shows no `0.0.0.0:3306`/`6379`. Mailhog `localhost:8025`, MinIO console `localhost:9001` reachable.
- [ ] **Step 6:** Commit + push. `README.md` = how to run only (not a vuln walkthrough).

**CHECKPOINT:** report PHP version + PoC result + running services before Task 2.

---

### Task 2: Core app — DB schema, seed, auth, sessions, role dashboards

**Files:**
- Create: `db/init.sql`, `db/seed.php`
- Create: `app/includes/config.php`, `db.php`, `session.php`, `auth.php`, `layout.php`
- Create: `app/login.php`, `app/logout.php`, `app/dashboard.php`
- Create: `app/assets/style.css`

**Interfaces (Produces):**
- `db(): PDO` in `db.php`
- `current_user(): ?array`, `require_login()`, `require_role(string $r)` in `auth.php` (require_role deliberately NOT called on some admin endpoints later)
- `render(string $title, string $bodyHtml)` in `layout.php`

- [ ] **Step 1:** `db/init.sql` — tables per design §8 (`users, patients, doctors, assignments, appointments, notes, attachments, password_resets`).
- [ ] **Step 2:** `db/seed.php` — 4 role accounts (design §9) + ~8 synthetic patients, appointments, notes, 2 attachments. Passwords stored **unsalted MD5** (finding #13).
- [ ] **Step 3:** Sessions via Redis (`session.save_handler=redis`), Redis **no AUTH** (#19). Session id **not** rotated on login (#9, fixation). Cookie without `HttpOnly`/`Secure`.
- [ ] **Step 4:** `login.php` — distinct messages for unknown user vs bad password (#15 enumeration). Clean clinic UI via `style.css` (calm blue/teal, sidebar, cards).
- [ ] **Step 5:** `dashboard.php` — per-role view (Patient/Doctor/Reception/Admin).
- [ ] **Step 6:** Verify: log in as each of the 4 roles, land on correct dashboard. `docker compose exec` seed runs clean.
- [ ] **Step 7:** Commit.

**CHECKPOINT:** 4 roles log in, dashboards render.

---

### Task 3: Business features — appointments, notes, attachments, reset

**Files:**
- Create: `app/appointment.php`, `app/appointment_list.php`
- Create: `app/note.php`, `app/note_view.php`
- Create: `app/upload.php`, `app/download.php`, `app/uploads/` (web-reachable sink)
- Create: `app/reset_request.php`, `app/reset.php`
- Create: `app/includes/minio.php` (S3 PUT/GET), `mailer.php` (SMTP -> Mailhog)

**Interfaces (Consumes):** `db()`, `current_user()`, `render()` from Task 2.

- [ ] **Step 1:** Appointments CRUD (book/list/cancel). IDs exposed as base64url object refs (sets up #3).
- [ ] **Step 2:** Notes: doctor writes `body_html`, stored raw; `note_view.php` renders it (sets up #7 stored XSS).
- [ ] **Step 3:** Upload to MinIO via `minio.php`; attachments meta in DB; bucket policy **public**, keys guessable (#11). `download.php` streams by `?file=` param (sets up #6 traversal).
- [ ] **Step 4:** Password reset -> email via Mailhog; token = `md5(user_id . static_salt)`, no expiry, reusable (#8).
- [ ] **Step 5:** Verify happy path: book appointment, write+view note, upload+download attachment, request reset and read link in Mailhog UI.
- [ ] **Step 6:** Commit.

**CHECKPOINT:** full happy path works for relevant roles.

---

### Task 4: Plant Critical/High vulns (#2,#3,#4,#5,#6) + confirm bypass

**Files (Modify):** `upload.php`, `download.php`, `note.php`/`appointment.php`, `profile_update.php` (Create), `admin_users.php`/`admin_role.php` (Create).

- [ ] **Step 1 (#2 upload RCE):** extension blacklist (`.php`) + Content-Type check only. Accept `.phtml`/`.pht`; Nginx routes `.phtml` to PHP-FPM. Verify: bare `shell.php` rejected; `GIF89a`-prefixed `shell.phtml` uploads AND executes.
- [ ] **Step 2 (#3 IDOR):** no ownership/assignment check behind the base64url ref. Verify: patient A decodes/increments ref, reads patient B's note/appointment/attachment.
- [ ] **Step 3 (#4 priv-esc):** `profile_update.php` trusts posted `role_id` (mass-assignment); form has no role field. Verify: posting `role_id=<admin id>` escalates; string `"admin"` does NOT (numeric id required).
- [ ] **Step 4 (#5 BAC):** `admin_users.php`/`admin_role.php` omit `require_role('admin')`. Verify: patient session hits them directly.
- [ ] **Step 5 (#6 traversal):** `download.php?file=../../` escapes uploads dir. Verify: read a file outside `uploads/`.
- [ ] **Step 6:** Commit.

**CHECKPOINT:** each exploit demonstrated; obvious payloads confirmed blocked.

---

### Task 5: Plant Medium vulns (#7,#8,#10,#11,#12,#14) + confirm bypass

**Files (Modify):** `note_view.php`, `reset_request.php`/`reset.php`, `search.php` (Create), `admin_role.php`, `appointment.php`, MinIO policy.

- [ ] **Step 1 (#7 XSS):** single-pass blacklist strips substring `script` + naive `on\w+=`. Verify: `<script>alert(1)</script>` neutralized; `<scr<script>ipt>alert(1)</scr</script>ipt>` OR `<svg><animate onbegin=alert(1)>` fires, stored, in another role's session.
- [ ] **Step 2 (#8/#10 SQLi + reset):** `search.php` numeric param concatenated; quotes escaped, `UNION`/space naive filter. Verify: `' OR 1=1` fails; `/**/`+`UNI/**/ON` numeric injection dumps a column. Reset token scheme reconstructable (#8).
- [ ] **Step 3 (#11 MinIO):** confirm public bucket + guessable key lists/fetches an object unauthenticated.
- [ ] **Step 4 (#12 CSRF):** no token on `admin_role.php`/`appointment.php`. Verify: cross-origin form changes state.
- [ ] **Step 5 (#14 reset email/host tamper):** reset link host from `Host` header. Verify tamper.
- [ ] **Step 6:** Commit.

**CHECKPOINT:** each Medium demonstrated; obvious payloads blocked.

---

### Task 6: Low/Info (#16–#20), scanners, README, final sweep

**Files (Modify):** `php.ini`, `docker/nginx/default.conf`, `.env`, `README.md`; Create `docs/stage-2/02-scan-notes.md`.

- [ ] **Step 1 (#16,#17,#18):** confirm `phpinfo.php` reachable, verbose errors on, version headers exposed, security headers absent.
- [ ] **Step 2 (#19,#20):** confirm Redis no-AUTH session dump path; `.env` secrets committed.
- [ ] **Step 3:** Run Trivy on the PHP image + Lynis in-container; capture genuine Low/Info into `02-scan-notes.md` (for our records, not a tester hint-sheet).
- [ ] **Step 4:** Re-run full 4-role happy path (Review Focus). Fix any break that is NOT an intended vuln.
- [ ] **Step 5:** Finalize `README.md` (run instructions, port table, lab-only warning). Commit + push.

**CHECKPOINT:** repo clones fresh, `docker compose up` yields a working, fully-planted lab.
