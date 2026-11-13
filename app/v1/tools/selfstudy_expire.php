<?php
// Submits every Self-study exam whose time has run out, marking the answers saved so far. Run it from cron every minute:
//   * * * * * php /path/to/app/v1/tools/selfstudy_expire.php
// (The study pages also do this for the student who opens them, so the cron job only matters for students who closed the page.)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyQuiz.php';
require_once __DIR__ . '/../services/SelfStudyProgress.php';
$db = \Models\Connection::getInstance()->getConnection();
if (!ss3_installed($db)) { fwrite(STDERR, "Run the migrations first.\n"); exit(1); }
$n = ss3_expire_due($db, time());
try { ssd_cron_beat($db); } catch (Throwable $e) { error_log('selfstudy cron heartbeat: ' . $e->getMessage()); }      // so Settings > Self-study can show that the job is running
echo $n . " attempt(s) submitted.\n";
