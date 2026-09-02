<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Services\TaskCompletionService;
use App\Services\PointService;
use App\Services\StreakService;
use CodeIgniter\I18n\Time;
use Config\Services;

class ProgressController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();

        $points = new PointService();

        return view('child/progress', [
            'title' => 'Progress',
            'child' => $context->child(),
            'family' => $context->family(),
            'progress' => (new TaskCompletionService())->getTodayProgress(
                (int) $context->child()->id,
                Time::now(app_timezone()),
            ),
            'balance' => $points->getBalance((int) $context->child()->id),
            'pointHistory' => $points->getHistory((int) $context->child()->id, 20),
            'currentStreak' => (new StreakService())->currentStreak(
                (int) $context->child()->id,
                Time::now(app_timezone()),
            ),
            'activeNav' => 'progress',
        ]);
    }
}
