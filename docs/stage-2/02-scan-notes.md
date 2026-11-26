# Build notes: PHP runtime pin (PHP2Shell)

- Pinned image: `php:8.3.35-fpm` (`docker/php/Dockerfile`). Tag exists on Docker Hub; running version confirmed `PHP/8.3.35`.
- Fix for the 8.3 branch is 8.3.36, so 8.3.35 is one patch below the fix.
- CVE-2026-109618 / CVE-2026-109619 (PHP2Shell) therefore apply to this runtime by version.
- Version is exposed via `X-Powered-By` (`expose_php=On`) and `/phpinfo.php`.
- Status: applicability by version only. No exploit code was fetched or run during the build; live validation is deferred to the Stage II testing phase.

# Build notes: MinIO image

`minio/minio` (Docker Hub) and `quay.io/minio/minio` are no longer pullable (denied / 401). The stack uses `chainguard/minio:latest`, a drop-in `minio server` binary.

# Scanner findings (Task 6, 2026-11-26)

These are genuine Low/Info findings surfaced by the chosen base images. They are
recorded for our own records, not as a hint-sheet for the testers.

## Trivy — `medislot-php:latest` (image scan)

Command (run from the repo root, Docker socket mounted):

    docker run --rm -v //var/run/docker.sock:/var/run/docker.sock \
      aquasec/trivy:latest image --scanners vuln medislot-php:latest

- Target: `medislot-php:latest` (Debian 13.7 base, `php:8.3.35-fpm`).
- Totals: **2588 OS-package vulnerabilities** — LOW 1013, MEDIUM 1326, HIGH 245, CRITICAL 4.
- Representative CRITICAL CVEs: `CVE-2026-111497`, `CVE-2026-6653`, `CVE-2026-99083`
  (affected packages include `libxml2`, `libbrotli1`, and the Debian kernel headers).
- These are inherited from the base image, independent of the deliberately pinned
  PHP runtime (PHP2Shell, documented above). They are the realistic "unpatched base
  image" Low/Info surface Trivy is expected to produce.

## Lynis — in-container audit (disposable container from the image)

Command:

    docker run --rm medislot-php:latest sh -c \
      'apt-get update -qq && apt-get install -y -qq lynis && lynis audit system --quick'

- **Hardening index: 57 / 100** (234 tests performed).
- Warnings (2): `PKGS-7392` (one or more vulnerable packages present),
  `LOGG-2138` (klogd not running).
- Suggestions (39), e.g.: install `libpam-tmpdir` (`DEB-0280`), `fail2ban` (`DEB-0880`),
  `needrestart` (`DEB-0831`); configure password-hashing rounds in `/etc/login.defs`
  (`AUTH-9230`); disable core dumps in `/etc/security/limits.conf` (`KRNL-5820`).
- Expected for a minimal, unhardened FPM container — no host hardening was applied,
  consistent with the lab's intent (design non-goals §3).
