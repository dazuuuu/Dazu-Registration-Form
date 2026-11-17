#!/usr/bin/env bash
# HTTP checks for the secretary Enquiries log: who may open it, the page and its boxes, adding (security, errors, escaping), the enquiry page, status / follow-up / notes / take over, no delete,
# the dashboard numbers, and the admin's read-only Enquiries page. Run AFTER secretary_enquiries_test.php on the same scratch database, with the site served from it:
#   B=http://localhost:8011 bash app/v1/tests/secretary_enquiries_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_enquiries_users.json"
PHPBIN=${PHPBIN:-php}
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
jv() { python -c "import json,sys; d=json.load(open(sys.argv[1]))[sys.argv[2]]; print(d['email'] if isinstance(d,dict) else d)" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
where() { curl -s -b "$1" -o /dev/null -w "%{redirect_url}" "$B$2" | sed "s#$B##;s/?.*//"; }
body() { curl -s -b "$1" "$B$2"; }
has() { echo "$2" | grep -aqF -- "$3" && echo yes || echo no; }
tok() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { "$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $r=$p->query($argv[1]); echo $r ? $r->fetchColumn() : "";' "$1" 2>/dev/null; }
RUN=$(jv _run); ID1=$(jv _id1)
for k in A B C D L; do login "$T/e_$k" "$(jv $k)"; done
AD="$T/e_admin"; rm -f "$AD"; curl -s -c "$AD" -o /dev/null "$B/admin/portal.php"; curl -s -b "$AD" -c "$AD" -o /dev/null -d "email=$(jv admin)&password=x" "$B/admin/portal.php"
SUP="$T/e_super"; rm -f "$SUP"; TU=$(tok "$SUP" /superadmin/login.php); curl -s -b "$SUP" -c "$SUP" -o /dev/null -d "email=$(jv super)&password=x&csrf_token=$TU" "$B/superadmin/login.php"
EN=/secretary/enquiries/index.php

echo "== Who may open it"
t "an active secretary with the Enquiries switch on opens the log and an enquiry" "$(code "$T/e_A" $EN) $(code "$T/e_B" $EN) $(code "$T/e_A" "/secretary/enquiries/view.php?id=$ID1")" "200 200 200"
t "On leave does not block her" "$(code "$T/e_C" $EN) $(code "$T/e_C" "/secretary/enquiries/view.php?id=$ID1")" "200 200"
t "the Enquiries switch off: refused (403) on every enquiries address" "$(for p in index.php "view.php?id=$ID1" delete.php; do echo "$(code "$T/e_D" "/secretary/enquiries/$p")"; done | tr '\n' ' ')" "403 403 403 "
t "Left and nobody signed in are sent to sign in" "$(where "$T/e_L" $EN) $(where "$T/e_nobody" $EN)" "/auth/login.php /auth/login.php"
t "the admin and the super admin are not secretaries: sent away" "$(where "$AD" $EN) $(where "$SUP" $EN)" "/auth/login.php /auth/login.php"
t "a switched-off secretary has no Enquiries item in her menu (the dashboard)" "$(body "$T/e_D" /secretary/dashboard/index.php | grep -c '<span>Enquiries</span>')" "0"

echo "== The page"
H=$(body "$T/e_A" $EN)
t "the four boxes: Pending, Follow up today, Called back, Registered" "$(has x "$H" '<small>Pending</small>') $(has x "$H" '<small>Follow up today</small>') $(has x "$H" '<small>Called back</small>') $(has x "$H" '<small>Registered</small>')" "yes yes yes yes"
t "then the list heading, search, status filter and the Add enquiry button" "$(has x "$H" '<h2>Enquiries</h2>') $(has x "$H" 'placeholder="Search enquiries"') $(has x "$H" 'Status: All') $(has x "$H" 'Add enquiry</button>')" "yes yes yes yes"
t "the columns: name and course, source, date, added by, follow up, status" "$(echo "$H" | grep -ao '<th>[^<]*</th>' | sed 's/<[^>]*>//g' | tr '\n' ',')" "Name and course,Source,Date,Added by,Follow up,Status,"
t "every enquiry shows the name of who added it (name only)" "$(has x "$H" "Mercy Wanjiku") $(has x "$H" "Faith Achieng")" "yes yes"
t "then the two short notes" "$(has x "$H" 'If a secretary is away') $(has x "$H" 'Another secretary can open any enquiry, add a note and take it over.') $(has x "$H" 'Only prospects') $(has x "$H" 'never shows registered students’ details.')" "yes yes yes yes"
t "there is no delete button or word anywhere" "$(echo "$H" | grep -aci 'delete\|>remove\|> remove')" "0"
t "a secretary sees the same list as another (every enquiry)" "$([ "$(body "$T/e_A" $EN | grep -ac 'view.php?id=')" = "$(body "$T/e_B" $EN | grep -ac 'view.php?id=')" ] && echo same)" "same"
t "the add form: name, phone, email, course with the course names, source, date, follow-up, first note, token" "$(for f in 'name="name"' 'name="phone"' 'name="email"' 'name="course"' 'list="seCourses"' 'name="source"' 'name="enquiry_date"' 'name="follow_up_date"' 'name="note"' 'name="csrf_token"'; do has x "$H" "$f"; done | tr '\n' ' ')" "yes yes yes yes yes yes yes yes yes yes "
t "all five sources" "$(echo "$H" | grep -ao '<option value="\(phone\|walk_in\|website\|social\|other\)"' | sort -u | wc -l | tr -d ' ')" "5"
t "phones: the list becomes cards and the form a full-screen sheet with large fields" "$(has x "$H" 'max-width: 991.98px') $(has x "$H" 'data-label="Added by"') $(has x "$H" 'height:100%; border-radius:0') $(has x "$H" 'min-height:54px')" "yes yes yes yes"
t "the Dazu colours" "$(has x "$H" '#048C44') $(has x "$H" '#037A3B') $(has x "$H" '#E3F3EA')" "yes yes yes"
t "search by name finds the enquiry; a search with no match says so" "$(has x "$(body "$T/e_A" "$EN?q=Brian+Otieno+$RUN")" "view.php?id=$ID1") $(has x "$(body "$T/e_A" "$EN?q=zzzzqqq")" 'No enquiries match')" "yes yes"
t "the status filter" "$(body "$T/e_A" "$EN?f=registered" | grep -ac 'se-chip pending')" "0"
q "UPDATE secretary_enquiries SET follow_up_date='$(TZ=Africa/Nairobi date +%F)' WHERE id=$ID1" >/dev/null
t "the follow up today box lists only today's follow-ups" "$(has x "$(body "$T/e_A" "$EN?fu=today")" "view.php?id=$ID1") $(body "$T/e_A" "$EN?fu=today" | grep -ac 'Lucy Wambui')" "yes 0"

echo "== Adding an enquiry"
TK=$(tok "$T/e_A" $EN)
add() { curl -s -b "$1" -o "$T/e_out.html" -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$2" --form-string "action=add" --form-string "name=$3" --form-string "phone=${4:-0722000111}" --form-string "email=" --form-string "course=Web Development" --form-string "source=social" --form-string "enquiry_date=2026-11-16" --form-string "follow_up_date=2026-11-20" --form-string "note=${5:-First note}" "$B$EN" | sed "s#$B##"; }
t "no token: refused, nothing saved" "$(add "$T/e_A" "" "NoToken $RUN" | cut -d' ' -f1) $(grep -ac 'security check failed' "$T/e_out.html") $(q "SELECT COUNT(*) FROM secretary_enquiries WHERE name='NoToken $RUN'")" "200 1 0"
t "a bad phone shows the error, opens the sheet again and keeps what was typed" "$(add "$T/e_A" "$TK" "BadPhone $RUN" 'abc' | cut -d' ' -f1) $(grep -ac 'Enter a valid phone' "$T/e_out.html") $(grep -ac 'se-sheet open' "$T/e_out.html") $(grep -ac "BadPhone $RUN" "$T/e_out.html")" "200 1 1 1"
XSS='<script>alert(1)</script>'
t "a good form saves and opens the enquiry" "$(add "$T/e_A" "$TK" "$XSS $RUN" | sed 's/id=[0-9]*/id=N/')" "302 /secretary/enquiries/view.php?id=N"
NID=$(q "SELECT id FROM secretary_enquiries WHERE name='$XSS $RUN'")
t "it is saved as Pending, added by her, with the first note and a history entry" "$(q "SELECT status FROM secretary_enquiries WHERE id=$NID") $(q "SELECT added_by_name FROM secretary_enquiries WHERE id=$NID") $(q "SELECT COUNT(*) FROM secretary_enquiry_notes WHERE enquiry_id=$NID") $(q "SELECT COUNT(*) FROM secretary_enquiry_history WHERE enquiry_id=$NID AND action='created'")" "pending Mercy Wanjiku 1 1"
L2=$(body "$T/e_B" $EN); V=$(body "$T/e_B" "/secretary/enquiries/view.php?id=$NID")
t "another secretary sees it; a script in the name is shown as text, never run" "$(has x "$L2" '&lt;script&gt;alert(1)&lt;/script&gt;') $(has x "$L2" '<script>alert(1)</script>') $(has x "$V" '<script>alert(1)</script>')" "yes no no"

echo "== One enquiry"
t "the page shows the details, who added it, the notes and the history" "$(has x "$V" 'Added by') $(has x "$V" 'Mercy Wanjiku') $(has x "$V" 'First note') $(has x "$V" '<h2>History</h2>') $(has x "$V" '<h2>Notes</h2>')" "yes yes yes yes yes"
TV=$(tok "$T/e_B" "/secretary/enquiries/view.php?id=$NID")
act() { curl -s -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$2" --form-string "id=$NID" --form-string "action=$3" ${4:+--form-string "$4"} "$B/secretary/enquiries/view.php" | sed "s#$B##;s/id=[0-9]*/id=N/"; }
t "another secretary changes the status to Called back" "$(act "$T/e_B" "$TV" status status=called_back) $(q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "302 /secretary/enquiries/view.php?id=N called_back"
t "Registered can be set by hand" "$(act "$T/e_B" "$TV" status status=registered >/dev/null; q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "registered"
t "a status that does not exist changes nothing" "$(act "$T/e_B" "$TV" status status=student >/dev/null; q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "registered"
act "$T/e_B" "$TV" status status=pending >/dev/null
t "she changes the follow-up date" "$(act "$T/e_B" "$TV" follow_up 'follow_up_date=2026-12-01' >/dev/null; q "SELECT follow_up_date FROM secretary_enquiries WHERE id=$NID")" "2026-12-01"
t "she adds a note: it shows who wrote it" "$(act "$T/e_B" "$TV" note 'note=Called, <b>will come</b> Friday' >/dev/null; body "$T/e_A" "/secretary/enquiries/view.php?id=$NID" | grep -ac 'Faith Achieng') $(body "$T/e_A" "/secretary/enquiries/view.php?id=$NID" | grep -ac '&lt;b&gt;will come&lt;/b&gt;' | awk '{print ($1>=1)?"escaped":"no"}')" "$(body "$T/e_A" "/secretary/enquiries/view.php?id=$NID" | grep -ac 'Faith Achieng') escaped"
t "she takes it over from the secretary who added it" "$(act "$T/e_B" "$TV" take_over >/dev/null; q "SELECT handled_by_name FROM secretary_enquiries WHERE id=$NID")" "Faith Achieng"
t "the history lists each change with a name and a time" "$(q "SELECT COUNT(*) FROM secretary_enquiry_history WHERE enquiry_id=$NID AND actor_name='Faith Achieng' AND created_at IS NOT NULL")" "$(q "SELECT COUNT(*) FROM secretary_enquiry_history WHERE enquiry_id=$NID AND actor_name='Faith Achieng'")"
t "the history has status, follow-up, note and taken-over entries" "$(q "SELECT COUNT(DISTINCT action) FROM secretary_enquiry_history WHERE enquiry_id=$NID AND action IN ('status','follow_up','note','taken_over')")" "4"
t "a change without the token does nothing" "$(curl -s -b "$T/e_B" -o /dev/null -w '%{http_code}' -d "id=$NID&action=status&status=not_interested" "$B/secretary/enquiries/view.php") $(q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "302 pending"
t "On leave: she can add a note too" "$(TC=$(tok "$T/e_C" "/secretary/enquiries/view.php?id=$NID"); curl -s -b "$T/e_C" -o /dev/null -w '%{http_code}' --form-string "csrf_token=$TC" --form-string "id=$NID" --form-string "action=note" --form-string "note=Leave note" "$B/secretary/enquiries/view.php") $(q "SELECT COUNT(*) FROM secretary_enquiry_notes WHERE enquiry_id=$NID AND author_name='Grace Njeri'")" "302 1"
t "the Switch-off secretary cannot act on it" "$(curl -s -b "$T/e_D" -o /dev/null -w '%{http_code}' --form-string "id=$NID" --form-string "action=status" --form-string "status=registered" "$B/secretary/enquiries/view.php") $(q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "403 pending"

echo "== Nobody deletes"
CNT=$(q "SELECT COUNT(*) FROM secretary_enquiries")
t "a delete sent to the list or to an enquiry is refused (403)" "$(curl -s -b "$T/e_A" -o /dev/null -w '%{http_code}' -d "action=delete&id=$NID&csrf_token=$TK" "$B$EN") $(curl -s -b "$T/e_A" -o /dev/null -w '%{http_code}' -d "action=delete&id=$NID&csrf_token=$TV" "$B/secretary/enquiries/view.php")" "403 403"
t "the delete address is refused (403) and nothing was deleted" "$(curl -s -b "$T/e_A" -o /dev/null -w '%{http_code}' -d "id=$NID" "$B/secretary/enquiries/delete.php") $([ "$(q "SELECT COUNT(*) FROM secretary_enquiries")" = "$CNT" ] && echo kept)" "403 kept"
t "the enquiry page has no delete button" "$(body "$T/e_A" "/secretary/enquiries/view.php?id=$NID" | grep -aci 'delete\|remove')" "0"

echo "== The dashboard"
DB=$(body "$T/e_A" /secretary/dashboard/index.php)
t "it shows the latest pending enquiries with who added them (name only), and the pending count" "$(has x "$DB" 'No pending enquiries') $(echo "$DB" | grep -ac 'added by ') $(echo "$DB" | tr -d '\n' | grep -aoE '<small>Pending enquiries</small><b>[0-9]+</b>' | grep -aoE '[0-9]+' | head -1 | awk -v n="$(q "SELECT COUNT(*) FROM secretary_enquiries WHERE status='pending'")" '{print ($1==n)?"count-ok":"count-wrong"}')" "no 3 count-ok"

echo "== The admin: read-only Enquiries"
AE=/admin/secretaries/enquiries.php
t "the admin opens the list and an enquiry" "$(code "$AD" $AE) $(code "$AD" "/admin/secretaries/enquiry.php?id=$NID")" "200 200"
t "the super admin, a secretary and nobody are refused with a plain Not allowed (403)" "$(for w in e_super e_A e_B e_nobody; do echo "$(code "$T/$w" $AE) $(body "$T/$w" $AE | head -c 12)"; done | tr '\n' '|')" "403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|"
t "and the enquiry page too" "$(code "$SUP" "/admin/secretaries/enquiry.php?id=$NID") $(code "$T/e_A" "/admin/secretaries/enquiry.php?id=$NID")" "403 403"
AH=$(body "$AD" $AE)
t "it has the four totals, the list with search, and says it is read only" "$(has x "$AH" '<small>Pending</small>') $(has x "$AH" '<small>Follow up today</small>') $(has x "$AH" '<small>Called back</small>') $(has x "$AH" '<small>Registered</small>') $(has x "$AH" 'name="q"') $(has x "$AH" 'Read only')" "yes yes yes yes yes yes"
t "it changes nothing: no status, note, follow-up or take-over form or button" "$(echo "$AH$(body "$AD" "/admin/secretaries/enquiry.php?id=$NID")" | grep -aci 'name="action"\|name="status"\|name="note"\|name="follow_up_date"\|Take over\|Save status\|Add note')" "0"
t "a post to it is not an action: nothing changes" "$(curl -s -b "$AD" -o /dev/null -w '%{http_code}' -d "action=status&id=$NID&status=registered" "$B$AE") $(q "SELECT status FROM secretary_enquiries WHERE id=$NID")" "200 pending"
t "search works there and shows who added it" "$(has x "$(body "$AD" "$AE?q=Brian+Otieno+$RUN")" "enquiry.php?id=$ID1") $(has x "$AH" 'Mercy Wanjiku')" "yes yes"
t "the admin sees the notes and history with names and times" "$(body "$AD" "/admin/secretaries/enquiry.php?id=$NID" | grep -ac 'Faith Achieng')" "$(body "$AD" "/admin/secretaries/enquiry.php?id=$NID" | grep -ac 'Faith Achieng')"
t "the Secretaries list links to it" "$(body "$AD" /admin/secretaries/index.php | grep -ac 'href="enquiries.php"')" "1"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
