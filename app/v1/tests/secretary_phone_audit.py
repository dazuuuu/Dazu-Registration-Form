"""
Phone-width audit of every secretary screen and every admin secretary screen, in a real (headless) Chrome with a 390 x 844 touch phone, against a LOCAL copy of the site.
  python app/v1/tests/secretary_phone_audit.py <base url> <secretary cookie jar> <admin cookie jar> <ids json> [chrome path]
For each screen it reports: sideways scrolling (the page is wider than the phone), parts of the page that stick out to the right, text that is cut off (a box that hides its overflow),
buttons / fields / menu links smaller than 40 px, and, for the screens with an "Add" form, whether the form opens as a full screen sheet and whether its Close and Cancel buttons are on screen and big enough.
<ids json> = {"enquiry": 1, "blog": 1, "submission": 1, "blogsub": 1} (ids that exist in the scratch database). Needs: pip install websocket-client
"""
import json, os, shutil, subprocess, sys, tempfile, time, urllib.request
import websocket

base = sys.argv[1].rstrip('/'); sec_jar = sys.argv[2]; adm_jar = sys.argv[3]; ids = json.load(open(sys.argv[4]))
chrome = sys.argv[5] if len(sys.argv) > 5 else r'C:\Program Files\Google\Chrome\Application\chrome.exe'
port = 9444
prof = tempfile.mkdtemp(prefix='secphone_')
proc = subprocess.Popen([chrome, '--headless=new', '--disable-gpu', f'--remote-debugging-port={port}', f'--user-data-dir={prof}', '--no-first-run', '--window-size=390,844', 'about:blank'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def cookies(jar):
    out = []
    for line in open(jar, encoding='utf-8', errors='ignore'):
        line = line.rstrip('\n')
        if line.startswith('#HttpOnly_'): line = line[len('#HttpOnly_'):]
        if not line or line.startswith('#'): continue
        p = line.split('\t')
        if len(p) >= 7: out.append((p[5], p[6]))
    return out


JS = r"""
(function () {
  var W = window.innerWidth, out = {w: W, sw: document.documentElement.scrollWidth, over: [], clipped: [], small: [], title: document.title};
  function inScroller(el) { for (var p = el.parentElement; p; p = p.parentElement) { var o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll') return true; } return false; }
  function vis(el) { var r = el.getBoundingClientRect(), s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && el.offsetParent !== null; }
  function desc(el) { return el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '') + (el.textContent ? ' "' + el.textContent.trim().slice(0, 24) + '"' : ''); }
  document.querySelectorAll('body *').forEach(function (el) {
    if (!vis(el)) return;
    var r = el.getBoundingClientRect(), s = getComputedStyle(el);
    if (r.right > W + 1 && !inScroller(el) && s.position !== 'fixed' && out.over.length < 5) out.over.push(desc(el) + ' right=' + Math.round(r.right));
    if ((s.overflowX === 'hidden' || s.overflowX === 'clip') && el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0 && el.children.length === 0 && el.textContent.trim().length > 0 && out.clipped.length < 5) out.clipped.push(desc(el));
    if (s.textOverflow === 'ellipsis' && el.scrollWidth > el.clientWidth + 2 && out.clipped.length < 5) out.clipped.push('ellipsis: ' + desc(el));
  });
  document.querySelectorAll('button, input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea, summary, [role=button], a.sec-abtn, a.sb-btn, a.se-add, a.sb-new, a.ba-btn, a.tk-btn, .sp-nav a, .sp-bottom a, .rv-filters a, .sb-filters a').forEach(function (el) {
    if (!vis(el) || inScroller(el) && false) return;
    var r = el.getBoundingClientRect();
    if (el.closest('[hidden]') || el.closest('.se-sheet:not(.open)') || el.closest('.rv-pane:not(.on)')) return;
    if (el.type === 'file' ) return;
    if (r.height < 40 && out.small.length < 8) out.small.push(desc(el) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
  });
  document.querySelectorAll('input[type=checkbox], input[type=radio]').forEach(function (el) { if (vis(el)) { var r = el.getBoundingClientRect(); var lab = el.closest('label'); var lr = lab ? lab.getBoundingClientRect() : r; if (Math.max(r.height, lr.height) < 40 && out.small.length < 8) out.small.push('checkbox ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' (label ' + Math.round(lr.height) + ')'); } });
  return out;
})()
"""
SHEET = r"""
(function (sel) {
  var btn = document.querySelector('[data-open-sheet]'); if (!btn) return null;
  btn.click(); var sh = document.getElementById('seSheet'); if (!sh) return {error: 'no sheet'};
  var cs = getComputedStyle(sh), form = sh.querySelector('.se-form'), fr = form.getBoundingClientRect();
  var close = sh.querySelector('.se-x'), cancel = sh.querySelector('.se-cancel');
  function box(el) { if (!el) return null; var r = el.getBoundingClientRect(); return {w: Math.round(r.width), h: Math.round(r.height), onscreen: r.top >= 0 && r.left >= 0 && r.right <= window.innerWidth + 1}; }
  var res = {open: sh.classList.contains('open'), display: cs.display, formW: Math.round(fr.width), formH: Math.round(fr.height), vw: window.innerWidth, vh: window.innerHeight, close: box(close), cancel: box(cancel)};
  var fields = sh.querySelectorAll('input:not([type=hidden]), select, textarea'), small = 0; fields.forEach(function (f) { if (f.offsetParent && f.getBoundingClientRect().height < 44) small++; }); res.smallFields = small;
  close.click(); res.closedAfterClose = !sh.classList.contains('open'); btn.click(); var c2 = sh.querySelector('.se-cancel'); c2 && c2.click(); res.closedAfterCancel = !sh.classList.contains('open');
  document.body.style.overflow = ''; return res;
})()
"""

PAGES = [
    ('secretary', '/secretary/dashboard/index.php', False), ('secretary', '/secretary/blogs/index.php', False), ('secretary', '/secretary/blogs/edit.php', False),
    ('secretary', '/secretary/blogs/edit.php?id=%(blog)s', False), ('secretary', '/secretary/enquiries/index.php', True), ('secretary', '/secretary/enquiries/view.php?id=%(enquiry)s', False),
    ('secretary', '/secretary/tasks/index.php', False), ('secretary', '/secretary/room/index.php', True), ('secretary', '/secretary/room/index.php?f=week', False), ('secretary', '/secretary/staffcalendar/index.php', False),
    ('secretary', '/secretary/notifications/index.php', False), ('secretary', '/secretary/idcard/index.php', False),
    ('admin', '/admin/secretaries/index.php', False), ('admin', '/admin/secretaries/create.php', False), ('admin', '/admin/secretaries/create.php?id=%(secretary)s', False),
    ('admin', '/admin/secretaries/blog-approvals.php', False), ('admin', '/admin/secretaries/blog-preview.php?id=%(blogsub)s', False), ('admin', '/admin/secretaries/blog-compare.php?id=%(blogsub)s', False),
    ('admin', '/admin/secretaries/enquiries.php', False), ('admin', '/admin/secretaries/enquiry.php?id=%(enquiry)s', False), ('admin', '/admin/secretaries/tasks.php', False),
    ('admin', '/admin/secretaries/diary.php', False), ('admin', '/admin/secretaries/activity.php', False),
]
try:
    ws_url = None
    for _ in range(60):
        try:
            tabs = json.load(urllib.request.urlopen(f'http://127.0.0.1:{port}/json')); ws_url = next(t['webSocketDebuggerUrl'] for t in tabs if t['type'] == 'page'); break
        except Exception: time.sleep(0.5)
    ws = websocket.create_connection(ws_url, timeout=40); mid = [0]

    def send(method, params=None):
        mid[0] += 1; ws.send(json.dumps({'id': mid[0], 'method': method, 'params': params or {}}))
        while True:
            m = json.loads(ws.recv())
            if m.get('id') == mid[0]: return m.get('result', {})

    def ev(js):
        r = send('Runtime.evaluate', {'expression': js, 'returnByValue': True, 'awaitPromise': False}); return r.get('result', {}).get('value')

    send('Page.enable'); send('Network.enable'); send('Runtime.enable')
    send('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 2, 'mobile': True})
    send('Emulation.setTouchEmulationEnabled', {'enabled': True})
    host = base.split('//')[1].split('/')[0].split(':')[0]
    problems = 0
    for who, path, sheet in PAGES:
        send('Network.clearBrowserCookies')
        for n, v in cookies(sec_jar if who == 'secretary' else adm_jar): send('Network.setCookie', {'name': n, 'value': v, 'domain': host, 'path': '/'})
        url = base + (path % ids)
        send('Page.navigate', {'url': url}); time.sleep(2.2)
        r = ev(JS) or {}
        # the Staff Calendar page and the admin layout are existing code: they are reported but marked as "frame" when the culprit is the existing frame
        flags = []
        if r.get('sw', 0) > r.get('w', 390) + 1: flags.append(f"SIDEWAYS SCROLL (page {r['sw']}px wide on a {r['w']}px phone)")
        if r.get('over'): flags.append('sticks out: ' + '; '.join(r['over']))
        if r.get('clipped'): flags.append('text cut off: ' + '; '.join(r['clipped']))
        if r.get('small'): flags.append('small (<40px high): ' + '; '.join(r['small']))
        if sheet:
            sh = ev(SHEET) or {}
            full = sh.get('open') and sh.get('formW', 0) >= sh.get('vw', 390) - 1 and sh.get('formH', 0) >= sh.get('vh', 844) - 2
            ok = full and sh.get('close', {}) and sh['close']['onscreen'] and min(sh['close']['w'], sh['close']['h']) >= 40 and sh.get('cancel', {}).get('onscreen') and sh.get('closedAfterClose') and sh.get('closedAfterCancel') and sh.get('smallFields', 1) == 0
            print(f"   sheet: full screen={bool(full)} close button={sh.get('close')} cancel={sh.get('cancel')} closes with X={sh.get('closedAfterClose')} closes with Cancel={sh.get('closedAfterCancel')} fields under 44px={sh.get('smallFields')} -> {'OK' if ok else 'PROBLEM'}")
            if not ok: problems += 1
        status = 'OK' if not flags else 'CHECK'
        if flags: problems += 1
        print(f"[{status}] {who:9s} {path % ids}  ({(r.get('title') or '')[:40]})" + ''.join('\n        - ' + f for f in flags))
    ws.close()
finally:
    proc.terminate(); time.sleep(1); shutil.rmtree(prof, ignore_errors=True)
print('\n%d screen(s) with something to look at.' % problems)
