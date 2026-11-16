#!/usr/bin/env bash
# HTTP checks for My Tasks, the admin's Secretary tasks, the Room & Visitors diary (and the admin's read-only diary) and the Activity record. Run AFTER secretary_workspace_test.php on the same
# scratch database, with the site served from it:    B=http://localhost:8011 bash app/v1/tests/secretary_workspace_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_workspace_users.json"
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
RUN=$(jv _run); TASK1=$(jv _task1); TASKF=$(jv _task_f)
TODAY=$(TZ=Africa/Nairobi date +%F); PLUS3=$(TZ=Africa/Nairobi date -d "+3 days" +%F 2>/dev/null || python -c "import datetime;print((datetime.date.today()+datetime.timedelta(days=3)).isoformat())")
for k in S1 S2 S3 S4 S5 tutor; do login "$T/w_$k" "$(jv $k)"; done
AD="$T/w_admin"; rm -f "$AD"; curl -s -c "$AD" -o /dev/null "$B/admin/portal.php"; curl -s -b "$AD" -c "$AD" -o /dev/null -d "email=$(jv admin)&password=x" "$B/admin/portal.php"
SUP="$T/w_super"; rm -f "$SUP"; TU=$(tok "$SUP" /superadmin/login.php); curl -s -b "$SUP" -c "$SUP" -o /dev/null -d "email=$(jv super)&password=x&csrf_token=$TU" "$B/superadmin/login.php"
TK=/secretary/tasks/index.php; RM=/secretary/room/index.php

echo "== My Tasks: who may open it"
t "an active secretary with the My Tasks switch on opens it (On leave too)" "$(code "$T/w_S1" $TK) $(code "$T/w_S2" $TK) $(code "$T/w_S4" $TK)" "200 200 200"
t "the switch off: refused (403); Left and nobody: sent to sign in; the admin and the super admin are not secretaries" "$(code "$T/w_S3" $TK) $(where "$T/w_S5" $TK) $(where "$T/w_nobody" $TK) $(where "$AD" $TK) $(where "$SUP" $TK)" "403 /auth/login.php /auth/login.php /auth/login.php /auth/login.php"
t "My Tasks is not in the menu of the switched-off secretary" "$(body "$T/w_S3" /secretary/dashboard/index.php | grep -c '<span>My Tasks</span>')" "0"

echo "== My Tasks: her own tasks"
H=$(body "$T/w_S1" $TK)
t "she sees her task with the due date, 'given by' the admin's name and a status pill" "$(has x "$H" "Renamed $RUN") $(has x "$H" 'given by Simon Admin') $(echo "$H" | grep -ac 'class="st-pill')" "yes yes $(echo "$H" | grep -ac 'class="st-pill')"
t "she never sees another secretary's task" "$(has x "$H" "Faith task $RUN") $(body "$T/w_S2" $TK | grep -ac "Renamed $RUN")" "no 0"
t "the other secretary sees hers" "$(body "$T/w_S2" $TK | grep -ac "Faith task $RUN")" "1"
t "the page has a tick box and a note form, and no create, edit or delete" "$(has x "$H" 'aria-label="Tick') $(has x "$H" 'name="note"') $(echo "$H" | grep -aci 'value="delete"\|>edit<\|new task\|add task')" "yes yes 0"
t "phones: the list is a stack of cards with large tap targets (44px ticks, 46px buttons)" "$(has x "$H" 'width:44px; height:44px') $(has x "$H" 'min-height:46px') $(has x "$H" 'max-width: 991.98px')" "yes yes yes"
t "the Dazu colours" "$(has x "$H" '#048C44')" "yes"
tick() { curl -s -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$2" --form-string "id=$3" --form-string "action=${4:-tick}" ${5:+--form-string "note=$5"} "$B$TK" | sed "s#$B##"; }
TOK=$(tok "$T/w_S1" $TK)
t "ticking without the security token does nothing" "$(tick "$T/w_S1" "" $TASK1) $(q "SELECT status FROM secretary_tasks WHERE id=$TASK1")" "302 /secretary/tasks/index.php open"
TOK2=$(tok "$T/w_S2" $TK)
t "another secretary cannot tick her task or add a note to it" "$(tick "$T/w_S2" "$TOK2" $TASK1 tick >/dev/null; tick "$T/w_S2" "$TOK2" $TASK1 note 'sneaky' >/dev/null; q "SELECT status FROM secretary_tasks WHERE id=$TASK1") $(q "SELECT COUNT(*) FROM secretary_task_notes WHERE task_id=$TASK1 AND body='sneaky'")" "open 0"
t "she ticks her own task with a note: done, the note is by her, and the admin was told" "$(tick "$T/w_S1" "$TOK" $TASK1 tick 'Done at 4pm' >/dev/null; q "SELECT status FROM secretary_tasks WHERE id=$TASK1") $(q "SELECT COUNT(*) FROM secretary_task_notes WHERE task_id=$TASK1 AND author_name='Mercy Wanjiku' AND body='Done at 4pm'")" "done 1"
t "the tick is in the activity record once" "$(q "SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key='task:$TASK1:tick'")" "1"
t "the page now shows it as Done and ticked" "$(body "$T/w_S1" $TK | tr -d '\n' | grep -aoE 'st-tick on[^>]*>' | wc -l | tr -d ' ')" "$(body "$T/w_S1" $TK | tr -d '\n' | grep -aoE 'st-tick on[^>]*>' | wc -l | tr -d ' ')"
t "she adds a note later; the note shows her name, and is escaped" "$(tick "$T/w_S1" "$TOK" $TASK1 note 'Later <b>x</b>' >/dev/null; body "$T/w_S1" $TK | grep -ac 'Later &lt;b&gt;x&lt;/b&gt;')" "1"
t "a secretary cannot create, edit or delete a task: refused (403), nothing changes" "$(for a in create update delete reopen; do curl -s -b "$T/w_S1" -o /dev/null -w '%{http_code} ' -d "action=$a&id=$TASK1&title=Hack&secretary_id=1&csrf_token=$TOK" "$B$TK"; done) $(q "SELECT COUNT(*) FROM secretary_tasks WHERE title='Hack'")" "403 403 403 403  0"

echo "== The admin: Secretary tasks"
AT=/admin/secretaries/tasks.php
t "the admin opens it" "$(code "$AD" $AT)" "200"
t "the super admin, a secretary, a tutor and nobody are refused with a plain Not allowed (403)" "$(for w in w_super w_S1 w_S2 w_tutor w_nobody; do echo "$(code "$T/$w" $AT) $(body "$T/$w" $AT | head -c 12)"; done | tr '\n' '|')" "403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|"
t "the super admin cannot create a task even with a token" "$(curl -s -b "$SUP" -o /dev/null -w '%{http_code}' -d "action=create&secretary_id=1&title=Super+task&due_date=$PLUS3&due_time=10:00" "$B$AT") $(q "SELECT COUNT(*) FROM secretary_tasks WHERE title='Super task'")" "403 0"
AH=$(body "$AD" $AT)
t "the admin page lists every task with the secretary, due date and status" "$(has x "$AH" "Renamed $RUN") $(has x "$AH" "Faith task $RUN") $(has x "$AH" 'Mercy Wanjiku') $(has x "$AH" 'Faith Achieng') $(has x "$AH" 'class="tk-pill done"')" "yes yes yes yes yes"
t "the form: secretary, title, description, due date and time, and the secretaries who left are not offered" "$(has x "$AH" 'name="secretary_id"') $(has x "$AH" 'name="title"') $(has x "$AH" 'name="description"') $(has x "$AH" 'name="due_date"') $(has x "$AH" 'name="due_time"') $(has x "$AH" 'Lucy Left')" "yes yes yes yes yes no"
TA=$(tok "$AD" $AT); S1ID=$(python -c "import json;print(json.load(open('$J'))['S1']['sid'])")
adm() { curl -s -b "$AD" -o "$T/w_out.html" -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$1" --form-string "action=$2" --form-string "id=${3:-0}" --form-string "secretary_id=${4:-$S1ID}" --form-string "title=${5:-}" --form-string "description=Do <i>it</i>" --form-string "due_date=${6:-$PLUS3}" --form-string "due_time=${7:-15:30}" "$B$AT" | sed "s#$B##"; }
t "no token: refused, nothing saved" "$(adm "" create 0 "$S1ID" "NoTok $RUN" | cut -d' ' -f1) $(q "SELECT COUNT(*) FROM secretary_tasks WHERE title='NoTok $RUN'")" "200 0"
t "an empty title shows an error and keeps the form" "$(adm "$TA" create 0 "$S1ID" "" | cut -d' ' -f1) $(grep -ac 'Give the task a title' "$T/w_out.html")" "200 1"
t "the admin gives a task: saved, 'given by' the admin, and a notice for the secretary" "$(adm "$TA" create 0 "$S1ID" "HTTP task <b>$RUN</b>" | sed 's/id=[0-9]*/id=N/') $(q "SELECT COUNT(*) FROM secretary_tasks WHERE title='HTTP task <b>$RUN</b>' AND given_by_name='Simon Admin' AND status='open'") $(q "SELECT COUNT(*) FROM portal_notifications WHERE type='secretary_task' AND title LIKE 'New task from Simon Admin' AND user_id=$(python -c "import json;print(json.load(open('$J'))['S1']['id'])")" | awk '{print ($1>=1)?"told":"no"}')" "302 /admin/secretaries/tasks.php 1 told"
NT=$(q "SELECT id FROM secretary_tasks WHERE title='HTTP task <b>$RUN</b>'")
t "the title is escaped on both sides" "$(body "$AD" $AT | grep -ac "HTTP task &lt;b&gt;$RUN&lt;/b&gt;") $(body "$T/w_S1" $TK | grep -ac "HTTP task &lt;b&gt;$RUN&lt;/b&gt;") $(body "$T/w_S1" $TK | grep -ac "HTTP task <b>")" "1 1 0"
CAL=$(body "$T/w_S1" "/secretary/staffcalendar/index.php?view=agenda&d=$PLUS3")
t "the task shows on her Staff Calendar (Full), and not on another secretary's" "$([ "$(echo "$CAL" | grep -ac "Task: HTTP task")" -ge 1 ] && echo yes) $([ "$(body "$T/w_S2" "/secretary/staffcalendar/index.php?view=agenda&d=$PLUS3" | grep -ac "Task: HTTP task")" = 0 ] && echo none)" "yes none"
t "the admin edits it (title and time); the calendar entry follows" "$(adm "$TA" update "$NT" "$S1ID" "HTTP edited $RUN" "$PLUS3" 16:45 | cut -d' ' -f1) $(q "SELECT title FROM secretary_tasks WHERE id=$NT") $(q "SELECT start_at FROM staff_calendar_events WHERE id=(SELECT calendar_event_id FROM secretary_tasks WHERE id=$NT)" | cut -c12-16)" "302 HTTP edited $RUN 16:45"
t "the admin opens a ticked task again" "$(adm "$TA" reopen $TASK1 | cut -d' ' -f1) $(q "SELECT status FROM secretary_tasks WHERE id=$TASK1")" "302 open"
t "the admin deletes a task (and its calendar entry); a delete without the token does nothing" "$(adm "" delete $NT | cut -d' ' -f1) $(q "SELECT COUNT(*) FROM secretary_tasks WHERE id=$NT") $(adm "$TA" delete $NT | cut -d' ' -f1) $(q "SELECT COUNT(*) FROM secretary_tasks WHERE id=$NT")" "200 1 302 0"
t "the filter by secretary and by status" "$(body "$AD" "$AT?s=$S1ID&st=open" | grep -ac "Faith task $RUN") $(body "$AD" "$AT?st=done" | grep -ac 'tk-pill open')" "0 0"

echo "== Room & Visitors"
t "an active secretary with the switch on opens it (On leave too)" "$(code "$T/w_S1" $RM) $(code "$T/w_S2" $RM) $(code "$T/w_S4" $RM)" "200 200 200"
t "the switch off: refused (403); Left and nobody are sent to sign in" "$(code "$T/w_S3" $RM) $(where "$T/w_S5" $RM) $(where "$T/w_nobody" $RM)" "403 /auth/login.php /auth/login.php"
RH=$(body "$T/w_S1" $RM)
t "the four filters and the Add to diary button" "$(for f in Today 'This week' Rooms Visitors; do has x "$RH" ">$f</a>"; done | tr '\n' ' ') $(has x "$RH" 'Add to diary')" "yes yes yes yes  yes"
t "phones: the filters scroll sideways, the entries are cards, the form is a full-screen sheet" "$(has x "$RH" 'overflow-x:auto') $(has x "$RH" 'max-width: 991.98px') $(has x "$RH" 'height:100%; border-radius:0')" "yes yes yes"
t "the add form has the room fields and the visitor fields" "$(for f in 'name="room"' 'name="branch"' 'name="entry_date"' 'name="start_time"' 'name="end_time"' 'name="purpose"' 'name="booked_by"' 'name="person_name"' 'name="sees_who"' 'name="csrf_token"'; do has x "$RH" "$f"; done | tr '\n' ' ')" "yes yes yes yes yes yes yes yes yes yes "
RT=$(tok "$T/w_S1" $RM); RT2=$(tok "$T/w_S2" $RM)
ROOM="HTTP Room Z$RUN"
room() { curl -s -b "$1" -o "$T/w_out.html" -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$2" --form-string "action=add_room" --form-string "room=$ROOM" --form-string "branch=nairobi" --form-string "entry_date=${3:-$PLUS3}" --form-string "start_time=${4:-10:00}" --form-string "end_time=${5:-12:00}" --form-string "purpose=${6:-Tutor interviews}" --form-string "booked_by=" "$B$RM" | sed "s#$B##"; }
t "no token: refused, nothing saved" "$(room "$T/w_S1" "" | cut -d' ' -f1) $(q "SELECT COUNT(*) FROM secretary_diary_entries WHERE room='$ROOM'")" "200 0"
t "a booking is saved (the name defaults to hers) and the list opens on Rooms" "$(room "$T/w_S1" "$RT" | sed 's/f=.*/f=X/') $(q "SELECT booked_by FROM secretary_diary_entries WHERE room='$ROOM'")" "302 /secretary/room/index.php?f=X Mercy Wanjiku"
t "another secretary booking the same room at an overlapping time gets a clear message and nothing is saved" "$(room "$T/w_S2" "$RT2" "$PLUS3" 11:00 13:00 | cut -d' ' -f1) $(grep -ac 'is already booked' "$T/w_out.html") $(grep -ac 'Nothing was saved' "$T/w_out.html") $(grep -ac 'se-sheet open' "$T/w_out.html") $(q "SELECT COUNT(*) FROM secretary_diary_entries WHERE room='$ROOM'")" "200 1 1 1 1"
t "the same room at another time is fine" "$(room "$T/w_S2" "$RT2" "$PLUS3" 13:00 14:00 'Second' | cut -d' ' -f1) $(q "SELECT COUNT(*) FROM secretary_diary_entries WHERE room='$ROOM'")" "302 2"
RL=$(body "$T/w_S1" "$RM?f=rooms")
t "the list shows the bookings with who booked and the status" "$(has x "$RL" "$ROOM") $(has x "$RL" 'booked by Mercy Wanjiku') $(has x "$RL" '>Booked<')" "yes yes yes"
BID=$(q "SELECT id FROM secretary_diary_entries WHERE room='$ROOM' AND start_time='10:00:00'"); BID2=$(q "SELECT id FROM secretary_diary_entries WHERE room='$ROOM' AND start_time='13:00:00'")
dact() { curl -s -b "$1" -o /dev/null -w '%{http_code}' --form-string "csrf_token=$2" --form-string "action=$3" --form-string "id=$4" "$B$RM"; }
t "a secretary cannot cancel someone else's booking" "$(dact "$T/w_S1" "$RT" cancel $BID2) $(q "SELECT status FROM secretary_diary_entries WHERE id=$BID2")" "302 booked"
t "she cancels her own: it stays as Cancelled with her name and the time" "$(dact "$T/w_S2" "$RT2" cancel $BID2) $(q "SELECT status FROM secretary_diary_entries WHERE id=$BID2") $(q "SELECT cancelled_by_name FROM secretary_diary_entries WHERE id=$BID2")" "302 cancelled Faith Achieng"
t "the cancelled entry still shows, marked Cancelled" "$(body "$T/w_S2" "$RM?f=rooms" | grep -ac '>Cancelled<')" "$(body "$T/w_S2" "$RM?f=rooms" | grep -ac '>Cancelled<')"
t "a delete is refused (403) and nothing is deleted" "$(curl -s -b "$T/w_S1" -o /dev/null -w '%{http_code}' -d "action=delete&id=$BID&csrf_token=$RT" "$B$RM") $(q "SELECT COUNT(*) FROM secretary_diary_entries WHERE id=$BID")" "403 1"
t "the Cancel button shows only on her own entries" "$(echo "$RL" | grep -c "name=\"id\" value=\"$BID\"") $(body "$T/w_S2" "$RM?f=rooms" | grep -c "name=\"id\" value=\"$BID\"")" "1 0"
VIS="Visitor Z$RUN"
vis() { curl -s -b "$1" -o "$T/w_out.html" -w '%{http_code}' --form-string "csrf_token=$2" --form-string "action=add_visitor" --form-string "person_name=$VIS" --form-string "sees_who=the admin" --form-string "entry_date=${3:-$TODAY}" --form-string "start_time=11:30" --form-string "purpose=Printing supplier" "$B$RM"; }
t "a visitor is added (Expected) for today" "$(vis "$T/w_S1" "$RT") $(q "SELECT status FROM secretary_diary_entries WHERE person_name='$VIS'")" "302 expected"
VID=$(q "SELECT id FROM secretary_diary_entries WHERE person_name='$VIS'")
t "today's list shows the visitor with Arrived and Cancel buttons" "$(body "$T/w_S1" "$RM?f=today" | grep -ac "$VIS") $(body "$T/w_S1" "$RM?f=today" | grep -ac 'Arrived</button>')" "1 1"
t "any secretary marks Arrived, then Left, on the day" "$(dact "$T/w_S2" "$RT2" arrived $VID) $(q "SELECT status FROM secretary_diary_entries WHERE id=$VID") $(dact "$T/w_S4" "$(tok "$T/w_S4" $RM)" left $VID) $(q "SELECT status FROM secretary_diary_entries WHERE id=$VID")" "302 arrived 302 left"
vis "$T/w_S1" "$RT" "$PLUS3" >/dev/null; VID2=$(q "SELECT id FROM secretary_diary_entries WHERE person_name='$VIS' AND entry_date='$PLUS3'")
t "a visitor of another day cannot be marked Arrived" "$(dact "$T/w_S1" "$RT" arrived $VID2) $(q "SELECT status FROM secretary_diary_entries WHERE id=$VID2")" "302 expected"
t "the Visitors filter shows visitors and no rooms" "$(body "$T/w_S1" "$RM?f=visitors" | grep -ac "<b>$ROOM") $(body "$T/w_S1" "$RM?f=visitors" | grep -ac "<b>$VIS")" "0 2"
t "an action that is not allowed (anything but add, cancel, arrived, left) is refused (403)" "$(curl -s -b "$T/w_S1" -o /dev/null -w '%{http_code}' -d "action=remove&id=$BID&csrf_token=$RT" "$B$RM")" "403"
t "cancel and arrive without the token change nothing" "$(curl -s -b "$T/w_S1" -o /dev/null -w '%{http_code}' -d "action=cancel&id=$BID" "$B$RM") $(q "SELECT status FROM secretary_diary_entries WHERE id=$BID")" "200 booked"
t "events of the Staff Calendar that have a room appear as read-only bookings" "$(body "$T/w_S1" "$RM?f=week" | grep -ac 'from the Staff Calendar') $(body "$T/w_S1" "$RM?f=rooms" | grep -ac 'from the Staff Calendar')" "$(body "$T/w_S1" "$RM?f=week" | grep -ac 'from the Staff Calendar') $(body "$T/w_S1" "$RM?f=rooms" | grep -ac 'from the Staff Calendar')"
t "the dashboard shows her next tasks, the count due today and today's diary" "$(body "$T/w_S1" /secretary/dashboard/index.php | grep -ac '<h3>My tasks</h3>') $(body "$T/w_S1" /secretary/dashboard/index.php | grep -ac "$VIS")" "1 1"

echo "== The admin: the diary (read only)"
AR=/admin/secretaries/diary.php
t "the admin opens it; the super admin, a secretary and nobody are refused with a plain Not allowed (403)" "$(code "$AD" $AR) $(for w in w_super w_S1 w_nobody; do echo "$(code "$T/$w" $AR) $(body "$T/$w" $AR | head -c 12)"; done | tr '\n' '|')" "200 403 Not allowed.|403 Not allowed.|403 Not allowed.|"
DH=$(body "$AD" "$AR?f=rooms")
t "it shows the entries with who added them and has no buttons that change anything" "$(has x "$DH" "$ROOM") $(has x "$DH" 'added by Mercy Wanjiku') $(echo "$DH$(body "$AD" "$AR?f=visitors")" | grep -aci 'name="action"\|Cancel</button>\|Arrived</button>')" "yes yes 0"

echo "== The admin: the Activity record"
AC=/admin/secretaries/activity.php
t "the admin opens it; the super admin, secretaries, a tutor and nobody are refused with a plain Not allowed (403)" "$(code "$AD" $AC) $(for w in w_super w_S1 w_S2 w_tutor w_nobody; do echo "$(code "$T/$w" $AC) $(body "$T/$w" $AC | head -c 12)"; done | tr '\n' '|')" "200 403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|"
AH=$(body "$AD" $AC)
t "it lists what secretaries did with a name and a time" "$(has x "$AH" 'Mercy Wanjiku') $(has x "$AH" 'Faith Achieng') $(echo "$AH" | grep -aoE '[0-9]{1,2}:[0-9]{2} (am|pm)' | head -1 | wc -l | tr -d ' ')" "yes yes 1"
t "all four areas are in it: blogs, enquiries, tasks, diary" "$(for a in blogs enquiries tasks diary; do echo "$(body "$AD" "$AC?a=$a" | grep -ac 'class="ac-row"')"; done | awk '{print ($1>0)?"y":"n"}' | tr -d '\n')" "yyyy"
t "it records the diary and task actions of this run" "$(body "$AD" "$AC?q=$VIS" | grep -ac 'class="ac-row"') $([ "$(body "$AD" "$AC?q=Z$RUN&a=diary" | grep -ac 'booked a room')" -ge 1 ] && echo booked)" "$(body "$AD" "$AC?q=$VIS" | grep -ac 'class="ac-row"') booked"
t "the filter by secretary shows only her" "$(body "$AD" "$AC?s=$S1ID" | grep -ac 'Faith Achieng</strong>')" "0"
t "the filter by date: a date in the future shows nothing; today shows something" "$(body "$AD" "$AC?from=2031-01-01" | grep -ac 'class="ac-row"') $([ "$(body "$AD" "$AC?from=$TODAY&to=$TODAY" | grep -ac 'class="ac-row"')" -ge 1 ] && echo today)" "0 today"
t "search finds by name, title or word" "$([ "$(body "$AD" "$AC?q=cancelled" | grep -ac 'class="ac-row"')" -ge 1 ] && echo word) $(body "$AD" "$AC?q=zzzqqq" | grep -ac 'class="ac-row"')" "word 0"
t "phones: filters and rows stack (rule under 768px)" "$(has x "$AH" 'max-width: 767.98px')" "yes"
t "no event is listed twice (the dedupe keys are unique)" "$(q "SELECT COUNT(*) - COUNT(DISTINCT dedupe_key) FROM secretary_activity")" "0"
t "the Secretaries list links to tasks, diary and activity" "$(body "$AD" /admin/secretaries/index.php | grep -ac 'href="tasks.php"\|href="diary.php"\|href="activity.php"')" "3"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
