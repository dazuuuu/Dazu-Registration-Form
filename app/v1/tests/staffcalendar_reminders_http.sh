#!/usr/bin/env bash
# HTTP checks for the Staff Calendar "Reminders" list: who can open it, that everybody sees only their own, mark as read (token, own rows only) and escaping.
# Run AFTER staffcalendar_test.php on the same scratch database (it writes the test logins), with the site served from that database:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_staffcal php -S localhost:8005 router.php &
#   B=http://localhost:8005 PHPBIN=php (same DB variables) bash app/v1/tests/staffcalendar_reminders_http.sh
B=${B:-http://localhost:8005}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/staffcal_users.json"
PHPBIN=${PHPBIN:-php}
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
jid() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['id'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
body() { curl -s -b "$1" "$B$2"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
has() { if echo "$2" | grep -aqF -- "$3"; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (missing: $3)"; fail=$((fail+1)); fi; }
hasnt() { if echo "$2" | grep -aqF -- "$3"; then echo "FAIL $1  (found: $3)"; fail=$((fail+1)); else echo "ok   $1"; pass=$((pass+1)); fi; }
q() { DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} "$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $r=$p->query($argv[1]); echo $r ? $r->fetchColumn() : "";' "$1" 2>/dev/null; }
x() { DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} "$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $p->exec($argv[1]);' "$1" 2>/dev/null; }
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
TR=/tutor/staffcalendar/reminders.php; SR=/superadmin/staffcalendar/reminders.php

login "$T/r_tutor" "$(mail tutor)"; login "$T/r_tutor2" "$(mail tutor2)"; login "$T/r_student" "$(mail student)"
login "$T/r_employee" "$(mail employee)"; login "$T/r_user" "$(mail user)"; login "$T/r_intern" "$(mail intern)"
rm -f "$T/r_admin"; curl -s -c "$T/r_admin" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/r_admin" -c "$T/r_admin" -o /dev/null -d "email=$(mail admin)&password=x" "$B/admin/portal.php"
rm -f "$T/r_super"; TOK=$(token "$T/r_super" /superadmin/login.php); curl -s -b "$T/r_super" -c "$T/r_super" -o /dev/null -d "email=$(mail super)&password=x&csrf_token=$TOK" "$B/superadmin/login.php"
TID=$(jid tutor); T2ID=$(jid tutor2); SID=$(jid super)

x "DELETE FROM staff_calendar_reminders WHERE user_id IN ($TID, $T2ID, $SID)"
x "INSERT INTO staff_calendar_reminders (user_id, kind, title, body, url, is_read) VALUES ($TID, 'on_day', 'Staff Calendar reminder', 'Mine <script>alert(1)</script> for tutor', 'tutor/staffcalendar/index.php?view=day&d=2031-03-10', 0), ($TID, 'on_day', 'Second', 'Second for tutor', 'tutor/staffcalendar/index.php', 0), ($T2ID, 'on_day', 'Other', 'Only for tutor two', 'tutor/staffcalendar/index.php', 0), ($SID, 'on_day', 'Super', 'Only for the super admin', 'superadmin/staffcalendar/index.php', 0)"

echo "== Who can open it"
t "tutor opens the page" "$(code "$T/r_tutor" $TR)" 200
t "super admin opens the page" "$(code "$T/r_super" $SR)" 200
t "tutor JSON" "$(code "$T/r_tutor" "$TR?json=1")" 200
t "super admin JSON" "$(code "$T/r_super" "$SR?json=1")" 200
for who in student employee user intern admin; do
  t "$who: page refused" "$(code "$T/r_$who" $TR | grep -c '^200$')" 0
  t "$who: JSON refused" "$(code "$T/r_$who" "$TR?json=1" | grep -c '^200$')" 0
  t "$who: super admin JSON refused" "$(code "$T/r_$who" "$SR?json=1" | grep -c '^200$')" 0
done
t "tutor cannot use the super admin address" "$(code "$T/r_tutor" "$SR?json=1")" 403
t "super admin cannot use the tutor address" "$(code "$T/r_super" "$TR?json=1")" 403
t "logged out: JSON is not served" "$(curl -s -o /dev/null -w '%{http_code}' "$B$TR?json=1" | grep -c '^200$')" 0
t "logged out: the JSON has no reminder text" "$(curl -s "$B$TR?json=1" | grep -c 'Only for')" 0

echo "== Own reminders only"
J1=$(body "$T/r_tutor" "$TR?json=1")
has "tutor sees their own" "$J1" 'Second for tutor'
hasnt "tutor does not see tutor two's" "$J1" 'Only for tutor two'
hasnt "tutor does not see the super admin's" "$J1" 'Only for the super admin'
t "unread count is 2" "$(echo "$J1" | python -c "import json,sys; print(json.load(sys.stdin)['unread'])")" 2
t "response is clean JSON" "$(echo "$J1" | python -c "import json,sys; json.load(sys.stdin); print('ok')")" ok
J2=$(body "$T/r_super" "$SR?json=1")
has "super admin sees their own" "$J2" 'Only for the super admin'
hasnt "super admin does not see the tutor's" "$J2" 'Second for tutor'
P1=$(body "$T/r_tutor" $TR)
has "the page lists the reminder" "$P1" 'Second for tutor'
hasnt "the page does not list another person's" "$P1" 'Only for tutor two'
hasnt "the page escapes the text" "$P1" '<script>alert(1)</script>'
has "the page shows the escaped text" "$P1" '&lt;script&gt;alert(1)&lt;/script&gt;'

echo "== Marking as read"
T1=$(token "$T/r_tutor" $TR)
t "token found on the page" "$([ -n "$T1" ] && echo y || echo n)" y
t "no token: refused" "$(curl -s -b "$T/r_tutor" -o /dev/null -w '%{http_code}' -d 'op=read&id=0' "$B$TR")" 403
t "wrong token: refused" "$(curl -s -b "$T/r_tutor" -o /dev/null -w '%{http_code}' -d 'op=read&id=0&csrf_token=deadbeef' "$B$TR")" 403
t "nothing was read by those" "$(q "SELECT COUNT(*) FROM staff_calendar_reminders WHERE user_id = $TID AND is_read = 1")" 0
OTHER=$(q "SELECT id FROM staff_calendar_reminders WHERE user_id = $T2ID LIMIT 1")
curl -s -b "$T/r_tutor" -o /dev/null -d "op=read&id=$OTHER&csrf_token=$T1" "$B$TR"
t "tutor cannot mark somebody else's as read" "$(q "SELECT is_read FROM staff_calendar_reminders WHERE id = $OTHER")" 0
MINE=$(q "SELECT id FROM staff_calendar_reminders WHERE user_id = $TID ORDER BY id LIMIT 1")
R1=$(curl -s -b "$T/r_tutor" -d "op=read&id=$MINE&csrf_token=$T1" "$B$TR")
t "marking one: JSON says one is left" "$(echo "$R1" | python -c "import json,sys; print(json.load(sys.stdin)['unread'])")" 1
curl -s -b "$T/r_tutor" -o /dev/null -d "op=read&id=0&csrf_token=$T1" "$B$TR"
t "mark all: nothing unread" "$(q "SELECT COUNT(*) FROM staff_calendar_reminders WHERE user_id = $TID AND is_read = 0")" 0
t "mark all did not touch other people" "$(q "SELECT COUNT(*) FROM staff_calendar_reminders WHERE user_id IN ($T2ID, $SID) AND is_read = 1")" 0
t "the form 'Mark all' goes back to the list" "$(curl -s -b "$T/r_super" -o /dev/null -w '%{http_code}' -d "op=read&id=0&back=1&csrf_token=$(token "$T/r_super" $SR)" "$B$SR")" 302

x "DELETE FROM staff_calendar_reminders WHERE user_id IN ($TID, $T2ID, $SID)"
echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
