<?php
// Book now via Daraja STK push (migration 136). Run from the command line against a scratch or local database:
//   php -d extension=gd app/v1/tests/booking_stk_test.php
// No real Safaricom credentials are used: a pending enrolment_stk_payments row is inserted directly (as
// bkp_initiate() would after a successful stkPush()), then bkp_handle_callback() is fed a canned Daraja
// callback payload, the same shape MpesaService::parseCallback() would hand it.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../bootstrap.php';
require_once APP_V1_ROOT . '/services/BookingPaymentService.php';
require_once APP_V1_ROOT . '/models/CourseModel.php';

$db = \Models\Connection::getInstance()->getConnection();
$fail = 0;
$ok = function (bool $c, string $what) use (&$fail) { echo ($c ? '  ok   ' : '  FAIL ') . $what . "\n"; if (!$c) $fail++; };
$at = fn(string $rel) => date('Y-m-d\TH:i', strtotime(en_now() . ' ' . $rel));
$course = (new \Models\CourseModel($db))->getAll(true)[0] ?? null;
if (!$course) exit("No active course to test with.\n");
$cid = (int) $course['id'];
$K = strtoupper(bin2hex(random_bytes(3)));

$ok(bkp_installed($db), 'migration 136 is applied');

$op = en_open($db, ['title' => 'TEST STK Intake', 'bookings_open_at' => $at('-1 day'), 'opens_at' => $at('+30 days'), 'closes_at' => $at('+90 days'), 'classes_start' => date('Y-m-d', strtotime('+40 days'))], 0);
$ok($op['ok'], 'test opening saved');
$oid = (int) $op['id'];

// ---- a paid callback creates a real registration, a receipt and marks the row paid ----
echo "paid callback\n";
$checkoutId = 'ws_CO_TEST_' . $K;
$db->prepare("INSERT INTO enrolment_stk_payments (course_id, opening_id, full_name, email, phone, study_mode, class_time, consent, amount,
                  merchant_request_id, checkout_request_id, status, ip, created_at)
              VALUES (?, ?, 'STK Test Student', ?, '0712345678', 'online', '08:00', 1, 1500, ?, ?, 'pending', '203.0.113.9', ?)")
   ->execute([$cid, $oid, 'stktest' . $K . '@example.invalid', 'ws_MR_TEST_' . $K, $checkoutId, en_now()]);

$cb = ['valid' => true, 'checkout_request_id' => $checkoutId, 'result_code' => 0, 'receipt' => 'QGH' . $K . 'X', 'phone' => '254712345678', 'amount' => 1500];
bkp_handle_callback($db, $cb);

$row = $db->prepare("SELECT * FROM enrolment_stk_payments WHERE checkout_request_id = ?");
$row->execute([$checkoutId]);
$row = $row->fetch(PDO::FETCH_ASSOC);
$ok($row && $row['status'] === 'paid' && $row['mpesa_receipt'] === 'QGH' . $K . 'X', 'the STK row is marked paid with the M-Pesa receipt');
$ok($row && !empty($row['registration_id']) && !empty($row['payment_id']), 'a registration and a payment were created');

$reg = $db->prepare("SELECT * FROM registrations WHERE id = ?");
$reg->execute([(int) $row['registration_id']]);
$reg = $reg->fetch(PDO::FETCH_ASSOC);
$ok((bool) $reg && preg_match('/^D[CD][A-Z]+\d{3}\/\d{2}$/', (string) $reg['registration_no']) === 1, 'a well-formed registration number was issued: ' . ($reg['registration_no'] ?? ''));
$ok((bool) $reg && $reg['email'] === 'stktest' . $K . '@example.invalid', 'the registration carries the booking details');

$payment = $db->prepare("SELECT * FROM payments WHERE id = ?");
$payment->execute([(int) $row['payment_id']]);
$payment = $payment->fetch(PDO::FETCH_ASSOC);
$ok((bool) $payment && $payment['status'] === 'approved' && !empty($payment['receipt_html']), 'the payment is approved with a stored receipt');

$booking = en_booking($db, (int) $row['booking_id']);
$ok((bool) $booking && $booking['status'] === 'confirmed' && $booking['payment_status'] === 'paid', 'the booking is confirmed and paid');

$status = bkp_status($db, $checkoutId);
$ok($status['success'] && $status['payment_status'] === 'paid' && $status['registration_no'] === $reg['registration_no'], 'bkp_status() reports paid with the registration number');
$ok(!empty($status['receipt_html']) && !empty($status['receipt_url']), 'bkp_status() returns the receipt HTML and a download link');

// ---- a replayed callback (Safaricom retries) is a no-op: no second registration/payment ----
echo "replay\n";
$regCountBefore = (int) $db->query("SELECT COUNT(*) FROM registrations WHERE id = " . (int) $row['registration_id'])->fetchColumn();
bkp_handle_callback($db, $cb);
$row2 = $db->prepare("SELECT * FROM enrolment_stk_payments WHERE checkout_request_id = ?");
$row2->execute([$checkoutId]);
$row2 = $row2->fetch(PDO::FETCH_ASSOC);
$ok((int) $row2['payment_id'] === (int) $row['payment_id'] && (int) $row2['registration_id'] === (int) $row['registration_id'], 'a replayed callback does not create a second registration or payment');

// ---- a failed callback is recorded, nothing else happens ----
echo "failed callback\n";
$checkoutId2 = 'ws_CO_TEST_FAIL_' . $K;
$db->prepare("INSERT INTO enrolment_stk_payments (course_id, opening_id, full_name, email, phone, study_mode, class_time, consent, amount,
                  checkout_request_id, status, ip, created_at)
              VALUES (?, ?, 'STK Test Fail', ?, '0712345679', 'online', '08:00', 1, 1500, ?, 'pending', '203.0.113.9', ?)")
   ->execute([$cid, $oid, 'stkfail' . $K . '@example.invalid', $checkoutId2, en_now()]);
bkp_handle_callback($db, ['valid' => true, 'checkout_request_id' => $checkoutId2, 'result_code' => 1032, 'result_desc' => 'Request cancelled by user']);
$f = $db->prepare("SELECT status, result_desc FROM enrolment_stk_payments WHERE checkout_request_id = ?");
$f->execute([$checkoutId2]);
$f = $f->fetch(PDO::FETCH_ASSOC);
$ok($f['status'] === 'failed' && $f['result_desc'] === 'Request cancelled by user', 'a failed STK push is recorded as failed, with the reason');

// clean up
$db->prepare("DELETE FROM payments WHERE id = ?")->execute([(int) $row['payment_id']]);
$db->prepare("DELETE FROM registrations WHERE id = ?")->execute([(int) $row['registration_id']]);
$db->prepare("DELETE FROM enrolment_stk_payments WHERE checkout_request_id IN (?, ?)")->execute([$checkoutId, $checkoutId2]);
en_delete_opening($db, $oid);
try { $db->exec("DELETE FROM class_email_queue WHERE to_email LIKE '%@example.invalid'"); } catch (Throwable $e) {}
echo $fail ? "\n$fail FAILED\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
