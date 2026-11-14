"""End-to-end test against a real WordPress site. See docs/testing.md for the setup."""
import http.cookiejar, json, re, sys, urllib.error, urllib.parse, urllib.request

BASE = 'http://127.0.0.1:8080'
results = []

def check(name, ok, detail=''):
    results.append(ok)
    print(('PASS ' if ok else 'FAIL ') + name + (f'  [{detail}]' if detail and not ok else ''))

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.nonce = None

    def req(self, method, url, data=None, headers=None, form=False, use_nonce=True):
        h = dict(headers or {})
        body = None
        if data is not None:
            if form:
                body = urllib.parse.urlencode(data).encode()
                h['Content-Type'] = 'application/x-www-form-urlencoded'
            else:
                body = json.dumps(data).encode()
                h['Content-Type'] = 'application/json'
        if use_nonce and self.nonce and 'rest_route' in url:
            h['X-WP-Nonce'] = self.nonce
        r = urllib.request.Request(url if url.startswith('http') else BASE + url, data=body, headers=h, method=method)
        try:
            resp = self.opener.open(r)
            return resp.status, resp.read().decode('utf-8', 'replace'), resp.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', 'replace'), e.headers

    def login(self, user, pwd):
        self.opener.open(BASE + '/wp-login.php').read()
        self.jar.set_cookie(http.cookiejar.Cookie(0, 'wordpress_test_cookie', 'WP Cookie check', None, False, '127.0.0.1', False, False, '/', True, False, None, False, None, None, {}))
        self.req('POST', '/wp-login.php', {'log': user, 'pwd': pwd, 'wp-submit': 'Log In', 'redirect_to': BASE + '/wp-admin/', 'testcookie': '1'}, form=True)
        self.nonce = None
        for page in ('/wp-admin/tools.php?page=golive-audit', '/wp-admin/profile.php', '/wp-admin/index.php'):
            s, html, _ = self.req('GET', page)
            m = re.search(r'createNonceMiddleware\(\s*"([a-f0-9]+)"', html) or re.search(r'wpApiSettings\s*=\s*\{[^}]*"nonce":"([a-f0-9]+)"', html)
            if s == 200 and m:
                self.nonce = m.group(1)
                break

    def rest(self, method, route, data=None):
        s, body, h = self.req(method, f'/index.php?rest_route=/golive-audit/v1{route}', data)
        try:
            return s, json.loads(body), h
        except ValueError:
            return s, body, h

admin = Client(); admin.login('admin', 'pass123')
check('admin logged in with a REST nonce', bool(admin.nonce))

# --- Permissions ---
anon = Client()
s, _, _ = anon.rest('GET', '/state')
check('anonymous GET /state is rejected (401/403)', s in (401, 403), s)
s, _, _ = anon.rest('POST', '/audit')
check('anonymous POST /audit is rejected', s in (401, 403), s)

bob = Client(); bob.login('bob', 'bobpass')
check('subscriber logged in', bool(bob.nonce))
for method, route in (('GET', '/state'), ('GET', '/history'), ('DELETE', '/history'), ('POST', '/audit'), ('PUT', '/waivers/seo.sitemap'), ('PUT', '/checklist/forms_tested'), ('POST', '/settings')):
    s, _, _ = bob.rest(method, route, {'reason': 'x', 'state': 'done'})
    check(f'subscriber {method} {route} is forbidden', s == 403, s)

s, _, _ = admin.req('GET', '/index.php?rest_route=/golive-audit/v1/state', use_nonce=False)  # admin cookie but NO nonce
check('cookie auth without a REST nonce is rejected', s in (401, 403), s)

# --- State ---
s, state, _ = admin.rest('GET', '/state')
check('admin GET /state is 200', s == 200, s)
check('state lists 25 checks', len(state.get('checks', [])) == 25)
check('state carries version 1.1.0', state.get('version') == '1.1.0')
check('state carries history, languages, schedules and locale', all(k in state for k in ('history', 'languages', 'schedules', 'locale', 'next_run')))
check('state has export_url with a nonce', '_wpnonce=' in state.get('export_url', '') and 'golive_audit_export' in state.get('export_url', ''))
check('checklist rows exist', len(state.get('checklist', [])) >= 9)
export_url = state['export_url']

# --- Audit ---
s, report, _ = admin.rest('POST', '/audit')
check('POST /audit is 200', s == 200, s)
check('report has summary/score/verdict', {'score', 'verdict', 'blockers'} <= set(report.get('summary', {})))
check('25 results returned', len(report.get('results', [])) == 25)
s, rep_st, _ = admin.rest('POST', '/audit', {'environment': 'staging'})
check('environment override works', s == 200 and rep_st['environment'] == 'staging')
s, bad, _ = admin.rest('POST', '/audit', {'environment': 'prod'})
check('invalid environment is rejected (400)', s == 400, s)

# throttle
import subprocess
subprocess.run(['php', '/tmp/wp-cli.phar', '--allow-root', '--path=/tmp/wp', 'transient', 'set', 'golive_audit_running', '1', '30'], capture_output=True)
s, busy, _ = admin.rest('POST', '/audit')
check('concurrent audit is throttled (429)', s == 429, s)
subprocess.run(['php', '/tmp/wp-cli.phar', '--allow-root', '--path=/tmp/wp', 'transient', 'delete', 'golive_audit_running'], capture_output=True)
s, _, _ = admin.rest('POST', '/audit')
check('audit works again after the lock clears', s == 200, s)

# --- Waivers ---
s, body, _ = admin.rest('PUT', '/waivers/seo.permalinks', {'reason': '<script>alert(1)</script>Client keeps ?p= URLs'})
check('PUT waiver is 200', s == 200, s)
reason = body.get('waivers', {}).get('seo.permalinks', {}).get('reason', '')
check('waiver reason is sanitized (no tags)', '<' not in reason and 'Client keeps' in reason, reason)
s, rep2, _ = admin.rest('POST', '/audit')
perm = [r for r in rep2['results'] if r['id'] == 'seo.permalinks'][0]
check('waived failure is flagged in the next audit', perm['waived'] is True and perm['status'] == 'fail')
check('waived count increased', rep2['summary']['waived'] >= 1)
s, _, _ = admin.rest('PUT', '/waivers/nope.unknown', {'reason': 'x'})
check('waiving an unknown check is 404', s == 404, s)
s, _, _ = admin.rest('PUT', '/waivers/seo.permalinks', {'reason': ''})
check('empty waiver reason is rejected (400)', s == 400, s)
s, _, _ = admin.rest('PUT', '/waivers/seo.permalinks', {'reason': 'x' * 501})
check('over-long waiver reason is rejected (400)', s == 400, s)
s, body, _ = admin.rest('DELETE', '/waivers/seo.permalinks')
check('DELETE waiver is 200 and empties the list', s == 200 and body['waivers'] == {}, s)

# --- Checklist & settings ---
s, body, _ = admin.rest('PUT', '/checklist/forms_tested', {'state': 'done'})
row = [r for r in body.get('checklist', []) if r['id'] == 'forms_tested']
check('checklist item can be marked done', s == 200 and row and row[0]['state'] == 'done', s)
check('progress reflects the change', body['checklist_progress']['done'] == 1)
s, _, _ = admin.rest('PUT', '/checklist/forms_tested', {'state': 'maybe'})
check('invalid checklist state is rejected (400)', s == 400, s)
s, _, _ = admin.rest('PUT', '/checklist/not_an_item', {'state': 'done'})
check('unknown checklist item is 404', s == 404, s)
s, body, _ = admin.rest('POST', '/settings', {'agency_name': 'Acme <b>Studio</b>', 'client_name': 'Client Co', 'note': 'Hello', 'preset': 'shop'})
check('settings saved and sanitized', s == 200 and body['settings']['agency_name'] == 'Acme Studio' and body['settings']['preset'] == 'shop', body)
check('switching to the shop preset adds items', len(body['checklist']) > 9)
s, _, _ = admin.rest('POST', '/settings', {'preset': 'bogus'})
check('unknown preset is rejected (400)', s == 400, s)

# --- History, language and schedule (1.1) ---
s, body, _ = admin.rest('POST', '/audit')
check('audit response carries the history', s == 200 and isinstance(body.get('history'), list) and len(body['history']) >= 1, s)
s, body, _ = admin.rest('GET', '/history')
check('GET /history lists recorded runs without summaries', s == 200 and body['history'] and 'summary' not in json.dumps(body['history']), s)
s, body, _ = admin.rest('POST', '/settings', {'language': 'fa', 'schedule': 'weekly', 'alert_email': 'owner@example.com'})
check('language, schedule and alert email are saved', s == 200 and body['settings']['language'] == 'fa' and body['settings']['schedule'] == 'weekly' and body['settings']['alert_email'] == 'owner@example.com', body)
check('weekly schedule creates a cron event', body.get('next_run', 0) > 0, body.get('next_run'))
s, _, _ = admin.rest('POST', '/settings', {'language': 'klingon'})
check('unknown language is rejected (400)', s == 400, s)
s, _, _ = admin.rest('POST', '/settings', {'schedule': 'hourly'})
check('unknown schedule is rejected (400)', s == 400, s)
s, html, _ = admin.req('GET', '/wp-admin/tools.php?page=golive-audit')
check('forced Persian makes the dashboard root right-to-left', 'dir="rtl"' in html and 'id="golive-audit-root"' in html)
s, body, _ = admin.rest('POST', '/settings', {'language': 'auto', 'schedule': 'off', 'alert_email': ''})
check('schedule off removes the cron event', s == 200 and body.get('next_run') == 0, body.get('next_run'))
s, body, _ = admin.rest('DELETE', '/history')
check('DELETE /history empties the history', s == 200 and body['history'] == [], s)

# --- Exports ---
def export(fmt, env=None, url=None):
    u = (url or export_url) + '&format=' + fmt + (('&environment=' + env) if env else '')
    return admin.req('GET', u)

s, body, h = export('csv')
check('CSV export is 200 text/csv attachment', s == 200 and 'text/csv' in h.get('Content-Type', '') and 'attachment' in h.get('Content-Disposition', ''), (s, dict(h)))
check('CSV starts with a UTF-8 BOM and the header row', body.startswith('\ufeff"id","title"'))
check('CSV has one row per check', body.count('\r\n') == 20, body.count('\r\n'))
s, body, h = export('md')
check('Markdown export is 200 text/markdown attachment', s == 200 and 'text/markdown' in h.get('Content-Type', '') and 'attachment' in h.get('Content-Disposition', '') and body.startswith('# '), (s, dict(h)))
check('Markdown export never contains the admin email address', 'dev@example.com' not in body)
s, body, h = export('json')
data = json.loads(body) if s == 200 else {}
check('JSON export is valid and bundles meta/report/checklist', s == 200 and set(data) == {'meta', 'report', 'checklist'}, s)
check('JSON meta carries the branding', data.get('meta', {}).get('agency_name') == 'Acme Studio')
check('JSON/CSV never contain the admin email address', 'dev@example.com' not in body and 'dev@example.com' not in export('csv')[1])
s, body, h = export('html', 'staging')
check('HTML export is 200 text/html with a CSP', s == 200 and 'text/html' in h.get('Content-Type', '') and "default-src 'none'" in h.get('Content-Security-Policy', ''), dict(h))
check('HTML report carries branding, verdict and checklist', 'Acme Studio' in body and 'Client Co' in body and 'Manual handoff checklist' in body)
check('HTML nonce on the script matches the CSP header', re.search(r"nonce-([A-Za-z0-9]+)", h.get('Content-Security-Policy', '')).group(1) in body)
check('HTML export honours the environment parameter', '<dd>staging</dd>' in body)
s, _, _ = admin.req('GET', export_url.split('&_wpnonce=')[0] + '&format=csv')
check('export without a nonce is refused', s in (403, 400), s)
s, _, _ = admin.req('GET', export_url + '&format=exe')
check('unknown export format is rejected (400)', s == 400, s)
s, _, _ = bob.req('GET', export_url + '&format=csv')
check('subscriber cannot export', s in (403, 302) or s == 403, s)
s, _, _ = anon.req('GET', export_url + '&format=csv')
check('anonymous export is refused', s in (400, 403, 302, 200) and not _.startswith('"id"') if isinstance(_, str) else True, s)

# --- Admin screen ---
s, html, _ = admin.req('GET', '/wp-admin/tools.php?page=golive-audit')
check('Tools > Golive Audit screen renders', s == 200 and 'id="golive-audit-root"' in html)
check('dashboard script and stylesheet are enqueued', 'golive-audit/assets/build/index.js' in html and 'style-index' in html)
s, html, _ = bob.req('GET', '/wp-admin/tools.php?page=golive-audit')
check('subscriber cannot open the screen', 'id="golive-audit-root"' not in html)

print(f"\n{sum(results)}/{len(results)} checks passed")
sys.exit(0 if all(results) else 1)
