#!/usr/bin/env bash
# Access audit: every admin secretary page (and the actions on them) against EVERY role, and every secretary page against every role that is not a secretary.
# The normal admin must get in; the super admin, tutors, students, staff (employee), interns, plain users, secretaries and signed-out visitors must be refused. Also: no old super admin secretary address is left.
# Run on a scratch database that has all migrations and the secretary test data (any secretary_*_test.php run), with the site served from it:   B=http://localhost:8011 bash app/v1/tests/secretary_audit_access.sh
B=${B:-http://localhost:8011}
T=${TMPDIR:-${TEMP:-/tmp}}
PHPBIN=${PHPBIN:-php}
export ROOT=${ROOT:-$(cd "$(dirname "$0")/../../.." && pwd)}
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then pass=$((pass+1)); else echo "FAIL $1  (got: $2, want: $3)"; fail=$((fail+1)); fi; }
"$PHPBIN" -r '
require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p = DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php");
$run = substr(md5((string) microtime(true)), 0, 6); $pw = password_hash("x", PASSWORD_DEFAULT); $out = ["_run" => $run];
$roles = $p->query("SELECT role_name, id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (["admin", "superadmin", "tutor", "student", "employee", "intern", "user"] as $r) {
  $p->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password) VALUES (?, ?, ?, ?, 1, 1, \x27active\x27, 0)")->execute(["au_$r$run", "au_$r$run@audit.test", $pw, $roles[$r]]);
  $id = (int) $p->lastInsertId(); $p->prepare("INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)")->execute([$id, ucfirst($r), "Audit"]);
  if ($r === "tutor") $p->prepare("INSERT INTO tutors (user_id, is_active) VALUES (?, 1)")->execute([$id]);
  $out[$r] = "au_$r$run@audit.test";
}
file_put_contents(getenv("TMPDIR")."/audit_roles.json", json_encode($out));' 2>&1 | head -3
J="$T/audit_roles.json"
em() { python -c "import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]])" "$J" "$1"; }
login() { rm -f "$1"; curl -s -c "$1" -o /dev/null "$B/login"; curl -s -b "$1" -c "$1" -o /dev/null -d "email=$2&password=x" "$B/login"; }
for r in tutor student employee intern user; do login "$T/a_$r" "$(em $r)"; done
AD="$T/a_admin"; rm -f "$AD"; curl -s -c "$AD" -o /dev/null "$B/admin/portal.php"; curl -s -b "$AD" -c "$AD" -o /dev/null -d "email=$(em admin)&password=x" "$B/admin/portal.php"
SU="$T/a_superadmin"; rm -f "$SU"; TU=$(curl -s -b "$SU" -c "$SU" "$B/superadmin/login.php" | grep -aoE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'); curl -s -b "$SU" -c "$SU" -o /dev/null -d "email=$(em superadmin)&password=x&csrf_token=$TU" "$B/superadmin/login.php"
# a real secretary (all switches on): the first secretary of the newest test run
SECMAIL=$("$PHPBIN" -r 'require getenv("ROOT")."/app/v1/lib/DazuPdo.php"; $p = DazuPdo::open(require getenv("ROOT")."/app/v1/config/database.php");
  $r = $p->query("SELECT u.email FROM secretaries s JOIN users u ON u.id = s.user_id WHERE s.status = \x27active\x27 AND u.is_active = 1 AND (SELECT COUNT(*) FROM secretary_switches w WHERE w.secretary_id = s.id AND w.switch_value <> \x27off\x27) >= 5 ORDER BY s.id DESC LIMIT 1")->fetchColumn(); echo $r;')
login "$T/a_secretary" "$SECMAIL"
code() { curl -s -b "$1" -o /dev/null -w "%{http_code}" "$B$2"; }
post() { curl -s -b "$1" -o /dev/null -w "%{http_code}" -d "$3" "$B$2"; }
ADMIN_PAGES="/admin/secretaries/index.php /admin/secretaries/create.php /admin/secretaries/create.php?id=1 /admin/secretaries/blog-approvals.php /admin/secretaries/blog-preview.php?id=1 /admin/secretaries/blog-compare.php?id=1 /admin/secretaries/tasks.php /admin/secretaries/diary.php /admin/secretaries/activity.php /admin/secretaries/enquiries.php /admin/secretaries/enquiry.php?id=1"
ADMIN_POSTS="/admin/secretaries/index.php|action=reset&id=1 /admin/secretaries/index.php|action=suspend&id=1 /admin/secretaries/switch.php|id=1&key=id_card&value=on /admin/secretaries/create.php|action=add /admin/secretaries/blog-approvals.php|action=approve&id=1&rev=1 /admin/secretaries/blog-approvals.php|action=send_back&id=1&rev=1&reason=x /admin/secretaries/tasks.php|action=create&secretary_id=1&title=X&due_date=2031-01-01&due_time=10:00 /admin/secretaries/tasks.php|action=delete&id=1"
echo "Admin pages and actions, one row per role (200 = let in; anything else = refused):"
for who in admin superadmin tutor student employee intern user secretary nobody; do
  jar="$T/a_$who"; [ "$who" = nobody ] && jar="$T/a_nobody_none"
  g=""; for pg in $ADMIN_PAGES; do c=$(code "$jar" "$pg"); g="$g $c"; [ "$who" = admin ] && { [ "$c" = 200 ] || [ "$c" = 404 ] || t "admin should open $pg" "$c" "200"; } || t "$who must be refused on $pg" "$c" "403"; done
  p=""; for pp in $ADMIN_POSTS; do u=${pp%%|*}; d=${pp#*|}; c=$(post "$jar" "$u" "$d"); p="$p $c"; [ "$who" = admin ] || t "$who must be refused on POST $u ($d)" "$c" "403"; done
  printf "  %-11s pages:%s | posts:%s\n" "$who" "$g" "$p"
done
echo "Secretary pages against every role that is not a secretary (must never return 200):"
SEC_PAGES="/secretary/dashboard/index.php /secretary/blogs/index.php /secretary/blogs/edit.php /secretary/enquiries/index.php /secretary/enquiries/view.php?id=1 /secretary/tasks/index.php /secretary/room/index.php /secretary/staffcalendar/index.php /secretary/notifications/index.php /secretary/idcard/index.php"
for who in admin superadmin tutor student employee intern user nobody; do
  jar="$T/a_$who"; [ "$who" = nobody ] && jar="$T/a_nobody_none"; g=""
  for pg in $SEC_PAGES; do c=$(code "$jar" "$pg"); g="$g $c"; { [ "$c" = 200 ] && t "$who must not get 200 on $pg" 200 "not 200"; } || true; done
  printf "  %-11s %s\n" "$who" "$g"
done
echo "Secretary JSON/action addresses against non-secretaries:"
for who in admin superadmin tutor student nobody; do jar="$T/a_$who"; [ "$who" = nobody ] && jar="$T/a_nobody_none"
  for u in /secretary/notifications/api.php?action=count /secretary/blogs/save.php /secretary/blogs/delete.php /secretary/blogs/upload_image.php /secretary/enquiries/delete.php; do c=$(post "$jar" "$u" "x=1"); { [ "$c" = 200 ] && t "$who must not get 200 on POST $u" 200 "not 200"; } || true; done; done
echo "Old super admin secretary addresses:"
for pg in /superadmin/secretaries/index.php /superadmin/secretaries/create.php /superadmin/secretaries/switch.php /superadmin/secretaries/; do t "old address $pg must not work" "$(code "$SU" "$pg")" "404"; done
t "no superadmin secretary folder exists on disk" "$(ls "$ROOT/public/superadmin" | grep -ci secretar)" "0"
echo; echo "$pass checks passed, $fail failed"
[ "$fail" = 0 ]
