<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\FamilyModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\FamilyAuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class PhaseOneAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $seed = DemoSeeder::class;
    protected $refresh = true;

    public function testBothParentsCanAccessTheSameFamilyDashboard(): void
    {
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();

        foreach (['parent1@example.com', 'parent2@example.com'] as $email) {
            $parent = (new UserModel())->where('email', $email)->first();
            $response = $this->withSession($this->parentSession((int) $parent->id, (int) $family['id']))
                ->get('/dashboard');

            $response->assertOK();
            $response->assertSee('Demo Family');
            $response->assertSee('Child One');
            $response->assertSee('Child Two');
            $response->assertSee('Child Three');
        }
    }

    public function testUnauthenticatedVisitorCannotAccessParentDashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirectTo('/login');
    }

    public function testChildSessionCannotAccessParentDashboard(): void
    {
        $child = (new UserModel())->where('username', 'child-one-internal')->first();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();

        $response = $this->withSession([
            'user_id' => (int) $child->id,
            'user_role' => 'child',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
        ])->get('/dashboard');

        $response->assertRedirectTo('/login');
    }

    public function testUnsafeChildRoutesRemainUnavailableAndRankingIsGetOnly(): void
    {
        $routes = service('routes')->loadRoutes();

        foreach (['child/ranking', 'child/siblings', 'child/logout', 'register'] as $path) {
            $this->assertArrayNotHasKey($path, $routes->getRoutes('GET'));
            $this->assertArrayNotHasKey($path, $routes->getRoutes('POST'));
        }
        $this->assertArrayHasKey('ranking', $routes->getRoutes('GET'));
        $this->assertArrayNotHasKey('ranking', $routes->getRoutes('POST'));
    }

    public function testChildDashboardFailsClosedUntilDevicePhase(): void
    {
        $response = $this->get('/child/today');

        $response->assertStatus(401);
        $response->assertSee('Peranti Perlu Disediakan');
        $response->assertDontSee('Log keluar');
        $response->assertDontSee('Switch User');
    }

    public function testParentLoginUsesPasswordHashAndBuildsValidatedSession(): void
    {
        $auth = new AuthService();

        $this->assertFalse($auth->loginParent('parent1@example.com', 'wrong-password'));
        $this->assertTrue($auth->loginParent('parent1@example.com', 'password'));
        $this->assertTrue($auth->isParent());
        $this->assertSame('Demo Family', $auth->currentFamily()['name']);
    }

    public function testParentOutsideFamilyCannotManageChild(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('families')->insert(['name' => 'Other Family', 'created_at' => $now, 'updated_at' => $now]);
        $otherFamilyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Parent',
            'email' => 'outside@example.com',
            'username' => null,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'parent',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $outsideParentId = (int) $this->db->insertID();
        $this->db->table('family_users')->insert([
            'family_id' => $otherFamilyId,
            'user_id' => $outsideParentId,
            'created_at' => $now,
        ]);

        $child = (new UserModel())->where('username', 'child-one-internal')->first();
        $authorization = new FamilyAuthorizationService();

        $this->assertFalse($authorization->parentCanManageChild($outsideParentId, (int) $child->id));

        $parent = (new UserModel())->where('email', 'parent1@example.com')->first();
        $this->assertTrue($authorization->parentCanManageChild((int) $parent->id, (int) $child->id));
    }

    private function parentSession(int $userId, int $familyId): array
    {
        return [
            'user_id' => $userId,
            'user_role' => 'parent',
            'family_id' => $familyId,
            'auth_expires_at' => time() + 3600,
        ];
    }
}
