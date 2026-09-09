<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\RewardException;
use App\Models\ChildRewardGoalModel;
use App\Models\FamilyModel;
use App\Models\PointTransactionModel;
use App\Models\RewardModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\PointService;
use App\Services\RewardService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class RewardGoalTest extends CIUnitTestCase
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

    private function reward(int $parent, string $title = 'Movie Night', int $cost = 500): int
    {
        return (new RewardService())->create($parent, ['title' => $title, 'points_required' => $cost, 'is_active' => 1]);
    }

    private function postChild(string $url, array $data = [])
    {
        $security = service('security');
        $this->withSession([$security->getTokenName() => $security->getHash()]);
        return $this->post($url, $data + [$security->getTokenName() => $security->getHash()]);
    }

    public function testSelectionReplacementAndCancellationNeverSpendPoints(): void
    {
        [$parent, $child, $sibling] = $this->ids();
        $service = new RewardService();
        $at = Time::now(app_timezone());
        $reward = $this->reward($parent);
        $other = $this->reward($parent, 'Book');
        $first = $service->setGoal($child, $reward, $at);
        $this->assertSame($first['id'], $service->setGoal($child, $reward, $at)['id']);
        $service->setGoal($sibling, $reward, $at);
        $second = $service->setGoal($child, $other, $at);
        $this->assertSame('cancelled', (new ChildRewardGoalModel())->find($first['id'])['status']);
        $this->assertSame(1, (new ChildRewardGoalModel())->where('child_id', $child)->where('status', 'active')->countAllResults());
        foreach ([[$child, $first['id']], [$sibling, $second['id']]] as [$actor, $id]) {
            try {
                $service->cancelGoal($actor, (int) $id);
                $this->fail('Stale or foreign goal cancellation must fail.');
            } catch (RewardException) {
                $this->assertSame($second['id'], $service->activeGoal($child)['id']);
            }
        }
        $service->cancelGoal($child, (int) $second['id']);
        $this->assertNull($service->activeGoal($child));
        $this->assertNotNull($service->activeGoal($sibling));
        $this->assertSame(0, (new PointTransactionModel())->countAllResults());
    }

    public function testInaccessibleRewardsDoNotReplaceExistingGoal(): void
    {
        [$parent, $child] = $this->ids();
        $service = new RewardService();
        $at = Time::now(app_timezone());
        $active = $service->setGoal($child, $this->reward($parent), $at);
        $archived = $this->reward($parent, 'Archived');
        $service->archive($parent, $archived);
        $foreign = $this->reward($parent, 'Foreign');
        $family = (new FamilyModel())->insert(['name' => 'Other family'], true);
        (new RewardModel())->update($foreign, ['family_id' => $family]);
        foreach ([$archived, $foreign, 999999] as $id) {
            try {
                $service->setGoal($child, $id, $at);
                $this->fail('Inaccessible reward accepted.');
            } catch (RewardException) {
                $this->assertSame($active['id'], $service->activeGoal($child)['id']);
            }
        }
        $service->archive($parent, (int) $active['reward_id']);
        $this->assertNull($service->activeGoal($child));
        $this->expectException(RewardException::class);
        $service->setGoal($parent, $foreign, $at);
    }

    public function testOnlySuccessfulMatchingRedemptionCompletesGoal(): void
    {
        [$parent, $child] = $this->ids();
        $service = new RewardService();
        $points = new PointService();
        $at = Time::now(app_timezone());
        $points->manualAdjustment($parent, $child, 600, 'Goal test', $at);
        $reward = $this->reward($parent);
        $goal = $service->setGoal($child, $reward, $at);
        $other = $service->requestRedemption($child, $this->reward($parent, 'Book', 50), $at);
        $service->approve($parent, (int) $other['id'], $at);
        $this->assertNotNull($service->activeGoal($child));
        $request = $service->requestRedemption($child, $reward, $at);
        $this->assertSame(550, $points->getBalance($child));
        $service->reject($parent, (int) $request['id'], $at);
        $this->assertNotNull($service->activeGoal($child));
        $request = $service->requestRedemption($child, $reward, $at);
        $service->approve($parent, (int) $request['id'], $at);
        $this->assertSame(50, $points->getBalance($child));
        $this->assertNull($service->activeGoal($child));
        $completed = (new ChildRewardGoalModel())->find($goal['id']);
        $this->assertSame('completed', $completed['status']);
        $this->assertNotNull($completed['completed_at']);
    }

    public function testRoutesUseTrustedChildAndDashboardShowsLiveBalance(): void
    {
        [$parent, $child, $sibling] = $this->ids();
        $reward = $this->reward($parent);
        $this->postChild('/child/rewards/' . $reward . '/goal')->assertRedirectTo('/login');
        $device = (new ChildDeviceService())->provision($parent, $child);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        (new PointService())->manualAdjustment($parent, $child, 430, 'Goal test', Time::now(app_timezone()));
        $this->postChild('/child/rewards/' . $reward . '/goal', ['child_id' => $sibling])->assertRedirectTo('/child/today');
        $goal = (new RewardService())->activeGoal($child);
        $this->assertNotNull($goal);
        $this->assertNull((new RewardService())->activeGoal($sibling));
        $page = $this->get('/child/today');
        $page->assertOK();
        $page->assertSee('Movie Night');
        $page->assertSee('86%');
        $body = $page->response()->getBody();
        $this->assertStringContainsString('data-goal-balance>430</span> / 500 mata', $body);
        $this->assertStringContainsString('data-goal-remaining>70</span> mata lagi', $body);
        $this->get('/child/rewards')->assertSee('Sasaran semasa');
        (new PointService())->manualAdjustment($parent, $child, 100, 'More points', Time::now(app_timezone()));
        $body = $this->get('/child/today')->response()->getBody();
        $this->assertStringContainsString('data-goal-percentage>100%', $body);
        $this->assertStringContainsString('data-goal-remaining>0</span>', $body);
        $this->assertNotNull((new RewardService())->activeGoal($child));
        $this->postChild('/child/reward-goals/' . $goal['id'] . '/cancel')->assertRedirectTo('/child/today');
        $this->get('/child/today')->assertSee('Pilih sasaran');
    }

    public function testFailedReplacementRollsBackCancellation(): void
    {
        [$parent, $child] = $this->ids();
        $service = new RewardService();
        $goal = $service->setGoal($child, $this->reward($parent), Time::now(app_timezone()));
        $replacement = $this->reward($parent, 'Replacement');
        $table = $this->db->protectIdentifiers($this->db->prefixTable('child_reward_goals'));
        $this->db->query("CREATE TRIGGER fail_goal_insert BEFORE INSERT ON {$table} BEGIN SELECT RAISE(ABORT, 'Simulated insert failure'); END");
        try {
            $service->setGoal($child, $replacement, Time::now(app_timezone()));
            $this->fail('Expected insert failure.');
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException) {
            $this->assertSame($goal['id'], $service->activeGoal($child)['id']);
        } finally {
            $this->db->query('DROP TRIGGER fail_goal_insert');
            $this->db->resetTransStatus();
        }
    }

    public function testInactiveChildCannotSelectGoal(): void
    {
        [$parent, $child] = $this->ids();
        $reward = $this->reward($parent);
        (new UserModel())->update($child, ['is_active' => 0]);
        $this->expectException(RewardException::class);
        (new RewardService())->setGoal($child, $reward, Time::now(app_timezone()));
    }

    public function testMigrationRetryPreservesGoalsAndRestoresMissingIndex(): void
    {
        [$parent, $child] = $this->ids();
        $goal = (new RewardService())->setGoal($child, $this->reward($parent), Time::now(app_timezone()));
        // Reproduce the persisted table left behind by a failed index creation.
        $this->db->query('DROP INDEX child_reward_goals_one_active');
        require_once APPPATH . 'Database/Migrations/2026-09-09-000021_CreateChildRewardGoals.php';
        $migration = new \App\Database\Migrations\CreateChildRewardGoals();
        $migration->up();
        $migration->up();
        $this->assertSame($goal['id'], (new RewardService())->activeGoal($child)['id']);
        $this->assertArrayHasKey('child_reward_goals_one_active', $this->db->getIndexData('child_reward_goals'));
    }

    public function testDatabaseRejectsSecondActiveGoal(): void
    {
        [$parent, $child] = $this->ids();
        $goal = (new RewardService())->setGoal($child, $this->reward($parent), Time::now(app_timezone()));
        unset($goal['id']);
        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('child_reward_goals')->insert($goal);
    }
}
