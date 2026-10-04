<?php
// app/v1/services/WebPush.php
// Standard Web Push (RFC 8030) with VAPID (RFC 8292) and aes128gcm payload encryption (RFC 8291), using only PHP's OpenSSL.
// The VAPID key pair is created on first use and kept in site_settings, so nothing has to be configured.

class WebPush
{
    private const SETTING_PRIVATE = 'push_vapid_private_pem';
    private const SETTING_PUBLIC = 'push_vapid_public';
    // DER prefix of a P-256 SubjectPublicKeyInfo; the 65-byte uncompressed point follows it.
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function supported(): bool
    {
        return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf') && function_exists('curl_init')
            && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    public static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    /** @return array{private:string, public:string} PEM private key and base64url public key (what browsers subscribe with). */
    public static function vapidKeys(PDO $db): array
    {
        $st = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN (?, ?)");
        $st->execute([self::SETTING_PRIVATE, self::SETTING_PUBLIC]);
        $have = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($have[self::SETTING_PRIVATE]) && !empty($have[self::SETTING_PUBLIC])) {
            return ['private' => $have[self::SETTING_PRIVATE], 'public' => $have[self::SETTING_PUBLIC]];
        }
        [$pem, $point] = self::newKeyPair();
        $public = self::b64url($point);
        $up = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $up->execute([self::SETTING_PRIVATE, $pem]);
        $up->execute([self::SETTING_PUBLIC, $public]);
        return ['private' => $pem, 'public' => $public];
    }

    /** @return array{0:string,1:string} [private key PEM, raw uncompressed public point] */
    private static function newKeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$key) {
            throw new RuntimeException('Could not create an EC key: ' . openssl_error_string());
        }
        openssl_pkey_export($key, $pem);
        return [$pem, self::publicPoint($key)];
    }

    private static function publicPoint($key): string
    {
        $d = openssl_pkey_get_details($key);
        return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function publicKeyFromPoint(string $point)
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX) . $point), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    /** Encrypt $payload for one subscription (RFC 8291 aes128gcm). */
    public static function encrypt(string $payload, string $p256dhB64, string $authB64): string
    {
        $uaPublic = self::b64urlDecode($p256dhB64);
        $authSecret = self::b64urlDecode($authB64);
        if (strlen($uaPublic) !== 65 || strlen($authSecret) < 16) {
            throw new InvalidArgumentException('Bad subscription keys.');
        }
        $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublic = self::publicPoint($local);
        $shared = openssl_pkey_derive(self::publicKeyFromPoint($uaPublic), $local);
        if ($shared === false) {
            throw new RuntimeException('ECDH failed.');
        }
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /** ES256 JWT for the push service at $endpoint. */
    public static function vapidJwt(string $endpoint, string $privatePem, string $subject): string
    {
        $p = parse_url($endpoint);
        $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $data = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.'
              . self::b64url(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $subject]));
        openssl_sign($data, $der, openssl_pkey_get_private($privatePem), OPENSSL_ALGO_SHA256);
        return $data . '.' . self::b64url(self::derToRaw($der));
    }

    /** DER ECDSA signature → 64-byte r||s as JWS needs. */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    /**
     * Send one push. Returns the HTTP status (201 = accepted; 404/410 = subscription gone), or 0 on a network error.
     */
    public static function send(array $sub, string $payload, array $keys, string $subject): int
    {
        $body = self::encrypt($payload, $sub['p256dh'], $sub['auth']);
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Urgency: high',
                'Authorization: vapid t=' . self::vapidJwt($sub['endpoint'], $keys['private'], $subject) . ', k=' . $keys['public'],
            ],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $code;
    }
}
