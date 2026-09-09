<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\RoutineModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\DailyProgressService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class DailyProgressTest extends CIUnitTestCase
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
            (int) $users->where('username', 'child-one-internal')->first()->id];
    }

    private function routine(int $parent, int $child, array $data = [], array $days = [1,2,3,4,5,6,7]): int
    {
        return (new RoutineService())->create($parent, $data + ['name' => 'Daily routine', 'child_user_id' => $child, 'is_active' => 1], $days);
    }

    private function task(int $parent, int $routine, array $data = []): int
    {
        return (new RoutineService())->createTask($parent, $routine, $data + ['title' => 'Required task', 'points' => 10, 'is_required' => 1, 'is_active' => 1]);
    }

    public function testPendingRejectedApprovedAutomaticAndUndo(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $completions = new TaskCompletionService();
        $progress = new DailyProgressService();
        $automatic = $this->task($parent, $this->routine($parent, $child));
        $approvalRoutine = $this->routine($parent, $child, ['requires_approval' => 1]);
        $approval = $this->task($parent, $approvalRoutine);
        $rejected = $this->task($parent, $this->routine($parent, $child), ['requires_approval' => 1]);
        $completions->completeTask($child, $automatic, $at);
        $pending = $completions->completeTask($child, $approval, $at);
        $reject = $completions->completeTask($child, $rejected, $at);
        $completions->rejectCompletion($parent, (int) $reject['id'], 'Try again', $at);
        $this->assertSame(['completed_count' => 1, 'total_count' => 3, 'percentage' => 33], $progress->forChild($child, $at));
        $completions->approveCompletion($parent, (int) $pending['id'], $at);
        $this->assertSame(['completed_count' => 2, 'total_count' => 3, 'percentage' => 67], $progress->forChild($child, $at));
        $completions->undoTask($child, $automatic, $at);
        $this->assertSame(1, $progress->forChild($child, $at)['completed_count']);
    }

    public function testCountsRoutinesNotTasksAndExcludesOptionalEmptyInactiveAndOtherDays(): void
    {
        [$parent, $child] = $this->ids();
        $at = Time::now(app_timezone());
        $routine = $this->routine($parent, $child);
        $first = $this->task($parent, $routine);
        $second = $this->task($parent, $routine);
        $this->task($parent, $routine, ['is_required' => 0]);
        $this->task($parent, $routine, ['is_active' => 0]);
        $this->routine($parent, $child); // Empty routine.
        $this->task($parent, $this->routine($parent, $child), ['is_required' => 0]);
        $this->task($parent, $this->routine($parent, $child, ['is_required' => 0]));
        $this->task($parent, $this->routine($parent, $child, ['is_active' => 0]));
        $tomorrow = ((int) $at->format('N') % 7) + 1;
        $this->task($parent, $this->routine($parent, $child, [], [$tomorrow]));
        $sibling = (int) (new UserModel())->where('username', 'child-two-internal')->first()->id;
        $this->task($parent, $this->routine($parent, $sibling));
        $completions = new TaskCompletionService();
        $progress = new DailyProgressService();
        $completions->completeTask($child, $first, $at->subDays(1));
        $this->assertSame(['completed_count' => 0, 'total_count' => 1, 'percentage' => 0], $progress->forChild($child, $at));
        $completions->completeTask($child, $first, $at);
        $this->assertSame(0, $progress->forChild($child, $at)['completed_count']);
        $completions->completeTask($child, $second, $at);
        $this->assertSame(['completed_count' => 1, 'total_count' => 1, 'percentage' => 100], $progress->forChild($child, $at));
    }

    public function testDashboardAndAjaxUseSameProgress(): void
    {
        [$parent, $child] = $this->ids();
        $device = (new ChildDeviceService())->provision($parent, $child);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $at = Time::now(app_timezone());
        for ($i = 0; $i < 6; ++$i) {
            $task = $this->task($parent, $this->routine($parent, $child));
            if ($i < 5) {
                (new TaskCompletionService())->completeTask($child, $task, $at);
            }
        }
        $page = $this->get('/child/today');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('data-daily-completed>5</span> / <span data-daily-total>6</span> rutin selesai', $body);
        $this->assertStringContainsString('data-daily-percentage>83%', $body);
        foreach (['complete' => 100, 'undo' => 83] as $action => $percentage) {
            $security = service('security');
            $this->withSession([$security->getTokenName() => $security->getHash()]);
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
            $response = $this->post('/child/tasks/' . $task . '/' . $action, [$security->getTokenName() => $security->getHash()]);
            $response->assertOK();
            $this->assertSame($percentage, json_decode($response->response()->getBody(), true)['daily_progress']['percentage']);
        }
    }

    public function testEmptyDayAndMigrationDefaultsExistingRoutinesToRequired(): void
    {
        [$parent, $child] = $this->ids();
        $this->assertSame(['completed_count' => 0, 'total_count' => 0, 'percentage' => 0], (new DailyProgressService())->forChild($child, Time::now(app_timezone())));
        $routine = $this->routine($parent, $child, ['is_required' => 0]);
        require_once APPPATH . 'Database/Migrations/2026-09-09-000022_AddRequiredRoutines.php';
        $migration = new \App\Database\Migrations\AddRequiredRoutines();
        $migration->down();
        $migration->up();
        $migration->up();
        $this->assertSame(1, (int) (new RoutineModel())->find($routine)['is_required']);
        (new RoutineService())->update($parent, $routine, ['child_user_id' => $child, 'is_required' => 0], [1,2,3,4,5,6,7]);
        (new RoutineService())->update($parent, $routine, ['child_user_id' => $child, 'name' => 'Updated'], [1,2,3,4,5,6,7]);
        $this->assertSame(0, (int) (new RoutineModel())->find($routine)['is_required']);
    }
}
