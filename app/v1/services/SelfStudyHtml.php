<?php
// app/v1/services/SelfStudyHtml.php — everything a tutor writes is cleaned with an ALLOW-LIST before it is shown,
// and only YouTube links are accepted for video (embedded from the privacy-friendly youtube-nocookie address).

if (!function_exists('ss_clean_html')) {

    /** tag => allowed attributes. Any tag or attribute not listed here is removed. */
    define('SS_HTML_ALLOW', [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href'], 'img' => ['src', 'alt'],
    ]);
    /** Removed together with everything inside them. */
    define('SS_HTML_DROP', ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'noscript', 'template', 'video', 'audio', 'canvas', 'head', 'title']);

    function ss_clean_html(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') return '';
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body><div id="ss-root">' . $html . '</div></body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('ss-root');
        if (!$root) return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        ss_clean_node($root);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) $out .= $dom->saveHTML($child);
        return trim($out);
    }

    function ss_clean_node(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!($child instanceof DOMElement)) { $node->removeChild($child); continue; }   // comments, processing instructions, CDATA
            $tag = strtolower($child->tagName);
            if (in_array($tag, SS_HTML_DROP, true)) { $node->removeChild($child); continue; }
            if ($tag === 'h1') { $new = $child->ownerDocument->createElement('h2'); foreach (iterator_to_array($child->childNodes) as $c) $new->appendChild($c); $node->replaceChild($new, $child); $child = $new; $tag = 'h2'; }
            if (!isset(SS_HTML_ALLOW[$tag])) {              // unknown tag: keep its (cleaned) children, lose the tag
                ss_clean_node($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, SS_HTML_ALLOW[$tag], true)) { $child->removeAttribute($attr->name); continue; }
                $val = $attr->value;
                if ($name === 'href') { $ok = ss_safe_url($val, ['http', 'https', 'mailto']); if ($ok === null) $child->removeAttribute($attr->name); else $child->setAttribute('href', $ok); }
                elseif ($name === 'src') { $ok = ss_safe_url($val, ['http', 'https'], true); if ($ok === null) $child->removeAttribute($attr->name); else $child->setAttribute('src', $ok); }
                elseif ($name === 'colspan' || $name === 'rowspan') { $n = max(1, min(20, (int) $val)); $child->setAttribute($name, (string) $n); }
            }
            if ($tag === 'a') { $child->setAttribute('rel', 'noopener noreferrer nofollow'); $child->setAttribute('target', '_blank'); }
            if ($tag === 'img') {
                if (!$child->hasAttribute('src')) { $node->removeChild($child); continue; }
                $child->setAttribute('loading', 'lazy');
            }
            ss_clean_node($child);
        }
    }

    /** Returns the URL when it is safe, else null. Control characters and spaces hidden inside "java script:" tricks are stripped first. */
    function ss_safe_url(string $u, array $schemes, bool $allowOwnUploads = false): ?string
    {
        $u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $probe = preg_replace('/[\x00-\x20\x7f]+/u', '', $u) ?? '';
        if ($probe === '') return null;
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)) {
            if (!in_array(strtolower($m[1]), $schemes, true)) return null;
            if (strtolower($m[1]) !== 'mailto' && !filter_var($probe, FILTER_VALIDATE_URL)) return null;
            return $probe;
        }
        if ($allowOwnUploads && preg_match('#^uploads/[A-Za-z0-9_./\-]+$#', $probe) && !str_contains($probe, '..')) return $probe;   // an image the site itself stores
        return null;
    }

    /** The 11-character YouTube id from a link (watch, youtu.be, embed, shorts, live) or a bare id. Anything else: null. */
    function ss_youtube_id(?string $in): ?string
    {
        $in = trim((string) $in);
        if ($in === '') return null;
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $in)) return $in;
        if (!preg_match('#^https?://#i', $in)) return null;
        $p = parse_url($in);
        if (!$p || empty($p['host'])) return null;
        $host = strtolower(preg_replace('/^(www|m|music)\./i', '', $p['host']));
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($p['path'] ?? '', '/');
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            $path = $p['path'] ?? '';
            if ($path === '/watch') { parse_str($p['query'] ?? '', $q); $id = $q['v'] ?? null; }
            elseif (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) $id = $m[2];
        }
        return (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) ? $id : null;
    }

    function ss_youtube_embed_url(string $id, string $origin = ''): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?enablejsapi=1&rel=0&modestbranding=1&playsinline=1&disablekb=1&iv_load_policy=3'
            . ($origin !== '' ? '&origin=' . rawurlencode($origin) : '');
    }
}
