<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Exceptions\RewardException;
use App\Models\AuditLogModel;
use App\Models\FamilyModel;
use App\Models\PointTransactionModel;
use App\Models\RewardModel;
use App\Models\RewardRedemptionModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\PointService;
use App\Services\RankingService;
use App\Services\RewardService;
use App\Services\RoutineService;
use App\Services\StreakService;
use App\Services\TaskCompletionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @internal
 */
final class PhaseSixToEightTest extends CIUnitTestCase
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

    public function testPerfectDayRequiresEveryRequiredTaskButIgnoresOptionalTask(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $today = $this->today();
        $routine = $this->createRoutine($parentId, $childId, [(int) $today->format('N')]);
        $required = $this->createTask($parentId, $routine, 'Required', 4, true);
        $this->createTask($parentId, $routine, 'Optional', 9, false);
        (new TaskCompletionService())->completeTask($childId, $required, $today);

        $day = (new StreakService())->evaluateDay($childId, $today);

        $this->assertTrue($day['qualifies']);
        $this->assertTrue($day['is_perfect']);
        $this->assertSame(1, $day['required_total']);
        $this->assertSame(1, $day['required_completed']);
    }

    public function testNeutralDayDoesNotIncrementOrBreakCurrentStreakAndTodayIsInProgress(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $today = $this->today();
        $neutralDay = $today->modify('-1 day');
        $perfectDay = $today->modify('-2 days');

        $oldRequiredRoutine = $this->createRoutine($parentId, $childId, [(int) $perfectDay->format('N')]);
        $oldRequired = $this->createTask($parentId, $oldRequiredRoutine, 'Old required', 3, true);
        $neutralRoutine = $this->createRoutine($parentId, $childId, [(int) $neutralDay->format('N')]);
        $this->createTask($parentId, $neutralRoutine, 'Neutral optional', 1, false);
        $todayRoutine = $this->createRoutine($parentId, $childId, [(int) $today->format('N')]);
        $this->createTask($parentId, $todayRoutine, 'Today still in progress', 2, true);
        $createdBefore = $perfectDay->modify('-1 day')->format('Y-m-d H:i:s');
        $this->db->table('routines')->update(['created_at' => $createdBefore]);
        (new TaskCompletionService())->completeTask($childId, $oldRequired, $perfectDay);

        $streaks = new StreakService();
        $this->assertFalse($streaks->evaluateDay($childId, $neutralDay)['qualifies']);
        $this->assertSame(1, $streaks->currentStreak($childId, $today));
    }

    public function testFailedPastQualifyingDayBreaksStreak(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $today = $this->today();
        $yesterday = $today->modify('-1 day');
        $routine = $this->createRoutine($parentId, $childId, [(int) $yesterday->format('N')]);
        $this->createTask($parentId, $routine, 'Missed yesterday', 2, true);
        $this->db->table('routines')->update(['created_at' => $yesterday->modify('-1 day')->format('Y-m-d H:i:s')]);

        $this->assertSame(0, (new StreakService())->currentStreak($childId, $today));
    }

    public function testRewardRequestIsPendingWithoutDeductingPointsAndUsesSnapshot(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();
        $points->manualAdjustment($parentId, $childId, 30, 'Reward test funding', $this->today());
        $rewards = new RewardService();
        $rewardId = $rewards->create($parentId, $this->rewardData('Movie Night', 20));

        $redemption = $rewards->requestRedemption($childId, $rewardId, $this->today());
        $changed = $this->rewardData('Movie Night', 25);
        $rewards->update($parentId, $rewardId, $changed);

        $this->assertSame('pending', $redemption['status']);
        $this->assertSame(20, (int) $redemption['points_used']);
        $this->assertSame(30, $points->getBalance($childId));
        $this->assertSame(0, (new PointTransactionModel())->where('type', 'reward')->countAllResults());

        $this->expectException(RewardException::class);
        $rewards->requestRedemption($childId, $rewardId, $this->today());
    }

    public function testRewardApprovalRechecksBalanceDeductsOnceAndAudits(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();
        $points->manualAdjustment($parentId, $childId, 40, 'Fund reward', $this->today());
        $rewards = new RewardService();
        $rewardId = $rewards->create($parentId, $this->rewardData('Family Outing', 25));
        $redemption = $rewards->requestRedemption($childId, $rewardId, $this->today());

        $approved = $rewards->approve($parentId, (int) $redemption['id'], $this->today());

        $this->assertSame('approved', $approved['status']);
        $this->assertSame(15, $points->getBalance($childId));
        $rewardTransaction = (new PointTransactionModel())->where('type', 'reward')->first();
        $this->assertSame(-25, (int) $rewardTransaction['points']);
        $this->assertSame((int) $redemption['id'], (int) $rewardTransaction['reference_id']);
        $this->assertSame(1, (new AuditLogModel())->where('action', 'reward.approved')->countAllResults());

        try {
            $rewards->approve($parentId, (int) $redemption['id'], $this->today());
            $this->fail('A redemption cannot be approved twice.');
        } catch (RewardException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'reward')->countAllResults());
    }

    public function testRewardRejectionDoesNotDeductAndIsAudited(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();
        $points->manualAdjustment($parentId, $childId, 20, 'Fund rejection', $this->today());
        $rewards = new RewardService();
        $rewardId = $rewards->create($parentId, $this->rewardData('Rejected Reward', 10));
        $redemption = $rewards->requestRedemption($childId, $rewardId, $this->today());

        $rejected = $rewards->reject($parentId, (int) $redemption['id'], $this->today());

        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame(20, $points->getBalance($childId));
        $this->assertSame(0, (new PointTransactionModel())->where('type', 'reward')->countAllResults());
        $this->assertSame(1, (new AuditLogModel())->where('action', 'reward.rejected')->countAllResults());
    }

    public function testApprovalFailsAtomicallyWhenBalanceChangedAfterRequest(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();
        $points->manualAdjustment($parentId, $childId, 20, 'Temporary fund', $this->today());
        $rewards = new RewardService();
        $rewardId = $rewards->create($parentId, $this->rewardData('Needs Recheck', 20));
        $redemption = $rewards->requestRedemption($childId, $rewardId, $this->today());
        $points->manualAdjustment($parentId, $childId, -15, 'Balance changed', $this->today());

        try {
            $rewards->approve($parentId, (int) $redemption['id'], $this->today());
            $this->fail('Approval must recheck balance.');
        } catch (RewardException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('pending', (new RewardRedemptionModel())->find((int) $redemption['id'])['status']);
        $this->assertSame(0, (new PointTransactionModel())->where('type', 'reward')->countAllResults());
    }

    public function testOutsideParentCannotManageRewardOrRedemption(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $outside = $this->createOutsideParent();
        (new PointService())->manualAdjustment($parentId, $childId, 20, 'Fund', $this->today());
        $rewards = new RewardService();
        $rewardId = $rewards->create($parentId, $this->rewardData('Private Reward', 5));
        $redemption = $rewards->requestRedemption($childId, $rewardId, $this->today());

        try {
            $rewards->getForParent($outside, $rewardId);
            $this->fail('Outside Parent must not read reward.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AuthorizationException::class);
        $rewards->approve($outside, (int) $redemption['id'], $this->today());
    }

    public function testTrustedChildRewardRouteIgnoresForgedSiblingIdentity(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        (new PointService())->manualAdjustment($parentId, $childOneId, 20, 'Child one funding', $this->today());
        $rewardId = (new RewardService())->create($parentId, $this->rewardData('Trusted Reward', 5));
        $device = (new ChildDeviceService())->provision($parentId, $childOneId, 'Reward phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $security = service('security');
        $tokenName = $security->getTokenName();
        $hash = $security->getHash();

        $response = $this->withSession([$tokenName => $hash])->post('/child/rewards/' . $rewardId . '/redeem', [
            $tokenName => $hash,
            'child_user_id' => $childTwoId,
        ]);

        $response->assertRedirectTo('/child/rewards');
        $redemption = (new RewardRedemptionModel())->first();
        $this->assertSame($childOneId, (int) $redemption['child_user_id']);
        $this->assertNotSame($childTwoId, (int) $redemption['child_user_id']);
    }

    public function testRankingUsesUncancelledAwardsAndExcludesRewardSpendingAndAdjustments(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $today = $this->today();
        $weekday = (int) $today->format('N');
        $routineOne = $this->createRoutine($parentId, $childOneId, [$weekday]);
        $taskOne = $this->createTask($parentId, $routineOne, 'Ten earned', 10, true);
        $routineTwo = $this->createRoutine($parentId, $childTwoId, [$weekday]);
        $taskTwo = $this->createTask($parentId, $routineTwo, 'Five earned', 5, true);
        $completions = new TaskCompletionService();
        $completions->completeTask($childOneId, $taskOne, $today);
        $completions->completeTask($childTwoId, $taskTwo, $today);
        (new PointService())->manualAdjustment($parentId, $childTwoId, 100, 'Excluded adjustment', $today);
        $rewardService = new RewardService();
        $rewardId = $rewardService->create($parentId, $this->rewardData('Excluded reward spend', 20));
        $redemption = $rewardService->requestRedemption($childTwoId, $rewardId, $today);
        $rewardService->approve($parentId, (int) $redemption['id'], $today);

        $ranking = (new RankingService())->daily($parentId, $today);
        $this->assertSame($childOneId, $ranking['rows'][0]['child_user_id']);
        $this->assertSame(10, $ranking['rows'][0]['earned_points']);
        $this->assertSame(5, $ranking['rows'][1]['earned_points']);
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'reward')->countAllResults());
        $this->assertGreaterThan((new PointService())->getBalance($childOneId), (new PointService())->getBalance($childTwoId));

        $completions->undoTask($childOneId, $taskOne, $today);
        $afterUndo = (new RankingService())->daily($parentId, $today);
        $childOneRow = array_values(array_filter($afterUndo['rows'], static fn (array $row): bool => $row['child_user_id'] === $childOneId))[0];
        $this->assertSame(0, $childOneRow['earned_points']);
        $this->assertSame($childTwoId, $afterUndo['rows'][0]['child_user_id']);
        $this->assertSame(5, $afterUndo['rows'][0]['earned_points']);
    }

    public function testRankingTieBreakersAndPeriodBoundariesAreDeterministic(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $today = $this->today();
        $weekday = (int) $today->format('N');
        $routineOne = $this->createRoutine($parentId, $childOneId, [$weekday]);
        $taskOne = $this->createTask($parentId, $routineOne, 'Child one done', 5, true);
        $this->createTask($parentId, $routineOne, 'Child one pending', 0, false);
        $routineTwo = $this->createRoutine($parentId, $childTwoId, [$weekday]);
        $taskTwo = $this->createTask($parentId, $routineTwo, 'Child two done', 5, true);
        (new TaskCompletionService())->completeTask($childOneId, $taskOne, $today);
        (new TaskCompletionService())->completeTask($childTwoId, $taskTwo, $today);
        $rankings = new RankingService();

        $daily = $rankings->daily($parentId, $today);
        $this->assertSame($childTwoId, $daily['rows'][0]['child_user_id']);
        $this->assertSame(100, $daily['rows'][0]['completion_percentage']);
        $this->assertSame(50, $daily['rows'][1]['completion_percentage']);

        $weekly = $rankings->weekly($parentId, new DateTimeImmutable('2026-09-02', new DateTimeZone(app_timezone())));
        $this->assertSame('2026-08-31', $weekly['start_date']);
        $this->assertSame('2026-09-06', $weekly['end_date']);
        $monthly = $rankings->monthly($parentId, new DateTimeImmutable('2026-09-15', new DateTimeZone(app_timezone())));
        $this->assertSame('2026-09-01', $monthly['start_date']);
        $this->assertSame('2026-09-30', $monthly['end_date']);
    }

    public function testRankingEligibilityAndParentOnlyRouteAreEnforced(): void
    {
        [$parentId, , $childTwoId] = $this->demoIds(true);
        $this->db->table('child_profiles')->where('user_id', $childTwoId)->update(['is_ranking_eligible' => 0]);
        $ranking = (new RankingService())->daily($parentId, $this->today());
        $this->assertNotContains($childTwoId, array_column($ranking['rows'], 'child_user_id'));

        $device = (new ChildDeviceService())->provision($parentId, $childTwoId, 'No ranking phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $this->get('/ranking')->assertRedirectTo('/login');
        $routes = service('routes')->loadRoutes();
        $this->assertArrayNotHasKey('child/ranking', $routes->getRoutes('GET'));
    }

    public function testParentRewardAndRankingViewsRenderEscapedContent(): void
    {
        [$parentId] = $this->demoIds();
        $rewardId = (new RewardService())->create($parentId, $this->rewardData('<script>alert(1)</script>', 5));
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $session = [
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
        ];

        $rewardPage = $this->withSession($session)->get('/rewards');
        $rewardPage->assertOK();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rewardPage->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rewardPage->response()->getBody());
        $rankingPage = $this->withSession($session)->get('/ranking?period=daily&date=' . $this->today()->format('Y-m-d'));
        $rankingPage->assertOK();
        $rankingPage->assertSee('Kedudukan untuk ibu bapa sahaja');
        $this->assertNotNull((new RewardModel())->find($rewardId));
    }

    public function testLaterPhaseRoutesAndPwaAreNowAvailableWithoutChildRanking(): void
    {
        $routes = service('routes')->loadRoutes();
        $this->assertArrayHasKey('reports', $routes->getRoutes('GET'));
        $this->assertArrayHasKey('child/profile', $routes->getRoutes('GET'));
        $this->assertArrayNotHasKey('child/ranking', $routes->getRoutes('GET'));
        $this->assertFileExists(ROOTPATH . 'public/manifest.webmanifest');
        $this->assertFileExists(ROOTPATH . 'public/service-worker.js');
    }

    private function createRoutine(int $parentId, int $childId, array $days): int
    {
        return (new RoutineService())->create($parentId, [
            'child_user_id' => $childId,
            'name' => 'Phase 6-8 Routine ' . bin2hex(random_bytes(3)),
            'description' => null,
            'type' => 'Daily',
            'start_time' => '07:00',
            'sort_order' => 0,
            'is_active' => 1,
        ], $days);
    }

    private function createTask(int $parentId, int $routineId, string $title, int $points, bool $required): int
    {
        return (new RoutineService())->createTask($parentId, $routineId, [
            'title' => $title,
            'description' => null,
            'task_time' => '07:15',
            'points' => $points,
            'is_required' => $required ? 1 : 0,
            'sort_order' => 0,
            'is_active' => 1,
        ]);
    }

    private function rewardData(string $title, int $points): array
    {
        return [
            'title' => $title,
            'description' => 'Family reward',
            'points_required' => $points,
            'image' => null,
            'is_active' => 1,
        ];
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
    }

    private function demoIds(bool $extended = false): array
    {
        $users = new UserModel();
        $parent = $users->where('email', 'parent1@example.com')->first();
        $childOne = $users->where('username', 'child-one-internal')->first();
        if (! $extended) {
            return [(int) $parent->id, (int) $childOne->id];
        }
        $childTwo = $users->where('username', 'child-two-internal')->first();

        return [(int) $parent->id, (int) $childOne->id, (int) $childTwo->id];
    }

    private function createOutsideParent(): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('families')->insert(['name' => 'Outside Reward Family', 'created_at' => $now, 'updated_at' => $now]);
        $familyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Reward Parent',
            'email' => 'outside-reward@example.com',
            'username' => null,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'parent',
            'is_active' => true,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentId = (int) $this->db->insertID();
        $this->db->table('family_users')->insert(['family_id' => $familyId, 'user_id' => $parentId, 'created_at' => $now]);

        return $parentId;
    }
}
