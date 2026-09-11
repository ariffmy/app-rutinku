<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\PointException;
use App\Models\PerfectDayModel;
use App\Models\FamilyModel;
use App\Models\PointTransactionModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use App\Services\PerfectDayService;
use App\Services\PointService;
use App\Services\RewardService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PointIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $seed = DemoSeeder::class;
    protected $refresh = true;

    private function ids(): array
    {
        $users = new UserModel();
        return [(int) $users->where('email', 'parent1@example.com')->first()->id,
            (int) $users->where('username', 'child-one-internal')->first()->id];
    }

    private function task(int $parent, int $child, bool $approval = false, int $points = 5): int
    {
        $routines = new RoutineService();
        $routine = $routines->create($parent, [
            'child_user_id' => $child, 'name' => 'Integrity routine', 'is_active' => 1,
            'requires_approval' => $approval ? 1 : 0,
        ], [1, 2, 3, 4, 5, 6, 7]);
        return $routines->createTask($parent, $routine, [
            'title' => 'Integrity task', 'points' => $points, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    public function testPendingAndRejectedCompletionsCannotReceivePointsDirectly(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $completion = (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, true), $at);
        $points = new PointService();
        foreach (['pending', 'rejected'] as $status) {
            if ($status === 'rejected') {
                (new TaskCompletionService())->rejectCompletion($parent, (int) $completion['id'], 'No', $at);
            }
            try {
                $points->awardTaskPoints($child, (int) $completion['id']);
                $this->fail("{$status} completion received points.");
            } catch (PointException) {
                $this->assertSame(0, (new PointTransactionModel())->where('type', 'task')->countAllResults());
            }
        }
    }

    public function testApprovalAndPerfectDayAwardEachSourceOnlyOnce(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $completion = (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, true, 7), $at);
        $service = new TaskCompletionService();
        $service->approveCompletion($parent, (int) $completion['id'], $at);
        try {
            $service->approveCompletion($parent, (int) $completion['id'], $at);
            $this->fail('Duplicate approval succeeded.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $day = (new PerfectDayModel())->where('child_id', $child)->first();
        $this->assertNotNull($day);
        (new PerfectDayService())->awardIfQualified($child, $at);
        (new PointService())->awardPerfectDayPoints($child, (int) $day['id']);
        $ledger = new PointTransactionModel();
        $this->assertSame(1, $ledger->where('type', 'task')->countAllResults());
        $this->assertSame(1, $ledger->where('type', 'bonus')->countAllResults());
        $this->assertSame(17, (new PointService())->getBalance($child));
    }

    public function testApprovalStatusRollsBackWhenLedgerWriteFails(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $completion = (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, true), $at);
        $failingPoints = new class extends PointService {
            public function awardTaskPoints(int $childUserId, int $completionId): array
            {
                throw new \RuntimeException('Simulated ledger failure.');
            }
        };
        try {
            (new TaskCompletionService(points: $failingPoints, db: $this->db))
                ->approveCompletion($parent, (int) $completion['id'], $at);
            $this->fail('Approval survived a failed ledger write.');
        } catch (\RuntimeException) {
            $saved = (new TaskCompletionModel())->find($completion['id']);
            $this->assertSame('pending', $saved['status']);
            $this->assertSame(0, (new PointTransactionModel())->countAllResults());
        }
    }

    public function testRewardDeductionIsIdempotentAndRejectsIneligibleSource(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $points = new PointService();
        $points->manualAdjustment($parent, $child, 30, 'Funding', $at, 101);
        $rewards = new RewardService();
        $reward = $rewards->create($parent, ['title' => 'Reward', 'points_required' => 20, 'is_active' => 1]);
        $redemption = $rewards->requestRedemption($child, $reward, $at);
        $rewards->approve($parent, (int) $redemption['id'], $at);
        $points->redeemReward($child, (int) $redemption['id'], $at, $parent);
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'reward')->countAllResults());
        $this->assertSame(10, $points->getBalance($child));

        $points->manualAdjustment($parent, $child, 20, 'Second funding', $at, 102);
        $other = $rewards->requestRedemption($child, $reward, $at);
        $rewards->reject($parent, (int) $other['id'], $at);
        $this->expectException(PointException::class);
        $points->redeemReward($child, (int) $other['id'], $at, $parent);
    }

    public function testManualRequestKeyPreventsDuplicateAndPayloadReuse(): void
    {
        [$parent, $child] = $this->ids();
        $points = new PointService();
        $at = Time::now(app_timezone());
        $first = $points->manualAdjustment($parent, $child, 12, 'Correction', $at, 987654);
        $duplicate = $points->manualAdjustment($parent, $child, 12, 'Correction', $at, 987654);
        $this->assertSame($first['id'], $duplicate['id']);
        $this->assertSame(1, (new PointTransactionModel())->where('type', 'adjustment')->countAllResults());
        $this->assertSame(12, $points->getBalance($child));
        $this->expectException(PointException::class);
        $points->manualAdjustment($parent, $child, 13, 'Changed payload', $at, 987654);
    }

    public function testLedgerSourceMigrationBackfillsHistoricalAdjustments(): void
    {
        [$parent, $child] = $this->ids();
        $this->db->table('point_transactions')->insert([
            'child_user_id' => $child, 'type' => 'adjustment', 'points' => 3,
            'reference_type' => null, 'reference_id' => null, 'description' => 'Historical',
            'transaction_date' => date('Y-m-d'), 'created_by_user_id' => $parent,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        require_once APPPATH . 'Database/Migrations/2026-09-11-000024_HardenPointLedgerSources.php';
        (new \App\Database\Migrations\HardenPointLedgerSources())->up();
        $row = $this->db->table('point_transactions')->where('id', $id)->get()->getRowArray();
        $this->assertSame('manual_adjustment', $row['reference_type']);
        $this->assertSame((int) $id, (int) $row['reference_id']);
    }

    public function testDuplicateManualAdjustmentHttpPostUsesOneLedgerEntry(): void
    {
        [$parent, $child] = $this->ids();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $security = service('security');
            $token = $security->getTokenName();
            $hash = $security->getHash();
            $this->withSession([
                'user_id' => $parent, 'user_role' => 'parent', 'family_id' => (int) $family['id'],
                'auth_expires_at' => time() + 3600, $token => $hash,
            ])->post('/points/adjustments', [
                $token => $hash, 'child_user_id' => $child, 'points' => '9',
                'reason' => 'Repeated HTTP request', 'request_id' => '777001',
            ])->assertRedirectTo('/points?child=' . $child);
        }
        $rows = (new PointTransactionModel())->where('reference_type', 'manual_adjustment')
            ->where('reference_id', 777001)->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame(9, (new PointService())->getBalance($child));
    }

    public function testLeaderboardCountsEarnedSourcesButNotSpendingOrAdjustments(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $points = new PointService();
        (new TaskCompletionService())->completeTask($child, $this->task($parent, $child, false, 8), $at);
        $points->manualAdjustment($parent, $child, 100, 'Not earned', $at, 202);
        $rewardService = new RewardService();
        $reward = $rewardService->create($parent, ['title' => 'Spend', 'points_required' => 5, 'is_active' => 1]);
        $redemption = $rewardService->requestRedemption($child, $reward, $at);
        $rewardService->approve($parent, (int) $redemption['id'], $at);
        $this->assertSame(18, $points->getEarnedPointsBetween($child, $at, $at));
        $this->assertSame(113, $points->getBalance($child));
    }
}
