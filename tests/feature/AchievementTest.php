<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\AchievementModel;
use App\Models\ChildAchievementModel;
use App\Models\PerfectDayModel;
use App\Models\PointTransactionModel;
use App\Models\UserModel;
use App\Services\AchievementService;
use App\Services\ChildDeviceService;
use App\Services\PointService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class AchievementTest extends CIUnitTestCase
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
        return [(int) $users->where('email', 'parent1@example.com')->first()->id,
            (int) $users->where('username', 'child-one-internal')->first()->id,
            (int) $users->where('username', 'child-two-internal')->first()->id];
    }

    private function task(int $parent, int $child, bool $approval = false, int $points = 3, bool $perfectEligible = false): int
    {
        $routines = new RoutineService();
        $routine = $routines->create($parent, [
            'child_user_id' => $child, 'name' => 'Achievement routine ' . bin2hex(random_bytes(2)),
            'is_active' => 1, 'requires_approval' => $approval ? 1 : 0,
            'perfect_day_eligible' => $perfectEligible ? 1 : 0,
        ], [1, 2, 3, 4, 5, 6, 7]);
        return $routines->createTask($parent, $routine, [
            'title' => 'Achievement task', 'points' => $points, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    private function achievement(string $code): array
    {
        return (new AchievementModel())->where('code', $code)->first();
    }

    public function testDefaultCatalogueIsSeededAndChildPageDistinguishesLockedAndUnlocked(): void
    {
        [$parent, $child] = $this->ids();
        $this->assertSame(6, (new AchievementModel())->countAllResults());
        $device = (new ChildDeviceService())->provision($parent, $child);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $page = $this->get('/child/achievements');
        $page->assertOK();
        $page->assertSee('Pencapaian');
        $page->assertSee('First Step');
        $page->assertSee('Dikunci');

        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child), Time::now(app_timezone()));
        $page = $this->get('/child/achievements');
        $page->assertSee('Dibuka');
        $page->assertSee('First Step');
        $this->assertSame(1, (new ChildAchievementModel())->where('child_id', $child)->countAllResults());
    }

    public function testRepeatedChecksAndDatabaseConstraintPreventDuplicateAwards(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child), $at);
        $service = new AchievementService();
        $service->checkAll($child, $at);
        $service->checkAll($child, $at);
        $first = (new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $this->achievement('FIRST_TASK')['id'])->first();
        $this->assertNotNull($first);
        $this->assertSame(1, (new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $first['achievement_id'])->countAllResults());

        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('child_achievements')->insert([
            'child_id' => $child, 'achievement_id' => $first['achievement_id'],
            'earned_at' => date('Y-m-d H:i:s'), 'points_awarded' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testPendingAndRejectedDoNotUnlockButApprovalDoes(): void
    {
        [$parent, $child, $sibling] = $this->ids();
        $at = Time::now(app_timezone());
        $completions = new TaskCompletionService();
        $pending = $completions->completeTask($child, $this->task($parent, $child, true), $at);
        (new AchievementService())->checkAll($child, $at);
        $this->assertNull((new ChildAchievementModel())->where('child_id', $child)->first());

        $rejected = $completions->completeTask($sibling, $this->task($parent, $sibling, true), $at);
        $completions->rejectCompletion($parent, (int) $rejected['id'], 'Belum siap', $at);
        (new AchievementService())->checkAll($sibling, $at);
        $this->assertNull((new ChildAchievementModel())->where('child_id', $sibling)->first());

        $completions->approveCompletion($parent, (int) $pending['id'], $at);
        $earned = (new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $this->achievement('FIRST_TASK')['id'])->first();
        $this->assertNotNull($earned);
    }

    public function testDisabledAchievementIsNeverAwarded(): void
    {
        [$parent, $child] = $this->ids();
        $first = $this->achievement('FIRST_TASK');
        (new AchievementModel())->update($first['id'], ['is_active' => 0]);
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child), Time::now(app_timezone()));
        $this->assertSame(0, (new ChildAchievementModel())->where('achievement_id', $first['id'])->countAllResults());
    }

    public function testAchievementBonusUsesLedgerOnceAndIsNotCountedTowardCollector(): void
    {
        [$parent, $child] = $this->ids();
        $first = $this->achievement('FIRST_TASK');
        (new AchievementModel())->update($first['id'], ['points_reward' => 7]);
        $at = Time::now(app_timezone());
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, false, 3), $at);
        $record = (new ChildAchievementModel())->where('child_id', $child)->where('achievement_id', $first['id'])->first();
        $points = new PointService();
        $points->awardAchievementPoints($child, (int) $record['id']);
        (new AchievementService())->checkAll($child, $at);
        $this->assertSame(1, (new PointTransactionModel())->where('reference_type', 'achievement')
            ->where('reference_id', $record['id'])->countAllResults());
        $this->assertSame(10, $points->getBalance($child));
        $this->assertNull((new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $this->achievement('1000_POINTS_EARNED')['id'])->first());
    }

    public function testPerfectDayAndConfiguredThresholdsUseDatabaseActivity(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        // A real eligible completion creates the Perfect Day record before achievements are checked.
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, false, 2, true), $at);
        $perfect = $this->achievement('FIRST_PERFECT_DAY');
        $this->assertNotNull((new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $perfect['id'])->first());

        $collector = $this->achievement('1000_POINTS_EARNED');
        (new PointService())->manualAdjustment($parent, $child, 2000, 'Bukan mata earned', $at, 7001);
        (new AchievementService())->checkAll($child, $at);
        $this->assertNull((new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $collector['id'])->first());

        (new AchievementModel())->update($collector['id'], ['condition_value' => 12]);
        (new AchievementService())->checkAll($child, $at);
        $this->assertNotNull((new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $collector['id'])->first());
    }

    public function testSevenDayStreakUsesCompletedRoutineHistory(): void
    {
        [$parent, $child] = $this->ids();
        $taskId = $this->task($parent, $child);
        $task = $this->db->table('routine_tasks')->where('id', $taskId)->get()->getRowArray();
        $today = Time::now(app_timezone())->setTime(12, 0);
        $firstDay = $today->subDays(6);
        $this->db->table('routines')->where('id', $task['routine_id'])->update([
            'created_at' => $firstDay->format('Y-m-d 00:00:00'),
        ]);

        $completions = new TaskCompletionService();
        for ($day = 0; $day < 7; ++$day) {
            $completions->completeTask($child, $taskId, $firstDay->addDays($day));
        }

        $streak = $this->achievement('7_DAY_STREAK');
        $this->assertNotNull((new ChildAchievementModel())->where('child_id', $child)
            ->where('achievement_id', $streak['id'])->first());
    }

    public function testBrowserCannotSupplyAchievementState(): void
    {
        [$parent, $child] = $this->ids();
        $device = (new ChildDeviceService())->provision($parent, $child);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $this->get('/child/achievements?code=FIRST_TASK&unlocked=1')->assertOK();
        $this->assertSame(0, (new ChildAchievementModel())->where('child_id', $child)->countAllResults());
        service('superglobals')->setCookieArray([]);
        $this->get('/child/achievements')->assertRedirectTo('/login');
    }
}
