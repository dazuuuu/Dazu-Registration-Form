#!/usr/bin/env bash
# The tutor's ID card and sample certificate previews (My ID & signature), over HTTP. Run AFTER tutor_preview_setup.php on the same scratch
# database, with the site served from it:
#   cd public && DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= DAZU_DB_NAME=dazu_tpv_test php -d extension=gd -S localhost:8006 router.php &
#   B=http://localhost:8006 bash app/v1/tests/tutor_preview_http.sh
# MODE=before checks the OLD route (id_cards/tutor_preview.php) to show the problem; the default checks the fixed routes.
B=${B:-http://localhost:8006}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/tpv_users.json"
mail() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['email'])" "$J" "$1"; }
uid() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]]['id'])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
res() { curl -s -b "$1" -o "$T/tpv_body" -w '%{http_code} %{content_type}' "$B$2" | sed 's/;.*//'; }   # "status content-type"
magic() { head -c 4 "$T/tpv_body"; }

login "$T/tp_t1" "$(mail tutor1)"; login "$T/tp_t2" "$(mail tutor2)"; login "$T/tp_stu" "$(mail student)"
rm -f "$T/tp_admin"; curl -s -c "$T/tp_admin" -o /dev/null "$B/admin/portal.php"; curl -s -b "$T/tp_admin" -c "$T/tp_admin" -o /dev/null -d "email=$(mail admin)&password=x" "$B/admin/portal.php"
# The "Tutor portal preview" (admin menu): ONE browser jar holds the admin cookie (path /) AND the preview cookie (path /tutor/), as in a real browser.
cp "$T/tp_admin" "$T/tp_prev"
TOK=$(curl -s -b "$T/tp_prev" -c "$T/tp_prev" "$B/admin/dashboard.php" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$T/tp_prev" -c "$T/tp_prev" -o /dev/null -d "csrf_token=$TOK" "$B/admin/preview-tutor-portal.php"
t "the Tutor portal preview opened (the preview cookie exists)" "$(grep -c DAZUPREVIEWT "$T/tp_prev")" "1"
t "...and it opens My ID & signature" "$(curl -s -b "$T/tp_prev" "$B/tutor/profile/index.php" | grep -ac 'tid-' | awk '{print ($1>=1)?1:0}')" "1"
T1=$(uid tutor1); T2=$(uid tutor2)

if [ "$MODE" = "before" ]; then
  echo "== The OLD route, id_cards/tutor_preview.php"
  for who in prev t1; do
    for d in card cert; do echo "   $who doc=$d -> $(res "$T/tp_$who" "/id_cards/tutor_preview.php?doc=$d")  body: $(head -c 60 "$T/tpv_body" | tr -d '\n')"; done
  done
  exit 0
fi

CARD=/tutor/profile/id_preview.php
echo "== A real tutor sees their own card and sample certificate (a watermarked PDF, drawn on screen)"
for d in card cert; do
  t "tutor 1: doc=$d is 200 and a PDF" "$(res "$T/tp_t1" "$CARD?doc=$d")" "200 application/pdf"
  t "...the file really is a PDF" "$(magic)" "%PDF"
done
t "tutor 1 also gets the photo for the page" "$(res "$T/tp_t1" "/tutor/profile/id_photo.php" | cut -d' ' -f1)" "200"
t "tutor 2 gets their own card too (a different tutor)" "$(res "$T/tp_t2" "$CARD?doc=card")" "200 application/pdf"

echo "== The Tutor portal preview tutor (the cookie only travels to /tutor/)"
for d in card cert; do
  t "preview tutor: doc=$d is 200 and a PDF" "$(res "$T/tp_prev" "$CARD?doc=$d")" "200 application/pdf"
  t "...the file really is a PDF" "$(magic)" "%PDF"
done
t "the preview tutor's page now points at the route that carries the cookie" "$(curl -s -b "$T/tp_prev" "$B/tutor/profile/index.php" | grep -ac 'tutor/profile/id_preview.php?doc=card' | awk '{print ($1>=1)?1:0}')" "1"
t "...and the old route is no longer used by the tutor's page" "$(curl -s -b "$T/tp_prev" "$B/tutor/profile/index.php" | grep -ac 'id_cards/tutor_preview.php')" "0"

echo "== Only their own"
t "tutor 2 asking for tutor 1's preview (?tutor=) gets 403" "$(res "$T/tp_t2" "$CARD?doc=card&tutor=$T1" | cut -d' ' -f1)" "403"
t "...and no PDF body" "$(magic | grep -c PDF)" "0"
t "the preview tutor asking for tutor 1's card gets 403" "$(res "$T/tp_prev" "$CARD?doc=card&tutor=$T1" | cut -d' ' -f1)" "403"
t "tutor 1 asking for tutor 2's certificate gets 403" "$(res "$T/tp_t1" "$CARD?doc=cert&tutor=$T2" | cut -d' ' -f1)" "403"
t "asking for your own id with ?tutor= is fine" "$(res "$T/tp_t1" "$CARD?doc=card&tutor=$T1" | cut -d' ' -f1)" "200"
t "a student is refused (403)" "$(res "$T/tp_stu" "$CARD?doc=card" | cut -d' ' -f1)" "403"
t "a logged-out visitor is refused (401)" "$(curl -s -o /dev/null -w '%{http_code}' "$B$CARD?doc=card")" "401"
t "the admin is not a tutor: the tutor route refuses (403), the admin route is for the admin" "$(res "$T/tp_admin" "$CARD?doc=card" | cut -d' ' -f1)" "403"
t "the photo route: tutor 2 cannot get tutor 1's photo (403)" "$(res "$T/tp_t2" "/tutor/profile/id_photo.php?u=$T1" | cut -d' ' -f1)" "403"
t "the photo route: a logged-out visitor is refused (401)" "$(curl -s -o /dev/null -w '%{http_code}' "$B/tutor/profile/id_photo.php")" "401"

echo "== The admin preview still works (existing route)"
t "admin: card of tutor 1 is 200 and a PDF" "$(res "$T/tp_admin" "/id_cards/tutor_preview.php?doc=card&tutor=$T1")" "200 application/pdf"
t "admin: sample certificate of tutor 1" "$(res "$T/tp_admin" "/id_cards/tutor_preview.php?doc=cert&tutor=$T1")" "200 application/pdf"
t "admin: the card page for the tutor renders" "$(curl -s -b "$T/tp_admin" -o /dev/null -w '%{http_code}' "$B/admin/tutors/id.php?id=$(python -c "import json,sys; print(json.load(open(sys.argv[1]))['tutor1']['tutor_id'])" "$J")")" "200"
t "a real tutor on the OLD route still works for their own card" "$(res "$T/tp_t1" "/id_cards/tutor_preview.php?doc=card")" "200 application/pdf"
t "a student on the OLD route is now 403 (not 404)" "$(res "$T/tp_stu" "/id_cards/tutor_preview.php?doc=card" | cut -d' ' -f1)" "403"
t "an admin with no ?tutor= on the OLD route is 403 (not 404)" "$(res "$T/tp_admin" "/id_cards/tutor_preview.php?doc=card" | cut -d' ' -f1)" "403"

echo "== pass $pass fail $fail"
[ "$fail" = 0 ]
