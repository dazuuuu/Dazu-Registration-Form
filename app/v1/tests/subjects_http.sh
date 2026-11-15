#!/usr/bin/env bash
# HTTP checks for Subjects: the form, the Subjects page, URL tampering by another tutor, student and admin. Run AFTER subjects_test.php on the same scratch
# database, with the site served from that database, for example:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_subj php -S localhost:8004 router.php &
#   B=http://localhost:8004 PHPBIN=php DAZU_DB_NAME=dazu_subj (same DB variables) bash app/v1/tests/subjects_http.sh
B=${B:-http://localhost:8004}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/subjects_users.json"
PHPBIN=${PHPBIN:-php}
jget() { python -c "import json,sys; d=json.load(open(sys.argv[1])); print(d[sys.argv[2]])" "$J" "$1"; }
COURSE=$(jget _course); PY=$(jget _py); SQL=$(jget _sql); ML=$(jget _ml); S1=$(jget _s1); ASSIGN=$(jget _assign); OLD=$(jget _old); RUN=$(jget _run)
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} "$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); echo $p->query($argv[1])->fetchColumn();' "$1" 2>/dev/null; }
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}

login "$T/s_tutor" "$(mail tutor)"; login "$T/s_tutor2" "$(mail tutor2)"; login "$T/s_student" "$(mail ann)"
rm -f "$T/s_admin"; curl -s -c "$T/s_admin" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/s_admin" -c "$T/s_admin" -o /dev/null -d "email=$(mail admin)&password=x" "$B/admin/portal.php"
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
DUE=$(python -c "import datetime; print((datetime.date.today()+datetime.timedelta(days=9)).isoformat())")
# form JAR URL SUBJECT_ID KIND TITLE : posts the assignment form (save as draft) and keeps the page in s_out.html
form() {
  TOK=$(token "$1" "/tutor/assignments/edit.php")
  curl -s -b "$1" -c "$1" -o "$T/s_out.html" "$B$2" -d "csrf_token=$TOK" -d "action=draft" -d "course_id=$COURSE" -d "kind=$4" -d "subject_id=$3" -d "title=$5" \
    -d "description=short" -d "details=<p>Do it</p>" -d "due_date=$DUE" -d "due_time=17:00" -d "types[]=link" -d "grading_mode=marks" -d "max_score=100" -d "pass_mark_percent=50" \
    -d "max_extension_days=7" -d "weight=2" -d "students[]=$S1"
}

echo "== Tutor: Subjects page and the form"
t "the tutor opens the Subjects page" "$(code "$T/s_tutor" "/tutor/assignments/subjects.php?course=$COURSE")" "200"
t "...it lists the tutor's subjects" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/subjects.php?course=$COURSE" | grep -ac 'Machine Learning')" "1"
t "the new assignment form has the Subject box with the course's subjects" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/edit.php" | grep -ac "data-course=\"$COURSE\"")" "3"
t "the form offers Monthly project as a kind" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/edit.php" | grep -ac 'value="monthly"')" "1"
BEFORE=$(q "SELECT COUNT(*) FROM tutor_assignments WHERE title LIKE 'HTTP test%'")
form "$T/s_tutor" /tutor/assignments/edit.php "" daily 'HTTP test none'
t "saving with NO subject in a course with subjects is refused (clear message)" "$(grep -ac 'Choose a subject for this assignment' "$T/s_out.html")" "1"
t "...and nothing was saved" "$(q "SELECT COUNT(*) FROM tutor_assignments WHERE title LIKE 'HTTP test%'")" "$BEFORE"
form "$T/s_tutor" /tutor/assignments/edit.php 999999 daily 'HTTP test forged'
t "saving with a subject that is not the tutor's own is refused" "$(grep -ac 'not one of yours' "$T/s_out.html")" "1"
form "$T/s_tutor" /tutor/assignments/edit.php "$PY" monthly "HTTP test ok $RUN"
t "saving a monthly project with a subject works (saved with the subject)" "$(q "SELECT CONCAT(kind,'-',subject_id) FROM tutor_assignments WHERE title='HTTP test ok $RUN'")" "monthly-$PY"
NEWID=$(q "SELECT id FROM tutor_assignments WHERE title='HTTP test ok $RUN'")
form "$T/s_tutor" "/tutor/assignments/edit.php?id=$NEWID" "$SQL" monthly "HTTP test ok $RUN"
t "moving it to another subject works" "$(q "SELECT subject_id FROM tutor_assignments WHERE id=$NEWID")" "$SQL"
t "...and the change is in the audit log (one for the first choice, one for the move)" "$(q "SELECT COUNT(*) FROM course_subject_events WHERE event='assignment_subject_changed' AND assignment_id=$NEWID")" "2"
t "the assignment page shows the subject card" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/view.php?id=$ASSIGN" | grep -ac 'id="subjectCard"')" "1"

echo "== Old assignment with no subject, in a course that has subjects"
SUBBEFORE=$(q "SELECT CONCAT_WS('|',id,status,score,published_at,submitted_at,is_late,feedback) FROM tutor_assignment_submissions WHERE assignment_id=$OLD AND student_user_id=$S1")
form "$T/s_tutor" "/tutor/assignments/edit.php?id=$OLD" "" daily "Old unsubjected edited $RUN"
t "editing it without choosing a subject saves (no error)" "$(grep -ac 'Choose a subject' "$T/s_out.html")" "0"
t "...the edit was saved" "$(q "SELECT title FROM tutor_assignments WHERE id=$OLD")" "Old unsubjected edited $RUN"
t "...it still has no subject (none was added)" "$(q "SELECT COALESCE(subject_id,'none') FROM tutor_assignments WHERE id=$OLD")" "none"
t "...its submission and mark are unchanged" "$(q "SELECT CONCAT_WS('|',id,status,score,published_at,submitted_at,is_late,feedback) FROM tutor_assignment_submissions WHERE assignment_id=$OLD AND student_user_id=$S1")" "$SUBBEFORE"
t "...no audit entry for a subject change was written" "$(q "SELECT COUNT(*) FROM course_subject_events WHERE assignment_id=$OLD")" "0"
t "NEW work: the subject box is marked required" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/edit.php" | grep -ac 'id="subjectBox" data-must="1"')" "1"
t "the edit page for the old assignment is told the subject is optional (subject box not required)" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/edit.php?id=$OLD" | grep -ac 'id="subjectBox" data-must="0"')" "1"

echo "== New subject without leaving the form (JSON)"
TOK=$(token "$T/s_tutor" "/tutor/assignments/edit.php")
R=$(curl -s -b "$T/s_tutor" -H 'X-Requested-With: fetch' -d "csrf_token=$TOK&action=create_ajax&course_id=$COURSE&name=Statistics&weight=&monthly_expected=3" "$B/tutor/assignments/subjects.php")
t "New subject returns the new subject at once" "$(echo "$R" | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'] and d['label'])")" "Statistics"
R=$(curl -s -b "$T/s_tutor" -H 'X-Requested-With: fetch' -d "csrf_token=$TOK&action=create_ajax&course_id=$COURSE&name=Statistics&monthly_expected=3" "$B/tutor/assignments/subjects.php")
t "...a duplicate name is refused with a message" "$(echo "$R" | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'])")" "False"
R=$(curl -s -b "$T/s_tutor" -H 'X-Requested-With: fetch' -d "action=create_ajax&course_id=$COURSE&name=NoToken&monthly_expected=1" "$B/tutor/assignments/subjects.php")
t "...without the CSRF token it is refused" "$(echo "$R" | python -c "import json,sys; d=json.load(sys.stdin); print(d['ok'])")" "False"

echo "== Nobody can set a subject mark by hand"
TOK=$(token "$T/s_tutor" "/tutor/assignments/subjects.php?course=$COURSE")
curl -s -b "$T/s_tutor" -o /dev/null -d "csrf_token=$TOK&course_id=$COURSE&id=$PY&action=update&name=Python Programming 101&code=DS-02&monthly_expected=2&mark=99&score=99&subject_mark=99&average=99" "$B/tutor/assignments/subjects.php"
t "the subjects table has no column that could hold a mark" "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='course_subjects' AND column_name REGEXP 'mark|score|average'")" "0"
t "posting mark=99 / score=99 / average=99 to the Subjects page changes no mark shown on the assignment page" "$(curl -s -b "$T/s_tutor" "$B/tutor/assignments/view.php?id=$ASSIGN" | grep -ac '99%')" "0"

echo "== Another tutor"
t "another tutor opens the Subjects page but sees none of the first tutor's subjects" "$(curl -s -b "$T/s_tutor2" "$B/tutor/assignments/subjects.php?course=$COURSE" | grep -aciE 'Python Programming 101|Machine Learning|Databases and SQL')" "0"
TOK2=$(token "$T/s_tutor2" "/tutor/assignments/subjects.php?course=$COURSE")
curl -s -b "$T/s_tutor2" -o /dev/null -d "csrf_token=$TOK2&course_id=$COURSE&id=$PY&action=update&name=Hacked&monthly_expected=1" "$B/tutor/assignments/subjects.php"
curl -s -b "$T/s_tutor2" -o /dev/null -d "csrf_token=$TOK2&course_id=$COURSE&id=$ML&action=remove" "$B/tutor/assignments/subjects.php"
t "...typing the URL / posting to rename a subject of another tutor does nothing" "$(q "SELECT name FROM course_subjects WHERE id=$PY")" "Python Programming 101"
t "...nor to remove it" "$(q "SELECT COUNT(*) FROM course_subjects WHERE id=$ML")" "1"
t "another tutor cannot open the first tutor's assignment (edit)" "$(code "$T/s_tutor2" "/tutor/assignments/edit.php?id=$ASSIGN" | cut -c1)" "3"
t "...nor its page (view)" "$(code "$T/s_tutor2" "/tutor/assignments/view.php?id=$ASSIGN" | cut -c1)" "3"
t "...and following the redirect shows no part of it" "$(curl -s -b "$T/s_tutor2" -L "$B/tutor/assignments/view.php?id=$ASSIGN" | grep -ac 'Python')" "0"

echo "== Student and admin"
t "a student cannot open the Subjects page" "$(code "$T/s_student" "/tutor/assignments/subjects.php?course=$COURSE" | cut -c1)" "3"
t "the admin cannot open the Subjects page" "$(code "$T/s_admin" "/tutor/assignments/subjects.php?course=$COURSE" | cut -c1)" "3"
t "the admin cannot open an assignment page (which holds the subject mark card)" "$(code "$T/s_admin" "/tutor/assignments/view.php?id=$ASSIGN" | cut -c1)" "3"
t "the admin certificates page shows no subject name or subject mark" "$(curl -s -b "$T/s_admin" "$B/admin/certificates/" | grep -aciE 'Python Programming|Machine Learning|subject mark')" "0"
t "the admin cannot send JSON to create a subject" "$(curl -s -b "$T/s_admin" -o /dev/null -w '%{http_code}' -d 'action=create_ajax&course_id=1&name=x' "$B/tutor/assignments/subjects.php" | cut -c1)" "3"

echo "== pass $pass fail $fail"
[ "$fail" = 0 ]
