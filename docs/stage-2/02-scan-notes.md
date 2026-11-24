# Build notes: PHP runtime pin (PHP2Shell)

- Pinned image: `php:8.3.35-fpm` (`docker/php/Dockerfile`). Tag exists on Docker Hub; running version confirmed `PHP/8.3.35`.
- Fix for the 8.3 branch is 8.3.36, so 8.3.35 is one patch below the fix.
- CVE-2026-109618 / CVE-2026-109619 (PHP2Shell) therefore apply to this runtime by version.
- Version is exposed via `X-Powered-By` (`expose_php=On`) and `/phpinfo.php`.
- Status: applicability by version only. No exploit code was fetched or run during the build; live validation is deferred to the Stage II testing phase.

# Build notes: MinIO image

`minio/minio` (Docker Hub) and `quay.io/minio/minio` are no longer pullable (denied / 401). The stack uses `chainguard/minio:latest`, a drop-in `minio server` binary.
