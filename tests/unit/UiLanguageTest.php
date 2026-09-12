<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class UiLanguageTest extends CIUnitTestCase
{
    public function testTransactionBadgeColours(): void
    {
        foreach (['task' => 'success', 'reversal' => 'danger', 'bonus' => 'warning', 'reward' => 'info', 'adjustment' => 'secondary', 'unknown' => 'secondary'] as $type => $colour) {
            $this->assertSame('text-bg-' . $colour, ui_transaction_badge(['type' => $type, 'points' => 5]));
        }
        $this->assertSame('text-bg-danger', ui_transaction_badge(['type' => 'adjustment', 'points' => -5]));
    }

    public function testChildAssetsHaveContentVersions(): void
    {
        $path = 'assets/js/child-today.js';
        $this->assertStringEndsWith('?v=' . substr(hash_file('sha256', FCPATH . $path), 0, 12), ui_asset_url($path));
        $view = file_get_contents(APPPATH . 'Views/child/today.php');
        $this->assertStringContainsString("ui_asset_url('assets/js/child-today.js')", $view);
        $this->assertStringNotContainsString('data-filter-clock', $view);
        $this->assertStringNotContainsString('Tugasan dalam satu jam', $view);
        $script = file_get_contents(FCPATH . $path);
        $this->assertStringNotContainsString('withinWindow', $script);
        $this->assertStringContainsString('task.hidden = false', $script);
    }

    public function testChildDashboardUsesClockIconAndCompactSummaryCards(): void
    {
        $today = file_get_contents(APPPATH . 'Views/child/today.php');
        $dailyProgress = file_get_contents(APPPATH . 'Views/child/partials/daily_progress.php');
        $rewardGoal = file_get_contents(APPPATH . 'Views/child/partials/reward_goal.php');
        $css = file_get_contents(FCPATH . 'assets/css/app.css');

        $this->assertStringContainsString("ui_icon('clock', 'Waktu:')", $today);
        $this->assertStringContainsString('child-summary-card', $dailyProgress);
        $this->assertStringContainsString('child-summary-card', $rewardGoal);
        $this->assertStringContainsString('.card.child-summary-card > .card-body', $css);
        $this->assertStringContainsString('.child-summary-progress', $css);
    }

    public function testChildAchievementCardsUseCompactLayout(): void
    {
        $view = file_get_contents(APPPATH . 'Views/child/achievements.php');
        $css = file_get_contents(FCPATH . 'assets/css/app.css');

        $this->assertStringContainsString('card child-compact-card achievement-card', $view);
        $this->assertStringContainsString('achievement-icon', $view);
        $this->assertStringContainsString('class="h6 mb-0"', $view);
        $this->assertStringContainsString('.card.child-compact-card > .card-body', $css);
    }

    public function testChildProgressCardsShareTheCompactAchievementLayout(): void
    {
        $view = file_get_contents(APPPATH . 'Views/child/progress.php');

        $this->assertGreaterThanOrEqual(5, substr_count($view, 'child-compact-card'));
        $this->assertStringContainsString('class="row g-2"', $view);
        $this->assertStringContainsString('child-compact-list-item', $view);
        $this->assertStringNotContainsString('display-5', $view);
        $this->assertStringNotContainsString('card-body p-4', $view);
    }

    public function testChildRewardCardsShareTheCompactAchievementLayout(): void
    {
        $view = file_get_contents(APPPATH . 'Views/child/rewards.php');
        $css = file_get_contents(FCPATH . 'assets/css/app.css');

        $this->assertStringContainsString('card child-compact-card child-reward-card reward-card', $view);
        $this->assertStringContainsString('class="row g-2"', $view);
        $this->assertStringNotContainsString('card-body p-4', $view);
        $this->assertStringContainsString('.child-reward-card .reward-card-image', $css);
        $this->assertStringContainsString('.child-reward-card .btn', $css);
    }

    public function testCardsUseOneSharedPaddingValue(): void
    {
        $css = file_get_contents(FCPATH . 'assets/css/app.css');
        $this->assertStringContainsString('--rutinku-card-padding: 1rem', $css);
        $this->assertStringContainsString('padding: var(--rutinku-card-padding) !important', $css);
        $this->assertStringContainsString('.card > .table-responsive .table > :not(caption) > * > *', $css);
        $this->assertStringNotContainsString('.child-task-card > .card-body { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding:', $css);
        $this->assertStringNotContainsString('.parent-summary-grid .card-body { padding:', $css);
    }

    public function testLoginUsesCardlessPwaLayout(): void
    {
        $view = file_get_contents(APPPATH . 'Views/auth/login.php');
        $this->assertStringContainsString('class="auth-content mx-auto"', $view);
        $this->assertStringNotContainsString('auth-card card', $view);
        $this->assertStringNotContainsString('card-body', $view);
        $this->assertStringContainsString('Ibu bapa atau Anak', $view);
    }

    public function testChildAccountFormCollectsCredentialsWithoutRepopulatingPasswords(): void
    {
        $view = file_get_contents(APPPATH . 'Views/parent/children/form.php');
        $controller = file_get_contents(APPPATH . 'Controllers/Parent/ChildController.php');
        $this->assertStringContainsString('name="email" type="email"', $view);
        $this->assertStringContainsString('name="password" type="password"', $view);
        $this->assertStringContainsString('name="password_confirm" type="password"', $view);
        $this->assertStringContainsString("unset(\$post['password'], \$post['password_confirm']", $controller);
        $this->assertStringNotContainsString('redirect()->back()->withInput()', $controller);
    }

    public function testRewardFormUsesControlledMetadataAndImagePreview(): void
    {
        $view = file_get_contents(APPPATH . 'Views/parent/rewards/form.php');
        $csp = new \Config\ContentSecurityPolicy();
        $this->assertStringContainsString('<select id="category"', $view);
        $this->assertStringNotContainsString('<datalist', $view);
        $this->assertStringContainsString('<textarea id="description"', $view);
        $this->assertStringContainsString('<select id="redemption_limit"', $view);
        $this->assertStringContainsString('data-reward-image-preview', $view);
        $this->assertFileExists(FCPATH . 'assets/js/reward-form.js');
        $this->assertContains('blob:', $csp->imageSrc);
    }

    public function testRewardCatalogueStatusBadgeDoesNotStretchWithLongTitles(): void
    {
        $view = file_get_contents(APPPATH . 'Views/parent/rewards/index.php');

        $this->assertStringContainsString('badge align-self-start flex-shrink-0', $view);
    }

    public function testParentAndChildRewardCardsShowTheCompleteImage(): void
    {
        $parentView = file_get_contents(APPPATH . 'Views/parent/rewards/index.php');
        $childView = file_get_contents(APPPATH . 'Views/child/rewards.php');
        $css = file_get_contents(FCPATH . 'assets/css/app.css');

        $this->assertStringContainsString('class="reward-card-image"', $parentView);
        $this->assertStringContainsString('class="reward-card-image"', $childView);
        $this->assertStringContainsString('.reward-card-image { display: block; object-fit: contain;', $css);
    }

    public function testMalaysianDatesAndTimes(): void
    {
        $this->assertSame('04/09/2026', ui_date('2026-09-04'));
        $this->assertSame('—', ui_date('2026-02-30'));
        $this->assertSame('—', ui_date(null));
        $this->assertSame('04/09/2026, 8 pagi', ui_datetime('2026-09-04 08:00:00'));
        $this->assertSame('04/09/2026, 8:30 malam', ui_datetime('2026-09-04 20:30:00'));
        $this->assertStringContainsString('04/09/2026', ui_task_schedule(['schedule_type' => 'once', 'start_date' => '2026-09-04']));
    }

    public function testStoredEnumValuesHaveMalayDisplayLabels(): void
    {
        $this->assertSame('Menunggu', ui_label('redemption', 'pending'));
        $this->assertSame('Diluluskan', ui_label('redemption', 'approved'));
        $this->assertSame('Ditolak', ui_label('redemption', 'rejected'));
        $this->assertSame('Dibatalkan', ui_label('redemption', 'cancelled'));
        $this->assertSame('Selesai', ui_label('redemption', 'completed'));
        $this->assertSame('1 kali seminggu', ui_reward_limit('weekly'));
        $this->assertSame('Pelarasan', ui_label('transaction', 'adjustment'));
        $this->assertSame('Pembatalan', ui_label('transaction', 'reversal'));
        $this->assertSame('Harian', ui_label('period', 'daily'));
        $this->assertSame('Mingguan', ui_label('period', 'weekly'));
        $this->assertSame('Bulanan', ui_label('period', 'monthly'));
        $this->assertSame('Telefon', ui_label('device', 'mobile'));
        $this->assertSame('Pelayar', ui_label('device', 'browser'));
        $this->assertSame('Tidak diketahui', ui_label('device', null));
    }

    public function testValidationUsesMalayMessagesAndFieldLabelsWithoutChangingErrorKeys(): void
    {
        $validator = \Config\Services::validation(null, false);
        $validator->setRules(['name' => 'required', 'email' => 'valid_email', 'start_time' => 'regex_match[/^\\d{2}:\\d{2}$/]']);
        $this->assertFalse($validator->run(['name' => '', 'email' => 'invalid', 'start_time' => 'oops']));
        $this->assertSame('Nama wajib diisi.', $validator->getError('name'));
        $this->assertSame('E-mel mesti alamat e-mel yang sah.', $validator->getError('email'));
        $this->assertSame('Format Masa mula tidak sah.', $validator->getError('start_time'));
        $this->assertSame(['name', 'email', 'start_time'], array_keys($validator->getErrors()));
    }

    public function testMalayValidationCoversEveryFrameworkRule(): void
    {
        $english = require SYSTEMPATH . 'Language/en/Validation.php';
        $malay = require APPPATH . 'Language/ms/Validation.php';
        $this->assertSame([], array_diff(array_keys($english), array_keys($malay)));
        foreach ($malay as $key => $message) {
            $this->assertNotSame($english[$key], $message);
        }
    }

    public function testLegacyDescriptionsTranslateOnlyGeneratedPrefixes(): void
    {
        $this->assertSame('Tugasan selesai: Brush Teeth', ui_point_description(['type' => 'task', 'description' => 'Task selesai: Brush Teeth']));
        $this->assertSame('Ganjaran: Movie Night', ui_point_description(['type' => 'reward', 'description' => 'Reward: Movie Night']));
        $this->assertSame('Batal: Tugasan selesai: Brush Teeth', ui_point_description(['type' => 'reversal', 'description' => 'Undo: Task selesai: Brush Teeth']));
        $this->assertSame('Batal: Tugasan selesai: Brush Teeth', ui_point_description(['type' => 'reversal', 'description' => 'Batal: Task selesai: Brush Teeth']));
        $this->assertSame('Reward: Good work', ui_point_description(['type' => 'adjustment', 'description' => 'Reward: Good work']));
        $this->assertSame('My own English description', ui_point_description(['type' => 'bonus', 'description' => 'My own English description']));
        $this->assertSame('Pelarasan', ui_point_description(['type' => 'adjustment', 'description' => null]));
    }

    public function testPublicAndErrorMessagesAreMalay(): void
    {
        $offline = file_get_contents(ROOTPATH . 'public/offline.html');
        $this->assertStringContainsString('Anda sedang luar talian', $offline);
        $this->assertStringNotContainsString('authenticated', $offline);
        $this->assertSame('404 - Halaman Tidak Ditemui', lang('Errors.pageNotFound'));
        $this->assertStringContainsString('Muat semula halaman', lang('Security.disallowedAction'));
        $manifest = json_decode(file_get_contents(ROOTPATH . 'public/manifest.webmanifest'), true);
        $this->assertSame('ms', $manifest['lang']);
        $this->assertSame('Rutin harian, mata dan ganjaran untuk keluarga.', $manifest['description']);
    }
}
