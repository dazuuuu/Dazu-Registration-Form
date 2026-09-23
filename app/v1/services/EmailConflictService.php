<?php
// app/v1/services/EmailConflictService.php
// When someone creates a tutor, employee, admin or student and the email already belongs to an account, the form can carry
// "replace_existing": the old account is deleted so the email can be registered fresh with the new role.

class EmailConflictService
{
    /** Tick-box shown under the email field of every create form. */
    public static function checkbox(): string
    {
        return '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="replaceExisting">'
             . '<label class="form-check-label small" for="replaceExisting">If this email already has an account, <strong>delete that account</strong> and register it as new</label></div>';
    }

    public static function hint(): string
    {
        return ' Tick "delete that account and register it as new" to override it.';
    }

    /**
     * Delete the account that owns $email (with its student registrations and role records) so the email is free again.
     * @return array{ok:bool, error?:string}
     */
    public static function release(PDO $pdo, string $email): array
    {
        $st = $pdo->prepare('SELECT id, role_id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([trim($email)]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return ['ok' => true];
        }
        $uid = (int) $u['id'];
        $actorRole = (int) ($_SESSION['role_id'] ?? 0);
        if ((int) $u['role_id'] === 1) {
            return ['ok' => false, 'error' => 'The superadmin account cannot be replaced.'];
        }
        if ($uid === (int) ($_SESSION['user_id'] ?? 0)) {
            return ['ok' => false, 'error' => 'You cannot replace your own account.'];
        }
        if ((int) $u['role_id'] === 2 && $actorRole !== 1) {
            return ['ok' => false, 'error' => 'Only a superadmin can replace an admin account.'];
        }
        try {
            $inTx = $pdo->inTransaction();
            if (!$inTx) $pdo->beginTransaction();
            $regs = $pdo->prepare('SELECT id FROM registrations WHERE user_id = ?');
            $regs->execute([$uid]);
            foreach ($regs->fetchAll(PDO::FETCH_COLUMN) as $rid) {
                foreach (['DELETE FROM payments WHERE registration_id = ?', 'DELETE FROM sms_students WHERE registration_id = ?'] as $sql) {
                    try { $pdo->prepare($sql)->execute([$rid]); } catch (Throwable $e) { /* table may not exist */ }
                }
                $pdo->prepare('DELETE FROM registrations WHERE id = ?')->execute([$rid]);
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            if (!$inTx) $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if (!$inTx && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[EmailConflictService::release] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not remove the existing account: ' . $e->getMessage()];
        }
    }
}
