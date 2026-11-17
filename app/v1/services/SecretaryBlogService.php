<?php
/**
 * Blogs for secretaries. Everything that touches a real blog goes through the EXISTING blog code (BlogController / BlogModel), never through new SQL on the blog tables, so a post made or approved
 * here is exactly the post the admin would have made. This file adds only: the list for secretaries, the approval flow (new tables 125 and 126), the activity record and the notifications.
 *
 * The rules, all checked here on the server:
 *  - A secretary with the blog approval switch OFF posts and changes live at once, like the admin.
 *  - With it ON (also when the switch row is missing) nothing she saves reaches the blogs table: a new post waits as a "new" submission, a change to any existing blog waits as a "change"
 *    submission, and the public keeps the last approved version. Only the admin's approval publishes it (approve()).
 *  - She can unpublish a blog and publish it again (a status change, recorded). With approval ON she can only publish again a blog that was published before (so that version was approved).
 *  - She can never delete: there is no delete function in this file, and the secretary pages refuse the request.
 *  - Every action is written to secretary_blog_log with the person's name and the time.
 */
require_once __DIR__ . '/../helpers/secretary.php';
require_once __DIR__ . '/../controllers/BlogController.php';
require_once __DIR__ . '/../helpers/blog_image.php';
require_once __DIR__ . '/../helpers/secretary_activity.php';
require_once __DIR__ . '/PortalNotifications.php';

class SecretaryBlogService
{
    public const FILTERS = ['all' => 'All', 'published' => 'Published', 'waiting' => 'Waiting for approval', 'unpublished' => 'Unpublished'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Does this secretary's blog approval switch say her posts need approval? A missing switch row counts as "needed" (the safe side). */
    public static function approvalNeeded(PDO $db, int $secretaryId): bool
    {
        $st = $db->prepare("SELECT switch_value FROM secretary_switches WHERE secretary_id = ? AND switch_key = 'blogs_approval'");
        $st->execute([$secretaryId]);
        $v = $st->fetchColumn();
        return $v === false || $v !== 'off';
    }

    // ------------------------------------------------------------------ cleaning and checking the form

    /**
     * The same cleaning as the admin editor (pasted base64 pictures become compressed files), plus, because a secretary's text is shown to the public: script blocks, inline event handlers
     * (onclick=...) and javascript: links are removed. YouTube videos, columns and FAQs the editor makes are kept.
     */
    public function clean(string $html): string
    {
        $html = blog_inline_images_to_files($html);
        $html = (string) preg_replace('#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $html);
        $html = (string) preg_replace('#<\s*/?\s*script\b[^>]*>#i', '', $html);
        $html = (string) preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = (string) preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1=$2#$2', $html);
        return $html;
    }

    /** Check the editor fields. Returns [payload, errors]. $in is the form ($_POST). */
    public function fields(array $in, bool $isNew, bool $approval): array
    {
        $e = [];
        $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $p = ['title' => $t('title'), 'excerpt' => $t('excerpt'), 'meta_description' => $t('meta_description'), 'meta_title' => $t('meta_title'), 'meta_keywords' => $t('meta_keywords')];
        $p['content'] = $this->clean((string) ($in['content'] ?? ''));
        if ($p['title'] === '' || mb_strlen($p['title']) > 255) $e[] = 'Give the post a title (up to 255 letters).';
        if (trim(strip_tags($p['content'])) === '' && !preg_match('#<(img|iframe)\b#i', $p['content'])) $e[] = 'The post needs some content.';
        if (mb_strlen($p['excerpt']) > 1000) $e[] = 'The excerpt is too long (1000 letters at most).';
        if (mb_strlen($p['meta_description']) > 500) $e[] = 'The SEO description is too long (500 letters at most).';
        if (mb_strlen($p['meta_title']) > 255 || mb_strlen($p['meta_keywords']) > 255) $e[] = 'The meta title and keywords are limited to 255 letters.';
        $cat = (int) ($in['category_id'] ?? 0);
        $p['category_id'] = null;
        if ($cat > 0) {
            $q = $this->db->prepare('SELECT 1 FROM blog_categories WHERE id = ?');
            $q->execute([$cat]);
            if ($q->fetchColumn()) $p['category_id'] = $cat; else $e[] = 'That category does not exist.';
        }
        $tags = [];
        foreach (explode(',', (string) ($in['tags'] ?? '')) as $tag) {
            $tag = trim($tag);
            if ($tag === '') continue;
            if (mb_strlen($tag) > 50) { $e[] = 'A tag is too long (50 letters at most).'; break; }
            if (!isset($tags[mb_strtolower($tag)])) $tags[mb_strtolower($tag)] = $tag;   // the first spelling wins
        }
        if (count($tags) > 20) $e[] = 'Use 20 tags at most.';
        $p['tags'] = array_values($tags);
        $p['is_featured'] = !empty($in['is_featured']) && (string) $in['is_featured'] !== '0' ? 1 : 0;
        $st = (string) ($in['status'] ?? 'draft');
        $p['status'] = $isNew && !$approval && $st === 'published' ? 'published' : ($isNew && $approval ? 'published' : 'draft');   // a post that waits is published when approved
        return [$p, $e];
    }

    // ------------------------------------------------------------------ the list

    private function nameSql(string $p, string $u): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE($p.first_name,''), ' ', COALESCE($p.last_name,''))), ''), $u.username)";
    }

    /** All blogs and all open submissions as list rows, newest first. Row: key, blog_id, sub_id, title, status (published|unpublished|waiting|sent_back), pending (waiting|sent_back|''), reason, author, date, date_label, cover. */
    public function rows(): array
    {
        $rows = [];
        $open = [];
        foreach ($this->db->query("SELECT s.*, " . $this->nameSql('p', 'u') . " AS by_name FROM secretary_blog_submissions s JOIN secretaries sc ON sc.id = s.secretary_id JOIN users u ON u.id = sc.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
                                   WHERE s.status IN ('waiting', 'approving', 'sent_back') ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $pl = json_decode((string) $s['payload'], true) ?: [];
            $pend = $s['status'] === 'sent_back' ? 'sent_back' : 'waiting';
            if ($s['kind'] === 'change' && $s['blog_id']) { $open[(int) $s['blog_id']] = $s + ['pend' => $pend]; continue; }
            $rows[] = ['key' => 's' . $s['id'], 'blog_id' => null, 'sub_id' => (int) $s['id'], 'title' => (string) $s['title'], 'status' => $pend, 'pending' => '', 'reason' => (string) ($s['reason'] ?? ''),
                       'author' => (string) $s['by_name'], 'date' => (string) $s['updated_at'], 'date_label' => 'Sent', 'cover' => (string) ($pl['featured_image'] ?? '')];
        }
        $q = "SELECT b.id, b.title, b.status, b.published_at, b.created_at, b.updated_at, b.featured_image, " . $this->nameSql('p', 'u') . " AS author
              FROM blogs b LEFT JOIN users u ON u.id = b.author_id LEFT JOIN user_profiles p ON p.user_id = u.id";
        foreach ($this->db->query($q)->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $id = (int) $b['id'];
            $pub = $b['status'] === 'published';
            $o = $open[$id] ?? null;
            $rows[] = ['key' => 'b' . $id, 'blog_id' => $id, 'sub_id' => $o ? (int) $o['id'] : null, 'title' => (string) $b['title'], 'status' => $pub ? 'published' : 'unpublished',
                       'pending' => $o ? $o['pend'] : '', 'reason' => $o ? (string) ($o['reason'] ?? '') : '', 'author' => (string) $b['author'],
                       'date' => (string) ($pub && $b['published_at'] ? $b['published_at'] : ($b['updated_at'] ?: $b['created_at'])), 'date_label' => $pub ? 'Published' : 'Updated',
                       'cover' => (string) $b['featured_image'], 'was_published' => !empty($b['published_at'])];
        }
        usort($rows, static fn($a, $b) => strcmp($b['date'], $a['date']));
        return $rows;
    }

    /** The rows for one filter, and the number in each filter. */
    public function listing(string $filter): array
    {
        $all = $this->rows();
        $match = static function (array $r, string $f): bool {
            switch ($f) {
                case 'published': return $r['status'] === 'published';
                case 'waiting': return $r['status'] === 'waiting' || $r['pending'] === 'waiting';
                case 'unpublished': return $r['status'] === 'unpublished';
                default: return true;
            }
        };
        $counts = [];
        foreach (array_keys(self::FILTERS) as $f) $counts[$f] = count(array_filter($all, fn($r) => $match($r, $f)));
        $filter = isset(self::FILTERS[$filter]) ? $filter : 'all';
        return [array_values(array_filter($all, fn($r) => $match($r, $filter))), $counts, $filter, count(array_filter($all, fn($r) => $r['status'] === 'sent_back' || $r['pending'] === 'sent_back'))];
    }

    // ------------------------------------------------------------------ one post for the editor

    public function submission(int $id): ?array
    {
        $st = $this->db->prepare("SELECT s.*, " . $this->nameSql('p', 'u') . " AS by_name, u.id AS by_user_id FROM secretary_blog_submissions s JOIN secretaries sc ON sc.id = s.secretary_id JOIN users u ON u.id = sc.user_id
                                  LEFT JOIN user_profiles p ON p.user_id = u.id WHERE s.id = ?");
        $st->execute([$id]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) return null;
        $s['data'] = json_decode((string) $s['payload'], true) ?: [];
        return $s;
    }

    private function openChangeFor(int $blogId): ?array
    {
        $st = $this->db->prepare("SELECT id FROM secretary_blog_submissions WHERE kind = 'change' AND blog_id = ? AND status IN ('waiting', 'sent_back') ORDER BY id DESC LIMIT 1");
        $st->execute([$blogId]);
        $id = $st->fetchColumn();
        return $id ? $this->submission((int) $id) : null;
    }

    /** What the editor shows: [fields, meta]. A post with a pending change shows the pending version. Null when the post does not exist. */
    public function load(?int $blogId, ?int $subId): ?array
    {
        $blank = ['title' => '', 'excerpt' => '', 'content' => '', 'meta_description' => '', 'meta_title' => '', 'meta_keywords' => '', 'category_id' => null, 'tags' => [], 'is_featured' => 0, 'status' => 'draft', 'featured_image' => ''];
        if ($subId) {
            $s = $this->submission($subId);
            if (!$s || !in_array($s['status'], ['waiting', 'sent_back'], true)) return null;
            if ($s['kind'] === 'change' && $s['blog_id']) return $this->load((int) $s['blog_id'], null);
            return [$s['data'] + $blank, ['blog_id' => null, 'sub_id' => (int) $s['id'], 'status' => $s['status'], 'reason' => (string) $s['reason'], 'blocks' => 0, 'was_published' => false]];
        }
        if (!$blogId) return [$blank, ['blog_id' => null, 'sub_id' => null, 'status' => 'new', 'reason' => '', 'blocks' => 0, 'was_published' => false]];
        $c = new BlogController($this->db);
        $b = $c->getBlogDetails($blogId);
        if (!$b) return null;
        $f = ['title' => (string) $b['title'], 'excerpt' => (string) ($b['excerpt'] ?? ''), 'content' => (string) ($b['content'] ?? ''), 'meta_description' => (string) ($b['meta_description'] ?? ''),
              'meta_title' => (string) ($b['meta_title'] ?? ''), 'meta_keywords' => (string) ($b['meta_keywords'] ?? ''), 'category_id' => $b['category_id'] ? (int) $b['category_id'] : null,
              'tags' => array_map(static fn($t) => (string) $t['name'], $b['tags'] ?? []), 'is_featured' => (int) $b['is_featured'], 'status' => (string) $b['status'], 'featured_image' => (string) ($b['featured_image'] ?? '')];
        $o = $this->openChangeFor((int) $blogId);
        if ($o) $f = $o['data'] + $f;
        return [$f, ['blog_id' => (int) $blogId, 'sub_id' => $o ? (int) $o['id'] : null, 'status' => $o ? $o['status'] : ($b['status'] === 'published' ? 'published' : 'unpublished'), 'reason' => $o ? (string) $o['reason'] : '',
                     'blocks' => count($b['sections'] ?? []), 'block_list' => $b['sections'] ?? [], 'was_published' => !empty($b['published_at'])]];
    }

    // ------------------------------------------------------------------ saving

    /**
     * Save the editor. $sec is her record from secretary_require(). $in the form, $coverUrl the already-saved cover (or null).
     * Returns ['ok', 'mode' => 'live'|'waiting', 'blog_id'?, 'sub_id'?] or ['ok' => false, 'error'].
     */
    public function save(array $sec, array $in, ?string $coverUrl, ?int $blogId, ?int $subId): array
    {
        $approval = self::approvalNeeded($this->db, (int) $sec['id']);
        $sub = null;
        if ($subId) {
            $sub = $this->submission($subId);
            if (!$sub || !in_array($sub['status'], ['waiting', 'sent_back'], true)) return ['ok' => false, 'error' => 'That post is no longer waiting. Reload the list.'];
            if ($sub['kind'] === 'change' && $sub['blog_id']) { $blogId = (int) $sub['blog_id']; }
        }
        $blog = null;
        if ($blogId) {
            $blog = $this->db->prepare('SELECT id, title, status, featured_image, updated_at FROM blogs WHERE id = ?');
            $blog->execute([$blogId]);
            $blog = $blog->fetch(PDO::FETCH_ASSOC);
            if (!$blog) return ['ok' => false, 'error' => 'That blog was not found (the admin may have deleted it).'];
        }
        $isNew = !$blogId;
        [$p, $errors] = $this->fields($in, $isNew, $approval);
        if ($errors) return ['ok' => false, 'error' => implode(' ', $errors)];
        $open = $blogId ? $this->openChangeFor($blogId) : null;
        $cover = $coverUrl ?: ($sub['data']['featured_image'] ?? ($open['data']['featured_image'] ?? ($blog['featured_image'] ?? '')));
        if (trim((string) $cover) === '') return ['ok' => false, 'error' => 'A cover image is required.'];
        $p['featured_image'] = (string) $cover;
        $uid = (int) $sec['user_id'];
        $me = secretary_person_name($this->db, $uid);

        if ($approval) {
            $json = json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $target = $sub ?: $open;
            if ($target) {
                $was = $target['status'];
                $this->db->prepare("UPDATE secretary_blog_submissions SET payload = ?, title = ?, status = 'waiting', reason = NULL, rev = rev + 1, secretary_id = ? WHERE id = ?")
                         ->execute([$json, mb_substr($p['title'], 0, 255), (int) $sec['id'], (int) $target['id']]);
                $this->log($was === 'sent_back' ? 'resubmitted' : ($isNew ? 'submitted' : 'change_submitted'), $blogId, (int) $target['id'], (int) $sec['id'], $uid, $me, 'secretary', $p['title'], $was === 'waiting' ? 'edited while waiting' : null);
                $id = (int) $target['id'];
            } else {
                $this->db->prepare('INSERT INTO secretary_blog_submissions (kind, blog_id, secretary_id, title, payload, status, rev, base_updated_at) VALUES (?, ?, ?, ?, ?, \'waiting\', 1, ?)')
                         ->execute([$isNew ? 'new' : 'change', $blogId ?: null, (int) $sec['id'], mb_substr($p['title'], 0, 255), $json, $blog ? (string) $blog['updated_at'] : null]);
                $id = (int) $this->db->lastInsertId();
                $this->log($isNew ? 'submitted' : 'change_submitted', $blogId, $id, (int) $sec['id'], $uid, $me, 'secretary', $p['title'], null);
            }
            $this->notifyAdmins(($isNew ? 'New blog post' : 'Blog change') . ' waiting for approval', $me . ' sent “' . $p['title'] . '”.');
            return ['ok' => true, 'mode' => 'waiting', 'sub_id' => $id, 'blog_id' => $blogId];
        }

        // approval off: live at once, through the existing blog code, exactly as the admin's
        $res = $this->writeLive($blogId, $p, $uid);
        if (empty($res['ok'])) return $res;
        $bid = (int) $res['blog_id'];
        if ($sub && $sub['kind'] === 'new') {
            $this->db->prepare("UPDATE secretary_blog_submissions SET status = 'approved', decided_by = NULL, decided_by_name = ?, decided_at = CURRENT_TIMESTAMP, result_blog_id = ? WHERE id = ?")->execute([$me, $bid, (int) $sub['id']]);
        }
        $this->log($isNew ? 'created' : 'updated', $bid, $sub ? (int) $sub['id'] : null, (int) $sec['id'], $uid, $me, 'secretary', $p['title'], $isNew ? ($p['status'] === 'published' ? 'published' : 'saved as draft') : null);
        return ['ok' => true, 'mode' => 'live', 'blog_id' => $bid];
    }

    /** Create or update a blog through BlogController. $asUserId is the author of a new post (the controller reads it from the session). */
    private function writeLive(?int $blogId, array $p, int $asUserId): array
    {
        $c = new BlogController($this->db);
        $data = ['title' => $p['title'], 'excerpt' => $p['excerpt'], 'content' => $p['content'], 'meta_description' => $p['meta_description'], 'meta_title' => $p['meta_title'],
                 'meta_keywords' => $p['meta_keywords'], 'category_id' => $p['category_id'] ?: '', 'tags' => $p['tags'], 'is_featured' => $p['is_featured']];
        $keep = $_SESSION['user_id'] ?? null;
        try {
            if ($blogId) {
                $r = $c->update($blogId, $data, $p['featured_image'] ?: null);
                return !empty($r['success']) ? ['ok' => true, 'blog_id' => $blogId] : ['ok' => false, 'error' => (string) ($r['message'] ?? 'Could not save the blog.')];
            }
            $_SESSION['user_id'] = $asUserId;   // the author of the new post
            $r = $c->create($data + ['status' => $p['status'] === 'published' ? 'published' : 'draft'], $p['featured_image']);
            return !empty($r['success']) ? ['ok' => true, 'blog_id' => (int) $r['blog_id']] : ['ok' => false, 'error' => (string) ($r['message'] ?? 'Could not save the blog.')];
        } finally {
            if ($keep === null) unset($_SESSION['user_id']); else $_SESSION['user_id'] = $keep;
        }
    }

    // ------------------------------------------------------------------ unpublish, publish again

    public function setPublished(array $sec, int $blogId, bool $publish): array
    {
        $st = $this->db->prepare('SELECT id, title, status, published_at FROM blogs WHERE id = ?');
        $st->execute([$blogId]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) return ['ok' => false, 'error' => 'That blog was not found.'];
        if ($publish && $b['status'] === 'published') return ['ok' => false, 'error' => 'That blog is already published.'];
        if (!$publish && $b['status'] !== 'published') return ['ok' => false, 'error' => 'That blog is not published.'];
        if ($publish && self::approvalNeeded($this->db, (int) $sec['id']) && empty($b['published_at'])) {
            return ['ok' => false, 'error' => 'This post was never published. Edit it and send it for approval instead.'];
        }
        $r = (new BlogController($this->db))->update($blogId, ['status' => $publish ? 'published' : 'draft']);
        if (empty($r['success'])) return ['ok' => false, 'error' => 'Could not change the blog.'];
        $uid = (int) $sec['user_id'];
        $this->log($publish ? 'republished' : 'unpublished', $blogId, null, (int) $sec['id'], $uid, secretary_person_name($this->db, $uid), 'secretary', (string) $b['title'], null);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ the admin: approve, send back

    /** Waiting items for the admin, oldest first. */
    public function waiting(): array
    {
        $out = [];
        foreach ($this->db->query("SELECT id FROM secretary_blog_submissions WHERE status = 'waiting' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) $out[] = $this->submission((int) $id);
        return $out;
    }

    public function waitingCount(): int
    {
        try { return (int) $this->db->query("SELECT COUNT(*) FROM secretary_blog_submissions WHERE status = 'waiting'")->fetchColumn(); } catch (Throwable $e) { return 0; }
    }

    /** Approve and publish through the existing blog code. $rev is the version the admin looked at. */
    public function approve(int $adminId, int $subId, int $rev): array
    {
        $s = $this->submission($subId);
        if (!$s) return ['ok' => false, 'error' => 'That item was not found.'];
        if ($s['status'] !== 'waiting') return ['ok' => false, 'error' => 'That item is no longer waiting (someone already decided, or it was sent back).'];
        if ((int) $s['rev'] !== $rev) return ['ok' => false, 'error' => 'The secretary changed this meanwhile. Open it again and check the new version.'];
        if ($s['kind'] === 'change') {
            $x = $this->db->prepare('SELECT 1 FROM blogs WHERE id = ?'); $x->execute([(int) $s['blog_id']]);
            if (!$x->fetchColumn()) return ['ok' => false, 'error' => 'The blog this change was for no longer exists. Send it back instead.'];
        }
        $claim = $this->db->prepare("UPDATE secretary_blog_submissions SET status = 'approving' WHERE id = ? AND status = 'waiting' AND rev = ?");
        $claim->execute([$subId, $rev]);
        if ($claim->rowCount() !== 1) return ['ok' => false, 'error' => 'Someone else is deciding this item. Reload the page.'];
        $me = secretary_person_name($this->db, $adminId);
        try {
            $p = $s['data'];
            $p['status'] = $s['kind'] === 'new' ? 'published' : ($p['status'] ?? 'draft');
            $res = $this->writeLive($s['kind'] === 'change' ? (int) $s['blog_id'] : null, $p, (int) $s['by_user_id']);
            if (empty($res['ok'])) throw new RuntimeException((string) $res['error']);
        } catch (Throwable $e) {
            $this->db->prepare("UPDATE secretary_blog_submissions SET status = 'waiting' WHERE id = ? AND status = 'approving'")->execute([$subId]);
            return ['ok' => false, 'error' => 'Could not publish: ' . $e->getMessage()];
        }
        $this->db->prepare("UPDATE secretary_blog_submissions SET status = 'approved', decided_by = ?, decided_by_name = ?, decided_at = CURRENT_TIMESTAMP, result_blog_id = ? WHERE id = ?")
                 ->execute([$adminId, $me, (int) $res['blog_id'], $subId]);
        $this->log('approved', (int) $res['blog_id'], $subId, (int) $s['secretary_id'], $adminId, $me, 'admin', (string) $s['title'], null);
        $this->notify((int) $s['by_user_id'], 'Your blog was approved', '“' . $s['title'] . '” is now published.');
        return ['ok' => true, 'blog_id' => (int) $res['blog_id']];
    }

    public function sendBack(int $adminId, int $subId, string $reason, int $rev): array
    {
        $reason = trim($reason);
        if ($reason === '') return ['ok' => false, 'error' => 'Write a short reason for the secretary.'];
        if (mb_strlen($reason) > 300) return ['ok' => false, 'error' => 'Keep the reason short (300 letters at most).'];
        $s = $this->submission($subId);
        if (!$s) return ['ok' => false, 'error' => 'That item was not found.'];
        if ($s['status'] !== 'waiting') return ['ok' => false, 'error' => 'That item is no longer waiting.'];
        $up = $this->db->prepare("UPDATE secretary_blog_submissions SET status = 'sent_back', reason = ?, decided_by = ?, decided_by_name = ?, decided_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'waiting' AND rev = ?");
        $me = secretary_person_name($this->db, $adminId);
        $up->execute([$reason, $adminId, $me, $subId, $rev]);
        if ($up->rowCount() !== 1) return ['ok' => false, 'error' => 'The secretary changed this meanwhile. Open it again.'];
        $this->log('sent_back', $s['blog_id'] ? (int) $s['blog_id'] : null, $subId, (int) $s['secretary_id'], $adminId, $me, 'admin', (string) $s['title'], $reason);
        $this->notify((int) $s['by_user_id'], 'Your blog was sent back', '“' . $s['title'] . '”: ' . $reason);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ compare

    /** What changed in a "change" item: field => [old, new] for the short fields, and the content as a list of line differences. */
    public function compare(array $s): array
    {
        $out = ['fields' => [], 'lines' => [], 'live_changed' => false, 'blog' => null];
        $new = $s['data'];
        if ($s['kind'] !== 'change' || !$s['blog_id']) return $out;
        $b = (new BlogController($this->db))->getBlogDetails((int) $s['blog_id']);
        if (!$b) return $out;
        $out['blog'] = $b;
        $out['live_changed'] = (string) $s['base_updated_at'] !== '' && (string) $b['updated_at'] !== (string) $s['base_updated_at'];
        $cat = static function (PDO $db, $id): string { if (!$id) return ''; $q = $db->prepare('SELECT name FROM blog_categories WHERE id = ?'); $q->execute([$id]); return (string) $q->fetchColumn(); };
        $old = ['Title' => (string) $b['title'], 'Excerpt' => (string) ($b['excerpt'] ?? ''), 'SEO description' => (string) ($b['meta_description'] ?? ''), 'Meta title' => (string) ($b['meta_title'] ?? ''),
                'Meta keywords' => (string) ($b['meta_keywords'] ?? ''), 'Category' => $cat($this->db, $b['category_id']), 'Tags' => implode(', ', array_map(static fn($t) => (string) $t['name'], $b['tags'] ?? [])),
                'Popular slider' => $b['is_featured'] ? 'Yes' : 'No', 'Cover image' => (string) ($b['featured_image'] ?? '')];
        $nw = ['Title' => (string) ($new['title'] ?? ''), 'Excerpt' => (string) ($new['excerpt'] ?? ''), 'SEO description' => (string) ($new['meta_description'] ?? ''), 'Meta title' => (string) ($new['meta_title'] ?? ''),
               'Meta keywords' => (string) ($new['meta_keywords'] ?? ''), 'Category' => $cat($this->db, $new['category_id'] ?? null), 'Tags' => implode(', ', (array) ($new['tags'] ?? [])),
               'Popular slider' => !empty($new['is_featured']) ? 'Yes' : 'No', 'Cover image' => (string) ($new['featured_image'] ?? '')];
        foreach ($old as $k => $v) if ($v !== $nw[$k]) $out['fields'][$k] = [$v, $nw[$k]];
        $out['lines'] = self::diffLines(self::textLines((string) ($b['content'] ?? '')), self::textLines((string) ($new['content'] ?? '')));
        return $out;
    }

    /** HTML to plain lines (a new line at every paragraph, heading, list item and break). */
    public static function textLines(string $html): array
    {
        $t = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote|summary|details)>|<br\s*/?>#i', "\n", $html);
        $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return array_values(array_filter(array_map(static fn($l) => trim(preg_replace('/\s+/u', ' ', $l)), explode("\n", $t)), static fn($l) => $l !== ''));
    }

    /** Line diff (longest common subsequence). Returns [['=' | '-' | '+', text], ...]. */
    public static function diffLines(array $a, array $b): array
    {
        $n = count($a); $m = count($b);
        if ($n * $m > 4000000) return array_merge(array_map(fn($x) => ['-', $x], $a), array_map(fn($x) => ['+', $x], $b));
        $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
        $out = []; $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) { $out[] = ['=', $a[$i]]; $i++; $j++; }
            elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) { $out[] = ['-', $a[$i++]]; }
            else { $out[] = ['+', $b[$j++]]; }
        }
        while ($i < $n) $out[] = ['-', $a[$i++]];
        while ($j < $m) $out[] = ['+', $b[$j++]];
        return $out;
    }

    // ------------------------------------------------------------------ the record and the notices

    public function log(string $action, ?int $blogId, ?int $subId, ?int $secId, ?int $actorId, string $name, string $role, string $title, ?string $detail): void
    {
        $this->db->prepare('INSERT INTO secretary_blog_log (action, blog_id, submission_id, secretary_id, actor_user_id, actor_name, actor_role, title, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                 ->execute([$action, $blogId, $subId, $secId, $actorId, mb_substr($name, 0, 160), $role, mb_substr($title, 0, 255), $detail === null ? null : mb_substr($detail, 0, 400),
                            (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s')]);
        if ($role === 'secretary' && $secId) {   // the activity record (one shared function; the key says where it came from, so it is never recorded twice)
            $map = ['created' => 'posted', 'updated' => 'edited', 'submitted' => 'sent for approval', 'change_submitted' => 'sent a change', 'resubmitted' => 'sent again', 'unpublished' => 'unpublished', 'republished' => 'published again'];
            secretary_activity($this->db, 'blogs', $map[$action] ?? $action, $secId, $name, $title, $detail, 'blog:' . (int) $this->db->lastInsertId());
        }
    }

    private function notify(int $userId, string $title, string $body): void
    {
        try { PortalNotifications::notify($this->db, $userId, 'blog_approval', $title, $body, $this->pathFor($userId)); } catch (Throwable $e) { error_log('[SecretaryBlogService] notify: ' . $e->getMessage()); }
    }

    private function pathFor(int $userId): string
    {
        $st = $this->db->prepare('SELECT r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $st->execute([$userId]);
        return $st->fetchColumn() === 'admin' ? 'admin/secretaries/blog-approvals.php' : 'secretary/blogs/index.php';
    }

    /** Tell every active admin (the existing in-app and push code, only called). */
    private function notifyAdmins(string $title, string $body): void
    {
        foreach ($this->db->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'admin' AND u.is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $id) $this->notify((int) $id, $title, $body);
    }
}
