<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Models\ChildWeeklyMissionModel;
use App\Models\PointTransactionModel;
use App\Models\UserModel;
use App\Models\WeeklyMissionModel;
use App\Services\ChildDeviceService;
use App\Services\FamilyService;
use App\Services\MissionService;
use App\Services\PointService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class WeeklyMissionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $seed = DemoSeeder::class;
    protected $refresh = true;

    protected function tearDown(): void
    {
        Services::trustedChildContext()->clear();
        service('superglobals')->setCookieArray([]);
        parent::tearDown();
    }

    private function ids(): array
    {
        $users = new UserModel();
        return [
            (int) $users->where('email', 'parent1@example.com')->first()->id,
            (int) $users->where('username', 'child-one-internal')->first()->id,
            (int) $users->where('username', 'child-two-internal')->first()->id,
        ];
    }

    private function task(int $parent, int $child, bool $approval = false, bool $perfectEligible = false): int
    {
        $routines = new RoutineService();
        $routine = $routines->create($parent, [
            'child_user_id' => $child,
            'name' => 'Mission routine ' . bin2hex(random_bytes(2)),
            'is_active' => 1,
            'requires_approval' => $approval ? 1 : 0,
            'perfect_day_eligible' => $perfectEligible ? 1 : 0,
        ], [1, 2, 3, 4, 5, 6, 7]);
        return $routines->createTask($parent, $routine, [
            'title' => 'Mission task', 'points' => 2, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    private function mission(int $parent, array $children, string $type, int $target, int $bonus, Time $at): int
    {
        return (new MissionService())->create($parent, [
            'title' => $type === 'perfect_day_count' ? 'Perfect Week' : 'Rajin Membaca',
            'description' => 'Misi ujian',
            'mission_type' => $type,
            'target_value' => $target,
            'bonus_points' => $bonus,
            'start_date' => $at->modify('monday this week')->format('Y-m-d'),
            'end_date' => $at->modify('sunday this week')->format('Y-m-d'),
        ], $children, $at);
    }

    public function testParentCreatesMissionForMultipleOwnChildrenAndUniqueAssignmentIsEnforced(): void
    {
        [$parent, $child, $sibling] = $this->ids();
        $missionId = $this->mission($parent, [$child, $sibling, $child], 'routine_completion_count', 4, 50, Time::now(app_timezone()));
        $this->assertSame(2, (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->countAllResults());

        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('child_weekly_missions')->insert([
            'mission_id' => $missionId, 'child_id' => $child, 'progress' => 0, 'status' => 'active',
        ]);
    }

    public function testParentCannotAssignMissionOutsideTheirChildren(): void
    {
        [$parent] = $this->ids();
        $this->db->table('families')->insert(['name' => 'Other Family']);
        $outsiderFamily = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Other Child', 'email' => 'other-child@example.com', 'username' => 'other-child',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT), 'role' => 'child', 'is_active' => 1,
        ]);
        $outsider = (int) $this->db->insertID();
        $this->db->table('family_users')->insert(['family_id' => $outsiderFamily, 'user_id' => $outsider]);

        $this->expectException(AuthorizationException::class);
        $this->mission($parent, [(int) $outsider], 'routine_completion_count', 1, 10, Time::now(app_timezone()));
    }

    public function testPendingAndRejectedDoNotProgressButApprovedCompletionDoes(): void
    {
        [$parent, $child, $sibling] = $this->ids();
        $at = Time::now(app_timezone());
        $missionId = $this->mission($parent, [$child, $sibling], 'routine_completion_count', 1, 15, $at);
        $completions = new TaskCompletionService();
        $pending = $completions->completeTask($child, $this->task($parent, $child, true), $at);
        $rejected = $completions->completeTask($sibling, $this->task($parent, $sibling, true), $at);
        $completions->rejectCompletion($parent, (int) $rejected['id'], 'Belum siap', $at);
        (new MissionService())->checkForChild($child, $at);
        (new MissionService())->checkForChild($sibling, $at);
        $this->assertSame(0, (int) (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->where('child_id', $child)->first()['progress']);
        $this->assertSame(0, (int) (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->where('child_id', $sibling)->first()['progress']);

        $completions->approveCompletion($parent, (int) $pending['id'], $at);
        $assignment = (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->where('child_id', $child)->first();
        $this->assertSame('completed', $assignment['status']);
        $this->assertSame(1, (int) $assignment['progress']);
    }

    public function testRoutineAndPerfectDayMissionsAwardBonusOnlyOnce(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $routineMission = $this->mission($parent, [$child], 'routine_completion_count', 1, 7, $at);
        $perfectMission = $this->mission($parent, [$child], 'perfect_day_count', 1, 11, $at);
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, false, true), $at);

        $service = new MissionService();
        $service->checkForChild($child, $at);
        $service->checkForChild($child, $at);
        $assignments = (new ChildWeeklyMissionModel())->whereIn('mission_id', [$routineMission, $perfectMission])->findAll();
        $this->assertCount(2, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertSame('completed', $assignment['status']);
            (new PointService())->awardWeeklyMissionPoints($child, (int) $assignment['id']);
            $this->assertSame(1, (new PointTransactionModel())->where('reference_type', 'weekly_mission')
                ->where('reference_id', $assignment['id'])->countAllResults());
        }
        $this->assertSame(30, (new PointService())->getBalance($child)); // 2 task + 10 Perfect Day + 7 + 11 mission bonuses.
    }

    public function testExpiredMissionIgnoresLateApproval(): void
    {
        [$parent, $child] = $this->ids();
        $now = Time::now(app_timezone());
        $past = $now->subDays(14);
        $missionId = $this->mission($parent, [$child], 'routine_completion_count', 1, 25, $past);
        $taskId = $this->task($parent, $child, true);
        $task = $this->db->table('routine_tasks')->where('id', $taskId)->get()->getRowArray();
        $this->db->table('routines')->where('id', $task['routine_id'])->update(['created_at' => $past->format('Y-m-d H:i:s')]);
        $pending = (new TaskCompletionService())->completeTask($child, $taskId, $past);

        (new MissionService())->checkForChild($child, $now);
        (new TaskCompletionService())->approveCompletion($parent, (int) $pending['id'], $now);
        $assignment = (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->where('child_id', $child)->first();
        $this->assertSame('expired', $assignment['status']);
        $this->assertSame(0, (int) $assignment['progress']);
        $this->assertSame(0, (new PointTransactionModel())->where('reference_type', 'weekly_mission')->countAllResults());
    }

    public function testParentAndChildPagesShowServerCalculatedProgress(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $missionId = $this->mission($parent, [$child], 'routine_completion_count', 4, 50, $at);
        $family = (new FamilyService())->currentFamilyForUser($parent);
        $parentPage = $this->withSession([
            'user_id' => $parent, 'user_role' => 'parent', 'family_id' => (int) $family['id'], 'auth_expires_at' => time() + 3600,
        ])->get('/missions');
        $parentPage->assertOK();
        $parentPage->assertSee('Rajin Membaca');
        $parentPage->assertSee('Tambah Misi');

        $device = (new ChildDeviceService())->provision($parent, $child);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $childPage = $this->get('/child/missions?progress=999&status=completed');
        $childPage->assertOK();
        $childPage->assertSee('0 / 4');
        $childPage->assertSee('Bonus +50 mata');
        $this->assertSame(0, (int) (new ChildWeeklyMissionModel())->where('mission_id', $missionId)->first()['progress']);
    }
}
