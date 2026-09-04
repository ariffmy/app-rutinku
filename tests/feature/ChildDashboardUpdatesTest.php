<?php
namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\UserModel;
use App\Models\ChildProfileModel;
use App\Services\ChildDeviceService;
use App\Services\RoutineService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ChildDashboardUpdatesTest extends CIUnitTestCase
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
    private function loginChild(): array
    {
        $parent = (new UserModel())->where('email', 'parent1@example.com')->first();
        $child = (new UserModel())->where('username', 'child-one-internal')->first();
        $device = (new ChildDeviceService())->provision((int) $parent->id, (int) $child->id);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        return [(int) $parent->id, (int) $child->id];
    }
    private function postChild(string $url, array $data = [], bool $ajax = false)
    {
        $security = service('security');
        $this->withSession([$security->getTokenName() => $security->getHash()]);
        $this->withHeaders($ajax ? ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'] : []);
        return $this->post($url, $data + [$security->getTokenName() => $security->getHash()]);
    }
    public function testAjaxCompleteUndoAndDuplicateDoNotInflatePoints(): void
    {
        [$parent, $child] = $this->loginChild();
        $routines = new RoutineService();
        $routine = $routines->create($parent, ['child_user_id' => $child, 'name' => 'Pagi', 'is_active' => 1], [1,2,3,4,5,6,7]);
        $task = $routines->createTask($parent, $routine, ['title' => 'Mandi', 'points' => 10, 'is_required' => 1, 'is_active' => 1]);
        $response = $this->postChild('/child/tasks/' . $task . '/complete', [], true);
        $response->assertOK();
        $data = json_decode($response->response()->getBody(), true);
        $this->assertTrue($data['completed']);
        $this->assertSame(10, $data['balance']);
        $this->assertNotEmpty($data['csrf']);
        $this->postChild('/child/tasks/' . $task . '/complete', [], true)->assertStatus(422);
        $undo = $this->postChild('/child/tasks/' . $task . '/undo', [], true);
        $undo->assertOK();
        $this->assertSame(0, json_decode($undo->response()->getBody(), true)['balance']);
        $again = $this->postChild('/child/tasks/' . $task . '/complete', [], true);
        $again->assertOK();
        $this->assertSame(10, json_decode($again->response()->getBody(), true)['balance']);
    }
    public function testProfileAvatarAndTodayHeader(): void
    {
        [, $child] = $this->loginChild();
        $this->postChild('/child/profile', ['avatar' => 'robot'])->assertRedirectTo('/child/profile');
        $this->assertSame('robot', (new ChildProfileModel())->where('user_id', $child)->first()['avatar']);
        $profile = $this->get('/child/profile');
        $profile->assertOK();
        $profile->assertSee('Umur');
        $profile->assertSee('Ibu bapa');
        $profile->assertDontSee('Hari Berturut-turut');
        $this->assertStringNotContainsString('<dt class="col-5">Peranti</dt>', $profile->response()->getBody());
        $this->postChild('/child/profile', ['avatar' => '../outside.jpg'])->assertRedirectTo('/child/profile');
        $this->assertSame('robot', (new ChildProfileModel())->where('user_id', $child)->first()['avatar']);
        $today = $this->get('/child/today');
        $today->assertOK();
        $today->assertSee('Pasti ke sudah?');
        $today->assertSee('Sudah selesai');
        $today->assertDontSee('0 daripada 0');
        $today->assertDontSee('hari berturut-turut');
    }
    public function testFamilyImageIsPrivateAndRequiresTrustedDevice(): void
    {
        [, $child] = $this->loginChild();
        $family = (new \App\Services\FamilyService())->currentFamilyForUser($child);
        $directory = WRITEPATH . 'uploads/family-' . $family['id'];
        $createdDirectory = ! is_dir($directory);
        if ($createdDirectory) mkdir($directory, 0700, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        $pixels = imagecreatetruecolor(2, 2);
        imagejpeg($pixels, $directory . '/' . $name);
        try {
            $response = $this->get('/child/images/' . $name);
            $response->assertOK();
            $this->assertStringContainsString('image/jpeg', $response->response()->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
            service('superglobals')->setCookieArray([]);
            $this->get('/child/images/' . $name)->assertStatus(401);
        } finally {
            unlink($directory . '/' . $name);
            if ($createdDirectory) rmdir($directory);
        }
    }

    public function testRewardCategoryPersistsAndImagePathCannotBeInjected(): void
    {
        [$parent] = $this->loginChild();
        $rewards = new \App\Services\RewardService();
        $id = $rewards->create($parent, ['title' => 'Buku', 'category' => 'Hadiah', 'points_required' => 30, 'is_active' => 1]);
        $this->assertSame('Hadiah', $rewards->getForParent($parent, $id)['category']);
        $this->assertNull(ui_image_url('https://example.com/image.jpg'));
        $this->assertNull(ui_image_url('../secrets.jpg'));
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('/child/images/' . str_repeat('a', 32) . '.jpg');
    }
}
