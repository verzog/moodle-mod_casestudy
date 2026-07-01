# mod_casestudy — contributor notes

## Versioning: bump on every merge

Bump `$plugin->version` in `version.php` on **every** pull request — including
tooling-only and docs-only changes — so the version number always uniquely
identifies the deployed codebase. This lets a "site version matches the GitHub
version" check reliably confirm exactly what is deployed.

- Use Moodle's date-based `YYYYMMDDXX` format.
- The new value must be **strictly greater** than the one currently on the base
  branch (`main`).
- A docs/tooling-only bump is a harmless no-op upgrade for Moodle (no schema or
  behaviour change); that's expected and fine.

CI enforces this: `.github/workflows/version-bump.yml` fails any pull request
whose `$plugin->version` is not greater than the base branch's.
