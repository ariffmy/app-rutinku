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
        if ($path === '/dashboard') {
            $buttons = $xpath->query('//main//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]');
            $this->assertGreaterThanOrEqual(1, $buttons->length);
            $this->assertSame(1, $xpath->query('//section[@aria-labelledby="children-heading"]')->length);
            $this->assertGreaterThanOrEqual(1, $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " parent-child-card ")]')->length);
            $this->assertSame(2, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " parent-summary-grid ")]/div[contains(concat(" ", normalize-space(@class), " "), " card ")]')->length);
            $this->assertSame(1, $xpath->query('//section[@aria-labelledby="activity-heading"]')->length);
            $this->assertStringNotContainsString('<th>Penyelesaian</th>', $html);
            $this->assertStringNotContainsString('<th>Hari Berturut-turut</th>', $html);
            $this->assertStringNotContainsString('Tugasan Hari Ini', $html);
            foreach ($buttons as $button) {
                $this->assertStringContainsString(' btn-primary ', ' ' . $button->getAttribute('class') . ' ');
                $this->assertStringNotContainsString('btn-outline', $button->getAttribute('class'));
                $this->assertStringNotContainsString('btn-sm', $button->getAttribute('class'));
            }
        }

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
        $this->assertStringContainsString('rutinku-static-v17', $worker);
        $this->assertStringContainsString('/assets/css/app.css', $worker);
        $this->assertStringContainsString('/assets/js/app.js', $worker);
    }

    public function testSharedGoldenThemeAcrossAppShells(): void
    {
        $css = file_get_contents(ROOTPATH . 'public/assets/css/app.css');
        $this->assertStringContainsString('--rutinku-primary: #ffbc0b', $css);
        $css = file_get_contents(ROOTPATH . 'public/assets/css/buttons.css');
        $this->assertStringContainsString('--bs-btn-color: #302300', $css);
        $this->assertStringContainsString('--bs-btn-hover-bg: #e9aa00', $css);
        $this->assertStringContainsString('--bs-btn-disabled-bg: #d5dce7', $css);
        foreach (['layouts/parent.php', 'layouts/child.php', 'auth/login.php', 'child/device_setup_required.php'] as $file) {
            $html = file_get_contents(APPPATH . 'Views/' . $file);
            $this->assertStringContainsString('content="#ffbc0b"', $html);
            $this->assertStringContainsString('assets/css/app.css', $html);
            $this->assertStringContainsString('assets/css/buttons.css', $html);
        }
        $manifest = json_decode(file_get_contents(ROOTPATH . 'public/manifest.webmanifest'), true);
        $this->assertSame('#ffbc0b', $manifest['theme_color']);
    }

    public function testButtonGeometryHasOneSharedDefinition(): void
    {
        $buttons = file_get_contents(ROOTPATH . 'public/assets/css/buttons.css');
        foreach (['min-height: 50px', 'display: inline-flex', 'align-items: center', 'justify-content: center', '--bs-btn-font-size: 1rem', '--bs-btn-border-radius: 30px'] as $rule) {
            $this->assertStringContainsString($rule, $buttons);
        }
        $this->assertStringNotContainsString('.task-save .btn', file_get_contents(ROOTPATH . 'public/assets/css/task-form.css'));
        $this->assertStringContainsString('/assets/css/buttons.css', file_get_contents(ROOTPATH . 'public/service-worker.js'));
        $this->assertStringContainsString('/assets/css/buttons.css', file_get_contents(ROOTPATH . 'public/offline.html'));
    }

    public function testTaskEditorUsesFullParentContainerWidth(): void
    {
        $css = file_get_contents(ROOTPATH . 'public/assets/css/task-form.css');
        $this->assertStringContainsString('.task-editor{width:100%;max-width:none;margin:0}', $css);
        $this->assertStringNotContainsString('max-width:620px', $css);
        $this->assertStringContainsString("extend('layouts/parent')", file_get_contents(APPPATH . 'Views/parent/routine_tasks/form.php'));
        $this->assertStringContainsString("extend('layouts/parent')", file_get_contents(APPPATH . 'Views/parent/routines/form.php'));
    }

    public function testRemovedFieldsAndFontAwesomeAssets(): void
    {
        foreach (['parent/routines/form.php' => ['description', 'type', 'sort_order'], 'parent/routine_tasks/form.php' => ['description', 'sort_order']] as $file => $fields) {
            $html = file_get_contents(APPPATH . 'Views/' . $file);
            foreach ($fields as $field) {
                $this->assertStringNotContainsString('name="' . $field . '"', $html);
            }
        }
        foreach (['layouts/parent.php', 'layouts/child.php'] as $file) {
            $this->assertStringContainsString('fontawesome/css/solid.min.css', file_get_contents(APPPATH . 'Views/' . $file));
        }
        $this->assertFileExists(ROOTPATH . 'public/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2');
        helper('ui');
        $this->assertStringContainsString('fa-solid fa-star', ui_icon('star'));
        $this->assertSame('', ui_icon('invalid'));
    }
}
