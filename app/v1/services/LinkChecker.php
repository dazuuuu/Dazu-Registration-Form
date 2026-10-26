<?php
// app/v1/services/LinkChecker.php
// Checks, from the server, whether a tutor could open a student's link: an anonymous request (no cookies, no login).
// Safety: https only, allowed sites only, every resolved IP must be public (no private, loopback, link-local or reserved
// IPv4/IPv6), the connection is pinned to that checked IP, at most 4 redirects each checked again, 6 seconds in total,
// at most 1 MB read. Results are cached 10 minutes per URL and rate-limited per user and per server.
// Only the result is stored (status, code, final host, time) in tutor_assignment_link_checks; page contents never are.
require_once __DIR__ . '/LinkRules.php';

class LinkChecker
{
    public const CACHE_SECONDS = 600;
    public const USER_LIMIT = 20;      // checks per user per 10 minutes
    public const SERVER_LIMIT = 400;   // checks per server per 10 minutes
    private const MAX_REDIRECTS = 4;
    private const TIMEOUT = 6.0;
    private const MAX_BYTES = 1048576;
    private const UA = 'DazuLinkCheck/1.0 (+https://dazu.ac.ke; checks if a tutor can open a student link)';

    private ?PDO $db;
    /** @var callable|null fn(string $url, string $ip, float $timeLeft): array{code:int, location:?string, body:string} — replaced in tests. */
    private $fetcher;
    /** @var callable|null fn(string $host): string[] — replaced in tests. */
    private $resolver;

    public function __construct(?PDO $db, ?callable $fetcher = null, ?callable $resolver = null)
    {
        $this->db = $db;
        $this->fetcher = $fetcher;
        $this->resolver = $resolver;
    }

    /**
     * Check one link. $allowedSites: the assignment's list (null/'' = every allowed site).
     * @return array{status:string, site:?string, host:string, http_code:?int, reason:string, title:string, message:string, cached:bool}
     */
    public function check(string $url, ?string $allowedSites, ?int $userId = null, ?int $submissionId = null, ?string $tutorGithub = null): array
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $site = $host !== '' ? LinkRules::site($host) : null;
        // Older assignments with no site list accept any https link; only links on the known sites are checked.
        if (!$site && !$allowedSites && preg_match('#^https://#i', $url)) return $this->out('not_checked', null, $host, null, 'Site not checked', false, $tutorGithub);
        if (!preg_match('#^https://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || !$site || !asg_link_site_ok_lc($url, $allowedSites)) {
            return $this->out('not_allowed', $site, $host, null, 'Not an https link on the allowed sites list', false, $tutorGithub);
        }
        $hash = hash('sha256', $url);
        if ($cached = $this->cached($hash)) {
            $this->store($hash, $host, $site, $cached['status'], $cached['http_code'] !== null ? (int) $cached['http_code'] : null, 'cached', $userId, $submissionId);
            return $this->out($cached['status'], $site, $host, $cached['http_code'] !== null ? (int) $cached['http_code'] : null, (string) $cached['reason'], true, $tutorGithub);
        }
        if ($this->overLimit($userId)) {
            return $this->out('unknown', $site, $host, null, 'Too many checks, try again in a few minutes', false, $tutorGithub);
        }
        [$status, $code, $finalHost, $reason] = $this->probe($site, $url);
        $this->store($hash, $finalHost ?: $host, $site, $status, $code, $reason, $userId, $submissionId);
        return $this->out($status, $site, $host, $code, $reason, false, $tutorGithub);
    }

    /** @return array{0:string,1:?int,2:string,3:string} [status, http code, final host, short reason] */
    private function probe(string $site, string $url): array
    {
        $target = LinkRules::probeUrl($site, $url);
        $deadline = microtime(true) + self::TIMEOUT;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $host = strtolower((string) parse_url($target, PHP_URL_HOST));
            if (!preg_match('#^https://#i', $target) || !LinkRules::site($host)) return ['unknown', null, $host, 'Redirect to a site that is not allowed'];
            $ip = $this->safeIp($host);
            if (!$ip) return ['unknown', null, $host, 'Host is not on a public address'];
            $left = $deadline - microtime(true);
            if ($left <= 0.2) return ['unknown', null, $host, 'Timed out'];
            $r = $this->fetcher ? ($this->fetcher)($target, $ip, $left) : $this->fetch($target, $ip, $left);
            $code = (int) $r['code'];
            if ($code >= 300 && $code < 400 && !empty($r['location'])) {
                $next = self::resolveLocation($target, (string) $r['location']);
                if (LinkRules::isLoginRedirect($next)) return [LinkRules::classify($site, $code, true), $code, $host, 'Redirects to a sign-in page'];
                $target = $next;
                continue;
            }
            $status = LinkRules::classify($site, $code, false, (string) ($r['body'] ?? ''));
            return [$status, $code ?: null, $host, $code ? 'HTTP ' . $code : (string) ($r['error'] ?? 'No answer')];
        }
        return ['unknown', null, '', 'Too many redirects'];
    }

    /** First resolved IP, or null if any address of the host is not public (stops DNS tricks to reach the server's own network). */
    private function safeIp(string $host): ?string
    {
        $ips = $this->resolver ? ($this->resolver)($host) : self::resolve($host);
        if (!$ips) return null;
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) return null;
        }
        return $ips[0];
    }

    private static function resolve(string $host): array
    {
        $out = [];
        foreach ((@dns_get_record($host, DNS_A | DNS_AAAA) ?: []) as $r) {
            if (!empty($r['ip'])) $out[] = $r['ip'];
            if (!empty($r['ipv6'])) $out[] = $r['ipv6'];
        }
        if (!$out) {
            $v4 = @gethostbynamel($host);
            if ($v4) $out = $v4;
        }
        return $out;
    }

    /** Public unicast only: blocks private, loopback, link-local, CGNAT, multicast, reserved and IPv4-mapped IPv6 tricks. */
    public static function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) $ip = $m[1];
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($ip);
            foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15], ['224.0.0.0', 4], ['0.0.0.0', 8]] as [$net, $bits]) {
                $mask = -1 << (32 - $bits);
                if (($n & $mask) === (ip2long($net) & $mask)) return false;
            }
            return true;
        }
        $p = strtolower($ip);
        return !preg_match('/^(::1?$|fe[89ab]|fc|fd|ff|2001:db8|64:ff9b)/', $p);
    }

    private static function resolveLocation(string $base, string $loc): string
    {
        if (preg_match('#^https?://#i', $loc)) return $loc;
        $b = parse_url($base);
        if (str_starts_with($loc, '//')) return 'https:' . $loc;
        if (str_starts_with($loc, '/')) return 'https://' . $b['host'] . $loc;
        return 'https://' . $b['host'] . rtrim(dirname($b['path'] ?? '/'), '/') . '/' . $loc;
    }

    /** One GET, never following redirects, connected only to the checked IP, no cookies, at most 1 MB. */
    private function fetch(string $url, string $ip, float $timeLeft): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_CONNECTTIMEOUT_MS => (int) min(3000, $timeLeft * 1000), CURLOPT_TIMEOUT_MS => (int) ($timeLeft * 1000),
            CURLOPT_USERAGENT => self::UA, CURLOPT_COOKIE => '', CURLOPT_HTTPHEADER => ['Accept-Language: en', 'Cookie:'],
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $err = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $loc = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
        // Stopped at 1 MB on purpose (CURLE_WRITE_ERROR): the code we have is still good.
        if ($err && $err !== 23) return ['code' => 0, 'location' => null, 'body' => '', 'error' => $err === 28 ? 'Timed out' : 'Connection failed'];
        return ['code' => $code, 'location' => $loc, 'body' => substr($body, 0, 200000)];
    }

    private function cached(string $hash): ?array
    {
        if (!$this->db) return null;
        $st = $this->db->prepare("SELECT status, http_code, reason FROM tutor_assignment_link_checks WHERE url_hash = ? AND checked_at >= ? AND reason <> 'cached' AND status <> 'unknown' ORDER BY id DESC LIMIT 1");
        $st->execute([$hash, gmdate('Y-m-d H:i:s', time() - self::CACHE_SECONDS)]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function overLimit(?int $userId): bool
    {
        if (!$this->db) return false;
        $since = gmdate('Y-m-d H:i:s', time() - 600);
        $st = $this->db->prepare("SELECT COUNT(*) FROM tutor_assignment_link_checks WHERE checked_at >= ? AND reason <> 'cached'");
        $st->execute([$since]);
        if ((int) $st->fetchColumn() >= self::SERVER_LIMIT) return true;
        if (!$userId) return false;
        $st = $this->db->prepare("SELECT COUNT(*) FROM tutor_assignment_link_checks WHERE checked_by = ? AND checked_at >= ? AND reason <> 'cached'");
        $st->execute([$userId, $since]);
        return (int) $st->fetchColumn() >= self::USER_LIMIT;
    }

    private function store(string $hash, string $host, ?string $site, string $status, ?int $code, string $reason, ?int $userId, ?int $submissionId): void
    {
        if (!$this->db) return;
        try {
            $this->db->prepare("INSERT INTO tutor_assignment_link_checks (submission_id, checked_by, url_hash, host, site, status, http_code, reason, checked_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$submissionId, $userId, $hash, mb_substr($host, 0, 190), $site, $status, $code, mb_substr($reason, 0, 255), gmdate('Y-m-d H:i:s')]);
            // Audit log: who, host, result (never the full URL).
            $this->db->prepare("INSERT INTO tutor_assignment_events (assignment_id, actor_user_id, student_user_id, event, details, created_at) VALUES (NULL, ?, NULL, 'link_check', ?, ?)")
                ->execute([$userId, json_encode(['host' => $host, 'result' => $status, 'submission_id' => $submissionId]), gmdate('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            error_log('[LinkChecker] ' . $e->getMessage());
        }
    }

    private function out(string $status, ?string $site, string $host, ?int $code, string $reason, bool $cached, ?string $tutorGithub): array
    {
        [$title, $message] = LinkRules::message($status, (string) $site, $host, $tutorGithub);
        return ['status' => $status, 'site' => $site, 'host' => $host, 'http_code' => $code, 'reason' => $reason,
                'title' => $title, 'message' => $message, 'fix' => LinkRules::isBad($status) ? LinkRules::fixText((string) $site, $host, $tutorGithub) : '',
                'cached' => $cached, 'bad' => LinkRules::isBad($status)];
    }
}

/** Same rule as asg_link_site_ok() (kept here so the checker also runs without the assignment helpers, e.g. in tests). */
function asg_link_site_ok_lc(string $url, ?string $sites): bool
{
    if (function_exists('asg_link_site_ok')) return asg_link_site_ok($url, $sites);
    if (!$sites) return true;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    foreach (explode(',', $sites) as $s) {
        $s = trim($s);
        if ($s !== '' && ($host === $s || str_ends_with($host, '.' . $s))) return true;
    }
    return false;
}
