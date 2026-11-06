<?php
// Book now + booking fee test (migration 101, built on simple enrolment). Run from the command line against a scratch or local database:
//   php -d extension=gd app/v1/tests/enrolment_fee_test.php
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
$ip = '203.0.113.' . random_int(1, 250);
$K = strtoupper(bin2hex(random_bytes(3)));   // random codes, so a code a real person already used never collides with the test
$ok(en_installed($db) && $db->query('SELECT 1 FROM enrolment_opening_seats LIMIT 0') !== false, 'migration 101 is applied');

// ---- stages ----
echo "stages\n";
$now = '2026-11-06 10:00:00';
$o = ['status' => 'open', 'override_mode' => 'auto', 'bookings_open_at' => '2026-11-01 08:00:00', 'opens_at' => '2027-01-01 08:00:00', 'closes_at' => '2027-02-28 23:59:59'];
$ok(en_stage($o, $now) === 'bookings', 'bookings open before enrolment opens');
$ok(en_stage(['bookings_open_at' => '2026-12-01 08:00:00'] + $o, $now) === 'upcoming', 'upcoming before bookings open');
$ok(en_stage($o, '2027-01-01 08:00:00') === 'open', 'enrolment open from the opening time');
$ok(en_stage($o, '2027-03-01 00:00:00') === 'closed', 'closed after it closes');
$ok(en_stage(['override_mode' => 'closed'] + $o, '2027-01-10 00:00:00') === 'closed', 'Force closed wins');
$ok(en_stage(['override_mode' => 'open'] + $o, $now) === 'open', 'Force open wins');
$ok(en_stage(['status' => 'open', 'override_mode' => 'auto', 'bookings_open_at' => null, 'opens_at' => null, 'closes_at' => null], $now) === 'open', 'an older opening (no new dates) stays open as before');
$ok(en_stage(['status' => 'closed', 'override_mode' => 'auto', 'bookings_open_at' => null, 'opens_at' => null, 'closes_at' => null], $now) === 'closed', 'an older closed opening stays closed');

// ---- the page state and the closed message ----
echo "course page state\n";
$sep = en_open($db, ['title' => 'TEST September 2026', 'bookings_open_at' => $at('-120 days'), 'opens_at' => $at('-90 days'), 'closes_at' => $at('-2 days'), 'classes_start' => date('Y-m-d', strtotime('-80 days'))], 0);   // closes after any older opening, so it is the one "last closed"
$ok($sep['ok'], 'a past opening can be saved');
$s = en_state($db, $cid);
$ok(!$s['open'] && $s['last_closed']['title'] === 'TEST September 2026', 'a closed opening and nothing open: enrolment is not open');
$msg = en_closed_message($s);
$ok(str_starts_with($msg, 'Hello, our intake has closed. Enrolment for the TEST September 2026 intake closed on ') && str_ends_with($msg, 'You can still book your place in the next intake.'), 'closed message wording');
$ok(en_enrol_blocked($db, $course) === $msg, 'server blocks enrolling while closed (setting on)');
en_setting_model($db)->set('en_hide_form', '0');
$ok(en_enrol_blocked($db, $course) === null, 'with the setting off, the Apply form works as today');
en_setting_model($db)->set('en_hide_form', '1');

$j = en_open($db, ['title' => 'TEST January 2027', 'bookings_open_at' => $at('-1 day'), 'opens_at' => $at('+30 days'), 'closes_at' => $at('+90 days'), 'classes_start' => date('Y-m-d', strtotime('+40 days')),
                   'seats' => '3', 'course_seats' => [$cid => '2']], 0);
$ok($j['ok'], 'January opening saved');
$jid = (int) $j['id'];
$s = en_state($db, $cid);
$ok(!$s['open'] && $s['next'] && (int) $s['next']['id'] === $jid, 'bookings open: Book now is for January, the Enroll form stays hidden');
$ok(en_enrol_blocked($db, $course) !== null, 'still blocked on the server while only bookings are open');
$ok(en_seat_info($db, $s['next'], $cid)['limit'] === 2, 'a different number of seats for one course (2 instead of 3)');
$ok(en_badge($db, $cid) === 'Book now', 'course card badge says Book now');
$ok(!en_open($db, ['title' => 'x', 'bookings_open_at' => $at('+5 days'), 'opens_at' => $at('+1 day'), 'closes_at' => $at('+9 days')], 0)['ok'], 'bookings must open before enrolment opens');

// ---- booking needs a valid, unused code ----
echo "booking and the payment code\n";
$base = ['opening_id' => $jid, 'full_name' => 'Test Booker', 'email' => 'tb1@example.invalid', 'phone' => '0712345678', 'study_mode' => 'online', 'class_time' => '08:00', 'consent' => 1];
$made = [];
$book = function (array $over) use ($db, $base, $course, $ip, &$made) { $db->exec("UPDATE enrolment_bookings SET ip = 'x' WHERE ip = '$ip'"); $r = en_book($db, $course, $over + $base, $ip); if (!empty($r['booking'])) $made[] = (int) $r['booking']['id']; return $r; };
$ok(!($r = $book([]))['ok'] && !empty($r['pay_error']), 'no code: refused');
$ok(!$book(['pay_method' => 'mpesa', 'pay_code' => 'ABC123'])['ok'], 'M-Pesa code too short: refused');
$ok(!$book(['pay_method' => 'bank', 'pay_code' => 'AB12'])['ok'], 'bank code under 6 characters: refused');
$ok(!$book(['pay_method' => 'mpesa', 'pay_code' => ('QGH7' . $K), 'consent' => 0])['ok'], 'no consent tick: refused');
$ok(!$book(['pay_method' => 'mpesa', 'pay_code' => ('QGH7' . $K), 'phone' => '12345'])['ok'], 'bad phone: refused');
$r = $book(['pay_method' => 'mpesa', 'pay_code' => strtolower('QGH7' . $K)]);
$b1 = $r['booking'] ?? []; if (!$r['ok']) echo '       (' . ($r['error'] ?? '') . ")
";
$ok($r['ok'] && $b1['status'] === 'payment_received' && $b1['payment_reference'] === ('QGH7' . $K) && (float) $b1['payment_amount'] === 1500.0, 'valid code: "Payment code received", saved in payment_reference and payment_amount');
$ok($b1['payment_status'] === 'unpaid' && $b1['fee_kind'] === 'course_fee_credit', 'not paid until the office confirms; Online counts towards course fees');
$ok(!($r = $book(['email' => 'tb2@example.invalid', 'phone' => '0722111222', 'pay_method' => 'mpesa', 'pay_code' => ('QGH7' . $K)]))['ok'] && !empty($r['pay_error']), 'the same code is never accepted twice');
$ok(!empty(($r = $book(['pay_method' => 'bank', 'pay_code' => ('BNK-' . $K)]))['duplicate']), 'same email and phone, same opening: "already booked"');
$ok(!empty(($r = $book(['email' => 'other@example.invalid', 'pay_method' => 'bank', 'pay_code' => ('BNK-' . $K)]))['duplicate']), 'same phone, other email: still "already booked"');
$r = $book(['email' => 'tb3@example.invalid', 'phone' => '+254733000111', 'study_mode' => 'physical', 'class_time' => '05:00', 'campus' => 'thika', 'pay_method' => 'bank', 'pay_code' => ('BNK-' . $K)]);
$b2 = $r['booking'] ?? [];
$ok($r['ok'] && $b2['fee_kind'] === 'registration_fee' && $b2['phone'] === '0733000111', 'Physical + bank code: registration fee; +254 number saved as 07xx');
$ok(!$book(['email' => 'tb4@example.invalid', 'phone' => '0744000222', 'study_mode' => 'physical', 'class_time' => '05:00', 'pay_method' => 'bank', 'pay_code' => ('BNK2' . $K)])['ok'], 'Physical without a campus: refused');
$r = $book(['email' => 'tb5@example.invalid', 'phone' => '0755000333', 'pay_method' => 'mpesa', 'pay_code' => ('YYY1' . $K)]);
$b3 = $r['booking'] ?? [];
$ok($r['ok'] && !empty($r['waitlisted']) && $b3['status'] === 'waiting' && $b3['payment_reference'] === null, 'course full (2 seats): waiting list, no payment taken');

// ---- office confirms or rejects ----
echo "office confirms or rejects\n";
$ok(!en_pay_reject($db, en_booking($db, (int) $b2['id']), 0, '  ')['ok'], 'reject needs a reason');
$ok(en_pay_confirm($db, en_booking($db, (int) $b1['id']), 0)['ok'], 'confirm works');
$c1 = en_booking($db, (int) $b1['id']);
$ok($c1['status'] === 'confirmed' && $c1['payment_status'] === 'paid' && $c1['receipt_sent_at'] !== null, 'booking Confirmed, payment_status paid, receipt queued');
$ok(!en_pay_confirm($db, $c1, 0)['ok'], 'cannot confirm twice');
$ok(en_pay_reject($db, en_booking($db, (int) $b2['id']), 0, 'Code not found in our M-Pesa statement')['ok'], 'reject with a reason works');
$c2 = en_booking($db, (int) $b2['id']);
$ok($c2['status'] === 'cancelled' && $c2['reject_reason'] !== null, 'rejected: booking cancelled, reason kept');
$c3 = en_booking($db, (int) $b3['id']);
$ok($c3['status'] === 'waiting' && $c3['offered_at'] !== null, 'the seat was released and offered to the first person waiting');
$ok(!($r = en_check_code($db, 'bank', strtolower('BNK-' . $K)))['ok'] && str_contains($r['error'], 'already been used'), 'a rejected code stays used');

// ---- the waiting person pays after the offer ----
echo "waiting list: pay after the offer\n";
$ok(!en_pay_submit_existing($db, en_booking($db, (int) $b3['id']), 'mpesa', 'bad')['ok'], 'offered slot: a bad code is refused');
$ok(en_pay_submit_existing($db, en_booking($db, (int) $b3['id']), 'mpesa', ('YYY1' . $K))['ok'], 'offered slot: a valid code is accepted');
$ok(en_booking($db, (int) $b3['id'])['status'] === 'payment_received', 'the waiting person now has "Payment code received"');

// ---- check my booking ----
echo "check my booking\n";
$tok = en_token($c1);
$ok(en_by_token($db, $tok)['id'] == $c1['id'] && en_by_token($db, $tok . 'x') === null && en_by_token($db, '999999-' . str_repeat('a', 40)) === null, 'secure link works; a wrong link does not');
$ok(en_by_ref_email($db, strtolower($c1['reference']), 'TB1@example.invalid')['id'] == $c1['id'] && en_by_ref_email($db, $c1['reference'], 'nobody@example.invalid') === null, 'reference + email finds the booking');
$ok(en_update_prefs($db, $c1, 'physical', '05:00', 'nairobi')['ok'] && en_booking($db, (int) $c1['id'])['fee_kind'] === 'registration_fee', 'changing to Physical changes how the fee counts');
$ok(!en_update_prefs($db, en_booking($db, (int) $c1['id']), 'physical', '08:00', 'nairobi')['ok'], 'a Physical class time must be 5:00 AM');
$m2 = en_open($db, ['title' => 'TEST April 2027', 'bookings_open_at' => $at('-1 day'), 'opens_at' => $at('+100 days'), 'closes_at' => $at('+150 days')], 0);
$ok(en_move($db, en_booking($db, (int) $c1['id']), (int) $m2['id'])['ok'] && (int) en_booking($db, (int) $c1['id'])['opening_id'] === (int) $m2['id'], 'a move to another opening works');
$ok(en_booking($db, (int) $c1['id'])['payment_status'] === 'paid', 'a move keeps the payment');
$ok(!en_move($db, en_booking($db, (int) $c1['id']), (int) $m2['id'])['ok'], 'moving to the same opening is refused');
$ok(en_cancel($db, en_booking($db, (int) $c1['id']))['ok'] && en_booking($db, (int) $c1['id'])['status'] === 'cancelled', 'cancel works (no refund)');
$ok(!en_cancel($db, en_booking($db, (int) $c1['id']))['ok'], 'cannot cancel twice');

// ---- tell me when the next intake opens ----
echo "tell me when\n";
$ok(!en_interest_add($db, $cid, 'A', 'x', '')['ok'] && en_interest_add($db, $cid, 'Interested Person', 'interest@example.invalid', '0712000999')['ok'], 'form checks the details');
$ok(en_interest_add($db, $cid, 'Interested Person', 'interest@example.invalid', '')['ok'] && (int) $db->query("SELECT COUNT(*) FROM enrolment_interest WHERE email = 'interest@example.invalid'")->fetchColumn() === 1, 'the same person is not listed twice');
$ok(en_notify_interest($db) >= 1 && $db->query("SELECT notified_at FROM enrolment_interest WHERE email = 'interest@example.invalid'")->fetchColumn() !== null, 'told once an opening is taking bookings');

// ---- the fee is counted when the person enrols ----
echo "fee counted at enrolment\n";
$cc = en_booking($db, (int) $b3['id']);
en_pay_confirm($db, $cc, 0);
$regId = (int) $db->query('SELECT id FROM registrations ORDER BY id DESC LIMIT 1')->fetchColumn();
$before = (int) $db->query("SELECT COUNT(*) FROM payments WHERE registration_id = $regId")->fetchColumn();
$origRef = $db->query("SELECT payment_reference FROM registrations WHERE id = $regId")->fetchColumn();
en_link_booking_on_enrol($db, $regId, 'tb5@example.invalid', $cid);
$ok(en_booking($db, (int) $b3['id'])['status'] === 'converted' && (int) en_booking($db, (int) $b3['id'])['converted_registration_id'] === $regId, 'enrolling converts the booking and links it to the new student');
$ok(en_pending_fee_credit($db, $regId) === 1500.0 && (int) $db->query("SELECT COUNT(*) FROM payments WHERE registration_id = $regId")->fetchColumn() === $before, 'the fee waits for approval, and the default first deposit is lower by KES 1,500');
en_apply_fee_on_approval($db, $regId, 4);
en_apply_fee_on_approval($db, $regId, 4);
$ok((int) $db->query("SELECT COUNT(*) FROM payments WHERE registration_id = $regId")->fetchColumn() === $before + 1 && en_pending_fee_credit($db, $regId) === 0.0, 'on approval: one approved payment of KES 1,500 on the student record (not twice)');

// ---- messages ----
echo "messages\n";
$ok(str_contains(en_office_text($cc), 'Booking payment received, please confirm'), 'office message text');
$ok(str_starts_with(en_whatsapp_url($db, $cc), 'https://wa.me/254722922454?text='), 'WhatsApp link goes to 254722922454');
$ok(en_phone('0722 922 454') === '0722922454' && en_phone('+254 112 345 678') === '0112345678' && en_phone('712345678') === '0712345678' && en_phone('0212345678') === null, 'Kenyan phone checks');

// clean up
$db->prepare("DELETE FROM payments WHERE registration_id = ? AND payer_reference LIKE 'Booking BK-%'")->execute([$regId]);
$db->prepare("UPDATE registrations SET payment_reference = ? WHERE id = ?")->execute([$origRef, $regId]);
foreach ([$sep['id'], $jid, $m2['id']] as $oid) en_delete_opening($db, (int) $oid);
$db->exec("DELETE FROM enrolment_interest WHERE email LIKE '%@example.invalid'");
try { $db->exec("DELETE FROM class_email_queue WHERE to_email LIKE '%@example.invalid' OR subject LIKE '%BK-%'"); } catch (Throwable $e) {}
echo $fail ? "\n$fail FAILED\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
