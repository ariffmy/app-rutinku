<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Exceptions\AuthorizationException;
use App\Models\AuditLogModel;
use App\Models\FamilyModel;
use App\Models\UserDeviceModel;
use App\Models\UserModel;
use App\Services\AuditLogService;
use App\Services\ChildDeviceService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class TrustedChildDeviceTest extends CIUnitTestCase
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

    public function testProvisionStoresOnlyTokenHashAndWritesSanitizedAudit(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $provisioned = (new ChildDeviceService())->provision($parentId, $childId, 'Telefon Child One', 'mobile');

        $device = (new UserDeviceModel())->find($provisioned->deviceId);
        $this->assertSame(hash('sha256', $provisioned->rawToken), $device['token_hash']);
        $this->assertNotSame($provisioned->rawToken, $device['token_hash']);
        $this->assertSame(64, strlen($provisioned->rawToken));

        $audit = (new AuditLogModel())->where('action', 'device.provisioned')->first();
        $serializedAudit = json_encode($audit, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($provisioned->rawToken, $serializedAudit);
        $this->assertStringNotContainsString($device['token_hash'], $serializedAudit);
    }

    public function testSecondProvisionRevokesFirstAndLeavesOneActiveDevice(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new ChildDeviceService();
        $first = $service->provision($parentId, $childId, 'First phone', 'mobile');
        $second = $service->provision($parentId, $childId, 'Second phone', 'mobile');

        $devices = new UserDeviceModel();
        $firstRow = $devices->find($first->deviceId);
        $secondRow = $devices->find($second->deviceId);

        $this->assertFalse((bool) $firstRow['is_trusted']);
        $this->assertNotNull($firstRow['revoked_at']);
        $this->assertTrue((bool) $secondRow['is_trusted']);
        $this->assertNull($secondRow['revoked_at']);
        $this->assertSame(1, $devices->where('user_id', $childId)->where('is_trusted', true)->where('revoked_at', null)->countAllResults());
    }

    public function testTrustedCookieCanAccessChildDashboardAndSetsServerIdentity(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $provisioned = (new ChildDeviceService())->provision($parentId, $childId, 'Child phone', 'mobile');
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $provisioned->rawToken);

        $response = $this->get('/child/today?child_id=999999');

        $response->assertOK();
        $response->assertSee('Hai, Child One');
        $response->assertDontSee('Child Two');
        $response->assertDontSee('Kedudukan');
        $response->assertDontSee('Log keluar');
        $this->assertSame($childId, (int) Services::trustedChildContext()->child()->id);

        $device = (new UserDeviceModel())->find($provisioned->deviceId);
        $this->assertNotNull($device['last_used_at']);
    }

    public function testRevokedCookieIsRejectedAndExpired(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new ChildDeviceService();
        $provisioned = $service->provision($parentId, $childId, 'Child phone', 'mobile');
        $this->assertTrue($service->revoke($parentId, $childId, $provisioned->deviceId));
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $provisioned->rawToken);

        $response = $this->get('/child/today');

        $response->assertStatus(401);
        $response->assertSee('Peranti Perlu Disediakan');
        $response->assertCookie(ChildDeviceService::COOKIE_NAME);
        $this->assertLessThan(time(), $response->response()->getCookie(ChildDeviceService::COOKIE_NAME)->getExpiresTimestamp());
        $this->assertFalse(Services::trustedChildContext()->isResolved());
        $this->assertSame(1, (new AuditLogModel())->where('action', 'device.revoked')->countAllResults());
    }

    public function testMalformedOrMissingTokenFailsClosedWithoutParentLoginRedirect(): void
    {
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), 'not-a-valid-device-token');

        $response = $this->get('/child/today');

        $response->assertStatus(401);
        $response->assertSee('Sila minta ibu bapa menyediakan peranti ini.');
        $response->assertDontSee('Log Masuk Ibu bapa');
    }

    public function testChildOrOutsideFamilyParentCannotProvisionDevice(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $service = new ChildDeviceService();

        try {
            $service->provision($childOneId, $childTwoId, 'Forbidden', 'mobile');
            $this->fail('A Child must not provision a device.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $outsideParentId = $this->createOutsideFamilyParent();
        $this->expectException(AuthorizationException::class);
        $service->provision($outsideParentId, $childOneId, 'Outside family', 'mobile');
    }

    public function testDeviceCannotBeRevokedThroughAnotherChildUrl(): void
    {
        [$parentId, $childOneId, $childTwoId] = $this->demoIds(true);
        $service = new ChildDeviceService();
        $device = $service->provision($parentId, $childOneId, 'Child One phone', 'mobile');

        $this->expectException(AuthorizationException::class);
        $service->revoke($parentId, $childTwoId, $device->deviceId);
    }

    public function testResetRevokesAllActiveDevicesAndIsAudited(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $service = new ChildDeviceService();
        $service->provision($parentId, $childId, 'Child phone', 'mobile');

        $this->assertSame(1, $service->reset($parentId, $childId));
        $this->assertSame(0, (new UserDeviceModel())->where('user_id', $childId)->where('is_trusted', true)->where('revoked_at', null)->countAllResults());
        $this->assertSame(1, (new AuditLogModel())->where('action', 'device.reset')->countAllResults());
    }

    public function testAuditServiceDropsSensitiveKeysRecursively(): void
    {
        [$parentId, $childId] = $this->demoIds();
        (new AuditLogService())->record(
            'security.test',
            $parentId,
            $childId,
            'test',
            1,
            'Sanitization test',
            null,
            [
                'safe' => 'retained',
                'raw_token' => 'must-not-appear',
                'nested' => ['password_hash' => 'must-not-appear-either'],
            ],
        );

        $audit = (new AuditLogModel())->where('action', 'security.test')->first();
        $this->assertStringContainsString('retained', $audit['new_values']);
        $this->assertStringNotContainsString('must-not-appear', $audit['new_values']);
        $this->assertStringNotContainsString('password', $audit['new_values']);
    }

    public function testDeviceCookieIsPersistentHttpOnlyAndSameSiteLax(): void
    {
        $response = service('response');
        (new ChildDeviceService())->attachCookie($response, str_repeat('a', 64));
        $cookie = $response->getCookie(ChildDeviceService::COOKIE_NAME);

        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertSame('Lax', $cookie->getSameSite());
        $this->assertGreaterThan(Time::now()->getTimestamp() + 15_000_000, $cookie->getExpiresTimestamp());
        $this->assertSame(config(\Config\Cookie::class)->secure, $cookie->isSecure());
    }

    public function testParentSetupActionSetsCookieDestroysSessionAndRedirectsToChildMode(): void
    {
        [$parentId, $childId] = $this->demoIds();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $security = service('security');
        $tokenName = $security->getTokenName();
        $csrfHash = $security->getHash();

        $response = $this->withSession([
            'user_id' => $parentId,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
            $tokenName => $csrfHash,
        ])->post('/children/' . $childId . '/devices/setup', [
            $tokenName => $csrfHash,
            'device_name' => 'Action test phone',
        ]);

        $response->assertRedirectTo('/child/today');
        $response->assertCookie(ChildDeviceService::COOKIE_NAME);
        $this->assertNull(service('session')->get('user_id'));
        $this->assertSame(1, (new UserDeviceModel())->where('user_id', $childId)->where('is_trusted', true)->countAllResults());
    }

    private function demoIds(bool $includeSecondChild = false): array
    {
        $users = new UserModel();
        $parent = $users->where('email', 'parent1@example.com')->first();
        $childOne = $users->where('username', 'child-one-internal')->first();

        if (! $includeSecondChild) {
            return [(int) $parent->id, (int) $childOne->id];
        }

        $childTwo = $users->where('username', 'child-two-internal')->first();

        return [(int) $parent->id, (int) $childOne->id, (int) $childTwo->id];
    }

    private function createOutsideFamilyParent(): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('families')->insert(['name' => 'Outside Family', 'created_at' => $now, 'updated_at' => $now]);
        $familyId = (int) $this->db->insertID();
        $this->db->table('users')->insert([
            'name' => 'Outside Parent',
            'email' => 'outside-device@example.com',
            'username' => null,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'parent',
            'is_active' => true,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentId = (int) $this->db->insertID();
        $this->db->table('family_users')->insert([
            'family_id' => $familyId,
            'user_id' => $parentId,
            'created_at' => $now,
        ]);

        return $parentId;
    }
}
