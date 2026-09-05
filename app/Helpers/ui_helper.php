<?php

/** Version local assets by content so an existing PWA cannot reuse an older build. */
function ui_asset_url(string $path): string
{
    $file = FCPATH . $path;
    return base_url($path) . (is_file($file) ? '?v=' . substr(hash_file('sha256', $file), 0, 12) : '');
}

function ui_transaction_badge(array $transaction): string
{
    return match ($transaction['type'] ?? '') {
        'task' => 'text-bg-success',
        'reversal' => 'text-bg-danger',
        'bonus' => 'text-bg-warning',
        'reward' => 'text-bg-info',
        'adjustment' => (int) ($transaction['points'] ?? 0) < 0 ? 'text-bg-danger' : 'text-bg-secondary',
        default => 'text-bg-secondary',
    };
}

function ui_icon(string $name, string $label = ''): string
{
    $icons = ['star', 'fire', 'gift', 'lightbulb', 'clock', 'check', 'arrow-left', 'bars', 'lock', 'user', 'cat', 'robot', 'rocket', 'sun'];
    if (! in_array($name, $icons, true)) {
        return '';
    }
    return '<i class="fa-solid fa-' . $name . ' ui-icon" aria-hidden="true"></i>'
        . ($label === '' ? '' : '<span class="visually-hidden">' . esc($label) . '</span>');
}

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
function ui_avatar_options(): array
{
    return ['user' => 'Kawan', 'cat' => 'Kucing', 'robot' => 'Robot', 'rocket' => 'Roket'];
}

function ui_image_url(?string $name, bool $child = false): ?string
{
    return preg_match('/\A[a-f0-9]{32}\.jpg\z/', $name ?? '')
        ? route_to($child ? 'child.image' : 'parent.image', $name) : null;
}

function ui_avatar(?string $avatar, bool $child = true): string
{
    $url = ui_image_url($avatar, $child);
    return $url !== null ? '<img class="profile-avatar" src="' . esc($url, 'attr') . '" alt="Gambar profil">'
        : '<span class="profile-avatar avatar-placeholder" role="img" aria-label="Avatar">' . ui_icon(array_key_exists($avatar ?? '', ui_avatar_options()) ? $avatar : 'user') . '</span>';
}

function ui_date(?string $value): string
{
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value ?? '', new \DateTimeZone(app_timezone()));
    return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d/m/Y') : '—';
}

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
        $label .= ' · ' . ui_date($task['start_date']);
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
