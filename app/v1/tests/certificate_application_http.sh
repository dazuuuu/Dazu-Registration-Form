#!/usr/bin/env bash
# HTTP checks for applying for a certificate. Run AFTER certificate_application_test.php on the same scratch database, with the site served from it:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_certapp php -d extension=gd -S localhost:8005 router.php &
#   B=http://localhost:8005 PHPBIN=php (same DB variables) bash app/v1/tests/certificate_application_http.sh
B=${B:-http://localhost:8005}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/certapp_users.json"
PHPBIN=${PHPBIN:-php}
jget() { python -c "import json,sys; d=json.load(open(sys.argv[1])); print(d[sys.argv[2]])" "$J" "$1"; }
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
READY_APP=$(jget _ready_app); RET_APP=$(jget _ret_app); CAPC=$(jget _capCourse); PLAINC=$(jget _plainCourse); NEWCERT=$(jget _cert); PREADY=$(jget _pready)
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { DAZU_DB_DRIVER=${DAZU_DB_DRIVER:-mysql} "$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); echo $p->query($argv[1])->fetchColumn();' "$1" 2>/dev/null; }
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }

for u in ready ret fees99 avg cat notime; do login "$T/c_$u" "$(mail $u)"; done
login "$T/c_tutor" "$(mail tutor)"; login "$T/c_tutor2" "$(mail tutor2)"
rm -f "$T/c_admin"; curl -s -c "$T/c_admin" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/c_admin" -c "$T/c_admin" -o /dev/null -d "email=$(mail admin)&password=x" "$B/admin/portal.php"

echo "== Student: the Certificates page"
P="$(curl -s -b "$T/c_fees99" "$B/students/certificates/index.php")"
t "fees at 99%: the Apply button is grey (disabled)" "$(echo "$P" | grep -ac 'btn btn-secondary flex-grow-1" disabled')" "1"
t "...there is no name form to submit" "$(echo "$P" | grep -ac 'name="print_name"')" "0"
t "...it shows a Pay the balance link and what is missing" "$(echo "$P" | grep -ac 'Pay the balance' | awk '{print ($1>=1)?1:0}')" "1"
t "...the checklist shows the fee balance, not a mark" "$(echo "$P" | grep -ac 'Fee balance of KES')" "1"
P="$(curl -s -b "$T/c_cat" "$B/students/certificates/index.php")"
t "all four true: the button opens (name check form, checkbox)" "$(echo "$P" | grep -ac 'name="print_name"')" "1"
t "...with the Apply for certificate button" "$(echo "$P" | grep -ac '>Apply for certificate</button>' | awk '{print ($1>=1)?1:0}')" "1"
P="$(curl -s -b "$T/c_ret" "$B/students/certificates/index.php")"
t "a student with a pending request sees 'You applied' and no form" "$(echo "$P" | grep -ac 'You applied on')" "1"

echo "== Apply by a direct URL while a condition is missing"
for u in fees99 avg; do
  TOK=$(token "$T/c_$u" "/students/certificates/index.php")
  R=$(curl -s -b "$T/c_$u" -c "$T/c_$u" -L -d "csrf_token=$TOK&course_id=$CAPC&print_name=Hacker Name&confirm=1" "$B/students/certificates/apply.php")
  t "direct POST to apply.php by '$u' is refused with a message" "$(echo "$R" | grep -ac 'You cannot apply yet')" "1"
done
t "...no application was created for them" "$(q "SELECT COUNT(*) FROM certificate_applications a JOIN users u ON u.id=a.student_user_id WHERE u.email IN ('$(mail fees99)','$(mail avg)')")" "0"
TOK=$(token "$T/c_cat" "/students/certificates/index.php")
t "apply.php without the CSRF token does not apply" "$(curl -s -b "$T/c_cat" -o /dev/null -d "course_id=$CAPC&print_name=Cat Cat&confirm=1" "$B/students/certificates/apply.php"; q "SELECT COUNT(*) FROM certificate_applications a JOIN users u ON u.id=a.student_user_id WHERE u.email='$(mail cat)'")" "0"
t "apply.php by GET does nothing" "$(code "$T/c_cat" "/students/certificates/apply.php?course_id=$CAPC" | cut -c1)" "3"
t "the admin cannot use apply.php" "$(curl -s -b "$T/c_admin" -o /dev/null -w '%{http_code}' -d "course_id=$CAPC&print_name=x" "$B/students/certificates/apply.php" | cut -c1)" "3"
curl -s -b "$T/c_cat" -c "$T/c_cat" -o /dev/null -d "csrf_token=$TOK&course_id=$CAPC&print_name=Catherine Cat&confirm=1" "$B/students/certificates/apply.php"
t "a student with all four true applies (after checking the name)" "$(q "SELECT CONCAT(a.status,'|',a.print_name) FROM certificate_applications a JOIN users u ON u.id=a.student_user_id WHERE u.email='$(mail cat)'")" "pending|Catherine Cat"
curl -s -b "$T/c_cat" -c "$T/c_cat" -o /dev/null -d "csrf_token=$TOK&course_id=$CAPC&print_name=Catherine Cat&confirm=1" "$B/students/certificates/apply.php"
t "a second application for the same course is refused (still one row)" "$(q "SELECT COUNT(*) FROM certificate_applications a JOIN users u ON u.id=a.student_user_id WHERE u.email='$(mail cat)'")" "1"

echo "== Sent certificate on the student's page"
P="$(curl -s -b "$T/c_ready" "$B/students/certificates/index.php")"
t "the certificate is listed on the student's Certificates page" "$(echo "$P" | grep -ac "preview.php?id=$NEWCERT" | awk '{print ($1>=1)?1:0}')" "1"
t "...with the transcript and the letter of recommendation" "$(echo "$P" | grep -ac 'capstone/transcript.php')" "1"
t "...the student downloads the certificate PDF" "$(curl -s -b "$T/c_ready" -o "$T/c_cert.pdf" -w '%{http_code} %{content_type}' "$B/admin/certificates/preview.php?id=$NEWCERT")" "200 application/pdf"
t "...another student cannot open it" "$(curl -s -b "$T/c_cat" -o /dev/null -w '%{http_code}' "$B/admin/certificates/preview.php?id=$NEWCERT")" "403"
HASH=$(q "SELECT qr_code_hash FROM certificates WHERE id=$NEWCERT")
t "the existing public verify page still works for the new certificate" "$(curl -s -o /dev/null -w '%{http_code}' "$B/verify/?c=$HASH")" "200"

echo "== Tutor"
t "the tutor sees the request list" "$(code "$T/c_tutor" "/tutor/certificates/index.php")" "200"
t "...Pending tab lists a request with 'Write and approve'" "$(curl -s -b "$T/c_tutor" "$B/tutor/certificates/index.php" | grep -ac 'Write and approve')" "1"
t "the tutor opens a request" "$(code "$T/c_tutor" "/tutor/certificates/request.php?id=$RET_APP")" "200"
t "...the page has the certificate preview and the lines box (300 letters)" "$(curl -s -b "$T/c_tutor" "$B/tutor/certificates/request.php?id=$RET_APP" | grep -ac 'name="lines"')" "2"
t "...the preview is a PDF" "$(curl -s -b "$T/c_tutor" -o /dev/null -w '%{http_code} %{content_type}' "$B/tutor/certificates/preview.php?id=$RET_APP")" "200 application/pdf"
TOK=$(token "$T/c_tutor" "/tutor/certificates/request.php?id=$RET_APP")
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&action=save&lines=covering Data Science with a final capstone project." "$B/tutor/certificates/request.php?id=$RET_APP"
t "the tutor saves the lines" "$(q "SELECT lines_text FROM certificate_applications WHERE id=$RET_APP")" "covering Data Science with a final capstone project."
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=$TOK&action=save&name=Hacked Name&print_name=Hacked&lines=covering Data Science with a final capstone project." "$B/tutor/certificates/request.php?id=$RET_APP"
t "...a name field posted with it changes nothing (only the lines can be edited)" "$(q "SELECT print_name FROM certificate_applications WHERE id=$RET_APP")" "Stuart Ret Mwangi"
curl -s -b "$T/c_tutor" -o /dev/null -d "csrf_token=bad&action=save&lines=NOCSRF" "$B/tutor/certificates/request.php?id=$RET_APP"
t "a save without a valid CSRF token is refused" "$(q "SELECT lines_text FROM certificate_applications WHERE id=$RET_APP")" "covering Data Science with a final capstone project."
t "the Mark course finished page has the end date form" "$(curl -s -b "$T/c_tutor" "$B/tutor/capstone/finish.php?student=$PREADY&course=$PLAINC" | grep -ac 'Extend the end date')" "1"

echo "== Another tutor, a student and the admin cannot open or change it"
BEFORE="$(q "SELECT CONCAT(status,'|',COALESCE(lines_text,''),'|',COALESCE(return_note,'')) FROM certificate_applications WHERE id=$RET_APP")"
for who in tutor2 ready admin; do
  t "$who cannot open the request page" "$(code "$T/c_$who" "/tutor/certificates/request.php?id=$RET_APP" | cut -c1)" "3"
  t "$who cannot open the preview" "$(curl -s -b "$T/c_$who" -o /dev/null -w '%{content_type}' "$B/tutor/certificates/preview.php?id=$RET_APP" | grep -c pdf)" "0"
  t "$who does not see the lines by following the redirect" "$(curl -s -b "$T/c_$who" -L "$B/tutor/certificates/request.php?id=$RET_APP" | grep -ac 'covering Data Science')" "0"
done
TOK2=$(token "$T/c_tutor2" "/tutor/certificates/index.php")
for act in "save&lines=HACKED" "return&note=hacked" "approve_send&lines=HACKED" "new_version&lines=HACKED&print_name=Hacked"; do
  curl -s -b "$T/c_tutor2" -o /dev/null -d "csrf_token=$TOK2&action=$act" "$B/tutor/certificates/request.php?id=$RET_APP"
done
t "another tutor posting to the request changes nothing" "$(q "SELECT CONCAT(status,'|',COALESCE(lines_text,''),'|',COALESCE(return_note,'')) FROM certificate_applications WHERE id=$RET_APP")" "$BEFORE"
TOKS=$(token "$T/c_ready" "/students/certificates/index.php")
curl -s -b "$T/c_ready" -o /dev/null -d "csrf_token=$TOKS&action=save&lines=HACKED" "$B/tutor/certificates/request.php?id=$RET_APP"
t "a student posting to the request changes nothing" "$(q "SELECT CONCAT(status,'|',COALESCE(lines_text,''),'|',COALESCE(return_note,'')) FROM certificate_applications WHERE id=$RET_APP")" "$BEFORE"
curl -s -b "$T/c_admin" -o /dev/null -d "action=save&lines=HACKED" "$B/tutor/certificates/request.php?id=$RET_APP"
t "the admin posting to the request changes nothing" "$(q "SELECT CONCAT(status,'|',COALESCE(lines_text,''),'|',COALESCE(return_note,'')) FROM certificate_applications WHERE id=$RET_APP")" "$BEFORE"
t "the other tutor's list is empty of this student's requests" "$(curl -s -b "$T/c_tutor2" "$B/tutor/certificates/index.php?tab=pending" | grep -ac 'Stu Ret')" "0"

echo "== Admin: words only"
P="$(curl -s -b "$T/c_admin" "$B/admin/certificates/index.php")"
t "the admin Certificates page lists applications with status words" "$(echo "$P" | grep -ac 'Certificate applications')" "1"
t "...it shows Sent and Applied" "$(echo "$P" | grep -ac '>Sent<\|>Applied<' | awk '{print ($1>=1)?1:0}')" "1"
t "...it shows no lines, no note and no printed-name text of the request" "$(echo "$P" | grep -aciE 'covering Data Science|covering Python|Please write your full second name|Stuart Ret Mwangi|Catherine Cat')" "0"

echo "== pass $pass fail $fail"
[ "$fail" = 0 ]
