<?php

namespace Tests\Unit;

use App\Services\TaskScheduleService;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use InvalidArgumentException;

final class TaskScheduleTest extends CIUnitTestCase
{
    public function testRecurrenceAndLocalDateBoundaries(): void
    {
        $service = new TaskScheduleService();
        foreach ([
            ['inherit', null, null, '2026-09-07', true],
            ['inherit', null, null, '2026-09-08', false],
            ['once', '2026-09-03', null, '2026-09-03', true],
            ['once', '2026-09-03', null, '2026-09-04', false],
            ['daily', '2026-09-03', null, '2026-09-02', false],
            ['daily', '2026-09-03', null, '2026-09-04', true],
            ['weekly', '2026-09-03', '2,4', '2026-09-08', true],
            ['weekly', '2026-09-03', '2,4', '2026-09-09', false],
            ['monthly', '2026-01-31', null, '2026-02-28', false],
            ['monthly', '2026-01-31', null, '2026-03-31', true],
            ['monthly', '2024-02-29', null, '2025-02-28', false],
        ] as [$type, $start, $days, $at, $expected]) {
            $this->assertSame($expected, $service->isScheduled(['schedule_type' => $type, 'start_date' => $start, 'repeat_days' => $days], new DateTimeImmutable($at . ' 09:00:00+08:00'), $type === 'inherit' ? [1] : [1, 2, 3, 4, 5, 6, 7]), "$type $at");
        }
        $this->assertTrue($service->isScheduled(['schedule_type' => 'once', 'start_date' => '2026-09-03'], new DateTimeImmutable('2026-09-02 16:30:00+00:00'), [4]));
    }

    public function testEveryFrequencyRespectsRoutineDays(): void
    {
        $service = new TaskScheduleService();
        foreach (['inherit', 'daily', 'once', 'weekly', 'monthly'] as $type) {
            $task = ['schedule_type' => $type, 'start_date' => '2026-09-05', 'repeat_days' => '6'];
            $at = new DateTimeImmutable('2026-09-05 08:00:00+08:00');
            $this->assertFalse($service->isScheduled($task, $at, [1, 2, 3, 4, 5]), $type);
            $this->assertTrue($service->isScheduled($task, $at, [6]), $type);
        }
    }

    public function testInvalidSchedulesAreRejected(): void
    {
        foreach ([['duration_minutes' => 0], ['duration_minutes' => 1441], ['schedule_type' => 'other'], ['schedule_type' => 'once', 'start_date' => '2026-02-30'], ['schedule_type' => 'daily'], ['schedule_type' => 'weekly', 'start_date' => '2026-09-03', 'repeat_days' => []], ['schedule_type' => 'weekly', 'start_date' => '2026-09-03', 'repeat_days' => [8]]] as $payload) {
            try {
                (new TaskScheduleService())->payload($payload);
                $this->fail('Invalid schedule accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTimeSummaryAcrossMidnight(): void
    {
        helper('ui');
        $this->assertSame('11:45 malam – 12:15 tengah malam (esok)', ui_task_time(['task_time' => '23:45:00', 'duration_minutes' => 30]));
    }

    public function testMalayTimeLabelsAndTimestamps(): void
    {
        helper('ui');
        foreach (['00:00' => '12 tengah malam', '00:15' => '12:15 tengah malam', '01:00' => '1 pagi', '08:00' => '8 pagi', '11:59' => '11:59 pagi', '12:00' => '12 tengah hari', '13:30' => '1:30 tengah hari', '14:30' => '2:30 petang', '18:59' => '6:59 petang', '19:00' => '7 malam', '20:05:00' => '8:05 malam'] as $input => $expected) {
            $this->assertSame($expected, ui_time($input));
        }
        $this->assertSame('03/09/2026, 8 pagi', ui_datetime('2026-09-03 08:00:00'));
        $this->assertSame('—', ui_datetime(null));
        $this->assertSame('—', ui_time('25:00'));
    }
}
