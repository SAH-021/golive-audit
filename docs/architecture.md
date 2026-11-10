# Architecture

```
Check classes  ->  AuditRunner  ->  Report  ->  WP-CLI | REST API | Exporter
      ^                  ^
 SiteContext (port)   AuditService (applies waivers)
      ^
 WpSiteContext (WordPress)   ArrayContext (tests)
```

## Layers

| Layer | Namespace | Knows about WordPress? |
| --- | --- | --- |
| Engine | `Audit\` (`CheckInterface`, `Result`, `CheckRegistry`, `AuditRunner`, `Report`, `AuditService`) | No |
| Checks | `Checks\` | No: they read the site only through `SiteContext` |
| Storage | `Storage\`, `Checklist\` | No: they use the `OptionStore` port |
| Export | `Export\ReportExporter` | Only `esc_html()` and `__()` |
| WordPress adapters | `Support\WpSiteContext`, `Storage\WpOptionStore`, `Rest\`, `Admin\`, `Cli\`, `Plugin` | Yes |

Because checks never call WordPress functions, the whole engine runs under plain PHPUnit.

## Decisions worth knowing

- **Status vs severity.** Status is `pass | fail | error | skipped`; severity is `critical | warning | low`. A "warning" in the UI is a `fail` with warning severity.
- **`error` is not `fail`.** A check that throws becomes an `error` result with only the exception's short class name (messages can contain server paths). An audit with errors is never "ready".
- **Environment awareness.** Each check declares the environments it applies to; the runner skips the rest. `EnvironmentOverride` pins the environment for `--env` and the dashboard selector without checks knowing.
- **Waivers are data, not logic.** `AuditService` applies stored waivers to a finished `Report`. Waived failures count as earned in the score but stay visible.
- **Score vs verdict.** The score is severity-weighted (critical 10, warning 3, low 1). The verdict is separate because one critical failure must block launch regardless of the score.
- **Reports are re-run on export.** The export endpoint runs a fresh audit, so a downloaded report always matches the current site.
- **No history in 1.0.** Nothing about past runs is stored; the three stored options are waivers, checklist progress and settings.

## Extending

Register checks on `golive_audit_register_checks`; see `docs/adding-a-check.md`.
