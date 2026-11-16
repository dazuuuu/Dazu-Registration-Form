#!/usr/bin/env bash
# HTTP checks for the admin's Secretaries list and the Staff Calendar rule for secretaries. Run AFTER secretary_admin_test.php on the same scratch database, with the site served from it:
#   cd public && DAZU_DB_NAME=<scratch> MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_ENCRYPTION= MAIL_USERNAME=u MAIL_PASSWORD=p php -S localhost:8011 router.php &
#   php app/v1/tests/smtp_sink.php 2525 <dir> &      B=http://localhost:8011 SINK=<dir> bash app/v1/tests/secretary_admin_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_admin_users.json"
SINK=${SINK:-$T/smtp_sink}
PHPBIN=${PHPBIN:-php}
jv() { python -c "import json,sys; d=json.load(open(sys.argv[1]))[sys.argv[2]]; print(d['email'] if isinstance(d,dict) else d)" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
where() { curl -s -b "$1" -o /dev/null -w "%{redirect_url}" "$B$2" | sed "s#$B##;s/?.*//"; }
body() { curl -s -b "$1" "$B$2"; }
has() { echo "$2" | grep -aqF -- "$3" && echo yes || echo no; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
q() { "$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); echo $p->query($argv[1])->fetchColumn();' "$1" 2>/dev/null; }
SF=$(jv _sec_full); SV=$(jv _sec_view)
EF=$(jv full); EV=$(jv view); EO=$(jv off); ET=$(jv tutor)

S="$T/h_admin"; rm -f "$S"; curl -s -c "$S" -o /dev/null "$B/admin/portal.php"; curl -s -b "$S" -c "$S" -o /dev/null -d "email=$(jv admin)&password=x" "$B/admin/portal.php"
SUP="$T/h_super"; rm -f "$SUP"; TOKU=$(token "$SUP" /superadmin/login.php); curl -s -b "$SUP" -c "$SUP" -o /dev/null -d "email=$(jv super)&password=x&csrf_token=$TOKU" "$B/superadmin/login.php"
login "$T/h_full" "$EF"; login "$T/h_view" "$EV"; login "$T/h_off" "$EO"; login "$T/h_tutor" "$ET"

echo "== The list"
t "a secretary cannot open the list" "$(code "$T/h_full" /admin/secretaries/index.php) $(body "$T/h_full" /admin/secretaries/index.php)" "403 Not allowed."
t "a tutor cannot open the list" "$(code "$T/h_tutor" /admin/secretaries/index.php) $(body "$T/h_tutor" /admin/secretaries/index.php)" "403 Not allowed."
t "the admin opens the list" "$(code "$S" /admin/secretaries/index.php)" "200"
L=$(body "$S" /admin/secretaries/index.php)
for h in 'ID number' 'Status' 'Blog approval' 'Calendar' 'ID card' 'Enquiries' 'Actions'; do t "column: $h" "$(has x "$L" ">$h</th>")" "yes"; done
t "a row for each secretary, with the ID number" "$(echo "$L" | grep -ac 'DTI/SEC/')" "$(q "SELECT COUNT(*) FROM secretaries")"
t "initials show when there is no picture (Annette Full = AF)" "$([ $(echo "$L" | grep -ac '>AF<') -ge 1 ] && echo yes)" "yes"
t "each row has Edit, Reset password and Suspend (or Lift suspension)" "$(echo "$L" | grep -ac 'create.php?id=') $(echo "$L" | grep -ac 'Reset password</button>') $(( $(echo "$L" | grep -ac 'Suspend</button>') + $(echo "$L" | grep -ac 'Lift suspension</button>') ))" "$(q "SELECT COUNT(*) FROM secretaries") $(q "SELECT COUNT(*) FROM secretaries") $(q "SELECT COUNT(*) FROM secretaries")"
t "the calendar select offers Full, View only and Off" "$(echo "$L" | tr -d '\n' | grep -aoE 'data-key="staff_calendar"[^>]*>(\s*<option[^>]*>(Full|View only|Off)</option>){3}' | head -1 | grep -ao '>Full<\|>View only<\|>Off<' | tr '\n' ' ')" ">Full< >View only< >Off< "
t "blog approval reads Needed / Not needed" "$(has x "$L" '>Not needed<') $(has x "$L" '>Needed<')" "yes yes"
t "the three information boxes" "$(has x "$L" '<h3>Calendar levels</h3>') $(has x "$L" '<h3>Blog approval</h3>') $(has x "$L" '<h3>Never deleted</h3>')" "yes yes yes"
t "the never-deleted box says so" "$(has x "$L" 'A secretary is never deleted')" "yes"
t "phones: the table becomes cards (rule under 992px with data labels)" "$(has x "$L" 'max-width: 991.98px') $(has x "$L" 'data-label="Blog approval"')" "yes yes"
t "the switches are large enough to tap (44px+)" "$(has x "$L" 'min-height:44px')" "yes"
t "the secretary's own page names are shown as text (no HTML tags from names)" "$(echo "$L" | grep -ac '<script>alert')" "0"

echo "== Switches save at once"
TOKS=$(echo "$L" | grep -aoE 'data-csrf="[a-f0-9]+"' | head -1 | sed 's/.*="//;s/"//')
post() { curl -s -b "$S" -w ' [%{http_code}]' -d "csrf_token=$1&id=$2&key=$3&value=$4" "$B/admin/secretaries/switch.php"; }
t "GET is refused" "$(code "$S" /admin/secretaries/switch.php)" "405"
t "a secretary or a tutor cannot call it (plain 403)" "$(for w in h_full h_tutor; do curl -s -b "$T/$w" -w ' %{http_code} ' -d "csrf_token=$TOKS&id=$SF&key=id_card&value=on" "$B/admin/secretaries/switch.php"; done)" "Not allowed. 403 Not allowed. 403 "
t "the super admin cannot call it, even with a valid token (plain 403), and nothing changed" "$(curl -s -b "$SUP" -w ' %{http_code}' -d "csrf_token=$TOKS&id=$SF&key=blogs_approval&value=on" "$B/admin/secretaries/switch.php") $(q "SELECT switch_value FROM secretary_switches WHERE secretary_id=$SF AND switch_key='blogs_approval'")" "Not allowed. 403 off"
t "the super admin cannot open the list or the form either" "$(code "$SUP" /admin/secretaries/index.php) $(code "$SUP" /admin/secretaries/create.php?id=$SF) $(code "$SUP" /superadmin/secretaries/index.php)" "403 403 404"
t "no token: refused (403)" "$(post "" $SF id_card on | grep -ao '\[[0-9]*\]')" "[403]"
t "a wrong token: refused (403)" "$(post deadbeef $SF id_card on | grep -ao '\[[0-9]*\]')" "[403]"
R=$(post "$TOKS" $SF id_card on)
t "a valid change answers ok and names who changed it" "$(has x "$R" '"ok":true') $(has x "$R" '"by":"Alice Admin"') $(has x "$R" '[200]')" "yes yes yes"
t "it is in the database at once" "$(q "SELECT switch_value FROM secretary_switches WHERE secretary_id=$SF AND switch_key='id_card'")" "on"
t "the log keeps the admin's name" "$(q "SELECT changed_by_name FROM secretary_changes WHERE secretary_id=$SF AND field_key='id_card' AND new_value='on' ORDER BY id DESC LIMIT 1")" "Alice Admin"
t "and the log has who and when" "$(q "SELECT COUNT(*) FROM secretary_changes WHERE secretary_id=$SF AND field_key='id_card' AND new_value='on' AND changed_by IS NOT NULL AND changed_at IS NOT NULL")" "1"
t "a value that is not allowed is refused (422)" "$(post "$TOKS" $SF staff_calendar everything | grep -ao '\[[0-9]*\]')" "[422]"
t "an unknown switch is refused (422)" "$(post "$TOKS" $SF admin_rights on | grep -ao '\[[0-9]*\]')" "[422]"
t "an unknown secretary is a 404" "$(post "$TOKS" 999999 id_card on | grep -ao '\[[0-9]*\]')" "[404]"
t "the list shows the new value after reload" "$(body "$S" /admin/secretaries/index.php | tr -d '\n' | grep -aoE 'data-key="id_card"[^>]*checked' | wc -l | tr -d ' ')" "$(q "SELECT COUNT(*) FROM secretary_switches WHERE switch_key='id_card' AND switch_value='on'")"
t "the page's script saves on change (fetch to switch.php)" "$(has x "$L" "fetch(wrap.dataset.url") $(has x "$L" "addEventListener('change'")" "yes yes"

echo "== The Staff Calendar rule"
SC=/secretary/staffcalendar/index.php; SA=/secretary/staffcalendar/action.php; SE=/secretary/staffcalendar/event.php
t "Full: the calendar opens" "$(code "$T/h_full" $SC)" "200"
t "View only: the calendar opens" "$(code "$T/h_view" $SC)" "200"
t "Off: the calendar is refused (403)" "$(code "$T/h_off" $SC)" "403"
t "a tutor cannot use the secretary calendar address" "$(where "$T/h_tutor" $SC)" "/auth/login.php"
t "nobody signed in cannot" "$(where "$T/h_none" $SC)" "/auth/login.php"
t "a secretary cannot open the tutor calendar" "$(where "$T/h_full" /tutor/staffcalendar/index.php)" "/auth/login.php"
t "or the super admin calendar" "$(where "$T/h_full" /superadmin/staffcalendar/index.php)" "/superadmin/login.php"
t "the tutor and the super admin still open theirs" "$(code "$T/h_tutor" /tutor/staffcalendar/index.php) $(code "$SUP" /superadmin/staffcalendar/index.php)" "200 200"
HF=$(body "$T/h_full" $SC); HV=$(body "$T/h_view" $SC)
t "Full sees New event; View only does not" "$(has x "$HF" 'class="sc-btn" data-new=') $(has x "$HV" 'class="sc-btn" data-new=')" "yes no"
t "the secretary menu shows Staff Calendar" "$(has x "$HF" '<span>Staff Calendar</span>')" "yes"
ev() { curl -s -b "$1" -H 'Accept: application/json' -w ' [%{http_code}]' --form-string "csrf_token=$2" --form-string "title=$3" --form-string "type=meeting" --form-string "start_date=2026-12-14" --form-string "start_time=09:00" --form-string "end_date=2026-12-14" --form-string "end_time=10:00" --form-string "repeat=none" "$B$SA"; }
TF=$(token "$T/h_full" $SC); TV=$(token "$T/h_view" $SC)
R=$(ev "$T/h_full" "$TF" "Full secretary event"); t "Full can add an event" "$(has x "$R" '"ok":true')" "yes"
EID=$(q "SELECT id FROM staff_calendar_events WHERE title='Full secretary event' ORDER BY id DESC LIMIT 1")
t "the event is saved with her as the owner" "$(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$EID AND owner_user_id=$(python -c "import json;print(json.load(open('$J'))['full']['id'])")")" "1"
R=$(ev "$T/h_view" "$TV" "View only tries"); t "View only cannot add an event (403)" "$(echo "$R" | grep -ao '\[[0-9]*\]') $(q "SELECT COUNT(*) FROM staff_calendar_events WHERE title='View only tries'")" "[403] 0"
R=$(curl -s -b "$T/h_view" -H 'Accept: application/json' -w ' [%{http_code}]' --form-string "csrf_token=$TV" --form-string "op=delete" --form-string "id=$EID" "$B$SA")
t "View only cannot delete an event (403), and it is still there" "$(echo "$R" | grep -ao '\[[0-9]*\]') $(q "SELECT COUNT(*) FROM staff_calendar_events WHERE id=$EID")" "[403] 1"
R=$(curl -s -b "$T/h_view" -H 'Accept: application/json' -w ' [%{http_code}]' --form-string "csrf_token=$TV" --form-string "id=$EID" --form-string "title=Hacked" --form-string "type=meeting" --form-string "start_date=2026-12-14" --form-string "start_time=09:00" --form-string "end_date=2026-12-14" --form-string "end_time=10:00" --form-string "repeat=none" "$B$SA")
t "View only cannot change one (403), the title is unchanged" "$(echo "$R" | grep -ao '\[[0-9]*\]') $(q "SELECT title FROM staff_calendar_events WHERE id=$EID")" "[403] Full secretary event"
for op in task_save gift_send suggest tick; do R=$(curl -s -b "$T/h_view" -H 'Accept: application/json' -o /dev/null -w '%{http_code}' --form-string "csrf_token=$TV" --form-string "op=$op" --form-string "id=$EID" "$B$SA"); t "View only: '$op' is refused (403)" "$R" "403"; done
R=$(curl -s -b "$T/h_view" -H 'Accept: application/json' -w ' [%{http_code}]' --form-string "csrf_token=$TV" --form-string "op=bday_save" --form-string "day=21" --form-string "month=6" "$B$SA")
t "View only CAN add her own birthday" "$(has x "$R" '"ok":true') $(q "SELECT birth_day FROM staff_calendar_birthdays WHERE user_id=$(python -c "import json;print(json.load(open('$J'))['view']['id'])")")" "yes 21"
t "View only can read an event card" "$(code "$T/h_view" "$SE?id=$EID&d=2026-12-14")" "200"
t "Off cannot read an event card or post" "$(code "$T/h_off" "$SE?id=$EID&d=2026-12-14") $(curl -s -b "$T/h_off" -o /dev/null -w '%{http_code}' -d 'csrf_token=x' "$B$SA")" "403 403"
t "the project page and reminders list are closed to Off, open to View only" "$(code "$T/h_off" /secretary/staffcalendar/reminders.php) $(code "$T/h_view" /secretary/staffcalendar/reminders.php)" "403 200"

echo "== The admin changes it, and it applies on the very next request"
post "$TOKS" $SV staff_calendar edit >/dev/null
TV2=$(token "$T/h_view" $SC); R=$(ev "$T/h_view" "$TV2" "Now Full")
t "View only set to Full: she can add an event at once" "$(has x "$R" '"ok":true')" "yes"
post "$TOKS" $SV staff_calendar off >/dev/null
t "set to Off: the next request is refused" "$(code "$T/h_view" $SC)" "403"
post "$TOKS" $SV staff_calendar view >/dev/null
t "set back to View only: she can look again" "$(code "$T/h_view" $SC)" "200"
post "$TOKS" $SF staff_calendar view >/dev/null; post "$TOKS" $SF staff_calendar edit >/dev/null

echo "== On leave does not block"
R=$(post "$TOKS" $SF status on_leave)
t "the admin sets On leave (saved at once)" "$(has x "$R" '"ok":true') $(q "SELECT status FROM secretaries WHERE id=$SF")" "yes on_leave"
t "On leave: she still signs in and the dashboard and the calendar open" "$(login "$T/h_fl" "$EF"; code "$T/h_fl" /secretary/dashboard/index.php) $(code "$T/h_fl" $SC)" "200 200"
t "the list shows the On leave badge" "$([ $(body "$S" /admin/secretaries/index.php | grep -ac 'sec-pill on_leave">On leave<') -ge 1 ] && echo yes)" "yes"
t "the status change is logged with the admin's name and a time" "$(q "SELECT changed_by_name FROM secretary_changes WHERE secretary_id=$SF AND field_key='status' AND new_value='on_leave' AND changed_at IS NOT NULL ORDER BY id DESC LIMIT 1")" "Alice Admin"
post "$TOKS" $SF status active >/dev/null

echo "== Left, Suspend, Reset password"
post "$TOKS" $SF status left >/dev/null
t "Left: her next request is refused" "$(where "$T/h_full" $SC)" "/auth/login.php"
t "Left: she cannot sign in" "$(login "$T/h_full2" "$EF"; code "$T/h_full2" /secretary/dashboard/index.php)" "302"
t "Left: she is still in the database, with her ID number" "$(q "SELECT COUNT(*) FROM secretaries WHERE id=$SF AND staff_no LIKE 'DTI/SEC/%'")" "1"
post "$TOKS" $SF status active >/dev/null; login "$T/h_full" "$EF"
t "Active again: she signs in and opens the calendar" "$(code "$T/h_full" $SC)" "200"
act() { curl -s -b "$S" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$(token "$S" /admin/secretaries/index.php)" --form-string "action=$1" --form-string "id=$2" "$B/admin/secretaries/index.php" | sed "s#$B##"; }
t "Suspend (form post) goes back to the list" "$(act suspend $SF)" "302 /admin/secretaries/index.php"
t "Suspended: the next request is refused at once" "$(where "$T/h_full" $SC)" "/auth/login.php"
t "Suspended: the list says so and offers to lift it" "$(body "$S" /admin/secretaries/index.php | grep -ac 'Lift suspension</button>' | awk '{print ($1>=1)?"yes":"no"}')" "yes"
t "Suspended: she cannot sign in" "$(login "$T/h_full3" "$EF"; where "$T/h_full3" /secretary/dashboard/index.php)" "/auth/login.php"
t "a suspend with no token changes nothing" "$(curl -s -b "$S" -o /dev/null -w '%{http_code}' -d "action=unsuspend&id=$SF" "$B/admin/secretaries/index.php") $(q "SELECT account_status FROM users u JOIN secretaries s ON s.user_id=u.id WHERE s.id=$SF")" "302 suspended"
act unsuspend $SF >/dev/null; login "$T/h_full" "$EF"
t "Lifted: she is back" "$(code "$T/h_full" $SC)" "200"
rm -f "$SINK"/*.eml 2>/dev/null; sleep 0.5
t "Reset password (form post) goes back to the list" "$(act reset $SF)" "302 /admin/secretaries/index.php"
sleep 1
M=$(cat "$SINK"/*.eml 2>/dev/null | tr -d '\r' | tr -d '\n' | sed 's/=3D/=/g;s/=$//')
PW=$(echo "$M" | grep -aoE 'Temporary password: *(<strong>)?[0-9a-f]{16}' | grep -aoE '[0-9a-f]{16}$' | head -1)
t "the email reached her with a 16-character temporary password" "${#PW}" "16"
t "her old password no longer works (the sign-in page is shown again, no redirect)" "$(rm -f "$T/h_f4"; curl -s -c "$T/h_f4" -o /dev/null "$B/login"; curl -s -b "$T/h_f4" -c "$T/h_f4" -o /dev/null -w '%{redirect_url}' -d "email=$EF&password=x" "$B/login" | sed "s#$B##")" ""
t "the temporary password forces a new password at the next sign-in" "$(curl -s -b "$T/h_f4" -c "$T/h_f4" -o /dev/null -w '%{redirect_url}' -d "email=$EF&password=$PW" "$B/login" | sed "s#$B##")" "/auth/change_password.php"
t "and until she does, the calendar sends her to change it" "$(where "$T/h_f4" $SC)" "/auth/change_password.php"

echo "== Edit"
E=$(body "$S" "/admin/secretaries/create.php?id=$SF")
t "the edit form opens with the saved values" "$(has x "$E" 'value="Annette"') $(has x "$E" 'Save changes') $(has x "$E" "value=\"full")" "yes yes yes"
t "the ID number is shown, locked" "$(echo "$E" | grep -aoE '<input[^>]*id="staff_no"[^>]*>' | grep -ac "value=\"DTI/SEC/.*disabled")" "1"
t "the edit form has the status choice and 'Added by' as a name" "$(has x "$E" 'id="status"') $(has x "$E" 'value="Alice Admin"')" "yes yes"
t "an unknown id is a 404" "$(code "$S" '/admin/secretaries/create.php?id=999999')" "404"
NO=$(q "SELECT staff_no FROM secretaries WHERE id=$SF")
TE=$(token "$S" "/admin/secretaries/create.php?id=$SF")
edit() { curl -s -b "$S" -o "$T/h_out.html" -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$1" --form-string "first_name=Annette" --form-string "last_name=Edited" --form-string "email=full${RUNID}@sa.test" --form-string "phone=0722333444" --form-string "branch=online" --form-string "job_title=Lead" --form-string "birth_day=14" --form-string "birth_month=11" --form-string "start_date=2026-01-05" --form-string "status=active" --form-string "sw[staff_calendar]=edit" --form-string "sw[blogs_approval]=off" "$B/admin/secretaries/create.php?id=$SF" | sed "s#$B##"; }
RUNID=$(jv _run)
t "an edit without a token is refused" "$(edit "" | cut -d' ' -f1) $(grep -ac 'security check failed' "$T/h_out.html")" "200 1"
t "an edit saves and goes back to the list" "$(edit "$TE")" "302 /admin/secretaries/index.php"
t "the change is there, the ID number is the same" "$(q "SELECT last_name FROM user_profiles WHERE user_id=(SELECT user_id FROM secretaries WHERE id=$SF)") $(q "SELECT branch FROM secretaries WHERE id=$SF") $([ "$(q "SELECT staff_no FROM secretaries WHERE id=$SF")" = "$NO" ] && echo same)" "Edited online same"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
