"""
Installability check of the Dazu Secretary app in a real (headless) Chrome, against a LOCAL copy of the site (localhost counts as a secure context). Never run it against the live site.
  python app/v1/tests/secretary_pwa_check.py <base url, e.g. http://localhost:8011> <PHPSESSID of a signed-in secretary> [chrome path]
It checks: the manifest Chrome reads and its errors, Chrome's own "can this be installed" errors, that the service worker registers for /secretary/ and becomes active,
that the cache holds ONLY the offline page and the app's own look (no page, no .php address), and that with the network off the app opens the offline page.
Needs: pip install websocket-client
"""
import json, subprocess, sys, tempfile, time, urllib.request, os, shutil
import websocket

base = sys.argv[1].rstrip('/')
sid = sys.argv[2]
chrome = sys.argv[3] if len(sys.argv) > 3 else r'C:\Program Files\Google\Chrome\Application\chrome.exe'
port = 9333
prof = tempfile.mkdtemp(prefix='secpwa_')
proc = subprocess.Popen([chrome, '--headless=new', '--disable-gpu', f'--remote-debugging-port={port}', f'--user-data-dir={prof}', '--no-first-run', '--window-size=420,860', 'about:blank'],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
fails = 0


def check(name, ok, extra=''):
    global fails
    if ok:
        print('ok  ', name)
    else:
        fails += 1
        print('FAIL', name, ('(' + str(extra) + ')') if extra != '' else '')


try:
    ws_url = None
    for _ in range(60):
        try:
            tabs = json.load(urllib.request.urlopen(f'http://127.0.0.1:{port}/json'))
            ws_url = next(t['webSocketDebuggerUrl'] for t in tabs if t['type'] == 'page')
            break
        except Exception:
            time.sleep(0.5)
    ws = websocket.create_connection(ws_url, timeout=30)
    mid = [0]

    def send(method, params=None):
        mid[0] += 1
        ws.send(json.dumps({'id': mid[0], 'method': method, 'params': params or {}}))
        while True:
            m = json.loads(ws.recv())
            if m.get('id') == mid[0]:
                return m.get('result', {}) if 'error' not in m else {'error': m['error']}

    def ev(js, await_promise=True):
        r = send('Runtime.evaluate', {'expression': js, 'awaitPromise': await_promise, 'returnByValue': True})
        return r.get('result', {}).get('value')

    send('Page.enable'); send('Network.enable'); send('Runtime.enable')
    host = base.split('//')[1].split('/')[0].split(':')[0]
    send('Network.setCookie', {'name': 'PHPSESSID', 'value': sid, 'domain': host, 'path': '/'})
    send('Page.navigate', {'url': base + '/secretary/dashboard/index.php'})
    time.sleep(4)
    title = ev('document.title')
    check('the dashboard opens signed in', 'Dashboard' in (title or ''), title)
    check('it is a secure context (localhost), so a service worker and install are allowed', ev('window.isSecureContext') is True)

    # the service worker registers for /secretary/ and becomes active
    ev("navigator.serviceWorker.ready.then(function(r){return r.scope;})")
    time.sleep(2)
    reg = ev("navigator.serviceWorker.getRegistration('/secretary/').then(function(r){return r ? {scope:r.scope, active: !!r.active, url: r.active && r.active.scriptURL} : null;})")
    check('the service worker is registered for /secretary/ and active', bool(reg) and reg['scope'].endswith('/secretary/') and reg['active'], reg)
    check('it is the new secretary/sw.js', bool(reg) and (reg.get('url') or '').endswith('/secretary/sw.js'), reg)

    # the manifest as Chrome reads it, and Chrome's own installability check
    man = send('Page.getAppManifest')
    check('Chrome reads the manifest without errors', not man.get('errors'), man.get('errors'))
    parsed = json.loads(man.get('data') or '{}')
    check('name, short name, standalone, theme colour', parsed.get('name') == 'Dazu Secretary' and parsed.get('short_name') == 'Dazu Secretary' and parsed.get('display') == 'standalone' and parsed.get('theme_color') == '#048C44', parsed)
    errs = send('Page.getInstallabilityErrors')
    check('Chrome finds nothing that stops the app from being installed', errs.get('installabilityErrors') == [], errs)

    # what the worker keeps
    keep = ev("caches.keys().then(function(ks){return Promise.all(ks.map(function(k){return caches.open(k).then(function(c){return c.keys().then(function(rs){return {cache:k, urls: rs.map(function(r){return r.url;})};});});}));})")
    urls = [u for c in (keep or []) for u in c['urls']]
    check('there is a secretary cache', any(c['cache'] == 'dazu-secretary-shell-v1' for c in (keep or [])), keep)
    check('it holds the offline page, an icon and the logo', any(u.endswith('/secretary/offline.html') for u in urls) and any('secretary-192.png' in u for u in urls) and any('logo.png' in u for u in urls), urls)
    check('it holds NO page and no .php address (nothing private)', not [u for u in urls if '.php' in u or u.rstrip('/').endswith('/secretary') or '/dashboard' in u], urls)

    # offline: the app opens the offline page instead of an error. The page-level network switch of the DevTools does not reach the service worker's own requests,
    # so for a true test set SERVER_PID to the process id of your LOCAL test server: it is stopped here (Windows: taskkill, otherwise kill).
    pid = os.environ.get('SERVER_PID')
    if pid:
        os.system(('taskkill /PID %s /F >NUL 2>&1' if os.name == 'nt' else 'kill %s') % pid)
        time.sleep(1)
    else:
        send('Network.emulateNetworkConditions', {'offline': True, 'latency': 0, 'downloadThroughput': 0, 'uploadThroughput': 0})
    send('Page.navigate', {'url': base + '/secretary/dashboard/index.php'})
    time.sleep(3)
    t2 = ev('document.title')
    body = ev('document.body ? document.body.innerText : ""')
    check('no network: the app shows the offline page', 'offline' in (t2 or '').lower() and 'Try again' in (body or ''), [t2, (body or '')[:80]])
    check('no network: it shows no dashboard data', 'Mercy' not in (body or '') and 'Good ' not in (body or ''))
    if not pid:
        send('Network.emulateNetworkConditions', {'offline': False, 'latency': 0, 'downloadThroughput': -1, 'uploadThroughput': -1})
        send('Page.navigate', {'url': base + '/secretary/dashboard/index.php'})
        time.sleep(3)
        check('back online: the dashboard is live again', 'Dashboard' in (ev('document.title') or ''))
    ws.close()
finally:
    proc.terminate()
    time.sleep(1)
    shutil.rmtree(prof, ignore_errors=True)
print('\n%s' % ('All checks passed.' if fails == 0 else '%d checks FAILED.' % fails))
sys.exit(0 if fails == 0 else 1)
