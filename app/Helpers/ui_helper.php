<?php

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
