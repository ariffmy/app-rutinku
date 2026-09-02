<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Exceptions\PointException;
use App\Models\AuditLogModel;
use App\Models\FamilyModel;
use App\Models\PointTransactionModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\PointService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use RuntimeException;

/**
 * @internal
 */
final class PointPhaseFiveTest extends CIUnitTestCase
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

    public function testCompletionAwardsPointsExactlyOnceAndUsesSnapshot(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 12);
        $completionService = new TaskCompletionService();
        $completion = $completionService->completeTask($childId, $taskId, $monday);

        try {
            $completionService->completeTask($childId, $taskId, $monday);
            $this->fail('Duplicate completion must fail.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }

        $transaction = (new PointTransactionModel())->where('type', 'task')->first();
        $this->assertNotNull($transaction);
        $this->assertSame(12, (int) $transaction['points']);
        $this->assertSame((int) $completion['id'], (int) $transaction['reference_id']);
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'task')->countAllResults());
        $this->assertSame(12, (new PointService())->getBalance($childId));
    }

    public function testUndoAppendsOneReversalAndRetainsLedgerHistory(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 8);
        $completionService = new TaskCompletionService();
        $completionService->completeTask($childId, $taskId, $monday);
        $completionService->undoTask($childId, $taskId, $monday);

        $transactions = (new PointTransactionModel())->where('child_user_id', $childId)->orderBy('id', 'ASC')->findAll();
        $this->assertSame(['task', 'reversal'], array_column($transactions, 'type'));
        $this->assertSame([8, -8], array_map('intval', array_column($transactions, 'points')));
        $this->assertSame((int) $transactions[0]['id'], (int) $transactions[1]['reference_id']);
        $this->assertSame(0, (new PointService())->getBalance($childId));
        $this->assertSame(0, (new TaskCompletionModel())->where('routine_task_id', $taskId)->countAllResults());

        try {
            $completionService->undoTask($childId, $taskId, $monday);
            $this->fail('Duplicate undo must fail.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'reversal')->countAllResults());
    }

    public function testCompletionRollsBackWhenPointAwardFails(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 4);
        $failingPoints = new class extends PointService {
            public function awardTaskPoints(int $childUserId, int $completionId): array
            {
                throw new RuntimeException('Simulated point failure.');
            }
        };
        $service = new TaskCompletionService(points: $failingPoints, db: $this->db);

        try {
            $service->completeTask($childId, $taskId, $monday);
            $this->fail('Completion must roll back when its ledger insert fails.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (new TaskCompletionModel())->countAllResults());
        $this->assertSame(0, (new PointTransactionModel())->countAllResults());
    }

    public function testUndoRollsBackWhenPointReversalFails(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 5);
        (new TaskCompletionService())->completeTask($childId, $taskId, $monday);
        $failingPoints = new class extends PointService {
            public function reverseTaskPoints(
                int $childUserId,
                int $completionId,
                DateTimeInterface $at,
                ?int $createdByUserId = null,
            ): array {
                throw new RuntimeException('Simulated reversal failure.');
            }
        };
        $service = new TaskCompletionService(points: $failingPoints, db: $this->db);

        try {
            $service->undoTask($childId, $taskId, $monday);
            $this->fail('Undo must roll back when reversal fails.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, (new TaskCompletionModel())->where('routine_task_id', $taskId)->countAllResults());
        $this->assertSame(0, (new PointTransactionModel())->where('type', 'reversal')->countAllResults());
        $this->assertSame(5, (new PointService())->getBalance($childId));
    }

    public function testManualAddAndDeductCreateLedgerAndAuditRecords(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();
        $monday = $this->monday();
        $points->manualAdjustment($parentId, $childId, 20, 'Bonus membantu keluarga', $monday);
        $points->manualAdjustment($parentId, $childId, -7, 'Pembetulan rekod', $monday);

        $this->assertSame(13, $points->getBalance($childId));
        $this->assertSame(2, (new PointTransactionModel())->where('type', 'adjustment')->countAllResults());
        $audits = (new AuditLogModel())->where('action', 'points.manual_adjustment')->findAll();
        $this->assertCount(2, $audits);
        $this->assertSame($parentId, (int) $audits[0]['user_id']);
        $this->assertSame($childId, (int) $audits[0]['target_user_id']);
        $this->assertStringContainsString('"reason":"Bonus membantu keluarga"', (string) $audits[0]['new_values']);
    }

    public function testManualAdjustmentRequiresNonZeroAmountAndReason(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $points = new PointService();

        try {
            $points->manualAdjustment($parentId, $childId, 0, 'Zero', $this->monday());
            $this->fail('Zero adjustment must fail.');
        } catch (PointException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(PointException::class);
        $points->manualAdjustment($parentId, $childId, 5, '   ', $this->monday());
    }

    public function testOutsideFamilyCannotReadOrAdjustChildPoints(): void
    {
        [, $childId] = $this->demoIds();
        $outsideParent = $this->createOutsideParent();
        $points = new PointService();

        try {
            $points->getParentAccount($outsideParent, $childId);
            $this->fail('Outside Parent must not read this point account.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AuthorizationException::class);
        $points->manualAdjustment($outsideParent, $childId, 100, 'Tamper', $this->monday());
    }

    public function testEarnedPointsExcludeAdjustmentRewardAndReversalTypes(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 10);
        $completions = new TaskCompletionService();
        $points = new PointService();
        $completions->completeTask($childId, $taskId, $monday);
        $points->manualAdjustment($parentId, $childId, 99, 'Tidak masuk earned points', $monday);

        $this->assertSame(10, $points->getEarnedPointsBetween($childId, $monday, $monday));
        $completions->undoTask($childId, $taskId, $monday);
        $this->assertSame(10, $points->getEarnedPointsBetween($childId, $monday, $monday));
        $this->assertSame(99, $points->getBalance($childId));
    }

    public function testPointLedgerModelRejectsUpdateAndDelete(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $transaction = (new PointService())->manualAdjustment($parentId, $childId, 2, 'Immutable', $this->monday());

        try {
            (new PointTransactionModel())->update((int) $transaction['id'], ['points' => 999]);
            $this->fail('Ledger update must fail.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        (new PointTransactionModel())->delete((int) $transaction['id']);
    }

    public function testParentAdjustmentRouteUsesCsrfAndChildSeesOnlyOwnHistory(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $security = service('security');
        $tokenName = $security->getTokenName();
        $csrfHash = $security->getHash();
        $session = [
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
            $tokenName => $csrfHash,
        ];

        $response = $this->withSession($session)->post('/points/adjustments', [
            $tokenName => $csrfHash,
            'child_user_id' => $childOneId,
            'points' => '15',
            'reason' => 'Route adjustment visible',
        ]);
        $response->assertRedirectTo('/points?child=' . $childOneId);
        (new PointService())->manualAdjustment($parentId, $childTwoId, 50, 'Sibling secret adjustment', $this->monday());

        $device = (new ChildDeviceService())->provision($parentId, $childOneId, 'Points phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $progress = $this->get('/child/progress');
        $progress->assertOK();
        $progress->assertSee('Route adjustment visible');
        $progress->assertDontSee('Sibling secret adjustment');
        $progress->assertSee('15');
    }

    public function testPhaseFiveRoutesRemainAvailableAfterRewardPhase(): void
    {
        $routes = service('routes')->loadRoutes();
        $this->assertArrayHasKey('points', $routes->getRoutes('GET'));
        $this->assertArrayHasKey('points/adjustments', $routes->getRoutes('POST'));
        $this->assertTrue($this->db->tableExists('rewards'));
        $this->assertTrue(method_exists(PointService::class, 'redeemReward'));
    }

    private function scheduledTask(int $parentId, int $childId, int $points): array
    {
        $routines = new RoutineService();
        $routineId = $routines->create($parentId, [
            'child_user_id' => $childId,
            'name' => 'Phase 5 Routine ' . bin2hex(random_bytes(3)),
            'description' => null,
            'type' => 'Morning',
            'start_time' => '07:00',
            'sort_order' => 0,
            'is_active' => 1,
        ], [1]);
        $taskId = $routines->createTask($parentId, $routineId, [
            'title' => 'Phase 5 Task',
            'description' => null,
            'task_time' => '07:15',
            'points' => $points,
            'is_required' => 1,
            'sort_order' => 0,
            'is_active' => 1,
        ]);

        return [$taskId, $this->monday()];
    }

    private function monday(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-07 08:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));
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
        $this->db->table('families')->insert(['name' => 'Outside Point Family', 'created_at' => $now, 'updated_at' => $now]);
        $familyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Point Parent',
            'email' => 'outside-points@example.com',
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
