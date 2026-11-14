# Testing

## PHP unit tests (no WordPress needed)

```
composer install
composer test        # or: phpunit
```

## JavaScript

```
npm install
npm run lint:js
npm run test:unit    # Vitest: helpers, plus the dashboard rendered in jsdom
npm run build
```

The dashboard tests (`assets/src/App.test.jsx`) mock `api.js` with responses captured from a real site (`assets/src/test/*.fixture.json`). To refresh the fixtures, run on a test site:

```
wp eval 'wp_set_current_user(1); echo wp_json_encode(rest_do_request(new WP_REST_Request("GET","/golive-audit/v1/state"))->get_data(), JSON_PRETTY_PRINT);'
wp eval 'wp_set_current_user(1); echo wp_json_encode(rest_do_request(new WP_REST_Request("POST","/golive-audit/v1/audit"))->get_data(), JSON_PRETTY_PRINT);'
```

## End-to-end (real WordPress)

`tests/e2e/run.py` drives a real site over HTTP: login, REST nonce, permissions for anonymous and subscriber users, every REST route, waivers, checklist, settings, all three exports, the admin screen and the 429 throttle.

Setup used for 1.0.0: WordPress installed with WP-CLI on MariaDB, served with `php -S 127.0.0.1:8080 -t <wordpress>`, the plugin copied into `wp-content/plugins/golive-audit`, an `admin` / `pass123` administrator and a `bob` / `bobpass` subscriber, `WP_DEBUG` and `WP_DEBUG_LOG` on. Run `python3 tests/e2e/run.py`, then check `wp-content/debug.log` is empty.


## 1.1.0 additions

New PHP tests: `NewChecksTest`, `HistoryStoreTest`, `AlertMessageTest`, `JalaliTest` (known Jalali dates and language mapping) and additions to `StoresTest` and `ReportExporterTest`. New JS tests in `utils.test.js` cover search, run deltas, trend coordinates and Persian digits and dates.

Honest status: these were written without a PHP interpreter, so run `composer test` yourself. What was executed: the Jalali algorithm against ICU's Persian calendar for 1901-2099, the JS tests, and a browser smoke test of the full dashboard with stubbed WordPress packages (tabs, history, search, settings payload, Persian digits, RTL chart).

New tests in `tests/e2e/run.py` are still to be added for `GET|DELETE /history`, the new settings fields and `format=md`.
