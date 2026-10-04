<?php
// app/v1/helpers/study_location.php — how a student learns (online or physical) and, for physical classes only, where (Thika or Nairobi).
// Mode lives in registrations.study_mode; the branch in registrations.study_location (migration 051). Online students have no location.

if (!function_exists('study_locations')) {
    function study_locations(): array
    {
        return ['thika' => 'Thika', 'nairobi' => 'Nairobi'];
    }

    function study_location_supported(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (bool) $pdo->query("SHOW COLUMNS FROM registrations LIKE 'study_location'")->fetch();
            } catch (Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** SQL fragment for the location column, safe before the migration is applied. */
    function study_location_col(PDO $pdo, string $alias = 'r'): string
    {
        return study_location_supported($pdo) ? "$alias.study_location" : 'NULL';
    }

    /**
     * Save mode and/or location on a registration. Null or invalid values are left as they are. A location is only kept for
     * physical classes: choosing Online clears it.
     */
    function study_placement_set(PDO $pdo, int $registrationId, ?string $mode, ?string $location): void
    {
        if (in_array($mode, ['online', 'physical'], true)) {
            $pdo->prepare('UPDATE registrations SET study_mode = ? WHERE id = ?')->execute([$mode, $registrationId]);
        }
        if (!study_location_supported($pdo)) {
            return;
        }
        $cur = $pdo->prepare('SELECT study_mode FROM registrations WHERE id = ?');
        $cur->execute([$registrationId]);
        $effective = (string) $cur->fetchColumn();
        if ($effective === 'online') {
            $pdo->prepare('UPDATE registrations SET study_location = NULL WHERE id = ?')->execute([$registrationId]);
        } elseif (isset(study_locations()[$location ?? ''])) {
            $pdo->prepare('UPDATE registrations SET study_location = ? WHERE id = ?')->execute([$location, $registrationId]);
        }
    }

    /** "Online", or "Physical · Thika" (a location is only shown for physical classes). */
    function study_placement_label(?string $mode, ?string $location): string
    {
        if ($mode === 'online') {
            return 'Online';
        }
        $parts = [];
        if ($mode === 'physical') $parts[] = 'Physical';
        if (isset(study_locations()[$location ?? ''])) $parts[] = study_locations()[$location];
        return implode(' · ', $parts);
    }

    /**
     * Mode + location selects. The location field only shows when Physical is chosen; for Online it disappears (and is not
     * submitted). $cur holds study_mode / study_location to preselect. $required makes the mode, and the location when shown, mandatory.
     * $keepOption adds a "Keep current" mode choice (used when transferring).
     */
    function study_placement_fields(array $cur = [], bool $required = true, string $colClass = 'col-md-6', bool $keepOption = false): string
    {
        static $uid = 0, $script = false;
        $id = 'sp' . (++$uid);
        $mode = $cur['study_mode'] ?? '';
        $loc = $cur['study_location'] ?? '';
        $req = $required ? ' required' : '';
        $h = '<div class="' . $colClass . '"><label class="form-label">Mode of study' . ($required ? ' *' : '') . '</label>'
           . '<select name="study_mode" class="form-select" data-sp="' . $id . '" data-sp-keep="' . ($keepOption ? '1' : '0') . '"' . $req . '>'
           . '<option value="">' . ($keepOption ? 'Keep current' : '— Select —') . '</option>';
        foreach (['online' => 'Online', 'physical' => 'Physical'] as $k => $l) {
            $h .= '<option value="' . $k . '"' . ($mode === $k ? ' selected' : '') . '>' . $l . '</option>';
        }
        $showLoc = $mode === 'physical' || ($keepOption && $mode === '');
        $hidden = $showLoc ? '' : ' style="display:none"';
        $h .= '</select></div><div class="' . $colClass . '" data-sp-loc="' . $id . '"' . $hidden . '><label class="form-label">Learning location' . ($required ? ' *' : '') . '</label>'
            . '<select name="study_location" class="form-select" data-sp-req="' . ($required ? '1' : '0') . '"' . ($mode === 'physical' && $required ? ' required' : '') . ($showLoc ? '' : ' disabled') . '>'
            . '<option value="">' . ($keepOption ? 'Keep current' : '— Select —') . '</option>';
        foreach (study_locations() as $k => $l) {
            $h .= '<option value="' . $k . '"' . ($loc === $k ? ' selected' : '') . '>' . $l . '</option>';
        }
        $h .= '</select></div>';
        if (!$script) {
            $script = true;
            $h .= '<script>(function(){function sync(m){var box=document.querySelector(\'[data-sp-loc="\'+m.dataset.sp+\'"]\');if(!box)return;var s=box.querySelector("select"),show=m.value==="physical"||(m.dataset.spKeep==="1"&&m.value==="");'
                . 'box.style.display=show?"":"none";s.disabled=!show;if(!show){s.value="";s.required=false;}else{s.required=s.dataset.spReq==="1"&&m.value==="physical";}}'
                . 'document.addEventListener("change",function(e){if(e.target&&e.target.dataset&&e.target.dataset.sp)sync(e.target);});'
                . 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("select[data-sp]").forEach(sync);});})();</script>';
        }
        return $h;
    }
}
