<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Models\AuditLogModel;
use App\Models\FamilyModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use App\Services\ChildManagementService;
use App\Services\PointService;
use App\Services\ReportService;
use App\Services\RoutineService;
use App\Services\TaskCompletionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Cookie;
use Config\Exceptions;
use Config\Security;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @internal
 */
final class PhaseNineToElevenTest extends CIUnitTestCase
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

    public function testDailyReportIncludesAllActiveChildrenAndCorrectMetrics(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds();
        $today = $this->today();
        $routineId = $this->createRoutine($parentId, $childOneId, [(int) $today->format('N')]);
        $requiredTask = $this->createTask($parentId, $routineId, 'Required report task', 10, true);
        $this->createTask($parentId, $routineId, 'Optional report task', 5, false);
        (new TaskCompletionService())->completeTask($childOneId, $requiredTask, $today);
        (new PointService())->manualAdjustment($parentId, $childTwoId, 100, 'Excluded report adjustment', $today);
        $this->db->table('child_profiles')->where('user_id', $childTwoId)->update(['is_ranking_eligible' => 0]);

        $report = (new ReportService())->daily($parentId, $today);
        $rows = array_column($report['rows'], null, 'child_user_id');

        $this->assertArrayHasKey($childTwoId, $rows, 'Reports include active children even when ranking-ineligible.');
        $this->assertSame(2, $report['total_scheduled_tasks']);
        $this->assertSame(1, $report['total_completed_tasks']);
        $this->assertSame(50, $report['completion_percentage']);
        $this->assertSame(10, $report['total_earned_points']);
        $this->assertSame(1, $report['total_perfect_days']);
        $this->assertSame(10, $rows[$childOneId]['earned_points']);
        $this->assertSame(0, $rows[$childTwoId]['earned_points']);
        $this->assertSame(100, $rows[$childTwoId]['current_balance']);
    }

    public function testWeeklyMonthlyAndFutureReportBoundaries(): void
    {
        [$parentId] = $this->demoIds();
        $reports = new ReportService();
        $reference = new DateTimeImmutable('2026-09-02', new DateTimeZone(app_timezone()));

        $weekly = $reports->weekly($parentId, $reference);
        $monthly = $reports->monthly($parentId, $reference);
        $future = $reports->monthly($parentId, new DateTimeImmutable('2099-02-12', new DateTimeZone(app_timezone())));

        $this->assertSame('2026-08-31', $weekly['start_date']);
        $this->assertSame('2026-09-06', $weekly['end_date']);
        $this->assertSame('2026-09-01', $monthly['start_date']);
        $this->assertSame('2026-09-30', $monthly['end_date']);
        $this->assertSame('2099-02-01', $future['start_date']);
        $this->assertSame('2099-02-28', $future['end_date']);
        $this->assertTrue($future['is_future']);
        $this->assertNull($future['calculation_end']);
        $this->assertSame([], $future['daily_breakdown']);
    }

    public function testOutsideParentCannotReadFamilyReport(): void
    {
        $outsideParentId = $this->createOutsideParent();
        $outsideReport = (new ReportService())->daily($outsideParentId, $this->today());

        $this->assertSame('Outside Report Family', $outsideReport['family']['name']);
        $this->assertSame([], $outsideReport['rows']);

        $this->expectException(AuthorizationException::class);
        (new ReportService())->daily($outsideParentId + 100000, $this->today());
    }

    public function testReportsAreParentOnlyAndEscapeFamilyContent(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $this->db->table('users')->where('id', $childId)->update(['name' => '<script>reportAttack()</script>']);

        $page = $this->withSession($this->parentSession($parentId))->get('/reports?period=daily&date=' . $this->today()->format('Y-m-d'));

        $page->assertOK();
        $page->assertSee('Laporan progress keluarga');
        $this->assertStringNotContainsString('<script>reportAttack()</script>', $page->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;reportAttack()&lt;/script&gt;', $page->response()->getBody());
        service('session')->destroy();
        $this->withSession([])->get('/reports')->assertRedirectTo('/login');
        $routes = service('routes')->loadRoutes();
        $this->assertArrayHasKey('reports', $routes->getRoutes('GET'));
        $this->assertArrayNotHasKey('child/reports', $routes->getRoutes('GET'));
    }

    public function testParentCanCreateAndUpdateChildWhileTrustedChildProfileStaysPrivate(): void
    {
        [$parentId, , $siblingId] = $this->demoIds();
        $children = new ChildManagementService();
        $childId = $children->create($parentId, [
            'name' => '<New Child>',
            'date_of_birth' => '2017-05-04',
            'is_ranking_eligible' => 1,
        ]);
        $children->update($parentId, $childId, [
            'name' => 'Private Child',
            'date_of_birth' => '2017-05-04',
            'is_ranking_eligible' => 0,
            'is_active' => 1,
        ]);

        $record = $children->getForParent($parentId, $childId);
        $this->assertSame('Private Child', $record['user']->name);
        $this->assertSame('2017-05-04', $record['profile']['date_of_birth']);
        $this->assertSame(0, (int) $record['profile']['is_ranking_eligible']);
        $this->assertSame(1, (new AuditLogModel())->where('action', 'child.created')->countAllResults());
        $this->assertSame(1, (new AuditLogModel())->where('action', 'child.profile_updated')->countAllResults());

        $device = (new ChildDeviceService())->provision($parentId, $childId, 'Private phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $profilePage = $this->get('/child/profile?child_user_id=' . $siblingId);
        $profilePage->assertOK();
        $profilePage->assertSee('Private Child');
        $profilePage->assertDontSee('Child Two');
        $this->assertStringNotContainsString((string) $record['user']->username, $profilePage->response()->getBody());
        $this->assertArrayNotHasKey('child/logout', service('routes')->loadRoutes()->getRoutes('POST'));
        $this->assertArrayNotHasKey('child/register', service('routes')->loadRoutes()->getRoutes('POST'));
    }

    public function testPwaManifestIconsAndCachePolicyAreInstallableAndPrivateSafe(): void
    {
        $manifestPath = ROOTPATH . 'public/manifest.webmanifest';
        $workerPath = ROOTPATH . 'public/service-worker.js';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $worker = (string) file_get_contents($workerPath);

        $this->assertSame('RutinKu', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertCount(2, $manifest['icons']);
        $this->assertFileExists(ROOTPATH . 'public/offline.html');
        $this->assertFileExists(ROOTPATH . 'public/assets/js/app.js');
        $this->assertFileExists(ROOTPATH . 'public/assets/vendor/bootstrap-5.3.3.min.css');
        $this->assertSame([192, 192], array_slice(getimagesize(ROOTPATH . 'public/assets/icons/icon-192.png'), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(ROOTPATH . 'public/assets/icons/icon-512.png'), 0, 2));
        $this->assertSame([180, 180], array_slice(getimagesize(ROOTPATH . 'public/assets/icons/apple-touch-icon.png'), 0, 2));
        $this->assertStringContainsString("fetch(request, { cache: 'no-store' })", $worker);
        $this->assertStringContainsString("caches.match('/offline.html')", $worker);
        $this->assertStringContainsString('/assets/vendor/bootstrap-5.3.3.min.css', $worker);
        $this->assertStringNotContainsString('cache.put', $worker);
        $this->assertStringNotContainsString('localStorage', (string) file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        $this->assertStringNotContainsString('sessionStorage', (string) file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
    }

    public function testDynamicResponsesAndProductionConfigurationHaveSecurityGuardrails(): void
    {
        [$parentId] = $this->demoIds();
        $response = $this->withSession($this->parentSession($parentId))->get('/dashboard')->response();
        $security = config(Security::class);
        $cookie = config(Cookie::class);
        $exceptions = config(Exceptions::class);

        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('Cookie', $response->getHeaderLine('Vary'));
        $this->assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('session', $security->csrfProtection);
        $this->assertTrue($security->regenerate);
        $this->assertTrue($cookie->httponly);
        $this->assertSame('Lax', $cookie->samesite);
        $this->assertContains('token_hash', $exceptions->sensitiveDataInTrace);
        $this->assertStringContainsString('Require all denied', (string) file_get_contents(ROOTPATH . '.htaccess'));
        $this->assertStringContainsString('app.forceGlobalSecureRequests = true', (string) file_get_contents(ROOTPATH . '.env.example'));
        $this->assertFileExists(ROOTPATH . 'docs/CPANEL_DEPLOYMENT.md');

        foreach ([
            ROOTPATH . 'app/Views/auth/login.php',
            ROOTPATH . 'app/Views/layouts/parent.php',
            ROOTPATH . 'app/Views/layouts/child.php',
            ROOTPATH . 'app/Views/child/device_setup_required.php',
        ] as $view) {
            $this->assertStringNotContainsString('cdn.jsdelivr.net', (string) file_get_contents($view));
        }
    }

    public function testFailedLoginNeverPersistsPasswordInOldInput(): void
    {
        $security = service('security');
        $tokenName = $security->getTokenName();
        $csrfHash = $security->getHash();

        $response = $this->withSession([$tokenName => $csrfHash])->post('/login', [
            $tokenName => $csrfHash,
            'email' => 'parent1@example.com',
            'password' => 'plaintext-must-not-be-stored',
            'remember_me' => '1',
        ]);

        $response->assertRedirect();
        $oldInput = service('session')->getFlashdata('_ci_old_input');
        $this->assertSame('parent1@example.com', $oldInput['post']['email']);
        $this->assertSame('1', $oldInput['post']['remember_me']);
        $this->assertArrayNotHasKey('password', $oldInput['post']);
    }

    public function testDeactivationPermanentlyRevokesOldChildDeviceAfterReactivation(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $devices = new ChildDeviceService();
        $children = new ChildManagementService();
        $provisioned = $devices->provision($parentId, $childId, 'Retired phone', 'mobile');

        $children->update($parentId, $childId, [
            'name' => 'Child One',
            'date_of_birth' => '',
            'is_ranking_eligible' => 1,
            'is_active' => 0,
        ]);
        $children->update($parentId, $childId, [
            'name' => 'Child One',
            'date_of_birth' => '',
            'is_ranking_eligible' => 1,
            'is_active' => 1,
        ]);

        $context = Services::trustedChildContext();
        $this->assertFalse($devices->resolveIntoContext($provisioned->rawToken, $context));
        $device = $this->db->table('user_devices')->where('id', $provisioned->deviceId)->get()->getRowArray();
        $this->assertSame(0, (int) $device['is_trusted']);
        $this->assertNotNull($device['revoked_at']);
        $this->assertSame(1, (new AuditLogModel())->where('action', 'device.revoked_on_child_deactivation')->countAllResults());
    }

    public function testExpiredChildDeviceTokenFailsClosedAndIsRevoked(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $devices = new ChildDeviceService();
        $provisioned = $devices->provision($parentId, $childId, 'Expired phone', 'mobile');
        $this->db->table('user_devices')->where('id', $provisioned->deviceId)->update([
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $this->assertFalse($devices->resolveIntoContext($provisioned->rawToken, Services::trustedChildContext()));
        $device = $this->db->table('user_devices')->where('id', $provisioned->deviceId)->get()->getRowArray();
        $this->assertSame(0, (int) $device['is_trusted']);
        $this->assertNotNull($device['revoked_at']);
    }

    private function createRoutine(int $parentId, int $childId, array $days): int
    {
        return (new RoutineService())->create($parentId, [
            'child_user_id' => $childId,
            'name' => 'Report Routine ' . bin2hex(random_bytes(3)),
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

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
    }

    private function demoIds(): array
    {
        $users = new UserModel();
        $parent = $users->where('email', 'parent1@example.com')->first();
        $childOne = $users->where('username', 'child-one-internal')->first();
        $childTwo = $users->where('username', 'child-two-internal')->first();

        return [(int) $parent->id, (int) $childOne->id, (int) $childTwo->id];
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

    private function createOutsideParent(): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('families')->insert(['name' => 'Outside Report Family', 'created_at' => $now, 'updated_at' => $now]);
        $familyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Report Parent',
            'email' => 'outside-report@example.com',
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
