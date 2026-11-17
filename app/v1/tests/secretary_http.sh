#!/usr/bin/env bash
# HTTP checks for the Secretary role: sign-in routing, forced password change, the guard on every request, who may open the admin pages,
# the form (CSRF, escaping, saving, email), and the menu line. Run AFTER secretary_test.php on the same scratch database, with the site served from it:
#   cd public && DAZU_DB_NAME=<scratch> MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_ENCRYPTION= MAIL_USERNAME=u MAIL_PASSWORD=p php -S localhost:8011 router.php &
#   php app/v1/tests/smtp_sink.php 2525 <dir> &        B=http://localhost:8011 SINK=<dir> bash app/v1/tests/secretary_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_users.json"
SINK=${SINK:-$T/smtp_sink}
PHPBIN=${PHPBIN:-php}
jmail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
token() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
where() { curl -s -b "$1" -o /dev/null -w "%{redirect_url}" "$B$2" | sed "s#$B##;s/?.*//"; }
body() { curl -s -b "$1" "$B$2"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
has() { echo "$2" | grep -aqF -- "$3" && echo yes || echo no; }

D=/secretary/dashboard/index.php
echo "== Sign-in sends a secretary to her own portal"
C="$T/s_new"; login "$C" "$(jmail sec_new)"
t "first sign-in goes to the change-password page" "$(curl -s -b "$C" -o /dev/null -w '%{redirect_url}' -d "email=$(jmail sec_new)&password=x" -c "$C" "$B/login" | sed "s#$B##")" "/auth/change_password.php"
t "before she chooses a password the dashboard sends her to change it" "$(where "$C" $D)" "/auth/change_password.php"
t "a short new password is refused" "$(curl -s -b "$C" -c "$C" -d 'password=abc&password_confirm=abc' "$B/auth/change_password.php" | grep -ac 'at least 8')" "1"
t "two different passwords are refused" "$(curl -s -b "$C" -c "$C" -d 'password=Longer1234&password_confirm=Other1234' "$B/auth/change_password.php" | grep -ac 'do not match')" "1"
t "a good new password lands her on the secretary dashboard" "$(curl -s -b "$C" -c "$C" -o /dev/null -w '%{redirect_url}' -d 'password=Longer1234&password_confirm=Longer1234' "$B/auth/change_password.php" | sed "s#$B##")" "/secretary/dashboard/index.php"
t "the dashboard opens (200)" "$(code "$C" $D)" "200"
H=$(body "$C" $D)
t "it greets her by first name (Nairobi time of day)" "$(echo "$H" | grep -aoE 'Good (morning|afternoon|evening), Secnew' | wc -l | tr -d ' ')" "1"
t "it uses the Dazu green" "$(has x "$H" '#048C44')" "yes"
t "the menu shows Enquiries (switch on) and not My ID Card (switch off)" "$(has x "$H" '<span>Enquiries</span>') $(has x "$H" '<span>My ID Card</span>')" "yes no"
C="$T/s_ok"; login "$C" "$(jmail sec_ok)"
t "a secretary signing in again goes straight to her dashboard" "$(curl -s -b "$C" -c "$C" -o /dev/null -w '%{redirect_url}' -d "email=$(jmail sec_ok)&password=x" "$B/login" | sed "s#$B##")" "/secretary/dashboard/index.php"
t "the sign-in page offers 'Continue' to her dashboard" "$(curl -s -b "$C" "$B/login" | grep -ac 'secretary/dashboard\|/secretary/dashboard')" "1"

echo "== The server checks the role and status on every request"
for who in student tutor admin; do login "$T/s_$who" "$(jmail $who)"; t "$who cannot open the secretary dashboard" "$(where "$T/s_$who" $D)" "/auth/login.php"; done
t "nobody signed in cannot open it" "$(where "$T/s_none" $D)" "/auth/login.php"
login "$T/s_sec_leave" "$(jmail sec_leave)"; t "On leave does not block her: the dashboard opens" "$(code "$T/s_sec_leave" $D)" "200"
for who in sec_left sec_off; do login "$T/s_$who" "$(jmail $who)"; t "$who is turned away" "$(code "$T/s_$who" $D)" "302"; done
t "a secretary whose status changes to Left while signed in is turned away on the next request" "$("$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r '
 require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php");
 $p->exec("UPDATE secretaries SET status=\x27left\x27 WHERE user_id=".(int)$argv[1]); echo "done";' "$(python -c "import json;print(json.load(open('$J'))['sec_ok']['id'])")" 2>&1 | tail -1) $(code "$C" $D)" "done 302"
t "a secretary cannot open the admin pages" "$(code "$T/s_sec_ok" /admin/secretaries/index.php)" "403"

echo "== The admin pages"
S="$T/s_admin"; rm -f "$S"; curl -s -c "$S" -o /dev/null "$B/admin/portal.php"; curl -s -b "$S" -c "$S" -o /dev/null -d "email=$(jmail admin)&password=x" "$B/admin/portal.php"
SUP="$T/s_super"; rm -f "$SUP"; TOKU=$(token "$SUP" /superadmin/login.php); curl -s -b "$SUP" -c "$SUP" -o /dev/null -d "email=$(jmail super)&password=x&csrf_token=$TOKU" "$B/superadmin/login.php"
t "the super admin is refused on the list, the form and an edit (plain 403)" "$(code "$SUP" /admin/secretaries/index.php) $(code "$SUP" /admin/secretaries/create.php) $(code "$SUP" '/admin/secretaries/create.php?id=1') $(body "$SUP" /admin/secretaries/index.php)" "403 403 403 Not allowed."
t "nobody signed in is refused (plain 403)" "$(code "$T/s_nobody" /admin/secretaries/index.php) $(body "$T/s_nobody" /admin/secretaries/create.php)" "403 Not allowed."
t "the old super admin addresses no longer exist" "$(code "$SUP" /superadmin/secretaries/index.php) $(code "$SUP" /superadmin/secretaries/create.php) $(code "$SUP" /superadmin/secretaries/switch.php)" "404 404 404"
t "a student cannot open the list" "$(code "$T/s_student" /admin/secretaries/index.php)" "403"
t "a student cannot open the form" "$(code "$T/s_student" /admin/secretaries/create.php)" "403"
t "a tutor cannot open the form" "$(code "$T/s_tutor" /admin/secretaries/create.php)" "403"
t "the admin opens the list" "$(code "$S" /admin/secretaries/index.php)" "200"
t "the admin opens the form" "$(code "$S" /admin/secretaries/create.php)" "200"
F=$(body "$S" /admin/secretaries/create.php)
t "the admin menu has the Secretaries line (side menu and phone menu)" "$(echo "$F" | grep -ao 'admin/secretaries/index.php' | wc -l | tr -d ' ' | awk '{print ($1>=2)?"yes":"no"}')" "yes"
t "the super admin menu has no Secretaries line" "$(body "$SUP" /superadmin/dashboard.php | grep -aci 'secretaries')" "0"
for f in 'name="first_name"' 'name="last_name"' 'name="email"' 'name="phone"' 'name="branch"' 'value="Secretary"' 'name="birth_day"' 'name="birth_month"' 'name="start_date"' 'name="emergency_name"' 'name="emergency_phone"' 'name="photo"' 'Create secretary' 'Added by'; do
  t "form has: $f" "$(has x "$F" "$f")" "yes"; done
t "branch offers Nairobi, Thika, Online" "$(echo "$F" | grep -ac '>Nairobi<\|>Thika<\|>Online<')" "3"
t "seven switches" "$(echo "$F" | grep -ao 'name="sw\[[a-z_]*\]"' | sort -u | wc -l | tr -d ' ')" "7"
t "blogs approval starts on, the ID card off, the calendar on View only" "$(echo "$F" | tr -d '\n' | grep -aoE 'name="sw\[(blogs_approval|id_card)\]" value="on" [^>]*>|name="sw\[staff_calendar\]" value="view" checked' | sed -E 's/ aria-label.*//' | tr '\n' '|')" 'name="sw[blogs_approval]" value="on" checked|name="sw[staff_calendar]" value="view" checked|name="sw[id_card]" value="on" |'
t "'Added by' shows the admin's name only, never the role" "$(echo "$F" | grep -aoE 'id="added_by" value="[^"]*"' | sed 's/.*value=//')" '"Adm Test"'
t "the ID number field is locked and cannot be typed in" "$(echo "$F" | grep -aoE '<input[^>]*id="staff_no"[^>]*>' | grep -ac 'disabled')" "1"
t "the form has a CSRF token" "$([ $(echo "$F" | grep -ac 'name="csrf_token"') -ge 1 ] && echo yes)" "yes"
t "the mobile layout rule is there (one column under 768px)" "$(echo "$F" | grep -ac 'max-width: 767.98px')" "1"

echo "== Saving"
TOK=$(token "$S" /admin/secretaries/create.php)
EM="http$RANDOM@sec.test"
post() { curl -s -b "$S" -c "$S" -o "$T/s_out.html" -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$1" --form-string "first_name=$2" --form-string "last_name=Mutua" --form-string "email=$3" --form-string "phone=0722000111" --form-string "branch=thika" --form-string "job_title=" --form-string "birth_day=5" --form-string "birth_month=7" --form-string "start_date=2026-11-02" --form-string "sw[staff_calendar]=edit" --form-string "sw[id_card]=on" --form-string "sw[blogs_approval]=off" "$B/admin/secretaries/create.php"; }
rm -rf "$SINK"/*.eml 2>/dev/null
t "no CSRF token: refused, nothing saved" "$(post "" Ann "nocsrf$RANDOM@sec.test" | cut -d' ' -f1) $(grep -ac 'security check failed' "$T/s_out.html")" "200 1"
t "a wrong token: refused" "$(post deadbeef Ann "badcsrf$RANDOM@sec.test" | cut -d' ' -f1) $(grep -ac 'security check failed' "$T/s_out.html")" "200 1"
t "a missing first name shows an error and keeps what was typed" "$(post "$TOK" "" "$EM" | cut -d' ' -f1) $(grep -ac 'Enter her first name' "$T/s_out.html") $(grep -ac 'value="Mutua"' "$T/s_out.html")" "200 1 1"
XSS='<script>alert(1)</script>'
t "a good form saves and goes back to the list" "$(post "$TOK" "$XSS" "$EM" | sed "s#$B##")" "302 /admin/secretaries/index.php"
L=$(body "$S" /admin/secretaries/index.php)
t "the list shows her, her ID number and Thika" "$(has x "$L" 'DTI/SEC/') $(has x "$L" 'Thika')" "yes yes"
t "a script in the name is shown as text, never run" "$(has x "$L" '<script>alert(1)</script>') $(has x "$L" '&lt;script&gt;alert(1)&lt;/script&gt;')" "no yes"
t "the list says 'Added by' with the admin's name" "$(has x "$L" 'Added by Adm Test')" "yes"
sleep 1
M=$(cat "$SINK"/*.eml 2>/dev/null | tr -d '\r')
t "the email has her address" "$(echo "$M" | grep -aqF "$EM" && echo yes || echo no)" "yes"
echo "== Sign in with the emailed password"
PW=$(echo "$M" | tr -d '\n' | sed 's/=3D/=/g;s/=$//' | grep -aoE 'Temporary password: *(<strong>)?[0-9a-f]{16}' | grep -aoE '[0-9a-f]{16}$' | head -1)
t "a 16-character temporary password was in the email" "${#PW}" "16"
NC="$T/s_made"; rm -f "$NC"; curl -s -c "$NC" -o /dev/null "$B/login"
t "she signs in with it and is sent to choose her own password" "$(curl -s -b "$NC" -c "$NC" -o /dev/null -w '%{redirect_url}' -d "email=$EM&password=$PW" "$B/login" | sed "s#$B##")" "/auth/change_password.php"
t "then she reaches the dashboard" "$(curl -s -b "$NC" -c "$NC" -o /dev/null -w '%{redirect_url}' -d 'password=MyOwn12345&password_confirm=MyOwn12345' "$B/auth/change_password.php" | sed "s#$B##")" "/secretary/dashboard/index.php"
t "the old temporary password no longer works" "$(rm -f "$T/s_made2"; curl -s -c "$T/s_made2" -o /dev/null "$B/login"; curl -s -b "$T/s_made2" -c "$T/s_made2" -o /dev/null -w '%{http_code}' -d "email=$EM&password=$PW" "$B/login")" "200"
t "the list's 'Reset password' needs a token too" "$(curl -s -b "$S" -o /dev/null -w '%{http_code}' -d 'action=reset&id=1' "$B/admin/secretaries/index.php")" "302"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
