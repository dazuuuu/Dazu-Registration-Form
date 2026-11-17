#!/usr/bin/env bash
# HTTP checks for the secretary dashboard and the installable app: the page, the menu per switch, the phone layout, who may open which page, the manifest, the icons,
# the service worker (it keeps no pages), the offline page and the notifications service. Run AFTER secretary_portal_test.php on the same scratch database, with the site served from it:
#   cd public && DAZU_DB_NAME=<scratch> php -S localhost:8011 router.php &      B=http://localhost:8011 bash app/v1/tests/secretary_portal_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_portal_users.json"
PHPBIN=${PHPBIN:-php}
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
jv() { python -c "import json,sys; d=json.load(open(sys.argv[1]))[sys.argv[2]]; print(d['email'] if isinstance(d,dict) else d)" "$J" "$1"; }
jid() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['sid'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
where() { curl -s -b "$1" -o /dev/null -w "%{redirect_url}" "$B$2" | sed "s#$B##;s/?.*//"; }
body() { curl -s -b "$1" "$B$2"; }
has() { echo "$2" | grep -aqF -- "$3" && echo yes || echo no; }
side() { echo "$1" | tr -d '\n' | sed 's/.*<nav class="sp-nav">//; s#</nav>.*##' | grep -aoE '<span>[^<]+</span>' | sed 's/<[^>]*>//g' | tr '\n' ','; }
bar() { echo "$1" | tr -d '\n' | sed 's/.*<nav class="sp-bottom"//; s#</nav>.*##' | grep -aoE '<span>[^<]+</span>' | sed 's/<[^>]*>//g' | tr '\n' ','; }
cnt() { echo "$1" | grep -ao -- "$2" | wc -l | tr -d ' '; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { "$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $p->exec($argv[1]);' "$1" 2>/dev/null; }
D=/secretary/dashboard/index.php
for k in all trusted none tasks leave left susp; do login "$T/p_$k" "$(jv $k)"; done

echo "== The dashboard of a secretary with everything on"
t "it opens" "$(code "$T/p_all" $D)" "200"
H=$(body "$T/p_all" $D)
t "greeting by Nairobi time with her first name" "$(echo "$H" | grep -aoE 'Good (morning|afternoon|evening), Mercy' | head -1 | cut -d, -f2)" " Mercy"
WANT=$(TZ=Africa/Nairobi date +%H | awk '{h=$1+0; print (h<12)?"morning":(h<17?"afternoon":"evening")}')
t "and it matches the Nairobi hour now ($WANT)" "$(has x "$H" "Good $WANT, Mercy")" "yes"
t "today's date, tasks today and enquiries waiting are in the banner" "$(echo "$H" | grep -aoE '[A-Z][a-z]+day, [0-9]{1,2} [A-Z][a-z]+ · 0 tasks today · [0-9]+ enquir(y|ies) waiting' | wc -l | tr -d ' ')" "1"
t "three small boxes: Tasks today, Pending enquiries, Blogs waiting" "$(has x "$H" '<small>Tasks today</small>') $(has x "$H" '<small>Pending enquiries</small>') $(has x "$H" '<small>Blogs waiting</small>')" "yes yes yes"
t "the four boxes" "$(has x "$H" '<h3>My tasks</h3>') $(has x "$H" '<h3>Pending enquiries</h3>') $(has x "$H" '<h3>Up next on the Staff Calendar</h3>') $(has x "$H" '<h3>Room and visitors today</h3>')" "yes yes yes yes"
t "friendly empty state for a secretary who has no tasks yet" "$(has x "$H" 'No tasks yet')" "yes"
t "the dashed note at the bottom" "$(has x "$H" 'What this dashboard never shows:') $(has x "$H" 'students, money and fees, settings, other admins’ details and student progress.') $(has x "$H" 'border:1.5px dashed')" "yes yes yes"
t "no On leave badge for an active secretary" "$(has x "$H" 'sp-badge-leave">On leave')" "no"
t "the menu: Dashboard, Blogs, Enquiries, My Tasks, Room & Visitors, Staff Calendar, My ID Card, Notifications" "$(side "$H")" "Dashboard,Blogs,Enquiries,My Tasks,Room &amp; Visitors,Staff Calendar,My ID Card,Notifications,"
t "SECRETARY PORTAL pill and the ACCOUNT heading" "$(has x "$H" 'SECRETARY PORTAL') $(has x "$H" '>ACCOUNT<')" "yes yes"
t "the phone bottom bar: Home, Blogs, Enquiries, Tasks, Calendar" "$(bar "$H")" "Home,Blogs,Enquiries,Tasks,Calendar,"
t "phone layout rules: dark header, one column, safe areas for a notch and a home bar" "$(has x "$H" 'max-width: 991.98px') $(has x "$H" 'env(safe-area-inset-bottom)') $(has x "$H" 'env(safe-area-inset-top)') $(has x "$H" 'viewport-fit=cover') $(has x "$H" 'grid-template-columns:1fr; gap:16px')" "yes yes yes yes yes"
t "the phone header has the logo and her initials (MW)" "$(has x "$H" 'class="sp-chiplogo"') $(echo "$H" | grep -ac 'class="sp-av"[^>]*>MW<')" "yes 2"
t "the Dazu colours" "$(has x "$H" '#048C44') $(has x "$H" '#037A3B') $(has x "$H" '#E3F3EA')" "yes yes yes"
t "tap targets are at least 44px (menu, buttons, bottom bar)" "$(has x "$H" 'min-height:46px') $(has x "$H" 'min-height:44px') $(has x "$H" 'min-height:56px')" "yes yes yes"

echo "== The installable app"
t "the page links the manifest, theme colour and the apple tags" "$(has x "$H" 'href="/secretary/manifest.php"') $(has x "$H" '<meta name="theme-color" content="#048C44">') $(has x "$H" 'apple-mobile-web-app-title" content="Dazu Secretary"') $(has x "$H" 'secretary-180.png')" "yes yes yes yes"
t "the Install app button is there but hidden until the browser allows it" "$(echo "$H" | grep -aoE '<button[^>]*data-sp-install[^>]*>' | head -1 | grep -ac ' hidden')" "1"
t "the script only shows it on beforeinstallprompt" "$(has x "$H" "addEventListener('beforeinstallprompt'") $(has x "$H" "addEventListener('appinstalled'")" "yes yes"
t "the app script is loaded with the secretary service worker and scope" "$(has x "$H" 'data-sw="/secretary/sw.js"') $(has x "$H" 'data-scope="/secretary/"') $(has x "$H" 'data-api="/secretary/notifications/api.php"')" "yes yes yes"
M=$(curl -s -D "$T/p_mh" "$B/secretary/manifest.php"); echo "$M" > "$T/p_manifest.json"
t "the manifest is JSON with the right type" "$(grep -aic 'content-type: application/manifest+json' "$T/p_mh")" "1"
mj() { python -c "import json,sys; m=json.load(open(sys.argv[1])); print(eval(sys.argv[2]))" "$T/p_manifest.json" "$1"; }
t "manifest: name and short name Dazu Secretary" "$(mj "m['name']+'|'+m['short_name']")" "Dazu Secretary|Dazu Secretary"
t "manifest: start page is the secretary dashboard, scope /secretary/, standalone" "$(mj "m['start_url']+'|'+m['scope']+'|'+m['display']")" "/secretary/dashboard/index.php|/secretary/|standalone"
t "manifest: theme colour #048C44" "$(mj "m['theme_color']")" "#048C44"
t "manifest: icons in all the usual sizes, plus maskable" "$(mj "','.join(sorted(set(i['sizes'].split('x')[0] for i in m['icons'] if i['purpose']=='any'), key=int))")|$(mj "','.join(i['sizes'] for i in m['icons'] if i['purpose']=='maskable')")" "72,96,128,144,152,180,192,384,512|192x192,512x512"
bad=0; for src in $(python -c "import json;print(' '.join(i['src'] for i in json.load(open('$T/p_manifest.json'))['icons']))"); do
  sz=$(curl -s "$B$src" | "$PHPBIN" -r '$d=stream_get_contents(STDIN); $i=@getimagesizefromstring($d); echo $i? $i[0]."x".$i[1]." ".$i["mime"] : "bad";'); exp=$(python -c "import json,sys;print([i['sizes'] for i in json.load(open('$T/p_manifest.json'))['icons'] if i['src']=='$src'][0])")
  [ "$sz" = "$exp image/png" ] || { bad=1; echo "   icon problem: $src is $sz, want $exp"; }; done
t "every icon is a real PNG of the size the manifest says" "$bad" "0"
SW=$(curl -s -D "$T/p_swh" "$B/secretary/sw.js")
t "the service worker file is served as JavaScript" "$(grep -aiEc 'content-type: (text|application)/javascript' "$T/p_swh") $(code "$T/p_all" /secretary/sw.js)" "1 200"
t "it is a NEW file for this portal (not the tutor or admin one) and keeps only the offline page, an icon and the logo at install" "$(has x "$SW" 'addAll([OFFLINE, ICON, LOGO])') $(has x "$SW" "NEW file")" "yes yes"
t "it never keeps a page: navigation goes to the network, .php addresses are excluded from the shell, uploads too" "$(has x "$SW" "req.mode === 'navigate'") $(has x "$SW" "/\\.php\$/i.test(url.pathname)") $(has x "$SW" "includes('/uploads/')")" "yes yes yes"
t "it does not touch forms or the API (only GET)" "$(has x "$SW" "req.method !== 'GET'")" "yes"
t "the offline page opens and has the Dazu look" "$(code "$T/p_none" /secretary/offline.html) $([ $(curl -s "$B/secretary/offline.html" | grep -ac '#048C44') -ge 1 ] && echo dazu-green)" "200 dazu-green"
t "the existing service workers are not used by this portal" "$(has x "$H" '/tutor/sw.js') $(has x "$H" 'portal-sw.js')" "no no"

echo "== The menu is also the rule: a switched-off page is refused by the server"
for pg in blogs enquiries tasks room idcard staffcalendar; do
  t "everything off: /$pg is refused (403)" "$(code "$T/p_none" /secretary/$pg/index.php)" "403"; done
t "everything off: Dashboard and Notifications still open" "$(code "$T/p_none" $D) $(code "$T/p_none" /secretary/notifications/index.php)" "200 200"
N=$(body "$T/p_none" $D)
t "everything off: the menu is only Dashboard and Notifications, no boxes, no stat boxes, no phone bar" "$(side "$N") $(cnt "$N" '<h3>') $(cnt "$N" '<small>') $(cnt "$N" '<nav class="sp-bottom"')" "Dashboard,Notifications, 0 0 0"
t "everything off: the note is still there" "$(has x "$N" 'What this dashboard never shows')" "yes"
for pg in blogs enquiries tasks room idcard staffcalendar; do t "everything on: /$pg opens" "$(code "$T/p_all" /secretary/$pg/index.php)" "200"; done
t "a placeholder page uses the secretary layout and says it is coming" "$(body "$T/p_all" /secretary/idcard/index.php | grep -ac 'will be here soon')" "1"
t "the menu marks the open page (side menu and phone bar)" "$(body "$T/p_all" /secretary/tasks/index.php | grep -aoE '<a href="/secretary/tasks/index.php" class="on"' | wc -l | tr -d ' ')" "2"
T2=$(body "$T/p_tasks" $D)
t "only Tasks on: the Tasks box and stat show, the enquiry, calendar and room boxes do not" "$(has x "$T2" '<h3>My tasks</h3>') $(has x "$T2" '<h3>Pending enquiries</h3>') $(has x "$T2" 'Up next on the Staff') $(has x "$T2" 'Room and visitors today') $(has x "$T2" '<small>Tasks today</small>') $(has x "$T2" '<small>Pending enquiries</small>')" "yes no no no yes no"
t "only Tasks on: Tasks opens, Enquiries and Blogs are refused" "$(code "$T/p_tasks" /secretary/tasks/index.php) $(code "$T/p_tasks" /secretary/enquiries/index.php) $(code "$T/p_tasks" /secretary/blogs/index.php)" "200 403 403"
TR=$(body "$T/p_trusted" $D)
t "blog approval off: no Blogs waiting box (Blogs itself is still in the menu)" "$(has x "$TR" '<small>Blogs waiting</small>') $(has x "$TR" '<span>Blogs</span>')" "no yes"
t "View only calendar: the Up next box and the menu item show; My ID Card (off) does not" "$(has x "$TR" '<h3>Up next on the Staff Calendar</h3>') $(has x "$TR" '<span>Staff Calendar</span>') $(has x "$TR" '<span>My ID Card</span>')" "yes yes no"
t "View only calendar page opens; the ID card page is refused" "$(code "$T/p_trusted" /secretary/staffcalendar/index.php) $(code "$T/p_trusted" /secretary/idcard/index.php)" "200 403"
t "the Staff Calendar page is inside the secretary frame with the calendar item marked" "$(body "$T/p_all" /secretary/staffcalendar/index.php | grep -aoE '<a href="/secretary/staffcalendar/index.php" class="on"' | wc -l | tr -d ' ')" "2"

echo "== On leave, Left, suspended"
L=$(body "$T/p_leave" $D)
t "On leave: she sees and uses everything" "$(code "$T/p_leave" $D) $(code "$T/p_leave" /secretary/blogs/index.php) $(code "$T/p_leave" /secretary/staffcalendar/index.php) $(code "$T/p_leave" /secretary/idcard/index.php)" "200 200 200 200"
t "On leave: the small badge is next to her name (desktop and phone header)" "$(cnt "$L" 'sp-badge-leave">On leave')" "2"
t "On leave: full menu and all boxes" "$(cnt "$L" '<h3>') $(side "$L" | tr ',' '\n' | grep -c .)" "4 8"
t "On leave: the notifications service works for her" "$(code "$T/p_leave" '/secretary/notifications/api.php?action=count')" "200"
t "Left: refused at once, and she cannot sign in" "$(where "$T/p_left" $D) $(code "$T/p_left" '/secretary/notifications/api.php?action=count')" "/auth/login.php 403"
t "Suspended: refused, and cannot sign in" "$(where "$T/p_susp" $D)" "/auth/login.php"
login "$T/p_susp2" "$(jv susp)"; t "Suspended: a fresh sign-in does not get in" "$(where "$T/p_susp2" $D)" "/auth/login.php"
SID=$(jid all)
q "UPDATE secretary_switches SET switch_value='off' WHERE secretary_id=$SID AND switch_key='tasks'"
t "the admin switches Tasks off: the very next request to it is refused, and the menu loses it" "$(code "$T/p_all" /secretary/tasks/index.php) $(body "$T/p_all" $D | grep -ac '<span>My Tasks</span>')" "403 0"
q "UPDATE secretary_switches SET switch_value='on' WHERE secretary_id=$SID AND switch_key='tasks'"
q "UPDATE secretaries SET status='left' WHERE id=$SID"
t "set to Left while signed in: the next request is refused" "$(where "$T/p_all" $D)" "/auth/login.php"
q "UPDATE secretaries SET status='active' WHERE id=$SID"; login "$T/p_all" "$(jv all)"

echo "== Who else"
login "$T/p_tutor" "$(jv _tutor)"
t "a tutor cannot open the secretary dashboard or its service" "$(where "$T/p_tutor" $D) $(code "$T/p_tutor" '/secretary/notifications/api.php?action=count')" "/auth/login.php 403"
t "nobody signed in: sent to sign in; the JSON service answers 403" "$(where "$T/p_nobody" $D) $(code "$T/p_nobody" '/secretary/notifications/api.php?action=count')" "/auth/login.php 403"
t "the manifest, the worker and the offline page are public files (no private data in them)" "$(code "$T/p_nobody" /secretary/manifest.php) $(code "$T/p_nobody" /secretary/sw.js) $(code "$T/p_nobody" /secretary/offline.html)" "200 200 200"
t "notifications service: count, and the push key request" "$(curl -s -b "$T/p_all" "$B/secretary/notifications/api.php?action=count" | grep -ac '"ok":true') $(curl -s -b "$T/p_all" "$B/secretary/notifications/api.php?action=vapid" | grep -ac '"ok"')" "1 1"
t "notifications service: a change needs the security token" "$(curl -s -b "$T/p_all" -o /dev/null -w '%{http_code}' -d 'action=read_all' "$B/secretary/notifications/api.php")" "403"
t "the notifications page opens inside the secretary frame, with the Turn on phone alerts button" "$(code "$T/p_all" /secretary/notifications/index.php) $(body "$T/p_all" /secretary/notifications/index.php | grep -ac 'data-pwa-enable-push')" "200 1"
t "the old placeholder text is gone" "$(has x "$H" 'Your dashboard is being built')" "no"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
