# Golive Audit

Launch-readiness and client-handoff audit for WordPress. It answers one question before you launch or hand a site to a client: **is this site ready?**

- 19 read-only checks that core Site Health does not cover, environment-aware (production vs staging)
- Readiness score and a verdict: *Ready*, *Needs attention* or *Launch blocked*
- Accepted risks: waive a failed check with a written reason that appears in the report
- Manual handoff checklist (business / blog / shop presets) for what no plugin can verify
- Reports: printable page (save as PDF), CSV and JSON, free of secrets
- React dashboard under **Tools > Golive Audit**, REST API, and WP-CLI for CI
- Extensible: register your own checks on `golive_audit_register_checks`

Requires WordPress 6.2+ and PHP 7.4+. Version 1.0.0. Working name: replace the three tokens `golive-audit`, `GoliveAudit`, `golive_audit_`/`GOLIVE_AUDIT_` to rename (check the name against WordPress.org first).

## Checks

| Area | Check | Severity | Runs in |
| --- | --- | --- | --- |
| Environment | `env.url_consistency`: no localhost/IP/staging host, URLs agree | critical | production |
| SEO | `seo.search_visibility`: indexable in production, hidden elsewhere | critical | production, staging, development |
| SEO | `seo.robots_txt`: robots.txt does not block the whole site | critical | production |
| SEO | `seo.permalinks`, `seo.sitemap`, `seo.site_identity`, `seo.front_page` | warning | see code |
| SEO | `seo.site_icon` | low | production, staging |
| Content | `content.default_content`: sample post/page removed | warning | production, staging |
| Content | `content.comment_defaults` | low | production, staging |
| Legal | `legal.privacy_policy`: page assigned and published | warning | production |
| Security | `security.salts`, `security.open_registration` | critical | production, staging |
| Security | `security.admin_users`, `security.debug_log` | warning | production, staging |
| Security | `security.file_editor` | low | production, staging |
| Email | `email.admin_address`, `email.transport` | warning | production |
| Language | `locale.timezone` | low | production, staging |

Not repeated because core Site Health covers them: PHP/WordPress versions, HTTPS, updates, caching, debug mode.

## Usage

Dashboard: **Tools > Golive Audit**.

```
wp golive-audit list-checks
wp golive-audit run                                    # fails (exit 1) on an unwaived critical failure
wp golive-audit run --env=production --fail-on=warning --format=json
```

REST API (`golive-audit/v1`, administrators only): `GET /state`, `POST /audit`, `PUT|DELETE /waivers/<check-id>`, `PUT /checklist/<item>`, `POST /settings`. Exports are served by `admin-post.php?action=golive_audit_export&format=html|csv|json`.

## Development

```
composer install && composer test      # 145 PHP unit tests, no WordPress needed
npm install
npm run lint:js && npm run test:unit   # 17 Vitest tests (helpers + the dashboard rendered in jsdom)
npm run build                          # compiles assets/src to assets/build
bin/build-zip.sh                       # installable zip in dist/
```

See `docs/architecture.md`, `docs/adding-a-check.md`, `docs/security.md` and `docs/testing.md`.

## What was verified for 1.0.0

- 145 PHP unit tests and 17 JS tests (including the dashboard rendered in jsdom against real API responses) pass; WordPress Coding Standards (WPCS) report no violations; ESLint (`@wordpress/scripts`) is clean.
- End-to-end on a real WordPress 7.1.4 site (`tests/e2e/run.py`, 54 checks): login, permissions for anonymous/subscriber users, every REST route, waivers, checklist, settings, all exports, the admin screen and the concurrency lock. `debug.log` stayed empty.
- WP-CLI commands and exit codes, and uninstall cleanup, tested on that site.
- The official WordPress.org Plugin Check reports no errors.

## Known limitations

- Sample content and the default tagline are detected by their English text only.
- `robots.txt` is read from the WordPress root; WordPress installed in a subdirectory with `robots.txt` in the parent folder is not seen.
- The mail transport check detects that something customizes mail; it cannot prove delivery.
- The dashboard has been built and linted but not clicked through in a browser; its logic helpers are unit-tested and its API was exercised end to end.
- No run history yet (planned for 1.1).
