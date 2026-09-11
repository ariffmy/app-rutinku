<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\FamilyModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\UserModel;
use App\Services\ChildManagementService;
use App\Services\RoutineService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ChildAccountFixesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $seed = DemoSeeder::class;
    protected $refresh = true;

    private function ids(): array
    {
        $users = new UserModel();
        return [
            (int) $users->where('email', 'parent1@example.com')->first()->id,
            (int) $users->where('username', 'child-one-internal')->first()->id,
        ];
    }

    private function parentSession(int $parentId): array
    {
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        return [
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
        ];
    }

    public function testPasswordCanChangeWhileKeepingTheSameEmail(): void
    {
        [$parent, $child] = $this->ids();
        $record = (new ChildManagementService())->getForParent($parent, $child);
        (new ChildManagementService())->update($parent, $child, [
            'name' => $record['user']->name,
            'email' => $record['user']->email,
            'password' => 'new-password-123',
            'date_of_birth' => $record['profile']['date_of_birth'] ?? null,
            'is_ranking_eligible' => $record['profile']['is_ranking_eligible'] ?? 1,
            'is_active' => 1,
        ]);

        $updated = (new UserModel())->find($child);
        $this->assertSame($record['user']->email, $updated->email);
        $this->assertTrue(password_verify('new-password-123', $updated->password_hash));
    }

    public function testLoginAndChildFormExposeAccessiblePasswordToggles(): void
    {
        [$parent, $child] = $this->ids();
        $login = $this->get('/login');
        $login->assertOK();
        $loginHtml = $login->response()->getBody();
        $this->assertStringContainsString('data-password-toggle', $loginHtml);
        $this->assertStringContainsString('aria-controls="password"', $loginHtml);
        $this->assertStringContainsString('fa-eye', $loginHtml);

        $edit = $this->withSession($this->parentSession($parent))->get('/children/' . $child . '/edit');
        $edit->assertOK();
        $html = $edit->response()->getBody();
        $this->assertSame(2, substr_count($html, 'data-password-toggle aria-controls='));
        $this->assertStringContainsString('aria-controls="password_confirm"', $html);
        $this->assertStringContainsString('assets/js/password-toggle.js', $html);
    }

    public function testNewChildReceivesExistingAllChildrenRoutinesAndTasksOnlyOnce(): void
    {
        [$parent, $child] = $this->ids();
        $routines = new RoutineService();
        $groupIds = $routines->createForAllChildren($parent, [
            'child_user_id' => $child,
            'name' => 'Rutin Semua Anak',
            'requires_approval' => 1,
            'is_required' => 1,
            'perfect_day_eligible' => 1,
            'is_active' => 1,
        ], [1, 3, 5]);
        $taskIds = $routines->createTaskForGroup($parent, $groupIds[0], [
            'title' => 'Baca buku', 'points' => 8, 'is_required' => 1, 'is_active' => 1,
            'schedule_type' => 'weekly', 'start_date' => date('Y-m-d'), 'repeat_days' => [1, 3, 5],
        ]);
        $routines->create($parent, [
            'child_user_id' => $child, 'name' => 'Rutin Individu', 'is_active' => 1,
        ], [2]);

        // Repair an existing group gap when the parent next opens the routine list.
        $missingChildId = (int) (new RoutineModel())->find($groupIds[1])['child_user_id'];
        (new RoutineModel())->delete($groupIds[1]);
        $routines->listForParent($parent);
        $repaired = (new RoutineModel())->where('child_user_id', $missingChildId)
            ->where('group_token', (new RoutineModel())->find($groupIds[0])['group_token'])->first();
        $this->assertNotNull($repaired);
        $this->assertCount(1, (new RoutineTaskModel())->where('routine_id', $repaired['id'])->findAll());

        $newChild = (new ChildManagementService())->create($parent, [
            'name' => 'Anak Baru', 'email' => 'anak-baru@example.com', 'password' => 'password-123',
            'date_of_birth' => null, 'is_ranking_eligible' => 1,
        ]);
        $group = (new RoutineModel())->find($groupIds[0]);
        $newRoutine = (new RoutineModel())->where('child_user_id', $newChild)
            ->where('group_token', $group['group_token'])->first();
        $this->assertNotNull($newRoutine);
        $this->assertSame('all', $newRoutine['assignment_scope']);
        $this->assertSame([1, 3, 5], $routines->daysForRoutine((int) $newRoutine['id']));
        $newTasks = (new RoutineTaskModel())->where('routine_id', $newRoutine['id'])->findAll();
        $this->assertCount(1, $newTasks);
        $this->assertSame('Baca buku', $newTasks[0]['title']);
        $this->assertSame((new RoutineTaskModel())->find($taskIds[0])['task_group_token'], $newTasks[0]['task_group_token']);
        $this->assertSame(0, (new RoutineModel())->where('child_user_id', $newChild)->where('name', 'Rutin Individu')->countAllResults());

        $this->assertSame([], $routines->addChildToAllChildrenRoutines($parent, $newChild));
        $this->assertSame(1, (new RoutineModel())->where('child_user_id', $newChild)
            ->where('group_token', $group['group_token'])->countAllResults());
    }
}
