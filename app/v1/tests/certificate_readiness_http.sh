#!/usr/bin/env bash
# HTTP checks for the transcript / letter release and the certificate rules. Run AFTER certificate_readiness_test.php on the same
# scratch database, with the site served from that database, for example:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_mytest \
#       php -S localhost:8003 router.php &
#   B=http://localhost:8003 DB_ENV="..." bash app/v1/tests/certificate_readiness_http.sh
# The test users all have the password "x" (see certificate_readiness_test.php).
B=${B:-http://localhost:8003}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/readiness_users.json"
get() { python -c "import json,sys; d=json.load(open(sys.argv[1])); print(d[sys.argv[2]]$3)" "$J" "$1"; }
RUN=$(get _run ''); COURSE=$(get _course ''); CAP=$(get _cap '')
uid() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['id'])" "$J" "$1"; }
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }

login "$T/h_ready" "$(mail ready)"; login "$T/h_notfin" "$(mail notfin)"; login "$T/h_other" "$(mail other)"; login "$T/h_tutor" "$(mail tutor)"
rm -f "$T/h_admin"; curl -s -c "$T/h_admin" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/h_admin" -c "$T/h_admin" -o /dev/null -d "email=$(mail admin)&password=x" "$B/admin/portal.php"
READY=$(uid ready); NOTFIN=$(uid notfin); OTHER=$(uid other); UNPAID=$(uid unpaid)
ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
export ROOT READY NOTFIN COURSE

echo "== Student: transcript and letter"
t "a Ready student opens the transcript" "$(code "$T/h_ready" "/capstone/transcript.php?course=$COURSE")" "200"
t "...and downloads the letter (PDF)" "$(curl -s -b "$T/h_ready" -o "$T/h_letter.pdf" -w '%{http_code} %{content_type}' "$B/capstone/letter.php?course=$COURSE&dl=1")" "200 application/pdf"
t "...the letter file is a real PDF" "$(head -c 4 "$T/h_letter.pdf")" "%PDF"
t "a student who is NOT ready cannot open the transcript (not allowed)" "$(code "$T/h_notfin" "/capstone/transcript.php?course=$COURSE")" "403"
t "...nor the letter" "$(code "$T/h_notfin" "/capstone/letter.php?course=$COURSE")" "403"
t "a student with nothing finished cannot open it either" "$(code "$T/h_other" "/capstone/transcript.php?course=$COURSE")" "403"
t "changing the URL to another student's id does not open theirs (transcript)" "$(curl -s -b "$T/h_other" -o /dev/null -w '%{http_code}' "$B/capstone/transcript.php?course=$COURSE&student=$READY")" "403"
t "changing the URL to another student's id does not open theirs (letter)" "$(curl -s -b "$T/h_other" -o /dev/null -w '%{http_code}' "$B/capstone/letter.php?course=$COURSE&student=$READY")" "403"
t "a Ready student asking for another student's id still only sees their own" "$(curl -s -b "$T/h_ready" "$B/capstone/transcript.php?course=$COURSE&student=$NOTFIN" | grep -ac 'Stu Ready')" "1"
t "the not-allowed page explains why, with no mark" "$(curl -s -b "$T/h_notfin" "$B/capstone/transcript.php?course=$COURSE" | grep -ac 'released when your course is complete')" "1"

echo "== Tutor"
t "the tutor opens the transcript of a Ready student" "$(code "$T/h_tutor" "/capstone/transcript.php?course=$COURSE&student=$READY")" "200"
t "the tutor sees a not-released transcript as a DRAFT" "$(curl -s -b "$T/h_tutor" "$B/capstone/transcript.php?course=$COURSE&student=$NOTFIN" | grep -ac 'DRAFT')" "1"
t "the tutor previews the letter of a student before release (draft PDF)" "$(curl -s -b "$T/h_tutor" -o /dev/null -w '%{http_code} %{content_type}' "$B/capstone/letter.php?course=$COURSE&student=$NOTFIN")" "200 application/pdf"

echo "== Admin never sees the letter, a mark or a comment"
t "the admin cannot open a letter" "$(code "$T/h_admin" "/capstone/letter.php?course=$COURSE&student=$READY")" "403"
t "the admin cannot open a transcript (the tutor did not allow a preview)" "$(code "$T/h_admin" "/capstone/transcript.php?course=$COURSE&student=$READY")" "403"
t "a direct URL to a capstone mark returns not allowed (redirected away)" "$(code "$T/h_admin" "/tutor/capstone/mark.php?id=1" | cut -c1-1)" "3"
t "the admin cannot open capstone files" "$(code "$T/h_admin" "/capstone/file.php?t=item&id=1")" "404"
PAGE="$(curl -s -b "$T/h_admin" "$B/admin/certificates/")"
t "the admin Certificates page loads and shows the capstone words" "$(echo "$PAGE" | grep -ac 'Capstone and certificate readiness')" "1"
t "...and shows no comment, no tutor sentence, no letter text" "$(echo "$PAGE" | grep -aciE 'SECRET|calmly|Yours faithfully|Distinction|LETTER OF RECOMMENDATION')" "0"
echo "$PAGE" | sed 's/<[^>]*>/ /g' | tr -s ' \n' ' ' | grep -aoE "Stu (Ready|Unpaid|Failed|Avg49|Held)[^S]{0,90}" | head -5 | sed 's/^/   admin sees: /'

echo "== Certificate: direct URL cannot issue when a condition fails"
CERTID=$(DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} php -r '
require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $c=require getenv("ROOT")."/app/v1/config/database.php"; $p=DazuPdo::open($c);
$st=$p->prepare("SELECT c.id FROM certificates c JOIN registrations r ON r.id=c.registration_id WHERE r.user_id=? AND c.status=\x27being_prepared\x27 ORDER BY c.id DESC LIMIT 1"); $st->execute([(int)getenv("NOTFIN")]); echo $st->fetchColumn();' 2>/dev/null)
if [ -n "$CERTID" ]; then
  TOK=$(curl -s -b "$T/h_admin" -c "$T/h_admin" "$B/admin/certificates/edit.php?id=$CERTID" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$T/h_admin" -c "$T/h_admin" -o /dev/null -d "csrf_token=$TOK&status=issued&snapshot_duration_months=12&taught_skills=x" "$B/admin/certificates/edit.php?id=$CERTID"
  export CERTID
  STATUS=$(DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} php -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); echo $p->query("SELECT status FROM certificates WHERE id=".(int)getenv("CERTID"))->fetchColumn();' 2>/dev/null)
  t "the admin edit page (direct POST status=issued) is refused: the certificate stays not issued" "$STATUS" "being_prepared"
else
  echo "skip  (no prepared certificate found for the not-finished student)"
fi

echo "== Public check of a letter"
HASH=$(DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} php -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $st=$p->prepare("SELECT letter_hash FROM course_release WHERE student_user_id=? AND course_id=?"); $st->execute([(int)getenv("READY"),(int)getenv("COURSE")]); echo $st->fetchColumn();' 2>/dev/null)
V="$(curl -s "$B/verify/letter.php?k=$HASH")"
t "the QR check page says Verified for a released letter" "$(echo "$V" | grep -ac 'Verified letter')" "1"
t "...and shows no mark or result" "$(echo "$V" | sed 's/<[^>]*>/ /g' | grep -aciE 'distinction|credit|[0-9]+ ?/ ?100|mark')" "0"
t "a wrong code shows the same 'cannot verify' page" "$(curl -s "$B/verify/letter.php?k=$(printf 'a%.0s' {1..32})" | grep -ac 'cannot verify')" "1"

echo "== pass $pass fail $fail"
[ "$fail" = 0 ]
