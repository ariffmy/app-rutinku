<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Models\FamilyModel;
use App\Models\UserModel;
use App\Services\ChildDeviceService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Security;
use Config\Services;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;

final class ParentNavigationTest extends CIUnitTestCase
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

    #[DataProvider('parentPages')]
    public function testParentNavigationHasAccessibleControlsAndOneActiveSection(string $path, string $activeLabel): void
    {
        $parent = (new UserModel())->where('email', 'parent1@example.com')->first();
        $family = (new FamilyModel())->where('name', 'Demo Family')->first();
        $page = $this->withSession([
            'user_id' => (int) $parent->id,
            'user_role' => 'parent',
            'family_id' => (int) $family['id'],
            'auth_expires_at' => time() + 3600,
        ])->get($path);
        $page->assertOK();
        $html = $page->response()->getBody();
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $this->assertSame(1, $xpath->query('//nav[@aria-label="Navigasi Ibu bapa"]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-parent-menu-toggle][@aria-controls="parent-nav-panel"][@aria-expanded="false"][@hidden]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="parent-nav-panel"][not(@hidden)]')->length, 'Without JavaScript the links must remain usable.');
        $this->assertSame(7, $xpath->query('//ul[@class="parent-nav-links"]/li/a')->length);
        $activeLinks = $xpath->query('//nav//a[@aria-current="page"]');
        $this->assertSame(1, $activeLinks->length);
        $this->assertSame($activeLabel, $activeLinks->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//div[@id="parent-nav-panel"]//form[@method="post"]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="parent-nav-panel"]//input[@name="' . config(Security::class)->tokenName . '"]')->length);
        $this->assertStringNotContainsString('onclick=', $html);

    }

    public static function parentPages(): array
    {
        return [
            ['/dashboard', 'Papan Pemuka'],
            ['/children', 'Anak-anak'],
            ['/children/new', 'Anak-anak'],
            ['/routines', 'Rutin'],
            ['/points', 'Mata'],
            ['/rewards', 'Ganjaran'],
            ['/ranking', 'Kedudukan'],
            ['/reports', 'Laporan'],
        ];
    }

    public function testChildNavigationDoesNotReceiveParentMenu(): void
    {
        $users = new UserModel();
        $parent = $users->where('email', 'parent1@example.com')->first();
        $child = $users->where('username', 'child-one-internal')->first();
        $device = (new ChildDeviceService())->provision((int) $parent->id, (int) $child->id);
        service('superglobals')->setCookie(ChildDeviceService::requestCookieName(), $device->rawToken);
        $page = $this->get('/child/today');
        $page->assertOK();
        $this->assertStringNotContainsString('data-parent-nav', $page->response()->getBody());
        $this->assertStringContainsString('child-nav', $page->response()->getBody());
    }

    public function testUpdatedNavigationAssetsUseNewPwaCacheVersion(): void
    {
        $worker = file_get_contents(ROOTPATH . 'public/service-worker.js');
        $this->assertStringContainsString('rutinku-static-v4', $worker);
        $this->assertStringContainsString('/assets/css/app.css', $worker);
        $this->assertStringContainsString('/assets/js/app.js', $worker);
    }
}
