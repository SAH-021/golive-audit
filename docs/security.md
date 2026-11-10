# Security notes

- **Access control.** Every REST route and the export endpoint require `manage_options`. The REST layer also needs a valid cookie nonce, but a nonce never replaces the capability check.
- **Input validation.** Route arguments declare types, enums and length limits with an explicit `validate_callback` (core skips schema validation when only a `sanitize_callback` is given), and the stores re-validate.
- **Output.** The dashboard is React (escaped by default). The printable report escapes every dynamic value, is served with a restrictive Content-Security-Policy and a per-request script nonce, and the CSV export neutralizes cells that start with `=`, `+`, `-`, `@` so spreadsheets do not run them as formulas.
- **No secrets in reports.** Salt/key checks report constant names only; the admin email check reports the domain only; error results keep only an exception class name.
- **Read-only.** The audit reads options, a few constants, `robots.txt` in the WordPress root and the existence of `debug.log`. File names are validated as plain names (no directories, no `..`).
- **No network.** The plugin makes no outbound requests.
- **Throttling.** Concurrent audits are rejected with HTTP 429 while one is running (30 second lock).
- **Links.** The dashboard only renders http(s) `fix_url` values, so a third-party check cannot inject a `javascript:` link.
