<?php

namespace App\Services;

use App\Exceptions\PointException;
use App\Models\PerfectDayModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class PerfectDayService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function evaluateDay(int $childId, DateTimeInterface $date): array
    {
        $schedule = (new TaskCompletionService(db: $this->db))->getTodayProgress($childId, $date);
        $required = 0;
        $completed = 0;
        foreach ($schedule['routines'] as $routine) {
            if (empty($routine['is_required']) || empty($routine['perfect_day_eligible'])) {
                continue;
            }
            foreach ($routine['tasks'] as $task) {
                if (! $task['is_required']) {
                    continue;
                }
                ++$required;
                if ($task['completion_status'] === 'completed') {
                    ++$completed;
                }
            }
        }

        return ['date' => $schedule['date'], 'required_total' => $required,
            'required_completed' => $completed, 'qualifies' => $required > 0,
            'is_perfect' => $required > 0 && $required === $completed];
    }

    public function awardIfQualified(int $childId, DateTimeInterface $date): ?array
    {
        $local = DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone(app_timezone()));
        $this->db->transException(true)->transStart();
        try {
            // Same child lock as task completion/approval serializes concurrent awards.
            if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
                $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
                $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$childId]);
            }
            $model = new PerfectDayModel($this->db);
            $record = $model->where('child_id', $childId)->where('perfect_date', $local->format('Y-m-d'))->first();
            if ($record === null) {
                $day = $this->evaluateDay($childId, $local);
                if (! $day['is_perfect']) {
                    $this->db->transComplete();
                    return null;
                }
                $id = $model->insert([
                    'child_id' => $childId, 'perfect_date' => $day['date'],
                    'completed_tasks' => $day['required_completed'], 'required_tasks' => $day['required_total'],
                    'bonus_points' => config(\Config\PerfectDay::class)->bonusPoints,
                    'created_at' => (new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format('Y-m-d H:i:s'),
                ], true);
                if ($id === false) {
                    throw new PointException('Perfect Day tidak dapat direkodkan.');
                }
                $record = $model->find($id);
            }
            (new PointService(db: $this->db))->awardPerfectDayPoints($childId, (int) $record['id']);
            $this->db->transComplete();
            return $record;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** Read-only dashboard state. Undo hides success but never issues a second award. */
    public function successForDay(int $childId, DateTimeInterface $date): ?array
    {
        $day = $this->evaluateDay($childId, $date);
        if (! $day['is_perfect']) {
            return null;
        }
        return (new PerfectDayModel($this->db))->where('child_id', $childId)->where('perfect_date', $day['date'])->first();
    }
}
