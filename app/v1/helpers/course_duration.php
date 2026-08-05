<?php
// app/v1/helpers/course_duration.php — shared course study duration helpers

/**
 * @return array{months:int,weeks:int,days:int,hours:int,schedule:string}
 */
function course_duration_values(?array $course): array
{
    return [
        'months'   => max(0, (int) ($course['duration_months'] ?? 0)),
        'weeks'    => max(0, (int) ($course['weeks_of_study'] ?? 0)),
        'days'     => max(0, (int) ($course['days_of_study'] ?? 0)),
        'hours'    => max(0, (int) ($course['hours_of_study'] ?? 0)),
        'schedule' => trim((string) ($course['study_schedule'] ?? '')),
    ];
}

function course_duration_has_any(array $data): bool
{
    $months = ($data['duration_months'] ?? '') === '' ? 0 : max(0, (int) $data['duration_months']);
    $weeks = ($data['weeks_of_study'] ?? '') === '' || $data['weeks_of_study'] === null ? 0 : max(0, (int) $data['weeks_of_study']);
    $days = ($data['days_of_study'] ?? '') === '' || $data['days_of_study'] === null ? 0 : max(0, (int) $data['days_of_study']);
    $hours = ($data['hours_of_study'] ?? '') === '' || $data['hours_of_study'] === null ? 0 : max(0, (int) $data['hours_of_study']);
    return $months > 0 || $weeks > 0 || $days > 0 || $hours > 0;
}

function course_duration_validate_message(array $data): ?string
{
    if (!course_duration_has_any($data)) {
        return 'Enter at least one study duration: hours, days, weeks, or months.';
    }
    foreach (['duration_months' => 36, 'weeks_of_study' => 520, 'days_of_study' => 3650, 'hours_of_study' => 8760] as $field => $max) {
        if (($data[$field] ?? '') === '' || $data[$field] === null) {
            continue;
        }
        $v = (int) $data[$field];
        if ($v < 0 || $v > $max) {
            return 'Duration value out of allowed range for ' . str_replace('_', ' ', $field) . '.';
        }
    }
    return null;
}

/**
 * Normalize POST/API duration fields for CourseModel.
 */
function course_normalize_duration_input(array $data): array
{
    $nullableInt = static function ($key) use ($data) {
        if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
            return null;
        }
        $v = max(0, (int) $data[$key]);
        return $v > 0 ? $v : null;
    };

    return [
        'duration_months' => ($data['duration_months'] ?? '') === '' ? 0 : max(0, (int) $data['duration_months']),
        'weeks_of_study'  => $nullableInt('weeks_of_study'),
        'days_of_study'   => $nullableInt('days_of_study'),
        'hours_of_study'  => $nullableInt('hours_of_study'),
        'study_schedule'  => isset($data['study_schedule']) && trim((string) $data['study_schedule']) !== ''
            ? trim((string) $data['study_schedule'])
            : null,
    ];
}

function course_study_summary(?array $course): string
{
    if (!$course) {
        return '';
    }
    $v = course_duration_values($course);
    $bits = [];
    if ($v['hours'] > 0) {
        $bits[] = $v['hours'] . ' hour' . ($v['hours'] === 1 ? '' : 's');
    }
    if ($v['days'] > 0) {
        $bits[] = $v['days'] . ' day' . ($v['days'] === 1 ? '' : 's');
    }
    if ($v['weeks'] > 0) {
        $bits[] = $v['weeks'] . ' week' . ($v['weeks'] === 1 ? '' : 's');
    }
    if ($v['months'] > 0) {
        $bits[] = $v['months'] . ' month' . ($v['months'] === 1 ? '' : 's');
    }
    if ($v['schedule'] !== '') {
        $bits[] = $v['schedule'];
    }
    return implode(' · ', $bits);
}

/**
 * Primary unit for fee scaling on SmS enrollment (most specific duration wins).
 *
 * @return array{unit:string,value:int,label:string}
 */
function course_primary_duration_meta(?array $course): array
{
    if (!$course) {
        return ['unit' => 'months', 'value' => 1, 'label' => '1 month'];
    }
    $v = course_duration_values($course);
    $summary = course_study_summary($course);
    if ($v['hours'] > 0) {
        return ['unit' => 'hours', 'value' => $v['hours'], 'label' => $summary ?: ($v['hours'] . ' hour' . ($v['hours'] === 1 ? '' : 's'))];
    }
    if ($v['days'] > 0) {
        return ['unit' => 'days', 'value' => $v['days'], 'label' => $summary ?: ($v['days'] . ' day' . ($v['days'] === 1 ? '' : 's'))];
    }
    if ($v['weeks'] > 0) {
        return ['unit' => 'weeks', 'value' => $v['weeks'], 'label' => $summary ?: ($v['weeks'] . ' week' . ($v['weeks'] === 1 ? '' : 's'))];
    }
    if ($v['months'] > 0) {
        return ['unit' => 'months', 'value' => $v['months'], 'label' => $summary ?: ($v['months'] . ' month' . ($v['months'] === 1 ? '' : 's'))];
    }
    return ['unit' => 'months', 'value' => 1, 'label' => '1 month'];
}

/** Months equivalent for payment plans / registration flows. */
function course_effective_months(?array $course): int
{
    $v = course_duration_values($course);
    if ($v['months'] > 0) {
        return $v['months'];
    }
    if ($v['weeks'] > 0) {
        return max(1, (int) ceil($v['weeks'] / 4));
    }
    if ($v['days'] > 0) {
        return max(1, (int) ceil($v['days'] / 30));
    }
    if ($v['hours'] > 0) {
        return 1;
    }
    return 1;
}

function course_format_duration_unit(string $unit, int $value): string
{
    if ($value <= 0) {
        return '';
    }
    if ($unit === 'hours') {
        return $value . ' hour' . ($value === 1 ? '' : 's');
    }
    if ($unit === 'days') {
        return $value . ' day' . ($value === 1 ? '' : 's');
    }
    if ($unit === 'weeks') {
        return $value . ' week' . ($value === 1 ? '' : 's');
    }
    return $value . ' month' . ($value === 1 ? '' : 's');
}
