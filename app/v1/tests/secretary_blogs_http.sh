#!/usr/bin/env bash
# HTTP checks for the secretary Blogs page, the editor, the approval flow, the admin's Blog approvals page, and that the admin's own blog pages and the public blog still work.
# Run AFTER secretary_blogs_test.php on the same scratch database, with the site served from it (see secretary_portal_http.sh for the server line; MAIL_* are not needed here).
#   B=http://localhost:8011 bash app/v1/tests/secretary_blogs_http.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
J="$T/secretary_blogs_users.json"
PHPBIN=${PHPBIN:-php}
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
jv() { python -c "import json,sys; d=json.load(open(sys.argv[1]))[sys.argv[2]]; print(d['email'] if isinstance(d,dict) else d)" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
pub() { local sl; sl=$(q "SELECT slug FROM blogs WHERE title='$1' ORDER BY id DESC LIMIT 1"); [ -n "$sl" ] && [ "$(curl -s "$B/blogs/readmore.php?slug=$sl" | grep -ac "$1")" -ge 1 ] && echo yes || echo no; }   # the public blog page of that title (not the paged list)
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
where() { curl -s -b "$1" -o /dev/null -w "%{redirect_url}" "$B$2" | sed "s#$B##;s/?.*//"; }
body() { curl -s -b "$1" "$B$2"; }
has() { echo "$2" | grep -aqF -- "$3" && echo yes || echo no; }
tok() { curl -s -b "$1" -c "$1" "$B$2" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
q() { "$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $r=$p->query($argv[1]); echo $r ? $r->fetchColumn() : "";' "$1" 2>/dev/null; }
RUN=$(jv _run); CAT=$(jv _cat)
CV="$T/cover_$RUN.png"; "$PHPBIN" -r '$i=imagecreatetruecolor(300,200); imagefill($i,0,0,imagecolorallocate($i,4,140,68)); imagepng($i,$argv[1]);' "$CV"
login "$T/b_first" "$(jv first)"; login "$T/b_later" "$(jv later)"; login "$T/b_noblog" "$(jv noblog)"
A="$T/b_admin"; rm -f "$A"; curl -s -c "$A" -o /dev/null "$B/admin/portal.php"; curl -s -b "$A" -c "$A" -o /dev/null -d "email=$(jv admin)&password=x" "$B/admin/portal.php"
SUP="$T/b_super"; rm -f "$SUP"; TU=$(tok "$SUP" /superadmin/login.php); curl -s -b "$SUP" -c "$SUP" -o /dev/null -d "email=$(jv super)&password=x&csrf_token=$TU" "$B/superadmin/login.php"
BL=/secretary/blogs/index.php

echo "== Who may open the Blogs page"
t "a secretary with the Blogs switch on opens it" "$(code "$T/b_first" $BL) $(code "$T/b_later" $BL)" "200 200"
t "a secretary with it off is refused by the server, on every blog address" "$(for p in index.php edit.php save.php upload_image.php delete.php; do echo "$(code "$T/b_noblog" /secretary/blogs/$p)"; done | tr '\n' ' ')" "403 403 403 403 403 "
t "nobody signed in is sent to sign in" "$(where "$T/b_nobody" $BL)" "/auth/login.php"
t "the admin and the super admin are not secretaries: sent away" "$(where "$A" $BL) $(where "$SUP" $BL)" "/auth/login.php /auth/login.php"

echo "== The editor"
E=$(body "$T/b_first" /secretary/blogs/edit.php); EL=$(body "$T/b_later" /secretary/blogs/edit.php)
for f in 'name="title"' 'name="excerpt"' 'name="content"' 'name="meta_description"' 'name="featured_image"' 'name="category_id"' 'name="tags"' 'name="is_featured"' 'name="meta_title"' 'name="meta_keywords"' 'name="csrf_token"'; do t "the editor has $f" "$(has x "$E" "$f")" "yes"; done
t "it is the admin's editor: Summernote with the Video, Columns and FAQ buttons (the admin's _editor.php is included)" "$(has x "$E" 'summernote-lite') $(has x "$E" 'function dzInitEditor') $(has x "$E" 'dzColumns')" "yes yes yes"
t "approval off: a Draft/Published choice and a Save post button" "$(has x "$E" '<option value="published">Published</option>') $(has x "$E" 'Save post')" "yes yes"
t "approval on: no status choice, a notice about waiting, and Send for approval" "$(has x "$EL" '<option value="published">Published</option>') $(has x "$EL" 'Waiting for approval') $(has x "$EL" 'Send for approval')" "no yes yes"
t "it fits a phone (one column under 992px via the grid, toolbar scrolls)" "$(has x "$E" 'class="col-lg-8"') $(has x "$E" 'max-width: 767.98px')" "yes yes"
t "an unknown blog is a 404" "$(code "$T/b_first" '/secretary/blogs/edit.php?id=999999')" "404"

echo "== Saving: security"
TK=$(tok "$T/b_first" /secretary/blogs/edit.php)
save() { curl -s -b "$1" -H 'Accept: application/json' -w ' [%{http_code}]' -F "csrf_token=$2" --form-string "title=$3" --form-string "excerpt=ex" --form-string "content=$4" --form-string "meta_description=md" --form-string "category_id=$CAT" --form-string "tags=Alpha, Beta" --form-string "is_featured=1" --form-string "status=${5:-published}" --form-string "blog_id=${6:-0}" --form-string "sub_id=${7:-0}" ${8:+-F "featured_image=@$8;type=image/png"} "$B/secretary/blogs/save.php"; }
t "no token: refused (403), nothing saved" "$(save "$T/b_first" "" "NoToken $RUN" '<p>x</p>' published 0 0 "$CV" | grep -ao '\[[0-9]*\]') $(q "SELECT COUNT(*) FROM blogs WHERE title='NoToken $RUN'")" "[403] 0"
t "a wrong token: refused (403)" "$(save "$T/b_first" deadbeef "BadToken $RUN" '<p>x</p>' published 0 0 "$CV" | grep -ao '\[[0-9]*\]')" "[403]"
t "GET is refused (405)" "$(code "$T/b_first" /secretary/blogs/save.php)" "405"

echo "== Approval OFF (the first secretary): live at once"
TITLE1="Live post $RUN"
R=$(save "$T/b_first" "$TK" "$TITLE1" '<p>Live body <strong>text</strong></p><script>alert(1)</script><p onclick="x()">click</p>' published 0 0 "$CV")
t "a new post is saved and goes live" "$(has x "$R" '"ok":true') $(has x "$R" '"mode":"live"')" "yes yes"
SLUG=$(q "SELECT slug FROM blogs WHERE title='$TITLE1'"); BID1=$(q "SELECT id FROM blogs WHERE title='$TITLE1'")
t "it is in the existing blogs table, published, with her as the author" "$(q "SELECT status FROM blogs WHERE id=$BID1") $(q "SELECT author_id FROM blogs WHERE id=$BID1")" "published $(python -c "import json;print(json.load(open('$J'))['first']['id'])")"
t "the body was cleaned: no script, no onclick; the text stays" "$(q "SELECT content FROM blogs WHERE id=$BID1" | grep -ac '<script\|onclick') $(q "SELECT content FROM blogs WHERE id=$BID1" | grep -ac 'Live body')" "0 1"
t "the cover was saved through the existing cover code" "$(q "SELECT featured_image FROM blogs WHERE id=$BID1" | grep -ac '/uploads/blogs/')" "1"
PUB=$(curl -s "$B/blogs/readmore.php?slug=$SLUG")
t "the public blog page shows it" "$(has x "$PUB" "$TITLE1")" "yes"
t "the public blog list shows it" "$(pub "$TITLE1")" "yes"
R=$(save "$T/b_first" "$TK" "Live post $RUN edited" '<p>Edited body</p>' published "$BID1" 0)
t "changing it goes live at once too" "$(has x "$R" '"mode":"live"') $(q "SELECT title FROM blogs WHERE id=$BID1")" "yes Live post $RUN edited"
L=$(body "$T/b_first" $BL)
t "the list shows it as Published with a date and the buttons Edit and Unpublish" "$(has x "$L" "Live post $RUN edited") $(has x "$L" '>Published<') $(has x "$L" 'Unpublish</button>') $(has x "$L" 'Edit</a>')" "yes yes yes yes"
t "there is no delete button or word anywhere on the list" "$(echo "$L" | grep -aci 'delete\|remove')" "0"
t "the four filters, with counts" "$(echo "$L" | grep -aoE '>(All|Published|Waiting for approval|Unpublished) <b>[0-9]+<' | wc -l | tr -d ' ')" "4"
t "a New blog button" "$(has x "$L" 'New blog')" "yes"
t "phones: the table becomes cards (rule under 992px)" "$(has x "$L" 'max-width: 991.98px') $(has x "$L" 'data-label="Actions"')" "yes yes"
TL=$(tok "$T/b_first" $BL)
act() { curl -s -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$2" --form-string "action=$3" --form-string "blog_id=$4" "$B/secretary/blogs/index.php" | sed "s#$B##"; }
t "Unpublish: back to the list, the blog is a draft, the public no longer shows it" "$(act "$T/b_first" "$TL" unpublish $BID1) $(q "SELECT status FROM blogs WHERE id=$BID1") $(pub "$TITLE1")" "302 /secretary/blogs/index.php draft no"
L=$(body "$T/b_first" "$BL?f=unpublished")
t "the Unpublished filter shows it with Publish again" "$(has x "$L" 'Publish again</button>') $(has x "$L" ">Unpublished<")" "yes yes"
t "Publish again: published, and public again" "$(act "$T/b_first" "$TL" publish $BID1) $(q "SELECT status FROM blogs WHERE id=$BID1") $(pub "Live post $RUN edited")" "302 /secretary/blogs/index.php published yes"
t "unpublish and publish again are recorded with her name and a time" "$(q "SELECT COUNT(*) FROM secretary_blog_log WHERE blog_id=$BID1 AND action IN ('unpublished','republished') AND actor_name='Faith First' AND created_at IS NOT NULL")" "2"
t "an action without the token changes nothing" "$(curl -s -b "$T/b_first" -o /dev/null -w '%{http_code}' -d "action=unpublish&blog_id=$BID1" "$B/secretary/blogs/index.php") $(q "SELECT status FROM blogs WHERE id=$BID1")" "302 published"

echo "== A secretary can never delete"
t "a delete sent to the list is refused (403) and the blog is still there" "$(curl -s -b "$T/b_first" -o /dev/null -w '%{http_code}' -d "action=delete&blog_id=$BID1&csrf_token=$TL" "$B/secretary/blogs/index.php") $(q "SELECT COUNT(*) FROM blogs WHERE id=$BID1")" "403 1"
t "the delete address is refused (403), for both secretaries" "$(curl -s -b "$T/b_first" -o /dev/null -w '%{http_code}' -d "id=$BID1" "$B/secretary/blogs/delete.php") $(curl -s -b "$T/b_later" -o /dev/null -w '%{http_code}' -d "id=$BID1" "$B/secretary/blogs/delete.php") $(q "SELECT COUNT(*) FROM blogs WHERE id=$BID1")" "403 403 1"
t "the admin's own delete service refuses a secretary, and the blog is still there" "$(curl -s -b "$T/b_first" -H 'Content-Type: application/json' -d "{\"id\":$BID1}" "$B/api/blog/delete.php" | grep -ac '"success":false') $(q "SELECT COUNT(*) FROM blogs WHERE id=$BID1")" "1 1"

echo "== Approval ON (a later secretary): it waits"
TK2=$(tok "$T/b_later" /secretary/blogs/edit.php)
TITLE2="Waiting post $RUN"
R=$(save "$T/b_later" "$TK2" "$TITLE2" '<p>Waiting body</p>' published 0 0 "$CV")
t "a new post is saved as waiting" "$(has x "$R" '"mode":"waiting"') $(q "SELECT COUNT(*) FROM secretary_blog_submissions WHERE title='$TITLE2' AND status='waiting' AND kind='new'")" "yes 1"
t "it is NOT in the blogs table and the public does not see it" "$(q "SELECT COUNT(*) FROM blogs WHERE title='$TITLE2'") $(pub "$TITLE2")" "0 no"
SUB=$(q "SELECT id FROM secretary_blog_submissions WHERE title='$TITLE2'")
L2=$(body "$T/b_later" $BL)
t "her list shows Waiting for approval" "$(has x "$L2" "$TITLE2") $(has x "$L2" '>Waiting for approval<')" "yes yes"
t "the Waiting filter has it" "$(body "$T/b_later" "$BL?f=waiting" | grep -ac "$TITLE2")" "1"
R=$(save "$T/b_later" "$TK2" "Change to live $RUN" '<p>Pending body</p>' published "$BID1" 0)
t "a change to a published blog waits; the public keeps the old version" "$(has x "$R" '"mode":"waiting"') $(q "SELECT title FROM blogs WHERE id=$BID1") $(curl -s "$B/blogs/readmore.php?slug=$(q "SELECT slug FROM blogs WHERE id=$BID1")" | grep -ac 'Edited body') $(curl -s "$B/blogs/readmore.php?slug=$(q "SELECT slug FROM blogs WHERE id=$BID1")" | grep -ac 'Pending body')" "yes Live post $RUN edited 1 0"
SUBC=$(q "SELECT id FROM secretary_blog_submissions WHERE blog_id=$BID1 AND kind='change' ORDER BY id DESC LIMIT 1")
t "the blog still shows Published, with Change waiting for approval" "$(body "$T/b_later" $BL | grep -ac 'Change waiting for approval')" "1"
save "$T/b_first" "$TK" "Never published $RUN" '<p>draft</p>' draft 0 0 "$CV" >/dev/null; NID=$(q "SELECT id FROM blogs WHERE title='Never published $RUN'")
TL2=$(tok "$T/b_later" $BL)
t "a post that was never published cannot be published by her (it needs approval): no button, and the server refuses" "$(body "$T/b_later" "$BL?f=unpublished" | grep -c "name=\"blog_id\" value=\"$NID\"") $(act "$T/b_later" "$TL2" publish $NID | cut -d' ' -f1) $(q "SELECT status FROM blogs WHERE id=$NID")" "0 302 draft"

echo "== The admin: Blog approvals"
AP=/admin/secretaries/blog-approvals.php
t "the admin opens it" "$(code "$A" $AP) $(code "$A" /admin/secretaries/blog-preview.php?id=$SUB) $(code "$A" /admin/secretaries/blog-compare.php?id=$SUBC)" "200 200 200"
t "the super admin, a secretary and nobody are refused with a plain Not allowed (403)" "$(for w in b_super b_first b_later b_nobody; do echo "$(code "$T/$w" $AP) $(body "$T/$w" $AP | head -c 12)"; done | tr '\n' '|')" "403 Not allowed.|403 Not allowed.|403 Not allowed.|403 Not allowed.|"
t "preview, compare and the actions are refused too" "$(for w in b_super b_later; do for p in "/admin/secretaries/blog-preview.php?id=$SUB" "/admin/secretaries/blog-compare.php?id=$SUBC"; do echo "$(code "$T/$w" "$p")"; done; done | tr '\n' ' ') $(curl -s -b "$SUP" -o /dev/null -w '%{http_code}' -d "action=approve&id=$SUB&rev=1" "$B$AP") $(curl -s -b "$T/b_later" -o /dev/null -w '%{http_code}' -d "action=approve&id=$SUB&rev=1" "$B$AP")" "403 403 403 403  403 403"
t "and nothing was published by those refused attempts" "$(q "SELECT COUNT(*) FROM blogs WHERE title='$TITLE2'")" "0"
APL=$(body "$A" $AP)
t "the list has both items, who sent them, and the four buttons" "$(has x "$APL" "$TITLE2") $(has x "$APL" 'From Lena Later') $(has x "$APL" 'Change to a live blog') $(echo "$APL" | grep -ac 'Approve and publish</button>') $(has x "$APL" 'Preview</a>') $(has x "$APL" 'Compare') $(has x "$APL" 'Send back')" "yes yes yes 2 yes yes yes"
t "the Secretaries list links to Blog approvals with the waiting count" "$(body "$A" /admin/secretaries/index.php | grep -ac 'Blog approvals (2 waiting)')" "1"
PV=$(body "$A" "/admin/secretaries/blog-preview.php?id=$SUB")
t "the preview shows the post inside a sandboxed frame (no scripts)" "$(has x "$PV" 'sandbox=""') $(has x "$PV" 'Waiting body')" "yes yes"
CP=$(body "$A" "/admin/secretaries/blog-compare.php?id=$SUBC")
t "compare shows the old and new title and the changed text" "$(has x "$CP" "Live post $RUN edited") $(has x "$CP" "Change to live $RUN") $(has x "$CP" 'Pending body') $(has x "$CP" 'Edited body')" "yes yes yes yes"
TA=$(tok "$A" $AP); REV=$(q "SELECT rev FROM secretary_blog_submissions WHERE id=$SUB")
adm() { curl -s -b "$A" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "csrf_token=$1" --form-string "action=$2" --form-string "id=$3" --form-string "rev=$4" ${5:+--form-string "reason=$5"} "$B$AP" | sed "s#$B##"; }
t "approve without a token is refused and nothing is published" "$(adm "" approve $SUB $REV) $(q "SELECT COUNT(*) FROM blogs WHERE title='$TITLE2'")" "302 /admin/secretaries/blog-approvals.php 0"
t "send back needs a reason" "$(adm "$TA" send_back $SUBC 1 "") $(q "SELECT status FROM secretary_blog_submissions WHERE id=$SUBC")" "302 /admin/secretaries/blog-approvals.php waiting"
t "send back with a reason" "$(adm "$TA" send_back $SUBC $(q "SELECT rev FROM secretary_blog_submissions WHERE id=$SUBC") 'Please keep the old title.') $(q "SELECT status FROM secretary_blog_submissions WHERE id=$SUBC")" "302 /admin/secretaries/blog-approvals.php sent_back"
LS=$(body "$T/b_later" $BL)
t "the secretary sees Sent back and the reason on her list" "$(has x "$LS" 'Change sent back') $(has x "$LS" 'Please keep the old title.')" "yes yes"
t "the sent back change is not live" "$(q "SELECT title FROM blogs WHERE id=$BID1")" "Live post $RUN edited"
t "she can open it to fix it: the editor shows the reason" "$(body "$T/b_later" "/secretary/blogs/edit.php?id=$BID1" | grep -ac 'Please keep the old title.')" "1"
t "approve and publish the new post" "$(adm "$TA" approve $SUB $REV) $(q "SELECT status FROM blogs WHERE title='$TITLE2'")" "302 /admin/secretaries/blog-approvals.php published"
t "the public now sees it, exactly like an admin post" "$(pub "$TITLE2") $(curl -s "$B/blogs/readmore.php?slug=$(q "SELECT slug FROM blogs WHERE title='$TITLE2'")" | grep -ac 'Waiting body')" "yes 1"
t "approving it a second time is refused (nothing duplicated)" "$(adm "$TA" approve $SUB $REV) $(q "SELECT COUNT(*) FROM blogs WHERE title='$TITLE2'")" "302 /admin/secretaries/blog-approvals.php 1"
t "approved is recorded with the admin's name; the secretary's in-app notice exists" "$(q "SELECT actor_name FROM secretary_blog_log WHERE action='approved' ORDER BY id DESC LIMIT 1") $([ $(q "SELECT COUNT(*) FROM portal_notifications WHERE type='blog_approval' AND title='Your blog was approved'") -ge 1 ] && echo notified)" "Alice Admin notified"
t "the waiting count on the Secretaries list went down" "$(body "$A" /admin/secretaries/index.php | grep -ac 'Blog approvals (')" "0"

echo "== The existing blog pages are unchanged and still work"
t "the admin's blog list, create and edit pages open as before" "$(code "$A" /admin/blogs/index.php) $(code "$A" /admin/blogs/create.php) $(code "$A" "/admin/blogs/edit.php?id=$BID1")" "200 200 200"
t "the admin's blog list shows the secretary's published post, and no waiting one" "$(body "$A" /admin/blogs/index.php | grep -ac "$TITLE2")" "1"
t "the admin can still delete (the existing way): a throwaway post is removed" "$(R=$(curl -s -b "$A" -H 'Content-Type: application/json' -d "{\"id\":$(q "SELECT id FROM blogs WHERE title='$TITLE2'")}" "$B/api/blog/delete.php"); echo "$R" | grep -ac '"success":true') $(q "SELECT COUNT(*) FROM blogs WHERE title='$TITLE2'")" "1 0"
t "On leave does not block her from Blogs" "$("$PHPBIN" -d extension=pgsql -d extension=pdo_pgsql -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p=DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php"); $p->exec("UPDATE secretaries SET status=\x27on_leave\x27 WHERE id=".(int)$argv[1]);' "$(python -c "import json;print(json.load(open('$J'))['later']['sid'])")"; code "$T/b_later" $BL)" "200"
q "UPDATE secretaries SET status='left' WHERE id=$(python -c "import json;print(json.load(open('$J'))['later']['sid'])")" >/dev/null
t "Left is refused at once" "$(where "$T/b_later" $BL)" "/auth/login.php"

echo; echo "$pass passed, $fail failed"
rm -f "$CV"
[ "$fail" = 0 ]
