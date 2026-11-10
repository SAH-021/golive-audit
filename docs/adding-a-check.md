# Adding a check

1. Create a class extending `GoliveAudit\Checks\AbstractCheck` (or implement `GoliveAudit\Audit\CheckInterface` directly).
2. Pick an ID such as `seo.my_check`: lowercase, dot-separated, unique. The category is the first segment and must exist in `Categories::labels()` for bundled checks.
3. Implement `title()`, `severity()` and `run( SiteContext $context ): Result`. Return `$this->pass()` or `$this->fail( $summary, $recommendation, $fix_url )`.
4. Override `environments()` if the check should not run in both production and staging.
5. Register it in `Plugin::default_checks()`, or from another plugin:

```php
add_action( 'golive_audit_register_checks', function ( \GoliveAudit\Audit\CheckRegistry $registry ) {
	$registry->register( new My_Plugin_Check() );
} );
```

Rules:

- Read the site only through `SiteContext`. If you need a new fact, add a method to the interface and implement it in `WpSiteContext`, `EnvironmentOverride` and the test `ArrayContext`.
- Never put secrets, key values, email addresses or file paths in a result.
- A missing or odd value is a `fail`, not an exception; exceptions are for bugs and become `error` results.
- Add `tests/Unit/...Test.php` using `ArrayContext`. `DefaultChecksTest` enforces the metadata conventions for every bundled check.
