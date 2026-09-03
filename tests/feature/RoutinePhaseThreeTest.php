<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Models\FamilyModel;
use App\Models\RoutineDayModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\RoutineService;
use App\Services\TodayTaskResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class RoutinePhaseThreeTest extends CIUnitTestCase
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

    public function testParentCreatesRoutineWithUniqueWeeklyDaysAndTasks(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId), [1, 3, 1, 5]);
        $taskId = $service->createTask($parentId, $routineId, $this->taskData('Brush Teeth', 10));

        $this->assertSame([1, 3, 5], $service->daysForRoutine($routineId));
        $this->assertSame('Brush Teeth', (new RoutineTaskModel())->find($taskId)['title']);
        $this->assertSame(1, (new RoutineModel())->where('child_user_id', $childId)->countAllResults());
    }

    public function testRoutineUpdateReplacesScheduleAndParentTwoCanManageSameFamily(): void
    {
        [$parentOneId, $childId, , $parentTwoId] = $this->demoIds(true);
        $service = new RoutineService();
        $routineId = $service->create($parentOneId, $this->routineData($childId), [1, 2]);
        $updated = $this->routineData($childId);
        $updated['name'] = 'Updated Routine';

        $service->update($parentTwoId, $routineId, $updated, [6, 7]);

        $this->assertSame('Updated Routine', (new RoutineModel())->find($routineId)['name']);
        $this->assertSame([6, 7], $service->daysForRoutine($routineId));
    }

    public function testOutsideFamilyCannotReadOrMutateRoutineAndTask(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId), [1]);
        $taskId = $service->createTask($parentId, $routineId, $this->taskData('Private Task', 5));
        $outsideParentId = $this->createOutsideFamilyParent();

        try {
            $service->getForParent($outsideParentId, $routineId);
            $this->fail('Outside parent must not read a routine.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AuthorizationException::class);
        $service->updateTask($outsideParentId, $taskId, $this->taskData('Tampered', 999));
    }

    public function testInvalidOrMissingDaysRejectRoutineWithoutPartialInsert(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();

        try {
            $service->create($parentId, $this->routineData($childId), [0, 8]);
            $this->fail('Invalid weekdays must be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (new RoutineModel())->countAllResults());
        $this->expectException(InvalidArgumentException::class);
        $service->create($parentId, $this->routineData($childId), []);
    }

    public function testTodayResolverUsesLocalWeekdayAndOnlyActiveMatchingRecords(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $service = new RoutineService();
        $monday = new DateTimeImmutable('2026-09-07 08:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));

        $morningId = $service->create($parentId, $this->routineData($childOneId, 'Morning', true, 1), [1]);
        $service->createTask($parentId, $morningId, $this->taskData('Required First', 10, true, true, 1));
        $service->createTask($parentId, $morningId, $this->taskData('Optional Second', 4, false, true, 2));
        $service->createTask($parentId, $morningId, $this->taskData('Inactive Task', 99, true, false, 0));

        $service->create($parentId, $this->routineData($childOneId, 'Tuesday Only'), [2]);
        $inactiveRoutine = $this->routineData($childOneId, 'Inactive Routine', false);
        $inactiveId = $service->create($parentId, $inactiveRoutine, [1]);
        $service->createTask($parentId, $inactiveId, $this->taskData('Hidden Routine Task', 50));

        $siblingId = $service->create($parentId, $this->routineData($childTwoId, 'Sibling Routine'), [1]);
        $service->createTask($parentId, $siblingId, $this->taskData('Sibling Task', 20));

        $result = (new TodayTaskResolver())->resolve($childOneId, $monday);

        $this->assertSame(1, $result['weekday']);
        $this->assertSame(2, $result['task_count']);
        $this->assertSame(1, $result['required_task_count']);
        $this->assertSame(14, $result['available_points']);
        $this->assertCount(1, $result['routines']);
        $this->assertSame(['Required First', 'Optional Second'], array_column($result['routines'][0]['tasks'], 'title'));
    }

    public function testResolverConvertsInputInstantToKualaLumpurDate(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId), [1]);
        $service->createTask($parentId, $routineId, $this->taskData('Monday Local Task', 3));
        $utcSunday = new DateTimeImmutable('2026-09-06 16:30:00', new DateTimeZone('UTC'));

        $result = (new TodayTaskResolver())->resolve($childId, $utcSunday);

        $this->assertSame('2026-09-07', $result['date']);
        $this->assertSame(1, $result['weekday']);
        $this->assertSame('Monday Local Task', $result['routines'][0]['tasks'][0]['title']);
    }

    public function testTrustedChildDashboardIgnoresForgedSiblingId(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $weekday = (int) (new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format('N');
        $service = new RoutineService();
        $ownRoutine = $service->create($parentId, $this->routineData($childOneId, 'Own Routine'), [$weekday]);
        $ownTask = $service->createTask($parentId, $ownRoutine, $this->taskData('Own Visible Task', 7));
        $siblingRoutine = $service->create($parentId, $this->routineData($childTwoId, 'Sibling Routine'), [$weekday]);
        $service->createTask($parentId, $siblingRoutine, $this->taskData('Sibling Secret Task', 9));

        $device = (new ChildDeviceService())->provision($parentId, $childOneId, 'Child One phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);

        $response = $this->get('/child/today?child_id=' . $childTwoId);

        $response->assertOK();
        $response->assertSee('Own Visible Task');
        $response->assertDontSee('Sibling Secret Task');
        $this->assertStringContainsString('/child/tasks/' . $ownTask . '/complete', $response->response()->getBody());
    }

    public function testRoutineDeleteCascadesDaysAndTasks(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId), [1, 2]);
        $service->createTask($parentId, $routineId, $this->taskData('Temporary', 1));

        $service->delete($parentId, $routineId);

        $this->assertNull((new RoutineModel())->find($routineId));
        $this->assertSame(0, (new RoutineDayModel())->where('routine_id', $routineId)->countAllResults());
        $this->assertSame(0, (new RoutineTaskModel())->where('routine_id', $routineId)->countAllResults());
    }

    public function testChildRoutineAndTaskTextIsEscaped(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $weekday = (int) (new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format('N');
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId, '<script>alert(1)</script>'), [$weekday]);
        $service->createTask($parentId, $routineId, $this->taskData('<img src=x onerror=alert(1)>', 2));
        $device = (new ChildDeviceService())->provision($parentId, $childId, 'Escape phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);

        $body = $this->get('/child/today')->response()->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
    }

    public function testParentCreateRouteUsesCsrfAndRedirectsToEdit(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $security = service('security');
        $tokenName = $security->getTokenName();
        $csrfHash = $security->getHash();

        $response = $this->withSession($this->parentSession($parentId, (int) $family['id'], $tokenName, $csrfHash))
            ->post('/routines', [
                $tokenName => $csrfHash,
                'child_user_id' => $childId,
                'name' => 'Route Routine',
                'description' => '',
                'type' => 'Morning',
                'start_time' => '07:00',
                'sort_order' => '0',
                'is_active' => '1',
                'days' => ['1', '2'],
            ]);

        $routine = (new RoutineModel())->where('name', 'Route Routine')->first();
        $this->assertNotNull($routine);
        $response->assertRedirectTo('/routines/' . $routine['id'] . '/edit');
    }

    public function testParentRoutineAndTaskManagementViewsRender(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $service = new RoutineService();
        $routineId = $service->create($parentId, $this->routineData($childId, 'Rendered Routine'), [1, 2]);
        $taskId = $service->createTask($parentId, $routineId, $this->taskData('Rendered Task', 6));
        $session = [
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
        ];

        $list = $this->withSession($session)->get('/routines');
        $list->assertOK();
        $list->assertSee('Rendered Routine');

        $editRoutine = $this->withSession($session)->get('/routines/' . $routineId . '/edit');
        $editRoutine->assertOK();
        $editRoutine->assertSee('Rendered Task');

        $editTask = $this->withSession($session)->get('/routine-tasks/' . $taskId . '/edit');
        $editTask->assertOK();
        $editTask->assertSee('Task untuk Rendered Routine');
    }

    public function testCompletionRoutesRemainAvailableAndRankingIsParentGetOnly(): void
    {
        $routes = service('routes')->loadRoutes();

        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('child/tasks/([0-9]+)/complete', $postRoutes);
        $this->assertArrayHasKey('child/tasks/([0-9]+)/undo', $postRoutes);
        $this->assertArrayHasKey('child/progress', $routes->getRoutes('GET'));
        $this->assertArrayHasKey('ranking', $routes->getRoutes('GET'));
        $this->assertArrayNotHasKey('ranking', $routes->getRoutes('POST'));
    }

    public function testAllChildrenGetIndependentRoutinesWithTheSameFieldsAndDays(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $data = $this->routineData($childId);
        $ids = $service->createForAllChildren($parentId, $data, [5, 1, 5]);
        $this->assertCount(3, $ids);
        $children = (new UserModel())->where('role', 'child')->findAll();
        $this->assertEqualsCanonicalizing(array_map(static fn ($child) => (int) $child->id, $children),
            array_map('intval', array_column((new RoutineModel())->findAll(), 'child_user_id')));
        foreach ($ids as $id) {
            $routine = $service->getForParent($parentId, $id);
            $this->assertSame($data['name'], $routine['name']);
            $this->assertSame($data['description'], $routine['description']);
            $this->assertSame($data['type'], $routine['type']);
            $this->assertSame('07:00:00', $routine['start_time']);
            $this->assertSame([1, 5], $routine['days']);
            $this->assertSame([], $routine['tasks']);
        }
        $service->createTask($parentId, $ids[0], $this->taskData('Only this child', 4));
        $this->assertSame([], $service->tasksForRoutine($ids[1]));
        $this->assertSame([], $service->tasksForRoutine($ids[2]));
    }

    public function testAllChildrenExcludesInactiveChildrenAndOtherFamilies(): void
    {
        [$parentId, $childId, $childTwoId] = $this->demoIds(true);
        (new UserModel())->update($childTwoId, ['is_active' => 0]);
        $outsideParent = $this->createOutsideFamilyParent();
        $outsideFamily = (new \App\Services\FamilyService())->currentFamilyForUser($outsideParent);
        $thirdChild = (new UserModel())->where('username', 'child-three-internal')->first();
        $this->db->table('family_users')->where('user_id', $thirdChild->id)->update(['family_id' => $outsideFamily['id']]);

        // A posted child ID is deliberately ignored for the all-children operation.
        $ids = (new RoutineService())->createForAllChildren($parentId, $this->routineData((int) $thirdChild->id), [1]);
        $this->assertCount(1, $ids);
        $this->assertSame($childId, (int) (new RoutineModel())->find($ids[0])['child_user_id']);
        $this->assertSame(1, (new RoutineModel())->countAllResults());
    }

    public function testAllChildrenRejectsFamilyWithNoActiveChildren(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $this->db->table('users')->where('role', 'child')->update(['is_active' => 0]);
        try {
            (new RoutineService())->createForAllChildren($parentId, $this->routineData($childId), [1]);
            $this->fail('An empty selection must not report success.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Tiada anak aktif', $exception->getMessage());
        }
        $this->assertSame(0, (new RoutineModel())->countAllResults());
    }

    public function testChildCannotUseAllChildrenService(): void
    {
        [, $childId] = $this->demoIds();
        try {
            (new RoutineService())->createForAllChildren($childId, $this->routineData($childId), [1]);
            $this->fail('A Child cannot create routines.');
        } catch (AuthorizationException) {
            $this->assertSame(0, (new RoutineModel())->countAllResults());
        }
    }

    public function testAllChildrenRollsBackEarlierCopiesIfALaterInsertFails(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $model = new class ($this->db) extends RoutineModel {
            public int $attempts = 0;

            public function insert($row = null, bool $returnID = true)
            {
                if (++$this->attempts === 2) {
                    throw new InvalidArgumentException('Simulated second-child insert failure.');
                }

                return parent::insert($row, $returnID);
            }
        };
        try {
            (new RoutineService(routines: $model, db: $this->db))->createForAllChildren($parentId, $this->routineData($childId), [1, 2]);
            $this->fail('The second insert must fail.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Simulated second-child insert failure.', $exception->getMessage());
        }
        $this->assertSame(2, $model->attempts);
        $this->assertSame(0, (new RoutineModel())->countAllResults());
        $this->assertSame(0, (new RoutineDayModel())->countAllResults());
    }

    public function testAllChildrenCreateRouteRedirectsToListWithCount(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $payload = $this->routineData($childId);
        $payload['child_user_id'] = 'all';
        $payload['days'] = [1, 3];
        $response = $this->postRoutineAsParent($parentId, '/routines', $payload);
        $response->assertRedirectTo('/routines');
        $this->assertSame(3, (new RoutineModel())->countAllResults());
        $this->assertStringContainsString('3 anak aktif', session('success'));
    }

    public function testAllChildrenOptionIsOnlyOfferedWhenCreatingAndPreservesInvalidInput(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $session = $this->parentSession($parentId, (int) $family['id'], 'unused', 'unused');
        $create = $this->withSession($session)->get('/routines/new');
        $create->assertOK();
        $create->assertSee('Semua anak');
        $payload = $this->routineData($childId);
        $payload['child_user_id'] = 'all';
        $payload['days'] = [];
        $this->postRoutineAsParent($parentId, '/routines', $payload)->assertRedirect();
        $this->assertSame(0, (new RoutineModel())->countAllResults());
        $this->assertSame('all', service('session')->getFlashdata('_ci_old_input')['post']['child_user_id']);
        // FeatureTestTrait resets the session for each request; carry flash input across the redirect.
        $retry = $this->withSession()->get('/routines/new');
        $retry->assertOK();
        $this->assertStringContainsString('value="all" selected', $retry->response()->getBody());
        $id = (new RoutineService())->create($parentId, $this->routineData($childId), [1]);
        $edit = $this->withSession($session)->get('/routines/' . $id . '/edit');
        $edit->assertOK();
        $this->assertStringNotContainsString('value="all"', $edit->response()->getBody());
    }

    public function testAllChildrenCannotBeSubmittedAsAnUpdate(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new RoutineService();
        $id = $service->create($parentId, $this->routineData($childId), [1]);
        $data = $this->routineData($childId, 'Must not change');
        $data['child_user_id'] = 'all';
        $data['days'] = [2];
        $this->postRoutineAsParent($parentId, '/routines/' . $id, $data)->assertRedirect();
        $this->assertSame(1, (new RoutineModel())->countAllResults());
        $this->assertSame('Morning Routine', (new RoutineModel())->find($id)['name']);
        $this->assertSame([1], $service->daysForRoutine($id));
    }

    #[DataProvider('invalidChildSelections')]
    public function testCreateRejectsInvalidChildSelection(mixed $selection): void
    {
        [$parentId, $childId] = $this->demoIds();
        $data = $this->routineData($childId);
        $data['child_user_id'] = $selection;
        $data['days'] = [1];
        $this->postRoutineAsParent($parentId, '/routines', $data)->assertRedirect();
        $this->assertSame(0, (new RoutineModel())->countAllResults());
        $this->assertArrayHasKey('child_user_id', session('errors'));
    }

    public static function invalidChildSelections(): array
    {
        return [[''], ['0'], ['all-children'], [['all']]];
    }

    private function postRoutineAsParent(int $parentId, string $path, array $payload)
    {
        $family = (new \App\Services\FamilyService())->currentFamilyForUser($parentId);
        $security = service('security');
        $token = $security->getTokenName();
        $hash = $security->getHash();

        return $this->withSession($this->parentSession($parentId, (int) $family['id'], $token, $hash))
            ->post($path, [$token => $hash] + $payload);
    }

    private function routineData(int $childId, string $name = 'Morning Routine', bool $active = true, int $sortOrder = 0): array
    {
        return [
            'child_user_id' => $childId,
            'name' => $name,
            'description' => 'A recurring routine',
            'type' => 'Morning',
            'start_time' => '07:00',
            'sort_order' => $sortOrder,
            'is_active' => $active ? 1 : 0,
        ];
    }

    private function taskData(string $title, int $points, bool $required = true, bool $active = true, int $sortOrder = 0): array
    {
        return [
            'title' => $title,
            'description' => null,
            'task_time' => '07:15',
            'points' => $points,
            'is_required' => $required ? 1 : 0,
            'sort_order' => $sortOrder,
            'is_active' => $active ? 1 : 0,
        ];
    }

    private function demoIds(bool $extended = false): array
    {
        $users = new UserModel();
        $parentOne = $users->where('email', 'parent1@example.com')->first();
        $childOne = $users->where('username', 'child-one-internal')->first();

        if (! $extended) {
            return [(int) $parentOne->id, (int) $childOne->id];
        }

        $childTwo = $users->where('username', 'child-two-internal')->first();
        $parentTwo = $users->where('email', 'parent2@example.com')->first();

        return [(int) $parentOne->id, (int) $childOne->id, (int) $childTwo->id, (int) $parentTwo->id];
    }

    private function parentSession(int $parentId, int $familyId, string $tokenName, string $csrfHash): array
    {
        return [
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => $familyId,
            'auth_expires_at' => time() + 3600,
            $tokenName => $csrfHash,
        ];
    }

    private function createOutsideFamilyParent(): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('families')->insert(['name' => 'Outside Routine Family', 'created_at' => $now, 'updated_at' => $now]);
        $familyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Routine Parent',
            'email' => 'outside-routine@example.com',
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
