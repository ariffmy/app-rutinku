<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Exceptions\MissionException;
use App\Models\ChildWeeklyMissionModel;
use App\Models\UserModel;
use App\Models\WeeklyMissionModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class MissionService
{
    public const TYPES = ['routine_completion_count', 'perfect_day_count'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function create(int $parentId, array $input, array $childIds, DateTimeInterface $at): int
    {
        $family = $this->parentFamily($parentId);
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $type = (string) ($input['mission_type'] ?? '');
        $target = filter_var($input['target_value'] ?? null, FILTER_VALIDATE_INT);
        $bonus = filter_var($input['bonus_points'] ?? null, FILTER_VALIDATE_INT);
        $start = $this->date((string) ($input['start_date'] ?? ''));
        $end = $this->date((string) ($input['end_date'] ?? ''));
        $childIds = array_values(array_unique(array_filter(array_map('intval', $childIds), static fn (int $id): bool => $id > 0)));

        if ($title === '' || mb_strlen($title) > 160) {
            throw new MissionException('Nama misi wajib dan tidak boleh melebihi 160 aksara.');
        }
        if (mb_strlen($description) > 1000 || ! in_array($type, self::TYPES, true)) {
            throw new MissionException('Maklumat misi tidak sah.');
        }
        if ($target === false || $target < 1 || $target > 1000 || $bonus === false || $bonus < 0 || $bonus > 1000000) {
            throw new MissionException('Target atau bonus misi tidak sah.');
        }
        if ($start > $end || (int) $start->diff($end)->format('%a') > 6) {
            throw new MissionException('Julat misi mesti antara satu hingga tujuh hari.');
        }
        if ($childIds === []) {
            throw new MissionException('Pilih sekurang-kurangnya seorang Anak.');
        }

        $allowed = array_map('intval', array_column(array_filter(
            (new FamilyService())->children((int) $family['id']),
            static fn (array $child): bool => (bool) $child['is_active'],
        ), 'id'));
        if (array_diff($childIds, $allowed) !== []) {
            throw new AuthorizationException('Ibu bapa tidak boleh menetapkan misi kepada Anak ini.');
        }

        $local = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
        $this->db->transException(true)->transStart();
        try {
            $missionId = (new WeeklyMissionModel($this->db))->insert([
                'family_id' => (int) $family['id'],
                'title' => $title,
                'description' => $description === '' ? null : $description,
                'mission_type' => $type,
                'target_value' => $target,
                'bonus_points' => $bonus,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'is_active' => 1,
                'created_by' => $parentId,
            ], true);
            if ($missionId === false) {
                throw new MissionException('Misi tidak dapat disimpan.');
            }
            $assignments = new ChildWeeklyMissionModel($this->db);
            foreach ($childIds as $childId) {
                if ($assignments->insert([
                    'mission_id' => (int) $missionId,
                    'child_id' => $childId,
                    'progress' => 0,
                    'status' => 'active',
                    'completed_at' => null,
                ]) === false) {
                    throw new MissionException('Assignment misi tidak dapat disimpan.');
                }
            }
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        foreach ($childIds as $childId) {
            $this->checkForChild($childId, $local);
        }
        return (int) $missionId;
    }

    /** @return list<array<string, mixed>> */
    public function checkForChild(int $childId, DateTimeInterface $at): array
    {
        $this->assertActiveChild($childId);
        $local = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
        $today = $local->format('Y-m-d');
        $completed = [];

        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childId);
            $rows = $this->db->table('child_weekly_missions assignment')
                ->select('assignment.*, mission.family_id, mission.title, mission.mission_type, mission.target_value, mission.bonus_points, mission.start_date, mission.end_date, mission.is_active')
                ->join('weekly_missions mission', 'mission.id = assignment.mission_id')
                ->where('assignment.child_id', $childId)
                ->where('assignment.status', 'active')
                ->orderBy('assignment.id', 'ASC')->get()->getResultArray();

            $assignments = new ChildWeeklyMissionModel($this->db);
            foreach ($rows as $row) {
                $assignmentId = (int) $row['id'];
                if (! (bool) $row['is_active']) {
                    $assignments->update($assignmentId, ['status' => 'cancelled']);
                    continue;
                }
                if ($today > $row['end_date']) {
                    $assignments->update($assignmentId, ['status' => 'expired']);
                    continue;
                }
                if ($today < $row['start_date']) {
                    continue;
                }

                $actual = $this->actualProgress($childId, $row);
                $progress = min($actual, (int) $row['target_value']);
                $changes = ['progress' => $progress];
                if ($actual >= (int) $row['target_value']) {
                    $changes['status'] = 'completed';
                    $changes['completed_at'] = $local->format('Y-m-d H:i:s');
                }
                if (! $assignments->update($assignmentId, $changes)) {
                    throw new MissionException('Kemajuan misi tidak dapat dikemas kini.');
                }
                if (($changes['status'] ?? null) === 'completed') {
                    if ((int) $row['bonus_points'] > 0) {
                        (new PointService(db: $this->db))->awardWeeklyMissionPoints($childId, $assignmentId);
                    }
                    $completed[] = $row + ['progress' => $progress, 'completed_at' => $changes['completed_at']];
                }
            }
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        if ($completed !== []) {
            (new AchievementService($this->db))->checkAll($childId, $local);
        }
        return $completed;
    }

    /** @return array{family: array, missions: list<array<string, mixed>>, children: list<array<string, mixed>>} */
    public function listForParent(int $parentId, DateTimeInterface $at): array
    {
        $family = $this->parentFamily($parentId);
        $children = array_values(array_filter((new FamilyService())->children((int) $family['id']), static fn (array $child): bool => (bool) $child['is_active']));
        foreach ($children as $child) {
            $this->checkForChild((int) $child['id'], $at);
        }
        $missions = (new WeeklyMissionModel($this->db))->where('family_id', (int) $family['id'])
            ->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->findAll();
        foreach ($missions as &$mission) {
            $mission['assignments'] = $this->db->table('child_weekly_missions assignment')
                ->select('assignment.*, users.name AS child_name')->join('users', 'users.id = assignment.child_id')
                ->where('assignment.mission_id', $mission['id'])->orderBy('users.name', 'ASC')->get()->getResultArray();
        }
        unset($mission);
        return ['family' => $family, 'missions' => $missions, 'children' => $children];
    }

    /** @return list<array<string, mixed>> */
    public function listForChild(int $childId, DateTimeInterface $at): array
    {
        $this->assertActiveChild($childId);
        $this->checkForChild($childId, $at);
        $date = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()))->format('Y-m-d');
        $rows = $this->db->table('child_weekly_missions assignment')
            ->select('assignment.*, mission.title, mission.description, mission.mission_type, mission.target_value, mission.bonus_points, mission.start_date, mission.end_date')
            ->join('weekly_missions mission', 'mission.id = assignment.mission_id')
            ->where('assignment.child_id', $childId)->where('mission.start_date <=', $date)->where('mission.end_date >=', $date)
            ->whereIn('assignment.status', ['active', 'completed'])->orderBy('assignment.status', 'DESC')->orderBy('mission.end_date', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['percentage'] = min(100, (int) round(((int) $row['progress'] / max(1, (int) $row['target_value'])) * 100));
        }
        unset($row);
        return $rows;
    }

    private function actualProgress(int $childId, array $mission): int
    {
        if ($mission['mission_type'] === 'routine_completion_count') {
            return $this->db->table('task_completions')->where('child_user_id', $childId)->where('status', 'completed')
                ->where('completion_date >=', $mission['start_date'])->where('completion_date <=', $mission['end_date'])->countAllResults();
        }
        if ($mission['mission_type'] === 'perfect_day_count') {
            return $this->db->table('perfect_days')->where('child_id', $childId)
                ->where('perfect_date >=', $mission['start_date'])->where('perfect_date <=', $mission['end_date'])->countAllResults();
        }
        throw new MissionException('Jenis misi tidak disokong.');
    }

    private function parentFamily(int $parentId): array
    {
        $parent = (new UserModel($this->db))->find($parentId);
        $family = (new FamilyService())->currentFamilyForUser($parentId);
        if ($parent === null || ! $parent->is_active || $parent->roleEnum() !== UserRole::PARENT || $family === null) {
            throw new AuthorizationException('Ibu bapa tidak sah untuk keluarga ini.');
        }
        return $family;
    }

    private function assertActiveChild(int $childId): void
    {
        $child = (new UserModel($this->db))->find($childId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD
            || (new FamilyService())->currentFamilyForUser($childId) === null) {
            throw new AuthorizationException('Identiti Anak tidak sah untuk misi.');
        }
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(app_timezone()));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new MissionException('Tarikh misi tidak sah.');
        }
        return $date;
    }

    private function lockChild(int $childId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
            $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$childId]);
        }
    }
}
