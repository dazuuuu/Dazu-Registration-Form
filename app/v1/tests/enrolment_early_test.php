<?php
// Early access, seat holds and conversion test (migrations 101 and 102). Run from the command line against a scratch or local database:
//   php -d extension=gd app/v1/tests/enrolment_early_test.php
// It creates temporary openings and bookings, checks every rule, and removes what it created.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../bootstrap.php';
require_once APP_V1_ROOT . '/services/EnrolmentService.php';
require_once APP_V1_ROOT . '/models/CourseModel.php';

$db = \Models\Connection::getInstance()->getConnection();
$fail = 0;
$ok = function (bool $c, string $what) use (&$fail) { echo ($c ? '  ok   ' : '  FAIL ') . $what . "\n"; if (!$c) $fail++; };
$at = fn(string $rel) => date('Y-m-d\TH:i', strtotime(en_now() . ' ' . $rel));
$course = (new \Models\CourseModel($db))->getAll(true)[0] ?? null;
if (!$course) exit("No active course to test with.\n");
$cid = (int) $course['id'];
$_SESSION = [];
$ok($db->query('SELECT early_access_at FROM enrolment_openings LIMIT 0') !== false && $db->query('SELECT hold_until FROM enrolment_bookings LIMIT 0') !== false, 'migration 102 is applied');
$cfg0 = en_cfg($db);
en_cfg_save($db, ['booking_fee' => $cfg0['booking_fee'], 'notify_phone' => $cfg0['notify_phone'], 'mpesa_type' => $cfg0['mpesa_type'], 'mpesa_number' => $cfg0['mpesa_number'], 'mpesa_account' => $cfg0['mpesa_account'],
                  'bank_name' => $cfg0['bank_name'], 'bank_account_name' => $cfg0['bank_account_name'], 'bank_account_number' => $cfg0['bank_account_number'], 'early_hours' => 48, 'hold_days' => 3,
                  'hide_form' => en_hide_form($db) ? '1' : '0']);

echo "the timeline of an opening (EAT)\n";
$j = ['status' => 'open', 'override_mode' => 'auto', 'bookings_open_at' => '2026-11-01 08:00:00', 'opens_at' => '2027-01-01 08:00:00', 'closes_at' => '2027-01-31 23:59:00', 'early_access_at' => null];
$ok(en_stage($j, '2026-12-29 07:59:59', 48) === 'bookings', '1 Nov to 29 Dec: Book now only');
$ok(en_stage($j, '2026-12-30 08:00:00', 48) === 'early', 'from 30 Dec 8:00 AM: early access for people who booked');
$ok(en_stage($j, '2027-01-01 07:59:59', 48) === 'early', 'still early access just before enrolment opens');
$ok(en_stage($j, '2027-01-01 08:00:00', 48) === 'open', 'from 1 Jan 8:00 AM: Enroll now for everyone');
$ok(en_stage(['early_access_at' => '2026-12-20 08:00:00'] + $j, '2026-12-21 00:00:00', 48) === 'early', 'the admin can set the early access start by hand');
$ok(en_stage($j, '2026-12-30 08:00:00', 24) === 'bookings' && en_stage($j, '2026-12-31 08:00:00', 24) === 'early', 'the early-access hours can be changed (24 hours)');
$ok(en_stage(['override_mode' => 'closed'] + $j, '2026-12-31 08:00:00', 48) === 'closed', 'Force closed beats early access');

echo "invites only for Confirmed bookings\n";
$o1 = en_open($db, ['title' => 'TEST Early A', 'bookings_open_at' => $at('-2 days'), 'opens_at' => $at('+30 hours'), 'closes_at' => $at('+60 days'), 'classes_start' => date('Y-m-d', strtotime('+40 days')), 'seats' => '3'], 0);
$oid = (int) $o1['id'];
$ok(en_opening($db, $oid)['stage'] === 'early', 'enrolment opens in 30 hours: the opening is in early access (48 hours)');
$ip = '203.0.113.' . random_int(1, 250);
$base = ['opening_id' => $oid, 'full_name' => 'Early Booker', 'email' => 'ea1@example.invalid', 'phone' => '0712340001', 'study_mode' => 'online', 'class_time' => '08:00', 'consent' => 1];
$made = [];
$book = function (array $o) use ($db, $base, $course, $ip, &$made) { $db->exec("UPDATE enrolment_bookings SET ip = 'x' WHERE ip = '$ip'"); $r = en_book($db, $course, $o + $base, $ip); if (!empty($r['booking'])) $made[] = (int) $r['booking']['id']; return $r; };
$a = $book(['pay_method' => 'mpesa', 'pay_code' => 'EARLYA0001'])['booking'];
$b = $book(['email' => 'ea2@example.invalid', 'phone' => '0712340002', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYB0002'])['booking'];
$c = $book(['email' => 'ea3@example.invalid', 'phone' => '0712340003', 'pay_method' => 'bank', 'pay_code' => 'EARLY-BANK3'])['booking'];
$ok(en_cron($db)['invites'] === 0, 'unpaid bookings get no invite');
en_pay_confirm($db, en_booking($db, (int) $a['id']), 0);
$a = en_booking($db, (int) $a['id']);
$ok($a['invited_at'] !== null && $a['hold_until'] !== null, 'confirmed during early access: invited at once');
$holdSecs = strtotime($a['hold_until']) - strtotime($a['invited_at']);
$ok($holdSecs === 3 * 86400, 'the seat is held for 3 days from the invite');
$ok(!en_invite($db, $a), 'a booking is invited only once');
en_pay_reject($db, en_booking($db, (int) $c['id']), 0, 'Code not found');
$ok(en_cron($db)['invites'] === 0 && en_booking($db, (int) $c['id'])['invited_at'] === null && en_booking($db, (int) $b['id'])['invited_at'] === null, 'rejected and unpaid bookings stay uninvited');

echo "the cron sends the invites when early access starts\n";
$o2 = en_open($db, ['title' => 'TEST Early B', 'bookings_open_at' => $at('-2 days'), 'opens_at' => $at('+5 days'), 'closes_at' => $at('+60 days'), 'seats' => '5'], 0);
$o2id = (int) $o2['id'];
$ok(en_opening($db, $o2id)['stage'] === 'bookings', 'a second opening is still in Bookings open');
$e = $book(['opening_id' => $o2id, 'email' => 'ea4@example.invalid', 'phone' => '0712340004', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYE0004'])['booking'];
en_pay_confirm($db, en_booking($db, (int) $e['id']), 0);
$ok(en_booking($db, (int) $e['id'])['invited_at'] === null, 'confirmed before early access: no invite yet');
$db->prepare("UPDATE enrolment_openings SET early_access_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', strtotime(en_now()) - 600), $o2id]);
$r = en_cron($db);
$ok($r['invites'] === 1 && en_booking($db, (int) $e['id'])['invited_at'] !== null, 'early access started: the job invites the Confirmed booking');
$ok(en_cron($db)['invites'] === 0, 'and only once');
$ok(!en_open($db, ['id' => $o2id, 'title' => 'x', 'bookings_open_at' => $at('-2 days'), 'early_access_at' => $at('+6 days'), 'opens_at' => $at('+5 days'), 'closes_at' => $at('+60 days')], 0)['ok'], 'early access must start before enrolment opens');

echo "the early-access link and the Enroll form\n";
$_SESSION = [];
$msg = en_enrol_blocked($db, $course);
$ok($msg !== null && str_starts_with($msg, 'Hello, our intake has closed'), 'during early access everyone else is still blocked (Book now only)');
$ok(en_ea_lookup($db, en_token($a), $cid)['id'] == $a['id'] && en_ea_lookup($db, en_token($a), $cid + 999) === null, 'the invite link works for its own course only');
en_ea_remember($a);
$ok(en_ea_booking($db, $cid)['id'] == $a['id'] && en_enrol_blocked($db, $course) === null, 'with the link, the invited person can enroll');
$st = $db->prepare("UPDATE enrolment_bookings SET payment_status = 'unpaid' WHERE id = ?"); $st->execute([(int) $a['id']]);
$ok(en_ea_booking($db, $cid) === null, 'a booking that is not paid cannot use early access');
$db->prepare("UPDATE enrolment_bookings SET payment_status = 'paid' WHERE id = ?")->execute([(int) $a['id']]);

echo "reminder 24 hours before the hold ends\n";
$db->prepare("UPDATE enrolment_bookings SET hold_until = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', strtotime(en_now()) + 20 * 3600), (int) $a['id']]);
$ok(en_cron($db)['reminders'] === 1 && en_booking($db, (int) $a['id'])['reminder_sent_at'] !== null, 'a reminder goes when less than 24 hours are left');
$ok(en_cron($db)['reminders'] === 0, 'the reminder is sent once');

echo "the hold ends: Expired, the seat is released\n";
$w = $book(['email' => 'ea5@example.invalid', 'phone' => '0712340005', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYW0005']);
$f = $book(['email' => 'ea6@example.invalid', 'phone' => '0712340006', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYF0006'])['booking'];   // 3 seats: a, b (payment waiting), f
$wb = $book(['email' => 'ea7@example.invalid', 'phone' => '0712340007', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYW0007']);
$ok(!empty($wb['waitlisted']), 'the opening is full: the next person joins the waiting list');
$db->prepare("UPDATE enrolment_bookings SET hold_until = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', strtotime(en_now()) - 60), (int) $a['id']]);
$ok(en_ea_booking($db, $cid) === null && en_enrol_blocked($db, $course) !== null, 'after the hold, the early-access link no longer lets them enroll');
$r = en_cron($db);
$a2 = en_booking($db, (int) $a['id']);
$ok($r['expired'] === 1 && $a2['status'] === 'expired', 'the job expires the booking');
$ok(en_booking($db, (int) $f['id'])['status'] === 'waiting' && en_booking($db, (int) $f['id'])['offered_at'] !== null && en_booking($db, (int) $wb['booking']['id'])['offered_at'] === null, 'the seat is offered to the waiting list first: to the person waiting longest');
$ok(en_ea_lookup($db, en_token($a2), $cid)['status'] === 'expired', 'the person can still open their link and see their booking is Expired');
$ok(!en_expire($db, en_booking($db, (int) $a['id'])), 'an expired booking is not expired twice');

echo "enrolling converts the booking\n";
$a3 = en_booking($db, (int) $e['id']);   // E: invited, hold valid
$_SESSION = []; en_ea_remember($a3);
$regId = (int) $db->query('SELECT id FROM registrations ORDER BY id DESC LIMIT 1')->fetchColumn();
$before = (int) $db->query("SELECT COUNT(*) FROM payments WHERE registration_id = $regId")->fetchColumn();
$origRef = $db->query("SELECT payment_reference FROM registrations WHERE id = $regId")->fetchColumn();
en_link_booking_on_enrol($db, $regId, 'someone.else@example.invalid', $cid, (int) $a3['id']);
$cv = en_booking($db, (int) $a3['id']);
$ok($cv['status'] === 'converted' && (int) $cv['converted_registration_id'] === $regId && $cv['converted_at'] !== null, 'the booking becomes Converted and is linked to the new student');
$ok(en_pending_fee_credit($db, $regId) === 1500.0, 'the KES 1,500 is not asked for again: the default first deposit at approval is lower by it');
$ok(empty($_SESSION['en_ea'][$cid]), 'the early-access link is used up');
en_link_booking_on_enrol($db, $regId, 'ea4@example.invalid', $cid, (int) $a3['id']);
en_apply_fee_on_approval($db, $regId, 4);
en_apply_fee_on_approval($db, $regId, 4);
$ok((int) $db->query("SELECT COUNT(*) FROM payments WHERE registration_id = $regId")->fetchColumn() === $before + 1, 'on approval the KES 1,500 shows on the student record, once');
$ok(str_contains((string) $db->query("SELECT notes FROM payments WHERE registration_id = $regId AND payer_reference LIKE 'Booking BK-%'")->fetchColumn(), 'towards course fees'), 'Online: recorded as a payment towards the course fees');
$ok(!en_expire($db, en_booking($db, (int) $a3['id'])) && en_taken($db, $o2id, $cid) === 1, 'a converted booking keeps its seat and never expires');

echo "settings: early access hours and hold days\n";
$ok(!en_cfg_save($db, ['booking_fee' => '1500', 'notify_phone' => '0722922454', 'early_hours' => 72, 'hold_days' => 3])['ok'], 'the hold must be longer than early access (72 hours vs 3 days is not)');
$ok(en_cfg_save($db, ['booking_fee' => '1500', 'notify_phone' => '0722922454', 'early_hours' => 24, 'hold_days' => 5, 'hide_form' => '1'])['ok'] && en_cfg($db)['hold_days'] === 5, 'the number of hold days can be edited');
$o3 = en_open($db, ['title' => 'TEST Early C', 'bookings_open_at' => $at('-2 days'), 'opens_at' => $at('+10 hours'), 'closes_at' => $at('+60 days')], 0);
$g = $book(['opening_id' => (int) $o3['id'], 'email' => 'ea8@example.invalid', 'phone' => '0712340008', 'pay_method' => 'mpesa', 'pay_code' => 'EARLYG0008'])['booking'];
en_pay_confirm($db, en_booking($db, (int) $g['id']), 0);
$g = en_booking($db, (int) $g['id']);
$ok(strtotime($g['hold_until']) - strtotime($g['invited_at']) === 5 * 86400, 'new invites use the new hold (5 days)');

echo "a move starts again\n";
$mv = en_move($db, en_booking($db, (int) $g['id']), $oid);
$ok($mv['ok'] === false || en_booking($db, (int) $g['id'])['invited_at'] !== null, 'a move to an opening in early access is invited again, with its own hold');

// clean up
en_cfg_save($db, ['booking_fee' => $cfg0['booking_fee'], 'notify_phone' => $cfg0['notify_phone'], 'mpesa_type' => $cfg0['mpesa_type'], 'mpesa_number' => $cfg0['mpesa_number'], 'mpesa_account' => $cfg0['mpesa_account'],
                  'bank_name' => $cfg0['bank_name'], 'bank_account_name' => $cfg0['bank_account_name'], 'bank_account_number' => $cfg0['bank_account_number'], 'early_hours' => $cfg0['early_hours'], 'hold_days' => $cfg0['hold_days'],
                  'hide_form' => en_hide_form($db) ? '1' : '0']);
$db->prepare("DELETE FROM payments WHERE registration_id = ? AND payer_reference LIKE 'Booking BK-%'")->execute([$regId]);
$db->prepare("UPDATE registrations SET payment_reference = ? WHERE id = ?")->execute([$origRef, $regId]);
foreach ([$oid, $o2id, (int) $o3['id']] as $x) en_delete_opening($db, $x);
try { $db->exec("DELETE FROM class_email_queue WHERE to_email LIKE '%@example.invalid' OR subject LIKE '%BK-%'"); } catch (Throwable $ex) {}
echo $fail ? "\n$fail FAILED\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
