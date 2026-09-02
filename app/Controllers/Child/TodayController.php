<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Services\TaskCompletionService;
use App\Services\PointService;
use App\Services\StreakService;
use CodeIgniter\I18n\Time;
use Config\Services;

class TodayController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();
        $schedule = (new TaskCompletionService())->getTodayProgress(
            (int) $context->child()->id,
            Time::now(app_timezone()),
        );

        return view('child/today', [
            'title' => 'Today',
            'child' => $context->child(),
            'family' => $context->family(),
            'schedule' => $schedule,
            'balance' => (new PointService())->getBalance((int) $context->child()->id),
            'currentStreak' => (new StreakService())->currentStreak(
                (int) $context->child()->id,
                Time::now(app_timezone()),
            ),
            'activeNav' => 'today',
        ]);
    }
}
