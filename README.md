# Golive Audit

Launch-readiness and client-handoff audit for WordPress. It answers one question before you launch or hand a site to a client: **is this site ready?**

- 25 read-only checks that core Site Health does not cover, environment-aware (production vs staging)
- Readiness score and a verdict: *Ready*, *Needs attention* or *Launch blocked*
- Accepted risks: waive a failed check with a written reason that appears in the report
- Manual handoff checklist (business / blog / shop presets) for what no plugin can verify
- Reports: printable page (save as PDF), Markdown, CSV and JSON, free of secrets
- Run history with a score trend chart and a "what changed since the last audit" view
- Scheduled audits (daily or weekly, WP-Cron) with an email alert when new problems appear
- Persian (فارسی) translation, right-to-left layout and Jalali dates, selectable independently of the site language
- React dashboard under **Tools > Golive Audit** (with search), REST API, and WP-CLI for CI
- Extensible: register your own checks on `golive_audit_register_checks`

Requires WordPress 6.2+ and PHP 7.4+. Version 1.1.0. Working name: replace the three tokens `golive-audit`, `GoliveAudit`, `golive_audit_`/`GOLIVE_AUDIT_` to rename (check the name against WordPress.org first).

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
| Content | `content.placeholder_text`: no "lorem ipsum" (Latin or Persian) in published posts and pages | warning | production, staging |
| Environment | `env.coming_soon`: no coming-soon / maintenance plugin or WooCommerce "coming soon" mode left on | warning | production |
| Security | `security.sensitive_files`: no wp-config backups, SQL dumps, `.env` or `phpinfo.php` in the site root | critical | production, staging |
| Security | `security.leftover_files` (`readme.html`, `license.txt`, `error_log`...), `security.xmlrpc`, `security.db_prefix` | low | production, staging |

Not repeated because core Site Health covers them: PHP/WordPress versions, HTTPS, updates, caching, debug mode.

## Usage

Dashboard: **Tools > Golive Audit**.

```
wp golive-audit list-checks
wp golive-audit run                                    # fails (exit 1) on an unwaived critical failure
wp golive-audit run --env=production --fail-on=warning --format=json
wp golive-audit run --record                           # also add the run to the dashboard history
wp golive-audit history --format=table
```

REST API (`golive-audit/v1`, administrators only): `GET /state`, `POST /audit`, `GET|DELETE /history`, `PUT|DELETE /waivers/<check-id>`, `PUT /checklist/<item>`, `POST /settings`. Exports are served by `admin-post.php?action=golive_audit_export&format=html|md|csv|json`.

## Languages

Persian (`fa_IR`) ships with the plugin: dashboard, reports, check texts and alert emails. It is used automatically on a Persian site, or you can force it (or English) under **Settings > Language**, which also switches the layout direction and shows reports with Jalali dates. To add another language: `wp i18n make-pot . languages/golive-audit.pot`, translate to `languages/golive-audit-<locale>.po`, then `wp i18n make-mo languages` and `wp i18n make-json languages --no-purge`.

## Development

```
composer install && composer test      # 145 PHP unit tests, no WordPress needed
npm install
npm run lint:js && npm run test:unit   # 17 Vitest tests (helpers + the dashboard rendered in jsdom)
npm run build                          # compiles assets/src to assets/build (the build committed in 1.1.0 was produced with esbuild; run this once to regenerate it with wp-scripts)
bin/build-zip.sh                       # installable zip in dist/
```

See `docs/architecture.md`, `docs/adding-a-check.md`, `docs/security.md` and `docs/testing.md`.

## What was verified for 1.1.0 (see also docs/testing.md)

The 1.0.0 test results (145 PHP tests, 17 JS tests, WPCS, ESLint, end-to-end on WordPress 7.1.4, Plugin Check) do **not** automatically apply to 1.1.0: the 1.1.0 changes were written in an environment without PHP or network access. What was actually run for 1.1.0: a Jalali conversion check against ICU for every day from 1901 to 2099, the dashboard helper tests, a browser smoke test of the whole dashboard with stubbed WordPress packages, structural checks of every PHP file, and a translation round-trip. Run `composer test`, `composer lint`, `npm run lint:js && npm run test:unit` and `python3 tests/e2e/run.py` before releasing.

## Known limitations

- Sample content and the default tagline are detected by their English text only.
- `robots.txt` is read from the WordPress root; WordPress installed in a subdirectory with `robots.txt` in the parent folder is not seen.
- The mail transport check detects that something customizes mail; it cannot prove delivery.
- History keeps the last 30 snapshots (score, verdict, counts and failed check IDs, never texts). Scheduled audits rely on WP-Cron, which needs site traffic or a real cron job.
- Forcing a language different from the WordPress admin language keeps the admin chrome in the admin language; only the plugin screen, reports and emails change.
