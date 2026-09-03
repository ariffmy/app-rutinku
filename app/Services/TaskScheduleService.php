<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Shared schedule rules for forms, child visibility and completion authorization. */
class TaskScheduleService
{
    public function payload(array $data): array
    {
        $duration = filter_var($data['duration_minutes'] ?? 15, FILTER_VALIDATE_INT);
        if ($duration === false || $duration < 1 || $duration > 1440) {
            throw new InvalidArgumentException('Tempoh mesti antara 1 hingga 1440 minit.');
        }
        $type = $data['schedule_type'] ?? 'inherit';
        if (! in_array($type, ['inherit', 'once', 'weekly', 'monthly', 'daily'], true)) {
            throw new InvalidArgumentException('Pilih kekerapan yang sah.');
        }
        $date = null;
        $days = null;
        if ($type !== 'inherit') {
            $date = $data['start_date'] ?? '';
            if (! is_string($date) || ! $this->validDate($date)) {
                throw new InvalidArgumentException('Pilih tarikh mula yang sah.');
            }
        }
        if ($type === 'weekly') {
            $input = $data['repeat_days'] ?? [];
            $input = is_string($input) ? explode(',', $input) : $input;
            if (! is_array($input) || $input === [] || count($input) > 7) {
                throw new InvalidArgumentException('Pilih sekurang-kurangnya satu hari ulangan mingguan.');
            }
            $normalized = [];
            foreach ($input as $day) {
                $day = filter_var($day, FILTER_VALIDATE_INT);
                if ($day === false || $day < 1 || $day > 7) {
                    throw new InvalidArgumentException('Hari ulangan mingguan tidak sah.');
                }
                $normalized[] = $day;
            }
            $normalized = array_unique($normalized);
            sort($normalized);
            $days = implode(',', $normalized);
        }

        return ['duration_minutes' => $duration, 'schedule_type' => $type, 'start_date' => $date, 'repeat_days' => $days];
    }

    public function isScheduled(array $task, DateTimeInterface $at, array $routineDays): bool
    {
        $local = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
        $type = $task['schedule_type'] ?? 'inherit';
        if (! in_array((int) $local->format('N'), array_map('intval', $routineDays), true)) {
            return false;
        }
        if ($type === 'inherit') {
            return true;
        }
        $start = $task['start_date'] ?? '';
        if (! is_string($start) || ! $this->validDate($start) || $local->format('Y-m-d') < $start) {
            return false;
        }

        return match ($type) {
            'once' => $local->format('Y-m-d') === $start,
            'daily' => true,
            'weekly' => in_array($local->format('N'), explode(',', (string) ($task['repeat_days'] ?? '')), true),
            // No rollover: a task on the 31st skips months without that date.
            'monthly' => $local->format('d') === substr($start, 8, 2),
            default => false,
        };
    }

    public function assertWithinRoutine(array $task, array $routineDays): void
    {
        $days = array_map('intval', $routineDays);
        if (($task['schedule_type'] ?? 'inherit') === 'weekly'
            && array_diff(array_map('intval', explode(',', (string) $task['repeat_days'])), $days) !== []) {
            throw new InvalidArgumentException('Hari tugasan mesti termasuk dalam hari rutin. Ubah hari rutin dahulu jika perlu.');
        }
        if (($task['schedule_type'] ?? 'inherit') === 'once') {
            $date = new DateTimeImmutable($task['start_date'], new DateTimeZone(app_timezone()));
            if (! in_array((int) $date->format('N'), $days, true)) {
                throw new InvalidArgumentException('Tarikh tugasan sekali mesti jatuh pada hari rutin. Pilih tarikh lain atau ubah hari rutin.');
            }
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
