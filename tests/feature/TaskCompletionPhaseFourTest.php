<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\TaskCompletionException;
use App\Models\AuditLogModel;
use App\Models\FamilyModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\RoutineService;
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
final class TaskCompletionPhaseFourTest extends CIUnitTestCase
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

    public function testScheduledTaskCanBeCompletedWithPointSnapshot(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 12);

        $completion = (new TaskCompletionService())->completeTask($childId, $taskId, $monday);

        $this->assertSame($childId, (int) $completion['child_user_id']);
        $this->assertSame($taskId, (int) $completion['routine_task_id']);
        $this->assertSame('2026-09-07', $completion['completion_date']);
        $this->assertSame(12, (int) $completion['points_awarded']);
        $this->assertSame('2026-09-07 08:00:00', $completion['completed_at']);
    }

    public function testCompletionRejectsSiblingInactiveAndWrongWeekdayTasks(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        [$siblingTaskId, $monday] = $this->scheduledTask($parentId, $childTwoId, 5);
        $service = new TaskCompletionService();

        try {
            $service->completeTask($childOneId, $siblingTaskId, $monday);
            $this->fail('A Child must not complete a sibling task.');
        } catch (TaskCompletionException) {
            $this->addToAssertionCount(1);
        }

        [$ownTaskId] = $this->scheduledTask($parentId, $childOneId, 5);
        (new RoutineTaskModel())->update($ownTaskId, ['is_active' => 0]);
        try {
            $service->completeTask($childOneId, $ownTaskId, $monday);
            $this->fail('An inactive task must be rejected.');
        } catch (TaskCompletionException) {
            $this->addToAssertionCount(1);
        }

        [$tuesdayTaskId] = $this->scheduledTask($parentId, $childOneId, 5, 2);
        $this->expectException(TaskCompletionException::class);
        $service->completeTask($childOneId, $tuesdayTaskId, $monday);
    }

    public function testInactiveRoutineIsRejected(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday, $routineId] = $this->scheduledTask($parentId, $childId, 4);
        (new RoutineModel())->update($routineId, ['is_active' => 0]);

        $this->expectException(TaskCompletionException::class);
        (new TaskCompletionService())->completeTask($childId, $taskId, $monday);
    }

    public function testDuplicateCompletionIsRejectedByServiceAndUniqueKey(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 8);
        $service = new TaskCompletionService();
        $service->completeTask($childId, $taskId, $monday);

        try {
            $service->completeTask($childId, $taskId, $monday);
            $this->fail('A duplicate completion must be rejected.');
        } catch (TaskCompletionException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, (new TaskCompletionModel())
            ->where('child_user_id', $childId)
            ->where('routine_task_id', $taskId)
            ->where('completion_date', '2026-09-07')
            ->countAllResults());
    }

    public function testCompletionKeepsPointSnapshotWhenTaskConfigurationChanges(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 10);
        (new TaskCompletionService())->completeTask($childId, $taskId, $monday);

        (new RoutineTaskModel())->update($taskId, ['points' => 99]);

        $completion = (new TaskCompletionModel())->where('routine_task_id', $taskId)->first();
        $this->assertSame(10, (int) $completion['points_awarded']);
    }

    public function testTodayProgressIncludesRequiredOptionalAndSnapshotTotals(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $monday = $this->monday();
        $routineService = new RoutineService();
        $routineId = $routineService->create($parentId, $this->routineData($childId), [1]);
        $requiredTask = $routineService->createTask($parentId, $routineId, $this->taskData('Required', 10, true));
        $routineService->createTask($parentId, $routineId, $this->taskData('Optional', 4, false));
        $completionService = new TaskCompletionService();
        $completionService->completeTask($childId, $requiredTask, $monday);

        $progress = $completionService->getTodayProgress($childId, $monday);

        $this->assertSame(1, $progress['completed_count']);
        $this->assertSame(2, $progress['total_count']);
        $this->assertSame(1, $progress['required_completed_count']);
        $this->assertSame(1, $progress['required_total_count']);
        $this->assertSame(50, $progress['completion_percentage']);
        $this->assertSame(10, $progress['completed_snapshot_points']);
        $this->assertTrue($progress['routines'][0]['tasks'][0]['is_completed']);
        $this->assertFalse($progress['routines'][0]['tasks'][1]['is_completed']);
    }

    public function testSameDayUndoRemovesCompletionAndCreatesOneAuditRecord(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 6);
        $service = new TaskCompletionService();
        $service->completeTask($childId, $taskId, $monday);

        $service->undoTask($childId, $taskId, $monday->modify('+10 hours'));

        $this->assertSame(0, (new TaskCompletionModel())->where('routine_task_id', $taskId)->countAllResults());
        $audit = (new AuditLogModel())->where('action', 'task.completion_undone')->first();
        $this->assertNotNull($audit);
        $this->assertSame($childId, (int) $audit['user_id']);
        $this->assertStringContainsString('"points_awarded":6', (string) $audit['old_values']);

        try {
            $service->undoTask($childId, $taskId, $monday);
            $this->fail('A second undo must be rejected.');
        } catch (TaskCompletionException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, (new AuditLogModel())->where('action', 'task.completion_undone')->countAllResults());
    }

    public function testUndoCannotRemoveCompletionFromAnotherDate(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday] = $this->scheduledTask($parentId, $childId, 3);
        $service = new TaskCompletionService();
        $service->completeTask($childId, $taskId, $monday);

        $this->expectException(TaskCompletionException::class);
        $service->undoTask($childId, $taskId, $monday->modify('+1 day'));
    }

    public function testTrustedChildPostUsesDeviceIdentityAndRejectsSiblingTask(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $weekday = (int) (new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format('N');
        [$ownTaskId] = $this->scheduledTask($parentId, $childOneId, 7, $weekday);
        [$siblingTaskId] = $this->scheduledTask($parentId, $childTwoId, 9, $weekday);
        $device = (new ChildDeviceService())->provision($parentId, $childOneId, 'Phase 4 phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $security = service('security');
        $tokenName = $security->getTokenName();
        $csrfHash = $security->getHash();
        $session = [$tokenName => $csrfHash];

        $ownResponse = $this->withSession($session)->post('/child/tasks/' . $ownTaskId . '/complete', [
            $tokenName => $csrfHash,
            'child_id' => $childTwoId,
        ]);
        $ownResponse->assertRedirectTo('/child/today');
        $this->assertSame(1, (new TaskCompletionModel())->where('child_user_id', $childOneId)->countAllResults());

        $csrfHash = service('security')->getHash();
        $siblingResponse = $this->withSession([$tokenName => $csrfHash])->post('/child/tasks/' . $siblingTaskId . '/complete', [
            $tokenName => $csrfHash,
            'child_id' => $childTwoId,
        ]);
        $siblingResponse->assertRedirectTo('/child/today');
        $this->assertSame(0, (new TaskCompletionModel())->where('child_user_id', $childTwoId)->countAllResults());
    }

    public function testChildTodayAndProgressRenderCompletionStateWithoutSiblingData(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $weekday = (int) (new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format('N');
        [$ownTaskId] = $this->scheduledTask($parentId, $childOneId, 7, $weekday, 'Own Phase 4 Task');
        $this->scheduledTask($parentId, $childTwoId, 9, $weekday, 'Sibling Secret Phase 4');
        $device = (new ChildDeviceService())->provision($parentId, $childOneId, 'Render phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);

        $today = $this->get('/child/today');
        $today->assertOK();
        $today->assertSee('Own Phase 4 Task');
        $today->assertSee('/child/tasks/' . $ownTaskId . '/complete');
        $today->assertDontSee('Sibling Secret Phase 4');

        $progress = $this->get('/child/progress');
        $progress->assertOK();
        $progress->assertSee('Progress hari ini');
        $progress->assertSee('Baki points');
    }

    public function testCompletedTaskAndRoutineAreArchivedInsteadOfDeletingHistory(): void
    {
        [$parentId, $childId] = $this->demoIds();
        [$taskId, $monday, $routineId] = $this->scheduledTask($parentId, $childId, 2);
        (new TaskCompletionService())->completeTask($childId, $taskId, $monday);
        $routineService = new RoutineService();

        $taskResult = $routineService->deleteTask($parentId, $taskId);
        $routineResult = $routineService->delete($parentId, $routineId);

        $this->assertSame('archived', $taskResult['action']);
        $this->assertSame('archived', $routineResult);
        $this->assertSame(0, (int) (new RoutineTaskModel())->find($taskId)['is_active']);
        $this->assertSame(0, (int) (new RoutineModel())->find($routineId)['is_active']);
        $this->assertSame(1, (new TaskCompletionModel())->where('routine_task_id', $taskId)->countAllResults());
    }

    public function testLaterReportAndPwaPhasesAreIntegratedWithoutChangingCompletionContracts(): void
    {
        $this->assertTrue(class_exists('App\\Services\\ReportService'));
        $this->assertFileExists(ROOTPATH . 'public/manifest.webmanifest');
        $this->assertFileExists(ROOTPATH . 'public/service-worker.js');
    }

    private function scheduledTask(
        int $parentId,
        int $childId,
        int $points,
        int $weekday = 1,
        string $title = 'Phase 4 Task',
    ): array {
        $routines = new RoutineService();
        $routineId = $routines->create($parentId, $this->routineData($childId), [$weekday]);
        $taskId = $routines->createTask($parentId, $routineId, $this->taskData($title, $points));

        return [$taskId, $this->monday(), $routineId];
    }

    private function monday(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-07 08:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));
    }

    private function routineData(int $childId): array
    {
        return [
            'child_user_id' => $childId,
            'name' => 'Phase 4 Routine ' . bin2hex(random_bytes(3)),
            'description' => null,
            'type' => 'Morning',
            'start_time' => '07:00',
            'sort_order' => 0,
            'is_active' => 1,
        ];
    }

    private function taskData(string $title, int $points, bool $required = true): array
    {
        return [
            'title' => $title,
            'description' => null,
            'task_time' => '07:15',
            'points' => $points,
            'is_required' => $required ? 1 : 0,
            'sort_order' => 0,
            'is_active' => 1,
        ];
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
}
