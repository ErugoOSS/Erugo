# PHP container smoke tests

The runtime images use Alpine 3.24 and its bundled HTTPS repositories. Both builds
reject PHP older than 8.3.36 or outside the 8.3 branch. PHP 8.3.36 fixes
[CVE-2026-109618 and CVE-2026-109619](https://www.php.net/archive/2026.php).

Both INI files also disable `session.upload_progress.enabled`. This follows PHP's
October 23 guidance for the separate, conditional session-handler issue tracked
as CVE-2026-115560. It is defense in depth, not a claim that Erugo uses the affected
handler combination. Erugo's upload progress comes from tusd. The existing PHP
upload, POST and memory limits remain `4G`.

[PR #294](https://github.com/ErugoOSS/Erugo/pull/294) already proposes the Alpine
3.24 bump. This patch adds the version guard, upload-progress mitigation and
repeatable checks. See [Alpine's support policy](https://alpinelinux.org/releases/)
for the shorter support window of the community repository, which supplies PHP.

## Run

Requirements are Python 3 and Docker or Podman. Run from the repository root.
The production build must use this checkout, not an existing deployment image.

```sh
python3 tests/container/test_php_config.py
docker build --build-arg TARGETARCH=amd64 -t erugo-php-smoke -f docker/alpine/Dockerfile .
python3 tests/container/smoke.py erugo-php-smoke

docker build --build-arg TARGETARCH=amd64 -t erugo-php-dev-smoke -f docker/dev/Dockerfile docker/dev
python3 tests/container/smoke.py --runtime-only erugo-php-dev-smoke
```

Use `TARGETARCH=arm64` when building natively on ARM64. Pass `--engine podman`,
or an executable wrapper, to select another engine. For an isolated rootless
Podman store, the wrapper must pass explicit `--root` and `--runroot` paths to
every invocation. Podman builds support `--cpu-period=100000 --cpu-quota=400000
--memory=4g` for bounded build resources.

The smoke test creates its own container and storage volume, publishes only a
random loopback port, and limits the container to two CPUs and 2 GiB of RAM.
It creates a disposable account with a generated password kept in memory.
It never takes deployment credentials or an existing container as input.
The temporary PHP probe, account, files and volume disappear on cleanup.
Only selected runtime values and check results go to stdout. Do not run Python
with `-O`, which disables assertions. The 2 GiB container limit is a test resource
limit, not a change to PHP's `4G` settings or a large-upload capacity test.

## Recorded results

Tested on Linux amd64 with rootless Podman 5.8.7 on 2026-10-26 UTC, from main
`922c00c2beafa5b6136ff551333d52387270be49` plus this patch. Both Dockerfiles built
successfully from source. The runtime was Alpine 3.24.3 with PHP 8.3.36.

- Source checks fail against the unmodified main Dockerfiles and INI files and
  pass with this patch.
- Both images pass CLI and real HTTP-to-FPM checks for PHP 8.3.36, disabled
  session upload progress, and unchanged `4G` limits. `php-fpm83 -t` passes.
- The exact Dockerfile guard rejects a simulated 8.3.35 version ID, accepts
  8.3.36, and rejects 8.4.0.
- Production passes login-page HTML and built-JavaScript fetches, password login,
  an authenticated two-file TUS upload with HEAD-based resume offsets, queue ZIP
  creation, and anonymous file and ZIP byte comparisons.
- After a container restart, password login and the same downloads still pass.
  The test confirms its container, volume and loopback listener are gone.

The dev check starts Caddy and FPM directly. It does not test the full development
entrypoint, mounted source, npm install or Vite hot reload. ARM64, browser rendering,
external databases, mail, LDAP, SSO and production-data upgrades were not tested.
This is a synthetic smoke test, not an exploit or comprehensive security audit.
The existing npm lockfile produced an audit warning during the build, with
1 moderate and 10 high vulnerabilities. Dependency changes are outside this patch.
