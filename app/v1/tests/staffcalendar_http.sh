#!/usr/bin/env bash
# HTTP checks for the Staff Calendar: who can open it, every view, far-away months, search, escaping, saving, ownership and private events.
# Run AFTER staffcalendar_test.php on the same scratch database, with the site served from that database, for example:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_staffcal php -S localhost:8005 router.php &
#   B=http://localhost:8005 PHPBIN=php (same DB variables) bash app/v1/tests/staffcalendar_http.sh
B=${B:-http://localhost:8005}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/staffcal_users.json"
PHPBIN=${PHPBIN:-php}
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
jid() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
body() { curl -s -b "$1" "$B$2"; }
yes() { [ "${1:-0}" -ge 1 ] && echo yes || echo no; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} "$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); echo $p->query($argv[1])->fetchColumn();' "$1" 2>/dev/null; }
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
TP=/tutor/staffcalendar/index.php; SP=/superadmin/staffcalendar/index.php
TA=/tutor/staffcalendar/action.php; SA=/superadmin/staffcalendar/action.php

login "$T/c_tutor" "$(mail tutor)"; login "$T/c_tutor2" "$(mail tutor2)"; login "$T/c_student" "$(mail student)"
login "$T/c_employee" "$(mail employee)"; login "$T/c_user" "$(mail user)"; login "$T/c_intern" "$(mail intern)"
rm -f "$T/c_admin" "$T/c_adminteach"
for who in admin adminteach; do curl -s -c "$T/c_$who" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/c_$who" -c "$T/c_$who" -o /dev/null -d "email=$(mail $who)&password=x" "$B/admin/portal.php"; done
rm -f "$T/c_super"; TOK=$(token "$T/c_super" /superadmin/login.php); curl -s -b "$T/c_super" -c "$T/c_super" -o /dev/null -d "email=$(mail super)&password=x&csrf_token=$TOK" "$B/superadmin/login.php"
NONE="$T/c_none"; rm -f "$NONE"

echo "== The tutor opens it"
t "tutor: page opens" "$(code "$T/c_tutor" $TP)" "200"
H=$(body "$T/c_tutor" $TP)
t "tutor: heading and greeting by first name" "$(echo "$H" | grep -ac -E 'Good (morning|afternoon|evening), Tina')" "1"
t "tutor: says it is private to tutors and the super admin" "$(echo "$H" | grep -ac 'Private to tutors and the super admin')" "1"
t "tutor: banner shows today's events and the next event" "$(echo "$H" | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'events? today · +Next: Staff meeting (in [0-9]+ days|today|tomorrow)')" "1"
t "tutor: This week stat" "$(echo "$H" | grep -ac 'This week')" "1"
t "tutor: Up next card with an In X days badge" "$(echo "$H" | grep -ac -E 'sc-badge">(In [0-9]+ days|Today|Tomorrow)<')" "1"
t "tutor: Birthdays coming up and Next holiday are there and empty" "$(echo "$H" | grep -ac -E 'Birthdays coming up|Next holiday')" "2"
t "tutor: one tick box per type (11)" "$(echo "$H" | grep -ao 'class="sc-filter"' | wc -l | tr -d ' ')" "11"
t "tutor: month grid is 6 weeks (42 days) starting Monday" "$(echo "$H" | grep -ao 'class="sc-cell' | wc -l | tr -d ' ')" "42"
t "tutor: first header is MON" "$(echo "$H" | grep -ao 'class="sc-dh">MON' | wc -l | tr -d ' ')" "1"
t "tutor: Month / Week / Day / Agenda switch" "$(echo "$H" | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'class=\"sc-views\".*>Month</a>.*>Week</a>.*>Day</a>.*>Agenda</a>')" "1"
t "tutor: the Staff Calendar item is in the tutor menu" "$(yes "$(echo "$H" | grep -ac 'Staff Calendar</span>')")" "yes"
t "tutor: Class Calendar item is still there" "$(yes "$(echo "$H" | grep -ac 'Class Calendar')")" "yes"
t "tutor: the Dazu green tokens are used" "$(yes "$(echo "$H" | grep -ac -E '#048C44')")" "yes"
t "tutor: the owner is shown as Added by the name, never the role" "$(echo "$H" | grep -ac -E 'Added by (Super Admin|Super admin|Tutor|Superadmin)(<|,| )' )" "0"

echo "== The super admin opens it"
t "super admin: page opens" "$(code "$T/c_super" $SP)" "200"
H=$(body "$T/c_super" $SP)
t "super admin: greeted by first name" "$(echo "$H" | grep -ac -E 'Good (morning|afternoon|evening), Simon')" "1"
t "super admin: sees the same Staff meeting" "$(yes "$(echo "$H" | grep -ac 'Staff meeting')")" "yes"
t "super admin: the Staff Calendar item is in the super admin menu" "$(yes "$(echo "$H" | grep -ac 'superadmin/staffcalendar/index.php')")" "yes"

echo "== Everyone else is refused, on the page and on the save address"
for who in admin adminteach employee user intern student; do
  for P in $TP $SP; do
    c=$(code "$T/c_$who" $P); t "$who cannot open $P (got $c)" "$([ "$c" = "200" ] && echo 200 || echo refused)" "refused"
    t "$who gets none of the calendar on $P" "$(body "$T/c_$who" $P | grep -ac -E 'sc-banner|Staff meeting')" "0"
  done
done
t "signed out: tutor page refused" "$([ "$(code $NONE $TP)" = "200" ] && echo 200 || echo refused)" "refused"
t "signed out: super admin page refused" "$([ "$(code $NONE $SP)" = "200" ] && echo 200 || echo refused)" "refused"
t "a tutor cannot use the super admin address" "$([ "$(code "$T/c_tutor" $SP)" = "200" ] && echo 200 || echo refused)" "refused"
t "the super admin cannot use the tutor address" "$([ "$(code "$T/c_super" $TP)" = "200" ] && echo 200 || echo refused)" "refused"
BEFORE=$(q "SELECT COUNT(*) FROM staff_calendar_events")
for who in admin adminteach employee user intern student; do
  for P in $TA $SA; do curl -s -b "$T/c_$who" -o /dev/null -d "title=Sneaky$who&type=meeting&start_date=2026-12-20&start_time=09:00&end_time=10:00&repeat=none" "$B$P"; done
done
t "no event was added by any of them" "$(q "SELECT COUNT(*) FROM staff_calendar_events")" "$BEFORE"
t "the menu item is not in the admin, student, employee or intern pages" "$(for who in admin employee student intern; do body "$T/c_$who" /$( [ $who = admin ] && echo admin/dashboard.php || echo $who/dashboard/index.php ) | grep -ac 'staffcalendar'; done | awk '{s+=$1} END{print s+0}')" "0"
t "the menu item is not in the pages of an admin who also teaches" "$(body "$T/c_adminteach" /tutor/dashboard/index.php | grep -ac 'staffcalendar')" "0"

echo "== Views and moving about"
for v in month week day agenda; do
  c=$(code "$T/c_tutor" "$TP?view=$v&d=2026-11-21"); t "view $v opens" "$c" "200"
  t "view $v shows the Staff meeting on 21 Nov" "$(yes "$(body "$T/c_tutor" "$TP?view=$v&d=2026-11-21" | grep -ac 'Staff meeting')")" "yes"
done
t "month label" "$(body "$T/c_tutor" "$TP?d=2026-11-13" | grep -ac 'November 2026')" "1"
t "next month by the arrow" "$(body "$T/c_tutor" "$TP?d=2026-12-01" | grep -ac 'December 2026')" "1"
t "any month forward: March 2099" "$(body "$T/c_tutor" "$TP?d=2099-03-10" | grep -ac 'March 2099')" "1"
t "any month back: January 1990" "$(body "$T/c_tutor" "$TP?d=1990-01-10" | grep -ac 'January 1990')" "1"
t "a nonsense date falls back to today" "$(code "$T/c_tutor" "$TP?d=banana&view=zzz")" "200"
t "a week view label" "$(yes "$(body "$T/c_tutor" "$TP?view=week&d=2026-11-21" | grep -ac '16 Nov – 22 Nov 2026')")" "yes"
t "month view, 21 Nov, shows the chip with its time and title" "$(body "$T/c_tutor" "$TP?d=2026-11-21" | grep -ac -E 'sc-t">10 am</span> <span class="sc-n">Staff meeting')" "1"
t "week starts on Monday: 16 Nov 2026 (Monday) is the first column" "$(body "$T/c_tutor" "$TP?view=week&d=2026-11-21" | grep -ao 'sc-wh" href="[^"]*">Mon 16' | wc -l | tr -d ' ')" "1"
t "phone: week strip is in the month view" "$(body "$T/c_tutor" "$TP?d=2026-11-21" | grep -ao 'class="sc-sd' | wc -l | tr -d ' ')" "7"

echo "== Search"
t "search finds the Staff meeting by title" "$(body "$T/c_tutor" "$TP?q=staff" | grep -ac 'Results for')" "1"
t "...and lists it" "$(body "$T/c_tutor" "$TP?q=staff" | grep -ac 'sc-rn">Staff meeting')" "1"
t "search with no match says so" "$(body "$T/c_tutor" "$TP?q=zzzqqq" | grep -ac 'No event has')" "1"

echo "== Saving, escaping, ownership"
TOK=$(token "$T/c_tutor" $TP)
post() { curl -s -b "$1" -c "$1" -o /dev/null -w "%{http_code}" "$B$2" "${@:3}"; }
t "a POST with no security token adds nothing" "$(post "$T/c_tutor" $TA -d 'title=NoToken&type=meeting&start_date=2026-12-20&start_time=09:00&end_time=10:00&repeat=none' >/dev/null; q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='NoToken'")" "0"
t "a GET on the save address adds nothing" "$(code "$T/c_tutor" $TA)" "405"
post "$T/c_tutor" $TA -d "csrf_token=$TOK" --data-urlencode 'title=<script>alert(1)</script>' -d 'type=training&start_date=2026-11-25&start_time=14:00&end_time=15:30&repeat=weekly&location=Room "A"&notify=1' >/dev/null
t "a valid POST adds the event, owned by the tutor" "$(q "SELECT owner_user_id FROM staff_calendar_events WHERE title='<script>alert(1)</script>'")" "$(jid _priv_owner)"
H=$(body "$T/c_tutor" "$TP?d=2026-11-25")
t "the title is shown escaped, never as a live script" "$(echo "$H" | grep -ac '<script>alert(1)</script>')" "0"
t "...and the escaped text is on the page" "$(echo "$H" | grep -ac '&lt;script&gt;alert(1)&lt;/script&gt;')" "$(echo "$H" | grep -ac '&lt;script&gt;alert(1)&lt;/script&gt;')"
t "a weekly event shows again a week later, with its repeat icon" "$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-02" | grep -a 'data-date="2026-12-02"' -A3 | grep -ac 'fa-rotate')")" "yes"
t "another tutor sees the event, added by the tutor's name" "$(body "$T/c_tutor2" "$TP?d=2026-11-25" | grep -ac 'Added by Tina Otieno')" "1"
EID=$(q "SELECT id FROM staff_calendar_events WHERE title='<script>alert(1)</script>'")
TOK2=$(token "$T/c_tutor2" $TP)
post "$T/c_tutor2" $TA -d "csrf_token=$TOK2" -d "op=delete&id=$EID" >/dev/null
t "another tutor cannot delete it" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$EID")" "1"
post "$T/c_tutor2" $TA -d "csrf_token=$TOK2" -d "id=$EID&title=Hijack&type=meeting&start_date=2026-11-25&start_time=14:00&end_time=15:00&repeat=none" >/dev/null
t "another tutor cannot edit it" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$EID AND title='Hijack'")" "0"
post "$T/c_tutor" $TA -d "csrf_token=$TOK" -d 'title=Quiet time&type=reminder&start_date=2026-11-26&start_time=09:00&end_time=09:30&repeat=none&private=1' >/dev/null
t "a private event is seen by its owner" "$(yes "$(body "$T/c_tutor" "$TP?d=2026-11-26" | grep -ac 'Quiet time')")" "yes"
t "...not by another tutor" "$(body "$T/c_tutor2" "$TP?d=2026-11-26" | grep -ac 'Quiet time')" "0"
t "...not by the super admin" "$(body "$T/c_super" "$SP?d=2026-11-26" | grep -ac 'Quiet time')" "0"
t "...not in search either" "$(body "$T/c_tutor2" "$TP?q=quiet" | grep -ac 'Quiet time')" "0"
PID=$(q "SELECT id FROM staff_calendar_events WHERE title='Quiet time'")
TOKS=$(token "$T/c_super" $SP)
post "$T/c_super" $SA -d "csrf_token=$TOKS" -d "op=delete&id=$PID" >/dev/null
t "the super admin cannot delete someone's private event" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$PID")" "1"
post "$T/c_super" $SA -d "csrf_token=$TOKS" -d "op=delete&id=$EID" >/dev/null
t "the super admin can delete another person's ordinary event" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$EID")" "0"
post "$T/c_tutor" $TA -d "csrf_token=$TOK" -d "op=delete&id=$PID" >/dev/null
t "the owner deletes their own event" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$PID")" "0"
t "the Staff meeting is untouched" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Staff meeting' AND start_at='2026-11-21 10:00:00'")" "1"

echo "== The event card request (event.php)"
SEED=$(jid _seed_event)
EV=/staffcalendar/event.php
card() { curl -s -b "$1" "$B$2$EV?id=$3&d=$4"; }
cj() { python -c "import json,sys; d=json.load(sys.stdin); print(d.get(sys.argv[1]))" "$1"; }
t "tutor: the card data loads (200, JSON)" "$(code "$T/c_tutor" "/tutor$EV?id=$SEED&d=2026-11-21")" "200"
CARD=$(card "$T/c_tutor" /tutor $SEED 2026-11-21)
t "card: title" "$(echo "$CARD" | cj title)" "Staff meeting"
t "card: weekday date" "$(echo "$CARD" | cj date_line)" "Saturday, 21 November"
t "card: times" "$(echo "$CARD" | cj time_line)" "10:00 am to 12:00 pm"
t "card: location" "$(echo "$CARD" | cj location)" "Nairobi House (Cianda House), 4th Floor, Room 406B"
t "card: reminder text" "$(echo "$CARD" | cj reminders)" "20 Nov 8:00 am · 21 Nov 8:00 am · 9:00 am"
t "card: the owner is a name, with no role word" "$(echo "$CARD" | cj owner | grep -ac -i -E '^(tutor|super ?admin|admin)$')" "0"
t "card: another tutor cannot edit" "$(echo "$CARD" | cj can_change)" "False"
t "card: the super admin can edit" "$(card "$T/c_super" /superadmin $SEED 2026-11-21 | cj can_change)" "True"
t "card: the super admin address works for the super admin" "$(code "$T/c_super" "/superadmin$EV?id=$SEED&d=2026-11-21")" "200"
t "card: the owner of an event can edit it" "$(card "$T/c_tutor" /tutor $(jid _tina_event) 2026-12-01 | cj can_change)" "True"
for who in admin adminteach employee user intern student; do
  for P in tutor superadmin; do
    t "card: $who gets a plain 403 on /$P" "$(code "$T/c_$who" "/$P$EV?id=$SEED&d=2026-11-21")" "403"
  done
done
t "card: signed out gets 403" "$(code $NONE "/tutor$EV?id=$SEED&d=2026-11-21")" "403"
t "card: the answer is plain text, not the event" "$(curl -s -b "$T/c_student" "$B/tutor$EV?id=$SEED&d=2026-11-21")" "Not allowed."
t "card: a tutor on the super admin address is refused" "$(code "$T/c_tutor" "/superadmin$EV?id=$SEED&d=2026-11-21")" "403"
t "card: the super admin on the tutor address is refused" "$(code "$T/c_super" "/tutor$EV?id=$SEED&d=2026-11-21")" "403"
TOK=$(token "$T/c_tutor" $TP)
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK" -d 'title=Card private&type=reminder&start_date=2026-12-05&start_time=09:00&end_time=09:30&repeat=none&private=1' "$B$TA"
PRIV=$(q "SELECT id FROM staff_calendar_events WHERE title='Card private' ORDER BY id DESC LIMIT 1")
t "card: a private event opens for its owner" "$(code "$T/c_tutor" "/tutor$EV?id=$PRIV&d=2026-12-05")" "200"
t "card: ...not for another tutor (403, Not allowed.)" "$(code "$T/c_tutor2" "/tutor$EV?id=$PRIV&d=2026-12-05")|$(curl -s -b "$T/c_tutor2" "$B/tutor$EV?id=$PRIV&d=2026-12-05")" "403|Not allowed."
t "card: ...not for the super admin" "$(code "$T/c_super" "/superadmin$EV?id=$PRIV&d=2026-12-05")" "403"
t "card: an event that does not exist is 404" "$(code "$T/c_tutor" "/tutor$EV?id=99999999&d=2026-12-05")" "404"
t "card: a POST is refused (405)" "$(curl -s -b "$T/c_tutor" -o /dev/null -w '%{http_code}' -d x=1 "$B/tutor$EV?id=$SEED")" "405"
t "card: nonsense id and day are handled (404)" "$(code "$T/c_tutor" "/tutor$EV?id=abc&d=zzz")" "404"
H=$(body "$T/c_tutor" "$TP?d=2026-11-21")
t "page: chips carry the day they were clicked on" "$(yes "$(echo "$H" | grep -ac 'data-d="2026-11-21"')")" "yes"
t "page: the card markup is there, the old centred pop-up is gone" "$(echo "$H" | grep -ac 'id="sc-pop"')|$(echo "$H" | grep -ac 'id="sc-detail"')" "1|0"
t "page: day, week and agenda rows carry the day too" "$(for v in day week agenda; do body "$T/c_tutor" "$TP?view=$v&d=2026-11-21" | grep -ac 'data-d="2026-11-21"'; done | awk '{s+=($1>0)} END{print s}')" "3"

echo "== The add / edit form"
H=$(body "$T/c_tutor" "$TP?d=2026-11-21")
t "form: title bar, owner line and footer note are there" "$(echo "$H" | grep -ac -E 'id="scf-title">New event|picked automatically|Only you and the super admin can edit this event after you save it')" "3"
t "form: one pill per type (11), Meeting first" "$(echo "$H" | grep -ao 'class="scf-pill"' | wc -l | tr -d ' ')" "11"
t "form: the four reminder switches (and the four of My settings), notify and private" "$(echo "$H" | grep -ao 'name="remind_[a-z_]*"' | wc -l | tr -d ' ')|$(echo "$H" | grep -ac 'name="notify"')|$(echo "$H" | grep -ac 'name="private"')" "8|1|1"
t "form: it carries a security token" "$(echo "$H" | grep -ac -E 'name="csrf_token" value="[a-f0-9]{64}"')" "$(echo "$H" | grep -ac -E 'name="csrf_token" value="[a-f0-9]{64}"')"
t "form: month days carry data-newday (42)" "$(echo "$H" | grep -ao 'class="sc-cell[^"]*" data-date="[0-9-]*" data-newday' | wc -l | tr -d ' ')" "42"
t "form: week columns and the day view are clickable too" "$(yes "$(body "$T/c_tutor" "$TP?view=week&d=2026-11-21" | grep -ac 'sc-wcol[^"]*" data-newday')")|$(yes "$(body "$T/c_tutor" "$TP?view=day&d=2026-11-21" | grep -ac 'data-newday')")" "yes|yes"
JT="Accept: application/json"
ajax() { curl -s -b "$1" -H "$JT" -w '\n%{http_code}' "$B$2" "${@:3}"; }
TOK=$(token "$T/c_tutor" $TP); TOK2=$(token "$T/c_tutor2" $TP); TOKS=$(token "$T/c_super" $SP)
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=Ajax event' -F 'type=training' -F 'start_date=2026-12-14' -F 'start_time=10:00' -F 'end_date=2026-12-14' -F 'end_time=11:00' -F 'repeat=weekly' -F 'location=Hall' -F 'remind_day_before=1' -F 'notify=1' -F 'owner_user_id=1' -F 'checklist=[{"t":"One","d":true},{"t":"Two","d":false}]' -F "return_view=month" -F "return_d=2026-11-21")
t "save: JSON answer with ok and where to go (the event's day)" "$(echo "$R" | head -1 | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'], d['go'].endswith('view=month&d=2026-12-14'))")|$(echo "$R" | tail -1)" "True True|200"
AID=$(q "SELECT id FROM staff_calendar_events WHERE title='Ajax event' ORDER BY id DESC LIMIT 1")
t "save: owner is the person logged in, not the posted owner" "$(q "SELECT owner_user_id FROM staff_calendar_events WHERE id=$AID")" "$(jid _priv_owner)"
t "save: reminder switches saved (only day-before on)" "$(q "SELECT CONCAT(remind_day_before,remind_on_day,remind_hour_before,remind_email) FROM staff_calendar_events WHERE id=$AID")" "1000"
t "save: checklist saved" "$(q "SELECT GROUP_CONCAT(CONCAT(label,':',is_done) ORDER BY sort_order) FROM staff_calendar_checklist WHERE event_id=$AID")" "One:1,Two:0"
t "the new event appears on its day in the month view" "$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-14" | grep -ac 'Ajax event')")" "yes"
t "a repeating event shows on every matching date (14, 21, 28 Dec)" "$(body "$T/c_tutor" "$TP?view=agenda&d=2026-12-01" | grep -ao "&quot;id&quot;:$AID," | wc -l | tr -d ' ')" "3"
t "...in the week view" "$(yes "$(body "$T/c_tutor" "$TP?view=week&d=2026-12-21" | grep -ac 'Ajax event')")" "yes"
t "...and in the day view" "$(yes "$(body "$T/c_tutor" "$TP?view=day&d=2026-12-28" | grep -ac 'Ajax event')")" "yes"
t "the card gives the owner the edit values" "$(curl -s -b "$T/c_tutor" "$B/tutor$EV?id=$AID&d=2026-12-14" | python -c "import json,sys; e=json.load(sys.stdin)['edit']; print(e['title'], e['repeat'], len(e['checklist']), e['rem_on_day'], e['start_time'])")" "Ajax event weekly 2 False 10:00"
t "the card gives another tutor no edit values" "$(curl -s -b "$T/c_tutor2" "$B/tutor$EV?id=$AID&d=2026-12-14" | python -c "import json,sys; print(json.load(sys.stdin)['edit'])")" "None"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=Bad times' -F 'type=meeting' -F 'start_date=2026-12-15' -F 'start_time=10:00' -F 'end_date=2026-12-15' -F 'end_time=09:00' -F 'repeat=none')
t "end before start: refused by the server (422, message)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; print(json.load(sys.stdin)['error'])")" "422|The event cannot end before it starts."
t "...and nothing was saved" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Bad times'")" "0"
R=$(ajax "$T/c_tutor" $TA -F 'form_v=2' -F 'title=Forged' -F 'type=meeting' -F 'start_date=2026-12-15' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none')
t "no security token: refused (403)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Forged'")" "403|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=wrong" -F 'title=Forged' -F 'type=meeting' -F 'start_date=2026-12-15' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none')
t "wrong security token: refused (403)" "$(echo "$R" | tail -1)" "403"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F "id=$AID" -F 'title=Hijack' -F 'type=meeting' -F 'start_date=2026-12-14' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none')
t "another tutor editing: refused on the server (403)" "$(echo "$R" | tail -1)|$(q "SELECT title FROM staff_calendar_events WHERE id=$AID")" "403|Ajax event"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F "id=$AID" -F 'op=delete')
t "another tutor deleting: refused (403), event stays" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$AID")" "403|1"
R=$(ajax "$T/c_super" $SA -F "csrf_token=$TOKS" -F "id=$AID" -F 'form_v=2' -F 'title=Ajax event (edited)' -F 'type=training' -F 'start_date=2026-12-14' -F 'start_time=10:00' -F 'end_date=2026-12-14' -F 'end_time=11:00' -F 'repeat=weekly' -F 'owner_user_id=1' -F 'checklist=[]')
t "the super admin can edit it, and stays not the owner; the checklist can be emptied" "$(echo "$R" | tail -1)|$(q "SELECT owner_user_id FROM staff_calendar_events WHERE id=$AID")|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$AID")" "200|$(jid _priv_owner)|0"
for who in student admin employee; do for P in $TA $SA; do
  R=$(ajax "$T/c_$who" $P -F "csrf_token=$TOK" -F 'title=Sneaky form' -F 'type=meeting' -F 'start_date=2026-12-20' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none'); done; done
t "student, admin and employee cannot save through the form address" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Sneaky form'")" "0"
# cover pictures
PNG="$T/cover_ok.png"; python -c "import base64,sys; open(sys.argv[1],'wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))" "$PNG"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=Cover event' -F 'type=meeting' -F 'start_date=2026-12-16' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F "cover=@$PNG;type=image/png")
CV=$(q "SELECT cover_image FROM staff_calendar_events WHERE title='Cover event' ORDER BY id DESC LIMIT 1")
t "cover: a PNG is accepted and stored in uploads/staffcalendar/" "$(echo "$R" | tail -1)|$(echo "$CV" | grep -ac '^uploads/staffcalendar/')|$([ -f "$ROOT/public/$CV" ] && echo file)" "200|1|file"
t "cover: kept exactly as uploaded (same bytes)" "$(cmp -s "$PNG" "$ROOT/public/$CV" && echo same)" "same"
echo '<?php echo 1;' > "$T/cover_bad.png"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'title=Bad cover' -F 'type=meeting' -F 'start_date=2026-12-16' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F "cover=@$T/cover_bad.png;type=image/png")
t "cover: a script named .png is refused (422), no event saved" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Bad cover'")" "422|0"
python -c "import sys; open(sys.argv[1],'wb').write(b'\x89PNG\r\n\x1a\n'+b'0'*(5*1024*1024+10))" "$T/cover_big.png"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'title=Big cover' -F 'type=meeting' -F 'start_date=2026-12-16' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F "cover=@$T/cover_big.png;type=image/png")
t "cover: a file over 5 MB is refused (422), no event saved" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='Big cover'")" "422|0"
CID=$(q "SELECT id FROM staff_calendar_events WHERE title='Cover event' ORDER BY id DESC LIMIT 1")
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "id=$CID" -F 'form_v=2' -F 'title=Cover event' -F 'type=meeting' -F 'start_date=2026-12-16' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F 'remove_cover=1')
t "cover: Remove clears it and deletes the file" "$(q "SELECT COALESCE(cover_image,'none') FROM staff_calendar_events WHERE id=$CID")|$([ -f "$ROOT/public/$CV" ] && echo still || echo gone)" "none|gone"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "id=$AID" -F 'op=delete')
t "the owner deletes it (the super admin only edited it); the answer is clean JSON, checklist gone" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; print(json.load(sys.stdin)['ok'])")|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$AID")" "200|True|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "id=$AID" -F 'op=delete')
t "deleting it again: 404" "$(echo "$R" | tail -1)" "404"

echo "== The details panel (full=1)"
FULL() { curl -s -b "$1" "$B$2$EV?id=$3&d=$4&full=1"; }
fj() { python -c "import json,sys; d=json.load(sys.stdin); v=d.get(sys.argv[1]); print(v if not isinstance(v,list) else '|'.join(map(str,v)))" "$1"; }
PN=$(FULL "$T/c_tutor" /tutor $SEED 2026-11-21)
t "panel: the full date and Nairobi time" "$(echo "$PN" | fj date_full)|$(echo "$PN" | fj time_full)" "Saturday, 21 November 2026|10:00 am to 12:00 pm · Nairobi time"
t "panel: the place on two lines" "$(echo "$PN" | fj where1)|$(echo "$PN" | fj where2)" "Nairobi House (Cianda House)|4th Floor, Room 406B"
t "panel: the reminder pills and the line under them" "$(echo "$PN" | fj reminder_times)|$(echo "$PN" | fj reminder_note)" "Fri 20 Nov 8:00 am|Sat 21 Nov 8:00 am|Sat 21 Nov 9:00 am|Push, email and in the app"
t "panel: added by a name only; not edited yet" "$(echo "$PN" | fj edited_text)|$(echo "$PN" | fj owner | grep -ac -i -E 'admin$|tutor$|·')" "Not edited yet|0"
t "panel: another tutor cannot edit or delete (buttons will be grey)" "$(echo "$PN" | fj can_change)" "False"
t "panel: the super admin can" "$(FULL "$T/c_super" /superadmin $SEED 2026-11-21 | fj can_change)" "True"
for who in admin student employee; do t "panel: $who gets a plain 403" "$(code "$T/c_$who" "/tutor$EV?id=$SEED&d=2026-11-21&full=1")" "403"; done
t "panel: signed out gets 403" "$(code $NONE "/tutor$EV?id=$SEED&d=2026-11-21&full=1")" "403"
t "panel: a private event is not opened for another tutor or the super admin" "$(code "$T/c_tutor2" "/tutor$EV?id=$PRIV&d=2026-12-05&full=1")|$(code "$T/c_super" "/superadmin$EV?id=$PRIV&d=2026-12-05&full=1")|$(code "$T/c_tutor" "/tutor$EV?id=$PRIV&d=2026-12-05&full=1")" "403|403|200"
t "panel: the page has the panel and the Agenda / search rows open it directly" "$(body "$T/c_tutor" "$TP?d=2026-11-21" | grep -ac 'id="scd"')|$(yes "$(body "$T/c_tutor" "$TP?view=agenda&d=2026-11-21" | grep -ac 'data-panel="1"')")|$(yes "$(body "$T/c_tutor" "$TP?q=staff" | grep -ac 'data-panel="1"')")|$(body "$T/c_tutor" "$TP?d=2026-11-21" | grep -ac 'sc-chip[^>]*data-panel')" "1|yes|yes|0"
TOK=$(token "$T/c_tutor" $TP); TOK2=$(token "$T/c_tutor2" $TP); TOKS=$(token "$T/c_super" $SP)
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=Tick event' -F 'type=meeting' -F 'start_date=2026-12-18' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F 'checklist=[{"t":"One","d":false},{"t":"Two","d":false}]')
TKID=$(q "SELECT id FROM staff_calendar_events WHERE title='Tick event' ORDER BY id DESC LIMIT 1")
IT1=$(q "SELECT id FROM staff_calendar_checklist WHERE event_id=$TKID ORDER BY sort_order LIMIT 1")
t "tick: not edited yet, no editor, after the first save" "$(q "SELECT COALESCE(last_edited_by,'none') FROM staff_calendar_events WHERE id=$TKID")" "none"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=tick' -F "id=$TKID" -F "item=$IT1" -F 'done=1')
t "tick: another tutor ticks an item, saved at once (clean JSON)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'], d['done'])")|$(q "SELECT is_done FROM staff_calendar_checklist WHERE id=$IT1")" "200|True True|1"
t "tick: and the owner sees the tick in the panel" "$(FULL "$T/c_tutor" /tutor $TKID 2026-12-18 | python -c "import json,sys; print([c['d'] for c in json.load(sys.stdin)['checklist_rows']])")" "[True, False]"
R=$(ajax "$T/c_super" $SA -F "csrf_token=$TOKS" -F 'op=tick' -F "id=$TKID" -F "item=$IT1")
t "tick: the super admin unticks it" "$(echo "$R" | tail -1)|$(q "SELECT is_done FROM staff_calendar_checklist WHERE id=$IT1")" "200|0"
t "tick: ticking is not an edit" "$(q "SELECT COALESCE(last_edited_by,'none') FROM staff_calendar_events WHERE id=$TKID")" "none"
R=$(ajax "$T/c_tutor2" $TA -F 'op=tick' -F "id=$TKID" -F "item=$IT1" -F 'done=1')
t "tick: no security token is refused (403) and nothing changes" "$(echo "$R" | tail -1)|$(q "SELECT is_done FROM staff_calendar_checklist WHERE id=$IT1")" "403|0"
for who in student admin employee; do R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=tick' -F "id=$TKID" -F "item=$IT1" -F 'done=1'); done
t "tick: a student, admin or employee cannot tick" "$(q "SELECT is_done FROM staff_calendar_checklist WHERE id=$IT1")" "0"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=tick' -F "id=$SEED" -F "item=$IT1" -F 'done=1')
t "tick: an item that belongs to another event is refused (404)" "$(echo "$R" | tail -1)|$(q "SELECT is_done FROM staff_calendar_checklist WHERE id=$IT1")" "404|0"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=tick' -F "id=$PRIV" -F "item=$IT1" -F 'done=1')
t "tick: nothing on a private event of someone else (404)" "$(echo "$R" | tail -1)" "404"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "id=$TKID" -F 'form_v=2' -F 'title=Tick event' -F 'type=meeting' -F 'start_date=2026-12-18' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none' -F 'checklist=[{"t":"One","d":true}]')
t "edit: the last editor and time are recorded, shown as a name" "$(q "SELECT last_edited_by FROM staff_calendar_events WHERE id=$TKID")|$(FULL "$T/c_tutor2" /tutor $TKID 2026-12-18 | fj edited_text | grep -ac -E '^Last edited by Tina Otieno · ')" "$(jid _priv_owner)|1"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "id=$TKID" -F 'op=delete')
t "delete from the panel works for the owner (clean JSON)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$TKID")|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$TKID")" "200|0|0"

echo "== Birthdays, the banner and the birthday messages"
set -- $("$PHPBIN" -r 'date_default_timezone_set("Africa/Nairobi"); echo date("j n Y");'); TD=$1; TM=$2; TY=$3
TODAYS=$(printf '%04d-%02d-%02d' $TY $TM $TD); NEXTY=$(printf '%04d-%02d-%02d' $((TY+1)) $TM $TD)
jid2() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]][sys.argv[3]])" "$J" "$1" "$2"; }
T2ID=$(jid2 tutor2 id); TID=$(jid2 tutor id)
TOK=$(token "$T/c_tutor" $TP); TOK2=$(token "$T/c_tutor2" $TP); TOKS=$(token "$T/c_super" $SP)
cell() { body "$1" "$2" | grep -a "data-date=\"$3\"" -A8 | grep -ac 'sc-bday'; }
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F "day=$TD" -F "month=$TM" -F 'year=1990' -F "user_id=$T2ID")
t "birthday: a tutor adds their own (clean JSON), and a posted user_id for someone else is ignored" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; print(json.load(sys.stdin)['ok'])")|$(q "SELECT COUNT(*) FROM staff_calendar_birthdays WHERE user_id=$TID")|$(q "SELECT COUNT(*) FROM staff_calendar_birthdays WHERE user_id=$T2ID")" "200|True|1|0"
t "birthday: saved with the year but the age is off by default" "$(q "SELECT CONCAT(birth_year,'/',show_age) FROM staff_calendar_birthdays WHERE user_id=$TID")" "1990/0"
t "chip: a pink birthday chip on today's cell, in the month view" "$(yes "$(cell "$T/c_tutor2" "$TP?d=$TODAYS" $TODAYS)")" "yes"
t "chip: and on the same day next year" "$(yes "$(cell "$T/c_tutor2" "$TP?d=$NEXTY" $NEXTY)")" "yes"
t "chip: it is a Birthday type item (data-type, pink, cake icon, the person's name)" "$(body "$T/c_tutor2" "$TP?d=$TODAYS" | grep -ac -E 'sc-chip sc-bday" data-type="birthday" style="--c:#d6457f.*fa-cake-candles')" "1"
t "chip: it is also in the Week, Day and Agenda views" "$(for v in week day agenda; do yes "$(body "$T/c_tutor2" "$TP?view=$v&d=$TODAYS" | grep -ac 'sc-bday\|sc-bdrow')"; done | tr '\n' ',')" "yes,yes,yes,"
t "chip: clicking it is not an event (no data-ev, no popup)" "$(body "$T/c_tutor2" "$TP?d=$TODAYS" | grep -ao '<span class="sc-chip sc-bday"[^>]*>' | grep -ac 'data-ev')" "0"
t "age: nobody sees an age when it is off (page of another tutor)" "$(body "$T/c_tutor2" "$TP?d=$TODAYS" | grep -ac -E 'turns [0-9]')" "0"
t "banner: the birthday person sees Happy Birthday, their first name, the pill and their message" "$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'BIRTHDAY · TODAY.*<h2>Happy Birthday, Tina!</h2><p>[^<]{8,}</p>')" "1"
t "banner: ...and the line Message N of M · never repeated for you" "$(body "$T/c_tutor" "$TP" | grep -ac -E 'Message [0-9]+ of [0-9]+ · never repeated for you')" "1"
t "banner: everyone else sees Today is Tina's birthday, no message and no count line" "$(body "$T/c_tutor2" "$TP" | grep -ac 'Today is Tina’s birthday')|$(body "$T/c_tutor2" "$TP" | grep -ac -E 'never repeated|Happy Birthday, Tina')" "1|0"
t "banner: the super admin sees the count line for them" "$(body "$T/c_super" "$SP" | grep -ac -E 'Message [0-9]+ of [0-9]+ · never repeated for Tina')" "1"
t "banner: the avatar has initials" "$(body "$T/c_tutor2" "$TP" | grep -ac 'sc-bd-ava">TO')" "1"
t "message: one message for the year, however many times it is opened" "$(q "SELECT COUNT(*) FROM staff_calendar_birthday_log WHERE user_id=$TID AND birth_year=$TY")" "1"
t "message: it is the same message every time that day" "$(body "$T/c_tutor" "$TP" | grep -a -o 'Message [0-9]* of' | head -1)|$(body "$T/c_tutor" "$TP" | grep -a -o 'Message [0-9]* of' | head -1)" "$(body "$T/c_tutor" "$TP" | grep -a -o 'Message [0-9]* of' | head -1)|$(body "$T/c_tutor" "$TP" | grep -a -o 'Message [0-9]* of' | head -1)"
t "message: the name is put in ({first_name} never shows in the banner)" "$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ' | grep -ao '<section class="sc-bd".*</section>' | head -1 | grep -ac '{first_name}')" "0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F "day=$TD" -F "month=$TM" -F 'year=1990' -F 'show_age=1')
t "age: when the person chooses it, others see it" "$(yes "$(body "$T/c_tutor2" "$TP?d=$TODAYS" | grep -ac 'turns 36')")" "yes"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F "day=$TD" -F "month=$TM" -F 'year=1990')
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F 'day=30' -F 'month=2')
t "birthday: 30 February is refused (422) and the saved one is unchanged" "$(echo "$R" | tail -1)|$(q "SELECT CONCAT(birth_day,'-',birth_month) FROM staff_calendar_birthdays WHERE user_id=$TID")" "422|$TD-$TM"
R=$(ajax "$T/c_super" $SA -F "csrf_token=$TOKS" -F 'op=bday_save' -F "user_id=$T2ID" -F 'day=29' -F 'month=2')
t "birthday: the super admin sets it for another tutor (29 February)" "$(echo "$R" | tail -1)|$(q "SELECT CONCAT(birth_day,'-',birth_month,'-',COALESCE(birth_year,'x'),'-',show_age) FROM staff_calendar_birthdays WHERE user_id=$T2ID")" "200|29-2-x-0"
t "29 Feb: shows on 28 February in 2027, on 29 February in 2028" "$(yes "$(cell "$T/c_tutor" "$TP?d=2027-02-10" 2027-02-28)")|$(yes "$(cell "$T/c_tutor" "$TP?d=2028-02-10" 2028-02-29)")|$(cell "$T/c_tutor" "$TP?d=2027-02-10" 2027-03-01)" "yes|yes|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_remove' -F "user_id=$T2ID")
t "birthday: a tutor removing 'someone else's' only removes their own (none here), tutor2's stays" "$(q "SELECT COUNT(*) FROM staff_calendar_birthdays WHERE user_id=$T2ID")" "1"
for who in student admin employee; do R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F 'day=1' -F 'month=1'); R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=suggest' -F 'tag=Funny' -F 'body=Sneaky {first_name}'); done
t "birthday: a student, admin or employee cannot add a birthday or suggest a message" "$(q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE body LIKE 'Sneaky%'")" "0"
R=$(ajax "$T/c_tutor2" $TA -F 'op=bday_save' -F 'day=1' -F 'month=1')
t "birthday: no security token is refused (403)" "$(echo "$R" | tail -1)" "403"
H=$(body "$T/c_tutor2" "$TP")
t "box: Birthdays coming up lists people with 'in X days' / today, and has Add my birthday + Suggest a message" "$(echo "$H" | grep -ac -E 'Add my birthday|Change my birthday')|$(echo "$H" | grep -ac 'Suggest a message')|$(yes "$(echo "$H" | grep -ac -E '· (today|tomorrow|in [0-9]+ days)')")" "1|1|yes"
t "box: a tutor has no link to the Birthday messages page" "$(echo "$H" | grep -ac 'staffcalendar/settings.php')" "0"
t "box: the super admin has the link" "$(body "$T/c_super" "$SP" | grep -ac 'superadmin/staffcalendar/settings.php?tab=messages')" "1"
t "box: the Birthday tick box under Show on calendar is there" "$(echo "$H" | grep -ac 'class="sc-filter" value="birthday"')" "1"

echo "== The Birthday messages page (super admin only)"
MP=/superadmin/staffcalendar/settings.php
t "messages page: the super admin opens it" "$(code "$T/c_super" $MP)" "200"
for who in tutor tutor2 student admin employee intern; do c=$(code "$T/c_$who" $MP); t "messages page: $who is refused (got $c)" "$([ "$c" = "200" ] && echo 200 || echo refused)" "refused"; done
t "messages page: signed out is refused" "$([ "$(code $NONE $MP)" = "200" ] && echo 200 || echo refused)" "refused"
t "messages page: a tutor sees no part of it" "$(body "$T/c_tutor" $MP | grep -ac -E 'Birthday messages|bm-card')" "0"
MPG=$(body "$T/c_super" "$MP?tab=messages")
t "messages page: counts and the add form" "$(echo "$MPG" | grep -ac -E 'messages in use')|$(echo "$MPG" | grep -ac 'name="op" value="add"')" "1|1"
t "messages page: filter by tag (Sheng shows only Sheng messages)" "$(body "$T/c_super" "$MP?tab=messages&tag=Sheng" | grep -ao 'bm-pill">Sheng' | wc -l | tr -d ' ')|$(body "$T/c_super" "$MP?tab=messages&tag=Sheng" | grep -ao 'bm-pill">Funny' | wc -l | tr -d ' ')" "$(q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE tag='Sheng'")|0"
t "messages page: a tutor posting to it changes nothing" "$(curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&op=add&tag=Tech&body=Hack {first_name}" "$B$MP"; q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE body LIKE 'Hack%'")" "0"
t "messages page: no security token adds nothing" "$(curl -s -b "$T/c_super" -o /dev/null -d "op=add&tag=Tech&body=Forged {first_name}" "$B$MP"; q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE body LIKE 'Forged%'")" "0"
TOKM=$(token "$T/c_super" $MP)
mp() { curl -s -b "$T/c_super" -o /dev/null -w '%{http_code}' -d "csrf_token=$TOKM" "$B$MP" "$@"; }
mp -d 'op=add' -d 'tag=Tech' --data-urlencode 'body=HTTP message <b>x</b> for {first_name}' >/dev/null
MID=$(q "SELECT id FROM staff_calendar_birthday_messages WHERE body LIKE 'HTTP message%' ORDER BY id DESC LIMIT 1")
t "messages page: the super admin adds a message (approved, on)" "$(q "SELECT CONCAT(status,is_active,tag) FROM staff_calendar_birthday_messages WHERE id=$MID")" "approved1Tech"
t "messages page: the text is shown escaped" "$(body "$T/c_super" "$MP?tab=messages&tag=Tech" | grep -ac '<b>x</b>')|$(body "$T/c_super" "$MP?tab=messages&tag=Tech" | grep -ac '&lt;b&gt;x&lt;/b&gt;' | awk '{print ($1>=1)?1:0}')" "0|1"
mp -d 'op=edit' -d "id=$MID" -d 'tag=Funny' --data-urlencode 'body=HTTP edited for {first_name}' >/dev/null
t "messages page: edit" "$(q "SELECT CONCAT(tag,':',body) FROM staff_calendar_birthday_messages WHERE id=$MID")" "Funny:HTTP edited for {first_name}"
mp -d 'op=off' -d "id=$MID" >/dev/null
t "messages page: switch off keeps the message" "$(q "SELECT is_active FROM staff_calendar_birthday_messages WHERE id=$MID")|$(q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE id=$MID")" "0|1"
mp -d 'op=on' -d "id=$MID" >/dev/null
t "messages page: switch on" "$(q "SELECT is_active FROM staff_calendar_birthday_messages WHERE id=$MID")" "1"
t "messages page: there is no delete action (the op is refused and the row stays)" "$(mp -d 'op=delete' -d "id=$MID" >/dev/null; q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE id=$MID")" "1"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=suggest' -F 'tag=Funny' -F 'body=HTTP suggestion <i>y</i> {first_name}')
t "suggest: a tutor sends a suggestion (clean JSON) and it waits as pending, off" "$(echo "$R" | tail -1)|$(q "SELECT CONCAT(status,is_active) FROM staff_calendar_birthday_messages WHERE body LIKE 'HTTP suggestion%' ORDER BY id DESC LIMIT 1")" "200|pending0"
SID=$(q "SELECT id FROM staff_calendar_birthday_messages WHERE body LIKE 'HTTP suggestion%' ORDER BY id DESC LIMIT 1")
t "suggest: the super admin sees it under Waiting, escaped, with the tutor's name only" "$(body "$T/c_super" "$MP?tab=messages&status=pending" | grep -ac 'Suggested by Tina Otieno')|$(body "$T/c_super" "$MP?tab=messages&status=pending" | grep -ac '<i>y</i>')|$(body "$T/c_super" "$MP?tab=messages&status=pending" | grep -ac -i 'suggested by [^<]*\(tutor\|admin\)')" "1|0|0"
t "suggest: while pending it is never used for a birthday" "$(q "SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE id=$SID AND status='approved' AND is_active=1")" "0"
mp -d 'op=approve' -d "id=$SID" >/dev/null
t "suggest: approved by the super admin, now in use" "$(q "SELECT CONCAT(status,is_active) FROM staff_calendar_birthday_messages WHERE id=$SID")" "approved1"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=suggest' -F 'tag=Blessing' -F 'body=HTTP reject me {first_name}')
RID=$(q "SELECT id FROM staff_calendar_birthday_messages WHERE body LIKE 'HTTP reject me%' ORDER BY id DESC LIMIT 1")
mp -d 'op=reject' -d "id=$RID" >/dev/null
t "suggest: rejected, kept on the list as rejected" "$(q "SELECT CONCAT(status,is_active) FROM staff_calendar_birthday_messages WHERE id=$RID")|$(yes "$(body "$T/c_super" "$MP?tab=messages&status=rejected" | grep -ac 'HTTP reject me')")" "rejected0|yes"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=suggest' -F 'tag=Rude' -F 'body=x')
t "suggest: a bad tag is refused (422)" "$(echo "$R" | tail -1)" "422"
t "suggest: a tutor cannot approve their own suggestion (no access to the page)" "$(curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&op=approve&id=$RID" "$B$MP"; q "SELECT status FROM staff_calendar_birthday_messages WHERE id=$RID")" "rejected"
t "messages page: the page is a single column list on phones (the rule is there)" "$(yes "$(echo "$MPG" | grep -ac 'max-width:767.98px')")" "yes"
q "DELETE FROM staff_calendar_birthdays WHERE user_id IN ($TID, $T2ID)" >/dev/null

echo "== The gift tracker"
gj() { ajax "$1" "$2" -F "csrf_token=$3" -F "op=$4" "${@:5}"; }
jok() { python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'], d.get('error',''))"; }
GP=/superadmin/staffcalendar/gifts.php
TOK=$(token "$T/c_tutor" $TP); TOK2=$(token "$T/c_tutor2" $TP); TOKS=$(token "$T/c_super" $SP)
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=bday_save' -F "day=$TD" -F "month=$TM")
body "$T/c_tutor2" "$TP" > /dev/null; body "$T/c_super" "$SP" > /dev/null
LID=$(q "SELECT id FROM staff_calendar_gift_lists WHERE user_id=$TID ORDER BY id DESC LIMIT 1")
N=$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND status<>'excluded'")
TOTK=$(python -c "print(format($N*1500, ','))")
t "list: made the first time the calendar is opened on the birthday, once only, with the amount of that moment" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_lists WHERE user_id=$TID")|$(q "SELECT amount FROM staff_calendar_gift_lists WHERE id=$LID")" "1|1500"
body "$T/c_tutor" "$TP" > /dev/null; body "$T/c_tutor2" "$TP" > /dev/null
t "list: opening the page again makes no second list" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_lists WHERE user_id=$TID")" "1"
t "list: the birthday person is not on it; everyone else starts as Not yet; the super admin is on it" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$TID")|$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND status<>'not_yet'")|$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$(jid2 super id)")" "0|0|1"
H2=$(body "$T/c_tutor2" "$TP" | tr -d '\r' | tr '\n' ' ')
t "card: another tutor sees Your gift to Tina with the amount and the privacy words" "$(echo "$H2" | grep -ac -E 'Your gift to Tina.*KES 1,500 · only you and Tina see your status')" "1"
t "card: with the green I have sent button for that list and the no-money text" "$(echo "$H2" | grep -ac -E "data-gift=\"send\" data-list=\"$LID\">I have sent")|$(echo "$H2" | grep -ac 'Dazu does not move any money. Send it the way you always do, then tap the button. Tina confirms, and your name turns green.')" "1|1"
t "list privacy: another tutor never sees the list or anyone's status for Tina" "$(echo "$H2" | grep -ao -E 'Only Tina and the super admin see this list|Only you and the super admin see this list' | wc -l | tr -d ' ')|$(body "$T/c_tutor2" "$TP" | grep -ac -E 'class=\"sg-pill|data-gift=\"confirm\"')" "0|0"
HT=$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ')
t "list: the birthday person sees Birthday gifts, the privacy line, the big count, the KES line and the bar" "$(echo "$HT" | grep -ac -E "Birthday gifts</h3><span>Only you and the super admin see this list.*0 of $N received.*KES 0 of KES $TOTK · KES 1,500 each.*sg-bar")" "1"
t "list: rows with a role word and a status pill, and the reminder button" "$(echo "$HT" | grep -ao 'class="sg-pill not_yet"' | wc -l | tr -d ' ')|$(echo "$HT" | grep -ac -E 'Remind those who have not sent')|$(echo "$HT" | grep -ac -E '<small>Super admin</small>')" "$N|1|1"
t "list: the birthday person has no card for their own gift" "$(echo "$HT" | grep -ac 'Your gift to Tina')" "0"
HS=$(body "$T/c_super" "$SP" | tr -d '\r' | tr '\n' ' ')
t "list: the super admin sees the list too, and the Gift history link" "$(echo "$HS" | grep -ac -E 'Only Tina and the super admin see this list')|$(echo "$HS" | grep -ac 'superadmin/staffcalendar/gifts.php')" "1|1"
t "list: a tutor has no Gift history link" "$(echo "$H2" | grep -ac 'staffcalendar/gifts.php')" "0"
for who in student admin employee intern; do R=$(gj "$T/c_$who" $TA "$TOK" gift_send -F "list=$LID"); R=$(gj "$T/c_$who" $TA "$TOK" gift_remind -F "list=$LID"); done
t "taps: a student, admin, employee or intern cannot tap anything" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND status<>'not_yet'")|$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id=$LID")" "0|0"

echo "== The reminder button"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_remind -F "list=$LID")
t "reminder: another tutor cannot use it (403)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id=$LID")" "403|0"
R=$(gj "$T/c_tutor" $TA "$TOK" gift_remind -F "list=$LID")
t "reminder: the birthday person sends gentle notes to everyone who is Not yet (clean JSON)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | jok | cut -d' ' -f1)|$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id=$LID")" "200|True|$N"
R=$(gj "$T/c_tutor" $TA "$TOK" gift_remind -F "list=$LID")
t "reminder: a second tap the same day says Already sent today and sends nothing (409)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | jok)|$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id=$LID")" "409|False Already sent today|$N"
R=$(gj "$T/c_super" $SA "$TOKS" gift_remind -F "list=$LID")
t "reminder: the super admin cannot send a second one the same day either" "$(echo "$R" | tail -1)" "409"
t "reminder: the button is greyed with Already sent today on the next page view" "$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'data-gift="remind" data-list="'$LID'" disabled><i class="fas fa-bell"></i> Already sent today')" "1"
t "reminder: the note shows on the Staff Calendar page of a person who is Not yet" "$(body "$T/c_tutor2" "$TP" | grep -ac 'Tina’s birthday gift is still waiting')" "1"
NID=$(q "SELECT id FROM staff_calendar_gift_notes WHERE list_id=$LID AND to_user_id=$(jid2 super id) ORDER BY id DESC LIMIT 1")
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_note_dismiss -F "note=$NID")
t "reminder: another person cannot dismiss someone else's note" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE id=$NID AND dismissed_at IS NULL")" "1"
R=$(gj "$T/c_super" $SA "$TOKS" gift_note_dismiss -F "note=$NID")
t "reminder: the person can dismiss their own" "$(q "SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE id=$NID AND dismissed_at IS NULL")" "0"

echo "== Tapping: each person changes only their own row"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_send -F "list=$LID")
t "I have sent: Not yet becomes Says sent for the person who tapped, nobody else (clean JSON)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | jok | cut -d' ' -f1)|$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")|$(q "SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id=$LID AND status='says_sent'")" "200|True|says_sent|1"
H2=$(body "$T/c_tutor2" "$TP" | tr -d '\r' | tr '\n' ' ')
t "I have sent: the card now says so and offers Undo, no I have sent button" "$(echo "$H2" | grep -ac -E "data-gift=\"undo\" data-list=\"$LID\"")|$(echo "$H2" | grep -ac -E "data-gift=\"send\" data-list=\"$LID\"")" "1|0"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_undo -F "list=$LID")
t "undo: it goes back to Not yet" "$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "not_yet"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_send -F "list=$LID")
R=$(gj "$T/c_tutor" $TA "$TOK" gift_send -F "list=$LID")
t "I have sent: the birthday person cannot send to themselves (422)" "$(echo "$R" | tail -1)" "422"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_confirm -F "list=$LID" -F "giver=$T2ID")
t "confirm: another tutor cannot confirm, not even their own row (403, still Says sent)" "$(echo "$R" | tail -1)|$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "403|says_sent"
t "confirm: the birthday person sees the Confirm received button on the Says sent row" "$(body "$T/c_tutor" "$TP" | grep -ac -E "data-gift=\"confirm\" data-list=\"$LID\" data-giver=\"$T2ID\"")" "1"
SID=$(jid2 super id)
R=$(gj "$T/c_super" $SA "$TOKS" gift_confirm -F "list=$LID" -F "giver=$T2ID")
t "confirm: the super admin confirms on their behalf, recorded with the super admin" "$(echo "$R" | tail -1)|$(q "SELECT CONCAT(status,':',confirmed_by) FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "200|received:$SID"
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_undo -F "list=$LID")
t "undo: once Received it cannot be undone (422)" "$(echo "$R" | tail -1)|$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "422|received"
R=$(gj "$T/c_super" $SA "$TOKS" gift_send -F "list=$LID")
R=$(gj "$T/c_tutor" $TA "$TOK" gift_confirm -F "list=$LID" -F "giver=$SID")
t "confirm: the birthday person confirms the super admin's row, recorded with them" "$(echo "$R" | tail -1)|$(q "SELECT CONCAT(status,':',confirmed_by) FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$SID")" "200|received:$TID"
R=$(gj "$T/c_tutor" $TA "$TOK" gift_confirm -F "list=$LID" -F "giver=999999999")
t "confirm: a person who is not on the list is refused" "$(echo "$R" | tail -1)" "422"
HT=$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ')
t "list: the counts follow (2 of N received, KES 3,000 of ...) and the Received pills are green" "$(echo "$HT" | grep -ac -E "2 of $N received.*KES 3,000 of KES $TOTK · KES 1,500 each")|$(echo "$HT" | grep -ao 'class="sg-pill received"' | wc -l | tr -d ' ')" "1|2"
t "list: a confirmed row remembers who confirmed it (shown as a tooltip, a name only)" "$(echo "$HT" | grep -ac 'title="Confirmed by Simon Mwangi"')" "1"

echo "== Gift settings and history (super admin only)"
for who in tutor tutor2 student admin employee intern; do c=$(code "$T/c_$who" $GP); t "gifts page: $who is refused (got $c)" "$([ "$c" = "200" ] && echo 200 || echo refused)" "refused"; done
t "gifts page: signed out is refused" "$([ "$(code $NONE $GP)" = "200" ] && echo 200 || echo refused)" "refused"
t "gifts page: the super admin opens it" "$(code "$T/c_super" $GP)" "200"
GPG=$(body "$T/c_super" $GP | tr -d '\r' | tr '\n' ' ')
t "history: the past birthday with received of expected, the KES totals, and who had not sent (names only)" "$(echo "$GPG" | grep -ac -E "Tina Otieno · .*2 of $N received · KES 3,000 of KES $TOTK · KES 1,500 each.*Had not sent:")" "1"
SETP=/superadmin/staffcalendar/settings.php
TOKG=$(token "$T/c_super" "$SETP?tab=gifts")
gp() { curl -s -b "$T/c_super" -o /dev/null -w '%{http_code}' -d "csrf_token=$TOKG" -d 'tab=gifts' "$B$SETP" "$@"; }
t "settings: a tutor cannot change the amount or exclude anyone" "$(curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&op=gift_amount&amount=9999" "$B$SETP"; curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&op=gift_exclude&user_id=$T2ID&note=x" "$B$SETP"; q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='gift_amount'")|$(q "SELECT COUNT(*) FROM staff_calendar_gift_exclusions WHERE user_id=$T2ID")" "1500|0"
t "settings: no security token changes nothing" "$(curl -s -b "$T/c_super" -o /dev/null -d "op=gift_amount&amount=7777" "$B$SETP"; q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='gift_amount'")" "1500"
gp -d 'op=gift_amount' -d 'amount=abc' >/dev/null; gp -d 'op=gift_amount' -d 'amount=50' >/dev/null
t "settings: a bad amount is refused" "$(q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='gift_amount'")" "1500"
gp -d 'op=gift_amount' -d 'amount=1800' >/dev/null
t "settings: the super admin changes the amount, and the list already made keeps KES 1,500" "$(q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='gift_amount'")|$(q "SELECT amount FROM staff_calendar_gift_lists WHERE id=$LID")" "1800|1500"
gp -d 'op=gift_exclude' -d "user_id=$T2ID" --data-urlencode 'note=on leave <b>x</b>' >/dev/null
t "settings: the super admin excludes a person with a note (stored; shown escaped)" "$(q "SELECT CONCAT(is_excluded,':',note) FROM staff_calendar_gift_exclusions WHERE user_id=$T2ID")|$(body "$T/c_super" "$SETP?tab=gifts" | grep -ac '<b>x</b>')" "1:on leave <b>x</b>|0"
t "settings: the exclusion does not change the list already made" "$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "received"
gp -d 'op=gift_amount' -d 'amount=1500' >/dev/null; gp -d 'op=gift_unexclude' -d "user_id=$T2ID" >/dev/null
t "settings: put back to 1,500 and not excluded" "$(q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='gift_amount'")|$(q "SELECT is_excluded FROM staff_calendar_gift_exclusions WHERE user_id=$T2ID")" "1500|0"

echo "== After 30 days the list is read-only history"
q "UPDATE staff_calendar_gift_lists SET birthday_date = DATE_SUB(birthday_date, INTERVAL 40 DAY), last_reminder_on = NULL WHERE id=$LID" >/dev/null
R=$(gj "$T/c_tutor" $TA "$TOK" gift_remind -F "list=$LID")
t "closed: the reminder is refused (422)" "$(echo "$R" | tail -1)" "422"
q "UPDATE staff_calendar_gift_items SET status='not_yet' WHERE list_id=$LID AND giver_user_id=$T2ID" >/dev/null
R=$(gj "$T/c_tutor2" $TA "$TOK2" gift_send -F "list=$LID")
t "closed: I have sent is refused (422) and the row stays Not yet" "$(echo "$R" | tail -1)|$(q "SELECT status FROM staff_calendar_gift_items WHERE list_id=$LID AND giver_user_id=$T2ID")" "422|not_yet"
t "closed: the page no longer shows Tina's list or card" "$(body "$T/c_tutor" "$TP" | grep -ac 'Only you and the super admin see this list')|$(body "$T/c_tutor2" "$TP" | grep -ac -E "data-list=\"$LID\"")" "0|0"
t "closed: the history shows it as read-only history" "$(body "$T/c_super" $GP | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'Tina Otieno · [^<]*</b><span class="gh-pill x">Read-only history')" "1"
t "phones: the gift rows and buttons have full-width rules" "$(body "$T/c_tutor" "$TP" | grep -ac -E '\.sg-btn\{width:100%')" "1"
q "DELETE FROM staff_calendar_birthdays WHERE user_id IN ($TID, $T2ID)" >/dev/null

echo "== Projects"
PJ=/tutor/staffcalendar/project.php; PJS=/superadmin/staffcalendar/project.php
SID=$(jid2 super id); T2ID=$(jid2 tutor2 id); TID=$(jid2 tutor id)
TOK=$(token "$T/c_tutor" $TP); TOK2=$(token "$T/c_tutor2" $TP); TOKS=$(token "$T/c_super" $SP)
TASKS='[{"id":0,"t":"Task A","who":'$T2ID',"start":"2026-11-19","due":"2026-11-20","status":"done"},{"id":0,"t":"Task B","who":'$SID',"start":"","due":"2026-11-24","status":"todo"},{"id":0,"t":"Task C","who":'$TID',"start":"","due":"","status":"in_progress"}]'
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=HTTP project <b>x</b>' -F 'type=project' -F 'start_date=2026-11-19' -F 'end_date=2026-11-25' -F 'all_day=1' -F 'repeat=weekly' -F 'private=1' -F "tasks=$TASKS" -F 'checklist=[{"t":"ignored","d":false}]')
PID=$(q "SELECT id FROM staff_calendar_events WHERE type_key='project' AND owner_user_id=$TID ORDER BY id DESC LIMIT 1")
t "project: made from the New event form (clean JSON)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; print(json.load(sys.stdin)['ok'])")" "200|True"
t "project: all day, never repeats, never private (even though the form said so)" "$(q "SELECT CONCAT(all_day,repeat_rule,is_private) FROM staff_calendar_events WHERE id=$PID")" "1none0"
t "project: three tasks saved; the simple checklist was ignored; pending notices recorded, none sent" "$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$PID")|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$PID AND label='ignored'")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id=$PID AND kind='assigned'")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id=$PID AND kind='due_before'")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id=$PID AND sent_at IS NOT NULL")" "3|0|3|1|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'form_v=2' -F 'title=No end' -F 'type=project' -F 'start_date=2026-12-02' -F 'repeat=none')
t "project: a project without an end date is refused (422)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='No end'")" "422|0"

echo "== Projects on the calendar"
c1() { body "$1" "$2" | grep -a "data-date=\"$3\"" -A4 | grep -ac "$4"; }
M2="$TP?d=2026-11-19"
t "bar: starts on the first day with the title (Thu 19), links to the project page" "$(yes "$(c1 "$T/c_tutor2" "$M2" 2026-11-19 'sc-chip sc-pbar pb-start pb-rs')")|$(yes "$(body "$T/c_tutor2" "$M2" | grep -ac "sc-pbar[^>]*href=\"[^\"]*project.php?id=$PID\"")")" "yes|yes"
t "bar: a middle day (Fri 20) is a plain continuation with no text" "$(c1 "$T/c_tutor2" "$M2" 2026-11-20 'class="sc-chip sc-pbar"')|$(body "$T/c_tutor2" "$M2" | grep -a 'data-date="2026-11-20"' -A4 | grep -ac 'pb-t')" "1|0"
t "bar: it splits at the end of the week row (Sun 22 ends the row and shows the progress), and carries on Mon 23" "$(yes "$(c1 "$T/c_tutor2" "$M2" 2026-11-22 'sc-chip sc-pbar pb-re')")|$(yes "$(c1 "$T/c_tutor2" "$M2" 2026-11-23 'sc-chip sc-pbar pb-rs')")|$(body "$T/c_tutor2" "$M2" | grep -a 'data-date="2026-11-22"' -A8 | grep -ac 'pb-p">33%')" "yes|yes|1"
t "bar: the last day (Wed 25) closes the bar with the percentage; Thu 26 has none" "$(yes "$(c1 "$T/c_tutor2" "$M2" 2026-11-25 'pb-end pb-re')")|$(c1 "$T/c_tutor2" "$M2" 2026-11-26 'sc-pbar')" "yes|0"
t "bar: it is a Project type item (the Project tick box hides it) and the title is escaped" "$(yes "$(body "$T/c_tutor2" "$M2" | grep -ac 'sc-pbar[^>]*data-type="project"')")|$(body "$T/c_tutor2" "$M2" | grep -ac '<b>x</b>')" "yes|0"
t "bar: dark charcoal, rounded at both ends (rules present)" "$(body "$T/c_tutor2" "$M2" | grep -ac -E '\.sc-pbar\{display:flex.*#373435')" "1"
t "bar: Week view (Thu 19 - Sun 22 are bars), Day view and Agenda show it as a row that links to the page" "$(yes "$(body "$T/c_tutor2" "$TP?view=week&d=2026-11-19" | grep -ac 'sc-pbar')")|$(yes "$(body "$T/c_tutor2" "$TP?view=day&d=2026-11-19" | grep -ac "sc-prow[^>]*href=\"[^\"]*project.php?id=$PID\"")")|$(yes "$(body "$T/c_tutor2" "$TP?view=agenda&d=2026-11-19" | grep -ac "sc-prow[^>]*href=\"[^\"]*project.php?id=$PID\"")")" "yes|yes|yes"
t "bar: a search for the title finds the project as a link" "$(yes "$(body "$T/c_tutor2" "$TP?q=HTTP+project" | grep -ac "sc-prow")")" "yes"
t "bar: the Project tick box is under Show on calendar" "$(body "$T/c_tutor2" "$M2" | grep -ac 'class="sc-filter" value="project"')" "1"
t "bar: Day view shows the project row before timed events (all-day first)" "$(body "$T/c_tutor2" "$TP?view=day&d=2026-11-19" | grep -a -o 'sc-prow\|class="sc-row"' | head -1)" "sc-prow"
H=$(body "$T/c_tutor" "$TP?d=2026-11-19" | tr -d '\r' | tr '\n' ' ')
t "form: the task list panel, the project-mode switches and More details routing are on the page" "$(echo "$H" | grep -ac 'id="scf-tk"')|$(echo "$H" | grep -ac 'name="tasks"')|$(echo "$H" | grep -ac 'PJURL')|$(echo "$H" | grep -ac 'Each person is notified when a task is given to them, and again the day before it is due.')" "1|1|1|1"
t "form: the Edit button of the project page opens the form (?edit=)" "$(body "$T/c_tutor" "$TP?d=2026-11-19&edit=$PID" | grep -ac "AUTOEDIT = $PID")" "1"
CARD=$(curl -s -b "$T/c_tutor" "$B/tutor$EV?id=$PID&d=2026-11-19&full=1")
t "card: it tells the type is project (More details goes to the page) and gives the tasks with ids to the owner" "$(echo "$CARD" | python -c "import json,sys; d=json.load(sys.stdin); print(d['type'], len(d['edit']['tasks']), all(t['id']>0 for t in d['edit']['tasks']))")" "project 3 True"
t "card: another tutor gets no edit values" "$(curl -s -b "$T/c_tutor2" "$B/tutor$EV?id=$PID&d=2026-11-19&full=1" | python -c "import json,sys; print(json.load(sys.stdin)['edit'])")" "None"

echo "== The project page"
PG=$(body "$T/c_tutor2" "$PJ?id=$PID" | tr -d '\r' | tr '\n' ' ')
t "page: opens for another tutor (a project is visible to everyone)" "$(code "$T/c_tutor2" "$PJ?id=$PID")" "200"
t "page: the dark Project pill, the title (escaped), the dates and the owner by name" "$(echo "$PG" | grep -ac -E 'class="pj-pillp"><i class="fas fa-diagram-project"></i> Project</span>HTTP project &lt;b&gt;x&lt;/b&gt;')|$(echo "$PG" | grep -ac 'Thu 19 Nov to Wed 25 Nov · Owner: Tina Otieno')|$(echo "$PG" | grep -ac '<b>x</b>')" "1|1|0"
t "page: the ring and 1 of 3 tasks done (33%), On track, and the progress line" "$(echo "$PG" | grep -ac -E '<b>33%</b>')|$(echo "$PG" | grep -ac '1 of 3 tasks done')|$(echo "$PG" | grep -ac 'pj-state ok">On track')|$(echo "$PG" | grep -ac 'Progress is worked out from the ticked checklist items.')" "1|1|1|1"
t "page: the Timeline with a column per day and where today is" "$(echo "$PG" | grep -ao 'class="pj-th' | wc -l | tr -d ' ')|$(echo "$PG" | grep -ac -E 'Timeline</h2><span>Today is [A-Z][a-z]{2} [0-9]+ [A-Z][a-z]{2}, ')" "7|1"
t "page: a bar per task, coloured, Task A done is faded" "$(echo "$PG" | grep -ao 'class="pj-bar' | wc -l | tr -d ' ')|$(echo "$PG" | grep -ac 'pj-bar done')" "3|1"
t "page: the Checklist with 3 rows, the note, status pills and the first name" "$(echo "$PG" | grep -ao 'class="pj-task"' | wc -l | tr -d ' ')|$(echo "$PG" | grep -ac 'Each person is notified when a task is given to them, and again the day before it is due.')|$(echo "$PG" | grep -ac 'class="pj-pill st-todo">To do')" "3|1|1"
t "page: the side boxes On the calendar it looks like, People, Added by (a name only, with the date and time)" "$(echo "$PG" | grep -ac 'On the calendar it looks like')|$(echo "$PG" | grep -ac -E 'Added by</h3><div class="pj-who">.*<b>Tina Otieno</b><small>[0-9]+ [A-Z][a-z]{2} [0-9]{4}, [0-9]+:[0-9]{2} (am|pm)</small>')|$(echo "$PG" | grep -ac -i -E 'Added by</h3>.{0,400}(owner|super ?admin|tutor)<')" "1|1|0"
t "page: People lists each person with how many tasks" "$(echo "$PG" | grep -ao -E '<small>[0-9]+ tasks?</small>' | wc -l | tr -d ' ')" "3"
t "page: another tutor only views: no Add task, no Edit, no pencil; can tick only the task given to them" "$(echo "$PG" | grep -ac 'id="pj-add"')|$(echo "$PG" | grep -ac 'class="pj-ic pj-edit"')|$(echo "$PG" | grep -ao 'class="pj-ck"[^>]*checked[^>]* *>' | grep -vc disabled)|$(echo "$PG" | grep -ao '<input type="checkbox" class="pj-ck"[^>]*disabled>' | wc -l | tr -d ' ')|$(echo "$PG" | grep -ao 'class="pj-st st-' | wc -l | tr -d ' ')" "0|0|1|2|1"
PO=$(body "$T/c_tutor" "$PJ?id=$PID" | tr -d '\r' | tr '\n' ' ')
t "page: the owner has Add task, Edit (opens the form) and Open in calendar, a pencil on each task and a status on each" "$(echo "$PO" | grep -ac 'id="pj-add"')|$(echo "$PO" | grep -ac "edit=$PID")|$(echo "$PO" | grep -ac 'Open in calendar')|$(echo "$PO" | grep -ao 'class="pj-ic pj-edit"' | wc -l | tr -d ' ')|$(echo "$PO" | grep -ao 'class="pj-st st-' | wc -l | tr -d ' ')" "1|1|1|3|3"
t "page: the super admin can manage it too (in the super admin portal)" "$(code "$T/c_super" "$PJS?id=$PID")|$(body "$T/c_super" "$PJS?id=$PID" | grep -ac 'id="pj-add"')" "200|1"
for who in student admin employee intern; do for P in $PJ $PJS; do c=$(code "$T/c_$who" "$P?id=$PID"); t "page: $who is refused on $P (got $c)" "$([ "$c" = "200" ] && echo 200 || echo refused)" "refused"; done; done
t "page: signed out is refused; a tutor cannot use the super admin address" "$([ "$(code $NONE "$PJ?id=$PID")" = "200" ] && echo 200 || echo refused)|$([ "$(code "$T/c_tutor" "$PJS?id=$PID")" = "200" ] && echo 200 || echo refused)" "refused|refused"
t "page: an event that is not a project, or does not exist, gives 404" "$(code "$T/c_tutor" "$PJ?id=$SEED")|$(code "$T/c_tutor" "$PJ?id=99999999")|$(code "$T/c_tutor" "$PJ")" "404|404|404"
t "page: single column and stacked task rows on phones (the rules are there)" "$(echo "$PG" | grep -ac -E 'grid-template-areas:"ck tt ac"')" "1"

echo "== Project actions: the server checks who is asking"
TA_ID=$(q "SELECT id FROM staff_calendar_checklist WHERE event_id=$PID AND label='Task A'"); TB_ID=$(q "SELECT id FROM staff_calendar_checklist WHERE event_id=$PID AND label='Task B'")
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=task_status' -F "event=$PID" -F "task=$TA_ID" -F 'status=todo' -F "return_project=$PID")
t "status: the person given a task changes it (clean JSON, goes back to the project page)" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'], d['go'].endswith('project.php?id=$PID'))")|$(q "SELECT CONCAT(status,is_done) FROM staff_calendar_checklist WHERE id=$TA_ID")" "200|True True|todo0"
t "status: ... and the day-before notice comes back for a task that is not done" "$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE task_id=$TA_ID AND kind='due_before'")" "1"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=task_status' -F "event=$PID" -F "task=$TB_ID" -F 'status=done')
t "status: a task given to someone else cannot be changed (403)" "$(echo "$R" | tail -1)|$(q "SELECT status FROM staff_calendar_checklist WHERE id=$TB_ID")" "403|todo"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=tick' -F "id=$PID" -F "item=$TB_ID" -F 'done=1')
t "tick: the details panel tick follows the same rule for a project task (not theirs: refused)" "$(q "SELECT status FROM staff_calendar_checklist WHERE id=$TB_ID")" "todo"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=task_save' -F "event=$PID" -F "task=$TA_ID" -F 'title=Renamed' -F "who=$T2ID" -F 'status=done')
t "edit: the person given the task cannot change its title (403)" "$(echo "$R" | tail -1)|$(q "SELECT label FROM staff_calendar_checklist WHERE id=$TA_ID")" "403|Task A"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=task_save' -F "event=$PID" -F 'title=Hack' -F "who=$T2ID")
R2=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F 'op=task_delete' -F "event=$PID" -F "task=$TA_ID")
t "add and delete: another tutor cannot (403) and nothing changes" "$(echo "$R" | tail -1)|$(echo "$R2" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$PID")" "403|403|3"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_save' -F "event=$PID" -F 'title=Task D' -F "who=$T2ID" -F 'start=2026-11-21' -F 'due=2026-11-24' -F 'status=todo')
TD_ID=$(q "SELECT id FROM staff_calendar_checklist WHERE event_id=$PID AND label='Task D'")
t "add: the owner adds a task and gives it to someone; a pending assigned notice and a day-before notice (23 Nov) are recorded" "$(echo "$R" | tail -1)|$(q "SELECT GROUP_CONCAT(CONCAT(kind,':',to_user_id,':',COALESCE(send_on,'')) ORDER BY id SEPARATOR ',') FROM staff_calendar_task_notices WHERE task_id=$TD_ID")" "200|assigned:$T2ID:$(date +%Y-%m-%d),due_before:$T2ID:2026-11-23"
R=$(ajax "$T/c_super" $SA -F "csrf_token=$TOKS" -F 'op=task_save' -F "event=$PID" -F "task=$TD_ID" -F 'title=Task D' -F "who=$SID" -F 'start=2026-11-21' -F 'due=2026-11-24' -F 'status=todo')
t "edit: the super admin gives it to someone else: the first person's waiting notices go, the new person gets both" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE task_id=$TD_ID AND to_user_id=$T2ID")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE task_id=$TD_ID AND to_user_id=$SID")" "200|0|2"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_save' -F "event=$PID" -F 'title=Bad person' -F "who=$(jid2 student id)")
t "add: a person who is not a tutor or the super admin is refused (422)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE label='Bad person'")" "422|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_save' -F "event=$PID" -F 'title=Bad dates' -F 'start=2026-11-24' -F 'due=2026-11-20')
t "add: a task that starts after it is due is refused (422)" "$(echo "$R" | tail -1)" "422"
R=$(ajax "$T/c_tutor" $TA -F 'op=task_save' -F "event=$PID" -F 'title=No token')
t "add: no security token is refused (403)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE label='No token'")" "403|0"
for who in student admin employee intern; do R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=task_save' -F "event=$PID" -F 'title=Sneaky'); R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=task_status' -F "event=$PID" -F "task=$TB_ID" -F 'status=done'); done
t "actions: a student, admin, employee or intern changes nothing" "$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE label='Sneaky'")|$(q "SELECT status FROM staff_calendar_checklist WHERE id=$TB_ID")" "0|todo"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_status' -F "event=$SEED" -F "task=$TB_ID" -F 'status=done')
t "actions: a task cannot be changed through another event (404)" "$(echo "$R" | tail -1)|$(q "SELECT status FROM staff_calendar_checklist WHERE id=$TB_ID")" "404|todo"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_delete' -F "event=$PID" -F "task=$TD_ID")
t "delete: the owner deletes a task, its notices go with it" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE id=$TD_ID")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE task_id=$TD_ID")" "200|0|0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_status' -F "event=$PID" -F "task=$TB_ID" -F 'status=done')
for tid in $TA_ID; do R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_status' -F "event=$PID" -F "task=$tid" -F 'status=done'); done
TC_ID=$(q "SELECT id FROM staff_calendar_checklist WHERE event_id=$PID AND label='Task C'")
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=task_status' -F "event=$PID" -F "task=$TC_ID" -F 'status=done')
t "progress: every task done shows Done and 100% on the page and on the bar" "$(body "$T/c_tutor2" "$PJ?id=$PID" | grep -ac 'pj-state done">Done')|$(body "$T/c_tutor2" "$PJ?id=$PID" | grep -ac '3 of 3 tasks done')|$(yes "$(body "$T/c_tutor2" "$M2" | grep -ac 'pb-p">100%')")" "1|1|yes"
q "UPDATE staff_calendar_checklist SET status='todo', is_done=0 WHERE id=$TB_ID" >/dev/null; q "UPDATE staff_calendar_checklist SET due_date='2026-01-05' WHERE id=$TB_ID" >/dev/null
t "progress: an unfinished task past its due date shows Behind in red" "$(body "$T/c_tutor2" "$PJ?id=$PID" | grep -ac 'pj-state bad">Behind')" "1"
R=$(ajax "$T/c_tutor2" $TA -F "csrf_token=$TOK2" -F "op=delete" -F "id=$PID")
t "delete the project: another tutor cannot (403)" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$PID")" "403|1"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F "op=delete" -F "id=$PID")
t "delete the project: the owner can, its tasks and notices are removed" "$(echo "$R" | tail -1)|$(q "SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id=$PID")|$(q "SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id=$PID")" "200|0|0"

echo "== Settings · Staff Calendar (super admin only)"
SETP=/superadmin/staffcalendar/settings.php
HS=$(body "$T/c_super" "$SP" | tr -d '\r' | tr '\n' ' '); HT=$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ')
t "gear: the super admin has the small gear button in the header; a tutor does not" "$(echo "$HS" | grep -ac 'class="sc-gear" href="[^"]*superadmin/staffcalendar/settings.php"')|$(echo "$HT" | grep -ac 'class="sc-gear"')|$(echo "$HT" | grep -ac 'staffcalendar/settings.php')" "1|0|0"
t "settings: the super admin opens it, titled Settings · Staff Calendar, with the five tabs" "$(code "$T/c_super" $SETP)|$(body "$T/c_super" $SETP | tr -d '\r' | tr '\n' ' ' | grep -ac -E 'Settings · Staff Calendar</h1>.*>Event types</a><a[^>]*>Birthday messages</a><a[^>]*>Holidays</a><a[^>]*>Gifts</a><a[^>]*>Notifications</a>')" "200|1"
for who in tutor tutor2 student admin employee intern; do c=$(code "$T/c_$who" $SETP); t "settings: $who is refused (got $c)" "$([ "$c" = "200" ] && echo 200 || echo refused)" "refused"; done
t "settings: signed out is refused; a tutor sees nothing of it" "$([ "$(code $NONE $SETP)" = "200" ] && echo 200 || echo refused)|$(body "$T/c_tutor" $SETP | grep -ac -E 'st-card|Event types')" "refused|0"
t "settings: the old Birthday messages address sends the super admin to the tab (filters kept); a tutor is refused" "$(curl -s -b "$T/c_super" -o /dev/null -w '%{http_code} %{redirect_url}' "$B/superadmin/staffcalendar/messages.php?tag=Sheng" | sed "s#$B##")|$([ "$(code "$T/c_tutor" /superadmin/staffcalendar/messages.php)" = "200" ] && echo 200 || echo refused)" "302 /superadmin/staffcalendar/settings.php?tab=messages&tag=Sheng|refused"
t "settings: the Birthdays coming up box opens the Birthday messages tab" "$(echo "$HS" | grep -ac 'href="[^"]*settings.php?tab=messages">Birthday messages')" "1"
ST() { body "$T/c_super" "$SETP?tab=$1$2" | tr -d '\r' | tr '\n' ' '; }
TY=$(ST types)
t "tab Event types: every type with a colour box and an on/off switch, an Add type form" "$(echo "$TY" | grep -ao 'class="st-trow' | wc -l | tr -d ' ')|$(echo "$TY" | grep -ao 'role="switch"' | wc -l | tr -d ' ')|$(echo "$TY" | grep -ac 'Add type')|$(echo "$TY" | grep -ac 'type="color" name="color" value="#048C44"')" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types")|$(q "SELECT COUNT(*) FROM staff_calendar_event_types")|1|1"
t "tab Birthday messages: the one library (the same list, tag filter, add form and counts)" "$(ST messages | grep -ac 'messages in use')|$(ST messages | grep -ao 'class="bm-tab' | wc -l | tr -d ' ')|$(ST messages | grep -ac 'Used [0-9]* times\?\|Not used yet')" "1|10|1"
HO=$(ST holidays)
t "tab Holidays: the year switch (2025 to 2034), each date and name, Eid expected to be confirmed, Mazingira Day" "$(echo "$HO" | grep -ao 'class="st-years".*</div></div>' | grep -ao '>20[0-9][0-9]</a>' | wc -l | tr -d ' ')|$(echo "$HO" | grep -ac 'Mazingira Day')|$(echo "$HO" | grep -ac 'Expected, to be confirmed')|$(echo "$HO" | grep -ac 'Jamhuri Day')" "10|1|1|1"
t "tab Holidays: the Sunday rule shows with a note (2027)" "$(ST holidays '&year=2027' | grep -ac 'Moved from Sunday 10 Oct')" "1"
GF=$(ST gifts)
t "tab Gifts: the amount, the fixed 'Once a day' (disabled), the excluded list and Exclude someone" "$(echo "$GF" | grep -ac 'Amount from each person (KES)')|$(echo "$GF" | grep -ac -E '<select id="gr" disabled[^>]*><option>Once a day</option>')|$(echo "$GF" | grep -ac 'Excluded from gifts')|$(echo "$GF" | grep -ac 'Exclude someone…')|$(echo "$GF" | grep -ac 'name="amount"[^>]*value="1500"')" "1|1|1|1|1"
NT=$(ST notifications)
t "tab Notifications: the six team switches, all on, saved only for now" "$(echo "$NT" | grep -ao 'class="st-sw"' | wc -l | tr -d ' ')|$(echo "$NT" | grep -ao 'name="[a-z_]*" value="1" checked' | wc -l | tr -d ' ')|$(echo "$NT" | grep -ac 'only saved for now')|$(echo "$NT" | grep -ac 'The birthday notice')|$(echo "$NT" | grep -ac 'The gift reminder')" "6|6|1|1|1"
t "settings: single-column and sideways-scrolling tabs on phones (the rules are there)" "$(echo "$TY" | grep -ac -E 'overflow-x:auto.*max-width:767.98px')" "1"

echo "== Settings: who may change what, event types"
TOKST=$(token "$T/c_super" $SETP)
sp() { curl -s -b "$T/c_super" -o /dev/null -w '%{http_code}' -d "csrf_token=$TOKST" "$B$SETP" "$@"; }
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&tab=types&op=type_add&name=Hacked&color=%23112233" "$B$SETP"; curl -s -b "$T/c_super" -o /dev/null -d "tab=types&op=type_add&name=Forged&color=%23112233" "$B$SETP"
t "types: a tutor posting, or a post without the security token, changes nothing" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE label IN ('Hacked','Forged')")" "0"
sp -d tab=types -d op=type_add -d 'name=HTTP workshop' -d 'color=#336699' >/dev/null
t "types: the super admin adds a type with a name and a colour" "$(q "SELECT CONCAT(type_key,':',color,':',is_active) FROM staff_calendar_event_types WHERE label='HTTP workshop'")" "http_workshop:#336699:1"
sp -d tab=types -d op=type_add -d 'name=http WORKSHOP' -d 'color=#336699' >/dev/null; sp -d tab=types -d op=type_add -d 'name=Bad colour' -d 'color=blue' >/dev/null
t "types: a duplicate name (any capitals) and a bad colour are refused" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE LOWER(label)='http workshop'")|$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE label='Bad colour'")" "1|0"
TOKH=$(token "$T/c_tutor" $TP)
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOKH" -F 'form_v=2' -F 'title=HTTP workshop event' -F 'type=http_workshop' -F 'start_date=2026-12-09' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none')
WID=$(q "SELECT id FROM staff_calendar_events WHERE title='HTTP workshop event' ORDER BY id DESC LIMIT 1")
t "types: the new type is in the New event form and the Show on calendar filter; an event of it shows on the calendar" "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac 'name="type" value="http_workshop"')|$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac 'class="sc-filter" value="http_workshop"')|$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac 'HTTP workshop event')")" "1|1|yes"
sp -d tab=types -d op=type_delete -d key=http_workshop >/dev/null
t "types: a type with events cannot be deleted" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE type_key='http_workshop'")" "1"
sp -d tab=types -d op=type_active -d key=http_workshop -d on=0 >/dev/null
t "types: switched off, its events are hidden everywhere (month, search, card) but still in the database" "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac 'HTTP workshop event')|$(body "$T/c_tutor" "$TP?q=HTTP+workshop+event" | grep -ac 'sc-rn">HTTP workshop event')|$(code "$T/c_tutor" "/tutor$EV?id=$WID&d=2026-12-09")|$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$WID")" "0|0|403|1"
t "types: ...and the type is gone from the New event form and the filter" "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac -E 'name="type" value="http_workshop"|sc-filter" value="http_workshop"')" "0"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOKH" -F 'form_v=2' -F 'title=HTTP off type' -F 'type=http_workshop' -F 'start_date=2026-12-10' -F 'start_time=10:00' -F 'end_time=11:00' -F 'repeat=none')
t "types: a new event of a type that is off is refused (422)" "$(echo "$R" | tail -1)" "422"
sp -d tab=types -d op=type_active -d key=http_workshop -d on=1 >/dev/null; sp -d tab=types -d op=type_color -d key=http_workshop -d 'color=#aa3366' >/dev/null
t "types: switched on, the events are back, in the new colour" "$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac 'HTTP workshop event')")|$(q "SELECT color FROM staff_calendar_event_types WHERE type_key='http_workshop'")|$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-09" | grep -ac -i 'style="--c:#aa3366')")" "yes|#AA3366|yes"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOKH" -F "op=delete" -F "id=$WID"); sp -d tab=types -d op=type_delete -d key=http_workshop >/dev/null
t "types: with no events left it can be deleted" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE type_key='http_workshop'")" "0"
sp -d tab=types -d op=type_delete -d key=project >/dev/null; sp -d tab=types -d op=type_delete -d key=birthday >/dev/null
t "types: Project and Birthday can never be deleted" "$(q "SELECT COUNT(*) FROM staff_calendar_event_types WHERE type_key IN ('project','birthday')")" "2"

echo "== Holidays on the calendar and in the tab"
c2() { body "$1" "$2" | grep -a "data-date=\"$3\"" -A4 | grep -ac "$4"; }
t "chips: teal all-day holiday chips on the right days (Jamhuri 12 Dec, Christmas 25 Dec, Boxing Day 26 Dec 2026)" "$(c2 "$T/c_tutor" "$TP?d=2026-12-01" 2026-12-12 'sc-chip sc-hol')|$(c2 "$T/c_tutor" "$TP?d=2026-12-01" 2026-12-25 'Christmas Day')|$(c2 "$T/c_tutor" "$TP?d=2026-12-01" 2026-12-26 'Boxing Day')" "1|1|1"
t "chips: the colour is the Holiday type colour (teal), they are not events (no data-ev) and carry data-type holiday for the tick box" "$(body "$T/c_tutor" "$TP?d=2026-12-01" | grep -ao '<span class="sc-chip sc-hol" data-type="holiday" style="--c:#1A9A9A[^>]*>' | head -1 | grep -c 'data-ev')|$(yes "$(body "$T/c_tutor" "$TP?d=2026-12-01" | grep -ac 'sc-hol" data-type="holiday" style="--c:#1A9A9A')")|$(body "$T/c_tutor" "$TP?d=2026-12-01" | grep -ac 'class="sc-filter" value="holiday"')" "0|yes|1"
t "chips: a Sunday holiday sits on the Monday (Mazingira Day 2027 on Mon 11 Oct, not Sun 10 Oct)" "$(c2 "$T/c_tutor" "$TP?d=2027-10-01" 2027-10-11 'Mazingira Day')|$(c2 "$T/c_tutor" "$TP?d=2027-10-01" 2027-10-10 'Mazingira Day')" "1|0"
t "chips: Eid ul-Fitr is drawn as expected until it is confirmed" "$(body "$T/c_tutor" "$TP?d=2026-03-10" | grep -a 'data-date="2026-03-20"' -A4 | grep -ac 'Eid ul-Fitr (expected)')|$(body "$T/c_tutor" "$TP?d=2026-03-10" | grep -ac 'title="Eid ul-Fitr · Expected, to be confirmed"')" "1|1"
t "chips: holidays show in the Day and Agenda views as rows, and a click on them does not open the New event form (no events)" "$(yes "$(body "$T/c_tutor" "$TP?view=day&d=2026-12-12" | grep -ac 'Public holiday')")|$(yes "$(body "$T/c_tutor" "$TP?view=agenda&d=2026-12-01" | grep -ac 'Jamhuri Day')")" "yes|yes"
NH=$(q "SELECT CONCAT(name,'|',DATEDIFF(holiday_date, CURDATE())) FROM staff_calendar_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date, name LIMIT 1")
NHN=${NH%|*}; NHD=${NH#*|}; if [ "$NHD" = "0" ]; then PH=today; elif [ "$NHD" = "1" ]; then PH=tomorrow; else PH="in $NHD days"; fi
t "next holiday: the side box shows the next holiday with in X days" "$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ' | grep -ac "Next holiday</h4>.*<b>$NHN</b><small>[^<]* · $PH</small>")" "1"
sp -d tab=types -d op=type_active -d key=holiday -d on=0 >/dev/null
t "holiday tick box: with the Holiday type off the chips are gone; on again they are back" "$(c2 "$T/c_tutor" "$TP?d=2026-12-01" 2026-12-12 'sc-hol')" "0"
sp -d tab=types -d op=type_active -d key=holiday -d on=1 >/dev/null
t "holiday type back on" "$(c2 "$T/c_tutor" "$TP?d=2026-12-01" 2026-12-12 'sc-hol')" "1"
EID=$(q "SELECT id FROM staff_calendar_holidays WHERE source_key='eid_ul_fitr:2026'")
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&tab=holidays&op=hol_confirm&id=$EID&date=2026-03-21" "$B$SETP"
t "holidays: a tutor cannot confirm or change a holiday" "$(q "SELECT CONCAT(holiday_date,is_confirmed) FROM staff_calendar_holidays WHERE id=$EID")" "2026-03-200"
sp -d tab=holidays -d op=hol_confirm -d "id=$EID" -d date=2026-03-21 -d year=2026 >/dev/null
t "holidays: the super admin confirms Eid: the date is set, no longer expected, the chip moves to 21 Mar" "$(q "SELECT CONCAT(holiday_date,is_confirmed) FROM staff_calendar_holidays WHERE id=$EID")|$(c2 "$T/c_tutor" "$TP?d=2026-03-10" 2026-03-21 'Eid ul-Fitr')|$(c2 "$T/c_tutor" "$TP?d=2026-03-10" 2026-03-20 'Eid ul-Fitr')|$(ST holidays | grep -ac 'Expected, to be confirmed')" "2026-03-211|1|0|0"
q "UPDATE staff_calendar_holidays SET holiday_date='2026-03-20', is_confirmed=0, note='' WHERE id=$EID" >/dev/null
sp -d tab=holidays -d op=hol_add -d 'date=2026-08-08' --data-urlencode 'name=HTTP <b>day</b>' -d 'note=once' -d year=2026 >/dev/null
HID=$(q "SELECT id FROM staff_calendar_holidays WHERE name LIKE 'HTTP %day%' ORDER BY id DESC LIMIT 1")
t "holidays: the super admin adds one; it is shown escaped and appears on the calendar" "$(q "SELECT holiday_date FROM staff_calendar_holidays WHERE id=$HID")|$(ST holidays '&year=2026' | grep -ac '<b>day</b>')|$(ST holidays '&year=2026' | grep -ac '&lt;b&gt;day&lt;/b&gt;')|$(c2 "$T/c_tutor" "$TP?d=2026-08-01" 2026-08-08 'sc-hol')" "2026-08-08|0|1|1"
sp -d tab=holidays -d op=hol_edit -d "id=$HID" -d 'date=2026-08-09' -d 'name=HTTP renamed day' -d 'note=' -d year=2026 >/dev/null
t "holidays: edit" "$(q "SELECT CONCAT(holiday_date,name) FROM staff_calendar_holidays WHERE id=$HID")" "2026-08-09HTTP renamed day"
sp -d tab=holidays -d op=hol_delete -d "id=$HID" -d year=2026 >/dev/null
t "holidays: remove" "$(q "SELECT COUNT(*) FROM staff_calendar_holidays WHERE id=$HID")" "0"
sp -d tab=holidays -d op=hol_fill -d fill_year=2036 >/dev/null; N36=$(q "SELECT COUNT(*) FROM staff_calendar_holidays WHERE holiday_date BETWEEN '2036-01-01' AND '2036-12-31'"); sp -d tab=holidays -d op=hol_fill -d fill_year=2036 >/dev/null
t "holidays: Fill in a year adds that year's standard holidays once" "$([ "$N36" -ge 11 ] && echo ok)|$(q "SELECT COUNT(*) FROM staff_calendar_holidays WHERE holiday_date BETWEEN '2036-01-01' AND '2036-12-31'")" "ok|$N36"
q "DELETE FROM staff_calendar_holidays WHERE holiday_date BETWEEN '2036-01-01' AND '2036-12-31'" >/dev/null

echo "== Notifications (team defaults) and My settings"
sp -d tab=notifications -d op=team_save -d remind_on_day=1 -d notice_gift=1 >/dev/null
t "team defaults: saved as chosen (only saved, nothing sent)" "$(q "SELECT GROUP_CONCAT(CONCAT(setting_key,'=',setting_value) ORDER BY setting_key SEPARATOR ',') FROM staff_calendar_settings WHERE setting_key LIKE 'team\\_%'")" "team_notice_birthday=0,team_notice_gift=1,team_remind_day_before=0,team_remind_email=0,team_remind_hour_before=0,team_remind_on_day=1"
t "team defaults: the tab shows them, and a new event starts with those reminder switches (the form gets the defaults)" "$(ST notifications | grep -ao 'name="[a-z_]*" value="1" checked' | wc -l | tr -d ' ')|$(body "$T/c_tutor" "$TP" | grep -ac '"remind_day_before":false')" "2|1"
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&tab=notifications&op=team_save&remind_email=1" "$B$SETP"
t "team defaults: a tutor cannot change them" "$(q "SELECT setting_value FROM staff_calendar_settings WHERE setting_key='team_remind_email'")" "0"
sp -d tab=notifications -d op=team_save -d remind_day_before=1 -d remind_on_day=1 -d remind_hour_before=1 -d remind_email=1 -d notice_birthday=1 -d notice_gift=1 >/dev/null
t "team defaults: all back on" "$(q "SELECT COUNT(*) FROM staff_calendar_settings WHERE setting_key LIKE 'team\\_%' AND setting_value='1'")" "6"
myform() { body "$1" "$TP" | tr -d '\r' | tr '\n' ' ' | python -c "import sys; s=sys.stdin.read(); a=s.index('id=\"sc-myform\"'); b=s.index('</form>',a); print(s[a:b])"; }
cnt() { grep -ao 'class="scf-sw" name="[a-z_]*" value="1" checked' | wc -l | tr -d ' '; }
MYT=$(body "$T/c_tutor" "$TP" | tr -d '\r' | tr '\n' ' ')
t "My settings: a box for a tutor and for the super admin with the six switches, all on to begin with" "$(echo "$MYT" | grep -ac 'id="sc-mys"')|$(myform "$T/c_tutor" | cnt)|$(body "$T/c_super" "$SP" | grep -ac 'id="sc-mys"')" "1|6|1"
t "My settings: the six labels" "$(echo "$MYT" | grep -ac -E 'Day before, 8:00 am.*On the day, 8:00 am.*1 hour before the start.*Also send an email.*Birthday notices.*Gift reminders')" "1"
R=$(ajax "$T/c_tutor" $TA -F "csrf_token=$TOK" -F 'op=prefs_save' -F 'remind_on_day=1' -F 'notice_gift=1' -F "user_id=$T2ID")
t "My settings: a tutor saves their own switches (clean JSON), once per switch" "$(echo "$R" | tail -1)|$(echo "$R" | head -1 | python -c "import json,sys; print(json.load(sys.stdin)['ok'])")|$(q "SELECT GROUP_CONCAT(CONCAT(pref_key,'=',enabled) ORDER BY pref_key SEPARATOR ',') FROM staff_calendar_user_prefs WHERE user_id=$TID")" "200|True|notice_birthday=0,notice_gift=1,remind_day_before=0,remind_email=0,remind_hour_before=0,remind_on_day=1"
t "My settings: it only changes their own switches (another person named in the request is ignored; the other tutor still has everything on)" "$(q "SELECT COUNT(*) FROM staff_calendar_user_prefs WHERE user_id=$T2ID")|$(myform "$T/c_tutor2" | cnt)" "0|6"
t "My settings: the box then shows the saved switches" "$(myform "$T/c_tutor" | cnt)" "2"
R=$(ajax "$T/c_tutor" $TA -F 'op=prefs_save' -F 'remind_on_day=1')
t "My settings: no security token is refused (403)" "$(echo "$R" | tail -1)" "403"
for who in student admin employee intern; do R=$(ajax "$T/c_$who" $TA -F "csrf_token=$TOK" -F 'op=prefs_save' -F 'remind_on_day=1'); done
t "My settings: a student, admin, employee or intern cannot save anything" "$(q "SELECT COUNT(*) FROM staff_calendar_user_prefs WHERE user_id IN ($(jid2 student id), $(jid2 admin id), $(jid2 employee id), $(jid2 intern id))")" "0"
q "DELETE FROM staff_calendar_user_prefs WHERE user_id IN ($TID, $T2ID)" >/dev/null

echo; echo "$pass passed, $fail failed"
[ "$fail" = "0" ]
