<?php

/** Display local wall-clock times without changing stored schedule values. */
function ui_time(?string $time): string
{
    if (! preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time ?? '', $parts)) {
        return '—';
    }
    $hour = (int) $parts[1];
    $minute = (int) $parts[2];
    if ($hour > 23 || $minute > 59) {
        return '—';
    }
    $period = match (true) {
        $hour === 0 => 'tengah malam',
        $hour < 12 => 'pagi',
        $hour < 14 => 'tengah hari',
        $hour < 19 => 'petang',
        default => 'malam',
    };

    return ($hour % 12 ?: 12) . ($minute ? ':' . $parts[2] : '') . ' ' . $period;
}

/** Database timestamps are stored in the application's local timezone. */
function ui_datetime(?string $value): string
{
    if (! $value) {
        return '—';
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone(app_timezone()));
    if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
        return '—';
    }

    return $date->format('d/m/Y') . ', ' . ui_time($date->format('H:i'));
}

function ui_task_time(array $task): string
{
    $duration = (int) ($task['duration_minutes'] ?? 15);
    if (empty($task['task_time'])) {
        return 'Bila-bila masa · ' . $duration . ' minit';
    }
    $start = substr($task['task_time'], 0, 5);
    [$hour, $minute] = array_map('intval', explode(':', $start));
    $end = $hour * 60 + $minute + $duration;

    return ui_time($start) . ' – ' . ui_time(sprintf('%02d:%02d', intdiv($end, 60) % 24, $end % 60)) . ($end >= 1440 ? ' (esok)' : '');
}

function ui_task_schedule(array $task): string
{
    $label = ['inherit' => 'Ikut rutin', 'once' => 'Sekali pada hari rutin', 'daily' => 'Ikut rutin mulai tarikh', 'weekly' => 'Hari tertentu dalam rutin', 'monthly' => 'Bulanan pada hari rutin'][$task['schedule_type'] ?? 'inherit'] ?? 'Ikut rutin';
    if (! empty($task['start_date'])) {
        $label .= ' · ' . $task['start_date'];
    }
    if (($task['schedule_type'] ?? '') === 'weekly') {
        $names = [1 => 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'];
        $label .= ' · ' . implode(', ', array_map(static fn (string $day): string => $names[(int) $day] ?? '', explode(',', (string) $task['repeat_days'])));
    }

    return $label;
}

/** Translate display labels without changing stored enum values or request parameters. */
function ui_label(string $group, ?string $value): string
{
    $labels = lang('Ui.' . $group);

    return is_array($labels) ? ($labels[$value ?? ''] ?? lang('Ui.unknown')) : lang('Ui.unknown');
}

/** Translate only application-generated legacy prefixes, never user-authored descriptions. */
function ui_point_description(array $transaction): string
{
    $description = (string) ($transaction['description'] ?? '');
    if ($description === '') {
        return ui_label('transaction', $transaction['type']);
    }
    $prefixes = match ($transaction['type']) {
        'task' => ['Task selesai: ' => 'Tugasan selesai: ', 'Task completion #' => 'Penyelesaian tugasan #'],
        'reward' => ['Reward: ' => 'Ganjaran: ', 'Reward redemption #' => 'Penebusan ganjaran #'],
        'reversal' => ['Undo: ' => 'Batal: ', 'Batal: ' => 'Batal: '],
        default => [],
    };
    foreach ($prefixes as $source => $translated) {
        if (str_starts_with($description, $source)) {
            $suffix = substr($description, strlen($source));
            if ($transaction['type'] === 'reversal') {
                $suffix = $suffix === 'task completion' ? 'penyelesaian tugasan'
                    : ui_point_description(['type' => 'task', 'description' => $suffix]);
            }

            return $translated . $suffix;
        }
    }

    return $description;
}
