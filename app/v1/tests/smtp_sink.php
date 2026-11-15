<?php
// A tiny local mail server for tests: accepts every message and saves it as <dir>/<n>.eml. Nothing is sent anywhere.
//   php app/v1/tests/smtp_sink.php <port> <dir>      then run the app with MAIL_HOST=127.0.0.1 MAIL_PORT=<port> MAIL_ENCRYPTION= MAIL_USERNAME=u MAIL_PASSWORD=p
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$port = (int) ($argv[1] ?? 2525);
$dir = $argv[2] ?? sys_get_temp_dir() . '/smtp_sink';
@mkdir($dir, 0775, true);
$srv = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if (!$srv) { fwrite(STDERR, "Cannot listen: $errstr\n"); exit(1); }
echo "listening on $port, saving to $dir\n";
$n = count(glob("$dir/*.eml"));
while (true) {
    $c = @stream_socket_accept($srv, 3600);
    if (!$c) continue;
    fwrite($c, "220 sink ESMTP\r\n");
    $data = false; $buf = '';
    while (($line = fgets($c)) !== false) {
        if ($data) {
            if (rtrim($line, "\r\n") === '.') {
                $data = false;
                file_put_contents("$dir/" . (++$n) . '.eml', $buf);
                $buf = '';
                fwrite($c, "250 OK queued\r\n");
            } else {
                $buf .= (isset($line[0]) && $line[0] === '.' && isset($line[1]) && $line[1] === '.') ? substr($line, 1) : $line;
            }
            continue;
        }
        $cmd = strtoupper(substr($line, 0, 4));
        if ($cmd === 'EHLO' || $cmd === 'HELO') fwrite($c, "250-sink\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n");
        elseif ($cmd === 'AUTH') {
            if (stripos($line, 'LOGIN') !== false && stripos($line, 'PLAIN') === false) {   // AUTH LOGIN: ask for the user name and password, accept anything
                fwrite($c, "334 VXNlcm5hbWU6\r\n"); fgets($c); fwrite($c, "334 UGFzc3dvcmQ6\r\n"); fgets($c);
            }
            fwrite($c, "235 ok\r\n");
        }
        elseif ($cmd === 'DATA') { $data = true; fwrite($c, "354 go\r\n"); }
        elseif ($cmd === 'QUIT') { fwrite($c, "221 bye\r\n"); break; }
        else fwrite($c, "250 OK\r\n");
    }
    fclose($c);
}
