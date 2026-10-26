<?php
// app/v1/services/LinkRules.php
// What a link-check answer means, per site. Sites change their pages, so every rule is here (tested in
// tests/link_checker_test.php). When the signals are not clear the answer is 'unknown' ("Could not check"), never a guess.
// Statuses: works | private | request_access | not_found | unknown.

class LinkRules
{
    /** Hosts a redirect may lead to that mean "you must sign in" (a clear private signal). Never fetched. */
    public const LOGIN_HOSTS = ['accounts.google.com', 'github.com/login', 'gitlab.com/users/sign_in', 'www.canva.com/login', 'www.figma.com/login'];

    /** Site key for an allowed host (null when the host is not on the allowed list). */
    public static function site(string $host): ?string
    {
        $host = strtolower($host);
        $map = [
            'github.com' => 'github', 'gitlab.com' => 'gitlab', 'docs.google.com' => 'google', 'drive.google.com' => 'google',
            'canva.com' => 'canva', 'figma.com' => 'figma', 'loom.com' => 'loom', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        ];
        foreach ($map as $suffix => $site) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) return $site;
        }
        return null;
    }

    /**
     * The URL actually requested for a link: oEmbed where the site has one (YouTube, Loom), otherwise the page itself.
     * Google: the public view of the file id.
     */
    public static function probeUrl(string $site, string $url): string
    {
        if ($site === 'youtube') return 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode($url);
        if ($site === 'loom') return 'https://www.loom.com/v1/oembed?url=' . rawurlencode($url);
        if ($site === 'google' && ($id = self::googleFileId($url))) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === 'docs.google.com' && preg_match('#^/(document|spreadsheets|presentation|forms)/#', (string) parse_url($url, PHP_URL_PATH), $m)) {
                return 'https://docs.google.com/' . $m[1] . '/d/' . $id . '/' . ($m[1] === 'forms' ? 'viewform' : 'edit');
            }
            return 'https://drive.google.com/file/d/' . $id . '/view';
        }
        return $url;
    }

    public static function googleFileId(string $url): ?string
    {
        if (preg_match('#/d/(?:e/)?([A-Za-z0-9_-]{10,})#', $url, $m)) return $m[1];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        return isset($q['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $q['id']) ? $q['id'] : null;
    }

    /** True when a redirect target is a sign-in page. */
    public static function isLoginRedirect(string $target): bool
    {
        $host = strtolower((string) parse_url($target, PHP_URL_HOST));
        $path = (string) parse_url($target, PHP_URL_PATH);
        foreach (self::LOGIN_HOSTS as $h) {
            [$lh, $lp] = array_pad(explode('/', $h, 2), 2, '');
            if ($host === $lh && ($lp === '' || str_starts_with(ltrim($path, '/'), $lp))) return true;
        }
        return false;
    }

    /**
     * Decide from what came back. $code: final HTTP status (0 = no answer); $loginRedirect: a redirect led to a sign-in page;
     * $body: the first part of the page (never stored).
     */
    public static function classify(string $site, int $code, bool $loginRedirect, string $body = ''): string
    {
        if ($loginRedirect) return 'private';
        if ($code === 0 || $code === 429 || $code >= 500) return 'unknown';
        if ($site === 'google') {
            if (preg_match('/You need access|Request access|You need permission/i', $body)) return 'request_access';
            if (in_array($code, [401, 403], true)) return 'private';
            if ($code === 404) return 'not_found';
            if ($code === 200 && preg_match('/ServiceLogin|accounts\.google\.com\/v3\/signin|<title>Sign in/i', $body)) return 'private';
            return $code === 200 ? 'works' : 'unknown';
        }
        if ($site === 'github') {
            // GitHub answers 403 when it rate-limits anonymous requests, so 403 is not proof of anything.
            if ($code === 200) return 'works';
            return $code === 404 ? 'not_found' : 'unknown';
        }
        if ($site === 'gitlab') {
            if ($code === 200) return 'works';
            if ($code === 401) return 'private';
            return $code === 404 ? 'not_found' : 'unknown';
        }
        if ($site === 'youtube' || $site === 'loom') {
            // oEmbed: 200 public or unlisted, 401/403 private, 404 / 400 missing.
            if ($code === 200) return 'works';
            if (in_array($code, [401, 403], true)) return 'private';
            return in_array($code, [400, 404], true) ? 'not_found' : 'unknown';
        }
        if ($site === 'canva' || $site === 'figma') {
            if (in_array($code, [401, 403], true)) return 'private';
            if ($code === 404) return 'not_found';
            // Figma serves the same app page to everyone and shows "request access" in the browser, so 200 is not proof.
            if ($code === 200 && $site === 'canva') return 'works';
            return 'unknown';
        }
        return 'unknown';
    }

    /** The fix the student is told, per site. $tutorGithub: optional GitHub name the tutor saved. */
    public static function fixText(string $site, string $host, ?string $tutorGithub = null): string
    {
        if ($site === 'google') return 'Press Share, then choose "Anyone with the link" and "Viewer".';
        if ($site === 'github' || $site === 'gitlab') {
            return 'Make the project public, or add your tutor' . ($tutorGithub ? ' (' . $tutorGithub . ')' : '') . ' as a collaborator.';
        }
        if ($site === 'youtube') return 'Public or Unlisted works. Private does not.';
        return 'Turn on link sharing so anyone with the link can view.';
    }

    /** [title, message] the student sees for a status. */
    public static function message(string $status, string $site, string $host, ?string $tutorGithub = null): array
    {
        switch ($status) {
            case 'works': return ['Link works', 'We can open this. Your tutor will be able to read it.'];
            case 'private': return ['This link is private', 'Your tutor cannot open it yet. ' . self::fixText($site, $host, $tutorGithub) . ' Then press Check again.'];
            case 'request_access': return ['Request access screen', 'Google asks for permission. Share it with "Anyone with the link" so your tutor can open it.'];
            case 'not_found': return ['We cannot find this page', 'The link may have a spelling mistake, or the project is private.'];
            case 'not_checked': return ['', ''];
            case 'not_allowed': return ['This site is not allowed', 'Your tutor only accepts links from the sites listed.'];
            default: return ['We could not check this link', 'You can still send your work. We will check it again before your tutor marks it.'];
        }
    }

    /** Bad = the tutor probably cannot open it. "Could not check" is never bad. */
    public static function isBad(?string $status): bool
    {
        return in_array($status, ['private', 'request_access', 'not_found'], true);
    }
}
