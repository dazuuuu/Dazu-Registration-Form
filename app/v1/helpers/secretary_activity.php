<?php
/**
 * The activity record: ONE small shared function every part of the secretary portal writes through. The dedupe key (where the event came from, for example "blog:12", "enq:40", "task:7:tick")
 * is UNIQUE in the table, so the same event can never be recorded twice, whoever calls it and however often.
 */
if (!function_exists('secretary_activity')) {
    /**
     * @param string      $area       blogs, enquiries, tasks or diary
     * @param string      $action     a short word: posted, edited, unpublished, added, status, ticked, cancelled ...
     * @param int|null    $secretaryId the secretary who did it (secretaries.id)
     * @param string      $actorName  her name (name only)
     * @param string      $subject    what it was about (a title, a person's name)
     * @param string|null $detail     a short note
     * @param string      $dedupeKey  unique per event, built from its source
     * @return bool true when it was recorded now, false when that event was already there (or the table is not installed)
     */
    function secretary_activity(PDO $db, string $area, string $action, ?int $secretaryId, string $actorName, string $subject, ?string $detail, string $dedupeKey): bool
    {
        try {
            $tx = $db->inTransaction();   // inside a transaction a failed insert needs a savepoint (PostgreSQL), outside it does not
            if ($tx) $db->exec('SAVEPOINT sec_act');
            try {
                $db->prepare('INSERT INTO secretary_activity (secretary_id, actor_name, area, action, subject, detail, dedupe_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$secretaryId, mb_substr($actorName, 0, 160), $area, mb_substr($action, 0, 24), mb_substr($subject, 0, 255), $detail === null ? null : mb_substr($detail, 0, 400), mb_substr($dedupeKey, 0, 60),
                              (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s')]);
                if ($tx) $db->exec('RELEASE SAVEPOINT sec_act');
                return true;
            } catch (PDOException $e) {
                if ($tx) $db->exec('ROLLBACK TO SAVEPOINT sec_act');
                if ((string) $e->getCode() === '23000' || (string) $e->getCode() === '23505' || stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) return false;   // already recorded
                throw $e;
            }
        } catch (Throwable $e) {
            error_log('[secretary_activity] ' . $e->getMessage());   // the record must never break the action itself
            return false;
        }
    }
}
