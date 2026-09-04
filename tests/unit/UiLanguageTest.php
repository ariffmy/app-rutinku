<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class UiLanguageTest extends CIUnitTestCase
{
    public function testChildAssetsHaveContentVersions(): void
    {
        $path = 'assets/js/child-today.js';
        $this->assertStringEndsWith('?v=' . substr(hash_file('sha256', FCPATH . $path), 0, 12), ui_asset_url($path));
        $view = file_get_contents(APPPATH . 'Views/child/today.php');
        $this->assertStringContainsString("ui_asset_url('assets/js/child-today.js')", $view);
        $this->assertStringNotContainsString('data-filter-clock', $view);
        $this->assertStringNotContainsString('Tugasan dalam satu jam', $view);
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
        $this->assertSame('Menunggu kelulusan', ui_label('redemption', 'pending'));
        $this->assertSame('Diluluskan', ui_label('redemption', 'approved'));
        $this->assertSame('Ditolak', ui_label('redemption', 'rejected'));
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
