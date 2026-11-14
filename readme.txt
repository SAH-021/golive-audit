=== Golive Audit ===
Contributors: sah021
Tags: launch checklist, site audit, pre-launch, handoff, wp-cli
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Launch-readiness audit for WordPress: environment-aware checks, a handoff checklist, accepted risks and client-ready reports.

== Description ==

Golive Audit answers one question before you launch or hand a site to a client: **is this site ready?**

It runs 25 read-only checks that WordPress's built-in Site Health does not cover, understands whether it is looking at a production or a staging site, and produces a report you can hand to the client.

**What it checks**

* Search engine visibility that matches the environment (indexable in production, hidden on staging)
* Site URLs: no localhost, IP-address or staging host left behind; WordPress Address and Site Address agree
* robots.txt that blocks the whole site, pretty permalinks, XML sitemap, site title and tagline, site icon, front page
* Leftover sample content, comment and pingback defaults, privacy policy page
* Unique salts and keys, the "admin" user and too many administrators, open registration that grants a privileged role, the file editor, a leftover debug.log
* A real administration email address, a configured mail transport, the timezone

Core Site Health already covers PHP and WordPress versions, HTTPS, updates, caching and debug mode, so those are deliberately not repeated.

**Built for handoffs**

* **Environment profiles**: audit as production or staging; the detected `WP_ENVIRONMENT_TYPE` is the default.
* **Readiness score and verdict**: "Ready", "Needs attention" or "Launch blocked" (an unaccepted critical failure blocks launch).
* **Accepted risks**: waive a failed check with a written reason; the reason appears in the report.
* **Manual handoff checklist**: backups restored, forms tested, analytics, cookie consent and more, with presets for business sites, blogs and shops.
* **Reports**: a printable page (save it as PDF), CSV and JSON. Reports never contain secrets, key values, the admin email address or server paths.
* **WP-CLI** for CI: `wp golive-audit run --env=production --fail-on=warning` exits non-zero when a check at that severity fails.
* **Extensible**: add your own checks with the `golive_audit_register_checks` action.

Everything is read-only. The plugin never changes your site; each problem links to the screen where you can fix it.

= Privacy =

Golive Audit makes no external requests and collects no data. It stores three options in your database: accepted risks, checklist progress and report settings. They are removed when you delete the plugin.

= Source code =

The dashboard in `assets/build` is compiled from the readable source in `assets/src` with `@wordpress/scripts` (`npm install && npm run build`). The full repository, including unit tests, is at the project's source repository.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it.
3. Open **Tools > Golive Audit**.

== Frequently Asked Questions ==

= Does it change my site? =

No. It only reads settings and files. It stores accepted risks, checklist progress and report settings.

= Why does it say my production site is blocked on staging settings? =

The audit uses the environment WordPress reports (`WP_ENVIRONMENT_TYPE`, which defaults to production). Pick another environment in the toolbar, or set the constant in wp-config.php.

= Can it prove my site is secure? =

No. It checks common launch mistakes and hygiene. It is not a vulnerability scanner or malware scanner.

= How do I add my own check? =

Implement `GoliveAudit\Audit\CheckInterface` and register it on the `golive_audit_register_checks` action.

== Changelog ==

= 1.1.0 =
* New: run history with a score trend chart and a "since the previous audit" view.
* New: scheduled audits (daily or weekly) with an email alert when new problems appear.
* New: Persian (فارسی) translation, right-to-left layout, Jalali dates, and a language setting (site default, English or Persian).
* New: Markdown report, check search, score change indicator, a "Recent audits" table in the printable report.
* New checks: lorem ipsum text, coming-soon/maintenance mode, backups and SQL dumps in the site root, install leftovers, XML-RPC, default table prefix.
* New: 10 more handoff checklist items and `wp golive-audit history`, `wp golive-audit run --record`.

= 1.0.0 =
* First release: 19 checks, environment profiles, readiness score, accepted risks, manual handoff checklist, printable/CSV/JSON reports, REST API and WP-CLI.

== Upgrade Notice ==

= 1.1.0 =
Adds run history, scheduled audits, Persian support and six new checks. Run history is empty until the first audit after updating.

= 1.0.0 =
First release.
