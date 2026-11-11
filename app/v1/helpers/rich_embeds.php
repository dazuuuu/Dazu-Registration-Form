<?php
// app/v1/helpers/rich_embeds.php
// Turns links written on a line of their own (in blogs and projects) into the real thing:
//  - a YouTube / Vimeo link  → the video player
//  - any other https link    → a live preview of the website; clicking it opens the site in a new tab.
//    Sites that refuse to be shown inside another page get a screenshot instead (behind the live view).
// Links inside a sentence stay normal links.

if (!function_exists('rich_embeds')) {

    function rich_youtube_id(string $url): string
    {
        return preg_match('~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~i', $url, $m) ? $m[1] : '';
    }

    function rich_vimeo_id(string $url): string
    {
        return preg_match('~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)~i', $url, $m) ? $m[1] : '';
    }

    function rich_video_html(string $src): string
    {
        return '<div class="dz-yt" style="width:100%;margin:16px auto;"><div style="position:relative;padding-bottom:56.25%;height:0;border-radius:12px;overflow:hidden;background:#000;">'
            . '<iframe src="' . htmlspecialchars($src, ENT_QUOTES) . '" style="position:absolute;inset:0;width:100%;height:100%;border:0" loading="lazy" '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen title="Video"></iframe></div></div>';
    }

    function rich_site_html(string $url): string
    {
        $u = htmlspecialchars($url, ENT_QUOTES);
        $host = htmlspecialchars(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)), ENT_QUOTES);
        $shot = htmlspecialchars('https://image.thum.io/get/width/1200/crop/750/' . $url, ENT_QUOTES);
        return '<a href="' . $u . '" target="_blank" rel="noopener noreferrer" class="dz-site" style="display:block;margin:16px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;background:#fff">'
            . '<span style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;font-size:12px;color:#374151">'
            . '<span style="display:flex;gap:4px"><i style="width:9px;height:9px;border-radius:50%;background:#f87171;display:block"></i><i style="width:9px;height:9px;border-radius:50%;background:#fbbf24;display:block"></i><i style="width:9px;height:9px;border-radius:50%;background:#34d399;display:block"></i></span>'
            . '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600">' . $host . '</span><span style="color:#15A24A;font-weight:700;white-space:nowrap">Open site ↗</span></span>'
            . '<span style="display:block;position:relative;padding-bottom:62.5%;height:0;overflow:hidden;background:#f9fafb url(\'' . $shot . '\') top center/cover no-repeat">'
            . '<iframe src="' . $u . '" loading="lazy" tabindex="-1" sandbox="allow-scripts allow-same-origin" referrerpolicy="no-referrer" title="' . $host . '" '
            . 'style="position:absolute;top:0;left:0;width:200%;height:200%;border:0;transform:scale(.5);transform-origin:0 0;pointer-events:none;background:transparent"></iframe>'
            . '<span style="position:absolute;inset:0"></span></span></a>';
    }

    /** The embed for a link on its own line, or '' when it should stay a link. */
    function rich_embed_for(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^https?://[^\s<>"\']+$~i', $url)) return '';
        if ($id = rich_youtube_id($url)) return rich_video_html('https://www.youtube-nocookie.com/embed/' . $id . '?rel=0');
        if ($id = rich_vimeo_id($url)) return rich_video_html('https://player.vimeo.com/video/' . $id);
        if (preg_match('~\.(jpe?g|png|gif|webp|svg|pdf|mp4|zip)(\?|$)~i', $url)) return '';
        return rich_site_html($url);
    }

    /** Rich-text HTML (blog posts, project write-ups) with standalone links made into players / site previews. */
    function rich_embeds(string $html): string
    {
        // A paragraph (or div) that holds only a link, or only a bare URL.
        $html = preg_replace_callback(
            '~<(p|div)\b[^>]*>\s*(?:<a\b[^>]*href="([^"]+)"[^>]*>[^<]*</a>|(https?://[^\s<]+))\s*(?:<br\s*/?>)?\s*</\1>~i',
            function ($m) { $e = rich_embed_for($m[2] !== '' ? $m[2] : $m[3]); return $e !== '' ? $e : $m[0]; },
            $html
        );
        // A bare URL on a line of its own in plain text.
        return preg_replace_callback('~(^|<br\s*/?>|\n)\s*(https?://[^\s<]+)\s*(?=$|<br|\r?\n)~i',
            function ($m) { $e = rich_embed_for($m[2]); return $e !== '' ? $m[1] . $e : $m[0]; }, $html);
    }

    /** A project description: plain text (old projects) or editor HTML (new ones), with embeds. */
    function rich_text(string $text): string
    {
        if ($text === '') return '';
        if ($text === strip_tags($text)) {
            $html = '';
            foreach (preg_split('/\R{2,}/', trim($text)) as $para) {
                $html .= '<p>' . nl2br(htmlspecialchars(trim($para), ENT_QUOTES, 'UTF-8'), false) . '</p>';
            }
            $text = $html;
        }
        return rich_embeds($text);
    }
}
